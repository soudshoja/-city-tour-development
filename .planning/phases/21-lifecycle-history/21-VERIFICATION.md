---
phase: 21-lifecycle-history
verified: 2026-03-25T00:00:00Z
status: passed
score: 9/10 must-haves verified
re_verification: false
gaps:
  - truth: "DOTW voucher/PDF retrieved from API if supported, otherwise generated locally (HIST-04)"
    status: failed
    reason: "VoucherService.sendVoucher and resendVoucher only send a WhatsApp text message formatted by MessageBuilderService. There is no DOTW API call to retrieve a PDF voucher, and no local PDF generation fallback. HIST-04 was claimed as 'already from Phase 19' but Phase 19 did not deliver it either — the method exists but the PDF retrieval path is absent."
    artifacts:
      - path: "app/Modules/DotwAI/Services/VoucherService.php"
        issue: "sendVoucher and resendVoucher both call MessageBuilderService::formatVoucherMessage + WhatsappController::sendToResayil only. No PDF fetch from DOTW API, no local generation fallback."
    missing:
      - "DOTW getBookingVoucher API call (or equivalent) to retrieve PDF voucher when API supports it"
      - "Local PDF generation fallback when DOTW API does not return a PDF"
human_verification:
  - test: "Trigger dotwai:process-deadlines with a confirmed booking whose cancellation_deadline is within 3 days"
    expected: "SendReminderJob queues, WhatsApp reminder message delivered with hotel name, deadline date, days remaining, and penalty"
    why_human: "Requires live queue worker, live WhatsApp gateway (Resayil), and a seeded confirmed booking — cannot verify delivery programmatically"
  - test: "Trigger dotwai:process-deadlines after a confirmed booking deadline has passed"
    expected: "AutoInvoiceDeadlineJob runs, invoice + journal entry created in DB, voucher WhatsApp sent, auto_invoiced_at set"
    why_human: "Requires live queue worker, DB with accounting data, and live WhatsApp gateway"
  - test: "Confirm an APR (is_apr=true) booking through confirmWithCredit or confirmAfterPayment"
    expected: "auto_invoiced_at is set, Invoice record created, no reminder cycle initiated"
    why_human: "Requires live booking flow with a real DOTW APR rate"
  - test: "Call POST /api/dotwai/resend_voucher with a confirmed booking prebook_key"
    expected: "WhatsApp message sent, response contains booking_ref and whatsappMessage confirmation"
    why_human: "Requires live WhatsApp gateway and auth token"
---

# Phase 21: Lifecycle History Verification Report

**Phase Goal:** The system automatically manages booking deadlines with WhatsApp reminders, auto-invoices after deadlines pass, and agents/customers can check booking status and resend vouchers at any time.

