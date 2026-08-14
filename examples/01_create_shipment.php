<?php

/**
 * Example 01 - Create a domestic PL->PL shipment (the most common case).
 * ---------------------------------------------------------------------------
 * Demonstrates:
 *   - building a ShipmentOrder with fully-populated Address objects
 *   - mixing several package types in one order
 *   - letting the client auto-compute loading/unloading dates
 *   - handling every exception type the client can throw
 *
 * A PL->PL route is "domestic": incoterms are not required and orderType may be
 * B2B or B2C. See example 02 for the international (non-PL->PL) case.
 *
 * Run:
 *   SUUS_LOGIN=ws_xxx SUUS_PASSWORD=xxx php examples/01_create_shipment.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use VeryCodeCom\Suus\SuusClient;
use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\OrderType;
use VeryCodeCom\Suus\Enum\PackageSymbol;
use VeryCodeCom\Suus\Exception\SuusAuthException;
use VeryCodeCom\Suus\Exception\SuusDuplicateReferenceException;
use VeryCodeCom\Suus\Exception\SuusValidationException;
use VeryCodeCom\Suus\Exception\SuusApiException;
use VeryCodeCom\Suus\Exception\SuusTransportException;
use VeryCodeCom\Suus\Exception\SuusResponseParseException;
use VeryCodeCom\Suus\Exception\SuusException;

// ---------------------------------------------------------------------------
// 1. Build the client. ::sandbox() targets the SUUS test environment
//    (https://pkt-dev.suus.com); use ::production() for the live portal.
// ---------------------------------------------------------------------------
$client = SuusClient::sandbox(
    login:    getenv('SUUS_LOGIN')    ?: 'ws_yourlogin',
    password: getenv('SUUS_PASSWORD') ?: 'your_password',
);

// ---------------------------------------------------------------------------
// 2. Describe the sender (loading address). Only name/street/streetNo/postcode/
//    city/countryCode plus one phone are required; the rest are optional but
//    recommended (some additional services require an e-mail on the address).
// ---------------------------------------------------------------------------
$sender = new Address(
    name:          'Nadawca Sp. z o.o.',
    street:        'Przemysłowa',
    streetNo:      '12',
    postcode:      '30-701',
    city:          'Kraków',
    countryCode:   'PL',
    phone:         '+48123456789',   // landline or mobilePhone is required
    mobilePhone:   '+48600100200',
    contactPerson: 'Jan Kowalski',   // defaults to `name` when omitted
    email:         'nadawca@example.pl',
);

$receiver = new Address(
    name:          'Odbiorca Sp. z o.o.',
    street:        'Marszałkowska',
    streetNo:      '100',
    postcode:      '00-026',
    city:          'Warszawa',
    countryCode:   'PL',
    phone:         '+48987654321',
    mobilePhone:   '+48600300400',
    contactPerson: 'Anna Nowak',
    email:         'odbiorca@example.pl',
);

// ---------------------------------------------------------------------------
// 3. Describe the goods. weightKg is required; dimensions are optional but
//    strongly recommended (SUUS may reject pallets without them). You can mix
//    package types freely, subject to the SUUS limits enforced locally by
//    ShipmentValidator (<=126 kg/pkg, <=800 kg/order, <=124 pkgs, 240x120x220 cm).
// ---------------------------------------------------------------------------
$packages = [
    new Package(PackageSymbol::EUR, weightKg: 120.0, lengthCm: 120.0, widthCm: 80.0, heightCm: 144.0),
    new Package(PackageSymbol::KAR, weightKg: 12.5,  lengthCm: 60.0,  widthCm: 40.0, heightCm: 30.0),
    new Package(PackageSymbol::INN, weightKg: 3.0,   lengthCm: 30.0,  widthCm: 20.0, heightCm: 15.0),
];

// ---------------------------------------------------------------------------
// 4. Assemble the order. Leaving loadingDate/unloadingDate null lets the client
//    pick valid business days automatically (loading = +2 business days in the
//    sender's country calendar; unloading = loading + 3 business days).
// ---------------------------------------------------------------------------
$order = new ShipmentOrder(
    reference:          'ORDER-' . date('YmdHis'), // must be unique per shipment
    sender:             $sender,
    receiver:           $receiver,
    packages:           $packages,
    orderType:          OrderType::B2B,            // B2B or B2C for domestic
    descriptionOfGoods: 'Artykuły przemysłowe',    // defaults to "General cargo"
    remarks:            'Prosimy o telefon przed dostawą.',
    // loadingDate / unloadingDate omitted -> auto-computed
);

// ---------------------------------------------------------------------------
// 5. Send it. Every failure mode has its own exception subtype so you can react
//    precisely; all of them extend SuusException.
// ---------------------------------------------------------------------------
try {
    $result = $client->createShipment($order);

    echo "Shipment created:\n";
    echo "  Shipment No : {$result->shipmentNo}\n";
    echo "  Reference   : {$result->reference}\n";
    echo "  Tracking URL: {$result->trackingUrl}\n";
} catch (SuusValidationException $e) {
    // Thrown before any network call when the order violates a local rule.
    echo "Local validation failed:\n";
    foreach ($e->getErrors() as $error) {
        echo "  - {$error}\n";
    }
} catch (SuusDuplicateReferenceException $e) {
    echo "Reference already exists (PRJ00310) - use a unique reference.\n";
} catch (SuusAuthException $e) {
    echo "Authentication failed (DRG00001) - check SUUS_LOGIN / SUUS_PASSWORD.\n";
} catch (SuusApiException $e) {
    // Any other business error returned by SUUS. Carries the raw return code
    // and (when present) per-field messages / returnDesc.
    echo "SUUS rejected the order [{$e->returnCode}]: {$e->getMessage()}\n";
    foreach ($e->getFormattedErrors() as $line) {
        echo "  - {$line}\n";
    }
} catch (SuusTransportException $e) {
    echo "Network/HTTP problem talking to SUUS: {$e->getMessage()}\n";
} catch (SuusResponseParseException $e) {
    echo "SUUS returned an unparseable response: {$e->getMessage()}\n";
} catch (SuusException $e) {
    echo "Unexpected SUUS error: {$e->getMessage()}\n";
}
