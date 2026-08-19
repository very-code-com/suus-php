<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Integration;

use VeryCodeCom\Suus\Calendar\CalendarFactory;
use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\DocumentType;
use VeryCodeCom\Suus\Enum\Incoterm;
use VeryCodeCom\Suus\Enum\OrderType;
use VeryCodeCom\Suus\Enum\PackageSymbol;
use VeryCodeCom\Suus\Enum\ShipmentCategory;
use VeryCodeCom\Suus\Exception\SuusApiException;
use VeryCodeCom\Suus\Service\CodService;
use VeryCodeCom\Suus\Service\EmailNotificationService;
use VeryCodeCom\Suus\Service\InsideDeliveryService;
use VeryCodeCom\Suus\Service\InsuranceService;
use VeryCodeCom\Suus\Service\LiftService;
use VeryCodeCom\Suus\Service\PalletTruckService;
use VeryCodeCom\Suus\Service\SmsNotificationService;
use VeryCodeCom\Suus\SuusClient;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests - make real addOrder calls to the SUUS sandbox.
 *
 * These tests are skipped unless the SUUS_SANDBOX environment variable is set to "1".
 *
 * Usage:
 *   SUUS_SANDBOX=1 SUUS_LOGIN=ws_xxx SUUS_PASSWORD=xxx vendor/bin/phpunit --testsuite integration
 *
 * The two addOrder tests register real sandbox orders. Set SUUS_ALLOW_ORDERS=0 to skip them.
 *
 * Two "maximal" addOrder tests exercise every field the library supports, while
 * honouring the documented SUUS constraints (WS PK 1.0):
 *   - Domestic PL->PL, orderType B2C: full addresses, a returnable+stackable EUR
 *     pallet, and the domestic/B2C service set (SMS awizo, inside delivery, COD,
 *     insurance, e-mail preadvice, lift, pallet truck).
 *   - International DE->DE, orderType B2B: incoterms + category + freight/currency,
 *     and the B2B service set (COD, insurance, e-mail, lift, pallet truck). Per
 *     docs, returnable/stackable and SMS/inside-delivery are not allowed here.
 *
 * The read methods are covered too, against an order the suite creates itself: that is
 * the only way to tell "SUUS cannot find this order" apart from "SUUS could not read the
 * request", since both answer PRJ000001. getEvents is the exception - SUUS registers the
 * first event asynchronously, so that test needs SUUS_EVENTS_SHIPMENT pointing at an
 * order registered a few minutes earlier.
 */
final class SuusClientIntegrationTest extends TestCase
{
    private SuusClient $client;

    protected function setUp(): void
    {
        if (getenv('SUUS_SANDBOX') !== '1') {
            $this->markTestSkipped('Set SUUS_SANDBOX=1 to run integration tests.');
        }

        $login    = getenv('SUUS_LOGIN')    ?: 'ws_yourlogin';
        $password = getenv('SUUS_PASSWORD') ?: '';

        if (empty($password)) {
            $this->markTestSkipped('Set SUUS_PASSWORD env var to run integration tests.');
        }

        $this->client = SuusClient::sandbox($login, $password);
    }

    /**
     * Guard for the tests that call addOrder: those register a real order in the sandbox.
     * Harmless there, never acceptable against production, so it takes a deliberate opt-out
     * to disable (SUUS_ALLOW_ORDERS=0).
     */
    private function skipUnlessOrdersAllowed(): void
    {
        if (getenv('SUUS_ALLOW_ORDERS') === '0') {
            $this->markTestSkipped('SUUS_ALLOW_ORDERS=0: skipping tests that create real orders.');
        }
    }

