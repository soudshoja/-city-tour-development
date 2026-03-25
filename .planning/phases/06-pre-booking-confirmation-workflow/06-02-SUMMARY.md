---
phase: 06-pre-booking-confirmation-workflow
plan: "02"
subsystem: api
tags: [dotw, graphql, lighthouse, eloquent, booking, validation, transaction]

requires:
  - phase: 06-01
    provides: DotwBooking model, dotw_bookings migration, createPreBooking schema types
  - phase: 05-rate-browsing-and-rate-blocking
    provides: DotwPrebook model (isValid(), activeForUser()), DotwBlockRates pattern reference
  - phase: 01-credential-management-and-markup-foundation
    provides: DotwService constructor with companyId, applyMarkup()

provides:
  - Full DotwCreatePreBooking resolver (434 lines) — all BOOK-01..08, ERROR-03, ERROR-04 requirements
  - ALLOCATION_EXPIRED check before DOTW API call (ERROR-03)
  - Passenger count + field-level + email validation (BOOK-02)
  - DotwService::confirmBooking() integration with raw allocationDetails (BOOK-04)
  - DB::transaction wrapping prebook expiry + DotwBooking::create() (BOOK-06)
  - Fail-silent supplementary audit log capturing prebook_key + confirmation_code (BOOK-07)
  - formatAlternatives() helper mapping raw searchHotels() output to HotelSearchResult schema shape
  - RATE_UNAVAILABLE / sold-out alternative hotel search with fail-silent catch (BOOK-08, ERROR-04)

affects: [07-error-hardening, 08-modular-architecture]

tech-stack:
  added: []
  patterns: [errorResponse/buildMeta helper pattern, fail-silent try/catch Throwable, RuntimeException vs Exception separation for distinct error codes, formatAlternatives helper for schema-shape mapping]

key-files:
  created: []
  modified:
    - app/GraphQL/Mutations/DotwCreatePreBooking.php (stub replaced with 434-line full implementation)

key-decisions:
  - "RuntimeException catch for credentials not configured (RECONFIGURE_CREDENTIALS) — Exception catch for DOTW booking failures (RATE_UNAVAILABLE / API_ERROR) — same separation as DotwBlockRates"
  - "formatAlternatives() private helper maps raw DotwService::searchHotels() output to HotelSearchResult schema shape with markup — searchHotels() returns raw array, not schema-shaped"
  - "Resayil headers pulled from both request()->attributes (ResayilContextMiddleware sets these) and request()->header() as fallback — defensive against middleware ordering"
  - "adultsCode field from SearchHotelRoomInput accessed as $firstRoom['adultsCode'] ?? $firstRoom['adults'] — handles both GraphQL input naming conventions"
  - "company_id null check before alternative hotel search — avoids instantiating DotwService without credentials"
  - "RESUBMIT NOT used anywhere — RETRY for resubmittable validation errors, RESEARCH for rate issues, RECONFIGURE_CREDENTIALS for credential failures"
  - "Supplementary audit log (BOOK-07) uses fail-silent \Throwable catch — audit failure must never break booking response (established pattern)"

patterns-established:
  - "Two-phase audit for mutations: Phase A (DotwService internal, raw API call), Phase B (supplementary resolver log, post-transaction prebook_key + confirmation_code)"
  - "formatAlternatives() helper pattern: maps raw service array to schema type — reusable for future alternative-suggestion features"
  - "Empty itinerary pattern: emptyItinerary() provides required non-null BookingItinerary structure for error responses"

requirements-completed: [BOOK-01, BOOK-02, BOOK-03, BOOK-04, BOOK-05, BOOK-06, BOOK-07, BOOK-08, ERROR-03, ERROR-04]

duration: 20min
completed: 2026-02-21
---

# Phase 06-02: DotwCreatePreBooking Resolver Summary

**Full createPreBooking mutation resolver (434 lines) covering BOOK-01..08, ERROR-03, ERROR-04 — prebook validation, passenger validation, confirmBooking, atomic transaction, audit, and alternative hotel suggestion**

