---
phase: 21-lifecycle-history
plan: "01"
subsystem: DotwAI Lifecycle Automation
tags: [scheduler, queue-jobs, whatsapp-reminders, auto-invoice, accounting, lifecycle]
dependency_graph:
  requires:
    - 20-cancellation-accounting (AccountingService, VoucherService, WhatsappController patterns)
    - 19-b2b-b2c-booking (DotwAIBooking model, VoucherService, MessageBuilderService)
  provides:
    - LifecycleService (deadline detection queries)
    - ProcessDeadlinesCommand (daily scheduler dispatcher)
    - SendReminderJob (WhatsApp reminder queue job)
    - AutoInvoiceDeadlineJob (deadline-pass auto-invoice queue job)
  affects:
    - DotwAIBooking (adds reminder_sent_at, auto_invoiced_at fields)
    - AccountingService (adds createAutoInvoiceForDeadline method)
    - MessageBuilderService (adds formatReminderMessage, formatDeadlinePassedMessage)
    - Kernel::schedule() (registers daily dotwai:process-deadlines command)
tech_stack:
  added: []
  patterns:
    - Queue jobs with Dispatchable/InteractsWithQueue/Queueable/SerializesModels traits
    - Idempotency gates via nullable timestamp fields (reminder_sent_at, auto_invoiced_at)
    - DB::transaction wrapping accounting + voucher in AutoInvoiceDeadlineJob
    - Clock skew guard in AutoInvoiceDeadlineJob (re-verify deadline before invoicing)
    - withoutGlobalScopes() for Account queries in queue context (no Auth::user())
key_files:
  created:
    - app/Modules/DotwAI/Database/Migrations/2026_03_25_000000_add_lifecycle_fields_to_dotwai_bookings_table.php
    - app/Modules/DotwAI/Services/LifecycleService.php
    - app/Modules/DotwAI/Jobs/SendReminderJob.php
    - app/Modules/DotwAI/Jobs/AutoInvoiceDeadlineJob.php
    - app/Modules/DotwAI/Commands/ProcessDeadlinesCommand.php
  modified:
    - app/Modules/DotwAI/Models/DotwAIBooking.php (fillable + casts for lifecycle fields)
    - app/Modules/DotwAI/Services/MessageBuilderService.php (2 new formatters)
    - app/Modules/DotwAI/Services/AccountingService.php (createAutoInvoiceForDeadline)
    - app/Modules/DotwAI/Providers/DotwAIServiceProvider.php (register ProcessDeadlinesCommand)
    - app/Console/Kernel.php (dailyAt 03:00 schedule registration)
    - app/Modules/DotwAI/Config/dotwai.php (webhook_url + webhook_events prep)
decisions:
  - "ProcessDeadlinesCommand is a pure dispatcher — no side effects; all work in queue jobs"
  - "reminder_sent_at stays NULL on job failure so scheduler retries on next cycle"
  - "AutoInvoiceDeadlineJob re-verifies deadline before invoicing to handle clock skew"
  - "AccountingService::createAutoInvoiceForDeadline uses company_id directly from booking (no DotwAIContext needed in queue)"
  - "webhook_url + webhook_events added to config now (no implementation) for Phase 21 Plan 02 prep"
metrics:
  duration: "~15 minutes"
  completed_date: "2026-03-25"
  tasks_completed: 3
  files_created: 5
  files_modified: 6
---

# Phase 21 Plan 01: Lifecycle Automation Infrastructure Summary

Scheduled daily deadline checks, WhatsApp reminders at 3/2/1 days before cancellation deadline, and auto-invoicing after deadline passes — all via queue jobs dispatched from a single Artisan scheduler command.

## What Was Built

### Migration
Adds `reminder_sent_at` and `auto_invoiced_at` nullable timestamp columns to `dotwai_bookings`. These serve as idempotency markers to prevent duplicate WhatsApp sends and duplicate invoice creation across scheduler cycles.

### LifecycleService
Pure query service with three methods:
- `findBookingsDueForReminder()` — confirmed, non-APR, no reminder sent, deadline within 3 days
- `findBookingsWithPassedDeadline()` — confirmed, deadline in the past, not yet auto-invoiced
- `markReminderSent(int $bookingId)` — sets `reminder_sent_at = now()`

### SendReminderJob
Queue job that:
1. Checks idempotency gate (skips if `reminder_sent_at` already set)
2. Calculates `daysLeft` until deadline
3. Sends bilingual AR/EN WhatsApp reminder via `WhatsappController::sendToResayil`
4. Marks `reminder_sent_at` only after successful send
5. Retries 3 times with 30s/120s backoff; on final failure, leaves `reminder_sent_at` NULL for next cycle

