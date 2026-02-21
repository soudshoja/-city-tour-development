# Phase 3: Background Invoice Creation - Research

**Researched:** 2026-02-13
**Domain:** Laravel 11 Queue Jobs, Database Transactions, Race Condition Prevention
**Confidence:** HIGH

## Summary

Phase 3 implements atomic, race-condition-free invoice creation from approved bulk uploads. The core challenge is ensuring all invoices within an upload succeed or fail together, while preventing duplicate invoice numbers even under concurrent uploads from multiple agents.

Laravel 11 provides robust primitives for this use case: **queue jobs** for background processing, **DB::transaction()** for atomic operations, **lockForUpdate()** for pessimistic locking on the invoice sequence table, and **structured logging** for detailed error tracking. The existing codebase already has the foundation: InvoiceSequence table with company_id uniqueness, InvoiceController with proven invoice creation logic, and BulkUpload/BulkUploadRow models ready to track invoice IDs.

**Primary recommendation:** Use a queued job with DB::transaction() wrapping all invoice creation, lockForUpdate() on invoice_sequence during number generation, and batch updates to link created invoice IDs back to BulkUpload. This ensures atomicity, prevents race conditions, and provides comprehensive audit trail through Laravel's failed_jobs table and structured logging.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Queue | 11.x | Background job processing | Built-in, battle-tested async processing with retry logic |
| Laravel DB Facade | 11.x | Database transactions and locking | Native transaction support with automatic rollback on exceptions |
| Laravel Log Facade | 11.x | Structured logging with context | Framework standard for error tracking with contextual data |
| Laravel Bus Facade | 11.x | Job chaining and batching | Advanced queue orchestration for complex workflows |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Laravel Horizon | Latest | Queue monitoring dashboard | Production monitoring of job throughput and failures |
| Monolog | Bundled with Laravel | Advanced logging handlers | Custom log channels, Slack notifications on failures |
| Laravel Context | 11.x | Request-scoped metadata | Tracking upload_id across all log entries in job execution |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Queue jobs | Synchronous processing | Simpler but blocks HTTP request, poor UX for large uploads |
| DB::transaction() | Manual BEGIN/COMMIT | More control but must handle rollback/savepoints manually |
| lockForUpdate() | Optimistic locking (version column) | Lower contention but requires retry logic on collision |

**Installation:**
```bash
# Queue system already configured in Laravel 11
# For Horizon (optional production monitoring):
composer require laravel/horizon
php artisan horizon:install
```

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Jobs/
│   └── CreateBulkInvoicesJob.php      # Main queue job
├── Services/
│   ├── InvoiceNumberGenerator.php     # Atomic sequence increment
│   └── BulkInvoiceCreationService.php # Invoice creation orchestration (optional)
└── Http/Controllers/
    └── BulkInvoiceController.php      # Dispatch job from approve()
```

### Pattern 1: Queue Job with Nested Transaction
**What:** Dispatch background job from approve() action, wrap all invoice creation in DB::transaction()
**When to use:** When multiple database operations must succeed/fail atomically
**Example:**
```php
// In BulkInvoiceController::approve()
CreateBulkInvoicesJob::dispatch($bulkUploadId)
    ->onQueue('invoices')
    ->afterCommit();  // Wait until status='processing' is committed

