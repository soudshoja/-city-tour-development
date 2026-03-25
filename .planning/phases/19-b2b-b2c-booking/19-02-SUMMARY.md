---
phase: 19-b2b-b2c-booking
plan: 02
subsystem: api
tags: [dotw, payment, myfatoorah, queue, webhook, b2b, b2c, whatsapp]

# Dependency graph
requires:
  - phase: 19-b2b-b2c-booking
    plan: 01
    provides: DotwAIBooking model, BookingService::confirmAfterPayment, CreditService, DotwAIResponse error codes

provides:
  - PaymentBridgeService: direct MyFatoorah ExecutePayment API with module-owned CallBackUrl and UserDefinedField tagging
  - PaymentLinkRequest: validates telephone + prebook_key
  - BookingController::paymentLink endpoint with idempotency guard
  - PaymentCallbackController: handles MyFatoorah webhook, always returns HTTP 200
  - ConfirmBookingAfterPaymentJob: queued re-block + confirm + task/invoice + WhatsApp (ShouldQueue, 4 tries, [30,120,300]s backoff)
  - MessageBuilderService::formatPaymentLink (bilingual AR/EN payment link message)
  - MessageBuilderService::formatBookingFailed (bilingual AR/EN failure + refund message)
  - PAYMENT_FAILED error code in DotwAIResponse
  - payment_callback route outside dotwai.resolve middleware group

affects:
  - 19-03-voucher (ConfirmBookingAfterPaymentJob sets voucher_sent_at after WhatsApp confirmation)
  - 20-accounting (Task + Invoice created by ConfirmBookingAfterPaymentJob)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Direct MyFatoorah ExecutePayment API call (no MyFatoorah::createCharge) for module-owned CallBackUrl"
    - "UserDefinedField JSON tagging: {dotwai_booking_id, prebook_key, process='dotwai_hotel'}"
    - "ShouldQueue with tries=4 and backoff=[30,120,300] for DOTW 25s response time"
    - "Idempotency gate: check confirmation_no before any job action"
    - "Payment callback always returns HTTP 200 (gateway retry prevention)"
    - "PaymentMethod queried with withoutGlobalScopes() to bypass Auth-based company scope"

key-files:
  created:
    - app/Modules/DotwAI/Services/PaymentBridgeService.php
    - app/Modules/DotwAI/Http/Requests/PaymentLinkRequest.php
    - app/Modules/DotwAI/Http/Controllers/PaymentCallbackController.php
    - app/Modules/DotwAI/Jobs/ConfirmBookingAfterPaymentJob.php
  modified:
    - app/Modules/DotwAI/Http/Controllers/BookingController.php
    - app/Modules/DotwAI/Services/MessageBuilderService.php
    - app/Modules/DotwAI/Services/DotwAIResponse.php
    - app/Modules/DotwAI/Routes/api.php

key-decisions:
  - "Direct MyFatoorah API call (not createCharge) gives full control over CallBackUrl and UserDefinedField without modifying MyFatoorah.php"
  - "PaymentMethod queried with withoutGlobalScopes() because it has a booted() Auth-based global scope that would return empty in a queue/API context"
  - "ConfirmBookingAfterPaymentJob::failed() marks booking as 'failed' only -- no auto-refund on final failure, admin handles manually"
  - "Task::create uses is_n8n_booking=true field for identification; supplier_id not set (DOTW not in suppliers table as a standard entry)"
  - "Invoice status is 'paid' (not 'unpaid') since payment was already received via gateway before invoice creation"

# Metrics
duration: 5min
completed: 2026-03-24
---

# Phase 19 Plan 02: Payment Pipeline Summary

**PaymentBridgeService (direct MyFatoorah API), PaymentCallbackController (always-200 webhook handler), ConfirmBookingAfterPaymentJob (queued re-block + confirm + task/invoice + WhatsApp), and payment_link + payment_callback routes**

## Performance

- **Duration:** 5 min
- **Started:** 2026-03-24T14:50:31Z
- **Completed:** 2026-03-24T14:55:49Z
- **Tasks:** 2
- **Files modified:** 8

## Accomplishments

- PaymentBridgeService calls MyFatoorah ExecutePayment API directly (bypassing the existing createCharge wrapper) so the module fully controls the CallBackUrl (pointing to `/api/dotwai/payment_callback`) and embeds a `UserDefinedField` with `{prebook_key, dotwai_booking_id, process='dotwai_hotel'}` for callback identification
- Creates a Payment record before the API call for accounting traceability (Pitfall 6 addressed)
- PaymentLinkRequest validates `telephone` + `prebook_key` with idempotency: if booking already has a `payment_link` and `payment_status='pending'`, the existing URL is returned without a new API call
- BookingController::paymentLink endpoint guards: B2B credit track blocked (does not need payment link), expired and already-confirmed bookings rejected
- PaymentCallbackController handles MyFatoorah redirect with `?paymentId=` param. Verifies via `getPaymentStatus`, extracts prebook_key from UserDefinedField, and dispatches `ConfirmBookingAfterPaymentJob`. Always returns HTTP 200 to prevent gateway retries
- ConfirmBookingAfterPaymentJob implements `ShouldQueue` with 4 tries and exponential backoff [30s, 120s, 300s]. Has an idempotency gate (checks `confirmation_no`) before any action. On success: creates Task + Invoice, sends WhatsApp confirmation, sets `voucher_sent_at`. On failure: sets `payment_status=refund_pending`, sends WhatsApp notification
- Added `formatPaymentLink` and `formatBookingFailed` bilingual AR/EN formatters to MessageBuilderService
- Added `PAYMENT_FAILED` error code with bilingual default to DotwAIResponse
- Payment callback route registered outside `dotwai.resolve` middleware group (gateway has no phone context)

