---
phase: 21-lifecycle-history
plan: "02"
subsystem: api
tags: [webhook, queue, booking-history, voucher, apr-invoicing, laravel-jobs]

# Dependency graph
requires:
  - phase: 21-lifecycle-history-01
    provides: ProcessDeadlinesCommand, SendReminderJob, AutoInvoiceDeadlineJob, auto_invoiced_at column
  - phase: 20-cancellation-accounting
    provides: AccountingService::createAutoInvoiceForDeadline, CancellationService
  - phase: 19-b2b-b2c-booking
    provides: BookingService::confirmWithCredit, BookingService::confirmAfterPayment, VoucherService::resendVoucher
provides:
  - APR auto-invoicing immediately on confirmation in both credit and gateway flows
  - WebhookEventService — static event dispatch with config-gated fire-and-forget delivery
  - WebhookDispatchJob — queued HTTP webhook with 4 tries and exponential backoff
  - GET /api/dotwai/booking_status — returns cancellation policy, deadline, penalty, current status
  - GET /api/dotwai/booking_history — paginated list with status/date filters
  - POST /api/dotwai/resend_voucher — re-sends booking confirmation via VoucherService
  - BookingStatusRequest, BookingHistoryRequest, ResendVoucherRequest validators
  - formatBookingStatusMessage, formatBookingHistoryMessage, formatVoucherResendConfirmation formatters
affects:
  - n8n workflows (consume booking_confirmed webhook events)
  - accounting (APR auto-invoice on confirmation)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - WebhookEventService all-static methods (pure functions, same as MessageBuilderService)
    - Webhook dispatch is always async via queue job (never synchronous HTTP in request cycle)
    - Config-gated webhooks: empty webhook_url or disabled event type silently skips
    - APR invoicing wrapped in try/catch — booking stays confirmed even if invoicing fails

key-files:
  created:
    - app/Modules/DotwAI/Services/WebhookEventService.php
    - app/Modules/DotwAI/Jobs/WebhookDispatchJob.php
    - app/Modules/DotwAI/Http/Requests/BookingStatusRequest.php
    - app/Modules/DotwAI/Http/Requests/BookingHistoryRequest.php
    - app/Modules/DotwAI/Http/Requests/ResendVoucherRequest.php
  modified:
    - app/Modules/DotwAI/Services/BookingService.php
    - app/Modules/DotwAI/Http/Controllers/BookingController.php
    - app/Modules/DotwAI/Services/MessageBuilderService.php
    - app/Modules/DotwAI/Routes/api.php

key-decisions:
  - "APR auto-invoice failure does not fail the booking — booking stays confirmed, error logged for reconciliation"
  - "WebhookDispatchJob retries: 4 tries, backoff 30s/2m/5m, 10s timeout per attempt"
  - "Webhook events are config-gated — dotwai.webhook_url empty = all webhooks disabled; per-event gating via webhook_events array"
  - "booking_history uses paginate() with collect(items()) to avoid calling map() on plain array"
  - "booking_status and booking_history both scope by company_id + phone (agent_phone OR client_phone)"

patterns-established:
  - "Static-only services: WebhookEventService follows same all-static pattern as MessageBuilderService"
  - "Endpoint pattern: thin controller delegates to service, always returns DotwAIResponse::success/error with whatsappMessage"
  - "Queue job pattern: implements ShouldQueue, public $tries/$backoff/$timeout, throws on failure to trigger retry"

requirements-completed: [LIFE-04, HIST-01, HIST-02, HIST-03, HIST-04, EVNT-01]

# Metrics
duration: 12min
completed: 2026-03-24
---

# Phase 21 Plan 02: APR Auto-Invoice, Three REST Endpoints, and Async Webhook Events Summary

**APR auto-invoicing on confirmation, three new REST endpoints (booking_status, booking_history, resend_voucher), and fire-and-forget webhook event dispatch via Laravel queue jobs for n8n automation**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-03-24T23:03:00Z
- **Completed:** 2026-03-24T23:15:00Z
- **Tasks:** 2
- **Files modified:** 9

## Accomplishments

- APR (non-refundable) bookings auto-invoice via AccountingService immediately on confirmation in both credit and gateway flows — no reminder cycle needed
- Three new REST endpoints for booking lifecycle queries: booking_status (with cancellation policy, deadline, penalty), booking_history (paginated with filters), and resend_voucher
- WebhookDispatchJob sends async HTTP events to n8n with 4 retries and exponential backoff — booking_confirmed event fires from both confirm flows

## Task Commits

Each task was committed atomically:

1. **Task 1: APR auto-invoice + WebhookEventService** - `bd512927` (feat)
2. **Task 2: WebhookDispatchJob + three REST endpoints** - `6c4a5f7d` (feat)

**Plan metadata:** (docs commit follows)

## Endpoint Summary

### GET /api/dotwai/booking_status
- **Auth:** dotwai.resolve middleware (phone-scoped)
- **Params:** `phone` (required), `prebook_key` or `booking_code` (one required)
- **Returns:** status, hotel details, cancellation_deadline, is_refundable, current_penalty, whatsappMessage

### GET /api/dotwai/booking_history
- **Auth:** dotwai.resolve middleware
- **Params:** `phone` (required), `status`, `from_date`, `to_date`, `page`, `per_page` (all optional)
- **Returns:** paginated booking list with hotel/date/status, total count, whatsappMessage

### POST /api/dotwai/resend_voucher
- **Auth:** dotwai.resolve middleware
- **Body:** `phone` (required), `prebook_key` (required)
- **Returns:** booking_ref, whatsappMessage confirming resend