    public function testRealDomesticAddOrderReturnsShipmentNumber(): void
    {
        $this->skipUnlessOrdersAllowed();

        $ref   = 'PL-' . date('YmdHis') . '-' . random_int(100, 999);
        $order = new ShipmentOrder(
            reference:   $ref,
            sender:      new Address(
                name:          'Testowy Nadawca Sp. z o.o.',
                street:        'Przemysłowa',
                streetNo:      '12',
                postcode:      '30-701',
                city:          'Kraków',
                countryCode:   'PL',
                phone:         '+48123456789',
                mobilePhone:   '+48600100200',
                contactPerson: 'Jan Kowalski',
                email:         'nadawca@example.pl',
            ),
            receiver:    new Address(
                name:          'Testowy Odbiorca Sp. z o.o.',
                street:        'Marszałkowska',
                streetNo:      '100',
                postcode:      '00-026',
                city:          'Warszawa',
                countryCode:   'PL',
                phone:         '+48987654321',
                mobilePhone:   '+48600300400',
                contactPerson: 'Anna Nowak',
                email:         'odbiorca@example.pl',
            ),
            packages:    $this->domesticPackages(),
            loadingDate: $this->loadingDate('PL'),
            unloadingDate: $this->unloadingDate('PL'),
            orderType:   OrderType::B2C,
            descriptionOfGoods: 'Artykuły przemysłowe',
            remarks:     'Prosimy o telefon przed dostawą.',
            additionalServices: $this->domesticServices(),
            costGroup:   '/SI',
        );

        $result = $this->client->createShipment($order);

        $this->assertNotEmpty($result->shipmentNo);
        $this->assertStringStartsWith('OPL', $result->shipmentNo);
        $this->assertSame($ref, $result->reference);
        $this->assertStringContainsString($result->shipmentNo, $result->trackingUrl);

        echo "\n[SUUS Integration] Domestic shipment created: {$result->shipmentNo}\n";
        echo "Tracking URL: {$result->trackingUrl}\n";
    }

    public function testRealInternationalAddOrderReturnsShipmentNumber(): void
    {
        $this->skipUnlessOrdersAllowed();

        $ref   = 'INT-' . date('YmdHis') . '-' . random_int(100, 999);
        $order = new ShipmentOrder(
            reference:   $ref,
            sender:      new Address(
                name:          'Versender GmbH',
                street:        'Musterstraße',
                streetNo:      '1',
                postcode:      '10115',
                city:          'Berlin',
                countryCode:   'DE',
                phone:         '+4930123456',
                mobilePhone:   '+49170111222',
                contactPerson: 'Hans Müller',
                email:         'versand@versender.example',
            ),
            receiver:    new Address(
                name:          'Empfänger GmbH',
                street:        'Hauptstraße',
                streetNo:      '10',
                postcode:      '80331',
                city:          'Munich',
                countryCode:   'DE',
                phone:         '+4989654321',
                mobilePhone:   '+49170333444',
                contactPerson: 'Petra Schmidt',
                email:         'empfang@empfaenger.example',
            ),
            packages:    $this->internationalPackages(),
            loadingDate: $this->loadingDate('DE'),
            unloadingDate: $this->unloadingDate('DE'),
            incoterms:   Incoterm::DAP,
            orderType:   OrderType::B2B,
            category:    ShipmentCategory::DROBNICA,
            descriptionOfGoods: 'Machine parts and accessories',
            remarks:     'Handle with care. Call before delivery.',
            additionalServices: $this->internationalServices(),
            costGroup:   '/SI',
            freight:     '150.00',
            currency:    'EUR',
        );

        $result = $this->client->createShipment($order);

        $this->assertNotEmpty($result->shipmentNo);
        $this->assertStringStartsWith('OPL', $result->shipmentNo);
        $this->assertSame($ref, $result->reference);
        $this->assertStringContainsString($result->shipmentNo, $result->trackingUrl);

        echo "\n[SUUS Integration] International shipment created: {$result->shipmentNo}\n";
        echo "Tracking URL: {$result->trackingUrl}\n";
    }

