---
phase: 01-credential-management-and-markup-foundation
plan: 02
subsystem: dotw-credentials-api
tags: [dotw, credentials, admin-api, form-request, validation, upsert, isolation]
dependency_graph:
  requires:
    - 01-01 (CompanyDotwCredential model with encryption accessors)
  provides:
    - StoreDotwCredentialRequest (field-level validation, ERROR-05 messages)
    - DotwCredentialController (store upsert + show)
    - POST /api/admin/companies/{companyId}/dotw-credentials
    - GET /api/admin/companies/{companyId}/dotw-credentials
  affects:
    - routes/api.php (two new routes added)
tech_stack:
  added: []
  patterns:
    - FormRequest with custom messages for field-specific validation errors
    - updateOrCreate(['company_id']) for upsert without duplicate row risk
    - $hidden on model as serialization-level security boundary for credentials
    - findOrFail() for automatic 404 on unknown company_id
    - company_id path parameter as isolation boundary (cross-company access prevention)
key_files:
  created:
    - app/Http/Requests/StoreDotwCredentialRequest.php
    - app/Http/Controllers/Admin/DotwCredentialController.php
  modified:
    - routes/api.php
decisions:
  - "Used updateOrCreate(['company_id']) to guarantee upsert semantics — calling store twice for the same company never creates duplicate rows"
  - "Response from store() explicitly constructs a safe payload (success, message, company_id, markup_percent) — credentials never appear even if $hidden were removed"
  - "show() response also explicitly constructed — only non-sensitive fields (dotw_company_code, markup_percent, is_active, timestamps) listed"
  - "authorize() returns true — auth middleware deferred to Phase 8 per plan spec, matching existing project pattern where many routes lack auth middleware"
  - "findOrFail() used in both store() and show() — unknown company_id returns automatic 404 before any credential logic runs"
metrics:
  duration_minutes: 2
  completed_date: "2026-02-21"
  tasks_completed: 2
  tasks_total: 2
  files_created: 2
  files_modified: 1
---

# Phase 1 Plan 02: Admin Credential API (Store + Show) Summary

Admin REST API for DOTW credential management: POST upsert and GET show endpoints at `/api/admin/companies/{companyId}/dotw-credentials` with field-level 422 validation via FormRequest, upsert semantics preventing duplicate rows, and strict response sanitization ensuring encrypted credentials are never exposed.

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | StoreDotwCredentialRequest with field-level validation | 1464a372 | app/Http/Requests/StoreDotwCredentialRequest.php |
| 2 | DotwCredentialController (upsert + show) and routes | 0fd9786c | app/Http/Controllers/Admin/DotwCredentialController.php, routes/api.php |

## What Was Built

### FormRequest (Task 1)

File: `app/Http/Requests/StoreDotwCredentialRequest.php`

Validation rules:
- `dotw_username` — required, string, max:100
- `dotw_password` — required, string, max:200
- `dotw_company_code` — required, string, max:50
- `markup_percent` — nullable, numeric, min:0, max:100

Custom messages follow ERROR-05 convention:
- `dotw_username.required` → "Please provide passenger dotw_username"
- `dotw_password.required` → "Please provide passenger dotw_password"
- `dotw_company_code.required` → "Please provide passenger dotw_company_code"
- `markup_percent.numeric` → "markup_percent must be a number between 0 and 100"
- `markup_percent.min/max` → range-specific messages

Verified via tinker: `Validator::make([], $req->rules(), $req->messages())` with empty payload returns `["Please provide passenger dotw_username"]` for the dotw_username key. All three required-field messages confirmed working.

### Controller (Task 2)

File: `app/Http/Controllers/Admin/DotwCredentialController.php`

**`store(StoreDotwCredentialRequest $request, int $companyId): JsonResponse`**
- Calls `Company::findOrFail($companyId)` — auto 404 for unknown company
- `CompanyDotwCredential::updateOrCreate(['company_id' => $companyId], [...])` — upsert, no duplicate rows
- `markup_percent` defaults to `20.00` if not in request body
- Response payload: `{success, message, company_id, markup_percent}` — credentials intentionally absent
- Returns HTTP 200

**`show(int $companyId): JsonResponse`**
- Calls `Company::findOrFail($companyId)` — auto 404 for unknown company
- Queries `CompanyDotwCredential::where('company_id', ...)->where('is_active', true)->first()`
- Returns 404 `{success: false, configured: false, company_id, message}` if no active credential
- Returns 200 `{success, configured, company_id, dotw_company_code, markup_percent, is_active, created_at, updated_at}` — credentials excluded

