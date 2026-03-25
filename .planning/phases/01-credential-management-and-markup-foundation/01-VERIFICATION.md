---
phase: 01-credential-management-and-markup-foundation
verified: 2026-02-21T08:30:00Z
status: passed
score: 9/9 must-haves verified
re_verification: false
---

# Phase 1: Credential Management and Markup Foundation — Verification Report

**Phase Goal:** Per-company DOTW credential storage, encryption, and admin API — companies can have isolated DOTW credentials with markup configuration, accessible via REST admin endpoints.
**Verified:** 2026-02-21
**Status:** passed
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths

| #  | Truth                                                                                                                   | Status     | Evidence                                                                                                      |
|----|-------------------------------------------------------------------------------------------------------------------------|------------|---------------------------------------------------------------------------------------------------------------|
| 1  | A company_dotw_credentials row exists for Company A and cannot be read by Company B context                             | VERIFIED   | `company_id` unique constraint in migration; controller scopes all queries to path param `$companyId`         |
| 2  | DotwService can be instantiated with a company_id and loads credentials from DB (not env)                               | VERIFIED   | Line 133: `CompanyDotwCredential::forCompany($companyId)->first()` — DB path active when `$companyId !== null`|
| 3  | Credentials stored in company_dotw_credentials are never returned in plaintext — only encrypt()/decrypt() round-trip    | VERIFIED   | `Crypt::encrypt/Crypt::decrypt` in `Attribute::make()` accessors (lines 89-90, 103-104); `$hidden` on model  |
| 4  | DotwService throws a typed exception with message 'DOTW credentials not configured for this company' when no row exists | VERIFIED   | Lines 136-138: `throw new \RuntimeException("DOTW credentials not configured for this company (company_id: {$companyId})")` |
| 5  | Default markup_percent of 20 is stored in company_dotw_credentials; per-company override is readable by DotwService    | VERIFIED   | Migration: `decimal('markup_percent', 5, 2)->default(20.00)`. DotwService line 144: `(float) $credential->markup_percent` |
| 6  | POST /api/admin/companies/{id}/dotw-credentials stores credentials encrypted; GET response never returns dotw_username or dotw_password | VERIFIED | Controller uses `CompanyDotwCredential::updateOrCreate`; response explicitly constructed without credential fields |
| 7  | Sending a request with a missing required field returns a 422 with a message naming the specific missing field          | VERIFIED   | `StoreDotwCredentialRequest::messages()` defines `dotw_username.required` → "Please provide passenger dotw_username" |
| 8  | Admin can update an existing credential row (upsert) without creating a duplicate company_id conflict                   | VERIFIED   | `CompanyDotwCredential::updateOrCreate(['company_id' => $companyId], [...])` — unique column as search key     |
| 9  | Markup percent can be omitted from request and defaults to 20; an explicit value overrides the default                  | VERIFIED   | `$request->input('markup_percent', 20.00)` in controller store(); migration default 20.00                      |

**Score:** 9/9 truths verified

---

### Required Artifacts

| Artifact                                                                           | Provides                                                              | Status     | Details                                                                                                                                    |
|------------------------------------------------------------------------------------|-----------------------------------------------------------------------|------------|--------------------------------------------------------------------------------------------------------------------------------------------|
| `database/migrations/2026_02_21_100001_create_company_dotw_credentials_table.php`  | company_dotw_credentials table with encrypted credential columns      | VERIFIED   | `Schema::create('company_dotw_credentials'` present. All 8 columns: id, company_id (unique), dotw_username, dotw_password, dotw_company_code, markup_percent (default 20.00), is_active, timestamps. FK to companies, indexes on company_id and is_active. |
| `app/Models/CompanyDotwCredential.php`                                             | Eloquent model with encrypt/decrypt accessors for username and password | VERIFIED | `Crypt::encrypt/Crypt::decrypt` in `Attribute::make()` accessor closures. `$hidden = ['dotw_username', 'dotw_password']`. `scopeForCompany`, `getMarkupMultiplier`, `company()` BelongsTo. |
| `app/Services/DotwService.php`                                                     | Refactored constructor accepting company_id, loading credentials from DB | VERIFIED | Constructor `__construct(?int $companyId = null)`. B2B path uses `CompanyDotwCredential::forCompany($companyId)->first()`. `applyMarkup()` method present. |
| `app/Http/Requests/StoreDotwCredentialRequest.php`                                 | Form request with validation rules for all 4 credential fields        | VERIFIED   | `dotw_username`, `dotw_password`, `dotw_company_code` required; `markup_percent` nullable numeric 0-100. Custom messages per field.        |
| `app/Http/Controllers/Admin/DotwCredentialController.php`                          | Admin REST controller for DOTW credential CRUD (upsert + show)        | VERIFIED   | `store()` and `show()` methods, both using `Company::findOrFail()`. Response payload explicitly constructed without credential fields.      |
| `routes/api.php`                                                                   | POST and GET routes at /api/admin/companies/{companyId}/dotw-credentials | VERIFIED | Import at line 23. Route group at lines 189-191 registers both POST and GET.                                                               |

---

### Key Link Verification

