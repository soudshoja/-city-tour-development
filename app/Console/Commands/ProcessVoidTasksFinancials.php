<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Task;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Services\Accounting\PostingSeam;
use App\Services\TaskStatusService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * W6.S item (1) (w6-brief.md "Consolidation + fixes" -- "... ProcessVoidTasksFinancials ...
 * TaskStatusService"). Financial dispatch routes through TaskStatusService::dispatchFinancial() --
 * that method itself now routes a `void`-status task through the engine
 * ({@see TaskStatusService::void()}) when ON (W6.V), falling through to the unchanged
 * processTaskFinancial() switch only when OFF.
 *
 * W6.V FIX (ct-void-map.md §7 bug 7, flagged by W6.S for this sub-wave, now fixable since the void
 * kind actually posts through the engine and has a real idempotency key to check):
 *   - Idempotency, engine ON: checked STRUCTURALLY -- a `transactions.idempotency_key` lookup for
 *     the original ticket's own reversal-of-sale, never the broken description-LIKE probe
 *     ("Void reversal for: ..." vs. the field actually written, "Void reversal: ...").
 *   - Idempotency, engine OFF: the legacy description-LIKE probe is preserved BYTE-FOR-BYTE
 *     (OFF-path parity is the hard rule here, not a second bug fix) -- still broken on OFF, exactly
 *     as at HEAD, not this sub-wave's problem to fix twice.
 *   - `--company-id` filter: applied INSIDE the query now (`->where('company_id', ...)`), not
 *     in-memory after `->get()`.
 */
class ProcessVoidTasksFinancials extends Command
{
    protected $signature = 'void-tasks:process-financials
                           {--dry-run : Show what would be processed without making changes}
                           {--limit=100 : Limit number of tasks to process}
                           {--company-id= : Process only tasks for specific company}';

    protected $description = 'Process financial transactions for void tasks that haven\'t been processed yet';

    private $taskStatusService;

    public function __construct(TaskStatusService $taskStatusService)
    {
        parent::__construct();
        $this->taskStatusService = $taskStatusService;
    }

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $companyId = $this->option('company-id');

        $this->info("=== Void Tasks Financial Processing ===");
        $this->info("Mode: " . ($isDryRun ? "DRY RUN" : "LIVE PROCESSING"));
        $this->info("Limit: {$limit} tasks");
        if ($companyId) {
            $this->info("Company ID: {$companyId}");
        }
        $this->line("");

        // Find void tasks that haven't been processed financially.
        // W6.V fix: --company-id filtered IN the query (was in-memory, ct-void-map.md §7 note).
        $query = Task::where('status', 'void')
            ->whereNotNull('original_task_id')
            ->with('originalTask');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $voidTasks = $query->get()
            ->filter(function ($voidTask) {
                if (!$voidTask->originalTask) {
                    return false; // Skip if no original task
                }

                $originalCompanyId = (int) $voidTask->originalTask->company_id;

                // W6.V fix (ct-void-map.md §7 bug 7): engine ON checks the REAL, structural
                // idempotency signal -- has the original ticket's own sale document already been
                // reversed? -- instead of the broken description-LIKE probe below (which searches
                // for "Void reversal for: ..." when the field actually written is "Void reversal:
                // ..." with no "for", so it never matched anything and every run reprocessed every
                // void task). Engine OFF preserves the legacy probe byte-for-byte (parity, not a
                // second fix).
                if (app(PostingSeam::class)->isEnabledFor($originalCompanyId)) {
                    $originalInvoiceDetail = \App\Models\InvoiceDetail::where('task_id', $voidTask->originalTask->id)->first();
                    if ($originalInvoiceDetail === null) {
                        return true; // nothing to check against -- let void() itself decide/refuse.
                    }

                    $saleKey = 'invoice-detail:' . $originalInvoiceDetail->id . ':sale';
                    $saleTransaction = Transaction::withoutGlobalScopes()
                        ->whereNull('deleted_at')
                        ->where('company_id', $originalCompanyId)
                        ->where('idempotency_key', $saleKey)
                        ->first();

                    if ($saleTransaction === null) {
                        return true; // no engine-posted sale to have reversed -- let void() decide.
                    }

                    $alreadyReversed = Transaction::withoutGlobalScopes()
                        ->whereNull('deleted_at')
                        ->where('reversal_of_transaction_id', $saleTransaction->id)
                        ->exists();

                    return !$alreadyReversed;
                }

                // Legacy OFF-path probe, preserved byte-for-byte.
                $hasVoidReversal = Transaction::where('description', 'like', 'Void reversal for: ' . $voidTask->originalTask->reference)
                    ->exists();

                return !$hasVoidReversal;
            });

        $voidTasks = $voidTasks->take($limit);

        $this->info("Found " . $voidTasks->count() . " unprocessed void tasks");
        $this->line("");

        if ($voidTasks->isEmpty()) {
            $this->info("No void tasks found that need financial processing.");
            return;
        }

        $processedCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        $this->output->progressStart($voidTasks->count());

        foreach ($voidTasks as $task) {
            $this->output->progressAdvance();

            try {
                // Check if original task exists
                $originalTask = Task::find($task->original_task_id);
                if (!$originalTask) {
                    $this->warn("Skipping task {$task->reference}: Original task not found (ID: {$task->original_task_id})");
                    $skippedCount++;
                    continue;
                }

                // Check if original task has financial entries
                $originalHasEntries = JournalEntry::where('task_id', $originalTask->id)->exists();
                if (!$originalHasEntries) {
                    $this->warn("Skipping task {$task->reference}: Original task {$originalTask->reference} has no journal entries to reverse");
                    $skippedCount++;
                    continue;
                }

                if ($isDryRun) {
                    $this->line("Would process: {$task->reference} (Original: {$originalTask->reference})");
                    $processedCount++;
                    continue;
                }

                // Process the void task financials
                DB::beginTransaction();

                try {
                    // W6.S item (1): single financial-dispatch call site -- no more reflection
                    // into TaskController's private processVoidTask().
                    $this->taskStatusService->dispatchFinancial($task);

                    DB::commit();
                    $processedCount++;

                    $this->line("✓ Processed: {$task->reference}");

                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }

            } catch (\Exception $e) {
                $this->error("Error processing task {$task->reference}: " . $e->getMessage());
                Log::error("Void task financial processing error", [
                    'task_id' => $task->id,
                    'task_reference' => $task->reference,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $errorCount++;
            }
        }

        $this->output->progressFinish();
        $this->line("");

        // Summary
        $this->info("=== Processing Summary ===");
        $this->info("Total void tasks found: " . $voidTasks->count());
        $this->info("Successfully processed: {$processedCount}");
        $this->info("Skipped (no original/entries): {$skippedCount}");
        $this->info("Errors: {$errorCount}");

        if ($isDryRun) {
            $this->line("");
            $this->info("This was a dry run. Run without --dry-run to actually process the tasks.");
        }

        return 0;
    }
}