## APR Auto-Invoice Behavior

- Fires after booking status is set to `confirmed` in both `confirmWithCredit()` and `confirmAfterPayment()`
- Uses `DB::transaction()` wrapping `AccountingService::createAutoInvoiceForDeadline($booking)`
- Sets `auto_invoiced_at = now()` on success (idempotency marker from Phase 21-01)
- On failure: logs error, does NOT throw — booking confirmed state is preserved for admin reconciliation

## Webhook Event Payload Structure

```json
{
  "event": "booking_confirmed",
  "timestamp": "2026-03-24T23:15:00+00:00",
  "source": "dotwai",
  "data": {
    "booking_id": 123,
    "booking_ref": "DOTW-12345678",
    "hotel_name": "Hilton Dubai Creek",
    "check_in": "2026-04-10",
    "check_out": "2026-04-15",
    "track": "b2b"
  }
}
```

Other event types (dispatched by existing jobs from Phase 21-01): `payment_completed`, `reminder_due`, `deadline_passed`

## Test Commands

```bash
# Test WebhookDispatchJob queuing (requires queue worker)
php artisan tinker
>>> use App\Modules\DotwAI\Jobs\WebhookDispatchJob;
>>> WebhookDispatchJob::dispatch(['event' => 'test', 'timestamp' => now()->toIso8601String(), 'data' => []]);

# Test WebhookEventService (webhook_url must be configured in .env)
php artisan tinker
>>> use App\Modules\DotwAI\Services\WebhookEventService;
>>> WebhookEventService::dispatchEvent('booking_confirmed', ['booking_id' => 1]);

# Test booking status endpoint
curl -X GET "http://localhost:8000/api/dotwai/booking_status?phone=%2B96550123456&prebook_key=DOTWAI-xxx" \
  -H "Authorization: Bearer {token}"

# Test booking history endpoint
curl -X GET "http://localhost:8000/api/dotwai/booking_history?phone=%2B96550123456&status=confirmed" \
  -H "Authorization: Bearer {token}"

# Test resend voucher endpoint
curl -X POST "http://localhost:8000/api/dotwai/resend_voucher" \
  -H "Authorization: Bearer {token}" \
  -d "phone=%2B96550123456&prebook_key=DOTWAI-xxx"

# Verify APR auto-invoice in BookingService
grep -n "invoiceAPRBooking" app/Modules/DotwAI/Services/BookingService.php
```

## Files Created/Modified

- `app/Modules/DotwAI/Services/WebhookEventService.php` (created) — Static dispatch coordination, config-gated
- `app/Modules/DotwAI/Jobs/WebhookDispatchJob.php` (created) — Fire-and-forget HTTP with retry/backoff
- `app/Modules/DotwAI/Http/Requests/BookingStatusRequest.php` (created) — phone + prebook_key/booking_code validator
- `app/Modules/DotwAI/Http/Requests/BookingHistoryRequest.php` (created) — phone + status/date/pagination validator
- `app/Modules/DotwAI/Http/Requests/ResendVoucherRequest.php` (created) — phone + prebook_key validator
- `app/Modules/DotwAI/Services/BookingService.php` (modified) — APR hook + webhook dispatch in both confirm methods
- `app/Modules/DotwAI/Http/Controllers/BookingController.php` (modified) — Three new endpoint methods
- `app/Modules/DotwAI/Services/MessageBuilderService.php` (modified) — Three new message formatters
- `app/Modules/DotwAI/Routes/api.php` (modified) — Three new routes registered

## Decisions Made

- APR invoicing failure does not fail the confirmed booking — revenue accounting is secondary to booking state (same pattern as Phase 20 AccountingService skipping JournalEntry when accounts not found)
- WebhookDispatchJob throws on HTTP error to trigger Laravel queue retry mechanism via `$backoff` array
- booking_history uses `collect($paginator->items())` to iterate safely — paginator items() returns array, not collection
- Both booking_status and booking_history scope by both agent_phone and client_phone to serve both actors

## Deviations from Plan

None — plan executed exactly as written. The `$bookings->items()->map(...)` call in the plan's code sample was adapted to use `collect($paginator->items())` since `items()` returns a plain PHP array (not a collection), but this is a minor correctness fix, not a deviation from intent.

## Issues Encountered

None significant. PHP syntax validation passed for all 9 files.

## Next Phase Readiness

Phase 21 is now fully complete:
- Plan 21-01: Deadline scheduler, reminder jobs, auto-invoice jobs
- Plan 21-02: APR on-confirmation invoice, booking status/history/resend endpoints, webhook events

The DotwAI module has complete lifecycle automation: prebook → confirm → reminder → deadline → cancel/invoice, with n8n webhook integration for external workflow automation.

## Self-Check: PASSED

All created files verified on disk. All task commits found in git log.

- FOUND: app/Modules/DotwAI/Services/WebhookEventService.php
- FOUND: app/Modules/DotwAI/Jobs/WebhookDispatchJob.php
- FOUND: app/Modules/DotwAI/Http/Requests/BookingStatusRequest.php
- FOUND: app/Modules/DotwAI/Http/Requests/BookingHistoryRequest.php
- FOUND: app/Modules/DotwAI/Http/Requests/ResendVoucherRequest.php
- FOUND: .planning/phases/21-lifecycle-history/21-02-SUMMARY.md
- COMMIT bd512927: feat(21-02): integrate APR auto-invoice and WebhookEventService
- COMMIT 6c4a5f7d: feat(21-02): add WebhookDispatchJob, three REST endpoints and message formatters

---
*Phase: 21-lifecycle-history*
*Completed: 2026-03-24*
