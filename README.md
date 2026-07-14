# suus-php

**PHP client library for the SUUS Logistics (Rohlig Logistics) SOAP API.**

The first open-source PHP package for SUUS/Rohlig freight integration - create shipments, pre-flight validate orders, track statuses, download documents, and handle multi-country business-day scheduling.

[![Latest Version](https://img.shields.io/packagist/v/very-code-com/suus-php.svg)](https://packagist.org/packages/very-code-com/suus-php)
[![Total Downloads](https://img.shields.io/packagist/dt/very-code-com/suus-php.svg)](https://packagist.org/packages/very-code-com/suus-php)
[![CI](https://github.com/very-code-com/suus-php/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/very-code-com/suus-php/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen)](https://phpstan.org)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue)](https://www.php.net)
[![License](https://img.shields.io/packagist/l/very-code-com/suus-php.svg)](LICENSE)

---

## Requirements

- PHP 8.2+
- `ext-curl`
- `ext-dom`

## Installation

```bash
composer require very-code-com/suus-php
```

## Quick Start

```php
use VeryCodeCom\Suus\SuusClient;
use VeryCodeCom\Suus\Dto\{Address, Package, ShipmentOrder};
use VeryCodeCom\Suus\Enum\{Incoterm, PackageSymbol};

$client = SuusClient::sandbox('ws_yourlogin', 'your_password');

$result = $client->createShipment(new ShipmentOrder(
    reference: 'ORDER-2025-001',
    sender:    new Address('Sender GmbH', 'Musterstr.', '1', '10115', 'Berlin', 'DE', phone: '+4930123'),
    receiver:  new Address('Odbiorca Sp. z o.o.', 'Marszałkowska', '100', '00-026', 'Warszawa', 'PL', phone: '+48600000'),
    packages:  [new Package(PackageSymbol::EUR, weightKg: 120.0)],
    incoterms: Incoterm::DAP,
));

echo $result->shipmentNo;   // e.g. OPLKRI2600895
echo $result->trackingUrl;  // https://portal.suus.com/order-details/OPLKRI2600895
```

See [`examples/`](examples/) for complete, runnable scripts.

---

## Configuration

```php
// Named constructors
$client = SuusClient::sandbox('ws_login', 'secret');
$client = SuusClient::production('ws_login', 'secret');

// From environment variables (recommended)
$config = SuusConfig::fromEnv();

// From array (framework config)
$config = SuusConfig::fromArray(['login' => '...', 'password' => '...', 'env' => 'production']);
```

| Env variable          | Required | Default      | Description                      |
|-----------------------|----------|--------------|----------------------------------|
| `SUUS_LOGIN`          | yes      | -            | API login (e.g. `ws_yourlogin`)  |
| `SUUS_PASSWORD`       | yes      | -            | API password                     |
| `SUUS_ENV`            | no       | `production` | `sandbox` or `production`        |
| `SUUS_TIMEOUT`        | no       | `30`         | Request timeout (seconds)        |
| `SUUS_CONNECT_TIMEOUT`| no       | `10`         | Connection timeout (seconds)     |
| `SUUS_DEBUG`          | no       | `0`          | `1`/`true` to enable verbose debug output (see below) |

### Debug mode

Set the `debug` flag (constructor arg, `SUUS_DEBUG=1`, or `'debug' => true` in
`fromArray`) to make the client attach the **raw SUUS response** to every thrown
exception and log a full **debug report** (message + raw XML + stack trace) at
`error` level via the injected PSR-3 logger:

```php
$config = new SuusConfig('ws_login', 'secret', sandbox: true, debug: true);
$client = new SuusClient($config, logger: $myPsrLogger);

try {
    $client->createShipment($order);
} catch (SuusException $e) {
    // Raw response is always available for inspection, regardless of the flag:
    echo $e->getRawResponse();   // the exact XML SUUS returned (or null)

    // Full developer report: class + message + raw response + stack trace:
    echo $e->getDebugReport();
}
```

This is ideal for diagnosing **unrecognised** errors (e.g. bare `BTN0001`) where
you need to see exactly what SUUS sent back. Leave `debug` off in production to
keep exceptions and logs concise.

---

## API Reference

### `createShipment(ShipmentOrder $order): ShipmentResult`

Creates a shipment via SUUS `addOrder`. Validates locally first.
Returns `ShipmentResult` with `shipmentNo`, `reference`, `trackingUrl`.

**`ShipmentOrder` fields:**

| Field                | Type                | Required | Notes |
|----------------------|---------------------|----------|-------|
| `reference`          | `string`            | yes      | Your unique reference (<= 50 chars) |
| `sender`             | `Address`           | yes      | Loading address |
| `receiver`           | `Address`           | yes      | Unloading address |
| `packages`           | `Package[]`         | yes      | >= 1 package |
| `loadingDate`        | `?DateTimeImmutable`| no       | `null` = auto (+2 business days in sender's calendar) |
| `unloadingDate`      | `?DateTimeImmutable`| no       | `null` = loadingDate + 3 business days |
| `incoterms`          | `?Incoterm`         | intl.    | Required for international routes |
| `orderType`          | `OrderType`         | no       | `B2C` default; **must be `B2B`** for international |
| `category`           | `ShipmentCategory`  | no       | `DROBNICA` / `PLUS24` / `PTL` (international) |
| `descriptionOfGoods` | `string`            | no       | Defaults to `General cargo` |
| `remarks`            | `string`            | no       | Free-text remarks (<= 100 chars) |
| `additionalServices` | `array`             | no       | Typed service objects - see [Additional Services](#additional-services) |
| `costGroup`          | `?string`           | no       | Cost-group tag, <= 20 chars (e.g. `/SI`) |
| `freight`            | `?string`           | no       | International only; must be paired with `currency` |
| `currency`           | `?string`           | no       | 3-letter code; must be paired with `freight` |

`Address` requires `name`, `street`, `streetNo`, `postcode`, `city`, `countryCode`
and at least one of `phone` / `mobilePhone`; `contactPerson` and `email` are optional
(some services require an e-mail on the relevant address).

-> [full example](examples/01_create_shipment.php) | [international routes](examples/02_international_shipment.php) | [additional services](examples/05_additional_services.php)

### `validate(ShipmentOrder $order): ValidationError[]`

Runs the same local business-rule checks as `createShipment()` with **no network
call**, auto-selecting the sender-country calendar and applying the configured
`ValidationPolicy` / `RouteClassifier`. Returns typed `ValidationError` objects
(`message` / `field` / `code`); an empty array means the order is valid. Ideal for
surfacing validation in your own UI before sending. See
[Pre-flight Validation, Policies & Route Classification](#pre-flight-validation-policies--route-classification).

-> [full example](examples/08_validation_and_policies.php)

### `fetchStatus(string $shipmentNo): StatusResult`

Polls events via SUUS `getEvents`.
Returns `StatusResult` with `status` (`ShipmentStatus` enum), `rawLatestCode`, `events[]`.

> **Note:** `getEvents` always returns `PRJ000001` in sandbox mode.

-> [full example](examples/03_fetch_status.php)

**Status mapping:**

| SUUS native codes                           | Normalized `ShipmentStatus` |
|---------------------------------------------|-----------------------------|
| `J_CR`, `KOL`, `M_KOL`                     | `Created`                   |
| `LOAD`, `ZALF`, `ZAL`, `M_DYS`, `WTRF`     | `InTransit`                 |
| `ROZF`, `UNDI`, `UNLO`                      | `Delivered`                 |
| `ANUL`                                      | `Cancelled`                 |
| `ZWRON`, `ZTF`                              | `Failed`                    |

### `fetchDocument(string $shipmentNo, DocumentType $type): string`

Downloads a document as raw PDF bytes via SUUS `getDocument`.

| `DocumentType`  | Description                    |
|-----------------|--------------------------------|
| `Label`         | Standard A4 shipping label     |
| `LabelA6`       | Thermal printer label (A6)     |
| `ShippingOrder` | Shipping order document        |
| `LoadingList`   | Loading list                   |

### `fetchLabel(string $shipmentNo): string`

Convenience shortcut for `fetchDocument(..., DocumentType::Label)`.

-> [full example](examples/04_fetch_document.php)

### `getColliNumbers(string $shipmentNo): array`

Returns per-package (colli) tracking numbers for multi-package shipments.

---

## Package Types

| `PackageSymbol` | Description               |
|-----------------|---------------------------|
| `KAR`           | Cardboard box (karton)    |
| `EUR`           | EUR pallet                |
| `JED`           | Disposable pallet         |
| `PLT`           | Industrial pallet         |
| `SKR`           | Crate (skrzynia)          |
| `ROL`           | Roll (rolka)              |
| `DPL`           | DPPL container            |
| `DHP`           | DHP pallet                |
| `CHP`           | CHEP pallet               |
| `AGD`           | Appliance (gabaryt AGD)   |
| `INN`           | Other / re-handling       |
| `WIA`           | Bundle (wiązka)           |
| `HB`            | Hobok                     |

Every `Package` carries `weightKg` (required) and optional `lengthCm`, `widthCm`,
`heightCm`. For a **returnable EUR pallet** set `returnable` and `stackable` (SUUS
requires `stackable = 1` when `symbol = EUR` and `returnable > 0`). Returnable /
stackable packaging is domestic-only.

```php
new Package(PackageSymbol::EUR, weightKg: 50.0, lengthCm: 120.0, widthCm: 80.0, heightCm: 144.0, returnable: 1, stackable: 1);
```

**SUUS package limits** (enforced locally by `ShipmentValidator`): max 126 kg per
package, max 800 kg per order, max 124 packages, max dimensions 240 x 120 x 220 cm,
and a minimum height of 20 cm for EUR pallets.

---

## Additional Services

Pass typed service objects in `ShipmentOrder`'s `additionalServices` array. Each
maps to a SUUS service symbol and its fields are serialized automatically.

| Service class                | SUUS symbol                     | Availability            | Key options |
|------------------------------|---------------------------------|-------------------------|-------------|
| `CodService`                 | `RohligCOD`                     | B2B & B2C               | `amount` (<= 15 000 PLN), `currency` (`PLN`) |
| `InsuranceService`           | `RohligUbezpieczenie3`          | B2B & B2C               | `amount`, `goodsType`, `additionalCosts`, `strikeClause`, `warClause`, `confirmGoodsNotExcluded` |
| `EmailNotificationService`   | `RohligZatwierdzeniePowiadomienie` | B2B & B2C            | `notifySender`, `notifyReceiver` (each party notified needs an e-mail on its address) |
| `LiftService`                | `RohligWinda`                   | B2B & B2C               | tail-lift (<= 750 kg) |
| `PalletTruckService`         | `StdPaleciak`                   | B2B & B2C               | pallet truck at delivery |
| `SmsNotificationService`     | `StdAwizacjaSms`                | **B2C, domestic only**  | receiver **mobilePhone required** (`PRJ00355`) |
| `InsideDeliveryService`      | `StdWniesienie2`                | **B2C, domestic only**  | carry goods inside |
| `DocumentReturnDomesticService`      | `StdDokumentyZwrotneINiezwrotneGrid2` | **domestic only** | `documentNumber`, `tag` (`DZ`/`DT`), `documentType` (`FK`/`WZ`/`ZLEC`/`SPEC`), `description` |
| `DocumentReturnInternationalService` | `StdDokumentyZwrotneINiezwrotneGrid3` | **international only** | same fields as the domestic variant |

> **Route-restricted services** are enforced locally: domestic-only services on an
> international order (and the international-only document-return service on a
> domestic order) are rejected before the API call. Relax this with
> `ValidationPolicy` (`enforceServiceRouteRestrictions`).

```php
use VeryCodeCom\Suus\Service\{CodService, InsuranceService, EmailNotificationService};

additionalServices: [
    new CodService(amount: 250.0, currency: 'PLN'),
    new InsuranceService(amount: 2500.0, goodsType: InsuranceService::GOODS_STANDARD),
    new EmailNotificationService(notifySender: true, notifyReceiver: true),
],
```

> **Insurance note:** `InsuranceService` always sends the mandatory SUUS declaration
> that the goods are not in an excluded group (`int01 = 1`; omitting it triggers
> `PRJ000293`). Disable it explicitly with `confirmGoodsNotExcluded: false` if ever
> required. Goods types: `GOODS_STANDARD` (`UB_POZ`), `GOODS_PHARMA` (`UB_LEK`),
> `GOODS_TEMP` (`UB_TEMP`). SUUS enforces a minimum insured value (1 000 PLN) and
> `PLN`-only currency.

-> [full example](examples/05_additional_services.php)

---

## International Routes & Incoterms

A shipment is **international** whenever **either** the sender or the receiver is
outside Poland. Only `PL->PL` counts as domestic. For every international route the
following are enforced **locally, before the API call** (all confirmed against the
SUUS WebApi docs, WS PK 1.0):

- `incoterms` is **required** (otherwise SUUS returns `PRJ00313`);
- `orderType` **must be `B2B`** - B2C is not supported for international routes;
- `returnable` / `stackable` packaging is **not available** (`PRJ00372` / `PRJ00373`);
- the domestic-only B2C services (SMS pre-advice, inside delivery) cannot be used;
- `freight` + `currency` may optionally be declared (both together, per `PRJ00387`).

The international-only rules above (except the always-on `incoterms` and
`freight`/`currency` checks) can be relaxed or redefined per integrator - see
[Pre-flight validation & policies](#pre-flight-validation-policies--route-classification).

| Route    | Classified as | Incoterms required |
|----------|---------------|--------------------|
| `PL->PL`  | Domestic      | No                 |
| `PL->DE`  | International  | Yes                |
| `PL->CH`  | International  | Yes (Swiss customs docs) |
| `DE->DE`  | International  | Yes                |
| `DE->AT`  | International  | Yes                |
| `DE->CH`  | International  | Yes (Swiss customs docs) |

Supported incoterms: `EXW`, `FCA`, `FAS`, `FOB`, `CFR`, `CIF`, `CPT`, `CIP`, `DAP`, `DDP`.

```php
$result = $client->createShipment(new ShipmentOrder(
    reference: 'INT-2025-001',
    sender:    new Address('Versender GmbH', 'Musterstr.', '1', '10115', 'Berlin', 'DE', phone: '+4930123'),
    receiver:  new Address('Empfänger GmbH', 'Hauptstr.', '10', '80331', 'Munich', 'DE', phone: '+4989654'),
    packages:  [new Package(PackageSymbol::KAR, weightKg: 10.0, lengthCm: 50.0, widthCm: 30.0, heightCm: 25.0)],
    incoterms: Incoterm::DAP,
    orderType: OrderType::B2B,   // required for international
    category:  ShipmentCategory::DROBNICA,
    freight:   '150.00',          // optional - must be paired with currency
    currency:  'EUR',
));
```

-> [full example](examples/02_international_shipment.php)

---

## Pre-flight Validation, Policies & Route Classification

### Validate before you send

`SuusClient::validate()` runs the **same** local business-rule checks as
`createShipment()` but makes **no network call**, auto-selecting the sender-country
calendar. It returns structured `ValidationError` objects (each is `Stringable`):

```php
use VeryCodeCom\Suus\Validation\ValidationError;

foreach ($client->validate($order) as $error) {
    // $error->code   e.g. "PRJ00372"  (reuses the SUUS code where one exists)
    // $error->field  e.g. "packages[0].returnable"
    // $error->message / (string) $error  human-readable text
    echo "[{$error->code}] {$error}\n";
}
```

`SuusValidationException::getValidationErrors()` returns the same typed objects;
`getErrors(): string[]` still returns plain messages.

### Relax the international-only rules (`ValidationPolicy`)

Strict by default. Turn off the international-only enforcement when your contract
allows it (SUUS still validates server-side):

```php
use VeryCodeCom\Suus\Validation\ValidationPolicy;

$client = new SuusClient($config, policy: ValidationPolicy::relaxed());
// or fine-grained:
$client = new SuusClient($config, policy: new ValidationPolicy(
    enforceInternationalB2B: false,                     // allow B2C internationally
    enforceServiceRouteRestrictions: true,              // keep service/route checks
    enforceInternationalPackagingRestrictions: true,
));
```

### Override the domestic/international decision (`RouteClassifierInterface`)

The classifier decides which routes the **library** treats as international. That
drives both local validation and the generated XML (`<shipper>`/`<consignee>` blocks
+ incoterms emission):

```php
use VeryCodeCom\Suus\Routing\CallableRouteClassifier;

$client = new SuusClient($config, routeClassifier: new CallableRouteClassifier(
    fn (ShipmentOrder $o): bool =>
        ($o->sender->getCountryCode() === 'DE' && $o->receiver->getCountryCode() === 'DE')
            ? false                    // treat DE->DE as domestic in the library
            : $o->isInternational(),   // default rule otherwise
));
```

> ⚠️ **This is a client-side override, not a SUUS-side one.** SUUS classifies each
> shipment on its **own** side from the address country codes: any route where either
> country is not `PL` is an international product, regardless of what the classifier
> returns. Verified against the sandbox — a `DE->DE` order forced to "domestic" is
> still rejected (`BTN0002`: *"Kraj … nie jest dostępny dla kontrahenta typu B2C dla
> produktu Drobnica międzynarodowa … nie określono warunków Incoterms"*). Use this
> seam only when your SUUS **contract/product** already supports the treatment you are
> forcing (e.g. a local in-country contract); it cannot create capability the contract
> does not include. For simply relaxing the local intl-only checks, prefer
> `ValidationPolicy`.

-> [full example](examples/08_validation_and_policies.php)

---

## Business-Day Calendars

The library ships calendars for all countries where SUUS operates.
`SuusClient` defaults to `PolishCalendar` (required +2 PL business days advance notice).

**Auto-detection:** `SuusClient` automatically picks the right calendar based on the sender's country code - no manual configuration needed for standard routes.

| Class                | Country     | Holidays included                                                         |
|----------------------|-------------|---------------------------------------------------------------------------|
| `PolishCalendar`     | PL - Poland      | 9 fixed + 4 Easter-based (Western)                               |
| `GermanCalendar`     | DE - Germany     | 5 federal fixed + 4 Easter-based (federal only, no Bundesland)   |
| `AustriaCalendar`    | AT - Austria     | 9 fixed + 4 Easter-based (Western)                               |
| `SwitzerlandCalendar`| CH - Switzerland | 4 widely-observed fixed + 4 Easter-based (22/26 cantons)         |
| `CzechCalendar`      | CZ - Czech Rep.  | 11 fixed + Good Friday + Easter Monday (Western)                 |
| `SlovakCalendar`     | SK - Slovakia    | 13 fixed + Good Friday + Easter Monday (Western)                 |
| `HungarianCalendar`  | HU - Hungary     | 8 fixed + 4 Easter-based (Western)                               |
| `RomanianCalendar`   | RO - Romania     | 10 fixed + 5 Easter-based (**Orthodox** Easter - differs from Western by up to 5 weeks) |
| `SlovenianCalendar`  | SI - Slovenia    | 12 fixed + Easter Sun/Mon + Whit Sunday (Western)                |

To override (e.g. force a specific calendar regardless of sender country):

```php
use VeryCodeCom\Suus\Calendar\GermanCalendar;

$client = new SuusClient($config, calendar: new GermanCalendar());
```

All calendars implement `BusinessCalendarInterface` and work standalone:

```php
$cal = new RomanianCalendar();
$cal->isBusinessDay(new DateTimeImmutable('2024-05-06'));  // false - Orthodox Easter Monday
$cal->isBusinessDay(new DateTimeImmutable('2024-04-01'));  // true  - Western Easter Mon (not RO holiday)
```

`CalendarFactory::forCountry(string $cc)` returns the right instance for any supported country code; unknown codes fall back to `PolishCalendar`.

-> [full example](examples/06_calendar.php)

---

## Exceptions

All exceptions extend `VeryCodeCom\Suus\Exception\SuusException`.

| Exception                          | Trigger                                                      |
|------------------------------------|--------------------------------------------------------------|
| `SuusValidationException`          | Local validation failed - carries typed `getValidationErrors(): ValidationError[]` (code + field + message) and `getErrors(): string[]` (plain messages) |
| `SuusAuthException`                | SUUS rejects credentials (`DRG00001`)                        |
| `SuusDuplicateReferenceException`  | Reference already exists (`PRJ00310`)                        |
| `SuusApiException`                 | Other SUUS API errors - carries `returnCode` + `errorCodes`; the message also includes SUUS's `returnDesc` for bare codes (e.g. `BTN0001` = service temporarily unavailable) |
| `SuusTransportException`           | Network error or non-200 HTTP response                       |
| `SuusResponseParseException`       | SUUS returned unparseable XML                                |

Every exception extends `SuusException` and exposes `getRawResponse(): ?string`
(the exact XML SUUS returned, when captured) and `getDebugReport(): string`
(message + raw response + stack trace) - see [Debug mode](#debug-mode). To validate
an order **before** sending (and avoid the exception entirely), use
[`SuusClient::validate()`](#validateshipmentorder-order-validationerror).

---

## Dependency Injection & Testing

The client accepts a custom `TransportInterface`, PSR-3 logger, and calendar override:

```php
new SuusClient(
    config:    SuusConfig,
    transport: TransportInterface        = new CurlTransport(),
    logger:    ?Psr\Log\LoggerInterface  = null,
    calendar:  ?BusinessCalendarInterface = null,  // null = auto-detect from sender country
)
```

-> [testing example](examples/07_di_and_testing.php)

---

## Known SUUS API Quirks

1. **`lenghtCm` typo** - SUUS uses `<lenghtCm>` (missing one `t`). Preserved intentionally.
2. **PHP's `SoapClient` is incompatible** - SUUS uses RPC/encoded SOAP 1.1. This library uses raw cURL with manually constructed XML.
3. **Response namespace quirk** - SUUS SOAP responses swap `xmlns:cw` and `xmlns:ns1`. Child elements carry no namespace prefix.
4. **`getEvents` / `getDocument` always fail in sandbox** - Only `addOrder` returns real data in the test environment.
5. **Loading date minimum** - SUUS requires **+2 Polish business days** advance notice.
6. **`<auth>` in every body** - Unlike most SOAP services, SUUS embeds the auth block inside every operation's body, not in the SOAP header.

---

## Running Tests

```bash
composer install

# Unit tests (no network required)
vendor/bin/phpunit --testsuite unit

# Integration tests against the real SUUS sandbox
SUUS_SANDBOX=1 SUUS_LOGIN=ws_xxx SUUS_PASSWORD=xxx \
  vendor/bin/phpunit --testsuite integration

# Manual sandbox smoke test (creates a real shipment, prints raw SOAP request/response)
SUUS_LOGIN=ws_xxx SUUS_PASSWORD=xxx php test_sandbox.php
```

The smoke test (`test_sandbox.php`) hits the sandbox endpoint directly and prints the full XML exchange - useful for verifying credentials and connectivity without running the full test suite. A successful run looks like:

```
--- SUCCESS ---
Shipment No : OPLKRI2600931
Reference   : TEST-20260603110000
Tracking URL: https://portal.suus.com/order-details/OPLKRI2600931
```

---

## License

[Apache License 2.0](LICENSE) - see [NOTICE](NOTICE) for attribution requirements.

You may use, distribute, and modify this library freely. You must retain the `NOTICE` file and copyright notices in any redistribution or derivative work.

---

*Built by [Very Code](https://very-code.com). Contributions welcome - open an issue or PR.*
