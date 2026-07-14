<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Integration;

use VeryCodeCom\Suus\Calendar\CalendarFactory;
use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\Incoterm;
use VeryCodeCom\Suus\Enum\OrderType;
use VeryCodeCom\Suus\Enum\PackageSymbol;
use VeryCodeCom\Suus\Enum\ShipmentCategory;
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
 * Two "maximal" addOrder tests exercise every field the library supports, while
 * honouring the documented SUUS constraints (WS PK 1.0):
 *   - Domestic PL->PL, orderType B2C: full addresses, a returnable+stackable EUR
 *     pallet, and the domestic/B2C service set (SMS awizo, inside delivery, COD,
 *     insurance, e-mail preadvice, lift, pallet truck).
 *   - International DE->DE, orderType B2B: incoterms + category + freight/currency,
 *     and the B2B service set (COD, insurance, e-mail, lift, pallet truck). Per
 *     docs, returnable/stackable and SMS/inside-delivery are NOT allowed here.
 *
 * Note: getEvents, getDocument, getColliNo always return PRJ000001 in sandbox -
 * only addOrder returns real data, so these tests only verify addOrder.
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

    public function testRealDomesticAddOrderReturnsShipmentNumber(): void
    {
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
