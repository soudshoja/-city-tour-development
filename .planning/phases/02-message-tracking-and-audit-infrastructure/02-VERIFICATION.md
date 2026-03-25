---
phase: 02-message-tracking-and-audit-infrastructure
verified: 2026-02-21T00:00:00Z
status: gaps_found
score: 7/9 must-haves verified
gaps:
  - truth: "Calling any DOTW GraphQL operation with X-Resayil-Message-ID header produces an audit log row with the message ID"
    status: partial
    reason: "SearchDotwHotels resolver correctly passes resayil IDs to searchHotels(), but the two getRooms() calls inside the same resolver (browse at line 220, blocking at line 254) do NOT pass $resayilMessageId or $resayilQuoteId. The rates and block audit log rows produced within the SearchDotwHotels flow will have null resayil_message_id, breaking MSG-02 for those operation types."
    artifacts:
      - path: "app/GraphQL/Queries/SearchDotwHotels.php"
        issue: "getRooms($browseParams, false) at line 220 and getRooms($blockingParams, true) at line 254 omit the three trailing resayil/company parameters that were added to the method signature"
    missing:
      - "Pass $resayilMessageId, $resayilQuoteId, $companyId to both getRooms() calls in SearchDotwHotels.__invoke()"
  - truth: "All four operation types (search, rates, block, book) produce audit log rows linked to originating WhatsApp message"
    status: partial
    reason: "The DotwService methods are correctly wired to accept and forward resayil IDs (MSG-04 satisfied at the service layer). However, the only current resolver (SearchDotwHotels) does not forward the IDs to getRooms calls, so rates and block rows from this resolver will be unlinked. searchHotels rows are correctly linked."
    artifacts:
      - path: "app/GraphQL/Queries/SearchDotwHotels.php"
        issue: "Two getRooms() calls lack resayil parameter forwarding; rates and block audit rows will have null resayil_message_id"
    missing:
      - "Forward $resayilMessageId, $resayilQuoteId, $companyId from resolver scope to both getRooms() calls"
---

# Phase 2: Message Tracking and Audit Infrastructure Verification Report

**Phase Goal:** Every DOTW GraphQL operation is linked to a WhatsApp conversation via resayil_message_id in audit logs; Super Admin and Company Admin can view logs via a role-scoped UI.
**Verified:** 2026-02-21
**Status:** gaps_found
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | A dotw_audit_logs table migration exists with all required columns | VERIFIED | `database/migrations/2026_02_21_100001_create_dotw_audit_logs_table.php` defines all 7 required columns plus two indexes |
| 2 | Audit log rows include full request and response payloads | VERIFIED | `request_payload` (longText) and `response_payload` (longText) columns present; DotwAuditService::log() persists both |
| 3 | No DOTW credentials appear in any audit log row | VERIFIED | DotwAuditService::sanitizePayload() recursively redacts 12 sensitive key names at any nesting depth; password, dotw_password, dotw_username, token, etc. all replaced with '[REDACTED]' |
| 4 | All four operation types are representable by the operation_type column | VERIFIED | Migration uses ENUM('search','rates','block','book'); DotwAuditService has OP_SEARCH/OP_RATES/OP_BLOCK/OP_BOOK constants |
| 5 | Calling any DOTW GraphQL operation with X-Resayil-Message-ID header produces an audit log row with the message ID | FAILED | searchHotels call is correctly linked. getRooms calls inside SearchDotwHotels (lines 220, 254) do NOT forward resayil IDs — rates and block rows will have null resayil_message_id |
| 6 | All four operation types produce audit log entries linked to originating WhatsApp message | FAILED | Service layer wiring is correct for all four types; resolver only wires searchHotels. getRooms audit entries from SearchDotwHotels are unlinked |
| 7 | A WhatsApp AI button appears in sidebar for Super Admin and Company Admin | VERIFIED | sidebar.blade.php lines 89-103: `@if(in_array(auth()->user()->role_id, [Role::ADMIN, Role::COMPANY]))` wraps the button |
| 8 | Super Admin sees all columns; Company Admin sees columns 3-8 scoped to own company | VERIFIED | DotwAuditLogIndex.php uses `!isSuperAdmin() -> where('company_id', user->company_id)`; blade uses `@if($isSuperAdmin)` for ID/Company columns |
| 9 | Roles below Company Admin cannot access the page | VERIFIED | DotwAuditAccess middleware enforces `[Role::ADMIN, Role::COMPANY]` only; registered as 'dotw_audit_access' alias; route uses auth + dotw_audit_access |

**Score:** 7/9 truths verified

---

## Required Artifacts

### Plan 02-01 Artifacts

