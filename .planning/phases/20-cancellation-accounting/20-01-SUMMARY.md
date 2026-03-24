---
phase: 20-cancellation-accounting
plan: "01"
subsystem: DotwAI Cancellation + Accounting
tags: [cancellation, accounting, journal-entry, invoice, whatsapp, dotw, b2b, phase-20]
dependency_graph:
  requires:
    - 19-01 (BookingService, DotwAIBooking, CreditService)
    - 19-02 (PaymentBridgeService, BookingController pattern)
  provides:
    - CancellationService (2-step cancellation orchestration)
    - AccountingService (Invoice + JournalEntry creation for penalties)
    - POST /api/dotwai/cancel_booking endpoint
  affects:
    - BookingController (CancellationService injected)
    - DotwAIResponse (two new error codes)
    - MessageBuilderService (two new formatters)
tech_stack:
  added: []
  patterns:
    - DotwService instantiated with companyId (existing pattern)
    - DOTW API call outside DB transaction, DB writes inside transaction
    - withoutGlobalScopes() for all Account + JournalEntry queries
    - Static MessageBuilderService formatters (bilingual AR/EN)
key_files:
  created:
    - app/Modules/DotwAI/Services/CancellationService.php
    - app/Modules/DotwAI/Services/AccountingService.php
    - app/Modules/DotwAI/Http/Requests/CancelBookingRequest.php
  modified:
    - app/Modules/DotwAI/Models/DotwAIBooking.php
    - app/Modules/DotwAI/Services/DotwAIResponse.php
    - app/Modules/DotwAI/Services/MessageBuilderService.php
    - app/Modules/DotwAI/Http/Controllers/BookingController.php
    - app/Modules/DotwAI/Routes/api.php
decisions:
  - "DOTW API call made BEFORE DB::transaction: HTTP cannot be rolled back, so call DOTW first, then commit Eloquent writes inside transaction"
  - "If DB transaction fails after DOTW success: log critical + best-effort status update, return success to user (cancellation happened)"
  - "AccountingService uses new CreditService() internally (no DI) to resolve clientId — consistent with DotwService(companyId) pattern"
  - "getClientIdForCompany is an instance method on CreditService, not static — used via $this->creditService in CancellationService"
  - "B2B refund amount = display_total_fare - charge (if positive); zero or negative skips refundCredit call"
  - "Accounts not found: log warning, skip JournalEntry but still create Invoice — admin can reconcile"
  - "formatCancellationPending and formatCancellationConfirmed follow all-static MessageBuilderService pattern"
  - "CancelBookingRequest uses 'phone' field (matching middleware lookup key) not 'telephone'"
metrics:
  duration: "4 minutes 20 seconds"
  completed_date: "2026-03-24"
  tasks_completed: 2
  files_created: 3
  files_modified: 5
---

# Phase 20 Plan 01: Cancellation + Accounting Summary

**One-liner:** 2-step DOTW cancellation with penalty-triggered Invoice + double-entry JournalEntry accounting, B2B credit refund, and bilingual WhatsApp messages with portal delay warning.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | CancellationService + AccountingService + model constant + error codes + WhatsApp formatters | 83a34cd5 | DotwAIBooking.php, CancellationService.php, AccountingService.php, MessageBuilderService.php, DotwAIResponse.php |
| 2 | CancelBookingRequest, BookingController cancel endpoint, route registration | 6dcb12c3 | CancelBookingRequest.php, BookingController.php, api.php |

## What Was Built

### CancellationService (`CancellationService.php`)
Two-step cancellation orchestration:
- **confirm=no**: Calls `DotwService::cancelBooking(['confirm' => 'no'])`, transitions booking to `cancellation_pending`, returns penalty preview
- **confirm=yes**: Calls DOTW first (outside transaction), then opens `DB::transaction` for: status update to `cancelled`, `AccountingService::createCancellationEntries` (if charge > 0), `CreditService::refundCredit` (B2B only, net amount)
- Error handling: DOTW exceptions return `DOTW_API_ERROR`; DB failure after DOTW success logs critical + best-effort save + returns success

### AccountingService (`AccountingService.php`)
Creates accounting entries for penalty charges:
- Resolves `clientId` via `(new CreditService())->getClientIdForCompany()`
- Creates `Invoice` (status=UNPAID, or PAID for B2B) + `InvoiceDetail`
- Resolves accounts with `Account::withoutGlobalScopes()->where('company_id', ...)->where('name', 'LIKE', '%Client%')` and `'%Revenue%'`
- Creates 2 `JournalEntry` records (debit receivable, credit revenue) — both with explicit `company_id`
- If accounts not found: logs warning, skips JournalEntry, still creates Invoice

### DotwAIBooking Model
Added `STATUS_CANCELLATION_PENDING = 'cancellation_pending'` constant (after `STATUS_EXPIRED`).

### MessageBuilderService
Two new static formatters:
- `formatCancellationPending(array $data)`: shows penalty, asks for confirmation to proceed
- `formatCancellationConfirmed(array $data)`: shows outcome, includes CANC-02 DOTW delay warning

### DotwAIResponse
Two new error codes with bilingual default messages:
- `CANCELLATION_NOT_ALLOWED` — booking not in cancellable status
- `CANCELLATION_FAILED` — generic cancellation processing error

### Endpoint
`POST /api/dotwai/cancel_booking` in `dotwai.resolve` middleware group:
- `CancelBookingRequest` validates: `phone`, `prebook_key`, `confirm` (no/yes), `penalty_amount` (required when confirm=yes)
- `BookingController::cancelBooking()` delegates to `CancellationService::cancel()`
- Response always includes `whatsappMessage` (preview or confirmed) + `whatsappOptions`

## Deviations from Plan

### Auto-fixed Issues

None — plan executed exactly as written.

### Clarifications Applied

**1. getClientIdForCompany is instance method, not static**
- **Found during:** Task 1 (reading CreditService.php)
- **Issue:** Plan interface notes showed `CreditService::getClientIdForCompany($companyId)` as static, but actual implementation is `public function getClientIdForCompany(int $companyId): ?int` (instance method)
- **Fix:** In CancellationService: used `$this->creditService->getClientIdForCompany()`. In AccountingService (no CreditService DI per plan): used `(new CreditService())->getClientIdForCompany()`, consistent with `new DotwService($companyId)` pattern
- **Impact:** None — behavior identical, pattern matches module conventions

## Self-Check: PASSED

- FOUND: app/Modules/DotwAI/Services/CancellationService.php
- FOUND: app/Modules/DotwAI/Services/AccountingService.php
- FOUND: app/Modules/DotwAI/Http/Requests/CancelBookingRequest.php
- FOUND commit: 83a34cd5 (Task 1)
- FOUND commit: 6dcb12c3 (Task 2)
