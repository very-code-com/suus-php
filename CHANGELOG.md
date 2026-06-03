# Changelog

All notable changes to `very-code-com/suus-php` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

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
- Full exception hierarchy: `SuusException` → `SuusValidationException`, `SuusAuthException`, `SuusDuplicateReferenceException`, `SuusApiException`, `SuusTransportException`, `SuusResponseParseException`
- PHP 8.1 enums: `DocumentType`, `Incoterm`, `OrderType`, `PackageSymbol`, `ShipmentCategory`, `ShipmentStatus`
- PSR-3 logger injection with `NullLogger` as default
- 96 unit tests, 125 assertions
- Integration test for real sandbox (skipped unless `SUUS_SANDBOX=1`)
- GitHub Actions CI (PHP 8.1, 8.2, 8.3)

### Notes

- Preserves the SUUS API typo: package length field is `<lenghtCm>` (not `<lengthCm>`)
- Uses raw cURL + manually built SOAP envelopes (PHP's SoapClient fails for this WSDL)
- Documents the SUUS response namespace quirk (xmlns:cw / xmlns:ns1 reversal)

[Unreleased]: https://github.com/very-code-com/suus-php/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/very-code-com/suus-php/releases/tag/v1.0.0
