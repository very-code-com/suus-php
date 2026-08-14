<?php

/**
 * Example 05 - Additional services (COD, insurance, notifications, handling).
 * ---------------------------------------------------------------------------
 * Pass typed service objects in ShipmentOrder's `additionalServices` array. Each
 * class maps to a SUUS service symbol and serializes its own fields.
 *
 *   Service class               SUUS symbol                        Availability
 *   -------------------------   --------------------------------   ----------------------
 *   CodService                  RohligCOD                          B2B & B2C
 *   InsuranceService            RohligUbezpieczenie3               B2B & B2C
 *   EmailNotificationService    RohligZatwierdzeniePowiadomienie   B2B & B2C
 *   LiftService                 RohligWinda                        B2B & B2C
 *   PalletTruckService          StdPaleciak                        B2B & B2C
 *   SmsNotificationService      StdAwizacjaSms                     B2C, DOMESTIC ONLY
 *   InsideDeliveryService       StdWniesienie2                     B2C, DOMESTIC ONLY
 *   DocumentReturnDomesticService       StdDokumentyZwrotneINiezwrotneGrid2  DOMESTIC ONLY
 *   DocumentReturnInternationalService  StdDokumentyZwrotneINiezwrotneGrid3  INTERNATIONAL ONLY
 *
 * Gotchas enforced by SUUS:
 *   - SMS pre-advice requires the receiver's mobilePhone (PRJ00355).
 *   - E-mail pre-advice requires an e-mail on the notified party's address.
 *   - Insurance requires a minimum insured value (1 000 PLN), PLN only, and the
 *     mandatory "goods not excluded" declaration (int01=1) which InsuranceService
 *     always sends unless you pass confirmGoodsNotExcluded: false.
 *
 * This example uses a domestic PL->PL B2C order so it can show the full set,
 * including the B2C domestic-only services. On international routes use only the
 * B2B-compatible services (see example 02).
 *
 * Run:
 *   SUUS_LOGIN=ws_xxx SUUS_PASSWORD=xxx php examples/05_additional_services.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use VeryCodeCom\Suus\SuusClient;
use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\OrderType;
use VeryCodeCom\Suus\Enum\PackageSymbol;
use VeryCodeCom\Suus\Service\CodService;
use VeryCodeCom\Suus\Service\DocumentReturnDomesticService;
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
    reference: 'SVC-' . date('YmdHis'),
    sender: new Address(
        name:        'Nadawca Sp. z o.o.',
        street:      'Przemysłowa',
        streetNo:    '12',
        postcode:    '30-701',
        city:        'Kraków',
        countryCode: 'PL',
        phone:       '+48123456789',
        email:       'nadawca@example.com',   // required by e-mail pre-advice
    ),
    receiver: new Address(
        name:        'Odbiorca Sp. z o.o.',
        street:      'Marszałkowska',
        streetNo:    '100',
        postcode:    '00-026',
        city:        'Warszawa',
        countryCode: 'PL',
        phone:       '+48987654321',
        mobilePhone: '+48600123456',          // required by SMS pre-advice
        email:       'odbiorca@example.com',
    ),
    packages: [
        new Package(PackageSymbol::EUR, weightKg: 120.0, lengthCm: 120.0, widthCm: 80.0, heightCm: 144.0),
    ],
    orderType: OrderType::B2C,   // B2C so the domestic-only services are allowed
    additionalServices: [
        // Cash on delivery - max 15 000 PLN, PLN only.
        new CodService(amount: 250.0, currency: 'PLN'),

        // Cargo insurance - declared value >= 1 000 PLN, PLN only. The mandatory
        // "goods not in excluded groups" declaration (int01=1) is sent by default.
        new InsuranceService(
            amount:          2500.0,
            goodsType:       InsuranceService::GOODS_STANDARD, // UB_POZ (also: GOODS_PHARMA, GOODS_TEMP)
            additionalCosts: 150.0,                            // freight/customs to insure
            strikeClause:    true,                             // optional extra risk
            warClause:       false,
        ),

        // E-mail pre-advice to sender and/or receiver (each needs an e-mail set).
        new EmailNotificationService(notifySender: true, notifyReceiver: true),

        // SMS pre-advice to the receiver (needs receiver mobilePhone). B2C domestic only.
        new SmsNotificationService(),

        // Handling services.
        new LiftService(),          // tail-lift (<= 750 kg) at delivery
        new PalletTruckService(),   // pallet truck at delivery
        new InsideDeliveryService(),// carry goods inside. B2C domestic only.

        // Return of documents to sender (KR) - DOMESTIC only. On an international
        // order use DocumentReturnInternationalService (GG) instead.
        new DocumentReturnDomesticService(
            documentNumber: 'FV/2026/07/001',
            tag:            DocumentReturnDomesticService::TAG_RETURN,      // DZ (or TAG_ACCOMPANYING = DT)
            documentType:   DocumentReturnDomesticService::DOC_INVOICE,    // FK (or WZ / ZLEC / SPEC)
            description:    'Zwrot podpisanej faktury',
        ),
    ],
);

try {
    $result = $client->createShipment($order);
    echo "Shipment with services created: {$result->shipmentNo}\n";
    echo "Tracking URL: {$result->trackingUrl}\n";
} catch (SuusException $e) {
    echo "SUUS error: {$e->getMessage()}\n";
}
