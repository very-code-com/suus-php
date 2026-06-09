# suus-php

**PHP client library for the SUUS Logistics (Rohlig Logistics) SOAP API.**

The first open-source PHP package for SUUS/Rohlig freight integration - create shipments, track statuses, download documents, and handle multi-country business-day scheduling.

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
$config = SuusConfig::fromArray(['login' => '…', 'password' => '…', 'env' => 'production']);
```

| Env variable          | Required | Default      | Description                      |
|-----------------------|----------|--------------|----------------------------------|
| `SUUS_LOGIN`          | yes      | -            | API login (e.g. `ws_yourlogin`)  |
| `SUUS_PASSWORD`       | yes      | -            | API password                     |
| `SUUS_ENV`            | no       | `production` | `sandbox` or `production`        |
| `SUUS_TIMEOUT`        | no       | `30`         | Request timeout (seconds)        |
| `SUUS_CONNECT_TIMEOUT`| no       | `10`         | Connection timeout (seconds)     |

---

## API Reference

### `createShipment(ShipmentOrder $order): ShipmentResult`

Creates a shipment via SUUS `addOrder`. Validates locally first.  
Returns `ShipmentResult` with `shipmentNo`, `reference`, `trackingUrl`.

→ [full example](examples/01_create_shipment.php) · [international routes](examples/02_international_shipment.php) · [additional services](examples/05_additional_services.php)

### `fetchStatus(string $shipmentNo): StatusResult`

Polls events via SUUS `getEvents`.  
Returns `StatusResult` with `status` (`ShipmentStatus` enum), `rawLatestCode`, `events[]`.

> **Note:** `getEvents` always returns `PRJ000001` in sandbox mode.

→ [full example](examples/03_fetch_status.php)

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

Convenience shortcut for `fetchDocument(…, DocumentType::Label)`.

→ [full example](examples/04_fetch_document.php)

### `getColliNumbers(string $shipmentNo): array`

Returns per-package (colli) tracking numbers for multi-package shipments.

---

## Package Types

| `PackageSymbol` | Description              |
|-----------------|--------------------------|
| `KAR`           | Cardboard box            |
| `EUR`           | EUR pallet               |
| `JED`           | Disposable pallet        |
| `PLT`           | Standard pallet          |
| `SKR`           | Crate / chest            |
| `ROL`           | Roll                     |
| `DPL`           | Double pallet            |
| `DHP`           | Large heavy package      |
| `CHP`           | Heavy package            |
| `AGD`           | Appliance                |
| `INN`           | Other                    |
| `WIA`           | Bucket                   |
| `HB`            | Half-block               |

---

## International Routes & Incoterms

`incoterms` is required whenever sender or receiver is not in Poland.

| Route    | Incoterms required | Notes                       |
|----------|--------------------|-----------------------------|
| `PL→PL`  | No                 | Domestic, no restrictions   |
| `PL→DE`  | Yes                |                             |
| `PL→AT`  | Yes                |                             |
| `PL→CH`  | Yes                | Swiss customs docs required |
| `DE→DE`  | No                 |                             |
| `DE→AT`  | Yes                |                             |
| `DE→CH`  | Yes                | Swiss customs docs required |

Supported incoterms: `EXW`, `FCA`, `FAS`, `FOB`, `CFR`, `CIF`, `CPT`, `CIP`, `DAP`, `DDP`.

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

→ [full example](examples/06_calendar.php)

---

## Exceptions

All exceptions extend `VeryCodeCom\Suus\Exception\SuusException`.

| Exception                          | Trigger                                                      |
|------------------------------------|--------------------------------------------------------------|
| `SuusValidationException`          | Local validation failed (date, incoterms, package limits)    |
| `SuusAuthException`                | SUUS rejects credentials (`DRG00001`)                        |
| `SuusDuplicateReferenceException`  | Reference already exists (`PRJ00310`)                        |
| `SuusApiException`                 | Other SUUS API errors - contains `returnCode`, `errorCodes`  |
| `SuusTransportException`           | Network error or non-200 HTTP response                       |
| `SuusResponseParseException`       | SUUS returned unparseable XML                                |

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

→ [testing example](examples/07_di_and_testing.php)

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
SUUS_LOGIN=ws_xxx SUUS_PASSWORD=xxx SUUS_ENV=sandbox \
  vendor/bin/phpunit --testsuite integration

# Manual sandbox smoke test (creates a real shipment, prints raw SOAP request/response)
SUUS_LOGIN=ws_xxx SUUS_PASSWORD=xxx php test_sandbox.php
```

The smoke test (`test_sandbox.php`) hits the sandbox endpoint directly and prints the full XML exchange — useful for verifying credentials and connectivity without running the full test suite. A successful run looks like:

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