**Verified:** 2026-03-25
**Status:** gaps_found
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Daily scheduler dispatches reminder jobs for bookings 3, 2, 1 day before cancellation deadline | VERIFIED | `Kernel.php:52` registers `dotwai:process-deadlines` at `dailyAt('03:00')` with `withoutOverlapping()->runInBackground()`. `ProcessDeadlinesCommand` calls `LifecycleService::findBookingsDueForReminder()` and dispatches `SendReminderJob::dispatch($booking->id)` per booking. |
| 2 | Reminder jobs send WhatsApp messages and mark reminder_sent_at to prevent duplicates | VERIFIED | `SendReminderJob` checks idempotency gate (`reminder_sent_at !== null`), calls `WhatsappController::sendToResayil`, then calls `LifecycleService::markReminderSent`. On failure, `reminder_sent_at` stays NULL for retry. |
| 3 | After cancellation deadline passes, scheduler dispatches auto-invoice job | VERIFIED | `ProcessDeadlinesCommand` calls `LifecycleService::findBookingsWithPassedDeadline()` and dispatches `AutoInvoiceDeadlineJob::dispatch($booking->id)`. |
| 4 | Auto-invoice job creates Invoice + JournalEntry and sends voucher via WhatsApp | VERIFIED | `AutoInvoiceDeadlineJob::handle` wraps `AccountingService::createAutoInvoiceForDeadline($booking)` + `VoucherService::sendVoucher($booking)` in `DB::transaction`, with clock skew guard, and sets `auto_invoiced_at = now()`. |
| 5 | APR bookings auto-invoice immediately on confirmation with no reminder cycle | VERIFIED | Both `BookingService::confirmWithCredit` (line 295) and `confirmAfterPayment` (line 411) check `$booking->is_apr` and call `$this->invoiceAPRBooking($booking)` which calls `AccountingService::createAutoInvoiceForDeadline` inside `DB::transaction`. |
| 6 | booking_status endpoint returns cancellation policy, deadline, penalty, current status | VERIFIED | `BookingController::bookingStatus` queries `DotwAIBooking` by phone + prebook_key/booking_code, scoped to company_id, returns `cancellation_deadline`, `is_refundable`, `current_penalty`, `status`, and `whatsappMessage`. |
| 7 | booking_history endpoint lists bookings with status/date filters and pagination | VERIFIED | `BookingController::bookingHistory` with `BookingHistoryRequest` validates status/from_date/to_date/page/per_page, queries with filters, paginates with `orderByDesc('created_at')`, returns total + whatsappMessage. |
| 8 | resend_voucher endpoint re-sends booking confirmation via WhatsApp | VERIFIED | `BookingController::resendVoucher` finds booking by phone + prebook_key, validates `status === STATUS_CONFIRMED`, calls `VoucherService::resendVoucher($booking)`. |
| 9 | Laravel dispatches async events to webhook URL for n8n consumption | VERIFIED | `WebhookEventService::dispatchEvent` checks `dotwai.webhook_url` and event enabled in `dotwai.webhook_events`, then dispatches `WebhookDispatchJob` (4 tries, 30s/2m/5m backoff, 10s timeout). `booking_confirmed` fires from both confirm methods. |
| 10 | DOTW voucher/PDF retrieved from API if supported, otherwise generated locally (HIST-04) | FAILED | `VoucherService::sendVoucher` and `resendVoucher` send only a WhatsApp text message via `MessageBuilderService::formatVoucherMessage`. No DOTW PDF API call exists, no local PDF generation fallback exists. The plan claimed this was inherited from Phase 19 but Phase 19 did not implement it. |

**Score: 9/10 truths verified**

---

## Required Artifacts

### Plan 21-01 Artifacts

| Artifact | Min Lines | Actual Lines | Status | Details |
|----------|-----------|--------------|--------|---------|
| `app/Modules/DotwAI/Services/LifecycleService.php` | — | 90 | VERIFIED | All three methods present: `findBookingsDueForReminder`, `findBookingsWithPassedDeadline`, `markReminderSent` with substantive queries |
| `app/Modules/DotwAI/Commands/ProcessDeadlinesCommand.php` | — | 111 | VERIFIED | `handle(LifecycleService $service)` dispatches both reminder and invoice jobs with logging |
| `app/Modules/DotwAI/Jobs/SendReminderJob.php` | 40 | 134 | VERIFIED | Implements ShouldQueue, `$tries=3`, `$backoff=[30,120]`, idempotency gate, WhatsApp send, `failed()` hook |
| `app/Modules/DotwAI/Jobs/AutoInvoiceDeadlineJob.php` | 50 | 142 | VERIFIED | Implements ShouldQueue, `$tries=3`, `$backoff=[60,300]`, clock skew guard, DB::transaction, `failed()` hook |
| `app/Modules/DotwAI/Database/Migrations/2026_03_25_000000_add_lifecycle_fields_to_dotwai_bookings_table.php` | — | 41 | VERIFIED | Adds `reminder_sent_at` and `auto_invoiced_at` nullable timestamp columns with reversible `down()` |

### Plan 21-02 Artifacts

| Artifact | Min Lines | Actual Lines | Status | Details |
|----------|-----------|--------------|--------|---------|
| `app/Modules/DotwAI/Services/BookingService.php` | — | 835 | VERIFIED | `invoiceAPRBooking` private method, APR check in both `confirmWithCredit` and `confirmAfterPayment`, webhook dispatch in both |
| `app/Modules/DotwAI/Services/WebhookEventService.php` | — | 88 | VERIFIED | `dispatchEvent`, `formatEventPayload` static methods, config-gated dispatch via `WebhookDispatchJob::dispatch` |
| `app/Modules/DotwAI/Jobs/WebhookDispatchJob.php` | 50 | 102 | VERIFIED | `$tries=4`, `$backoff=[30,120,300]`, `$timeout=10`, HTTP::post with retry throw, `failed()` hook |
| `app/Modules/DotwAI/Http/Controllers/BookingController.php` | — | 639 | VERIFIED | `bookingStatus`, `bookingHistory`, `resendVoucher` methods all substantive with company_id scoping |
| `app/Modules/DotwAI/Http/Requests/BookingStatusRequest.php` | — | 46 | VERIFIED | phone regex, prebook_key/booking_code nullable |
| `app/Modules/DotwAI/Http/Requests/BookingHistoryRequest.php` | — | 38 | VERIFIED | phone, status enum, from_date/to_date, pagination |
| `app/Modules/DotwAI/Http/Requests/ResendVoucherRequest.php` | — | 34 | VERIFIED | phone regex, prebook_key required |

