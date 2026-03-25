# Phase 21: Lifecycle + History - Research

**Researched:** 2026-03-25
**Domain:** Booking lifecycle automation, scheduler jobs, webhook events, booking status/history endpoints
**Confidence:** HIGH

## Summary

Phase 21 implements automated booking lifecycle management in the DotwAI module: scheduled reminders before cancellation deadlines, auto-invoicing after deadlines pass, booking status/history endpoints for agents and customers, and async event dispatch to n8n webhooks. The phase extends existing services (CancellationService, AccountingService, VoucherService, MessageBuilderService) with four new components: a scheduler command (`dotwai:process-deadlines`), reminder/deadline-passed queue jobs, three new REST endpoints, and webhook event dispatch logic.

**Primary recommendation:** Build lifecycle as a scheduled command + queue jobs pattern (already established in codebase), extend MessageBuilderService with reminder/status formatters, dispatch webhook events via Queue jobs with fire-and-forget HTTP requests.

## User Constraints (from CONTEXT.md)

### Locked Decisions
- Cancellation deadline is already stored (`cancellation_deadline` field on DotwAIBooking model)
- BookingService::prebook already extracts earliest charge-applicable date and stores it
- Reminders: 3 days, 2 days, 1 day before deadline via WhatsApp (Resayil)
- Reminder tracking via `reminder_sent_at` or `reminder_count` on DotwAIBooking
- Auto-invoicing after deadline uses AccountingService + VoucherService from Phase 20
- APR detection: `rate_basis` contains 'APR' or first cancellation rule shows 100% penalty from day 1
- Scheduler runs daily (Phase 21 decision discretion)
- Booking status endpoint: phone + prebook_key or booking_code
- Booking history endpoint: phone + optional status/date filters
- Webhook events: payment_completed, reminder_due, deadline_passed, booking_confirmed
- Config: dotwai.webhook_url, dotwai.webhook_events (array)

### Claude's Discretion
- Scheduler frequency: daily vs every 6 hours
- Migration for reminder tracking: add columns vs separate table
- Event payload structure: fields per event type
- Batch vs individual reminder sends
- Booking history pagination approach
- APR detection logic refinement

### Deferred Ideas (OUT OF SCOPE)
- Dashboard monitoring (Phase 22)
- Multi-supplier aggregation
- Booking modification (cancel + rebook)

## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| LIFE-01 | Cancellation deadline date stored per booking | Existing DotwAIBooking::cancellation_deadline field; BookingService::prebook already extracts this |
| LIFE-02 | Daily scheduler sends WhatsApp reminders at 3/2/1 days before deadline | Scheduler command pattern + queue jobs for non-blocking sends |
| LIFE-03 | After deadline passes: auto-create invoice, send voucher, record entries | Reuse AccountingService::createCancellationEntries adapted for deadline-pass flow |
| LIFE-04 | APR bookings auto-invoiced on confirmation (no reminder cycle) | Trigger in BookingService::confirmWithCredit/confirmAfterPayment via is_apr flag + 100% penalty detection |
| LIFE-05 | Scheduler job runs daily, dispatches reminders + auto-invoicing via queue | Implement ProcessDeadlinesCommand in DotwAI module, register in Kernel::schedule |
| HIST-01 | Booking status endpoint: returns deadline, cancellation policy, current penalty | New BookingController::bookingStatus method, extend MessageBuilderService formatters |
| HIST-02 | Booking history endpoint: lists bookings with status/date filters | New BookingController::bookingHistory method, implement pagination logic |
| HIST-03 | Resend voucher endpoint: calls VoucherService::resendVoucher | Simple endpoint wiring, VoucherService already built |
| HIST-04 | DOTW voucher/PDF retrieval via DotwService::getBookingDetails fallback | Extend VoucherService with DOTW PDF fetch attempt, fallback to text voucher |
| EVNT-01 | Laravel dispatches async events to webhook URL for n8n consumption | Implement WebhookDispatchJob queue job, fire-and-forget HTTP POST with retry logic |

## Standard Stack

