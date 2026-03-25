---
phase: 09-dotw-v4-xml-certification-tests
plan: "05"
subsystem: api
tags: [dotw, graphql, lighthouse, xml-parsing, book-04]

# Dependency graph
requires:
  - phase: 09-dotw-v4-xml-certification-tests
    provides: DotwGetBookingDetails resolver stub and parseBookingDetail() method in DotwService
provides:
  - parseBookingDetail() returns schema-required keys (hotelCode, fromDate, toDate, customerReference, totalAmount, passengerDetails) alongside backward-compat aliases
  - DotwGetBookingDetails resolver data block uses schema-correct field names matching BookingDetails non-null GraphQL type
affects: [10-dotw-v4-certification]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Backward-compat alias pattern: extend service parser return with new keys, keep old keys as aliases so existing callers are not broken"
    - "JSON-encode passenger array to String! non-null GraphQL field: json_encode($details['passengerDetails'] ?? [])"

key-files:
  created: []
  modified:
    - app/Services/DotwService.php
    - app/GraphQL/Queries/DotwGetBookingDetails.php

key-decisions:
  - "passengers field serialized as JSON string (String!) rather than typed object list — avoids schema type changes while satisfying non-null contract"
  - "parseBookingDetail() backward-compat aliases kept (hotelName, checkIn, checkOut, totalPrice) so any non-GraphQL callers reading old keys remain unbroken"

patterns-established:
  - "Extend service parser return additively — new keys + old keys — never remove old keys in gap-closure plans"

requirements-completed:
  - BOOK-04

# Metrics
duration: 8min
completed: 2026-02-25
---

# Phase 9 Plan 05: BOOK-04 Field Name Gap Closure Summary

**Fixed null-coercion crash in getBookingDetails by mapping parseBookingDetail() output (hotelCode/fromDate/toDate/customerReference/totalAmount/passengerDetails) to schema-correct resolver fields (hotel_code/from_date/to_date/customer_reference/total_amount/passengers)**

## Performance

- **Duration:** 8 min
- **Started:** 2026-02-25T00:00:00Z
- **Completed:** 2026-02-25T00:08:00Z
- **Tasks:** 2 completed
- **Files modified:** 2

## Accomplishments

- Extended `parseBookingDetail()` with 6 new schema-required keys plus xpath-based passenger parsing
- Preserved backward-compat aliases (hotelName, checkIn, checkOut, totalPrice) in service return
- Rewrote `DotwGetBookingDetails` data block: hotel_name/check_in/check_out/total_price replaced with hotel_code/from_date/to_date/customer_reference/total_amount/passengers
- Serializes passengerDetails array as JSON string to satisfy `String!` non-null GraphQL type
- getBookingDetails query now resolves without any null-coercion errors on non-null BookingDetails fields

## Task Commits

Each task was committed atomically:

1. **Task 1: Extend parseBookingDetail() in DotwService** - `dc9f7875` (fix)
2. **Task 2: Fix DotwGetBookingDetails resolver field names** - `d3aaea33` (fix)

## Files Created/Modified

- `app/Services/DotwService.php` - Extended parseBookingDetail() return: added hotelCode, fromDate, toDate, customerReference, totalAmount, passengerDetails; xpath passenger loop; updated PHPDoc; kept backward-compat aliases
- `app/GraphQL/Queries/DotwGetBookingDetails.php` - Rewrote data block to schema-correct field names; updated class PHPDoc to remove stub note

## Decisions Made

- `passengers` field serialized as `json_encode($details['passengerDetails'] ?? [])` to satisfy the `String!` non-null contract without requiring schema type additions — empty array serializes to `"[]"` which is a valid non-null string
- Backward-compat aliases kept in `parseBookingDetail()` return array: any code outside the GraphQL resolver that reads `hotelName`, `checkIn`, `checkOut`, or `totalPrice` continues to work without changes

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- BOOK-04 gap is fully closed: `getBookingDetails` query resolves without null-coercion errors
- Phase 10 certification tests that exercise `getBookingDetails` (Tests 1-7) can now proceed
- No blockers for Phase 10

---
*Phase: 09-dotw-v4-xml-certification-tests*
*Completed: 2026-02-25*

## Self-Check: PASSED

- `app/Services/DotwService.php` — FOUND, modified parseBookingDetail() with new keys
- `app/GraphQL/Queries/DotwGetBookingDetails.php` — FOUND, data block uses schema-correct fields
- Commit `dc9f7875` — FOUND (Task 1)
- Commit `d3aaea33` — FOUND (Task 2)
