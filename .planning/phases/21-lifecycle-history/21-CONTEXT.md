# Phase 21: Lifecycle + History - Context

**Gathered:** 2026-03-25
**Status:** Ready for planning
**Source:** Auto-generated from requirements + prior phase decisions + codebase scout

<domain>
## Phase Boundary

Build automated booking lifecycle management (deadline reminders, auto-invoicing) and booking history/status endpoints. After this phase: the system sends WhatsApp reminders before cancellation deadlines, auto-invoices after deadlines pass, APR bookings are invoiced immediately, agents can check booking status and history, and Laravel pushes async events to n8n.

Requirements: LIFE-01, LIFE-02, LIFE-03, LIFE-04, LIFE-05, HIST-01, HIST-02, HIST-03, HIST-04, EVNT-01 (10 total)

Depends on: Phase 20 (CancellationService, AccountingService, VoucherService)

</domain>

<decisions>
## Implementation Decisions

### Cancellation Deadline Storage (LIFE-01)
- ALREADY DONE: `cancellation_deadline` datetime field exists on DotwAIBooking model (Phase 19)
- BookingService::prebook already extracts earliest charge-applicable fromDate and stores it
- Phase 21 just needs to ensure the scheduler reads this field — no new migration needed

### Auto-Reminders (LIFE-02)
- Daily scheduler job checks bookings with upcoming deadlines
- Send WhatsApp reminders via Resayil at 3 days, 2 days, and 1 day before cancellation_deadline
- Only for refundable bookings (rate_basis != APR) with status = 'confirmed'
- Track reminder_sent_at or reminder_count on DotwAIBooking to avoid duplicate sends
- Use MessageBuilderService for bilingual AR/EN reminder formatting
- Include: hotel name, dates, deadline date, current penalty amount, "cancel now" prompt

### Auto-Invoicing After Deadline (LIFE-03)
- Same scheduler: if cancellation_deadline has passed and booking not cancelled
- Auto-create invoice using AccountingService (from Phase 20)
- Send voucher via VoucherService (from Phase 19)
- Create accounting entries (JournalEntry) for the confirmed stay
- Update booking status to reflect invoiced/finalized state
- All with explicit company_id (ACCT-04 pattern from Phase 20)

### APR Auto-Invoice (LIFE-04)
- Non-refundable (APR) bookings: auto-invoice immediately on confirmation
- No reminder cycle — skip straight to invoice + voucher
- Triggered in BookingService::confirmWithCredit / confirmAfterPayment when rate is APR
- Check: booking.rate_basis contains 'APR' or cancellation rules show 100% penalty from day 1

### Scheduler Job (LIFE-05)
- Laravel artisan command: `dotwai:process-deadlines`
- Registered in schedule: runs daily (or every 6 hours for tighter window)
- Steps: 1) find reminders due, 2) send reminders, 3) find passed deadlines, 4) auto-invoice
- Queued dispatches for each action (not blocking the scheduler)

### Booking Status Endpoint (HIST-01)
- GET /api/dotwai/booking_status — accepts phone + prebook_key or booking_code
- Returns: booking details, cancellation policy, deadline, current penalty amount, status
- WhatsApp-formatted response with all key info

### Booking History Endpoint (HIST-02)
- GET /api/dotwai/booking_history — accepts phone, optional status filter, date range
- Returns: list of bookings for the agent/company, sorted by date
- WhatsApp-formatted list with booking summaries

### Resend Voucher (HIST-03)
- POST /api/dotwai/resend_voucher — accepts phone + prebook_key
- Calls existing VoucherService::resendVoucher (built in Phase 19)
- Simple endpoint wiring

### DOTW Voucher/PDF (HIST-04)
- Try DotwService::getBookingDetails to get DOTW booking info
- If PDF available from DOTW, retrieve and forward; otherwise use existing text voucher
- Fallback to locally generated voucher text (already built)

### Event Webhooks (EVNT-01)
- Laravel dispatches async events to a configurable webhook URL
- Events: payment_completed, reminder_due, deadline_passed, booking_confirmed
- Simple HTTP POST with JSON payload to webhook URL (for n8n consumption)
- Config: dotwai.webhook_url, dotwai.webhook_events (array of enabled events)
- Fire-and-forget with logging (don't block on webhook response)

### Claude's Discretion
- Scheduler frequency (daily vs every 6 hours)
- Migration for reminder tracking fields (add columns vs separate table)
- Event payload structure (what fields to include per event type)
- Whether to batch reminders or send individually
- Booking history pagination approach
- How to detect APR rate basis from booking data

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `DotwAIBooking.cancellation_deadline` — Already stored datetime field
- `VoucherService::sendVoucher()` / `resendVoucher()` — Built in Phase 19
- `AccountingService::createCancellationEntries()` — Built in Phase 20 (adapt for auto-invoice)
- `MessageBuilderService` — Static bilingual formatters (extend with reminder/status formatters)
- `DotwAIResponse` — Response envelope (extend with new error codes)
- `CreditService` — Credit operations
- `BookingController` — Extend with status/history/resend endpoints
- `DotwService::getBookingDetails()` — For HIST-04 DOTW voucher retrieval

### Established Patterns
- All services instantiated with companyId for credential resolution
- MessageBuilderService: all-static methods (pure functions)
- JournalEntry/Account: withoutGlobalScopes() + explicit company_id in queue context
- DotwAIResponse wraps all outputs with whatsappMessage + error codes
- Queue jobs: ShouldQueue, retries, backoff arrays (see ConfirmBookingAfterPaymentJob)

### Integration Points
- `app/Modules/DotwAI/Routes/api.php` — Add booking_status, booking_history, resend_voucher routes
- `app/Modules/DotwAI/Http/Controllers/BookingController.php` — Add new endpoint methods
- `app/Console/Kernel.php` or `routes/console.php` — Register scheduler command
- `app/Modules/DotwAI/Config/dotwai.php` — Add webhook_url, webhook_events config

</code_context>

<specifics>
## Specific Ideas

- Reminder text should create urgency: "Your free cancellation window closes in X days"
- Auto-invoice after deadline is the critical business logic — this prevents missed charges
- APR detection: check if cancellationRules first entry has 100% penalty from booking date
- Webhook events enable n8n to trigger follow-up conversations (e.g., "your booking is confirmed!")
- Booking history for agents shows all company bookings; for B2C shows only their own

</specifics>

<deferred>
## Deferred Ideas

- Dashboard monitoring (Phase 22)
- Multi-supplier aggregation (Future)
- Booking modification (cancel + rebook — no DOTW amendment API)

</deferred>

---

*Phase: 21-lifecycle-history*
*Context gathered: 2026-03-25 via auto-generation from requirements*
