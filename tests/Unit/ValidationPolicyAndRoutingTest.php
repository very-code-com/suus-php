<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Unit;

use VeryCodeCom\Suus\Calendar\PolishCalendar;
use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\Incoterm;
use VeryCodeCom\Suus\Enum\OrderType;
use VeryCodeCom\Suus\Enum\PackageSymbol;
use VeryCodeCom\Suus\Exception\SuusValidationException;
use VeryCodeCom\Suus\Internal\Soap\SoapEnvelopeBuilder;
use VeryCodeCom\Suus\Internal\Validator\ShipmentValidator;
use VeryCodeCom\Suus\Routing\CallableRouteClassifier;
use VeryCodeCom\Suus\Routing\DefaultRouteClassifier;
use VeryCodeCom\Suus\Service\DocumentReturnDomesticService;
use VeryCodeCom\Suus\Service\DocumentReturnInternationalService;
use VeryCodeCom\Suus\Service\InsideDeliveryService;
use VeryCodeCom\Suus\Service\SmsNotificationService;
use VeryCodeCom\Suus\SuusClient;
use VeryCodeCom\Suus\SuusConfig;
use VeryCodeCom\Suus\Transport\TransportInterface;
use VeryCodeCom\Suus\Transport\TransportResponse;
use VeryCodeCom\Suus\Validation\ValidationCode;
use VeryCodeCom\Suus\Validation\ValidationError;
use VeryCodeCom\Suus\Validation\ValidationPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Covers the flexibility features: ValidationPolicy, RouteClassifier, the
 * international restrictions (B2C services, returnable/stackable packaging)
 * and typed ValidationError.
 */