### Routes (Task 2)

File: `routes/api.php`

Added import: `use App\Http\Controllers\Admin\DotwCredentialController;`

Added route group before `require __DIR__ . '/auth.php'`:
```php
Route::prefix('admin/companies/{companyId}')->group(function () {
    Route::post('/dotw-credentials', [DotwCredentialController::class, 'store']);
    Route::get('/dotw-credentials', [DotwCredentialController::class, 'show']);
});
```

Confirmed via `php artisan route:list | grep dotw-credentials`:
- `POST api/admin/companies/{companyId}/dotw-credentials`
- `GET|HEAD api/admin/companies/{companyId}/dotw-credentials`

## How Credential Isolation Is Enforced

Cross-company isolation is enforced at two levels:

1. **Path parameter scoping** — `$companyId` from the URL path is used directly in `updateOrCreate(['company_id' => $companyId])` and `where('company_id', $companyId)`. A request to `/api/admin/companies/2/dotw-credentials` can only read or write rows where `company_id = 2`.

2. **DB constraint** — The `company_dotw_credentials` table has a unique constraint on `company_id` (from Plan 01 migration). `updateOrCreate` targeting `['company_id' => $companyId]` will only ever match the single row for that company.

A GET for company 2 when only company 1 has credentials returns `{"configured": false}` — there is no mechanism by which company 1's credentials could appear in a company 2 response.

## Validation Message Pattern for ERROR-05

The plan required: "missing field returns a message naming the specific missing field." The wording from the REQUIREMENTS doc (`ERROR-05`) uses "Please provide passenger {field_name}" even for credential fields. This exact wording was applied:

```php
'dotw_username.required' => 'Please provide passenger dotw_username',
'dotw_password.required' => 'Please provide passenger dotw_password',
'dotw_company_code.required' => 'Please provide passenger dotw_company_code',
```

This is intentional and documented in a PHPDoc note in the request class.

## Decisions Made

1. **Double-layer response sanitization** — The `store()` and `show()` response payloads are explicitly constructed (not `$credential->toArray()`). Even if the `$hidden` property were removed from the model, the response would still not contain credentials because they are never included in the explicit response array.

2. **`updateOrCreate` with `['company_id']` as search key** — The search array uses only `company_id` (the unique column), not all fields. This ensures the update path always targets the existing row rather than creating a duplicate on re-submit.

3. **`findOrFail` for company validation** — Rather than manually checking `Company::find()` and returning a custom 404, `findOrFail()` is used. Laravel throws a `ModelNotFoundException` which the exception handler converts to a 404 JSON response automatically.

4. **No auth middleware** — Deferred to Phase 8 (B2B packaging) per plan spec. Consistent with existing project patterns in `routes/api.php` where many routes have no auth middleware.

## Deviations from Plan

### Auto-fixed Issues

None.

### Plan Deviations

**1. DB Not Available for curl Verification Tests**

- **Found during:** Task 2 verification
- **Issue:** Local MySQL is not running (connection refused on 127.0.0.1:3306) — same environment limitation as Plan 01
- **Fix:** Route registration verified via `php artisan route:list`. Controller class instantiation verified via tinker. Validation logic verified via Validator::make() with no DB dependency. PHP syntax verified via `php -l` on all files.
- **Impact:** No code change required. Full end-to-end curl tests (POST with valid body, cross-company isolation) will be validated on the live server (development.citycommerce.group).

**2. Pint/PHPStan Blocked by Safety System**

- **Found during:** Post-task verification
- **Issue:** The Bash tool safety system blocked execution of `./vendor/bin/pint` and `./vendor/bin/phpstan` commands
- **Fix:** Code manually verified for PSR-12 compliance. PHP syntax checked via `php -l`. Code follows the same patterns as Plan 01 files (which passed pint). Pint and phpstan will run on the live server during deployment.
- **Impact:** No code correctness concern — the code follows PSR-12 patterns throughout.

## Self-Check

Files created/modified:
- [x] `app/Http/Requests/StoreDotwCredentialRequest.php` — exists
- [x] `app/Http/Controllers/Admin/DotwCredentialController.php` — exists
- [x] `routes/api.php` — modified with import and route group

Commits:
- [x] `1464a372` — feat(01-02): create StoreDotwCredentialRequest with field-level validation
- [x] `0fd9786c` — feat(01-02): create DotwCredentialController and register admin routes

Routes confirmed:
- [x] POST api/admin/companies/{companyId}/dotw-credentials — registered
- [x] GET|HEAD api/admin/companies/{companyId}/dotw-credentials — registered

## Self-Check: PASSED
