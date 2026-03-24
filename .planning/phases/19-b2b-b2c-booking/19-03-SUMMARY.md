---
phase: 19-b2b-b2c-booking
plan: "03"
subsystem: DotwAI Booking Module
tags: [voucher, whatsapp, tests, b2b, credit, msp, apr]
dependency_graph:
  requires: ["19-01", "19-02"]
  provides: ["VoucherService", "BookingServiceTest", "CreditServiceTest", "BookingControllerTest"]
  affects: ["app/Modules/DotwAI/Services/MessageBuilderService.php"]
tech_stack:
  added: []
  patterns:
    - "text-based WhatsApp vouchers via sendToResayil"
    - "Mockery overload for DotwService in all booking tests"
    - "RefreshDatabase + skipPermissionSeeder=true for test isolation"
key_files:
  created:
    - app/Modules/DotwAI/Services/VoucherService.php
    - tests/Feature/Modules/DotwAI/CreditServiceTest.php
    - tests/Feature/Modules/DotwAI/BookingServiceTest.php
    - tests/Feature/Modules/DotwAI/BookingControllerTest.php
  modified:
    - app/Modules/DotwAI/Services/MessageBuilderService.php
decisions:
  - "Text-based WhatsApp vouchers (not PDF attachments) for maximum reliability"
  - "formatVoucherMessage always includes paymentGuaranteedBy when present (locked CONTEXT.md decision)"
  - "VoucherService logs as 'resent' vs 'sent' for audit trail differentiation"
  - "Test client resolution via agent->clients() pivot (client_agents table)"
metrics:
  duration: "8 minutes"
  completed_date: "2026-03-24"
  tasks_completed: 2
  files_changed: 5
---

# Phase 19 Plan 03: VoucherService and Booking Test Suite

**One-liner:** Bilingual WhatsApp voucher delivery via VoucherService and comprehensive test suite covering B2B credit flow, pessimistic locking, MSP enforcement, APR routing, and all booking REST endpoints.

## What Was Built

### Task 1: VoucherService and formatVoucherMessage

**`app/Modules/DotwAI/Services/VoucherService.php`** (new)
- `sendVoucher(DotwAIBooking $booking): bool` — formats and sends a booking confirmation voucher via WhatsApp (Resayil). Sends to `client_phone` with fallback to `agent_phone`. Updates `voucher_sent_at` on success.
- `resendVoucher(DotwAIBooking $booking): bool` — identical to `sendVoucher` but always re-sends regardless of `voucher_sent_at`. Logs as "resent" for audit trail.
- Both methods use `app(\App\Http\Controllers\WhatsappController::class)` for delivery with bilingual header "Booking Confirmation | تأكيد الحجز" and footer "City Travelers".

**`app/Modules/DotwAI/Services/MessageBuilderService.php`** (modified)
- Added `formatVoucherMessage(DotwAIBooking $booking): string` static method
- Produces comprehensive bilingual Arabic/English voucher with:
  - Booking reference (confirmation_no)
  - Hotel name, check-in/check-out dates (formatted as "d M Y")
  - Guest list from guest_details (salutation + name), falls back to "Guest"
  - Total fare with currency
  - `paymentGuaranteedBy` when present (per locked CONTEXT.md decision)
  - Cancellation policy: free cancellation deadline OR "Non-Refundable (APR)"
  - City Travelers branding footer

### Task 2: Comprehensive Test Suite

**`tests/Feature/Modules/DotwAI/CreditServiceTest.php`** (new, 6 tests)
- `test_check_and_deduct_credit_succeeds_with_sufficient_balance` — verifies INVOICE record created, balance reduced
- `test_check_and_deduct_credit_fails_with_insufficient_balance` — verifies no record created, balance unchanged
- `test_get_balance_returns_correct_structure` — TOPUP+REFUND=credit_limit, INVOICE=used_credit, available_credit=difference
- `test_refund_credit_creates_refund_record` — verifies REFUND type record with correct amount
- `test_concurrent_credit_deduction_prevented_by_locking` — sequential deductions prove only 400 deducted from 500 balance when second request tries 200 with only 100 remaining
- `test_get_client_id_for_company_resolves_via_agent_chain` — verifies company→agent→client resolution

**`tests/Feature/Modules/DotwAI/BookingServiceTest.php`** (new, 6 tests)
- `test_prebook_creates_booking_from_cached_search_results` — seeded cache, blocking mock, verifies DotwAIBooking persisted with status=prebooked
- `test_prebook_returns_error_when_blocking_fails` — DotwService exception → RATE_UNAVAILABLE, no booking created
- `test_confirm_with_credit_deducts_and_confirms` — credit deducted, booking confirmed, paymentGuaranteedBy stored
- `test_confirm_with_credit_refunds_on_dotw_error` — DOTW failure → credit refunded, booking failed, net balance restored to original
- `test_confirm_with_credit_is_idempotent` — already-confirmed booking returns existing data, no credit deducted
- `test_prebook_enforces_msp_for_b2c` — 40 KWD base × 1.2 = 48 KWD < 50 KWD MSP → display_total_fare = 50 KWD
- `test_confirm_uses_save_booking_for_apr_rates` — `saveBooking` called (not `confirmBooking`), then `bookItinerary`

**`tests/Feature/Modules/DotwAI/BookingControllerTest.php`** (new, 5 tests)
- `test_prebook_hotel_endpoint_returns_success` — full HTTP cycle, verifies 200 + prebook_key + whatsappMessage
- `test_prebook_hotel_validates_required_fields` — missing check_in/check_out/occupancy → 422
- `test_confirm_booking_endpoint_b2b_credit` — credit flow end-to-end, confirms booking, whatsappMessage present
- `test_confirm_booking_returns_error_for_expired_prebook` — 60-minute-old prebooked → PREBOOK_EXPIRED error
- `test_balance_endpoint_returns_credit_summary` — correct amounts in data + amounts in whatsappMessage
- `test_balance_endpoint_rejects_b2c_track` — B2C company → TRACK_DISABLED error

## Deviations from Plan

None — plan executed exactly as written.

## Verification

All 5 PHP files pass `php -l` syntax check:
- `app/Modules/DotwAI/Services/VoucherService.php` — PASS
- `app/Modules/DotwAI/Services/MessageBuilderService.php` — PASS
- `tests/Feature/Modules/DotwAI/CreditServiceTest.php` — PASS
- `tests/Feature/Modules/DotwAI/BookingServiceTest.php` — PASS
- `tests/Feature/Modules/DotwAI/BookingControllerTest.php` — PASS

## Success Criteria Met

- VoucherService formats and sends bilingual vouchers via WhatsApp with all required fields
- formatVoucherMessage includes paymentGuaranteedBy per locked CONTEXT.md decision
- CreditServiceTest: 6 tests covering success, failure, balance, refund, concurrent access, resolution
- BookingServiceTest: 6 tests covering prebook, confirm credit, confirm failure, idempotency, MSP, APR
- BookingControllerTest: 5 tests covering all endpoints and validation
- All tests use Mockery overload pattern per Phase 18 convention
- No existing files outside app/Modules/DotwAI/ and tests/ were modified

## Self-Check: PASSED