    /**
     * Read-back round trip: create an order, then fetch its colli numbers and every
     * shipment-level document for it.
     *
     * This is the test whose absence let three malformed request envelopes ship. Each
     * of the read methods answers PRJ000001 ("order not found") when the request is
     * shaped wrong, which is indistinguishable from a genuinely unknown shipment
     * unless the order is one this test just created.
     */
    public function testReadBackColliNumbersAndDocumentsForAFreshOrder(): void
    {
        $this->skipUnlessOrdersAllowed();

        $ref   = 'RT-' . date('YmdHis') . '-' . random_int(100, 999);
        $order = new ShipmentOrder(
            reference: $ref,
            sender:    new Address(
                name: 'Testowy Nadawca Sp. z o.o.', street: 'Przemysłowa', streetNo: '12',
                postcode: '30-701', city: 'Kraków', countryCode: 'PL',
                phone: '+48123456789', contactPerson: 'Jan Kowalski', email: 'nadawca@example.pl',
            ),
            receiver:  new Address(
                name: 'Testowy Odbiorca Sp. z o.o.', street: 'Marszałkowska', streetNo: '100',
                postcode: '00-026', city: 'Warszawa', countryCode: 'PL',
                phone: '+48987654321', contactPerson: 'Anna Nowak', email: 'odbiorca@example.pl',
            ),
            // Three packages, so a wrapper-vs-leaf colli parsing bug cannot hide behind
            // a single-package shipment that happens to come out correct either way.
            packages: [
                new Package(PackageSymbol::KAR, weightKg: 10.0, lengthCm: 40.0, widthCm: 30.0, heightCm: 20.0),
                new Package(PackageSymbol::KAR, weightKg: 12.0, lengthCm: 50.0, widthCm: 40.0, heightCm: 30.0),
                new Package(PackageSymbol::KAR, weightKg:  8.0, lengthCm: 30.0, widthCm: 20.0, heightCm: 15.0),
            ],
            loadingDate:   $this->loadingDate('PL'),
            unloadingDate: $this->unloadingDate('PL'),
            orderType:     OrderType::B2C,
            descriptionOfGoods: 'Artykuły przemysłowe',
        );

        $shipmentNo = $this->client->createShipment($order)->shipmentNo;
        echo "\n[SUUS Integration] Read-back shipment: {$shipmentNo} (ref {$ref})\n";

        // getColliNo: one number per package, each a distinct leaf value.
        $colli = $this->client->getColliNumbers($shipmentNo);
        $this->assertCount(3, $colli);
        $this->assertSame($colli, array_unique($colli), 'colli numbers must be distinct leaves');
        foreach ($colli as $number) {
            $this->assertMatchesRegularExpression('/^[A-Z0-9]+$/', $number);
        }
        // Same set by reference - but SUUS does not guarantee the order between calls,
        // so compare as sets. Never match colli to packages by index.
        $byRef = $this->client->getColliNumbersByReference($ref);
        sort($colli);
        sort($byRef);
        $this->assertSame($colli, $byRef);

        // getDocument: real PDFs, not an error page or an empty payload.
        foreach ([DocumentType::Label, DocumentType::LabelA6, DocumentType::ShippingOrder] as $type) {
            $pdf = $this->client->fetchDocument($shipmentNo, $type);
            $this->assertStringStartsWith('%PDF', $pdf, $type->value);
            $this->assertGreaterThan(1000, strlen($pdf), $type->value);
        }

        // colliNo narrows the label set: one package must be materially smaller
        // than all three, which a request that silently ignored colliNo would not be.
        $allLabels = $this->client->fetchDocument($shipmentNo, DocumentType::Label);
        $oneLabel  = $this->client->fetchDocument($shipmentNo, DocumentType::Label, [$colli[0]]);
        $this->assertStringStartsWith('%PDF', $oneLabel);
        $this->assertLessThan(strlen($allLabels), strlen($oneLabel));
    }

    /**
     * getEvents against an existing sandbox order.
     *
     * SUUS registers the first event (J_CR) asynchronously, a few minutes after
     * addOrder, so this deliberately uses an externally supplied, old-enough order.
     * Two immediate reads must return the same real event time.
     */
    public function testReadBackEventsForExistingOrderHasStableTimestamps(): void
    {
        $shipmentNo = getenv('SUUS_EVENTS_SHIPMENT') ?: '';
        if ($shipmentNo === '') {
            $this->markTestSkipped(
                'Set SUUS_EVENTS_SHIPMENT to a shipment number registered at least a few '
                . 'minutes ago; SUUS records the first event asynchronously.'
            );
        }

        $firstPoll  = $this->client->fetchStatus($shipmentNo);
        $secondPoll = $this->client->fetchStatus($shipmentNo);

        $this->assertNotSame('', $firstPoll->rawLatestCode);
        $this->assertNotEmpty($firstPoll->events);
        $this->assertNotEmpty($secondPoll->events);
        $this->assertNotSame('', $firstPoll->events[0]->description);
        $this->assertNotNull($firstPoll->events[0]->occurredAt);
        $this->assertNotNull($secondPoll->events[0]->occurredAt);
        $this->assertEquals(
            $firstPoll->events[0]->occurredAt,
            $secondPoll->events[0]->occurredAt,
            'The same event must not be assigned the wall-clock time on each poll.',
        );
    }

