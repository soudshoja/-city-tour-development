---
phase: 19-b2b-b2c-booking
plan: 01
subsystem: api
tags: [dotw, hotel-booking, credit, pessimistic-locking, b2b, b2c, whatsapp]

# Dependency graph
requires:
  - phase: 18-foundation-search
    provides: DotwAIContext DTO, HotelSearchService with getCachedResults and phone-cache, DotwAIResponse static envelope, MessageBuilderService, DotwService wrapper pattern

provides:
  - DotwAIBooking Eloquent model with full booking lifecycle (prebooked → confirmed/failed)
  - Migration for dotwai_bookings table (no FK constraints for module isolation)
  - CreditService with pessimistic lockForUpdate credit deduction and getBalance
  - BookingService orchestrating prebook, confirmWithCredit, confirmAfterPayment, getCompanyBalance
  - BookingController with prebookHotel, confirmBooking, getCompanyBalance endpoints
  - PrebookRequest and ConfirmBookingRequest form request classes
  - Routes: POST prebook_hotel, POST confirm_booking, GET balance
  - 7 new booking error codes in DotwAIResponse
  - 3 new WhatsApp message formatters in MessageBuilderService

affects:
  - 19-02-payment (Plan 02 uses confirmAfterPayment and DotwAIBooking model directly)
  - 19-03-voucher (Plan 03 uses DotwAIBooking.status=confirmed and voucher_sent_at)
  - 20-accounting (Invoice/task creation triggered by confirmed bookings)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "BookingService orchestrates DOTW via new DotwService($companyId) instantiation (no DI)"
    - "Pessimistic credit locking: DB::transaction + lockForUpdate on Credit::where(client_id)"
    - "Idempotency gates on confirm methods: check confirmation_no before any action"
    - "APR branch: is_apr=true uses saveBooking+bookItinerary instead of confirmBooking"
    - "Re-block on confirmAfterPayment: always get fresh allocation_details before DOTW call"
    - "MSP enforcement: if B2C and displayFare < minimumSellingPrice, use MSP as display price"
    - "Track determination: b2b=credit line, b2b_gateway=gateway payment, b2c=customer track"

key-files:
  created:
    - app/Modules/DotwAI/Models/DotwAIBooking.php
    - app/Modules/DotwAI/Database/Migrations/2026_03_24_100000_create_dotwai_bookings_table.php
    - app/Modules/DotwAI/Services/CreditService.php
    - app/Modules/DotwAI/Services/BookingService.php
    - app/Modules/DotwAI/Http/Controllers/BookingController.php
    - app/Modules/DotwAI/Http/Requests/PrebookRequest.php
    - app/Modules/DotwAI/Http/Requests/ConfirmBookingRequest.php
  modified:
    - app/Modules/DotwAI/Services/DotwAIResponse.php
    - app/Modules/DotwAI/Services/MessageBuilderService.php
    - app/Modules/DotwAI/Config/dotwai.php
    - app/Modules/DotwAI/Routes/api.php

key-decisions:
  - "DotwService::sanitizePassengerName is private -- BookingService has its own sanitizePassengerName helper with identical logic for module self-containment"
  - "Search cache contains only hotel summaries (no room detail) -- prebook always re-calls getRooms with blocking=true regardless of option_number or hotel_id input"
  - "CreditService::getClientIdForCompany resolves via Agent->clients() relationship (not Company->clients directly) since Company model has no clients() method"
  - "Config additions: prebook_expiry_minutes (30), payment_link_expiry_hours (48), default_payment_gateway (myfatoorah)"

patterns-established:
  - "BookingService::prebook: resolve hotel → block rate → enforce MSP → persist DotwAIBooking"
  - "confirmWithCredit: idempotency check → credit lock → DOTW call → refund on failure"
  - "confirmAfterPayment: idempotency check → re-block → DOTW call → needs_refund on failure"
  - "All controllers: try/catch returning DOTW_API_ERROR on exception (Phase 18 pattern maintained)"

requirements-completed: [B2B-01, B2B-03, B2B-04, B2B-05, B2B-06, B2C-03, B2C-05]

# Metrics
duration: 9min
completed: 2026-03-24
---

# Phase 19 Plan 01: B2B/B2C Booking Infrastructure Summary

**DotwAIBooking model + migration, CreditService with lockForUpdate, BookingService orchestrating 3-track prebook/confirm flow, BookingController with 3 REST endpoints, and MSP enforcement for B2C**

## Performance

