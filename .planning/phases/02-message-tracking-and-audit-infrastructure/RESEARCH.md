# Research: Phase 2 — Message Tracking & Audit Infrastructure

**Date:** 2026-02-21
**Phase:** 02-message-tracking-and-audit-infrastructure
**Status:** Complete — no external research needed (Level 0 discovery)

---

## Discovery Level: 0 (Skip External Research)

All work follows established codebase patterns. No new external dependencies are introduced. Decisions below are based on reading the existing codebase.

---

## Codebase Findings

### Existing DOTW Infrastructure (Already Shipped)

The following files exist and are fully implemented — Phase 2 must NOT replace them, only extend them:

| File | Purpose | Lines |
|------|---------|-------|
| `app/Services/DotwService.php` | Full DOTW V4 XML client (search, getRooms, confirm, save, cancel, etc.) | ~1407 |
| `app/Models/DotwPrebook.php` | Eloquent model for rate allocations | ~155 |
| `app/Models/DotwRoom.php` | Room occupancy within a prebook | ~100 |
| `database/migrations/2026_02_21_033317_create_dotw_prebooks_table.php` | Existing migration | — |
| `database/migrations/2026_02_21_033318_create_dotw_rooms_table.php` | Existing migration | — |
| `app/GraphQL/Queries/SearchDotwHotels.php` | Existing GraphQL query resolver | ~865 |
| `config/dotw.php` | DOTW configuration (env-based, no hardcoded paths) | ~100 |

**Critical finding:** `DotwService` currently reads credentials from `config/dotw.php` (global env vars), not per-company. Phase 1 will address per-company credentials. Phase 2 audit logging must work with both the current single-credential setup and the future per-company setup. The `company_id` parameter on `DotwAuditService::log()` is therefore nullable.

### GraphQL Setup (Lighthouse)

- GraphQL library: **Lighthouse** (confirmed in `CLAUDE.md` and `graphql/schema.graphql`)
- Schema file: `graphql/schema.graphql` (single file — DOTW additions should go in a separate `graphql/dotw.graphql` per MOD-04, but Phase 2 adds no new schema types)
- Existing resolvers: `app/GraphQL/Queries/` and `app/GraphQL/Mutations/`
- The existing `SearchDotwHotels.php` resolver is the current entry point for DOTW GraphQL

### Header Extraction Pattern

Lighthouse middleware integrates via `config/lighthouse.php` `middleware` array (same as Laravel HTTP middleware). The Request object is available in resolvers via the GraphQL context: `$context->request()`.

For Phase 2, we use `$request->attributes->set()` in middleware and `$request->attributes->get()` in resolvers — this is the standard Laravel mechanism for passing data between middleware and controllers/resolvers without pollating the global state.

### Credential Isolation (MSG-05)

The DOTW XML wrapper (`wrapRequest()` method in `DotwService`) injects credentials into the XML string directly — they never appear in the `$params` array passed to any public method. This means:

- `searchHotels($params)` — `$params` has no credentials
- `getRooms($params)` — `$params` has no credentials
- `confirmBooking($params)` — `$params` has no credentials

The `DotwAuditService` sanitizer is a defense-in-depth measure (catches any future accidental credential inclusion), not the primary isolation mechanism.

### Sensitive Passenger Data (MSG-05)

The `confirmBooking($params)` call includes `$params['rooms'][*]['passengers']` which contains `firstName`, `lastName`, and `salutation`. These are NOT in the redaction list — DOTW spec requires passenger names for booking, and they are not credentials or government IDs. The requirement says "no sensitive passenger details in plaintext" — this means passport numbers, government IDs, credit card numbers. Names are acceptable in audit logs for booking dispute resolution.

Keys redacted by `DotwAuditService::sanitizePayload()`:
- `password`, `dotw_password`
- `dotw_username`, `username`
- `md5` (catches hashed password leakage)
- `secret`, `token`, `authorization`
- `credit_card`, `card_number`, `cvv`
- `passport_number`

### Operation Type Mapping

| DOTW Method | GraphQL Concept | `operation_type` Value |
|-------------|----------------|----------------------|
| `searchHotels()` | `searchHotels` query | `search` |
| `getRooms($params, false)` | `getRoomRates` query (browse) | `rates` |
| `getRooms($params, true)` | `blockRates` mutation | `block` |
| `confirmBooking()` or `saveBooking()` + `bookItinerary()` | `createPreBooking` mutation | `book` |

### Migration Naming Convention

Existing DOTW migrations: `2026_02_21_033317_*` and `2026_02_21_033318_*`. New migration for Phase 2 uses `2026_02_21_100001_*` to ensure it runs after existing DOTW migrations while avoiding timestamp collision.

### Why No Foreign Key to companies Table (MOD-06)

The DOTW module must be copyable to a standalone deployment. The `companies` table belongs to the invoice/task system. Foreign keys to non-DOTW tables would break standalone deployment. `company_id` is stored as a plain integer column — application code enforces isolation, not database constraints.

---

## Architecture Decisions

### Decision: Middleware vs. Lighthouse Context Extension

**Options considered:**
1. Implement `Nuwave\Lighthouse\Schema\Context` extension
2. Use standard Laravel HTTP middleware on the `/graphql` route

**Choice:** Standard Laravel HTTP middleware.

**Reason:** Simpler, no Lighthouse version-specific API surface, works with current Lighthouse 6.x pattern seen in `config/lighthouse.php`. The middleware stack is shared between Lighthouse and the rest of Laravel, so `$request->attributes` is a clean, non-coupled way to pass the header values to resolver context.

### Decision: Parameters vs. Container Binding for Resayil IDs

**Options considered:**
1. Bind `resayil_message_id` into the IoC container per-request
2. Pass as optional parameters to DotwService methods
3. Read from `request()` helper inside DotwService

**Choice:** Optional parameters on DotwService methods.

**Reason:** Keeps DotwService testable and decoupled from the HTTP layer. Callers (GraphQL resolvers) extract the header and pass it down. DotwService remains usable in CLI/artisan contexts without HTTP context.

### Decision: Nullable company_id on Audit Log

**Reason:** Phase 1 (per-company credentials) is a parallel Wave 1 phase. Phase 2 must not depend on Phase 1. The `company_id` column is nullable so Phase 2 works with the current single-credential `DotwService` and Phase 1 can later populate it when per-company credential resolution is available.

---

## No External Dependencies Added

Phase 2 introduces zero new Composer packages. All functionality uses:
- Laravel Eloquent (existing)
- Laravel Migrations (existing)
- Lighthouse GraphQL middleware (existing)
- PHP built-in `array_walk_recursive` for sanitization

---

## Files NOT Modified by Phase 2

These files are untouched — Phase 2 is additive only:

- `graphql/schema.graphql` — no new types needed (audit logs are internal)
- `config/dotw.php` — no new config keys
- `app/Models/DotwPrebook.php` — unchanged
- `app/Models/DotwRoom.php` — unchanged
- `app/GraphQL/Queries/SearchDotwHotels.php` — Phase 4 will update this resolver to pass Resayil IDs from context to DotwService
