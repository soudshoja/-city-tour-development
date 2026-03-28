---
phase: 24-dotw-certification-fixes-v2-olga-march-27-feedback
plan: 02
subsystem: api
tags: [dotw, booking, nationality, residence, apr, certification]

# Dependency graph
requires:
  - phase: 19-b2b-b2c-booking
    provides: BookingService prebook/confirm flow
  - phase: 21-lifecycle-history
    provides: LifecycleService reminder queries, APR auto-invoice on confirmation
provides:
  - APR booking flow removed from all DotwAI services
  - All bookings use confirmBooking only (no savebooking+bookitinerary)
  - Nationality and residence resolved from user input via fuzzy matching
  - DotwCertify test 16 marked N/A with DOTW explanation
affects: [phase-24-certification, dotw-api-calls, booking-lifecycle]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "resolveResidenceCode() mirrors resolveNationalityCode() pattern in HotelSearchService"
    - "Config default 66 (Kuwait) as fallback when user does not provide residence"

key-files:
  created: []
  modified:
    - app/Modules/DotwAI/Services/BookingService.php
    - app/Modules/DotwAI/Services/HotelSearchService.php
    - app/Modules/DotwAI/Services/LifecycleService.php
    - app/Modules/DotwAI/Services/MessageBuilderService.php
    - app/Modules/DotwAI/Views/voucher-pdf.blade.php
    - app/Modules/DotwAI/Config/dotwai-system-message.md
    - app/Console/Commands/DotwCertify.php

key-decisions:
  - "APR flow removed entirely — DOTW confirmed APRs no longer exist in their API (Olga Chicu, March 2026)"
  - "is_apr field kept in DB schema/fillable for backward compatibility but always set to false going forward"
  - "resolveResidenceCode() added to HotelSearchService mirroring resolveNationalityCode() pattern"
  - "Config default 66 (Kuwait) remains as fallback for residence when not provided by user"
  - "LifecycleService is_apr=false filter removed — all confirmed bookings now receive reminders"

requirements-completed: [CERT-04, CERT-05]

# Metrics
duration: 10min
completed: 2026-03-28
---

# Phase 24 Plan 02: APR Removal and Nationality/Residence Wiring Summary

**APR booking flow (savebooking+bookitinerary) removed from all DotwAI services and nationality/residence now resolved from user input via fuzzy matching with Kuwait config fallback**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-03-28T04:00:00Z
- **Completed:** 2026-03-28T04:09:23Z
- **Tasks:** 2
- **Files modified:** 7

## Accomplishments

- Removed all APR-specific code paths: `invoiceAPRBooking()` method, APR auto-invoice blocks in `confirmWithCredit` and `confirmAfterPayment`, `callDotwConfirm` APR branch (savebooking+bookitinerary)
- All bookings now exclusively use `confirmBooking` — no conditional branching on `is_apr`
- LifecycleService `getDueReminders()` query no longer filters `where('is_apr', false)` — all confirmed bookings get cancellation reminders
- WhatsApp messages and PDF vouchers show "Non-Refundable" (not "Non-Refundable (APR)")
- DotwCertify test 16 marked as N/A with clear explanation referencing Olga's confirmation
- Added `resolveResidenceCode()` to HotelSearchService (mirrors `resolveNationalityCode()` using fuzzy matching)
- `searchHotels()` and `getHotelDetails()` both call `resolveResidenceCode($input['residence'] ?? null)`
- `resolveHotelAndRoom()` in BookingService uses `$input['nationality_code'/'residence_code']` with config fallback
- `buildRoomSelectionsFromBooking()` already used `$booking->nationality_code/residence_code` (no change needed)

## Task Commits

Each task was committed atomically:

1. **Task 1: Remove APR flow from all DotwAI services** - `32fc127c` (feat)
2. **[Deviation cleanup] Clean up stale APR PHPDoc reference** - `83a0ffe0` (fix)
3. **Task 2: Wire nationality/residence from user input** - `cc222c92` (feat)

## Files Created/Modified

- `app/Modules/DotwAI/Services/BookingService.php` - Removed invoiceAPRBooking(), APR auto-invoice blocks, callDotwConfirm APR branch; resolveHotelAndRoom now uses input nationality/residence
- `app/Modules/DotwAI/Services/HotelSearchService.php` - Added resolveResidenceCode(), updated searchHotels() and getHotelDetails() to use it; removed isAPR variable in parseRoomDetails
- `app/Modules/DotwAI/Services/LifecycleService.php` - Removed where('is_apr', false) from getDueReminders(), updated PHPDoc
- `app/Modules/DotwAI/Services/MessageBuilderService.php` - Removed APR label from formatPrebookConfirmation() and formatVoucherMessage()
- `app/Modules/DotwAI/Views/voucher-pdf.blade.php` - Removed is_apr condition from cancellation policy block, changed "Non-Refundable (APR)" to "Non-Refundable"
- `app/Modules/DotwAI/Config/dotwai-system-message.md` - Replaced APR Rates section with note that APRs are removed by DOTW
- `app/Console/Commands/DotwCertify.php` - Test 16 runTest16() replaced with startTest+skipTest, body made unreachable

## Decisions Made

- `is_apr` DB column/fillable/casts retained for backward compat (migration removal is risky) — column will always be false
- Config default 66 (Kuwait) fallback for residence is correct — agency operates from Kuwait
- The unreachable APR test code in DotwCertify was kept (with `return;` above it) to preserve historical context of what the test did

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Stale PHPDoc reference to savebooking+bookitinerary in confirmWithCredit**
- **Found during:** Task 1 (verification step)
- **Issue:** PHPDoc said "immediately calls DOTW confirmBooking (or saveBooking + bookItinerary for APR rates)" which was stale after removing the APR flow
- **Fix:** Updated PHPDoc to simply say "immediately calls DOTW confirmBooking"
- **Files modified:** app/Modules/DotwAI/Services/BookingService.php
- **Committed in:** 83a0ffe0

---

**Total deviations:** 1 auto-fixed (Rule 1 - stale comment cleanup)
**Impact on plan:** Minor documentation cleanup, no functional impact.

## Issues Encountered

None - plan executed cleanly.

## User Setup Required

None - no external service configuration required.

## Known Stubs

None - all changes are clean code removal and method additions with live data flow.

## Next Phase Readiness

- CERT-04 (nationality/residence) and CERT-05 (APR removal) requirements are complete
- DotwCertify test 16 will now show as SKIP with clear explanation for Olga
- Remaining phase 24 plans: CERT-01 (salutation IDs), CERT-02 (special request codes), CERT-03 (rateBasis=0), CERT-06 (2-room cancel), CERT-07 (mandatory display), CERT-08 (B2B/B2C doc)

---
*Phase: 24-dotw-certification-fixes-v2-olga-march-27-feedback*
*Completed: 2026-03-28*