| From                                            | To                                            | Via                                                       | Status   | Details                                                                                        |
|-------------------------------------------------|-----------------------------------------------|-----------------------------------------------------------|----------|-----------------------------------------------------------------------------------------------|
| `app/Services/DotwService.php`                  | `app/Models/CompanyDotwCredential.php`         | `CompanyDotwCredential::forCompany($companyId)->first()`  | WIRED    | Line 5: `use App\Models\CompanyDotwCredential`. Line 133: `CompanyDotwCredential::forCompany($companyId)->first()` |
| `app/Models/CompanyDotwCredential.php`          | `company_dotw_credentials` (DB)               | Eloquent ORM with `Crypt::encrypt/Crypt::decrypt` accessors | WIRED  | Lines 89-90, 103-104: `Crypt::encrypt($value)` on set, `Crypt::decrypt($value)` on get. `$table = 'company_dotw_credentials'` |
| `routes/api.php`                                | `app/Http/Controllers/Admin/DotwCredentialController.php` | `Route::post('/dotw-credentials', [DotwCredentialController::class, 'store'])` | WIRED | Import line 23. Routes lines 190-191 reference both `store` and `show` actions |
| `app/Http/Controllers/Admin/DotwCredentialController.php` | `app/Models/CompanyDotwCredential.php` | `CompanyDotwCredential::updateOrCreate(['company_id' => $companyId])` | WIRED | Line 8: `use App\Models\CompanyDotwCredential`. Line 52: `updateOrCreate` call |

---

### Requirements Coverage

| Requirement | Source Plan | Description                                                           | Status    | Evidence                                                                                    |
|-------------|-------------|-----------------------------------------------------------------------|-----------|---------------------------------------------------------------------------------------------|
| CRED-01     | 01-01       | Migration creates company_dotw_credentials table                       | SATISFIED | Migration file exists, `Schema::create('company_dotw_credentials'` with all required columns |
| CRED-02     | 01-02       | Admin API allows storing/updating per-company DOTW credentials securely | SATISFIED | POST + GET at `/api/admin/companies/{companyId}/dotw-credentials`; `updateOrCreate` upsert   |
| CRED-03     | 01-01       | Credentials encrypted at rest, never logged in plaintext              | SATISFIED | `Crypt::encrypt/Crypt::decrypt` in model accessors. `$hidden` prevents JSON serialization. Logger records `company_id` not credentials |
| CRED-04     | 01-01       | DotwService resolves correct company credentials based on company context | SATISFIED | Constructor B2B path: `CompanyDotwCredential::forCompany($companyId)->first()`             |
| CRED-05     | 01-01, 01-02 | Missing credentials returns helpful error directing admin to configure | SATISFIED | DotwService throws `RuntimeException("DOTW credentials not configured for this company ...")`. GET endpoint returns `{configured: false, message: "DOTW credentials not configured for this company"}` |
| MARKUP-01   | 01-01       | Default 20% B2C markup applied to all fares                          | SATISFIED | Migration `->default(20.00)`. DotwService `config('dotw.b2c_markup_percentage', 20)` fallback |
| MARKUP-02   | 01-01       | Admin can set custom markup percentage per company                     | SATISFIED | `markup_percent` column in table; stored via `updateOrCreate` in controller                 |
| B2B-04      | 01-01       | Multi-company credential isolation                                    | SATISFIED | Unique constraint on `company_id`; all queries scoped to path parameter `$companyId`        |
| ERROR-05    | 01-02       | Missing field returns "Please provide passenger {field_name}"         | SATISFIED | `StoreDotwCredentialRequest::messages()` defines per-field messages matching exact ERROR-05 wording |

All 9 requirements claimed by Phase 1 plans are satisfied. No orphaned requirements (all CRED-*, MARKUP-01/02, B2B-04, ERROR-05 are accounted for in plans 01-01 and 01-02).

---

### Anti-Patterns Found

No anti-patterns detected across all 5 Phase 1 files:

- No TODO/FIXME/PLACEHOLDER comments
- No empty return stubs (`return null`, `return []`, `return {}`)
- No console.log or equivalent placeholder implementations
- `authorize()` returning `true` is documented as intentional — auth deferred to Phase 8 per plan spec

---

### Human Verification Required

#### 1. End-to-end credential round-trip on live database

**Test:** POST to `POST /api/admin/companies/{id}/dotw-credentials` with valid payload, then verify the stored row in `company_dotw_credentials` has encrypted blobs (length >> plaintext), and GET returns the response without dotw_username or dotw_password keys.
**Expected:** Row stored with encrypted blobs. Response contains only `{success, message, company_id, markup_percent}` for POST; `{success, configured, company_id, dotw_company_code, markup_percent, is_active, created_at, updated_at}` for GET.
**Why human:** Local MySQL not running in dev environment (documented deviation in SUMMARY.md). Full DB verification requires live server (development.citycommerce.group).

#### 2. Cross-company isolation end-to-end

**Test:** Store credentials for company A. Perform GET for company B's ID. Confirm `{configured: false}` returned with no reference to company A's credentials.
**Expected:** Company B GET returns 404 with `{configured: false}`.
**Why human:** Requires live DB to execute the actual query isolation path.

#### 3. DotwService exception path with non-existent company_id

**Test:** `new App\Services\DotwService(99999)` with a running DB where no row exists for company_id 99999.
**Expected:** `RuntimeException` thrown with message "DOTW credentials not configured for this company (company_id: 99999)".
**Why human:** In dev environment DB is unavailable; SQLSTATE exception masks the custom message (documented deviation in SUMMARY.md).

---

### Gaps Summary

No gaps. All 9/9 observable truths verified against the actual codebase. All 5 required artifacts exist, are substantive (not stubs), and are wired. All 9 required requirements are satisfied.

**Notable implementation decisions verified as correct:**
- Constructor is `__construct(?int $companyId = null)` — the backward-compatible nullable variant specified in the task action block (the `<done>` text in the plan had a documentation discrepancy listing `int $companyId` without the nullable; the actual implementation matches the action specification).
- `CompanyDotwCredential::forCompany($companyId)` calls the `scopeForCompany` local scope (Laravel magic strips the `scope` prefix) — confirmed at model line 125 and DotwService line 133.

---

_Verified: 2026-02-21_
_Verifier: Claude (gsd-verifier)_
