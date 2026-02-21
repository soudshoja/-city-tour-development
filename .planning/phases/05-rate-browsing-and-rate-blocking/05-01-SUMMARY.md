---
phase: 05-rate-browsing-and-rate-blocking
plan: "01"
subsystem: api
tags: [graphql, lighthouse, eloquent, dotw, migrations]

# Dependency graph
requires:
  - phase: 04-hotel-search-graphql
    provides: "SearchHotelRoomInput type (reused by GetRoomRatesInput and BlockRatesInput)"
  - phase: 03-cache-service-and-graphql-response-architecture
    provides: "DotwMeta, DotwError, DotwErrorCode, DotwErrorAction types (reused in all Phase 5 response types)"

provides:
  - "Migration adding company_id + resayil_message_id columns with compound index to dotw_prebooks table"
  - "DotwPrebook model with company_id, resayil_message_id in fillable/casts and activeForUser() scope for BLOCK-08 enforcement"
  - "GetRoomRatesInput, BlockRatesInput — Phase 5 query and mutation input types in dotw.graphql"
  - "GetRoomRatesResponse, GetRoomRatesData, RoomRateResult — rate browsing response chain"
  - "RateDetail — full rate type with RATE-05 currency fields (original_currency, exchange_rate, final_currency)"
  - "CancellationRule — cancellation policy data type"
  - "BlockRatesResponse, BlockRatesData — rate blocking response with prebook_key and countdown_timer_seconds"
  - "getRoomRates query and blockRates mutation declared with @field resolver references"

affects:
  - 05-rate-browsing-and-rate-blocking (Plans 02 and 03 implement the declared resolvers)
  - 06-pre-booking-and-confirmation-workflow (consumes prebook_key and DotwPrebook.activeForUser)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "No FK on company_id in DOTW tables — standalone module per MOD-06 (consistent with dotw_audit_logs)"
    - "Compound index on (company_id, resayil_message_id, expired_at) for efficient BLOCK-08 active-prebook queries"
    - "activeForUser() static scope returns Builder — caller chains additional constraints"
    - "Phase 5 operations always cached: false — rates change minute-to-minute, tokens expire"
    - "allocation_details as opaque passthrough — documented as verbatim-only token"

key-files:
  created:
    - database/migrations/2026_02_21_155718_add_company_id_resayil_to_dotw_prebooks_table.php
  modified:
    - app/Models/DotwPrebook.php
    - graphql/dotw.graphql

key-decisions:
  - "No FK on company_id in dotw_prebooks — consistent with dotw_audit_logs standalone module approach (MOD-06)"
  - "getRoomRates always returns cached: false — rates change minute-to-minute, allocationDetails tokens expire"
  - "blockRates always returns cached: false — blocking is a side-effecting mutation, never cached"
  - "RateDetail.original_currency is String! (not nullable) — empty string sentinel when DOTW omits currency (RATE-05)"
  - "RateDetail.exchange_rate is Float (nullable) — null when DOTW performs no currency conversion (RATE-05)"
  - "activeForUser() scope uses where expired_at > now() — matches compound index column order for query optimization"

patterns-established:
  - "Phase 5 resolver class paths follow Phase 4 pattern: Queries/DotwGetRoomRates, Mutations/DotwBlockRates"
  - "Input types reuse SearchHotelRoomInput from Phase 4 — no redefinition in Phase 5"

requirements-completed:
  - RATE-01
  - RATE-02
  - RATE-03
  - RATE-04
  - RATE-05
  - RATE-06
  - RATE-07
  - BLOCK-01
  - BLOCK-04
  - BLOCK-05
  - BLOCK-08
  - MARKUP-03
  - MARKUP-04
  - MARKUP-05

# Metrics
duration: 2min
completed: 2026-02-21
---

# Phase 5 Plan 01: Rate Browsing & Rate Blocking — Data Layer & GraphQL Contracts Summary

**DotwPrebook model extended with BLOCK-08 columns + activeForUser scope, and 10 new GraphQL types (getRoomRates query, blockRates mutation) with RATE-05 currency transparency declared in dotw.graphql**

## Performance

- **Duration:** 2 min
- **Started:** 2026-02-21T07:57:15Z
- **Completed:** 2026-02-21T07:59:30Z
- **Tasks:** 3
- **Files modified:** 3

## Accomplishments

- Migration adds company_id + resayil_message_id to dotw_prebooks with compound index on (company_id, resayil_message_id, expired_at) — no FK per MOD-06 standalone design
- DotwPrebook model updated with new columns in fillable/casts, PHPDoc properties, and activeForUser() static scope returning Builder for BLOCK-08 single-active-prebook enforcement
- graphql/dotw.graphql extended with 10 new types: GetRoomRatesInput, BlockRatesInput, GetRoomRatesResponse, GetRoomRatesData, RoomRateResult, RateDetail (with RATE-05 currency fields), CancellationRule, BlockRatesResponse, BlockRatesData — plus getRoomRates query and blockRates mutation declarations pointing to Plans 02+03 resolver classes

## Task Commits

Each task was committed atomically:

1. **Task 1: Migration — add company_id and resayil_message_id to dotw_prebooks** - `8435d2e8` (chore)
2. **Task 2: Update DotwPrebook model for new columns + activeForUser scope** - `7359ccb0` (feat)
3. **Task 3: Extend graphql/dotw.graphql with Phase 5 types and operations** - `1e6d618d` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified

- `database/migrations/2026_02_21_155718_add_company_id_resayil_to_dotw_prebooks_table.php` — Adds company_id, resayil_message_id columns + compound index; no FK (MOD-06)
- `app/Models/DotwPrebook.php` — New fillable entries, integer cast for company_id, PHPDoc @property, activeForUser() scope
- `graphql/dotw.graphql` — 10 new types + 2 operation declarations (257 lines added, no existing types modified)

## Decisions Made

- No FK on company_id in dotw_prebooks — consistent with dotw_audit_logs standalone module approach (MOD-06)
- getRoomRates always returns cached: false — rates change minute-to-minute, allocationDetails tokens expire immediately once consumed
- blockRates always returns cached: false — blocking is a side-effecting mutation, caching would be incorrect
- RateDetail.original_currency is String! (non-nullable, empty string sentinel when DOTW omits) per RATE-05
- RateDetail.exchange_rate is Float (nullable) — null when DOTW performs no conversion per RATE-05
- activeForUser() scope uses where('expired_at', '>', now()) — matches compound index column order for query plan optimization
- SearchHotelRoomInput reused from Phase 4 — not redefined in Phase 5 input types

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

Pint detected a minor PHPDoc formatting issue after editing DotwPrebook.php (no_superfluous_phpdoc_tags, phpdoc_trim). Applied Pint to fix automatically — no functional change, purely style.

## User Setup Required

None — no external service configuration required. Migration will be applied when database is available.

## Next Phase Readiness

- Plans 02 and 03 can now be executed: resolver class paths are declared in the schema, DotwPrebook has the columns needed for BLOCK-08
- Plan 02 (DotwGetRoomRates resolver) depends on this plan's GetRoomRatesInput, GetRoomRatesResponse, RoomRateResult, RateDetail types
- Plan 03 (DotwBlockRates resolver) depends on this plan's BlockRatesInput, BlockRatesData types and DotwPrebook.activeForUser() scope
- Schema parses without type-level errors — confirmed via `php artisan lighthouse:print-schema`

---
*Phase: 05-rate-browsing-and-rate-blocking*
*Completed: 2026-02-21*
