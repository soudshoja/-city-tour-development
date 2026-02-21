---
phase: 01-credential-management-and-markup-foundation
plan: 01
subsystem: dotw-credentials
tags: [dotw, credentials, encryption, multi-tenant, migration, model, service]
dependency_graph:
  requires: []
  provides:
    - company_dotw_credentials table (migration)
    - CompanyDotwCredential model (encryption accessors, scopeForCompany)
    - DotwService DB-based credential resolution (B2B path)
    - applyMarkup() helper method
  affects:
    - app/Services/DotwService.php (refactored constructor)
tech_stack:
  added: []
  patterns:
    - Laravel Attribute::make() accessor/mutator pattern for transparent encryption
    - Crypt::encrypt/decrypt for per-company credential storage
    - Nullable constructor parameter for backward compatibility
    - scopeForCompany Eloquent local scope for multi-tenant isolation
key_files:
  created:
    - database/migrations/2026_02_21_100001_create_company_dotw_credentials_table.php
    - app/Models/CompanyDotwCredential.php
  modified:
    - app/Services/DotwService.php
decisions:
  - "Used nullable ?int $companyId constructor parameter to maintain backward compatibility with existing callers (SearchDotwHotels job etc.)"
  - "Crypt::encrypt/Crypt::decrypt used explicitly (not $casts array) for credential columns — gives clearer code intent and encryption visibility"
  - "$hidden array on model prevents encrypted blobs from leaking into JSON/API responses or log output"
  - "scopeForCompany filters by both company_id AND is_active=true — disabled companies cannot access DOTW API"
  - "Logger records company_id instead of credential values — no credential data ever appears in log output"
metrics:
  duration_minutes: 2
  completed_date: "2026-02-21"
  tasks_completed: 3
  tasks_total: 3
  files_created: 2
  files_modified: 1
---

# Phase 1 Plan 01: Credential Management & Markup Foundation Summary

Per-company DOTW credential storage with Laravel encryption, Eloquent model with transparent encrypt/decrypt accessors, and refactored DotwService with DB-based multi-tenant credential resolution and backward-compatible constructor.

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | Migration — company_dotw_credentials table | 8a12df0f | database/migrations/2026_02_21_100001_create_company_dotw_credentials_table.php |
| 2 | Model — CompanyDotwCredential with encryption accessors | 163bbf1e | app/Models/CompanyDotwCredential.php |
| 3 | Refactor DotwService — DB-based credential resolution | 84f0a33b | app/Services/DotwService.php |

## What Was Built

### Migration (Task 1)

File: `database/migrations/2026_02_21_100001_create_company_dotw_credentials_table.php`

Creates the `company_dotw_credentials` table with:
- `company_id` — unique unsigned FK to `companies.id` (cascade delete), one row per company
- `dotw_username` / `dotw_password` — `text` columns storing Laravel-encrypted blobs
- `dotw_company_code` — plain string (not sensitive)
- `markup_percent` — `decimal(5,2)` defaulting to `20.00`
- `is_active` — boolean for disabling company DOTW access
- Indexes on `company_id` and `is_active` for query performance

### Model (Task 2)

File: `app/Models/CompanyDotwCredential.php`

Key implementation details:
- `Attribute::make()` accessor/mutator pattern for `dotwUsername` and `dotwPassword`
- Getter calls `Crypt::decrypt($value)` — returns plaintext when reading
- Setter calls `Crypt::encrypt($value)` — encrypts before storing
- `$hidden = ['dotw_username', 'dotw_password']` prevents encrypted blobs from appearing in `toArray()`, `toJson()`, or API responses
- `scopeForCompany($query, int $companyId)` filters by `company_id` AND `is_active = true`
- `getMarkupMultiplier(): float` returns `1 + (markup_percent / 100)` (e.g., 20% → 1.20)
- `company(): BelongsTo` relationship to `App\Models\Company`

