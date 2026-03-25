# Phase 20: Cancellation + Accounting - Context

**Gathered:** 2026-03-24
**Status:** Ready for planning
**Source:** Auto-generated from prior phase decisions + codebase scout

<domain>
## Phase Boundary

Build the cancellation flow (2-step: show penalty, then confirm) and hybrid accounting integration. After this phase: agents and customers can cancel bookings with full penalty visibility, cancellations with charges create journal entries + invoices, free cancellations update CRM/booking status only, and company statements can be generated.

Requirements: CANC-01, CANC-02, CANC-03, CANC-04, ACCT-01, ACCT-02, ACCT-03, ACCT-04, ACCT-05 (9 total)

Depends on: Phase 19 (BookingService, DotwAIBooking model, CreditService, BookingController)

</domain>

<decisions>
## Implementation Decisions

### Cancellation Flow (2-step via DOTW API)
- Step 1: `cancel_booking` with `confirm=no` calls `DotwService::cancelBooking(['confirm' => 'no', 'bookingCode' => X])` — returns penalty amount (charge field) without executing cancellation
- Step 2: User confirms — same endpoint called with `confirm=yes` and `penaltyApplied` amount — executes the cancellation
- Both steps return `bookingCode`, `refund`, `charge`, `status` from `parseCancellation()`
- DotwAIBooking status transitions: `confirmed` → `cancellation_pending` (after step 1 shown) → `cancelled` (after step 2 confirmed)
- WhatsApp message after step 1: show penalty amount, ask for explicit confirmation
- WhatsApp message after step 2: confirmation with warning that DOTW cancellation may take time to reflect on their portal

### Cancellation Accounting (hybrid approach — locked from PROJECT.md)
- Penalty > 0 (charged cancellation): create Invoice + JournalEntry for the penalty amount — money moved
- Penalty = 0 (free cancellation): update DotwAIBooking status to `cancelled`, update CRM record — NO journal entry, no invoice
- B2B with credit: refund the original booking amount minus penalty back to credit line via `CreditService::refundCredit()`
- B2B without credit / B2C: penalty was already paid, refund difference via payment gateway (or note for manual processing)
- APR bookings: already invoiced at confirmation (Phase 19 LIFE-04 deferred to Phase 21 — but cancellation still needs to handle the case where APR booking was auto-invoiced)

### Journal Entry Creation
- Uses existing JournalEntry model with debit/credit pattern
- CRITICAL (ACCT-04): Must use explicit `company_id` field — NOT rely on Auth global scope — since cancellations may be triggered from queue/scheduler context
- JournalEntry links: `invoice_id`, `task_id`, `company_id`, `branch_id`, `account_id`
- Account mapping: use existing Chart of Accounts (Account model) — cancellation penalty debits customer receivable, credits revenue
- Currency from DotwAIBooking record (stored at prebook time)

### Invoice Creation for Penalties
- Use existing Invoice model: `client_id`, `agent_id`, `currency`, `amount`, `status`
- Invoice status: `paid` (if penalty deducted from credit) or `pending` (if penalty to be collected)
- Link InvoiceDetail with penalty line item description
- No invoice for free cancellations (CANC-04)

### Company Statement (ACCT-02)
- New endpoint: GET /api/dotwai/statement — accepts phone, date_from, date_to
- Returns: list of bookings, cancellations, credits, debits for the company
- Matches with DOTW portal statement for reconciliation
- WhatsApp-formatted summary with totals

### Credit Limit Management (ACCT-05)
- CreditService already has `getBalance()` from Phase 19
- Add: ability to view credit transactions history
- Company credit limit stored on CompanyDotwCredential (or Company model)
- Admin-only adjustment — not exposed via WhatsApp API

### Claude's Discretion
- CancellationService class structure (standalone or extend BookingService)
- AccountingService class structure (standalone service or split per concern)
- Statement format details (exact fields, grouping by date/type)
- How to handle edge case: DOTW cancellation succeeds but our accounting entry fails (retry? flag for admin?)
- Whether to create a separate AccountingService or add methods to BookingService
- Test strategy for accounting entries (mock vs real DB)

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `DotwService::cancelBooking(['confirm' => 'no/yes', 'bookingCode' => X, 'penaltyApplied' => Y])` — 2-step cancel already built (line 529)
- `DotwService::parseCancellation()` — Returns `{bookingCode, refund, charge, status}` (line 1955)
- `CreditService::refundCredit()` — Already built in Phase 19 for credit refunds
- `CreditService::getBalance()` — Already built in Phase 19
- `DotwAIBooking` model — Has status field with transitions, `company_id`, price fields
- `MessageBuilderService` — Static formatters for WhatsApp messages (extend with cancel/statement formatters)
- `DotwAIResponse` — Response envelope with bilingual error codes (extend with CANCELLATION_FAILED, etc.)
- `BookingController` — Extend with cancel and statement endpoints

### Established Patterns
- All DotwAI services are instantiated with companyId for credential resolution
- MessageBuilderService uses all-static methods (pure functions)
- JournalEntry has company global scope — MUST bypass with `withoutGlobalScopes()` or explicit company_id in queue context
- Invoice::saving auto-generates invoice_number — no manual assignment needed
- Credit model types: INVOICE, TOPUP, REFUND, INVOICE_REFUND — use REFUND for credit-back on cancellation
- DotwAIResponse wraps all outputs with whatsappMessage + error codes

### Integration Points
- `app/Modules/DotwAI/Routes/api.php` — Add cancel_booking and statement routes
- `app/Modules/DotwAI/Http/Controllers/BookingController.php` — Add cancelBooking() and getStatement() methods
- `app/Modules/DotwAI/Services/BookingService.php` — Or create new CancellationService
- `app/Models/JournalEntry.php` — Use directly (don't modify, create entries programmatically)
- `app/Models/Invoice.php` — Use directly (don't modify)
- `app/Models/Credit.php` — Use directly via CreditService

</code_context>

<specifics>
## Specific Ideas

- DOTW cancelBooking has confirm=no/yes pattern — this naturally maps to the 2-step requirement (CANC-01)
- WhatsApp cancellation confirmation must include the warning about DOTW portal delay (CANC-02 — locked decision from PROJECT.md)
- The `charge` field from DOTW parseCancellation is the penalty amount — use this directly
- JournalEntry creation in queue context is a known concern (STATE.md blocker) — resolve with explicit company_id bypass
- Statement generation should query DotwAIBooking + JournalEntry + Credit tables, grouped by date

</specifics>

<deferred>
## Deferred Ideas

- Auto-reminders before cancellation deadline (Phase 21 — LIFE-01, LIFE-02)
- Auto-invoicing after deadline passes (Phase 21 — LIFE-03)
- APR auto-invoice on confirmation (Phase 21 — LIFE-04)
- Booking history endpoint (Phase 21 — HIST-01, HIST-02)
- Resend voucher (Phase 21 — HIST-03)
- Event webhooks for automation (Phase 21 — EVNT-01)
- Dashboard monitoring (Phase 22)

</deferred>

---

*Phase: 20-cancellation-accounting*
*Context gathered: 2026-03-24 via auto-advance from Phase 19*