// In CreateBulkInvoicesJob::handle()
DB::transaction(function () use ($bulkUpload) {
    $invoiceIds = [];
    foreach ($invoiceGroups as $group) {
        $invoiceNumber = $this->generateInvoiceNumber($companyId);
        $invoice = Invoice::create([...]);
        foreach ($group->rows as $row) {
            InvoiceDetail::create([
                'invoice_id' => $invoice->id,
                'task_id' => $row->task_id,
                // ...
            ]);
        }
        $invoiceIds[] = $invoice->id;
    }

    // Update upload with invoice IDs (audit trail)
    $bulkUpload->update([
        'status' => 'completed',
        'invoice_ids' => json_encode($invoiceIds),
    ]);
});
```
**Source:** [Laravel 11 Queues Documentation](https://laravel.com/docs/11.x/queues), [Laravel 12 Database Transactions](https://laravel.com/docs/12.x/database)

### Pattern 2: Pessimistic Locking for Invoice Number Generation
**What:** Use lockForUpdate() to lock invoice_sequence row during read-increment-write cycle
**When to use:** Preventing duplicate sequence numbers under concurrent access
**Example:**
```php
protected function generateInvoiceNumber($companyId): string
{
    // Lock sequence row to prevent race conditions
    $sequence = InvoiceSequence::where('company_id', $companyId)
        ->lockForUpdate()
        ->first();

    if (!$sequence) {
        $sequence = InvoiceSequence::create([
            'company_id' => $companyId,
            'current_sequence' => 1
        ]);
    }

    $invoiceNumber = sprintf('INV-%s-%05d', now()->year, $sequence->current_sequence);
    $sequence->increment('current_sequence');

    return $invoiceNumber;
}
```
**Source:** [Handling Race Conditions in Laravel: Pessimistic Locking](https://ohansyah.medium.com/handling-race-conditions-in-laravel-pessimistic-locking-d88086433154), [Laravel Pessimistic Locking Guide](https://arjunamrutiya.medium.com/laravel-pessimistic-locking-easy-guide-with-examples-d7e8da8fd108)

### Pattern 3: Prevent Duplicate Task Invoicing
**What:** Check InvoiceDetail for existing task_id before creating new invoice detail
**When to use:** Ensuring tasks aren't invoiced multiple times across uploads
**Example:**
```php
// Before creating InvoiceDetail for each row
foreach ($validRows as $row) {
    // Check if task already invoiced
    $existingDetail = InvoiceDetail::where('task_id', $row->task_id)
        ->whereHas('invoice', fn($q) => $q->whereNotIn('status', ['void', 'cancelled']))
        ->first();

    if ($existingDetail) {
        // Mark row as error, continue to next
        $row->update([
            'status' => 'error',
            'errors' => ['Task already invoiced in invoice #' . $existingDetail->invoice_number]
        ]);
        continue;
    }

    // Safe to create InvoiceDetail
    InvoiceDetail::create([...]);
}
```
**Source:** Derived from existing codebase patterns (InvoiceDetail has task_id foreign key)

### Pattern 4: Job Failure Handling with Detailed Logging
**What:** Implement failed() method on job, use Log::withContext() for structured error tracking
**When to use:** Debugging failed invoice creations, providing actionable error info
**Example:**
```php
class CreateBulkInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60]; // Exponential backoff in seconds

    public function handle(): void
    {
        Log::withContext([
            'bulk_upload_id' => $this->bulkUploadId,
            'company_id' => $this->companyId,
        ]);

        Log::info('Starting bulk invoice creation', [
            'upload_filename' => $bulkUpload->original_filename,
            'valid_rows' => $bulkUpload->valid_rows,
        ]);

        try {
            DB::transaction(function () { /* ... */ });
        } catch (\Exception $e) {
            Log::error('Invoice creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // Re-throw to trigger retry
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Bulk invoice creation permanently failed', [
            'bulk_upload_id' => $this->bulkUploadId,
            'exception' => $exception->getMessage(),
        ]);

        // Update BulkUpload to failed status with error message
        BulkUpload::where('id', $this->bulkUploadId)->update([
            'status' => 'failed',
            'error_summary' => [
                'job_failure' => $exception->getMessage(),
                'failed_at' => now()->toDateTimeString(),
            ],
        ]);
    }
}
```
**Source:** [Laravel 12 Queues - Job Failure](https://laravel.com/docs/12.x/queues), [Laravel 11 Logging with Context](https://laravel.com/docs/11.x/logging), [How to Handle Failed Jobs in Laravel Queue](https://salehmegawer.com/en/blog/laravel-queue-failed-jobs-handling-4)

### Anti-Patterns to Avoid
- **Dispatching job inside transaction:** Can lead to job executing before transaction commits. Use `->afterCommit()` method on job dispatch.
- **Reading sequence without locking:** Race condition allows duplicate numbers. Always use `lockForUpdate()` on InvoiceSequence.
- **Silent failure in transaction:** If any operation fails silently (no exception), transaction commits partial data. Always throw exceptions on validation failures.
- **Forgetting to update BulkUpload status:** Leaves upload stuck in 'processing'. Always update status in both success and failure paths.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Sequence number generation | Custom atomic increment logic | lockForUpdate() + Eloquent increment() | Database-level lock prevents race conditions, tested by millions of Laravel apps |
| Job retry logic | Manual retry loop with exponential backoff | Laravel queue $tries, $backoff properties | Framework handles retry scheduling, failed job tracking, exponential delays automatically |
| Transaction rollback | try/catch with manual rollback() | DB::transaction(Closure) | Auto-rollback on exception, supports nested transactions via savepoints, cleaner code |
| Logging context | Manual array building per log call | Log::withContext() or Context facade | Automatically appends to all subsequent logs, persists across queue jobs |
| Invoice number uniqueness | Application-level mutex/lock file | Database unique constraint on invoice_number | Database enforces at data layer, survives app crashes, multi-server safe |

**Key insight:** Laravel 11's queue and transaction primitives are battle-tested under high concurrency. Edge cases like deadlocks, connection drops during transactions, and job serialization failures are already handled. Custom solutions will inevitably rediscover these problems.

## Common Pitfalls

### Pitfall 1: Job Dispatched Before Transaction Commits
**What goes wrong:** Job starts processing before BulkUpload status='processing' is committed, finds status still 'validated', logic breaks
**Why it happens:** Default behavior dispatches jobs immediately, even if inside transaction
**How to avoid:** Use `->afterCommit()` on job dispatch, or enable `after_commit` in queue config
**Warning signs:** Intermittent "upload not found" errors in job logs, race conditions in tests
**Source:** [Laravel: Don't Queue Jobs Inside of a Transaction](https://patriqueouimet.ca/post/laravel-dont-queue-jobs-inside-of-a-transaction)

### Pitfall 2: Deadlock from Multiple Concurrent Uploads
**What goes wrong:** Two jobs lock invoice_sequence for different companies, then try to insert invoices (which may lock invoice table rows), circular wait causes deadlock
**Why it happens:** MySQL/PostgreSQL detect deadlock, kill one transaction with "Deadlock detected" error
**How to avoid:** Lock tables in consistent order (sequence first, then invoice, then details), keep transaction duration short
**Warning signs:** "Deadlock found when trying to get lock" in logs, random job failures under load
**Source:** [Preventing Data Races with Pessimistic Locking in Laravel](https://medium.com/@harrisrafto/preventing-data-races-with-pessimistic-locking-in-laravel-549596051457)

### Pitfall 3: Large Transaction Timeout
**What goes wrong:** Creating 100+ invoices in single transaction exceeds default 60s job timeout, job killed mid-transaction
**Why it happens:** Each invoice involves multiple inserts (Invoice, InvoiceDetail per task), locks accumulate
**How to avoid:** Set higher timeout on job (`public $timeout = 300;`), or chunk invoice creation into batches with Bus::chain()
**Warning signs:** Jobs timing out for large uploads, "Maximum execution time exceeded" in logs
**Source:** [Laravel 11 Queues - Job Timeout](https://laravel.com/docs/11.x/queues)

### Pitfall 4: Forgetting to Revert BulkUpload on Transaction Rollback
**What goes wrong:** Transaction rolls back invoice creation, but BulkUpload.status stays 'processing', upload stuck forever
**Why it happens:** Status update inside transaction gets rolled back too, but job doesn't retry
**How to avoid:** Update status to 'failed' in failed() method (outside transaction), or use separate transaction for final status update
**Warning signs:** BulkUploads stuck in 'processing', invoices missing but no error reported
**Source:** Derived from Laravel transaction behavior patterns

### Pitfall 5: Invoice Number Gaps on Rollback
**What goes wrong:** Sequence incremented, transaction rolls back, invoice number never used (gap in numbering)
**Why it happens:** Incrementing sequence inside transaction that later fails wastes numbers
**How to avoid:** Accept this as trade-off for atomicity (gaps are harmless), or use database sequences/auto-increment (not portable)
**Warning signs:** Missing invoice numbers in sequence (INV-2026-00001, INV-2026-00003, missing 00002)
**Source:** [Laravel Invoices: Auto-Generate Serial Numbers](https://laraveldaily.com/post/laravel-invoices-auto-generate-serial-numbers), [Create Guaranteed Unique Invoice Number in Laravel](https://talltips.novate.co.uk/laravel/create-guaranteed-unique-invoice-number-in-laravel)

## Code Examples

Verified patterns from official sources and existing codebase:

### Dispatching Job from Controller
```php
// In BulkInvoiceController::approve()
public function approve(int $id): RedirectResponse
{
    $user = Auth::user();
    $companyId = getCompanyId($user);

    // Conditional update to prevent race conditions (ALREADY IMPLEMENTED)
    $updated = BulkUpload::where('id', $id)
        ->where('company_id', $companyId)
        ->where('status', 'validated')
        ->update(['status' => 'processing']);

    if ($updated === 0) {
        return redirect()->back()->withErrors(['error' => 'Upload already processed.']);
    }

    // Dispatch job AFTER status update is committed
    CreateBulkInvoicesJob::dispatch($id)
        ->onQueue('invoices')
        ->afterCommit();

    return redirect()->route('bulk-invoices.success', $id)
        ->with('message', 'Invoices are being created in the background.');
}
```
**Source:** Existing BulkInvoiceController::approve() pattern + Laravel Queue best practices

### Job Structure with Transaction and Grouping
```php
namespace App\Jobs;

