<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Task;
use App\Models\JournalEntry;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessVoidTasksFinancials extends Command
{
    protected $signature = 'void-tasks:process-financials 
                           {--dry-run : Show what would be processed without making changes}
                           {--limit=100 : Limit number of tasks to process}
                           {--company-id= : Process only tasks for specific company}';

    protected $description = 'Process financial transactions for void tasks that haven\'t been processed yet';

    private $taskController;

    public function __construct()
    {
        parent::__construct();
        $this->taskController = new TaskController();
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

        // Find void tasks that haven't been processed financially
        // A void task is considered processed if there's a Transaction with description 
        // "Void reversal for: {original_task_reference}"
        $voidTasks = Task::where('status', 'void')
            ->whereNotNull('original_task_id')
            ->with('originalTask')
            ->get()
            ->filter(function($voidTask) {
                if (!$voidTask->originalTask) {
                    return false; // Skip if no original task
                }
                
                // Check if there's already a void reversal transaction for this original task
                $hasVoidReversal = \App\Models\Transaction::where('description', 'like', 'Void reversal for: ' . $voidTask->originalTask->reference)
                    ->exists();
                    
                return !$hasVoidReversal;
            });

        if ($companyId) {
            $voidTasks = $voidTasks->where('company_id', $companyId);
        }

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
                    // Use reflection to access the private method
                    $reflection = new \ReflectionClass($this->taskController);
                    $method = $reflection->getMethod('processVoidTask');
                    $method->setAccessible(true);

                    // Get branch ID using the same logic as TaskController
                    $branchId = $this->getTaskBranchId($task);
                    
                    $method->invoke($this->taskController, $task, $branchId);

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

    /**
     * Get branch ID for task financial processing
     * Returns agent's branch_id if agent exists, otherwise returns company's main branch_id
     */
    private function getTaskBranchId(Task $task)
    {
        if ($task->agent && $task->agent->branch_id) {
            return $task->agent->branch_id;
        }
        
        // Get company's main branch if no agent
        $company = \App\Models\Company::find($task->company_id);
        if (!$company) {
            throw new \Exception('Company not found for task: ' . $task->reference);
        }
        
        $mainBranch = $company->getMainBranch();
        return $mainBranch->id;
    }
}