| Artifact | Expected | Status | Details |
|----------|---------|--------|---------|
| `database/migrations/2026_02_21_100001_create_dotw_audit_logs_table.php` | dotw_audit_logs migration | VERIFIED | All 7 columns present (company_id, resayil_message_id, resayil_quote_id, operation_type, request_payload, response_payload, created_at); 2 indexes; no FK to companies |
| `app/Models/DotwAuditLog.php` | Eloquent model for audit logs | VERIFIED | table = dotw_audit_logs, timestamps=false, UPDATED_AT=null, fillable has all 6 fields, casts: created_at->datetime, request_payload->array, response_payload->array, log() static factory method present |
| `app/Services/DotwAuditService.php` | Sanitized audit logging service | VERIFIED | OP_SEARCH/OP_RATES/OP_BLOCK/OP_BOOK constants; log() method with try/catch fail-silent pattern; sanitizePayload() recursive closure; 12 SENSITIVE_KEYS |

### Plan 02-02 Artifacts

| Artifact | Expected | Status | Details |
|----------|---------|--------|---------|
| `app/GraphQL/Middleware/ResayilContextMiddleware.php` | Extracts X-Resayil-* headers | VERIFIED | Standard Laravel HTTP middleware; extracts X-Resayil-Message-ID and X-Resayil-Quote-ID into request attributes |
| `config/lighthouse.php` | ResayilContextMiddleware registered | VERIFIED | Line 32 confirms `\App\GraphQL\Middleware\ResayilContextMiddleware::class` in route.middleware array |
| `app/Services/DotwService.php` | Calls DotwAuditService::log() for all 4 operations | VERIFIED | searchHotels (line 233), getRooms (lines 307-318, both OP_BLOCK and OP_RATES), confirmBooking (line 376), saveBooking (line 434), bookItinerary (line 490) all call auditService->log() |
| `app/GraphQL/Queries/SearchDotwHotels.php` | Passes Resayil IDs from context to DotwService | PARTIAL | Correctly extracts IDs from $context->request()->attributes and passes to searchHotels(). Does NOT pass IDs to getRooms() calls (lines 220 and 254 omit parameters) |

### Plan 02-03 Artifacts

| Artifact | Expected | Status | Details |
|----------|---------|--------|---------|
| `resources/views/layouts/sidebar.blade.php` | WhatsApp AI button for ADMIN + COMPANY | VERIFIED | Lines 89-103; in_array check for Role::ADMIN and Role::COMPANY; links to route('admin.dotw.audit-logs') |
| `app/Http/Livewire/Admin/DotwAuditLogIndex.php` | Livewire with role-based columns | VERIFIED | WithPagination, 5 filters with queryString sync, isSuperAdmin() role check, company scoping in query, toggleRow(), resetFilters(), paginate(25) |
| `resources/views/livewire/admin/dotw-audit-log-index.blade.php` | Role-aware audit log table | VERIFIED | @if($isSuperAdmin) for ID/Company columns, operation badges (search=blue, rates=yellow, block=orange, book=green), collapsible JSON payloads, pagination, empty state message |
| `app/Http/Middleware/DotwAuditAccess.php` | Gate to Role::ADMIN and Role::COMPANY | VERIFIED | Checks `[Role::ADMIN, Role::COMPANY]` array; aborts 403 for others |
| `routes/web.php` | Route /admin/dotw/audit-logs | VERIFIED | Lines 930-935; middleware(['auth', 'dotw_audit_access']); name('admin.dotw.audit-logs') |
| `bootstrap/app.php` | dotw_audit_access alias registered | VERIFIED | Line 28 confirms alias registration |

---

## Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| ResayilContextMiddleware | DotwService (via resolver) | request->attributes -> searchHotels() | PARTIAL | IDs flow to searchHotels() correctly. getRooms() calls in same resolver are unlinked |
| DotwAuditService | DotwAuditLog | DotwAuditLog::log() | VERIFIED | Line 95 in DotwAuditService.php calls DotwAuditLog::log([...]) |
| DotwAuditService | sanitizePayload | strips sensitive keys before persisting | VERIFIED | Both $sanitizedRequest and $sanitizedResponse pass through sanitizePayload() before being passed to DotwAuditLog::log() |
| DotwService | DotwAuditService | DotwAuditService::log() called in 5 methods | VERIFIED | searchHotels, getRooms (OP_RATES + OP_BLOCK branch), confirmBooking, saveBooking, bookItinerary all call $this->auditService->log() |
| DotwAuditLogIndex | DotwAuditLog | Eloquent query with company scoping | VERIFIED | render() builds query with `when(!isSuperAdmin(), company_id scope)` |
| DotwAuditAccess | Route | 'dotw_audit_access' alias in bootstrap/app.php | VERIFIED | Alias registered and referenced in route middleware array |

