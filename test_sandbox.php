<?php

/**
 * Quick sandbox connectivity test.
 * Run: php test_sandbox.php
 */

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use VeryCodeCom\Suus\SuusClient;
use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\OrderType;
use VeryCodeCom\Suus\Enum\PackageSymbol;
use VeryCodeCom\Suus\Exception\SuusDuplicateReferenceException;
use VeryCodeCom\Suus\Exception\SuusException;
use VeryCodeCom\Suus\Transport\TransportInterface;
use VeryCodeCom\Suus\Transport\TransportRequest;
use VeryCodeCom\Suus\Transport\TransportResponse;
use VeryCodeCom\Suus\Transport\CurlTransport;

$login    = getenv('SUUS_LOGIN')    ?: 'ws_yourlogin';
$password = getenv('SUUS_PASSWORD') ?: 'your_password';

// Wraps CurlTransport to print raw request/response XML
$debugTransport = new class(new CurlTransport()) implements TransportInterface {
    public function __construct(private readonly CurlTransport $inner) {}
    public function send(TransportRequest $request): TransportResponse {
        echo "\n=== REQUEST ===\n" . $request->body . "\n";
        $response = $this->inner->send($request);
        echo "\n=== RESPONSE (HTTP {$response->statusCode}) ===\n" . $response->body . "\n\n";
        return $response;
    }
};

echo "Connecting to SUUS sandbox...\n";
echo "Login: $login\n\n";

$config = \VeryCodeCom\Suus\SuusConfig::sandbox($login, $password);
$client = new SuusClient($config, transport: $debugTransport);

// Unique reference based on timestamp
$ref = 'TEST-' . date('YmdHis');

$order = new ShipmentOrder(
    reference: $ref,
    orderType: OrderType::B2B,
    sender: new Address(
        name:        'Testowy Nadawca Sp. z o.o.',
        street:      'Przemysłowa',
        streetNo:    '12',
        postcode:    '30-701',
        city:        'Kraków',
        countryCode: 'PL',
        phone:       '+48123456789',
    ),
    receiver: new Address(
        name:        'Testowy Odbiorca Sp. z o.o.',
        street:      'Marszałkowska',
        streetNo:    '100',
        postcode:    '00-026',
        city:        'Warszawa',
        countryCode: 'PL',
        phone:       '+48987654321',
    ),
    packages: [
        new Package(PackageSymbol::EUR, weightKg: 50.0, lengthCm: 120.0, widthCm: 80.0, heightCm: 20.0),
    ],
);

echo "Sending addOrder (ref: $ref)...\n";

try {
    $result = $client->createShipment($order);

    echo "\n--- SUCCESS ---\n";
    echo "Shipment No : {$result->shipmentNo}\n";
    echo "Reference   : {$result->reference}\n";
    if ($result->trackingUrl) {
        echo "Tracking URL: {$result->trackingUrl}\n";
    }
} catch (SuusDuplicateReferenceException $e) {
    echo "\nDuplicate reference — use a different reference.\n";
    echo $e->getMessage() . "\n";
} catch (SuusException $e) {
    echo "\n--- SUUS ERROR ---\n";
    echo get_class($e) . ": " . $e->getMessage() . "\n";
    if ($e->getPrevious()) {
        echo "Caused by: " . $e->getPrevious()->getMessage() . "\n";
    }
} catch (\Throwable $e) {
    echo "\n--- ERROR ---\n";
    echo get_class($e) . ": " . $e->getMessage() . "\n";
}