- **Duration:** 9 min
- **Started:** 2026-03-24T14:37:34Z
- **Completed:** 2026-03-24T14:46:38Z
- **Tasks:** 2
- **Files modified:** 11

## Accomplishments

- Full booking lifecycle model (DotwAIBooking) with 7 status values, 40+ fields, and isExpired/canConfirm helpers
- CreditService with pessimistic lockForUpdate protecting against race conditions on concurrent B2B confirmations (B2B-06)
- BookingService handling all 3 tracks: B2B credit (immediate confirm), B2B gateway (await payment + re-block), B2C (MSP-enforced pricing + await payment + re-block)
- 3 REST endpoints: POST prebook_hotel, POST confirm_booking, GET balance -- all returning bilingual WhatsApp messages
- 7 new booking error codes with bilingual Arabic/English defaults
- 3 new MessageBuilderService formatters for prebook, confirmation, and balance

## Task Commits

1. **Task 1: DotwAIBooking model, migration, CreditService, config updates, new error codes** - `e645f8ba` (feat)
2. **Task 2: BookingService, BookingController, form requests, and route wiring** - `5748df17` (feat)

## Files Created/Modified

- `app/Modules/DotwAI/Models/DotwAIBooking.php` - Booking lifecycle model with status constants, casts, helper methods
- `app/Modules/DotwAI/Database/Migrations/2026_03_24_100000_create_dotwai_bookings_table.php` - Migration with composite indexes, no FK constraints
- `app/Modules/DotwAI/Services/CreditService.php` - checkAndDeductCredit (lockForUpdate), refundCredit, getBalance, getClientIdForCompany
- `app/Modules/DotwAI/Services/BookingService.php` - prebook, confirmWithCredit, confirmAfterPayment, getCompanyBalance
- `app/Modules/DotwAI/Http/Controllers/BookingController.php` - prebookHotel, confirmBooking, getCompanyBalance endpoints
- `app/Modules/DotwAI/Http/Requests/PrebookRequest.php` - option_number|hotel_id, dates, occupancy validation
- `app/Modules/DotwAI/Http/Requests/ConfirmBookingRequest.php` - prebook_key, passengers, email validation
- `app/Modules/DotwAI/Services/DotwAIResponse.php` - Added 7 booking error codes with bilingual defaults
- `app/Modules/DotwAI/Services/MessageBuilderService.php` - Added formatPrebookConfirmation, formatBookingConfirmation, formatBalanceSummary
- `app/Modules/DotwAI/Config/dotwai.php` - Added prebook_expiry_minutes, payment_link_expiry_hours, default_payment_gateway
- `app/Modules/DotwAI/Routes/api.php` - Added BookingController import and 3 booking routes

## Decisions Made

- DotwService::sanitizePassengerName is private -- added private helper with identical logic in BookingService (module self-containment, no modification of existing service)
- Search cache stores hotel summaries only (no room data) -- prebook always calls getRooms(blocking=true) regardless of input mode; option_number just resolves the hotel_id
- CreditService::getClientIdForCompany uses Agent->branch->company_id chain since Company model has no direct clients() relationship
- Track determination: context->isB2B() = 'b2b' (credit line), context->isB2C() = 'b2c', anything else = 'b2b_gateway'

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] sanitizePassengerName is private in DotwService**
- **Found during:** Task 2 (BookingService::buildConfirmParams)
- **Issue:** Plan specified `DotwService::sanitizePassengerName($name)` as a static call, but the method is declared `private function` in DotwService -- would cause a fatal error
- **Fix:** Added private `sanitizePassengerName()` helper directly to BookingService with identical logic
- **Files modified:** app/Modules/DotwAI/Services/BookingService.php
- **Verification:** PHP syntax check passes
- **Committed in:** `5748df17` (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 - bug fix)
**Impact on plan:** Fix was essential for correctness. No scope creep.

## Issues Encountered

None - plan executed cleanly once the sanitizePassengerName visibility issue was resolved.

## User Setup Required

None - no external service configuration required for this plan. The migration will need to be run (`php artisan migrate`) when deploying.

## Next Phase Readiness

- Plan 02 (payment gateway integration) can now use `DotwAIBooking` model directly and call `BookingService::confirmAfterPayment()`
- Plan 03 (voucher delivery) can query `DotwAIBooking::where('status', 'confirmed')->whereNull('voucher_sent_at')` for pending vouchers
- `CreditService` is ready for Plan 20 accounting integration
- Migration must be run before testing: `php artisan migrate`

---
*Phase: 19-b2b-b2c-booking*
*Completed: 2026-03-24*
