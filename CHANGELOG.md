# Changelog

All notable changes to `very-code-com/suus-php` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [1.1.0] - 2026-08-19

Fixes the read side of the API. `getEvents`, `getColliNo` and `getDocument` were all
built with an envelope SUUS does not accept, so every one of them answered
`PRJ000001` ("no order found for the given waybill number or reference") for orders
that demonstrably existed. `addOrder` was unaffected, which is what hid the problem:
shipments booked correctly and only reading was dead. Verified against the live
endpoint and against the SUUS WebApi specification (WS PK 1.0, sections 5.2-5.4).

### Fixed

- **`getEvents` and `getColliNo` now send the documented `<shipments><shipment>`
  wrapper.** Both take an `ArrayOfShipments`, never a bare `<shipmentNo>` (spec 5.2 /
  5.4); sent flat, the shipment list arrives empty and SUUS reports the order as not
  found. `fetchStatus()` returned zero events for every shipment, so a polling job
  saw no progress ever.
- **`getDocument` now names the document symbol `<document>`, not `<documentType>`**
  (spec 5.3). SUUS saw no document symbol and answered `PRJ000001`, which reads as
  "order not found" and points every investigation at the order rather than at the
  request. Labels could not be downloaded at all.
- **`ResponseParser::colliNumbers()` read one level too high.** The `<colliNo>`
  element in a `getColliNo` response is an `ArrayOfColli` wrapper holding
  `<colli><colliNo>` leaves (spec 5.4); reading the wrapper concatenated every child,
  so a six-package shipment yielded one run-together string instead of six numbers.
  A single-package shipment happened to come out correct, which kept this latent.
- **`fetchStatus()` and `getColliNumbers()` no longer swallow SUUS errors.** A
  `success=false` response raised nothing and came back as an empty result, so a
  rejected request was indistinguishable from a shipment with no events. Both now
  raise `SuusApiException` with the return code and description, as
  `fetchDocument()` already did.

### Added

- `fetchDocument()` accepts `array $colliNumbers` to request the label for one or
  several specific packages rather than the shipment's whole set (spec 5.3). Pass
  numbers from `getColliNumbers()`; left empty, SUUS returns every label.
- `fetchLoadingList(string $masterNo)` - the collective loading list is the one
  document keyed by the master waybill number rather than by shipment, and could not
  be requested before.
- Reference-keyed variants of every read call, since an integration usually holds its
  own reference rather than the SUUS waybill number and the spec treats the two as
  interchangeable: `fetchStatusByReference()`, `fetchDocumentByReference()`,
  `getColliNumbersByReference()`.

- Integration coverage for the read side: the suite now creates a three-package order
  and reads its colli numbers and documents back, which is the only way to tell "SUUS
  cannot find this order" apart from "SUUS could not read the request" - both answer
  `PRJ000001`. Verified green against the sandbox.

### Notes

- `PRJ000001` from SUUS means "I could not find that order" **or** "I could not read
  your request". An order visible in the portal that the API cannot find means the
  envelope is wrong.
- `getEvents` lags `addOrder`. SUUS registers the first event (`J_CR`) asynchronously a
  few minutes after the order, so a just-created shipment legitimately answers
  `PRJ000001` for a while. This is what made the malformed envelope look like a sandbox
  limitation for so long.
- Colli numbers do not come back in a stable order between calls. Treat the result of
  `getColliNumbers()` as a set; never map a colli number to a package by index.
- No signature is broken: the new parameters are all optional and appended.

---

## [1.0.0] - 2026-08-14

Initial public release.

### Added

#### Client & configuration

- `SuusClient` - main API client with named constructors `::sandbox()` / `::production()`,
  accepting optional `ValidationPolicy`, `RouteClassifierInterface`, calendar and PSR-3
  logger overrides.
- `createShipment()` - SUUS `addOrder` with full local validation before the API call.
- `fetchStatus()` - SUUS `getEvents` with a normalized `ShipmentStatus` enum plus the
  full event history.
- `fetchDocument()` / `fetchLabel()` - SUUS `getDocument` returning raw PDF bytes.
- `getColliNumbers()` - SUUS `getColliNo` for per-package labels.
- `SuusClient::validate(ShipmentOrder): ValidationError[]` - pre-flight validation that
  runs the exact checks `createShipment()` performs, without any network call, and
  auto-selects the sender-country business-day calendar.
- `SuusConfig` with `::sandbox()` / `::production()` named constructors plus `fromEnv()`
  and `fromArray()`, covering `sandbox`, `timeout`, `connectTimeout` and `debug`.
- **Debug mode** - `SuusConfig` `debug` flag (constructor arg, `SUUS_DEBUG` env var, or
  `'debug'` array key). When enabled, the client logs a full debug report (message + raw
  SUUS response + stack trace) at `error` level for every failure, which is what makes
  bare codes such as `BTN0001` diagnosable.

#### Validation

- **Typed validation errors** - `VeryCodeCom\Suus\Validation\ValidationError`
  (`message` + `field` + `code`, `Stringable`) and `ValidationCode` (stable codes,
  reusing the exact SUUS codes from the WebApi docs where one exists, e.g.
  `PRJ00372` / `PRJ00373` / `PRJ00351`). `SuusValidationException::getValidationErrors()`
  returns them; `getErrors(): string[]` returns their string form.
- **`ValidationPolicy`** (`VeryCodeCom\Suus\Validation`) - injectable into `SuusClient`
  (`policy:` arg). Toggles the route-shaped rules (`enforceInternationalB2B`,
  `enforceServiceRouteRestrictions`, `enforceInternationalPackagingRestrictions`).
  `ValidationPolicy::strict()` is the default; `ValidationPolicy::relaxed()` turns them
  all off. SUUS still validates server-side.
