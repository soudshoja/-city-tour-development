---
plan: 07-01
phase: 07-error-hardening-and-circuit-breaker
status: complete
completed: 2026-02-21
requirements: [ERROR-02]
---

# Plan 07-01 Summary: Typed Timeout Exception

## What Was Built

Created `DotwTimeoutException` and wired it through `DotwService::post()` so resolvers can distinguish API timeouts from credential errors and generic API failures. All four DOTW resolvers now catch `DotwTimeoutException` before `RuntimeException` and return `API_TIMEOUT` + `RETRY`.

## Key Files

### Created
- `app/Exceptions/DotwTimeoutException.php` — Typed exception class extending `\Exception` (NOT `\RuntimeException`). The class identity is the discriminator.

### Modified
- `app/Services/DotwService.php` — Added `ConnectionException` import + new catch block in `post()` before generic `Exception` catch. Logs `timeout_seconds` and `company_id` (no credentials). Default timeout fallback changed from 120 to 25.
- `config/dotw.php` — Default timeout from `120` → `25` seconds per ERROR-02 SLA. Comment updated.
- `app/GraphQL/Queries/DotwSearchHotels.php` — DotwTimeoutException catch added before RuntimeException catch.
- `app/GraphQL/Queries/DotwGetRoomRates.php` — DotwTimeoutException catch added before RuntimeException catch.
- `app/GraphQL/Mutations/DotwBlockRates.php` — DotwTimeoutException catch added before RuntimeException catch.
- `app/GraphQL/Queries/DotwGetCities.php` — DotwTimeoutException catch added before RuntimeException catch. Also upgraded from string-matching credential detection to proper `RuntimeException` catch (see Plan 07-03).

## Catch Order (All 4 Resolvers)
```
DotwTimeoutException → RuntimeException → \Exception
```

## Verification Results
- All 6 files pass `php -l` (no syntax errors)
- `grep "ConnectionException"` shows import and catch in DotwService::post()
- `grep "DOTW_TIMEOUT', 25"` returns match in config/dotw.php
- All 4 resolver files contain DotwTimeoutException import and API_TIMEOUT error response
- Lighthouse schema prints without fatal errors — all DOTW operations resolve

## Notes
- DotwTimeoutException intentionally extends `\Exception` not `\RuntimeException` — this ensures it is caught by the new `DotwTimeoutException` catch block, NOT swallowed by the `RuntimeException` catch (credential errors)
- Pint fixed 1 style issue in DotwService (new_with_parentheses, unary_operator_spaces)