---

## Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|------------|------------|-------------|--------|----------|
| MSG-01 | 02-01 | DB migration creates dotw_audit_logs table with required columns | SATISFIED | Migration verified: all 7 columns + 2 indexes; no FK to companies |
| MSG-02 | 02-02 | Every GraphQL operation logs Resayil message_id + quote_id | PARTIAL | Middleware extracts and forwards headers. searchHotels audit rows linked. rates/block rows from SearchDotwHotels are NOT linked — resayil_message_id will be null for those rows |
| MSG-03 | 02-01, 02-02, 02-03 | Audit log captures entire request/response for debugging | SATISFIED | request_payload and response_payload persisted for all operations; blade view shows collapsible JSON payloads |
| MSG-04 | 02-01, 02-02 | All DOTW operations (search, rates, block, book) link to originating WhatsApp message | PARTIAL | Service methods all correctly accept and forward resayil IDs; operation_type column correctly distinguishes all 4 types. SearchDotwHotels resolver creates rates/block logs without resayil linkage |
| MSG-05 | 02-01, 02-02 | Audit logs never contain encrypted credentials or sensitive passenger details | SATISFIED | DotwAuditService::sanitizePayload() recursively redacts password, dotw_password, dotw_username, username, md5, secret, token, authorization, credit_card, card_number, cvv, passport_number |

---

## Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `app/GraphQL/Queries/SearchDotwHotels.php` | 220 | `getRooms($browseParams, false)` — missing 3 resayil params | Blocker | rates audit logs will have null resayil_message_id, breaking MSG-02 and MSG-04 for this operation type |
| `app/GraphQL/Queries/SearchDotwHotels.php` | 254 | `getRooms($blockingParams, true)` — missing 3 resayil params | Blocker | block audit logs will have null resayil_message_id, breaking MSG-02 and MSG-04 for this operation type |

---

## Human Verification Required

### 1. Sidebar Button Visibility

**Test:** Log in as Super Admin (role_id == Role::ADMIN). Verify the WhatsApp AI sidebar button is visible. Log in as Company Admin (role_id == Role::COMPANY). Verify it is visible. Log in as branch/agent/client. Verify it is absent.
**Expected:** Button visible only for ADMIN and COMPANY roles.
**Why human:** Role-conditional rendering requires a running app with authenticated sessions.

### 2. Role-Scoped Log Table

**Test:** As Company Admin, navigate to /admin/dotw/audit-logs. Verify only logs for own company_id appear. Verify ID and Company columns are hidden. As Super Admin, verify all companies' logs appear and all 7 columns are visible.
**Expected:** Company Admin sees rows 3-8 columns only, scoped to own company_id. Super Admin sees all 7 columns and all companies.
**Why human:** Requires DB with test data and authenticated sessions.

### 3. Collapsible JSON Payloads

**Test:** Click "View" button on any audit log row. Verify request and response payloads expand in terminal-style green-on-dark display. Click again to collapse.
**Expected:** Toggle works; JSON is pretty-printed; no credentials visible in payload.
**Why human:** Livewire toggle interaction requires a running browser.

---

## Gaps Summary

Two blockers found, both in the same file (`app/GraphQL/Queries/SearchDotwHotels.php`):

The resolver correctly extracts `$resayilMessageId` and `$resayilQuoteId` from request attributes (set by `ResayilContextMiddleware`) and passes them to `DotwService::searchHotels()`. However, the two subsequent `getRooms()` calls on lines 220 and 254 use the old call signature without the three trailing optional parameters (`$resayilMessageId`, `$resayilQuoteId`, `$companyId`). Both `DotwService::getRooms()` parameters default to null, so no error is thrown — the audit log rows are silently written without any conversation linkage.

This means when `SearchDotwHotels` runs a full search (search + browse rooms + block rooms), the `rates` and `block` audit rows produced will have `resayil_message_id = null`, preventing MSG-02 and MSG-04 from being fully satisfied for those operation types.

**Root cause:** The resolver was updated to extract resayil IDs and pass them to `searchHotels()` (Task 4 of Plan 02-02), but the getRooms() calls in the same resolver method were not updated in the same commit.

**Fix scope:** Two-line change in `SearchDotwHotels.__invoke()`:
- Line 220: `getRooms($browseParams, false, $resayilMessageId, $resayilQuoteId, $companyId)`
- Line 254: `getRooms($blockingParams, true, $resayilMessageId, $resayilQuoteId, $companyId)`

All other phase deliverables (migration, model, service, middleware, Livewire UI, sidebar, route, access middleware) are substantively implemented and correctly wired.

---

_Verified: 2026-02-21_
_Verifier: Claude (gsd-verifier)_