### Core
| Component | Purpose | Implementation |
|-----------|---------|-----------------|
| Laravel Scheduler (Kernel::schedule) | Execute daily deadline check | dotwai:process-deadlines artisan command |
| Queue Jobs (ShouldQueue) | Non-blocking reminders, invoicing, webhooks | ProcessReminderDueJob, AutoInvoiceDeadlineJob, WebhookDispatchJob |
| Laravel HTTP Client | Fire-and-forget webhook dispatch | Illuminate\Support\Facades\Http + Queue job for retries |
| MessageBuilderService | Bilingual reminder/status formatting | Extend existing static methods |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Laravel 11 Mail | Built-in | WhatsApp via Resayil (not email) | Already integrated; use WhatsappController |
| Eloquent Query Builder | Built-in | Deadline queries, scoping | Find bookings with upcoming/passed deadlines |
| DB::transaction | Built-in | Atomic invoice+entry creation | LIFE-03 auto-invoicing flow |

### Installation
No new packages required — all components use Laravel 11 built-in features.

## Architecture Patterns

### Recommended Project Structure
```
app/Modules/DotwAI/
├── Commands/
│   └── ProcessDeadlinesCommand.php          # Scheduler entry point
├── Jobs/
│   ├── SendReminderJob.php                  # Queue job for reminder at specific day
│   ├── AutoInvoiceDeadlineJob.php           # Queue job for deadline-passed invoicing
│   └── WebhookDispatchJob.php               # Fire-and-forget webhook event
├── Services/
│   ├── LifecycleService.php (NEW)           # Reminder/deadline detection logic
│   └── WebhookEventService.php (NEW)        # Event dispatch coordination
├── Routes/
│   └── api.php (EXTEND)                     # Add booking_status, booking_history, resend_voucher
└── Http/Controllers/
    └── BookingController.php (EXTEND)       # Add new endpoint methods
```

### Pattern 1: Scheduler Command → Queue Dispatch

**What:** Lightweight scheduler command finds items needing action, dispatches them to queue jobs for async processing.

**When to use:** Heavy operations (DOTW calls, invoice creation, HTTP webhooks) that shouldn't block the scheduler tick.

**Example:**
```php
// app/Modules/DotwAI/Commands/ProcessDeadlinesCommand.php
class ProcessDeadlinesCommand extends Command
{
    public function handle(): int
    {
        // Find bookings needing reminders (refundable, 3/2/1 days out)
        $bookingsDue = DotwAIBooking::where('status', 'confirmed')
            ->where('is_apr', false)
            ->whereNull('reminder_sent_at')
            ->whereBetween('cancellation_deadline', [
                now()->addDays(1),
                now()->addDays(3)
            ])
            ->get();

        foreach ($bookingsDue as $booking) {
            SendReminderJob::dispatch($booking->id);
        }

        // Find passed deadlines (not yet invoiced)
        $overdue = DotwAIBooking::where('status', 'confirmed')
            ->where('cancellation_deadline', '<', now())
            ->whereNull('auto_invoiced_at')
            ->get();

        foreach ($overdue as $booking) {
            AutoInvoiceDeadlineJob::dispatch($booking->id);
        }

        return self::SUCCESS;
    }
}
```

**Source:** Laravel 11 console commands pattern; established in codebase via ProcessAirFilesCommand.

### Pattern 2: Fire-and-Forget Webhook Events via Queue

**What:** Queue job wraps webhook event dispatch with retries, doesn't block scheduler.

**When to use:** External webhooks (n8n) that may timeout or fail; needs independent retry logic.

**Example:**
```php
// app/Modules/DotwAI/Jobs/WebhookDispatchJob.php
class WebhookDispatchJob implements ShouldQueue
{
    public int $tries = 4;
    public array $backoff = [30, 120, 300];  // Exponential: 30s, 2m, 5m
    public int $timeout = 10;

    public function handle(): void
    {
        $webhook_url = config('dotwai.webhook_url');
        if (empty($webhook_url)) {
            return;  // No webhook configured, skip
        }

        try {
            Http::timeout(5)->post($webhook_url, $this->payload);
            Log::info('[DotwAI] Webhook dispatched', $this->payload);
        } catch (Throwable $e) {
            // Retry via job's backoff; final fail is logged
            Log::warning('[DotwAI] Webhook dispatch failed', ['error' => $e->getMessage()]);
            throw $e;  // Trigger retry
        }
    }
}
```

**Source:** Laravel 11 queue jobs; established in codebase via ConfirmBookingAfterPaymentJob pattern.

