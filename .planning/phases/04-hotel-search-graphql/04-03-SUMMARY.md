---
phase: 04-hotel-search-graphql
plan: "03"
subsystem: dotw-gap-closure
tags: [gap-closure, requirements, currency, dotw, graphql]
dependency_graph:
  requires: [04-02-SUMMARY.md]
  provides: [SEARCH-03-complete, SEARCH-06-deferred, company-currency-lookup]
  affects: [app/GraphQL/Queries/DotwSearchHotels.php, app/Models/CompanyDotwCredential.php]
tech_stack:
  added: []
  patterns: [CompanyDotwCredential DB lookup, ternary chain for input priority]
key_files:
  created: []
  modified:
    - .planning/REQUIREMENTS.md
    - database/migrations/2026_02_21_100001_create_company_dotw_credentials_table.php
    - app/Models/CompanyDotwCredential.php
    - app/GraphQL/Queries/DotwSearchHotels.php
decisions:
  - currency column is plain string (not encrypted) — not a credential, not sensitive
  - ternary chain priority: input.currency (trim) > company DB currency > 'USD' fallback
  - SEARCH-06 traceability moved to Phase 5 (partial Phase 4, deferred metadata)
metrics:
  duration: "~2 minutes"
  completed: "2026-02-21"
  tasks: 3
  files_modified: 4
---

# Phase 4 Plan 03: Gap Closure — REQUIREMENTS.md, Currency Column, DB Currency Lookup Summary

**One-liner:** Fixed SEARCH-06 requirement tracking (unchecked, deferred to Phase 5) and SEARCH-03 implementation gap (company currency from DB, not hardcoded USD).

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Fix REQUIREMENTS.md — uncheck SEARCH-06 and annotate SEARCH-03 | `02268336` | `.planning/REQUIREMENTS.md` |
| 2 | Add currency column to migration and model | `b93b6905` | `database/migrations/2026_02_21_100001_create_company_dotw_credentials_table.php`, `app/Models/CompanyDotwCredential.php` |
| 3 | Resolve company currency in DotwSearchHotels from DB | `6914ecf4` | `app/GraphQL/Queries/DotwSearchHotels.php` |

## Gaps Closed

### SEARCH-06 — Requirement Tracking Correction
- **Was:** `[x]` checked (incorrectly marked complete)
- **Now:** `[ ]` unchecked with deferral note: "hotel_code and cheapest rates satisfied in Phase 4; name, city, rating, location, image_url deferred to Phase 5 getRoomRates (DOTW searchhotels command does not return hotel metadata)"
- **Traceability row updated:** Phase 5, Partial status
- **No code change needed:** The requirement was correctly split during implementation; only the tracking was wrong

### SEARCH-03 — Currency Default Implementation
- **Was:** `DotwSearchHotels.php` hardcoded `'USD'` as fallback when `input.currency` is absent
- **Root cause:** `company_dotw_credentials` table had no `currency` column
- **Fix — migration:** Added `$table->string('currency', 3)->default('USD')` between `markup_percent` and `is_active`
- **Fix — model:** Added `'currency'` to `$fillable`, `'currency' => 'string'` to `$casts`, and `@property string $currency` PHPDoc
- **Fix — resolver:** Replaced `trim($input['currency'] ?? 'USD')` with `trim($input['currency'] ?? '') ?: (CompanyDotwCredential::where('company_id', $companyId)->value('currency') ?? 'USD')`
- **Priority chain:** Explicit input > company DB setting > 'USD' last resort

## Decisions Made

1. **currency column is plain string** — not encrypted. Currency code (e.g., "USD", "KWD") is not a credential; no need for Crypt::encrypt(). Plain `string(3)` with default 'USD'.

2. **Ternary chain for currency priority** — `trim($input['currency'] ?? '') ?: (DB lookup ?? 'USD')` is idiomatic PHP: empty string from trim() is falsy so the Elvis operator falls through to the DB lookup. Clean single-line expression.

3. **SEARCH-06 traceability row updated to Phase 5** — The partial delivery (hotel_code + rates) is correct Phase 4 behavior. The metadata fields (name, city, rating, location, image_url) require `getRoomRates` which is Phase 5. Traceability now reads "Phase 5 | Partial".

## Verification Results

All 7 criteria from plan verified:

1. `grep "SEARCH-06" .planning/REQUIREMENTS.md` — shows `[ ]` unchecked with deferral note
2. `grep "SEARCH-03" .planning/REQUIREMENTS.md` — shows `[x]` with company_dotw_credentials.currency reference
3. `grep "currency" migration` — shows `$table->string('currency', 3)->default('USD')`
4. `grep "currency" model` — appears in fillable, casts, and PHPDoc
5. `grep "CompanyDotwCredential" DotwSearchHotels.php` — shows import + DB lookup at line 7 and line 82
6. `./vendor/bin/pint DotwSearchHotels.php --test` — PASS, no changes
7. `php -l DotwSearchHotels.php` — no syntax errors

## Deviations from Plan

**1. [Rule 1 - Enhancement] Updated traceability table for SEARCH-06**
- **Found during:** Task 1
- **Issue:** The plan said to update the checkbox and requirement text, but the traceability table at the bottom of REQUIREMENTS.md still showed "SEARCH-06 | Phase 4 | Complete" — inconsistent with the unchecked requirement
- **Fix:** Updated traceability row to "Phase 5 | Partial — hotel_code + rates done Phase 4; metadata deferred to Phase 5"
- **Files modified:** `.planning/REQUIREMENTS.md`
- **Commit:** `02268336`

## Self-Check: PASSED

Files confirmed modified:
- `.planning/REQUIREMENTS.md` — SEARCH-06 unchecked, SEARCH-03 annotated, traceability row updated
- `database/migrations/2026_02_21_100001_create_company_dotw_credentials_table.php` — currency column present
- `app/Models/CompanyDotwCredential.php` — currency in fillable, casts, PHPDoc
- `app/GraphQL/Queries/DotwSearchHotels.php` — CompanyDotwCredential import + DB lookup

Commits verified:
- `02268336` — fix(04-03): correct SEARCH-06 status and annotate SEARCH-03
- `b93b6905` — feat(04-03): add currency column to migration and model
- `6914ecf4` — feat(04-03): resolve company currency from DB in DotwSearchHotels
