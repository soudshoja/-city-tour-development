# Phase 8 Research: Modular Architecture & B2B Packaging

**Phase Goal:** The entire DOTW module can be copied to a production subdomain with config changes and migrations only — no tight coupling to the invoice/task system, with a README that documents every deployment step and environment variable.

**Requirements to satisfy:** MOD-01, MOD-02, MOD-03, MOD-04, MOD-05, MOD-06, MOD-07, MOD-08, B2B-05

---

## Current State of the DOTW Module

### Files That Exist

**Service Layer**
- `app/Services/DotwService.php` — 1533 lines. Clean: no imports from invoice/task/payment systems. All imports are from `App\Models\CompanyDotwCredential`, `App\Services\DotwAuditService`, and Laravel stdlib.
- `app/Services/DotwAuditService.php` — Audit log wrapper. No non-DOTW imports.
- `app/Services/DotwCacheService.php` — Cache wrapper. No non-DOTW imports.

**GraphQL Resolvers**
- `app/GraphQL/Queries/DotwGetCities.php`
- `app/GraphQL/Queries/DotwSearchHotels.php`
- `app/GraphQL/Queries/DotwGetRoomRates.php`
- `app/GraphQL/Mutations/DotwBlockRates.php`
- `app/GraphQL/Mutations/DotwCreatePreBooking.php`
All resolvers import only DOTW models and services — zero coupling to invoice/task.

**GraphQL Middleware**
- `app/GraphQL/Middleware/DotwTraceMiddleware.php`
- `app/GraphQL/Middleware/ResayilContextMiddleware.php`

**HTTP Middleware**
- `app/Http/Middleware/DotwAuditAccess.php`

**Livewire Admin**
- `app/Http/Livewire/Admin/DotwAuditLogIndex.php`

**Models**
- `app/Models/CompanyDotwCredential.php`
- `app/Models/DotwPrebook.php`
- `app/Models/DotwRoom.php`
- `app/Models/DotwBooking.php`
- `app/Models/DotwAuditLog.php`
All use standard Eloquent. No relationships to Invoice, Task, Payment, Agent, Client models.

**Migrations (6 files)**
- `2026_02_21_033317_create_dotw_prebooks_table.php`
- `2026_02_21_033318_create_dotw_rooms_table.php`
- `2026_02_21_100001_create_company_dotw_credentials_table.php`
- `2026_02_21_100001_create_dotw_audit_logs_table.php`
- `2026_02_21_155718_add_company_id_resayil_to_dotw_prebooks_table.php`
- `2026_02_21_165035_create_dotw_bookings_table.php`

**Config**
- `config/dotw.php` — 136 lines. All values use `env()`. No hardcoded paths.

**GraphQL Schema**
- `graphql/dotw.graphql` — 677 lines. Registered via `#import dotw.graphql` in `graphql/schema.graphql` (line 1). Can be deregistered by removing this single line.

**Existing Documentation**
- `DOTW_INTEGRATION.md` — Covers env vars for legacy path only (env-based credentials). Does NOT cover the B2B per-company credential path added in Phase 1.

---

## Requirement-by-Requirement Analysis

### MOD-01: DotwService.php contains all DOTW business logic, no invoice/task/payment imports
**Status: Already satisfied.** DotwService.php has zero imports from the invoice/task/payment subsystems. Grep confirms no `use.*Invoice`, `use.*Task`, `use.*Payment`, `use.*Agent`, `use.*Client` in any DOTW file.

**Action needed: NONE.** Verification statement in plan only.

### MOD-02: config/dotw.php is environment-agnostic (all values from env())
**Status: Already satisfied.** All 10 config values use `env()`. No hardcoded server paths.

**Action needed: NONE.** Verification statement in plan only.