    /** A read that SUUS rejects must raise, never come back as an empty result. */
    public function testRejectedReadRaisesInsteadOfReturningEmpty(): void
    {
        $unknown = 'OPLKRI9999999';

        foreach (['fetchStatus', 'getColliNumbers', 'fetchDocument'] as $method) {
            try {
                $this->client->{$method}($unknown);
                $this->fail("{$method}() returned normally for an unknown shipment.");
            } catch (SuusApiException $e) {
                $this->assertSame('PRJ000001', $e->returnCode, $method);
            }
        }
    }

    public function testTrackingUrlFormat(): void
    {
        $this->assertSame(
            'https://portal.suus.com/order-details/OPLTEST123',
            $this->client->trackingUrl('OPLTEST123'),
        );
    }

    /** A valid loading date: 5 business days ahead in the sender's country calendar. */
    private function loadingDate(string $country): \DateTimeImmutable
    {
        $calendar = CalendarFactory::forCountry($country);
        return $calendar->addBusinessDays(new \DateTimeImmutable('today'), 5);
    }

    /** A valid unloading date: 3 business days after the loading date. */
    private function unloadingDate(string $country): \DateTimeImmutable
    {
        $calendar = CalendarFactory::forCountry($country);
        return $calendar->addBusinessDays($this->loadingDate($country), 3);
    }

    /**
     * Domestic packages: a returnable + stackable EUR pallet (allowed only on
     * domestic routes) plus additional package types, exercising every Package field.
     *
     * @return Package[]
     */
    private function domesticPackages(): array
    {
        return [
            new Package(
                symbol:     PackageSymbol::EUR,
                weightKg:   50.0,
                lengthCm:   120.0,
                widthCm:    80.0,
                heightCm:   144.0,
                returnable: 1,
                stackable:  1,
            ),
            new Package(PackageSymbol::KAR, weightKg: 10.0, lengthCm: 50.0, widthCm: 30.0, heightCm: 25.0),
            new Package(PackageSymbol::INN, weightKg: 5.5, lengthCm: 40.0, widthCm: 40.0, heightCm: 40.0),
        ];
    }

    /**
     * International packages: no returnable/stackable (SUUS rules PRJ00372/PRJ00373).
     *
     * @return Package[]
     */
    private function internationalPackages(): array
    {
        return [
            new Package(PackageSymbol::KAR, weightKg: 10.0, lengthCm: 50.0, widthCm: 30.0, heightCm: 25.0),
            new Package(PackageSymbol::INN, weightKg: 5.5, lengthCm: 40.0, widthCm: 40.0, heightCm: 40.0),
        ];
    }

    /**
     * Domestic/B2C service set: SMS awizo + inside delivery are domestic/B2C only.
     *
     * @return \VeryCodeCom\Suus\Service\ServiceInterface[]
     */
    private function domesticServices(): array
    {
        return [
            new CodService(amount: 500.00, currency: 'PLN'),
            new InsuranceService(
                amount:           2500.00,
                goodsType:        InsuranceService::GOODS_STANDARD,
                additionalCosts:  150.00,
                strikeClause:     true,
                warClause:        false,
            ),
            new SmsNotificationService(),
            new EmailNotificationService(notifySender: true, notifyReceiver: true),
            new LiftService(),
            new PalletTruckService(),
            new InsideDeliveryService(),
        ];
    }

    /**
     * International/B2B service set: no SMS/inside delivery (those are B2C domestic).
     *
     * @return \VeryCodeCom\Suus\Service\ServiceInterface[]
     */
    private function internationalServices(): array
    {
        return [
            new CodService(amount: 500.00, currency: 'PLN'),
            new InsuranceService(
                amount:          2500.00,
                goodsType:       InsuranceService::GOODS_STANDARD,
                additionalCosts: 150.00,
                strikeClause:    true,
                warClause:       false,
            ),
            new EmailNotificationService(notifySender: true, notifyReceiver: true),
            new LiftService(),
            new PalletTruckService(),
        ];
    }
}