### Pattern 3: Queued Invoice Creation (Deadline-Pass Flow)

**What:** Reuse AccountingService to create Invoice + JournalEntry when deadline passes.

**When to use:** Money-movement events that need accounting audit trail and billing visibility.

**Example:**
```php
// In AutoInvoiceDeadlineJob::handle()
$booking = DotwAIBooking::find($this->bookingId);
$context = new DotwAIContext(
    companyId: $booking->company_id,
    agent: Agent::find(...),  // resolve from agent_phone
    track: 'b2b' | 'b2c'
);

$accountingService = new AccountingService();
DB::transaction(function () use ($accountingService, $booking, $context) {
    // Create invoice + entries for confirmed stay
    $accountingService->createAutoInvoiceForDeadline($booking, $context);

    // Send voucher via WhatsApp
    $voucherService = new VoucherService();
    $voucherService->sendVoucher($booking);

    // Dispatch webhook event
    WebhookDispatchJob::dispatch([
        'event' => 'deadline_passed',
        'booking_ref' => $booking->booking_ref,
        'invoiced_at' => now()->toIso8601String(),
    ]);

    $booking->update(['auto_invoiced_at' => now()]);
});
```

**Source:** Phase 20 AccountingService pattern; extended for deadline flows.

### Anti-Patterns to Avoid
- **Synchronous webhook calls in scheduler:** Block scheduler tick on network timeout. Use Queue job instead.
- **Reminder duplicate tracking with just timestamps:** Use `reminder_sent_at` + only query null value; prevents race conditions.
- **Creating invoices for passed deadlines without transaction:** Can result in orphaned Invoice records if JournalEntry fails.
- **Checking APR status only at prebook:** Recheck at confirmation; DOTW response may reveal different cancellation rules.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Booking deadline tracking | Custom reminder storage table | DotwAIBooking::reminder_sent_at + reminder_count fields | Single source of truth; avoids sync bugs |
| Scheduled tasks | Cron jobs outside Laravel | artisan command + Kernel::schedule | Respects app context, retries, logging |
| Webhook dispatch | Direct Http::post() in service | Queue job with backoff + timeout | Handles network failures, rate limits, retry jitter |
| Reminder text formatting | String concatenation | MessageBuilderService static methods | Reusable, testable, bilingual-ready |
| Invoice creation for deadlines | Manual INSERT via raw SQL | AccountingService + DB::transaction | Enforces business logic, creates audit trail |

**Key insight:** Deadline automation is a distributed system problem (scheduler → queue → services). Each layer has one job; failures at any point should not cascade.

## Common Pitfalls

### Pitfall 1: Scheduler Blocking on Network Calls
**What goes wrong:** ProcessDeadlinesCommand calls DOTW API or WhatsApp directly → timeout → entire scheduler stalls.
**Why it happens:** Developer assumes scheduler runs everything; doesn't separate "find work" from "do work".
**How to avoid:** Scheduler only queries DB, dispatches queue jobs. Jobs do DOTW/WhatsApp calls with timeout + retry.
**Warning signs:** Scheduler taking > 5 seconds to run; increased error logs during reminder window.

### Pitfall 2: Duplicate Reminders Sent
**What goes wrong:** Job runs twice in race condition → customer gets two messages at same second.
**Why it happens:** No idempotency check on reminder dispatch; checking `reminder_sent_at` after sending is too late.
**How to avoid:** Always check `reminder_sent_at IS NULL` in DB query before dispatching job; atomically update after send succeeds.
**Warning signs:** Customer reports getting two identical messages 1-2 seconds apart.

### Pitfall 3: Auto-Invoice Without Verifying Deadline Really Passed
**What goes wrong:** Booking marked invoiced 1 minute before deadline due to clock skew or race condition.
**Why it happens:** Server time drifts; scheduler runs multiple times in rapid succession.
**How to avoid:** Always re-query `cancellation_deadline` in job and verify it's actually in the past before creating invoice.
**Warning signs:** Invoices created with dates that seem off by hours/days.

### Pitfall 4: APR Detection Happens Once, Rules Change Later
**What goes wrong:** Booking flagged as APR at prebook, but DOTW returns different rules at confirm → no reminder sent.
**Why it happens:** API response structure changed; developer assumed it's static.
**How to avoid:** Recheck `is_apr` flag at confirm time; recompute from DOTW cancellation rules if they differ.
**Warning signs:** Some APR bookings get invoices, others get reminder cycle.