---

## Key Link Verification

### Plan 21-01 Key Links

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `app/Console/Kernel.php` | `dotwai:process-deadlines` | `schedule()->command()` | WIRED | Line 51-54: `dailyAt('03:00')->withoutOverlapping()->runInBackground()` |
| `ProcessDeadlinesCommand.php` | `SendReminderJob` | `SendReminderJob::dispatch()` | WIRED | Line 72: `SendReminderJob::dispatch($booking->id)` |
| `ProcessDeadlinesCommand.php` | `AutoInvoiceDeadlineJob` | `AutoInvoiceDeadlineJob::dispatch()` | WIRED | Line 90: `AutoInvoiceDeadlineJob::dispatch($booking->id)` |
| `SendReminderJob.php` | `DotwAIBooking.reminder_sent_at` | `update reminder_sent_at` | WIRED | Line 100+: `markReminderSent` called after WhatsApp send |
| `DotwAIServiceProvider.php` | `ProcessDeadlinesCommand` | command registration | WIRED | Line 50: `ProcessDeadlinesCommand::class` in `boot()` commands array |

### Plan 21-02 Key Links

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `BookingService.php` | `AccountingService::createAutoInvoiceForDeadline` | APR auto-invoice on confirm | WIRED | Lines 295-296, 411-412: `is_apr` check → `invoiceAPRBooking` → `createAutoInvoiceForDeadline` |
| `BookingService.php` | `WebhookEventService::dispatchEvent` | dispatch after confirm | WIRED | Lines 304, 420: `WebhookEventService::dispatchEvent('booking_confirmed', [...])` |
| `WebhookEventService.php` | `WebhookDispatchJob` | `WebhookDispatchJob::dispatch` | WIRED | Line 52: `WebhookDispatchJob::dispatch($payload)` |
| `Routes/api.php` | `BookingController` | route registration | WIRED | Lines 62-64: `booking_status`, `booking_history`, `resend_voucher` registered inside middleware group |

---

## Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|-------------|--------|----------|
| LIFE-01 | 21-01 | Cancellation deadline date stored from DOTW getRooms response | SATISFIED | `cancellation_deadline` field exists on `DotwAIBooking`, populated during prebook (Phase 19 pre-existing) |
| LIFE-02 | 21-01 | Auto-reminders via WhatsApp at 3/2/1 days before deadline | SATISFIED | `LifecycleService::findBookingsDueForReminder` queries 0-3 day window; `SendReminderJob` sends and tracks via `reminder_sent_at` |
| LIFE-03 | 21-01 | After deadline passes: auto-invoice + voucher + accounting | SATISFIED | `AutoInvoiceDeadlineJob` wraps `createAutoInvoiceForDeadline` + `sendVoucher` in DB::transaction |
| LIFE-04 | 21-02 | APR bookings auto-invoice on confirmation | SATISFIED | `BookingService::confirmWithCredit` and `confirmAfterPayment` both call `invoiceAPRBooking` when `is_apr=true` |
| LIFE-05 | 21-01 | Scheduler checks deadlines daily | SATISFIED | `Kernel.php` registers `dotwai:process-deadlines` at 03:00 daily |
| HIST-01 | 21-02 | booking_status returns deadline, cancellation policy, penalty | SATISFIED | `BookingController::bookingStatus` returns all required fields with WhatsApp formatting |
| HIST-02 | 21-02 | booking_history with status/date filters and pagination | SATISFIED | `BookingController::bookingHistory` with `BookingHistoryRequest` validates and applies all filters |
| HIST-03 | 21-02 | resend_voucher re-sends confirmation via WhatsApp | SATISFIED | `BookingController::resendVoucher` calls `VoucherService::resendVoucher` after status validation |
| HIST-04 | 21-02 | DOTW voucher/PDF retrieved from API, otherwise generated locally | BLOCKED | `VoucherService` sends only a WhatsApp text message. No DOTW PDF retrieval call, no local PDF generation. Claimed as pre-existing from Phase 19 but was never implemented. |
| EVNT-01 | 21-02 | Laravel pushes async events to automation webhook | SATISFIED | `WebhookEventService` + `WebhookDispatchJob` dispatch `booking_confirmed` (and config-ready for `payment_completed`, `reminder_due`, `deadline_passed`) |

