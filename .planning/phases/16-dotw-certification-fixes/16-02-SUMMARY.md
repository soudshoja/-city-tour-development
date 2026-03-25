---
phase: 16-dotw-certification-fixes
plan: 02
subsystem: api
tags: [dotw, xml, certification, hotel-booking, php, skip-to-pass]

# Dependency graph
requires:
  - 16-01 (mechanical fixes: pagination, roomField, rateBasis, salutation)
provides:
  - changedOccupancy confirmbooking XML dual-source pattern documented with verification logging
  - Test 15 uses DOTW-provided hotel 2344175 (The S Hotel Al Barsha) with exact specified dates/occupancy
  - Test 6 uses 60-day future dates and scans all hotels for 2+ room types
  - Tests 16, 17, 18, 20 have expanded multi-hotel + Conrad 809755 fallback search strategies

affects: [16-01, DOTW certification submission]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Test 15 uses DOTW hotelid filter in searchhotels <return><filters><c:condition> to target specific hotel"
    - "Tests 16/17/18/20 use Conrad London 809755 as targeted fallback after generic city scan fails"
    - "changedOccupancy dual-source: adultsCode+children from validForOccupancy; actualAdults+actualChildren from original search"

key-files:
  created: []
  modified:
    - app/Console/Commands/DotwCertify.php

key-decisions:
  - "Test 6 dates changed from 2 days to 60 days out — sandbox error 60 (deadline expired) is triggered by near-future dates, not a code bug; 60-day window is still within most hotel cancellation penalty periods"
  - "Test 15 uses hardcoded DOTW-provided dates 2026-05-14 to 2026-05-15 with hotel 2344175 — DOTW confirmed this specific hotel/date combination has active specials"
  - "Hotel 809755 (Conrad London St James) added as fallback for tests 16, 17, 18, 20 — DOTW provided this hotel ID for MSP (test 11) but luxury hotels often have APR/restricted/minStay/propertyFee features"
  - "Test 18 extended from 2 nights to 4 nights — longer stays increase probability of triggering minStay constraints"
  - "Tests 17 and 18 scan ALL returned hotels (removed 3-hotel limit) — DOTW pagination removal means full result set is returned"

requirements-completed: [DOTW-FIX-05, DOTW-FIX-06]

# Metrics
duration: 12min
completed: 2026-03-17
---

# Phase 16 Plan 02: DOTW Certification Fixes (SKIP→PASS and changedOccupancy) Summary

**changedOccupancy confirmbooking gets explicit dual-source verification logging; all 6 previously-SKIP tests (6, 15, 16, 17, 18, 20) updated with DOTW-provided hotel IDs and expanded multi-hotel search strategies**

## Performance

- **Duration:** 12 min
- **Started:** 2026-03-17T08:24:21Z
- **Completed:** 2026-03-17T08:35:57Z
- **Tasks:** 2
- **Files modified:** 1

## Accomplishments

- Added DOTW changedOccupancy dual-source pattern comment block and 4 VERIFICATION XML log lines to test 14 confirmbooking step showing adultsCode/children from validForOccupancy and actualAdults/actualChildren from original search
- Test 15 (Special Promotions): replaced generic Dubai hotel scan with direct DOTW-provided hotel 2344175 (The S Hotel Al Barsha), hardcoded to 2026-05-14 to 2026-05-15 with 2A+2C ages 8 and 12 per DOTW specification
- Test 6 (Cancel with penalty): changed dates from `addDays(2)` to `addDays(60)` and changed hotel selection from first-hotel to scan-all-hotels-for-2-room-types
- Test 16 (APR): added hotel 809755 (Conrad London) as tertiary fallback after Dubai rateBasis=1331 and Dubai rateBasis=-1 both fail
- Test 17 (Restricted Cancellation): removed 3-hotel scan limit (now scans ALL returned hotels); added Conrad 809755 getRooms fallback
- Test 18 (Minimum Stay): extended stay from 2 nights to 4 nights; removed 5-hotel scan limit; added Conrad 809755 getRooms fallback
- Test 20 (Property Fees): scans all Dubai hotels for propertyFees; adds Conrad 809755 searchhotels fallback

## Task Commits

Each task was committed atomically:

1. **Task 1: Add changedOccupancy dual-source verification logging** - `6bd2db1e` (feat)
2. **Task 2: Convert SKIP tests to PASS using DOTW hotel IDs and expanded strategies** - `298bfd7d` (feat)

## Files Created/Modified

- `app/Console/Commands/DotwCertify.php` - changedOccupancy logging added; test 6/15/16/17/18/20 updated with targeted hotel IDs and expanded search strategies

## Decisions Made

- Test 6 dates 60 days out: sandbox error 60 = "cancellation deadline expired" is triggered by 2-day future dates because those dates fall inside the last-minute no-cancel window. At 60 days the booking can be confirmed and cancelled with or without a penalty depending on the hotel's policy.
- Test 15 DOTW-specified parameters: hotel 2344175, May 14-15 2026, 2A+2C ages 8+12 used verbatim from DOTW's certification feedback — makes this test deterministic rather than hoping any Dubai hotel has specials on a given day.
- Hotel 809755 (Conrad London) reused as fallback: DOTW mentioned this hotel for MSP (test 11) but its features as a luxury London property make it a good candidate for APR, restricted cancellation, minStay and property fees.
- Test 18 extended to 4 nights: minStay constraints are more commonly triggered on longer stays; extending from 2 to 4 nights costs nothing and increases coverage.

## Deviations from Plan

None — plan executed exactly as written. All tasks completed as specified.

## Issues Encountered

- Test 15 SKIPs remain possible if DOTW's confirmed specials for hotel 2344175 expire before certification run. The SKIP message now explicitly names the hotel and dates so the reviewer knows the parameters were correctly specified.
- Tests 17, 18, 20 fallbacks to hotel 809755 may also SKIP if Conrad London has no restricted/minStay/fee rates in the sandbox. The cascade of Dubai scan + Conrad fallback significantly reduces SKIP probability compared to the original code.

## User Setup Required

None.

## Next Phase Readiness

- All 6 DOTW certification compliance requirements (DOTW-FIX-01 through DOTW-FIX-06) are now addressed across plans 01 and 02
- Full certification run (`php artisan dotw:certify`) against the DOTW sandbox will demonstrate whether any tests still SKIP due to sandbox data limitations
- PHPStan passes cleanly, Pint formatting applied

---
*Phase: 16-dotw-certification-fixes*
*Completed: 2026-03-17*
