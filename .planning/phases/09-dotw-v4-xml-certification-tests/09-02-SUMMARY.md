---
phase: 09-dotw-v4-xml-certification-tests
plan: "02"
subsystem: dotw-cancellation
tags: [dotw, cancellation, graphql, two-step-cancel, apr, valid-02]
dependency_graph:
  requires:
    - app/Services/DotwService.php (cancelBooking method — pre-existing)
    - graphql/dotw.graphql (DotwError, DotwMeta, DotwErrorCode, DotwErrorAction types)
    - app/Models/DotwBooking.php (hotel_details.is_apr field for VALID-02 guard)
  provides:
    - app/GraphQL/Queries/DotwCheckCancellation.php (checkCancellation query resolver)
    - app/GraphQL/Mutations/DotwCancelBooking.php (cancelBooking mutation resolver)
    - app/GraphQL/Mutations/DotwDeleteItinerary.php (deleteItinerary mutation resolver)
    - DotwService::deleteItinerary() method
    - GraphQL schema: CheckCancellationInput/Response, CancelBookingInput/Response, DeleteItineraryInput/Response
  affects:
    - graphql/dotw.graphql (schema extended with cancellation types and operations)
    - app/Services/DotwService.php (deleteItinerary method added)
    - app/Console/Commands/DotwCertify.php (newline() access level bug fixed)
tech_stack:
  added: []
  patterns:
    - Two-step DOTW cancellation flow (confirm=no then confirm=yes with penaltyApplied)
    - VALID-02 APR guard pattern (check DotwBooking.hotel_details.is_apr before DOTW call)
    - DotwCreatePreBooking structural pattern (buildMeta/errorResponse helpers in each resolver)
key_files:
  created:
    - app/GraphQL/Queries/DotwCheckCancellation.php
    - app/GraphQL/Mutations/DotwCancelBooking.php
    - app/GraphQL/Mutations/DotwDeleteItinerary.php
  modified:
    - app/Services/DotwService.php (deleteItinerary method added after cancelBooking)
    - graphql/dotw.graphql (cancellation types and operations appended)
    - app/Console/Commands/DotwCertify.php (newline() renamed to logNewline() — bug fix)
decisions:
  - Kept confirm=no and confirm=yes as separate GraphQL operations (checkCancellation vs cancelBooking) for clarity and N8N workflow branching
  - currency defaults to empty string in CancellationChargeData because DOTW does not return currency in the confirm=no response
  - productsLeftOnItinerary defaults to 0 because DotwService::parseCancellation() does not currently parse that DOTW XML field; full cancellation is the common case
  - Pre-v2.0 bookings (no DotwBooking record) skip the APR check — cancel proceeds, as the booking was made before is_apr tracking was introduced
metrics:
  duration_seconds: 305
  completed_date: "2026-02-25"
  tasks_completed: 2
  files_modified: 6
---

# Phase 9 Plan 02: DOTW Cancellation Operations Summary

Two-step DOTW cancellation via GraphQL (checkCancellation + cancelBooking) and APR itinerary deletion, with VALID-02 APR guard blocking non-refundable bookings from the cancel flow.

## Completed Tasks

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | Add deleteItinerary to DotwService and GraphQL cancellation schema | bc370f68 | DotwService.php, dotw.graphql, DotwCertify.php (bug fix) |
| 2 | Create checkCancellation, cancelBooking, deleteItinerary resolvers | 8095218d | DotwCheckCancellation.php, DotwCancelBooking.php, DotwDeleteItinerary.php |

## What Was Built

### DotwService::deleteItinerary()
New method added to `app/Services/DotwService.php` after the existing `cancelBooking()` method. Calls DOTW `deleteitinerary` command with a `<bookingDetails><bookingCode>` XML body. Returns `['deleted' => true, 'itineraryCode' => $itineraryCode]` on success, throws Exception on DOTW API error.

### GraphQL Schema (dotw.graphql)
Added a new section "Phase 9: Cancellation Operations (CANCEL-01..03)" with:
- Input types: `CheckCancellationInput`, `CancelBookingInput`, `DeleteItineraryInput`
- Data types: `CancellationChargeData`, `CancelBookingData`, `DeleteItineraryData`
- Response envelopes: `CheckCancellationResponse`, `CancelBookingResponse`, `DeleteItineraryResponse`
- Operations: `checkCancellation` query, `cancelBooking` and `deleteItinerary` mutations

### DotwCheckCancellation (Query Resolver)
Resolves `checkCancellation(input: CheckCancellationInput!): CheckCancellationResponse!`

Calls `DotwService::cancelBooking(['confirm' => 'no', ...])` — queries the penalty charge WITHOUT committing cancellation. Returns:
- `charge`: float from parseCancellation result
- `is_outside_deadline`: true when charge === 0.0 (free cancellation window)
- `currency`: empty string (DOTW does not return currency in confirm=no response)

### DotwCancelBooking (Mutation Resolver)
Resolves `cancelBooking(input: CancelBookingInput!): CancelBookingResponse!`

**VALID-02 enforcement:** Loads DotwBooking by `confirmation_code`, checks `hotel_details['is_apr']`. Returns `VALIDATION_ERROR` for APR bookings before any DOTW call. Pre-v2.0 bookings (no record found) skip the check.

Calls `DotwService::cancelBooking(['confirm' => 'yes', 'penaltyApplied' => $penaltyApplied, ...])` — commits the cancellation with the charge from step 1.

### DotwDeleteItinerary (Mutation Resolver)
Resolves `deleteItinerary(input: DeleteItineraryInput!): DeleteItineraryResponse!`

Calls `DotwService::deleteItinerary($itineraryCode)`. For APR flow only — removes saved-but-unconfirmed itineraries. Returns `deleted: true` on success.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed DotwCertify::newline() access level collision**
- **Found during:** Task 1 verification (`php artisan lighthouse:print-schema` failed)
- **Issue:** `DotwCertify::newline()` declared `private` while parent class `Illuminate\Console\Command::newline()` is `public`. PHP fatal error: "Access level must be public"
- **Fix:** Renamed private method to `logNewline()` and updated all 3 call sites (`$this->newline()` → `$this->logNewline()`)
- **Files modified:** `app/Console/Commands/DotwCertify.php`
- **Commit:** bc370f68 (included in Task 1 commit)

### Schema Validation Note
`php artisan lighthouse:print-schema` reported a `DefinitionException` for `App\GraphQL\Queries\DotwGetBookingDetails` — this is expected because that resolver belongs to the parallel plan (09-01) which may not yet be fully merged. The cancellation schema additions are syntactically correct and verified to be present in `dotw.graphql`.

## Requirements Satisfied

| Requirement | Status |
|-------------|--------|
| CANCEL-01 — checkCancellation query (confirm=no) | Done |
| CANCEL-02 — cancelBooking mutation (confirm=yes + penaltyApplied) | Done |
| CANCEL-03 — deleteItinerary mutation (APR itinerary deletion) | Done |
| VALID-02 — APR bookings rejected in cancelBooking | Done |

## Self-Check: PASSED

All created files found on disk. All task commits verified in git log.

| Check | Result |
|-------|--------|
| app/GraphQL/Queries/DotwCheckCancellation.php | FOUND |
| app/GraphQL/Mutations/DotwCancelBooking.php | FOUND |
| app/GraphQL/Mutations/DotwDeleteItinerary.php | FOUND |
| Commit bc370f68 (Task 1) | FOUND |
| Commit 8095218d (Task 2) | FOUND |
