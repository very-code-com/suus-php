<?php

/**
 * Example 02 - Create an international shipment (DE->PL) with all header options.
 * ---------------------------------------------------------------------------
 * A shipment is "international" whenever EITHER the sender OR the receiver is
 * outside Poland. For every international route the SUUS API requires / forbids:
 *
 *   REQUIRED:
 *     - incoterms (one of EXW, FCA, FAS, FOB, CFR, CIF, CPT, CIP, DAP, DDP)
 *     - orderType B2B (B2C is domestic-only)
 *   OPTIONAL:
 *     - category  (DROBNICA / 24PLUS / PTL)
 *     - freight + currency (must be given together, per SUUS rule PRJ00387)
 *     - costGroup (cost-group tag, <= 20 chars)
 *   NOT ALLOWED:
 *     - returnable / stackable packaging (PRJ00372 / PRJ00373)
 *     - B2C domestic services (SMS pre-advice, inside delivery)
 *
 * Internally the library also mirrors the sender/receiver into the SUUS
 * <shipper>/<consignee> sections that international orders require.
 *
 * Run:
 *   SUUS_LOGIN=ws_xxx SUUS_PASSWORD=xxx php examples/02_international_shipment.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use VeryCodeCom\Suus\SuusClient;
use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\Incoterm;
use VeryCodeCom\Suus\Enum\OrderType;
use VeryCodeCom\Suus\Enum\PackageSymbol;
use VeryCodeCom\Suus\Enum\ShipmentCategory;
use VeryCodeCom\Suus\Service\CodService;
use VeryCodeCom\Suus\Service\InsuranceService;
use VeryCodeCom\Suus\Exception\SuusValidationException;
use VeryCodeCom\Suus\Exception\SuusApiException;
use VeryCodeCom\Suus\Exception\SuusException;

$client = SuusClient::sandbox(
    login:    getenv('SUUS_LOGIN')    ?: 'ws_yourlogin',
    password: getenv('SUUS_PASSWORD') ?: 'your_password',
);

// The client auto-detects the business-day calendar from the sender's country
// (DE -> GermanCalendar here), so the auto-computed loading date lands on a valid
// German business day. You can still pass explicit dates if you prefer.

$order = new ShipmentOrder(
    reference: 'INTL-' . date('YmdHis'),
    sender: new Address(
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
    receiver: new Address(
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
    ),
    // No returnable/stackable pallets on international routes.
    packages: [
        new Package(PackageSymbol::KAR, weightKg: 12.5, lengthCm: 60.0, widthCm: 40.0, heightCm: 30.0),
        new Package(PackageSymbol::INN, weightKg: 5.0,  lengthCm: 40.0, widthCm: 40.0, heightCm: 40.0),
    ],
    incoterms:          Incoterm::DAP,             // REQUIRED for non-PL->PL
    orderType:          OrderType::B2B,            // REQUIRED for non-PL->PL
    category:           ShipmentCategory::DROBNICA, // DROBNICA / PLUS24 / PTL
    descriptionOfGoods: 'Machine parts and accessories',
    remarks:            'Handle with care. Call before delivery.',
    // Only B2B-compatible services on international routes:
    additionalServices: [
        new CodService(amount: 500.0, currency: 'PLN'),
        new InsuranceService(amount: 2500.0, goodsType: InsuranceService::GOODS_STANDARD),
    ],
    costGroup: '/SI',      // optional cost-group tag
    freight:   '150.00',   // optional - must be paired with currency
    currency:  'EUR',
);

try {
    $result = $client->createShipment($order);

    echo "International shipment created!\n";
    echo "  Shipment No : {$result->shipmentNo}\n";
    echo "  Reference   : {$result->reference}\n";
    echo "  Tracking URL: {$result->trackingUrl}\n";
} catch (SuusValidationException $e) {
    echo "Local validation failed:\n";
    foreach ($e->getErrors() as $error) {
        echo "  - {$error}\n";
    }
} catch (SuusApiException $e) {
    echo "SUUS rejected the order [{$e->returnCode}]: {$e->getMessage()}\n";
} catch (SuusException $e) {
    echo "SUUS error: {$e->getMessage()}\n";
}