### Pitfall 5: Webhook Dispatch Fails Silently
**What goes wrong:** Webhook event never reaches n8n; no retry happens; automation never triggered.
**Why it happens:** Queue job exception logged but job removed from queue (no retry logic in failed() hook).
**How to avoid:** Use $tries + $backoff array; implement failed() hook to log dead letter queue.
**Warning signs:** n8n workflows never see payment_completed events.

### Pitfall 6: Global Scope on Account/JournalEntry in Queue Context
**What goes wrong:** Job runs in queue worker; Auth::user() is null; withoutGlobalScopes() query fails silently.
**Why it happens:** Global scopes assume auth context; queue jobs don't have user context.
**How to avoid:** All Account/JournalEntry queries in jobs use withoutGlobalScopes() + explicit company_id (already done in Phase 20).
**Warning signs:** Accounting entries mysteriously missing for deadline-pass bookings created via queue.

## Code Examples

Verified patterns from official sources:

### Laravel 11 Scheduler Registration (Kernel.php)
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Daily deadline processor
    $schedule->command('dotwai:process-deadlines')
        ->dailyAt('03:00')  // Run at 3 AM daily
        ->withoutOverlapping()
        ->runInBackground();
}
```

**Source:** [Laravel 11 Task Scheduling docs](https://laravel.com/docs/11.x/scheduling)

### Queue Job with Exponential Backoff
```php
// app/Modules/DotwAI/Jobs/SendReminderJob.php
class SendReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;  // Retry up to 3 times
    public array $backoff = [30, 120];  // 30s, then 2m
    public int $timeout = 10;

    public function __construct(
        private int $bookingId,
    ) {}

    public function handle(): void
    {
        $booking = DotwAIBooking::find($this->bookingId);
        if (!$booking || !empty($booking->reminder_sent_at)) {
            return;  // Already processed or deleted
        }

        // Send reminder via WhatsApp
        $message = MessageBuilderService::formatReminderMessage($booking);
        app(\App\Http\Controllers\WhatsappController::class)
            ->sendToResayil($booking->client_phone ?? $booking->agent_phone, $message);

        $booking->update(['reminder_sent_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[DotwAI] Reminder job failed after retries', [
            'booking_id' => $this->bookingId,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

**Source:** [Laravel 11 Queues documentation](https://laravel.com/docs/11.x/queues); ConfirmBookingAfterPaymentJob in codebase.

### Fire-and-Forget Webhook Dispatch
```php
// app/Modules/DotwAI/Jobs/WebhookDispatchJob.php
class WebhookDispatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 4;
    public array $backoff = [30, 120, 300, 600];  // Exponential
    public int $timeout = 10;

    public function __construct(
        private array $payload,
    ) {}

    public function handle(): void
    {
        $url = config('dotwai.webhook_url');
        if (empty($url)) {
            return;  // Webhook not configured
        }

        try {
            Http::timeout(5)->post($url, [
                'event' => $this->payload['event'],
                'timestamp' => now()->toIso8601String(),
                'data' => $this->payload['data'] ?? [],
            ]);

            Log::info('[DotwAI] Webhook dispatched', ['event' => $this->payload['event']]);
        } catch (Throwable $e) {
            Log::warning('[DotwAI] Webhook failed', ['error' => $e->getMessage()]);
            throw $e;  // Trigger retry
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[DotwAI] Webhook dispatch dead-lettered', [
            'payload' => $this->payload,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

**Source:** [Laravel HTTP Client docs](https://laravel.com/docs/11.x/http-client); [Laravel Queue Retries](https://laravel.com/docs/11.x/queues#dealing-with-failed-jobs).

### Message Formatting for Reminders
```php
// In MessageBuilderService (extend existing class)
public static function formatReminderMessage(DotwAIBooking $booking): string
{
    $daysLeft = now()->diffInDays($booking->cancellation_deadline, false);
    $penalty = $booking->cancellation_rules[0]['penalty'] ?? 'TBD';

    $lines = [];
    $lines[] = "⏰ تذكير من سياحتك | Booking Reminder";
    $lines[] = "──────────────────────────";
    $lines[] = "الفندق | Hotel: " . $booking->hotel_name;
    $lines[] = "التواريخ | Dates: " . $booking->check_in . " to " . $booking->check_out;
    $lines[] = "آخر موعد إلغاء | Cancellation Deadline: " . $booking->cancellation_deadline->format('Y-m-d');
    $lines[] = "الأيام المتبقية | Days Left: " . abs($daysLeft) . " يوم | days";

    if ($daysLeft > 0) {
        $lines[] = "الغرامة الحالية | Current Penalty: " . $penalty;
        $lines[] = "";
        $lines[] = "إلغاء الآن لتجنب الرسوم | Cancel now to avoid charges";
    } else {
        $lines[] = "تم تأكيد حجزك | Your booking is confirmed";
    }

    return implode("\n", $lines);
}
```

**Source:** MessageBuilderService pattern in codebase; [WhatsApp message limits](https://support.wati.io/en/articles/11463458-whatsapp-template-message-guidelines-naming-formatting-and-translations).

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Manual reminder emails sent by admin | Automated WhatsApp reminders via scheduler | Phase 21 | No human intervention needed; higher delivery rates |
| Invoicing only after customer action | Auto-invoice on deadline pass | Phase 21 | Captures revenue before customer contact |
| Single invoice for whole stay | Separate invoices for penalty vs confirmation | Phase 20+ | Better accounting visibility |
| No webhook events to n8n | Async event dispatch with retries | Phase 21 | n8n can trigger post-booking workflows |

**Deprecated/outdated:**
- Manual deadline tracking in spreadsheets: replaced by DotwAIBooking::cancellation_deadline field.
- Synchronous DOTW calls in request handlers: queue jobs handle this since Phase 19.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.x via Laravel Pest/Testbench |
| Config file | phpunit.xml (existing, covers tests/Unit and tests/Feature) |
| Quick run command | `php artisan test tests/Unit/DotwAI/` (< 30 seconds) |
| Full suite command | `php artisan test tests/Unit/DotwAI/ tests/Feature/Modules/DotwAI/` (< 2 minutes) |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| LIFE-01 | Deadline field populated from DOTW response | Unit | `php artisan test tests/Unit/DotwAI/BookingServiceTest.php::test_prebook_stores_deadline -x` | ✅ Existing |
| LIFE-02 | Scheduler finds bookings due for reminder (3/2/1 day window) | Unit | `php artisan test tests/Unit/DotwAI/ProcessDeadlinesCommandTest.php::test_finds_reminders_due -x` | ❌ Wave 0 |
| LIFE-02 | Reminder job sends WhatsApp message | Feature | `php artisan test tests/Feature/Modules/DotwAI/SendReminderJobTest.php::test_sends_whatsapp_reminder -x` | ❌ Wave 0 |
| LIFE-03 | Auto-invoice after deadline pass creates Invoice + JournalEntry | Unit | `php artisan test tests/Unit/DotwAI/AutoInvoiceDeadlineJobTest.php::test_creates_invoice_and_entries -x` | ❌ Wave 0 |
| LIFE-04 | APR booking auto-invoiced on confirmation (no reminders) | Feature | `php artisan test tests/Feature/Modules/DotwAI/APRAutoInvoiceTest.php::test_apr_invoiced_at_confirm -x` | ❌ Wave 0 |
| LIFE-05 | ProcessDeadlinesCommand dispatches jobs to queue | Unit | `php artisan test tests/Unit/DotwAI/ProcessDeadlinesCommandTest.php::test_dispatches_queued_jobs -x` | ❌ Wave 0 |
| HIST-01 | booking_status endpoint returns deadline + policy + penalty | Feature | `php artisan test tests/Feature/Modules/DotwAI/BookingStatusEndpointTest.php::test_returns_status -x` | ❌ Wave 0 |
| HIST-02 | booking_history endpoint lists bookings with filters | Feature | `php artisan test tests/Feature/Modules/DotwAI/BookingHistoryEndpointTest.php::test_filters_by_status -x` | ❌ Wave 0 |
| HIST-03 | resend_voucher endpoint calls VoucherService | Feature | `php artisan test tests/Feature/Modules/DotwAI/ResendVoucherEndpointTest.php::test_resends_via_whatsapp -x` | ❌ Wave 0 |
| HIST-04 | Voucher retrieves from DOTW, fallback to local text | Unit | `php artisan test tests/Unit/DotwAI/VoucherServiceTest.php::test_dotw_pdf_fallback -x` | ❌ Wave 0 |
| EVNT-01 | Webhook event dispatched with retry logic | Unit | `php artisan test tests/Unit/DotwAI/WebhookDispatchJobTest.php::test_retry_on_http_error -x` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test tests/Unit/DotwAI/ProcessDeadlinesCommandTest.php --filter test_finds_reminders_due`
- **Per wave merge:** `php artisan test tests/Unit/DotwAI/ tests/Feature/Modules/DotwAI/`
- **Phase gate:** Full suite green + manual webhook event verification via n8n logs before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Unit/DotwAI/ProcessDeadlinesCommandTest.php` — covers LIFE-02, LIFE-05
- [ ] `tests/Feature/Modules/DotwAI/SendReminderJobTest.php` — covers LIFE-02 job dispatch
- [ ] `tests/Unit/DotwAI/AutoInvoiceDeadlineJobTest.php` — covers LIFE-03
- [ ] `tests/Feature/Modules/DotwAI/APRAutoInvoiceTest.php` — covers LIFE-04
- [ ] `tests/Feature/Modules/DotwAI/BookingStatusEndpointTest.php` — covers HIST-01
- [ ] `tests/Feature/Modules/DotwAI/BookingHistoryEndpointTest.php` — covers HIST-02
- [ ] `tests/Feature/Modules/DotwAI/ResendVoucherEndpointTest.php` — covers HIST-03
- [ ] `tests/Unit/DotwAI/WebhookDispatchJobTest.php` — covers EVNT-01 with mock Http client
- [ ] Framework install: None — PHPUnit already in vendor/ from existing setup

## Sources

### Primary (HIGH confidence)
- [Laravel 11 Task Scheduling](https://laravel.com/docs/11.x/scheduling) — artisan command scheduling, frequency methods, withoutOverlapping
- [Laravel 11 Queues](https://laravel.com/docs/11.x/queues) — ShouldQueue, $tries, $backoff array, timeout, failed() hook, SerializesModels
- [Laravel 11 HTTP Client](https://laravel.com/docs/11.x/http-client) — timeout(), post(), exception handling
- CLAUDE.md project instructions — WhatsApp via Resayil, Queue pattern, MessageBuilderService pattern
- Existing codebase patterns — ConfirmBookingAfterPaymentJob, AccountingService, VoucherService, MessageBuilderService

### Secondary (MEDIUM confidence)
- [Laravel Queue Retries Best Practices](https://medium.com/insiderengineering/optimize-your-laravel-applications-performance-effective-strategies-for-implementing-job-retries-384da22e3a50) — exponential backoff, retry_after vs timeout relationship
- [WhatsApp Message Formatting Guidelines](https://support.wati.io/en/articles/11463458-whatsapp-template-message-guidelines-naming-formatting-and-translations) — character limits (65536 for plain text, 2048 for captions), formatting best practices
- [Laravel Webhook Patterns](https://www.juststeveking.com/articles/the-definitive-guide-to-webhooks-in-laravel/) — fire-and-forget via queue jobs, signature verification (not needed here)

## Metadata

**Confidence breakdown:**
- **Standard stack:** HIGH — Laravel 11 scheduler, queues, HTTP client are core frameworks with stable APIs
- **Architecture:** HIGH — Patterns (scheduler → queue → service) established in codebase; extends Phase 20 services directly
- **Pitfalls:** HIGH — Based on common distributed system issues; specific to scheduler/queue context
- **APR detection:** MEDIUM — Business logic inferred from context; needs Phase 21 planning to finalize exact rule
- **Webhook payload structure:** MEDIUM — n8n event shape not documented in codebase; planner will define exact fields

**Research date:** 2026-03-25
**Valid until:** 2026-04-25 (30 days; Laravel 11 stable, no major changes expected)

**Caveats:**
- APR detection logic refinement deferred to planning phase (exact field/rule to check)
- Webhook event payload structure (which fields per event type) deferred to planning
- Scheduler frequency (daily vs 6-hourly) is discretion item; recommend daily for lower webhook load
