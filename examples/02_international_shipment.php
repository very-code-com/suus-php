<?php

/**
 * Example: Create an international DE→PL shipment with incoterms.
 * Run: php examples/02_international_shipment.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use VeryCodeCom\Suus\SuusClient;
use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\Incoterm;
use VeryCodeCom\Suus\Enum\PackageSymbol;
use VeryCodeCom\Suus\Exception\SuusException;

$client = SuusClient::sandbox(
    login:    getenv('SUUS_LOGIN')    ?: 'ws_yourlogin',
    password: getenv('SUUS_PASSWORD') ?: 'your_password',
);

$order = new ShipmentOrder(
    reference: 'INTL-2025-001',
    sender: new Address(
        name:        'Versender GmbH',
        street:      'Musterstraße',
        streetNo:    '1',
        postcode:    '10115',
        city:        'Berlin',
        countryCode: 'DE',
        phone:       '+4930123456',
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
        new Package(PackageSymbol::KAR, weightKg: 12.5, lengthCm: 60.0, widthCm: 40.0, heightCm: 30.0),
    ],
    incoterms: Incoterm::DAP,  // required for any non-PL→PL route
);

try {
    $result = $client->createShipment($order);

    echo "International shipment created!\n";
    echo "  Shipment No : {$result->shipmentNo}\n";
    echo "  Tracking URL: {$result->trackingUrl}\n";
} catch (SuusException $e) {
    echo "SUUS error: {$e->getMessage()}\n";
}