## Performance

- **Duration:** 20 min
- **Started:** 2026-02-21T17:05:00Z
- **Completed:** 2026-02-21T17:25:00Z
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments
- Full DotwCreatePreBooking resolver — 434 lines replacing 34-line stub from Plan 06-01
- ERROR-03 check (ALLOCATION_EXPIRED + RESEARCH) before any DOTW API call — zero wasted API calls on expired prebooks
- BOOK-02 passenger validation: count matches total adults across rooms, all 6 required fields, email format validation
- BOOK-04: DotwService::confirmBooking() called with reconstructed params — raw allocationDetails (no encoding)
- BOOK-06: DB::transaction atomically expires prebook + creates dotw_bookings record
- BOOK-07: Fail-silent supplementary audit log (two-phase pattern consistent with blockRates)
- BOOK-08/ERROR-04: RATE_UNAVAILABLE path calls searchHotels() + formatAlternatives() for up to 3 alternatives

## Task Commits

1. **Task 1: Full DotwCreatePreBooking resolver** - `de86107a` (feat)

## Files Created/Modified
- `app/GraphQL/Mutations/DotwCreatePreBooking.php` — Full resolver (434 lines). Implements BOOK-01..08, ERROR-03, ERROR-04

## Decisions Made
- `formatAlternatives()` helper added — DotwService::searchHotels() returns raw array (not schema-shaped), requires mapping to HotelSearchResult type with markup applied per DotwService::applyMarkup()
- Plan template used `$altResults['hotels'] ?? []` which would have been wrong (searchHotels returns an array, not `['hotels' => [...]]`) — fixed to call searchHotels() then slice the raw array
- Resayil headers read from both request()->attributes (ResayilContextMiddleware) and request()->header() as fallback — defensive approach that ensures headers available regardless of middleware order
- companyId null guard before alternative search — avoids DotwService instantiation without credentials when company context is unknown

## Deviations from Plan

### Auto-fixed Issues

**1. [Plan template bug] searchHotels() return value shape**
- **Found during:** Task 1 (implementing ERROR-04 alternative hotel path)
- **Issue:** Plan template used `$altResults['hotels'] ?? []` but DotwService::searchHotels() returns a flat array of hotels, not `['hotels' => [...]]`
- **Fix:** Call searchHotels(), slice result with array_slice($rawHotels, 0, 3), then format with formatAlternatives() private helper
- **Files modified:** app/GraphQL/Mutations/DotwCreatePreBooking.php
- **Verification:** formatAlternatives() maps hotel['hotelId'] → hotel_code, applies DotwService::applyMarkup() per room type
- **Committed in:** de86107a

**2. [Plan template] Resayil header access pattern**
- **Found during:** Task 1 (comparing with DotwBlockRates reference implementation)
- **Issue:** Plan template used `request()->header('X-Resayil-Message-ID')` but DotwBlockRates uses `$context?->request()->attributes->get('resayil_message_id')` (ResayilContextMiddleware sets these on request attributes)
- **Fix:** Used request()->attributes->get() with request()->header() as fallback — both paths available
- **Files modified:** app/GraphQL/Mutations/DotwCreatePreBooking.php
- **Verification:** Consistent with ResayilContextMiddleware attribute-setting pattern
- **Committed in:** de86107a

---

**Total deviations:** 2 auto-fixed (both plan template bugs — implementation is correct per actual codebase)
**Impact on plan:** Both fixes required for correctness. No scope creep.

## Issues Encountered
- None beyond the two plan template corrections documented above.

## Next Phase Readiness
- Phase 6 is complete — all 10 requirement IDs (BOOK-01..08, ERROR-03, ERROR-04) implemented and committed
- lighthouse:print-schema validates cleanly — createPreBooking mutation is fully resolvable
- Phase 7 (Error Hardening & Circuit Breaker) and Phase 8 (Modular Architecture & B2B Packaging) can proceed

---
*Phase: 06-pre-booking-confirmation-workflow*
*Completed: 2026-02-21*