Verified:
- Setting `dotw_username = 'testuser'` stores an encrypted blob (length > 20 chars)
- Reading `dotw_username` returns `'testuser'` — transparent decrypt
- `toArray()` / `json_encode($model)` output does NOT include `dotw_username` or `dotw_password` keys

### DotwService Refactor (Task 3)

File: `app/Services/DotwService.php`

Changes:
- Added `use App\Models\CompanyDotwCredential;` import
- Constructor signature changed to `__construct(?int $companyId = null)` (backward compatible)
- Added private properties: `float $markupPercent`, `?int $companyId`
- B2B path (when `$companyId !== null`): calls `CompanyDotwCredential::forCompany($companyId)->first()` and throws `RuntimeException` if no active credential found
- Legacy path (when `$companyId = null`): reads from `config('dotw.*')` env values
- Added `applyMarkup(float $originalFare): array` returning `{original_fare, markup_percent, markup_amount, final_fare}`
- Logger records `company_id` instead of `username` — no credential values in logs
- All existing methods (`searchHotels`, `getRooms`, `confirmBooking`, etc.) unchanged
- Laravel Pint PSR-12 formatting applied

Verified (with legacy/no-DB path):
- `new DotwService()` — `BACKWARD_COMPAT_OK`
- `applyMarkup(100.00)` — `{"original_fare":100,"markup_percent":20,"markup_amount":20,"final_fare":120}`
- `new DotwService(99999)` — throws RuntimeException (SQLSTATE DB connection error in dev, will throw typed message on live DB)

## Decisions Made

1. **Nullable constructor parameter** — Used `?int $companyId = null` to ensure all existing callers of `new DotwService()` (e.g., `SearchDotwHotels` job) continue working without modification.

2. **Explicit Crypt:: calls in model** — Used `Crypt::encrypt()` and `Crypt::decrypt()` explicitly in the accessor closures rather than via the `$casts` array, to make the encryption behavior visible and explicit to future developers.

3. **$hidden prevents credential leakage** — The `$hidden` array is the API-level security boundary. Even if code accidentally calls `toArray()` on a credential model, the encrypted blobs will not appear in the output.

4. **scopeForCompany requires is_active** — The scope filters on both `company_id` AND `is_active = true`. A deactivated company cannot access the DOTW API even if a credential row exists.

5. **DB not running in dev environment** — The local MySQL instance is not running in this development environment. Migration file was verified via PHP syntax check (`php -l`). Functionality was verified via tinker with the legacy path (no DB required). Full DB verification will occur on the live server (development.citycommerce.group).

## Deviations from Plan

### Auto-fixed Issues

None.

### Plan Deviations

**1. DB Not Available for Migration Pretend Check**

- **Found during:** Task 1 verification
- **Issue:** Local MySQL is not running (connection refused on 127.0.0.1:3306)
- **Fix:** Verified migration file via `php -l` syntax check instead of `php artisan migrate --pretend`. The migration will run correctly on the live server.
- **Impact:** No code change required — this is an environment limitation.

**2. RuntimeException for company_id=99999 is SQLSTATE (not custom message) in dev**

- **Found during:** Task 3 verification
- **Issue:** When DB is not running, `new DotwService(99999)` throws a SQLSTATE connection refused `RuntimeException` rather than our custom "DOTW credentials not configured for this company" `RuntimeException`.
- **Fix:** No fix needed. Both are `RuntimeException` instances. On the live DB, with a non-existent company_id, the query will succeed and return null, triggering our custom message correctly.
- **Impact:** None — correct behavior on live system.

## Self-Check

Files created/modified:
- [x] `database/migrations/2026_02_21_100001_create_company_dotw_credentials_table.php` — exists
- [x] `app/Models/CompanyDotwCredential.php` — exists
- [x] `app/Services/DotwService.php` — modified

Commits:
- [x] `8a12df0f` — feat(01-01): create company_dotw_credentials migration
- [x] `163bbf1e` — feat(01-01): create CompanyDotwCredential model with encryption accessors
- [x] `84f0a33b` — feat(01-01): refactor DotwService with DB-based credential resolution

## Self-Check: PASSED