use App\Models\BulkUpload;
use App\Models\BulkUploadRow;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoiceSequence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateBulkInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];
    public $timeout = 300; // 5 minutes for large uploads

    public function __construct(
        public int $bulkUploadId
    ) {}

    public function handle(): void
    {
        $bulkUpload = BulkUpload::with('rows.client', 'rows.supplier', 'rows.task')
            ->findOrFail($this->bulkUploadId);

        Log::withContext(['bulk_upload_id' => $this->bulkUploadId]);
        Log::info('Starting bulk invoice creation', [
            'filename' => $bulkUpload->original_filename,
            'valid_rows' => $bulkUpload->valid_rows,
        ]);

        $invoiceIds = [];

        DB::transaction(function () use ($bulkUpload, &$invoiceIds) {
            // Group valid rows by composite key (SAME LOGIC AS PREVIEW)
            $validRows = $bulkUpload->rows()->where('status', 'valid')->get();

            $invoiceGroups = $validRows->groupBy(function ($row) {
                $clientId = $row->client_id;
                $invoiceDate = $row->raw_data['invoice_date'] ?? date('Y-m-d');
                return "{$clientId}_{$invoiceDate}";
            });

            foreach ($invoiceGroups as $compositeKey => $rows) {
                [$clientId, $invoiceDate] = explode('_', $compositeKey);

                // Generate invoice number with pessimistic lock
                $invoiceNumber = $this->generateInvoiceNumber($bulkUpload->company_id);

                // Get first row for shared metadata
                $firstRow = $rows->first();
                $client = $firstRow->client;
                $agent = $bulkUpload->agent;

                // Calculate totals
                $subTotal = $rows->sum(fn($r) => $r->raw_data['task_price'] ?? $r->task->total);

                // Create invoice (leverage existing Invoice model)
                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'client_id' => $clientId,
                    'agent_id' => $bulkUpload->agent_id,
                    'currency' => $firstRow->raw_data['currency'] ?? 'KWD',
                    'sub_amount' => $subTotal,
                    'amount' => $subTotal,
                    'invoice_date' => $invoiceDate,
                    'status' => 'issued',
                ]);

                // Create invoice details for each task
                foreach ($rows as $row) {
                    // Check if task already invoiced (prevent duplicates)
                    $existingDetail = InvoiceDetail::where('task_id', $row->task_id)
                        ->whereHas('invoice', fn($q) => $q->whereNotIn('status', ['void', 'cancelled']))
                        ->first();

                    if ($existingDetail) {
                        throw new \Exception("Task {$row->task_id} already invoiced in {$existingDetail->invoice_number}");
                    }

                    InvoiceDetail::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoiceNumber,
                        'task_id' => $row->task_id,
                        'task_description' => $row->raw_data['task_description'] ?? $row->task->reference,
                        'task_price' => $row->raw_data['task_price'] ?? $row->task->total,
                        'supplier_price' => $row->task->price ?? 0,
                    ]);
                }

                $invoiceIds[] = $invoice->id;

                Log::info('Invoice created', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoiceNumber,
                    'client_id' => $clientId,
                    'task_count' => $rows->count(),
                ]);
            }

            // Update BulkUpload with invoice IDs (audit trail)
            $bulkUpload->update([
                'status' => 'completed',
                'invoice_ids' => json_encode($invoiceIds),
            ]);
        });

        Log::info('Bulk invoice creation completed', [
            'invoices_created' => count($invoiceIds),
            'invoice_ids' => $invoiceIds,
        ]);
    }

    protected function generateInvoiceNumber(int $companyId): string
    {
        // Lock invoice_sequence row to prevent race conditions
        $sequence = InvoiceSequence::where('company_id', $companyId)
            ->lockForUpdate()
            ->first();

        if (!$sequence) {
            $sequence = InvoiceSequence::create([
                'company_id' => $companyId,
                'current_sequence' => 1
            ]);
        }

        $year = now()->year;
        $invoiceNumber = sprintf('INV-%s-%05d', $year, $sequence->current_sequence);

        // Atomic increment
        $sequence->increment('current_sequence');

        return $invoiceNumber;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Bulk invoice creation permanently failed', [
            'bulk_upload_id' => $this->bulkUploadId,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Update status outside of transaction
        BulkUpload::where('id', $this->bulkUploadId)->update([
            'status' => 'failed',
            'error_summary' => [
                'error' => $exception->getMessage(),
                'failed_at' => now()->toDateTimeString(),
            ],
        ]);
    }
}
```
**Source:** Composite pattern from existing BulkInvoiceController::preview() + InvoiceController invoice creation logic + Laravel Queue job patterns

### Adding invoice_ids Column to BulkUpload (Migration)
```php
// database/migrations/2026_02_XX_add_invoice_ids_to_bulk_uploads_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulk_uploads', function (Blueprint $table) {
            $table->json('invoice_ids')->nullable()->after('error_summary');
        });
    }

    public function down(): void
    {
        Schema::table('bulk_uploads', function (Blueprint $table) {
            $table->dropColumn('invoice_ids');
        });
    }
};
```
**Source:** Standard Laravel migration pattern for JSON audit columns

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Read-increment-write without locking | lockForUpdate() + increment() | Laravel 5.x+ | Prevents duplicate invoice numbers under concurrent access |
| Jobs execute in transaction | Jobs dispatch with afterCommit() | Laravel 8.x (2020) | Prevents jobs executing before data is committed |
| Manual transaction rollback | DB::transaction(Closure) auto-rollback | Laravel 5.x+ | Cleaner code, impossible to forget rollback |
| Per-log context arrays | Log::withContext() / Context facade | Laravel 8.x / 11.x | Automatic context propagation across logs and queue jobs |
| Custom sequence tables with app locks | Database pessimistic locking | Industry standard | Database-level consistency, multi-server safe |

**Deprecated/outdated:**
- **Queue::push() syntax:** Use Job::dispatch() (since Laravel 5.2)
- **DB::beginTransaction() without auto-rollback:** Use DB::transaction(Closure) for safety
- **firstOrCreate() for sequences without locking:** Race condition, use lockForUpdate() + firstOrCreate() together

## Open Questions

1. **Should invoice creation be chunked for very large uploads (500+ rows)?**
   - What we know: Single transaction simplifies atomicity, but risks timeout for huge uploads
   - What's unclear: What's the practical upper limit before chunking becomes necessary?
   - Recommendation: Start with single transaction, add chunking with Bus::chain() if timeouts occur in production (measure first)

2. **Should duplicate task check happen at validation (Phase 1) or creation (Phase 3)?**
   - What we know: Phase 1 validation checks many things, Phase 3 checks before insert
   - What's unclear: Time window between validation and creation allows task to be invoiced elsewhere
   - Recommendation: Check in both places - Phase 1 for early feedback, Phase 3 as final guard (defense in depth)

3. **How to handle invoice number gaps from rollback?**
   - What we know: Gaps occur when sequence incremented but transaction rolls back
   - What's unclear: Is gap-less numbering a business requirement or just preference?
   - Recommendation: Accept gaps (standard practice), invoice number uniqueness matters more than sequential perfection

4. **Should job retry on duplicate task errors?**
   - What we know: Duplicate task is likely permanent error (task invoiced by another upload)
   - What's unclear: Could be transient if other invoice gets voided/cancelled
   - Recommendation: Don't retry on duplicate task error (mark job as failed, let admin resolve manually)

## Sources

### Primary (HIGH confidence)
- [Laravel 11 Queues Official Documentation](https://laravel.com/docs/11.x/queues) - Queue jobs, dispatching, failure handling
- [Laravel 12 Database Transactions Documentation](https://laravel.com/docs/12.x/database) - DB::transaction(), nested transactions
- [Laravel 11 Logging Documentation](https://laravel.com/docs/11.x/logging) - Log::withContext(), structured logging
- Existing codebase:
  - `app/Http/Controllers/InvoiceController.php` (lines 1279-1283: invoice number generation pattern)
  - `app/Http/Controllers/BulkInvoiceController.php` (lines 302-305: conditional update pattern, lines 275-280: grouping logic)
  - `app/Models/InvoiceSequence.php` (current_sequence structure)
  - `app/Models/Invoice.php`, `app/Models/InvoiceDetail.php` (relationships)

### Secondary (MEDIUM confidence)
- [Handling Race Conditions in Laravel: Pessimistic Locking](https://ohansyah.medium.com/handling-race-conditions-in-laravel-pessimistic-locking-d88086433154) - lockForUpdate() practical examples
- [Laravel Pessimistic Locking: Easy Guide with Examples](https://arjunamrutiya.medium.com/laravel-pessimistic-locking-easy-guide-with-examples-d7e8da8fd108) - Race condition prevention patterns
- [Create Guaranteed Unique Invoice Number in Laravel](https://talltips.novate.co.uk/laravel/create-guaranteed-unique-invoice-number-in-laravel) - Atomic sequence generation
- [Laravel: Don't Queue Jobs Inside of a Transaction](https://patriqueouimet.ca/post/laravel-dont-queue-jobs-inside-of-a-transaction) - afterCommit() necessity
- [How to Handle Failed Jobs in Laravel Queue](https://salehmegawer.com/en/blog/laravel-queue-failed-jobs-handling-4) - failed() method implementation
- [Laravel Invoices: Auto-Generate Serial Numbers - 4 Different Ways](https://laraveldaily.com/post/laravel-invoices-auto-generate-serial-numbers) - Sequence number generation approaches
- [Preventing Data Races with Pessimistic Locking in Laravel](https://medium.com/@harrisrafto/preventing-data-races-with-pessimistic-locking-in-laravel-549596051457) - Deadlock prevention
- [4 Ways To Prevent Race Conditions in Laravel](https://backpackforlaravel.com/articles/tutorials/4-ways-to-prevent-race-conditions-in-laravel) - Comprehensive race condition strategies
- [Laravel 11 Context Documentation](https://laravel.com/docs/11.x/context) - Request-scoped metadata
- [How to Configure Laravel Logging](https://oneuptime.com/blog/post/2026-02-03-laravel-logging/view) - Structured logging best practices

### Tertiary (LOW confidence)
- Community discussions on Laracasts about invoice numbering race conditions - Useful anecdotes but not authoritative
- Medium articles about queue job patterns - Informative but need official doc verification

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All components are Laravel 11 built-ins with official documentation
- Architecture: HIGH - Patterns derived from official docs + verified in existing codebase (InvoiceController)
- Pitfalls: MEDIUM-HIGH - Deadlock/timeout issues are well-documented, specific thresholds are deployment-dependent
- Code examples: HIGH - Composite of existing codebase patterns (BulkInvoiceController grouping logic) + official Laravel queue/transaction docs

**Research date:** 2026-02-13
**Valid until:** 60 days (Laravel 11 is stable release, queue/transaction patterns are mature and unlikely to change)