---

## Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| No anti-patterns found | — | — | — | All key files are free of TODOs, stubs, placeholder returns, or empty handlers |

---

## Git Commits (All Verified)

| Commit | Description |
|--------|-------------|
| `a6c264cc` | feat(21-01): add lifecycle tracking fields, LifecycleService, and reminder formatters |
| `e463b6fd` | feat(21-01): create SendReminderJob and AutoInvoiceDeadlineJob queue jobs |
| `fce9cfb3` | feat(21-01): create ProcessDeadlinesCommand and register in Kernel scheduler |
| `bd512927` | feat(21-02): integrate APR auto-invoice and WebhookEventService into confirmation flows |
| `6c4a5f7d` | feat(21-02): add WebhookDispatchJob, three REST endpoints and message formatters |

All 5 commits exist in git history and match the tasks described in SUMMARY files.

---

## Human Verification Required

### 1. WhatsApp Reminder Delivery

**Test:** Seed a confirmed booking with `is_apr=false`, `reminder_sent_at=null`, `cancellation_deadline = now() + 2 days`. Run `php artisan dotwai:process-deadlines` with a queue worker active.
**Expected:** `SendReminderJob` runs, WhatsApp message delivered to `client_phone` or `agent_phone` with hotel name, deadline, days remaining, and penalty. `reminder_sent_at` set to now.
**Why human:** Requires live queue worker, live Resayil WhatsApp gateway, and seeded data.

### 2. Auto-Invoice After Deadline Passes

**Test:** Seed a confirmed booking with `cancellation_deadline = now() - 1 hour`, `auto_invoiced_at=null`. Run `php artisan dotwai:process-deadlines`.
**Expected:** `AutoInvoiceDeadlineJob` runs, Invoice record created, JournalEntry created, voucher WhatsApp sent, `auto_invoiced_at` set.
**Why human:** Requires live queue, live WhatsApp, and accounting chart of accounts populated.

### 3. APR Auto-Invoice on Confirmation

**Test:** Complete a booking flow with a DOTW rate that has `is_apr=true`. Call `confirmWithCredit` or `confirmAfterPayment`.
**Expected:** `auto_invoiced_at` populated, Invoice record created, `booking_confirmed` webhook event dispatched to configured `DOTWAI_WEBHOOK_URL`.
**Why human:** Requires live DOTW API with APR rate and configured webhook URL.

### 4. Resend Voucher Endpoint

**Test:** Call `POST /api/dotwai/resend_voucher` with a valid auth token, a confirmed booking's prebook_key, and the associated phone number.
**Expected:** WhatsApp message delivered, response contains `booking_ref` and `whatsappMessage`.
**Why human:** Requires live WhatsApp gateway and auth token.

---

## Gaps Summary

### Gap 1: HIST-04 — DOTW PDF Voucher Retrieval Not Implemented

The requirement HIST-04 specifies that DOTW's PDF voucher should be retrieved from the API when supported, with a locally-generated fallback otherwise. The `VoucherService` (both `sendVoucher` and `resendVoucher`) sends only a formatted WhatsApp text message using `MessageBuilderService::formatVoucherMessage`. There is no DOTW API call for PDF retrieval and no local PDF generation anywhere in the codebase.

The plan's success criteria noted "HIST-04: VoucherService already supports DOTW PDF fallback (from Phase 19)" — this claim was inaccurate. Phase 19 created the VoucherService with text-only vouchers. Phase 21 did not add the PDF path either.

**Impact:** Customers receive a text summary instead of an actual PDF booking voucher. This may be functionally acceptable for WhatsApp delivery (text is readable), but the requirement as written is not met.

**To close:** Add DOTW `getBookingVoucher` (or equivalent) API call in `VoucherService`, with text-only fallback if the API returns no PDF.

---

_Verified: 2026-03-25_
_Verifier: Claude (gsd-verifier)_