final class ValidationPolicyAndRoutingTest extends TestCase
{
    private ShipmentValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ShipmentValidator(new PolishCalendar());
    }

    /** @param array<string, mixed> $overrides */
    private function makeOrder(array $overrides = []): ShipmentOrder
    {
        return new ShipmentOrder(
            reference:          $overrides['reference']          ?? 'ORDER-001',
            sender:             $overrides['sender']             ?? new Address('Sender', 'St', '1', '00-001', 'Warsaw', 'PL', phone: '+48600000'),
            receiver:           $overrides['receiver']           ?? new Address('Recv', 'St', '2', '10115', 'Berlin', 'DE', phone: '+4930000'),
            packages:           $overrides['packages']           ?? [new Package(PackageSymbol::KAR, weightKg: 10.0)],
            incoterms:          array_key_exists('incoterms', $overrides) ? $overrides['incoterms'] : Incoterm::DAP,
            orderType:          $overrides['orderType']          ?? OrderType::B2B,
            additionalServices: $overrides['additionalServices'] ?? [],
            loadingDate:        $overrides['loadingDate']        ?? (new PolishCalendar())->addBusinessDays(new \DateTimeImmutable('today'), 5),
        );
    }

    /**
     * @param ValidationError[] $errors
     */
    private function hasCode(array $errors, string $code): bool
    {
        return (bool) array_filter($errors, fn(ValidationError $e): bool => $e->code === $code);
    }

    // ---------------------------------------------------------------
    // International packaging restrictions (PRJ00372 / PRJ00373)
    // ---------------------------------------------------------------

    public function testInternationalReturnablePackagingIsRejected(): void
    {
        $order  = $this->makeOrder(['packages' => [new Package(PackageSymbol::EUR, weightKg: 50.0, heightCm: 30.0, returnable: 1, stackable: 1)]]);
        $errors = $this->validator->validate($order);

        $this->assertTrue($this->hasCode($errors, ValidationCode::RETURNABLE_INTERNATIONAL), 'expected PRJ00372');
        $this->assertTrue($this->hasCode($errors, ValidationCode::STACKABLE_INTERNATIONAL), 'expected PRJ00373');
    }

    public function testDomesticReturnablePackagingIsAllowed(): void
    {
        $order = $this->makeOrder([
            'receiver'  => new Address('Recv', 'St', '2', '30-002', 'Kraków', 'PL', phone: '+48500000'),
            'incoterms' => null,
            'packages'  => [new Package(PackageSymbol::EUR, weightKg: 50.0, heightCm: 30.0, returnable: 1, stackable: 1)],
        ]);
        $errors = $this->validator->validate($order);

        $this->assertFalse($this->hasCode($errors, ValidationCode::RETURNABLE_INTERNATIONAL));
        $this->assertFalse($this->hasCode($errors, ValidationCode::STACKABLE_INTERNATIONAL));
    }

    public function testRelaxedPolicyAllowsInternationalReturnablePackaging(): void
    {
        $order  = $this->makeOrder(['packages' => [new Package(PackageSymbol::EUR, weightKg: 50.0, heightCm: 30.0, returnable: 1, stackable: 1)]]);
        $errors = $this->validator->validate($order, policy: ValidationPolicy::relaxed());

        $this->assertFalse($this->hasCode($errors, ValidationCode::RETURNABLE_INTERNATIONAL));
        $this->assertFalse($this->hasCode($errors, ValidationCode::STACKABLE_INTERNATIONAL));
    }

    // ---------------------------------------------------------------
    // International B2C service restrictions
    // ---------------------------------------------------------------

    public function testInternationalB2cServicesAreRejected(): void
    {
        $order = $this->makeOrder(['additionalServices' => [new SmsNotificationService(), new InsideDeliveryService()]]);
        $errors = $this->validator->validate($order);

        $b2cErrors = array_filter($errors, fn(ValidationError $e): bool => $e->code === ValidationCode::DOMESTIC_ONLY_SERVICE);
        $this->assertCount(2, $b2cErrors, 'both SMS and inside-delivery should be rejected internationally');
    }

    public function testInternationalLegacyStringB2cServiceIsRejected(): void
    {
        $order  = $this->makeOrder(['additionalServices' => ['StdAwizacjaSms']]);
        $errors = $this->validator->validate($order);

        $this->assertTrue($this->hasCode($errors, ValidationCode::DOMESTIC_ONLY_SERVICE));
    }

    public function testRelaxedPolicyAllowsInternationalB2cServices(): void
    {
        $order  = $this->makeOrder(['additionalServices' => [new SmsNotificationService()]]);
        $errors = $this->validator->validate($order, policy: ValidationPolicy::relaxed());

        $this->assertFalse($this->hasCode($errors, ValidationCode::DOMESTIC_ONLY_SERVICE));
    }

    // ---------------------------------------------------------------
    // Document-return services: route restrictions in both directions
    // ---------------------------------------------------------------

    private function domesticOrder(array $additionalServices): ShipmentOrder
    {
        return $this->makeOrder([
            'receiver'           => new Address('Recv', 'St', '2', '30-002', 'Kraków', 'PL', phone: '+48500000'),
            'incoterms'          => null,
            'additionalServices' => $additionalServices,
        ]);
    }

    public function testDomesticOnlyDocumentReturnRejectedOnInternational(): void
    {
        $order  = $this->makeOrder(['additionalServices' => [new DocumentReturnDomesticService('FV/1/2026')]]);
        $errors = $this->validator->validate($order);

        $this->assertTrue($this->hasCode($errors, ValidationCode::DOMESTIC_ONLY_SERVICE));
    }

    public function testDomesticOnlyDocumentReturnAllowedOnDomestic(): void
    {
        $errors = $this->validator->validate($this->domesticOrder([new DocumentReturnDomesticService('FV/1/2026')]));

        $this->assertFalse($this->hasCode($errors, ValidationCode::DOMESTIC_ONLY_SERVICE));
        $this->assertFalse($this->hasCode($errors, ValidationCode::INTERNATIONAL_ONLY_SERVICE));
    }

    public function testInternationalOnlyDocumentReturnRejectedOnDomestic(): void
    {
        $errors = $this->validator->validate($this->domesticOrder([new DocumentReturnInternationalService('FV/2/2026')]));

        $this->assertTrue($this->hasCode($errors, ValidationCode::INTERNATIONAL_ONLY_SERVICE));
    }

    public function testInternationalOnlyDocumentReturnAllowedOnInternational(): void
    {
        $order  = $this->makeOrder(['additionalServices' => [new DocumentReturnInternationalService('FV/2/2026')]]);
        $errors = $this->validator->validate($order);

        $this->assertFalse($this->hasCode($errors, ValidationCode::INTERNATIONAL_ONLY_SERVICE));
        $this->assertFalse($this->hasCode($errors, ValidationCode::DOMESTIC_ONLY_SERVICE));
    }

    public function testRelaxedPolicyAllowsDocumentReturnOnEitherRoute(): void
    {
        $intl = $this->validator->validate(
            $this->makeOrder(['additionalServices' => [new DocumentReturnDomesticService('FV/1/2026')]]),
            policy: ValidationPolicy::relaxed(),
        );
        $dom = $this->validator->validate(
            $this->domesticOrder([new DocumentReturnInternationalService('FV/2/2026')]),
            policy: ValidationPolicy::relaxed(),
        );

        $this->assertFalse($this->hasCode($intl, ValidationCode::DOMESTIC_ONLY_SERVICE));
        $this->assertFalse($this->hasCode($dom, ValidationCode::INTERNATIONAL_ONLY_SERVICE));
    }

    // ---------------------------------------------------------------
    // Document-return service DTOs
    // ---------------------------------------------------------------

    public function testDocumentReturnServiceSymbolsAndFields(): void
    {
        $kr = new DocumentReturnDomesticService(
            documentNumber: 'FV/1/2026',
            tag:            DocumentReturnDomesticService::TAG_RETURN,
            documentType:   DocumentReturnDomesticService::DOC_INVOICE,
            description:    'zwrot faktury',
        );
        $gg = new DocumentReturnInternationalService('CMR-9');

        $this->assertSame('StdDokumentyZwrotneINiezwrotneGrid2', $kr->getSymbol());
        $this->assertSame('StdDokumentyZwrotneINiezwrotneGrid3', $gg->getSymbol());

        $fields = $kr->getSoapFields();
        $this->assertSame('1', $fields['int01']);
        $this->assertSame('FV/1/2026', $fields['varchar1']);
        $this->assertSame('DZ', $fields['varchar2']);
        $this->assertSame('FK', $fields['varchar3']);
        $this->assertSame('zwrot faktury', $fields['varchar4']);

        // description omitted when empty
        $this->assertArrayNotHasKey('varchar4', $gg->getSoapFields());
    }

    public function testDocumentReturnServiceRejectsInvalidTag(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DocumentReturnDomesticService('FV/1', tag: 'ZZ');
    }

    public function testDocumentReturnServiceRejectsInvalidDocumentType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DocumentReturnDomesticService('FV/1', documentType: 'XXX');
    }

    public function testDocumentReturnServiceRejectsEmptyDocumentNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DocumentReturnInternationalService('   ');
    }

    // ---------------------------------------------------------------
    // International B2B enforcement is policy-gated
    // ---------------------------------------------------------------

    public function testStrictPolicyRejectsInternationalB2c(): void
    {
        $errors = $this->validator->validate($this->makeOrder(['orderType' => OrderType::B2C]));
        $this->assertTrue($this->hasCode($errors, ValidationCode::INTERNATIONAL_B2B_ONLY));
    }

    public function testRelaxedPolicyAllowsInternationalB2c(): void
    {
        $errors = $this->validator->validate($this->makeOrder(['orderType' => OrderType::B2C]), policy: ValidationPolicy::relaxed());
        $this->assertFalse($this->hasCode($errors, ValidationCode::INTERNATIONAL_B2B_ONLY));
    }

    // ---------------------------------------------------------------
    // Route classifier: DE->DE treated as domestic
    // ---------------------------------------------------------------

    private function deToDeB2cOrder(): ShipmentOrder
    {
        return $this->makeOrder([
            'sender'    => new Address('Versand', 'Str', '1', '10115', 'Berlin', 'DE', phone: '+4930111'),
            'receiver'  => new Address('Kunde', 'Weg', '2', '80331', 'München', 'DE', phone: '+4989222'),
            'incoterms' => null,
            'orderType' => OrderType::B2C,
        ]);
    }

    public function testDefaultClassifierTreatsDeToDeAsInternational(): void
    {
        // Without an override, DE->DE is international -> incoterms required + B2B required.
        $errors = $this->validator->validate($this->deToDeB2cOrder());

        $this->assertTrue($this->hasCode($errors, ValidationCode::INCOTERMS_REQUIRED));
        $this->assertTrue($this->hasCode($errors, ValidationCode::INTERNATIONAL_B2B_ONLY));
    }

    public function testCustomClassifierMakesDeToDeDomestic(): void
    {
        $classifier = new CallableRouteClassifier(
            fn(ShipmentOrder $o): bool =>
                ($o->sender->getCountryCode() === 'DE' && $o->receiver->getCountryCode() === 'DE')
                    ? false
                    : $o->isInternational(),
        );

        $errors = $this->validator->validate($this->deToDeB2cOrder(), classifier: $classifier);

        $this->assertFalse($this->hasCode($errors, ValidationCode::INCOTERMS_REQUIRED));
        $this->assertFalse($this->hasCode($errors, ValidationCode::INTERNATIONAL_B2B_ONLY));
        $this->assertSame([], $errors, 'a reclassified DE->DE B2C order should be fully valid');
    }

    public function testBuilderOmitsShipperConsigneeWhenClassifierSaysDomestic(): void
    {
        $domesticClassifier = new CallableRouteClassifier(fn(ShipmentOrder $o): bool => false);
        $builder = new SoapEnvelopeBuilder(SuusConfig::sandbox('u', 'p'), $domesticClassifier);

        $xml = $builder->buildAddOrder($this->deToDeB2cOrder(), '2099-12-01', '2099-12-04');

        $this->assertStringNotContainsString('<shipper', $xml);
        $this->assertStringNotContainsString('<consignee', $xml);
    }

    public function testBuilderEmitsShipperConsigneeWithDefaultClassifier(): void
    {
        $builder = new SoapEnvelopeBuilder(SuusConfig::sandbox('u', 'p'), new DefaultRouteClassifier());
        $order   = $this->makeOrder(); // PL -> DE, international by default

        $xml = $builder->buildAddOrder($order, '2099-12-01', '2099-12-04');

        $this->assertStringContainsString('<shipper', $xml);
        $this->assertStringContainsString('<consignee', $xml);
    }

    // ---------------------------------------------------------------
    // SuusClient wiring: policy + classifier + public validate()
    // ---------------------------------------------------------------

    private function neverSendTransport(): TransportInterface
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->never())->method('send');
        return $transport;
    }

    private function successTransport(): TransportInterface
    {
        $xml = (string) file_get_contents(__DIR__ . '/../Fixtures/add_order_success.xml');
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('send')->willReturn(new TransportResponse(200, $xml));
        return $transport;
    }

    public function testPublicValidateReturnsTypedErrorsWithoutNetwork(): void
    {
        $client = new SuusClient(SuusConfig::sandbox('u', 'p'), $this->neverSendTransport());

        $errors = $client->validate($this->makeOrder(['incoterms' => null]));

        $this->assertContainsOnlyInstancesOf(ValidationError::class, $errors);
        $this->assertTrue($this->hasCode($errors, ValidationCode::INCOTERMS_REQUIRED));
    }

    public function testPublicValidateReturnsEmptyForValidOrder(): void
    {
        $client = new SuusClient(SuusConfig::sandbox('u', 'p'), $this->neverSendTransport());
        $this->assertSame([], $client->validate($this->makeOrder()));
    }

    public function testClientWithRelaxedPolicyAllowsInternationalB2c(): void
    {
        $client = new SuusClient(
            SuusConfig::sandbox('u', 'p'),
            $this->successTransport(),
            null,
            null,
            ValidationPolicy::relaxed(),
        );

        // International B2C would normally fail strict validation; relaxed lets it through to the API.
        $result = $client->createShipment($this->makeOrder(['orderType' => OrderType::B2C]));
        $this->assertSame('OPLKRI2600895', $result->shipmentNo);
    }

    public function testClientWithCustomClassifierValidatesDeToDeAsDomestic(): void
    {
        $client = new SuusClient(
            SuusConfig::sandbox('u', 'p'),
            $this->neverSendTransport(),
            null,
            null,
            null,
            new CallableRouteClassifier(fn(ShipmentOrder $o): bool => false),
        );

        $this->assertSame([], $client->validate($this->deToDeB2cOrder()));
    }

    // ---------------------------------------------------------------
    // Typed errors: ValidationError + SuusValidationException
    // ---------------------------------------------------------------

    public function testValidationErrorIsStringableWithFieldAndCode(): void
    {
        $err = new ValidationError('boom', 'packages[0].weightKg', ValidationCode::PACKAGE_WEIGHT_EXCEEDED);
        $this->assertSame('boom', (string) $err);
        $this->assertSame('packages[0].weightKg', $err->field);
        $this->assertSame('PRJ00351', $err->code);
    }

    public function testValidationExceptionExposesTypedAndStringErrors(): void
    {
        $errors = [new ValidationError('bad incoterms', 'incoterms', ValidationCode::INCOTERMS_REQUIRED)];
        $ex     = new SuusValidationException($errors);

        $this->assertSame($errors, $ex->getValidationErrors());
        $this->assertSame(['bad incoterms'], $ex->getErrors());
        $this->assertStringContainsString('bad incoterms', $ex->getMessage());
    }

    public function testValidationExceptionWrapsBareStringsForBackwardCompat(): void
    {
        $ex = new SuusValidationException(['plain string error']);

        $this->assertSame(['plain string error'], $ex->getErrors());
        $this->assertContainsOnlyInstancesOf(ValidationError::class, $ex->getValidationErrors());
        $this->assertNull($ex->getValidationErrors()[0]->code, 'a wrapped bare string has no code');
    }

    public function testClientThrowsTypedValidationErrorForInternationalReturnable(): void
    {
        $client = new SuusClient(SuusConfig::sandbox('u', 'p'), $this->neverSendTransport());

        try {
            $client->createShipment($this->makeOrder(['packages' => [new Package(PackageSymbol::EUR, weightKg: 50.0, heightCm: 30.0, returnable: 1, stackable: 1)]]));
            $this->fail('Expected SuusValidationException.');
        } catch (SuusValidationException $e) {
            $this->assertTrue($this->hasCode($e->getValidationErrors(), ValidationCode::RETURNABLE_INTERNATIONAL));
        }
    }
}
