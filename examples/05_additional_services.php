<?php

/**
 * Example: Create a shipment with additional services (insurance, SMS, COD, etc.).
 * Run: php examples/05_additional_services.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use VeryCodeCom\Suus\SuusClient;
use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\PackageSymbol;
use VeryCodeCom\Suus\Service\CodService;
use VeryCodeCom\Suus\Service\EmailNotificationService;
use VeryCodeCom\Suus\Service\InsideDeliveryService;
use VeryCodeCom\Suus\Service\InsuranceService;
use VeryCodeCom\Suus\Service\LiftService;
use VeryCodeCom\Suus\Service\PalletTruckService;
use VeryCodeCom\Suus\Service\SmsNotificationService;
use VeryCodeCom\Suus\Exception\SuusException;

$client = SuusClient::sandbox(
    login:    getenv('SUUS_LOGIN')    ?: 'ws_yourlogin',
    password: getenv('SUUS_PASSWORD') ?: 'your_password',
);

$order = new ShipmentOrder(
    reference: 'ORDER-SVC-001',
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
        mobilePhone: '+48600123456',
        email:       'odbiorca@example.com',
    ),
    packages: [
        new Package(PackageSymbol::EUR, weightKg: 120.0),
    ],
    services: [
        new InsuranceService(declaredValueEur: 500.0),   // cargo insurance
        new SmsNotificationService(),                     // SMS to receiver on delivery
        new EmailNotificationService(),                   // e-mail to receiver on delivery
        new CodService(amountEur: 250.0),                 // cash on delivery
        new LiftService(),                                // tail-lift required at delivery
        new InsideDeliveryService(),                      // carry goods inside
        new PalletTruckService(),                         // pallet truck at delivery
    ],
);

try {
    $result = $client->createShipment($order);
    echo "Shipment with services created: {$result->shipmentNo}\n";
} catch (SuusException $e) {
    echo "SUUS error: {$e->getMessage()}\n";
}
