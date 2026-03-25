# Research: Phase 1 — Credential Management & Markup Foundation

**Date:** 2026-02-21
**Level:** 0 (no external discovery needed — all patterns confirmed in existing codebase)

---

## Existing DOTW Infrastructure (already in place)

### What exists before Phase 1:
- `app/Services/DotwService.php` (1,407 lines) — fully functional, uses `config('dotw.*')` for credentials
- `config/dotw.php` — reads from env: DOTW_USERNAME, DOTW_PASSWORD, DOTW_COMPANY_CODE
- `database/migrations/2026_02_21_033317_create_dotw_prebooks_table.php` — dotw_prebooks (no company_id)
- `database/migrations/2026_02_21_033318_create_dotw_rooms_table.php` — dotw_rooms
- `app/Models/DotwPrebook.php` — no company_id, no multi-tenant isolation
- `app/Models/DotwRoom.php` — room occupancy model

### What Phase 1 must add:
- New migration: `company_dotw_credentials` table (company_id FK, encrypted username/password, company_code, markup_percent)
- New model: `CompanyDotwCredential` with encryption accessors
- Refactored DotwService constructor: takes `company_id`, loads from DB
- Admin API endpoints: POST/GET `/api/admin/companies/{id}/dotw-credentials`

---

## Encryption Strategy

**Decision: Use Laravel's built-in `encrypt()` / `decrypt()` helpers (backed by `Crypt` facade)**

Rationale:
- Already used in User model for `two_factor_code` (commented out but pattern confirmed)
- Uses APP_KEY from .env as encryption key — no new key management needed
- Produces AES-256-CBC encrypted blobs automatically
- Survives `php artisan key:generate` rotation (decryption fails gracefully, forces re-entry)

Implementation: Eloquent Attribute accessors on `CompanyDotwCredential`:
- Setter calls `Crypt::encrypt($value)` before storing
- Getter calls `Crypt::decrypt($value)` when reading
- Columns are `text` type (encrypted blob is longer than `varchar(255)`)

**NOT using Laravel's `Encrypted` cast** — the cast does not support nullable decrypt gracefully in Laravel 11 when the column might be set by raw SQL in tests. Manual accessors are explicit.

**Hidden from serialization**: `$hidden = ['dotw_username', 'dotw_password']` on the model ensures encrypted (or decrypted) values never appear in toArray() / toJson() — protecting against credential leakage in API responses and log dumps.

---

## Company Authentication Context

**How the authenticated user's company is resolved (from User model):**

```php
// User::company() is an Attribute (not a relationship) that cascades:
// 1. User directly owns a Company (hasOne)
// 2. User is Accountant → accountant.branch.company
// 3. User is Agent → agent.branch.company
// 4. User is Branch owner → branch.company
```

**For Phase 1**, the admin credential endpoint uses `{companyId}` as a URL parameter — no auth middleware yet. The company_id is trusted from the URL path. Full auth enforcement comes in Phase 8.

**For downstream phases** (4-6) where DotwService is instantiated mid-request: the calling code must pass `auth()->user()->company->id` or the GraphQL context's company_id. Phase 1 does not implement this — it only provides the infrastructure.

---

## DotwService Refactor Scope

**Minimal refactor**: Only the constructor changes. All methods (searchHotels, getRooms, confirmBooking, etc.) remain identical — they use `$this->username`, `$this->passwordMd5`, `$this->companyCode` which are now populated from DB instead of env.

**Breaking change**: Any existing code that does `new DotwService()` (no args) will break. Current callsites:
- `app/GraphQL/Queries/SearchDotwHotels.php` (865+ lines) — uses `new DotwService()`

These callsites are NOT modified in Phase 1 — they are updated when each operation phase is planned (Phases 4-6). For now, SearchDotwHotels.php continues using env-based credentials via the old instantiation pattern. This is acceptable because SearchDotwHotels.php is the existing pre-DOTW-v1.0 code, not part of the new B2B module.

---

## Admin API Pattern

**Pattern confirmed from existing routes/api.php:**
- No auth middleware on most admin/operational routes (matching existing project style)
- Company lookup via `Company::findOrFail($id)` pattern (seen in CompanyController)
- `updateOrCreate` is the correct Eloquent method for upsert with unique constraint on company_id

**Route placement**: Added to routes/api.php under `/admin/companies/{companyId}/dotw-credentials` prefix — consistent with REST resource naming.

---

## Markup Handling

**Default 20%** comes from `company_dotw_credentials.markup_percent` column default.

`applyMarkup(float $originalFare): array` on DotwService returns:
```php
[
    'original_fare' => 100.00,
    'markup_percent' => 20.0,
    'markup_amount' => 20.00,
    'final_fare' => 120.00,
]
```

This transparent structure satisfies MARKUP-03 (used in Phases 5-6) and is consistent with the existing `basePrice`/`markup`/`finalPrice` pattern visible in the SearchDotwHotels GraphQL response.

---

## Files NOT Modified in Phase 1

| File | Reason |
|------|--------|
| app/GraphQL/Queries/SearchDotwHotels.php | Pre-DOTW-v1.0 legacy — updated in Phase 4 |
| app/Models/DotwPrebook.php | company_id added in Phase 2 (audit infra) |
| config/dotw.php | Credential config keys remain for backward compat; service ignores them at runtime |
| database/migrations/2026_02_21_033317_* | Existing migration unchanged; company_id on prebooks added in Phase 2 |