## Task Commits

1. **Task 1: PaymentBridgeService, PaymentLinkRequest, payment_link endpoint** - `e8571a52` (feat)
2. **Task 2: PaymentCallbackController, ConfirmBookingAfterPaymentJob, callback route** - `d3d009c0` (feat)

## Files Created/Modified

- `app/Modules/DotwAI/Services/PaymentBridgeService.php` - Direct MyFatoorah ExecutePayment API, Payment record creation, booking update
- `app/Modules/DotwAI/Http/Requests/PaymentLinkRequest.php` - Validates telephone + prebook_key
- `app/Modules/DotwAI/Http/Controllers/BookingController.php` - Added paymentLink endpoint + PaymentBridgeService DI
- `app/Modules/DotwAI/Http/Controllers/PaymentCallbackController.php` - Webhook handler, always HTTP 200, dispatches job
- `app/Modules/DotwAI/Jobs/ConfirmBookingAfterPaymentJob.php` - Queued re-block + confirm + Task/Invoice + WhatsApp
- `app/Modules/DotwAI/Services/MessageBuilderService.php` - Added formatPaymentLink + formatBookingFailed
- `app/Modules/DotwAI/Services/DotwAIResponse.php` - Added PAYMENT_FAILED constant + bilingual default
- `app/Modules/DotwAI/Routes/api.php` - Added payment_link (inside middleware) + payment_callback (outside middleware)

## Decisions Made

- **Direct MyFatoorah API call**: The existing `MyFatoorah::createCharge()` hardcodes `route('payments.callback')` as CallBackUrl. Direct API call avoids modifying existing code while giving the module its own callback URL (zero-modification policy)
- **withoutGlobalScopes() on PaymentMethod**: PaymentMethod has a `booted()` method adding a global scope on `company_id` tied to Auth::user(). In queue/API context, Auth is not set, so the scope would filter to nothing. `withoutGlobalScopes()` bypasses this safely
- **Job::failed() does not auto-refund**: After all retries are exhausted, the booking is marked `failed` but no automatic refund is triggered. Refund requires admin review to avoid false positives from transient network errors
- **Invoice status = 'paid'**: Since payment was collected by the gateway before this job runs, the Invoice is created with `status='paid'` (InvoiceStatus enum). Setting it to `unpaid` would create an accounting discrepancy
- **Task is_n8n_booking=true**: Flags DOTW AI bookings for identification in the task list. supplier_id is not set because DOTW is not a standard supplier entry in the context of this module

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Security/Correctness] PaymentMethod global scope bypass**
- **Found during:** Task 1 (PaymentBridgeService implementation)
- **Issue:** PaymentMethod model has `booted()` with a global scope filtering by `company_id` using `Auth::user()`. In a queue context or API context without authenticated user, this would return zero results, silently failing to find payment methods
- **Fix:** Added `withoutGlobalScopes()` to the PaymentMethod query in PaymentBridgeService with a fallback query by `whereNotNull('myfatoorah_id')`
- **Files modified:** app/Modules/DotwAI/Services/PaymentBridgeService.php
- **Committed in:** `e8571a52` (Task 1 commit)

---

**2. [Rule 2 - Correctness] Invoice status must be 'paid' not 'unpaid'**
- **Found during:** Task 2 (ConfirmBookingAfterPaymentJob invoice creation)
- **Issue:** Plan specified `status => 'pending'` for Invoice, but InvoiceStatus enum does not have a 'pending' value. Valid values: paid, unpaid, partial, paid_by_refund, refunded, partial_refund. Since payment was already received via gateway, 'paid' is the correct status
- **Fix:** Used `status => 'paid'` (InvoiceStatus::PAID enum value)
- **Files modified:** app/Modules/DotwAI/Jobs/ConfirmBookingAfterPaymentJob.php
- **Committed in:** `d3d009c0` (Task 2 commit)

---

**Total deviations:** 2 auto-fixed (Rule 2 - correctness/security)
**Impact on plan:** Both fixes were essential for correct operation. No scope creep.

## Verification Results

- All 8 PHP files pass `php -l` syntax check: PASS
- PaymentBridgeService calls MyFatoorah ExecutePayment directly with module-owned CallBackUrl: PASS
- PaymentCallbackController returns 200 to gateway in all cases (including exceptions): PASS
- ConfirmBookingAfterPaymentJob has idempotency gate (checks confirmation_no): PASS
- ConfirmBookingAfterPaymentJob re-blocks rate before confirming via BookingService::confirmAfterPayment: PASS
- Job creates Task + Invoice after successful confirmation (B2C-04): PASS
- Job sends WhatsApp voucher after confirmation (B2B-07): PASS
- If re-block fails, refund_pending is set and customer notified (B2C-02): PASS
- Payment callback route is OUTSIDE the dotwai.resolve middleware group: PASS
- Job has retry logic: 4 tries, backoff [30, 120, 300] (Pitfall 4): PASS

## Next Phase Readiness

- Plan 03 (voucher delivery) can query `DotwAIBooking::where('status', 'confirmed')->whereNull('voucher_sent_at')` for any pending vouchers (ConfirmBookingAfterPaymentJob sets voucher_sent_at, but Plan 03 can enrich with full voucher PDF)
- Accounting integration (Plan 20) has both Task + Invoice created by ConfirmBookingAfterPaymentJob with `is_n8n_booking=true` for identification
- Payment refund flow (Plan 03 or admin) looks for `payment_status='refund_pending'` bookings

---
*Phase: 19-b2b-b2c-booking*
*Completed: 2026-03-24*

## Self-Check: PASSED

All 9 files found. Both commits (e8571a52, d3d009c0) confirmed present in git log.