### MOD-03: DOTW migrations (dotw_*.php) are standalone and idempotent
**Status: Partially satisfied.** All migration tables are named `dotw_*`. The files have timestamp prefixes (standard Laravel convention) not bare `dotw_` filenames. The requirement spec `dotw_*.php` likely refers to the table naming convention, not literal filenames — since renaming migration files in Laravel breaks the migration history (the migrations table tracks class names derived from filenames).

**Idempotency:** Laravel's migration system is inherently idempotent — each migration runs once and is recorded in the `migrations` table. Running `php artisan migrate` twice does not re-run applied migrations. The `down()` methods use `Schema::dropIfExists()` which is safe.

**One real gap:** The `company_dotw_credentials` migration has `$table->foreign('company_id')->references('id')->on('companies')`. The `companies` table is the core multi-tenant table — not part of the invoice/task system. This FK is acceptable per MOD-06 scope.

**Action needed:** Add an `ifDoesntHaveTable` / `hasForeignKey` guard or document the FK dependency explicitly. Actually, Laravel migrations track runs via the migrations table — they don't need `hasTable` guards for idempotency. The real action is confirming they run clean on fresh DB and documenting the `companies` table prerequisite.

### MOD-04: graphql/dotw.graphql is modular and composable (can be registered/deregistered independently)
**Status: Already satisfied.** The schema.graphql file registers it with `#import dotw.graphql` on line 1. Removing that line deregisters all DOTW operations.

**Action needed: NONE.** Document in README only.

### MOD-05: DOTW models use standard Eloquent patterns, no FKs to invoice/tasks
**Status: Already satisfied.** All 5 DOTW models (`CompanyDotwCredential`, `DotwPrebook`, `DotwRoom`, `DotwBooking`, `DotwAuditLog`) use standard Eloquent. Foreign keys:
- `dotw_rooms.dotw_preboot_id → dotw_prebooks` (DOTW-internal, fine)
- `company_dotw_credentials.company_id → companies` (core multi-tenant table, not invoice/task)
- `dotw_audit_logs`, `dotw_prebooks`, `dotw_bookings` — no FKs to companies (nullable company_id, no constraint)

**Action needed: NONE.** Verify and document.

### MOD-06: No dependencies on invoice/task system
**Status: Already satisfied.** Confirmed by grep across all DOTW files — zero references to Invoice, Task, Payment, Client, or Agent models/services.

**Action needed: NONE.** Verify and document.

### MOD-07: Can copy DOTW module to new Laravel installation with: 4 env vars + migrations + GraphQL schema registration
**Status: NOT satisfied.** The claim is "4 env vars" but the module has 8 env vars in config/dotw.php. The existing DOTW_INTEGRATION.md covers legacy path only. The B2B per-company DB credential path (Phase 1) is undocumented. No step-by-step copy guide exists.

**Action needed:** Write the deployment guide (part of README in MOD-08).

### MOD-08: README documents deployment: env vars, migration steps, GraphQL schema registration
**Status: NOT satisfied.** `DOTW_INTEGRATION.md` exists but:
1. Covers legacy credentials path only (not B2B per-company path)
2. Does not list all env vars added across all phases (cache TTL, prefix, etc.)
3. No step-by-step new-installation guide
4. No GraphQL registration step
5. Does not document the WhatsApp header requirements (X-Resayil-Message-ID, X-Resayil-Quote-ID, X-Company-ID)
6. Does not document middleware registration steps (bootstrap/app.php, lighthouse.php)
7. Does not cover audit log admin route

**Action needed:** Update `DOTW_INTEGRATION.md` to be the complete deployment README.

### B2B-05: API designed for external B2B partners (N8N/Resayil primary, but extensible)
**Status: NOT satisfied.** The schema exists but:
1. No dedicated API usage guide for external partners
2. No example queries/mutations for each operation
3. No authentication guide for external B2B consumers (beyond N8N)
4. No schema introspection endpoint documented
5. No mention of CORS, rate limiting considerations, or extension points for v2

**Action needed:** Add a B2B API Consumer Guide section to the deployment README, including example GraphQL queries for each operation and the header requirements.