- `ShipmentValidator` (`@internal`) - pre-flight validation of loading date, incoterms,
  weight/package limits, `currency` (3 chars) and `costGroup` (<=20) lengths.
- Route-restricted rules enforced locally and confirmed against the SUUS WebApi
  documentation (WS PK 1.0):
  - international orders require `incoterms` and `orderType` B2B;
  - international orders reject domestic-only services (`StdAwizacjaSms`,
    `StdWniesienie2`, `StdDokumentyZwrotneINiezwrotneGrid2`) and returnable/stackable
    packaging (`PRJ00372` / `PRJ00373`);
  - domestic orders reject the international-only document-return service
    (`StdDokumentyZwrotneINiezwrotneGrid3`);
  - `freight` and `currency` are optional but must be provided together (`PRJ00387`).

#### Routing

- **`RouteClassifierInterface`** (`VeryCodeCom\Suus\Routing`) with
  `DefaultRouteClassifier` and `CallableRouteClassifier` - injectable into `SuusClient`
  (`routeClassifier:` arg) to redefine which routes the library treats as international.
  Drives both validation and the generated XML (`<shipper>` / `<consignee>` blocks +
  incoterms emission). This is a client-side override only: SUUS still classifies each
  shipment server-side from the address country codes, so it cannot make SUUS treat a
  non-`PL` route as domestic (verified against the sandbox); use it only when your SUUS
  contract/product already supports the forced treatment.

#### Calendars

- `BusinessCalendarInterface` + `AbstractCalendar` with `isBusinessDay()`,
  `addBusinessDays()`, `minLoadingDate()` and both Western (`easterDate()`) and Orthodox
  (`orthodoxEasterDate()`, Julian + 13-day correction) Easter algorithms.
- Calendars for PL, DE, AT, CH, CZ, SK, HU, RO and SI, resolved from the sender country
  via `CalendarFactory::forCountry()`. `PolishCalendarInterface` is kept for backwards
  compatibility.

#### Services & DTOs

- Typed additional services: `CodService`, `InsuranceService`, `EmailNotificationService`,
  `SmsNotificationService`, `LiftService`, `PalletTruckService`, `InsideDeliveryService`,
  and `DocumentReturnDomesticService` (`StdDokumentyZwrotneINiezwrotneGrid2`, domestic
  only) / `DocumentReturnInternationalService`
  (`StdDokumentyZwrotneINiezwrotneGrid3`, international only) sharing
  `AbstractDocumentReturnService` with `TAG_*` / `DOC_*` constants.
- `InsuranceService` always emits `int01=1` - the mandatory SUUS "goods not in excluded
  groups" declaration (missing it causes `PRJ000293`); toggle with
  `bool $confirmGoodsNotExcluded = true`.
- DTOs: `Address`, `Package`, `ShipmentOrder` (including `costGroup`, `freight` and
  `currency` for international orders), `ShipmentResult`, `StatusResult`, `StatusEvent`,
  `DeliveryPoint`.
- Enums: `DocumentType`, `Incoterm`, `OrderType`, `PackageSymbol`, `ShipmentCategory`,
  `ShipmentStatus`.

#### Internals & errors

- `SoapEnvelopeBuilder` - raw cURL SOAP XML builder (works around PHP `SoapClient`
  incompatibility with SUUS's RPC/encoded SOAP 1.1 and its in-body `<auth>` block).
- `ResponseParser` - DOMXPath-based response parser handling the SUUS namespace reversal
  quirk, including `returnDesc()` so bare SUUS codes without an `errorCodes` list (e.g.
  `BTN0001`) surface a human-readable reason.
- `StatusMapper` - maps 14 native SUUS event codes to the `ShipmentStatus` enum.
- `TransportInterface` + `CurlTransport` - transport abstraction for easy test mocking.
- Full exception hierarchy: `SuusException` -> `SuusValidationException`,
  `SuusAuthException`, `SuusDuplicateReferenceException`, `SuusApiException`,
  `SuusTransportException`, `SuusResponseParseException`.
- `SuusException::getRawResponse(): ?string` - the exact XML SUUS returned (always
  captured for API/parse/transport errors, independent of the debug flag).
- `SuusException::getDebugReport(): string` - bundles the message, raw response and stack
  trace for logging or developer-facing output. Error surfacing is centralised through a
  single internal `fail()` path so this is applied consistently across all API methods.
- PSR-3 logger injection with `NullLogger` as the default.

#### Tooling & docs

- Runnable examples `01`-`08`, including `08_validation_and_policies.php` covering
  `validate()`, `ValidationPolicy` and `RouteClassifierInterface`.
- 355 unit tests / 540 assertions (no network), 3 live sandbox integration tests
  (skipped unless `SUUS_SANDBOX=1`), PHPStan level 8 clean.
- GitHub Actions CI plus a gated sandbox integration workflow.

### Notes

- Preserves the SUUS API typo: the package length field is `<lenghtCm>` (not `<lengthCm>`).
- Uses raw cURL + manually built SOAP envelopes (PHP's `SoapClient` fails for this WSDL).
- Documents the SUUS response namespace quirk (`xmlns:cw` / `xmlns:ns1` reversal).
- `BTN*` codes are SUUS system errors (service temporarily unavailable), not validation
  failures; data-validation failures use the `DRG*` / `PRJ*` families.

[Unreleased]: https://github.com/very-code-com/suus-php/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/very-code-com/suus-php/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/very-code-com/suus-php/releases/tag/v1.0.0
