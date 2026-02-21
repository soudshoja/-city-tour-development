---
phase: 04-hotel-search-graphql
plan: 02
subsystem: api
tags: [graphql, lighthouse, dotw, hotel-search, caching, markup, b2b]

# Dependency graph
requires:
  - phase: 04-01
    provides: graphql/dotw.graphql Phase 4 schema — SearchHotelsInput, SearchHotelsResponse, RoomTypeRate, RateMarkup types and searchHotels query definition
  - phase: 03-01
    provides: DotwCacheService with buildKey(), isCached(), remember() — 2.5-min per-company cache
  - phase: 01-01
    provides: DotwService B2B constructor path — DotwService(?int $companyId) resolves credentials from DB
provides:
  - DotwSearchHotels GraphQL resolver — searchHotels query fully wired to cache, DotwService, and schema
  - Per-company markup applied to every RoomTypeRate via DotwService::applyMarkup()
  - Cache hit detection pattern — isCached() before remember() annotates cached: true/false in response
  - Auth guard pattern — unauthenticated call returns CREDENTIALS_NOT_CONFIGURED (never falls back to env)
affects:
  - 04-PHASE (completes Phase 4)
  - 05-rate-browsing (getRoomRates resolver follows same auth guard + cache + error pattern)
  - 06-prebook-confirmation (same auth/error shape)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - DotwService instantiated inside cache closure (not injected) — per-company credential context preserved
    - isCached() called BEFORE remember() to detect cache hits without injecting cached flag into results
    - formatHotels() instantiates DotwService once, reuses across all hotels — no per-hotel instantiation
    - RuntimeException catch for credential errors, Exception catch for API errors — separate error codes

key-files:
  created:
    - app/GraphQL/Queries/DotwSearchHotels.php
  modified: []

key-decisions:
  - "DotwService instantiated inside cache remember() closure — ensures per-company credentials are always resolved with companyId, not injected into resolver constructor"
  - "isCached() before remember() pattern: wasCached flag captured before cache read so cached: true accurately reflects whether DOTW API was bypassed"
  - "DotwService reused in formatHotels() for markup — single instance across all hotels avoids repeated DB credential lookups per hotel"
  - "RuntimeException catch separates credential failures from API failures — distinct error codes for N8N workflow branching"
  - "Pint-formatted buildFilters() uses ! empty() instead of !empty() — consistent with Laravel Pint braces_position rule"

patterns-established:
  - "B2B auth guard: auth()->user()?->company?->id === null returns CREDENTIALS_NOT_CONFIGURED (Phase 5 getRoomRates resolver follows this)"
  - "Cache-then-format: cache stores raw DotwService output; markup applied AFTER cache read (not stored in cache)"
  - "Error response shape: success: false, error: {error_code, error_message, error_details, action}, cached: false, meta: {company_id: 0}, data: null"

requirements-completed: [SEARCH-05, SEARCH-06, SEARCH-07, SEARCH-08, B2B-03]

# Metrics
duration: 2min
completed: 2026-02-21
---

# Phase 4 Plan 02: DotwSearchHotels Resolver Summary

**searchHotels B2B GraphQL resolver with 2.5-minute per-company caching, markup applied to every RoomTypeRate, and CREDENTIALS_NOT_CONFIGURED guard for unauthenticated calls**

## Performance

- **Duration:** 2 min
- **Started:** 2026-02-21T06:36:30Z
- **Completed:** 2026-02-21T06:38:33Z
- **Tasks:** 1 of 1
- **Files modified:** 1

## Accomplishments
- Created `app/GraphQL/Queries/DotwSearchHotels.php` (360 lines) — the primary B2B hotel search entry point
- Implemented isCached()-before-remember() pattern: wasCached flag captured before cache read so cached: true accurately reflects API bypass
- Applied per-company markup via DotwService::applyMarkup() to every RoomTypeRate in formatHotels()
- Wired to graphql/dotw.graphql SearchHotelsResponse shape with all required fields (success, error, cached, data, meta)
- Schema registered: `php artisan lighthouse:print-schema` confirms searchHotels query appears with correct types

## Task Commits

Each task was committed atomically:

1. **Task 1: Create DotwSearchHotels resolver — main searchHotels B2B implementation** - `94801520` (feat)

**Plan metadata:** (created below)

## Files Created/Modified
- `app/GraphQL/Queries/DotwSearchHotels.php` - searchHotels GraphQL resolver — B2B hotel search with caching, markup, and error handling

## Decisions Made

**DotwService inside closure, not injected:** The plan specifies that DotwService must be instantiated inside the cache remember() closure with the resolved $companyId. This ensures each cache miss resolves credentials fresh from the DB. The resolver only injects DotwCacheService (lightweight, no DB calls in constructor).

**parseHotels() key mapping confirmed:** Cross-checked DotwService::parseHotels() (lines 1297-1344) against plan's expected keys. All matched exactly: `hotelId`, `rooms[].adults/children/childrenAges`, `rooms[].roomTypes[].code/name/rateBasisId/rateType/nonRefundable/total/totalTaxes/totalMinimumSelling`.

**DotwService reuse in formatHotels():** Plan says "instantiate DotwService once at the start of formatHotels() and reuse it — do NOT create a new instance per hotel." Implemented accordingly. This avoids N repeated DB credential lookups for N hotels.

**No audit call in resolver:** DotwService::searchHotels() already calls DotwAuditService internally (SEARCH-07 / MSG-07). The resolver does not call it — confirmed by grep showing DotwAuditService appears only in comments.

## Deviations from Plan

None — plan executed exactly as written. Pint auto-corrected minor style issues (`!empty` → `! empty`, brace position) — this is expected formatting normalization, not a deviation.

## Issues Encountered

- PHPStan not installed in this environment (vendor/bin/phpstan missing). Used PHP syntax check via `php artisan tinker` instantiation as substitute. All other verifications passed: Pint clean, resolver instantiates, schema prints correctly.

## User Setup Required

None — no external service configuration required for this plan. The resolver uses existing DotwCacheService (Phase 3) and DotwService (Phase 1) which rely on existing config/dotw.php and company_dotw_credentials table.

## Next Phase Readiness

- Phase 4 is now complete: both plans (04-01 schema + 04-02 resolver) are committed
- Phase 5 getRoomRates resolver should follow the same auth guard + cache + error pattern established here
- Pattern to reuse: `$companyId = auth()->user()?->company?->id; if ($companyId === null) return $this->errorResponse(...)` before any DotwService instantiation
- Cache-then-format pattern: store raw DOTW output in cache, apply markup after cache read (not stored)

---
*Phase: 04-hotel-search-graphql*
*Completed: 2026-02-21*
