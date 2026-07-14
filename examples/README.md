# Examples

Runnable scripts demonstrating every part of the `very-code-com/suus-php` client.

Set your sandbox credentials once, then run any script:

```bash
export SUUS_LOGIN=ws_yourlogin
export SUUS_PASSWORD=your_password

php examples/01_create_shipment.php
```

The scripts fall back to placeholder credentials if the env vars are unset, so
the offline ones (06, 07) run with no configuration at all.

| # | Script | What it shows | Needs network? |
|---|--------|---------------|----------------|
| 01 | [`01_create_shipment.php`](01_create_shipment.php) | Domestic PL->PL order with fully-populated addresses, mixed package types, auto-computed dates, and handling of every exception type | Yes (sandbox) |
| 02 | [`02_international_shipment.php`](02_international_shipment.php) | International DE->PL order: incoterms, B2B rule, category, `freight`/`currency`, `costGroup`, B2B-only services | Yes (sandbox) |
| 03 | [`03_fetch_status.php`](03_fetch_status.php) | Tracking via `getEvents`: normalized `ShipmentStatus`, event history, exhaustive `match` on status | Yes (**production** - sandbox returns `PRJ000001`) |
| 04 | [`04_fetch_document.php`](04_fetch_document.php) | Downloading labels / shipping order / loading list as PDF, plus per-package colli numbers | Yes (**production** - sandbox returns `PRJ000001`) |
| 05 | [`05_additional_services.php`](05_additional_services.php) | The full additional-services catalogue (COD, insurance, e-mail/SMS pre-advice, lift, pallet truck, inside delivery) on a domestic B2C order | Yes (sandbox) |
| 06 | [`06_calendar.php`](06_calendar.php) | Business-day calendars for all 9 countries, holiday comparison, Orthodox Easter (RO), `minLoadingDate`, standalone scheduling helpers | No |
| 07 | [`07_di_and_testing.php`](07_di_and_testing.php) | Dependency injection: stub `TransportInterface` (no network), PSR-3 logger, calendar override - the pattern used by the unit tests | No |

## Notes

- **Sandbox vs production** - `getEvents` / `getDocument` / `getColliNo` only
  return real data on production; in the sandbox they always answer `PRJ000001`.
  Only `addOrder` (create shipment) returns usable data in the sandbox.
- **Unique references** - the create examples derive their `reference` from the
  current timestamp so you can re-run them without hitting `PRJ00310`
  (duplicate reference).
- **International routes** are B2B-only, require `incoterms`, and cannot use
  returnable/stackable packaging or the B2C domestic-only services.

See the project [README](../README.md) for the full API reference.
