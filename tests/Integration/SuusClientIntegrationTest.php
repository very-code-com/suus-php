<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Tests\Integration;

use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\Incoterm;
use VeryCodeCom\Suus\Enum\PackageSymbol;
use VeryCodeCom\Suus\SuusClient;
use VeryCodeCom\Suus\SuusConfig;
use PHPUnit\Framework\TestCase;

/**
 * Integration test - makes a real addOrder call to the SUUS sandbox.
 *
 * These tests are skipped unless the SUUS_SANDBOX environment variable is set to "1".
 *
 * Usage:
 *   SUUS_SANDBOX=1 SUUS_LOGIN=ws_xxx SUUS_PASSWORD=xxx vendor/bin/phpunit --testsuite integration
 *
 * Note: getEvents, getDocument, getColliNo always return PRJ000001 in sandbox -
 * only addOrder returns real data. This test only verifies addOrder.
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

    public function testRealAddOrderReturnsShipmentNumber(): void
    {
        $ref   = 'TEST-' . date('YmdHis') . '-' . random_int(100, 999);
        $order = new ShipmentOrder(
            reference:   $ref,
            sender:      new Address(
                name:        'Versender GmbH',
                street:      'Musterstraße',
                streetNo:    '1',
                postcode:    '10115',
                city:        'Berlin',
                countryCode: 'DE',
                phone:       '+4930123456',
            ),
            receiver:    new Address(
                name:        'Empfänger GmbH',
                street:      'Hauptstraße',
                streetNo:    '10',
                postcode:    '80331',
                city:        'Munich',
                countryCode: 'DE',
                phone:       '+4989654321',
            ),
            packages:    [
                new Package(PackageSymbol::KAR, weightKg: 10.0, lengthCm: 50.0, widthCm: 30.0, heightCm: 20.0),
            ],
            loadingDate: new \DateTimeImmutable('+5 days'),
        );

        $result = $this->client->createShipment($order);

        $this->assertNotEmpty($result->shipmentNo);
        $this->assertStringStartsWith('OPL', $result->shipmentNo);
        $this->assertSame($ref, $result->reference);
        $this->assertStringContainsString($result->shipmentNo, $result->trackingUrl);

        echo "\n[SUUS Integration] Shipment created: {$result->shipmentNo}\n";
        echo "Tracking URL: {$result->trackingUrl}\n";
    }

    public function testTrackingUrlFormat(): void
    {
        $this->assertSame(
            'https://portal.suus.com/order-details/OPLTEST123',
            $this->client->trackingUrl('OPLTEST123'),
        );
    }
}