### AutoInvoiceDeadlineJob
Queue job that:
1. Checks idempotency gate (skips if `auto_invoiced_at` already set)
2. Re-verifies deadline has passed (clock skew guard)
3. Inside `DB::transaction`: calls `AccountingService::createAutoInvoiceForDeadline` + `VoucherService::sendVoucher`
4. Marks `auto_invoiced_at = now()`
5. Retries 3 times with 60s/300s backoff; on final failure, logged as critical

### AccountingService extension
New `createAutoInvoiceForDeadline(DotwAIBooking $booking)` method:
- Creates Invoice + InvoiceDetail for the full booking amount
- Links `invoice_id` back to the booking
- Resolves accounts via `withoutGlobalScopes()` (queue safety)
- Creates double-entry JournalEntry (debit receivable, credit revenue) with type `'booking'`
- Gracefully skips JournalEntry if accounts not found (logs warning, invoice still created)

### MessageBuilderService extension
- `formatReminderMessage(DotwAIBooking, int $daysLeft)` — bilingual AR/EN with hotel, dates, deadline, days left, penalty
- `formatDeadlinePassedMessage(DotwAIBooking)` — bilingual confirmation that booking is now locked

### ProcessDeadlinesCommand
Artisan command `dotwai:process-deadlines`:
1. Calls `LifecycleService::findBookingsDueForReminder()` → dispatches `SendReminderJob` per booking
2. Calls `LifecycleService::findBookingsWithPassedDeadline()` → dispatches `AutoInvoiceDeadlineJob` per booking
3. Logs counts for both phases

### Kernel registration
```php
$schedule->command('dotwai:process-deadlines')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();
```

### DotwAIServiceProvider update
`ProcessDeadlinesCommand` added to the module's command registration alongside `ImportHotelsCommand` and `SyncStaticDataCommand`.

### dotwai.php config prep
Added `webhook_url` and `webhook_events` keys for Phase 21 Plan 02 (no implementation yet).

## Commits

| Task | Commit   | Description |
|------|----------|-------------|
| 1    | a6c264cc | add lifecycle tracking fields, LifecycleService, and reminder formatters |
| 2    | e463b6fd | create SendReminderJob and AutoInvoiceDeadlineJob queue jobs |
| 3    | fce9cfb3 | create ProcessDeadlinesCommand and register in Kernel scheduler |

## Verification Commands

```bash
# 1. Apply migrations
php artisan migrate --path=app/Modules/DotwAI/Database/Migrations

# 2. Verify command is registered
php artisan list | grep dotwai
# Expected: dotwai:process-deadlines, dotwai:import-hotels, dotwai:sync-static-data

# 3. Run command manually (dry-run with no bookings)
php artisan dotwai:process-deadlines
# Expected: "No bookings due for reminders." + "No bookings with passed deadlines."

# 4. Check scheduler registration
php artisan schedule:list | grep dotwai
# Expected: dotwai:process-deadlines (daily 03:00)

# 5. Verify new model fields
php artisan tinker --execute="echo \App\Modules\DotwAI\Models\DotwAIBooking::first()?->reminder_sent_at ?? 'null (correct)';"
```

## Deviations from Plan

### Auto-added: AccountingService::createAutoInvoiceForDeadline (Rule 2 - Missing Critical)
- **Found during:** Task 2 (AutoInvoiceDeadlineJob calls this method)
- **Issue:** Plan referenced `$accountingService->createAutoInvoiceForDeadline($booking)` but the method did not exist in AccountingService
- **Fix:** Added `createAutoInvoiceForDeadline` method to AccountingService following existing `createCancellationEntries` pattern
- **Files modified:** `app/Modules/DotwAI/Services/AccountingService.php`
- **Commit:** e463b6fd

### Auto-added: ProcessDeadlinesCommand registration in DotwAIServiceProvider (Rule 3 - Blocking)
- **Found during:** Task 3
- **Issue:** Kernel.php's `commands()` method only loads `app/Console/Commands`. The DotwAI module's commands are registered via ServiceProvider. Without adding `ProcessDeadlinesCommand` there, the scheduler would fail to find the command.
- **Fix:** Added `ProcessDeadlinesCommand::class` to `DotwAIServiceProvider::boot()` alongside existing commands.
- **Files modified:** `app/Modules/DotwAI/Providers/DotwAIServiceProvider.php`
- **Commit:** fce9cfb3

## What Phase 21 Plan 02 Still Needs

- APR bookings auto-invoiced immediately on confirmation (hook in `BookingService::confirmB2b/confirmAfterPayment`)
- Webhook event dispatch (`DotwAIWebhookDispatcher` or similar) using the prepared `webhook_url` config
