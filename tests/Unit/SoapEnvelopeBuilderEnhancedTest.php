<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Unit;

use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\Incoterm;
use VeryCodeCom\Suus\Enum\OrderType;
use VeryCodeCom\Suus\Enum\PackageSymbol;
use VeryCodeCom\Suus\Internal\Soap\SoapEnvelopeBuilder;
use VeryCodeCom\Suus\Service\CodService;
use VeryCodeCom\Suus\Service\InsuranceService;
use VeryCodeCom\Suus\Service\LiftService;
use VeryCodeCom\Suus\Service\SmsNotificationService;
use VeryCodeCom\Suus\SuusConfig;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the enhanced SoapEnvelopeBuilder features:
 *  - Fixed additionalServices XML (complex type, not string)
 *  - Package returnable/stackable fields
 *  - Field length serialization
 *  - getDeliveryPoints envelope
 */
final class SoapEnvelopeBuilderEnhancedTest extends TestCase
{
    private SoapEnvelopeBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new SoapEnvelopeBuilder(SuusConfig::sandbox('user', 'pass'));
    }

    // -- Helper --------------------------------------------------------

    private function makeOrder(array $additionalServices = [], array $packages = []): ShipmentOrder
    {
        return new ShipmentOrder(
            reference:          'ORDER-TEST',
            sender:             new Address('Sender Co', 'Lipowa', '1', '30-001', 'Kraków', 'PL', phone: '+48600111222'),
            receiver:           new Address('Emp GmbH', 'Berliner Str.', '10', '10117', 'Berlin', 'DE', phone: '+4930999888'),
            packages:           $packages ?: [new Package(PackageSymbol::KAR, weightKg: 10.0)],
            incoterms:          Incoterm::DAP,
            loadingDate:        new \DateTimeImmutable('2025-09-15'),
            additionalServices: $additionalServices,
        );
    }

    private function build(ShipmentOrder $order): string
    {
        return $this->builder->buildAddOrder($order, '2025-09-15', '2025-09-18');
    }

    // -- Additional services: element name -----------------------------

    public function testAdditionalServiceUsesCorrectElementName(): void
    {
        $xml = $this->build($this->makeOrder([new CodService(500.0)]));

        // WSDL: element must be <additionalService>, NOT <service>
        $this->assertStringContainsString('<additionalService ', $xml);
        $this->assertStringNotContainsString('<service xsi:type="xsd:string">', $xml);
    }

    public function testAdditionalServiceUsesComplexXsiType(): void
    {
        $xml = $this->build($this->makeOrder([new CodService(500.0)]));
        $this->assertStringContainsString('xsi:type="cw:AdditionalService"', $xml);
    }

    public function testAdditionalServicesArrayTypeAttribute(): void
    {
        $xml = $this->build($this->makeOrder([new CodService(500.0)]));
        $this->assertStringContainsString('SOAP-ENC:arrayType="cw:AdditionalService[1]"', $xml);
    }

    public function testAdditionalServicesArrayTypeCountTwo(): void
    {
        $xml = $this->build($this->makeOrder([new CodService(500.0), new SmsNotificationService()]));
        $this->assertStringContainsString('SOAP-ENC:arrayType="cw:AdditionalService[2]"', $xml);
    }

    // -- COD service XML -----------------------------------------------

    public function testCodServiceSymbolInXml(): void
    {
        $xml = $this->build($this->makeOrder([new CodService(500.0)]));
        $this->assertStringContainsString('<symbol xsi:type="xsd:string">RohligCOD</symbol>', $xml);
    }

    public function testCodServiceAmountAsDecimalInXml(): void
    {
        $xml = $this->build($this->makeOrder([new CodService(500.0)]));
        $this->assertStringContainsString('<decimal1 xsi:type="xsd:decimal">500.00</decimal1>', $xml);
    }

    public function testCodServiceCurrencyAsStringInXml(): void
    {
        $xml = $this->build($this->makeOrder([new CodService(500.0)]));
        $this->assertStringContainsString('<varchar1 xsi:type="xsd:string">PLN</varchar1>', $xml);
    }

    // -- Insurance service XML -----------------------------------------

    public function testInsuranceServiceSymbolInXml(): void
    {
        $xml = $this->build($this->makeOrder([new InsuranceService(1000.0)]));
        $this->assertStringContainsString('<symbol xsi:type="xsd:string">RohligUbezpieczenie3</symbol>', $xml);
    }

    public function testInsuranceServiceBoolFieldsAsBooleanInXml(): void
    {
        $xml = $this->build($this->makeOrder([new InsuranceService(1000.0, strikeClause: true)]));
        $this->assertStringContainsString('<bool1 xsi:type="xsd:boolean">true</bool1>', $xml);
        $this->assertStringContainsString('<bool2 xsi:type="xsd:boolean">false</bool2>', $xml);
    }

    public function testInsuranceServiceInt01AsStringInXml(): void
    {
        // int01 must be xsd:string per WSDL despite the name; it is the mandatory
        // "goods not excluded" declaration, always emitted as "1".
        $xml = $this->build($this->makeOrder([new InsuranceService(500.0)]));
        $this->assertStringContainsString('<int01 xsi:type="xsd:string">1</int01>', $xml);
    }

    // -- Lift service XML ----------------------------------------------

    public function testLiftServiceBool1TrueInXml(): void
    {
        $xml = $this->build($this->makeOrder([new LiftService()]));
        $this->assertStringContainsString('<symbol xsi:type="xsd:string">RohligWinda</symbol>', $xml);
        $this->assertStringContainsString('<bool1 xsi:type="xsd:boolean">true</bool1>', $xml);
    }

    // -- Plain string service codes ------------------------------------

    public function testPlainStringServiceCodeIsAccepted(): void
    {
        $xml = $this->build($this->makeOrder(['StdAwizacjaSms']));

        // Must produce <additionalService> (not <service>) with <symbol>
        $this->assertStringContainsString('<additionalService ', $xml);
        $this->assertStringContainsString('<symbol xsi:type="xsd:string">StdAwizacjaSms</symbol>', $xml);
        // and never the old, broken format
        $this->assertStringNotContainsString('<service xsi:type="xsd:string">', $xml);
    }

    // -- Package returnable / stackable --------------------------------

    public function testPackageReturnableAppearsInXml(): void
    {
        $pkg = new Package(PackageSymbol::EUR, weightKg: 15.0, returnable: 2);
        $xml = $this->build($this->makeOrder([], [$pkg]));
        $this->assertStringContainsString('<returnable xsi:type="xsd:integer">2</returnable>', $xml);
    }

    public function testPackageStackableAppearsInXml(): void
    {
        $pkg = new Package(PackageSymbol::EUR, weightKg: 15.0, returnable: 2, stackable: 1);
        $xml = $this->build($this->makeOrder([], [$pkg]));
        $this->assertStringContainsString('<stackable xsi:type="xsd:integer">1</stackable>', $xml);
    }

    public function testPackageReturnableOmittedWhenNull(): void
    {
        $pkg = new Package(PackageSymbol::KAR, weightKg: 5.0);
        $xml = $this->build($this->makeOrder([], [$pkg]));
        $this->assertStringNotContainsString('<returnable', $xml);
        $this->assertStringNotContainsString('<stackable', $xml);
    }

    // -- Header: costGroup / freight / currency ------------------------

    public function testCostGroupAppearsInHeaderXml(): void
    {
        $order = new ShipmentOrder(
            reference:  'CG-1',
            sender:     new Address('Sender Co', 'Lipowa', '1', '30-001', 'Kraków', 'PL', phone: '+48600111222'),
            receiver:   new Address('Recv Sp', 'Testowa', '2', '00-950', 'Warszawa', 'PL', phone: '+48600333444'),
            packages:   [new Package(PackageSymbol::KAR, weightKg: 10.0)],
            costGroup:  '/SI',
        );
        $xml = $this->build($order);
        $this->assertStringContainsString('<costGroup xsi:type="xsd:string">/SI</costGroup>', $xml);
    }

    public function testFreightAndCurrencyAppearForInternationalOrder(): void
    {
        $order = new ShipmentOrder(
            reference:  'FR-1',
            sender:     new Address('Sender Co', 'Lipowa', '1', '30-001', 'Kraków', 'PL', phone: '+48600111222'),
            receiver:   new Address('Emp GmbH', 'Berliner Str.', '10', '10117', 'Berlin', 'DE', phone: '+4930999888'),
            packages:   [new Package(PackageSymbol::KAR, weightKg: 10.0)],
            incoterms:  Incoterm::DAP,
            orderType:  OrderType::B2B,
            freight:    '150.00',
            currency:   'EUR',
        );
        $xml = $this->build($order);
        $this->assertStringContainsString('<freight xsi:type="xsd:string">150.00</freight>', $xml);
        $this->assertStringContainsString('<currency xsi:type="xsd:string">EUR</currency>', $xml);
    }

    public function testFreightOmittedForDomesticOrder(): void
    {
        $order = new ShipmentOrder(
            reference:  'FR-DOM',
            sender:     new Address('Sender Co', 'Lipowa', '1', '30-001', 'Kraków', 'PL', phone: '+48600111222'),
            receiver:   new Address('Recv Sp', 'Testowa', '2', '00-950', 'Warszawa', 'PL', phone: '+48600333444'),
            packages:   [new Package(PackageSymbol::KAR, weightKg: 10.0)],
            freight:    '150.00',
            currency:   'EUR',
        );
        $xml = $this->build($order);
        $this->assertStringNotContainsString('<freight', $xml);
        $this->assertStringNotContainsString('<currency', $xml);
    }

    // -- getDeliveryPoints envelope ------------------------------------

    public function testGetDeliveryPointsEnvelopeHasCorrectMethod(): void
    {
        $xml = $this->builder->buildGetDeliveryPoints();
        $this->assertStringContainsString('<cw:getDeliveryPoints>', $xml);
    }

    public function testGetDeliveryPointsEnvelopeHasAuth(): void
    {
        $xml = $this->builder->buildGetDeliveryPoints();
        $this->assertStringContainsString('<auth xsi:type="cw:Auth">', $xml);
    }
}
