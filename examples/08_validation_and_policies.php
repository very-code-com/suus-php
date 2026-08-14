<?php

/**
 * Example 08 - Pre-flight validation, validation policy & route classification.
 * ---------------------------------------------------------------------------
 * Three flexibility features, none of which touch the network:
 *
 *   1. SuusClient::validate() - run the same local business-rule checks as
 *      createShipment() without sending anything. Returns typed ValidationError
 *      objects (code + field + message) you can map to your own UI.
 *
 *   2. ValidationPolicy - relax the international-only rules (B2B-only,
 *      no B2C services, no returnable/stackable) when your contract allows it.
 *      Strict by default; SUUS still validates server-side.
 *
 *   3. RouteClassifierInterface - redefine which routes count as "domestic".
 *      A German shop shipping DE->DE to consumers can treat those as domestic,
 *      which also drops the <shipper>/<consignee> blocks and incoterms
 *      requirement from the generated XML.
 *
 * Run:
 *   php examples/08_validation_and_policies.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use VeryCodeCom\Suus\SuusClient;
use VeryCodeCom\Suus\SuusConfig;
use VeryCodeCom\Suus\Dto\Address;
use VeryCodeCom\Suus\Dto\Package;
use VeryCodeCom\Suus\Dto\ShipmentOrder;
use VeryCodeCom\Suus\Enum\OrderType;
use VeryCodeCom\Suus\Enum\PackageSymbol;
use VeryCodeCom\Suus\Routing\CallableRouteClassifier;
use VeryCodeCom\Suus\Service\DocumentReturnInternationalService;
use VeryCodeCom\Suus\Service\SmsNotificationService;
use VeryCodeCom\Suus\Validation\ValidationError;
use VeryCodeCom\Suus\Validation\ValidationPolicy;

$config = SuusConfig::sandbox('ws_yourlogin', 'your_password');

/** Pretty-print a ValidationError[] list. */
$dump = static function (array $errors): void {
    if ($errors === []) {
        echo "  OK - no validation errors\n";
        return;
    }
    /** @var ValidationError $e */
    foreach ($errors as $e) {
        printf("  - [%s] %s (field: %s)\n", $e->code ?? '-', (string) $e, $e->field ?? '-');
    }
};

// A German consumer (DE->DE, B2C) order using an inside-delivery service and a
// returnable pallet. Under the default strict rules this is "international".
$deToDe = new ShipmentOrder(
    reference: 'DE-DE-001',
    sender:    new Address('Versand GmbH', 'Hauptstr.', '1', '10115', 'Berlin', 'DE', phone: '+4930111'),
    receiver:  new Address('Endkunde', 'Marktweg', '9', '80331', 'München', 'DE', mobilePhone: '+49170999888'),
    packages:  [new Package(PackageSymbol::EUR, weightKg: 40.0, heightCm: 30.0, returnable: 1, stackable: 1)],
    orderType: OrderType::B2C,
    additionalServices: [new SmsNotificationService()],
);

// -- 1. Default (strict) client: DE->DE is international -> many violations ----
echo "1) Strict defaults, DE->DE B2C treated as international:\n";
$strict = new SuusClient($config);
$dump($strict->validate($deToDe));

// -- 2. Relaxed policy: keep it international, but stop enforcing the intl-only rules
echo "\n2) Relaxed ValidationPolicy (international-only rules off):\n";
$relaxed = new SuusClient($config, policy: ValidationPolicy::relaxed());
$dump($relaxed->validate($deToDe));

// -- 3. Custom route classifier: treat DE->DE as domestic (client-side only) --
echo "\n3) Custom RouteClassifier - DE->DE reclassified as domestic (local validation only):\n";
$domesticDeToDe = new SuusClient($config, routeClassifier: new CallableRouteClassifier(
    static fn(ShipmentOrder $o): bool =>
        ($o->sender->getCountryCode() === 'DE' && $o->receiver->getCountryCode() === 'DE')
            ? false                      // DE->DE is domestic for this merchant
            : $o->isInternational(),     // everything else keeps the default rule
));
$dump($domesticDeToDe->validate($deToDe));

// -- 4. Service/route restrictions apply in both directions ------------------
echo "\n4) International-only service (document return GG) on a DOMESTIC order:\n";
$plToPl = new ShipmentOrder(
    reference: 'PL-PL-001',
    sender:    new Address('Nadawca', 'Przemysłowa', '12', '30-701', 'Kraków', 'PL', phone: '+48123456789'),
    receiver:  new Address('Odbiorca', 'Marszałkowska', '100', '00-026', 'Warszawa', 'PL', phone: '+48987654321'),
    packages:  [new Package(PackageSymbol::KAR, weightKg: 8.0)],
    orderType: OrderType::B2C,
    additionalServices: [new DocumentReturnInternationalService('FV/2026/07/001')],
);
$dump($strict->validate($plToPl));

echo "\nOption 3 changes what this library sends (no <shipper>/<consignee>, no incoterms)\n";
echo "and how it validates locally, but SUUS classifies routes on its own side from the\n";
echo "address country codes. A DE->DE order is still an international product server-side\n";
echo "and is rejected (BTN0002) unless your SUUS contract supports that treatment. To\n";
echo "simply relax the local international-only checks, use ValidationPolicy (option 2).\n";
