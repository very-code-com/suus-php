# Changelog

All notable changes to `very-code-com/suus-php` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [1.1.0] - 2026-07-14

### Added

- **Debug mode** - `SuusConfig` gains a `debug` flag (constructor arg, `SUUS_DEBUG`
  env var, or `'debug'` array key). When enabled, the client logs a full debug
  report (message + raw SUUS response + stack trace) at `error` level for every
  failure, ideal for diagnosing unrecognised errors such as bare `BTN0001`.
- `SuusException::getRawResponse(): ?string` - the exact XML SUUS returned (always
  captured for API/parse/transport errors, independent of the debug flag).
- `SuusException::getDebugReport(): string` - bundles the message, raw response and
  stack trace for logging or developer-facing output.
- `ShipmentOrder`: optional `costGroup`, `freight` and `currency` header fields for
  full international-order support (freight/currency are serialized only for
  international routes and must be provided together, per SUUS rule PRJ00387).
- `ResponseParser::returnDesc()` - parses the `<returnDesc>` element so bare SUUS
  codes without an `errorCodes` list (e.g. `BTN0001`) surface a human-readable
  reason in the thrown `SuusApiException` message.
- Validation: international (non-PL->PL) orders now require `orderType` B2B, reject
  a lone `freight`/`currency`, and enforce `currency` (3 chars) / `costGroup` (<=20)
  length limits.

### Changed

- `InsuranceService`: `int01` is now always emitted as `"1"` - the mandatory SUUS
  "goods not in excluded groups" declaration (missing it caused PRJ000293). The
  misleading `?string $goodsDeclaration` constructor argument is replaced by
  `bool $confirmGoodsNotExcluded = true`.
- Error surfacing is centralised through a single internal `fail()` path so raw
  responses and debug logging are applied consistently across all API methods.

### Fixed

- `examples/05_additional_services.php`: corrected constructor argument names
  (`additionalServices`, `InsuranceService(amount:)`, `CodService(amount:)`) and
  added the EUR-pallet dimensions / sender e-mail required by SUUS.

---

## [1.0.0] - 2025-06-10

### Added

- `SuusClient` - main API client with named constructors `::sandbox()` / `::production()`
- `createShipment()` - SUUS `addOrder` with local validation before the API call
- `fetchStatus()` - SUUS `getEvents` with normalized `ShipmentStatus` enum + full event history
- `fetchDocument()` / `fetchLabel()` - SUUS `getDocument` returning raw PDF bytes
- `getColliNumbers()` - SUUS `getColliNo` for per-package labels
- `SuusConfig` with `::sandbox()` / `::production()` named constructors
- `PolishCalendar` - business day calculator with Easter algorithm (weekends + 9 fixed holidays + 4 movable)
- `ShipmentValidator` - pre-flight validation (loading date, incoterms, weight/package limits)
- `StatusMapper` - maps 14 native SUUS event codes to `ShipmentStatus` enum
- `SoapEnvelopeBuilder` - raw cURL SOAP XML builder (works around PHP SoapClient incompatibility)
- `ResponseParser` - DOMXPath-based response parser (handles SUUS namespace reversal quirk)
- `TransportInterface` + `CurlTransport` - transport abstraction for easy test mocking
- Full exception hierarchy: `SuusException` -> `SuusValidationException`, `SuusAuthException`, `SuusDuplicateReferenceException`, `SuusApiException`, `SuusTransportException`, `SuusResponseParseException`
- PHP 8.1 enums: `DocumentType`, `Incoterm`, `OrderType`, `PackageSymbol`, `ShipmentCategory`, `ShipmentStatus`
- PSR-3 logger injection with `NullLogger` as default
- 96 unit tests, 125 assertions
- Integration test for real sandbox (skipped unless `SUUS_SANDBOX=1`)
- GitHub Actions CI (PHP 8.1, 8.2, 8.3)

### Notes

- Preserves the SUUS API typo: package length field is `<lenghtCm>` (not `<lengthCm>`)
- Uses raw cURL + manually built SOAP envelopes (PHP's SoapClient fails for this WSDL)
- Documents the SUUS response namespace quirk (xmlns:cw / xmlns:ns1 reversal)

[Unreleased]: https://github.com/very-code-com/suus-php/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/very-code-com/suus-php/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/very-code-com/suus-php/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/very-code-com/suus-php/releases/tag/v1.0.0