---

## What Phase 8 Actually Needs to Build

Based on the analysis, the majority of MOD requirements are already architecturally satisfied by prior phases. The work in Phase 8 is:

### 1. Verification Audit (MOD-01 through MOD-06)
A single plan that programmatically verifies each MOD requirement is satisfied — runs grep checks, confirms no coupling, documents the verification results. This is quick (no code to write, just confirmation + minor fixes if any are found).

**Potential fix needed:** The `dotw_rooms` migration uses `dotw_preboot_id` (typo — should be `dotw_prebook_id`). This is a DOTW-internal naming issue, not a coupling issue, but it's inconsistent. Since the migration has run, renaming the column requires a new migration. Decision: leave the column name as-is (changing it requires a migration that alters the existing table, adding complexity and risk to Phase 8 scope). Document as a known issue.

### 2. README / Deployment Documentation (MOD-07, MOD-08, B2B-05)
Update `DOTW_INTEGRATION.md` to be a complete deployment guide covering:
- Complete env var list (all phases)
- Step-by-step new installation guide
- B2B per-company credential setup
- GraphQL schema registration (add `#import dotw.graphql` to schema.graphql)
- Middleware registration (bootstrap/app.php, lighthouse.php)
- Admin routes (web.php)
- Lighthouse route middleware (ResayilContextMiddleware, DotwTraceMiddleware)
- WhatsApp header requirements for external B2B consumers
- Example queries for each operation

---

## File Inventory for Phase 8

### Files to VERIFY (no modification expected):
- `app/Services/DotwService.php` — MOD-01 verification
- `config/dotw.php` — MOD-02 verification
- `database/migrations/2026_02_21_*_dotw*.php` (all 6) — MOD-03 verification
- `graphql/dotw.graphql` + `graphql/schema.graphql` — MOD-04 verification
- `app/Models/Dotw*.php` (5 models) — MOD-05 verification
- `app/GraphQL/**/*Dotw*.php` (5 resolvers) — MOD-06 verification

### Files to MODIFY:
- `DOTW_INTEGRATION.md` — Complete overhaul for MOD-07, MOD-08, B2B-05

### Files to CREATE:
- None anticipated — all DOTW code is already in place from Phases 1–6.

---

## Pitfalls

1. **MOD-03 migration filenames:** Don't try to rename migration files — this breaks the migrations table which tracks applied migrations by filename-derived class name. The requirement's `dotw_*.php` refers to table naming convention, not literal file naming.

2. **company_dotw_credentials FK to companies:** This FK is acceptable — `companies` is the core multi-tenant table, not the invoice/task system. MOD-06 only prohibits coupling to invoice/task.

3. **B2B-05 scope creep:** "Extensible for external partners" does NOT require building new auth mechanisms or rate limiters in v1 — those are GQL-01..04 in v2 requirements. B2B-05 in v1 is satisfied by comprehensive schema documentation and an API consumer guide.

4. **DOTW_INTEGRATION.md vs. new README:** Don't create a new file — update the existing `DOTW_INTEGRATION.md` to avoid fragmented documentation.

5. **dotw_rooms typo ('preboot' not 'prebook'):** This is a pre-existing typo from before Phase 1. Do NOT create a migration to fix it in Phase 8 — it would be out of scope and risky. Document it as a known issue.

---

## Summary

Phase 8 is primarily a **documentation and verification** phase, not a code-writing phase. The architecture has been correctly modular since Phase 1. The work is:

1. **Plan 08-01:** Modular architecture audit — verify MOD-01 through MOD-06 with grep-based checks, identify any coupling violations, fix if found.
2. **Plan 08-02:** Deployment README — update `DOTW_INTEGRATION.md` to be a comprehensive deployment + B2B consumer guide (MOD-07, MOD-08, B2B-05).

Estimated effort: Light. Most is writing documentation. No migrations, no schema changes, no new PHP classes.
