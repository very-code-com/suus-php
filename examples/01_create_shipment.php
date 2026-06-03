<?php

/**
 * Example: Create a domestic PL→PL shipment.
 * Run: php examples/01_create_shipment.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use VeryCodeCom\Suus\SuusClient;
use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\PackageSymbol;
use VeryCodeCom\Suus\Exception\SuusDuplicateReferenceException;
use VeryCodeCom\Suus\Exception\SuusException;

$client = SuusClient::sandbox(
    login:    getenv('SUUS_LOGIN')    ?: 'ws_yourlogin',
    password: getenv('SUUS_PASSWORD') ?: 'your_password',
);

$order = new ShipmentOrder(
    reference: 'ORDER-2025-001',
    sender: new Address(
        name:        'Nadawca Sp. z o.o.',
        street:      'Przemysłowa',
        streetNo:    '12',
        postcode:    '30-701',
        city:        'Kraków',
        countryCode: 'PL',
        phone:       '+48123456789',
    ),
    receiver: new Address(
        name:        'Odbiorca Sp. z o.o.',
        street:      'Marszałkowska',
        streetNo:    '100',
        postcode:    '00-026',
        city:        'Warszawa',
        countryCode: 'PL',
        phone:       '+48987654321',
    ),
    packages: [
        new Package(PackageSymbol::EUR, weightKg: 120.0, lengthCm: 120.0, widthCm: 80.0, heightCm: 15.0),
        new Package(PackageSymbol::KAR, weightKg: 12.5,  lengthCm: 60.0,  widthCm: 40.0, heightCm: 30.0),
    ],
);

try {
    $result = $client->createShipment($order);

    echo "Shipment created successfully!\n";
    echo "  Shipment No : {$result->shipmentNo}\n";
    echo "  Reference   : {$result->reference}\n";
    echo "  Tracking URL: {$result->trackingUrl}\n";
} catch (SuusDuplicateReferenceException $e) {
    echo "Reference already exists - use a unique reference.\n";
} catch (SuusException $e) {
    echo "SUUS error: {$e->getMessage()}\n";
}
