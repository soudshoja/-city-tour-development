<?php

namespace App\Console\Commands;

use App\Events\CheckConfirmedOrIssuedTask;
use App\Models\Task;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessExpiredConfirmedTasks extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tasks:process-expired-confirmed {--dry-run : Run without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Process expired confirmed tasks and change their status to void';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('Running in dry-run mode - no changes will be made');
        }

        $now = Carbon::now();
        $this->info("Processing expired confirmed tasks at: {$now}");

        $suppliersToBeExecute = [
            'jazeera'
        ];

        // Get all confirmed tasks that have expired
        $expiredTasks = Task::where('status', 'confirmed')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', $now)
            ->whereHas('supplier', function ($query) use ($suppliersToBeExecute) {
                $query->where(function ($q) use ($suppliersToBeExecute) {
                    foreach ($suppliersToBeExecute as $supplier) {
                        $q->orWhere('name', 'like', '%' . $supplier . '%');
                    }
                });
            })
            ->whereDoesntHave('invoiceDetail')
            ->with(['agent.branch', 'client', 'supplier'])
            ->get();

        if ($expiredTasks->isEmpty()) {
            $this->info('No expired confirmed tasks found.');
            return 0;
        }

        $this->info("Found {$expiredTasks->count()} expired confirmed tasks to process");
        $this->info("Tasks from suppliers: " . $expiredTasks->pluck('supplier.name')->unique()->implode(', '));
        $this->line('');

        $processedCount = 0;
        $voidedCount = 0;
        $errorCount = 0;

        foreach ($expiredTasks as $task) {
            try {
                $this->processExpiredTask($task, $isDryRun);
                $processedCount++;
                
                if (!$isDryRun) {
                    $voidedCount++;
                }
            } catch (\Exception $e) {
                $errorCount++;
                Log::error("Failed to process expired task {$task->reference}: " . $e->getMessage(), [
                    'task_id' => $task->id,
                    'reference' => $task->reference,
                    'expiry_date' => $task->expiry_date,
                    'exception' => $e->getMessage()
                ]);
                $this->error("Error processing task {$task->reference}: " . $e->getMessage());
            }
        }

        // Summary
        $this->info("Processing complete:");
        $this->line("- Total processed: {$processedCount}");
        if (!$isDryRun) {
            $this->line("- Voided: {$voidedCount}");
        }
        $this->line("- Errors: {$errorCount}");

        return $errorCount > 0 ? 1 : 0;
    }

    /**
     * Process a single expired confirmed task - simply change to void
     */
    private function processExpiredTask(Task $task, bool $isDryRun): void
    {
        $this->line("Processing task: {$task->reference} (expired: {$task->expiry_date})");
        $this->info("  → Changing status from 'confirmed' to 'void'");
        
        if (!$isDryRun) {
            $this->changeTaskToVoid($task);
        }
    }

    /**
     * Change task status to void
     */
    private function changeTaskToVoid(Task $task): void
    {
        DB::transaction(function () use ($task) {
            $oldStatus = $task->status;
            
            try{
                $task->status = 'void';
                $task->save();
            } catch (Exception $e){
                
                Log::error("Failed to change task status: " . $e->getMessage(), [
                    'task_id' => $task->id,
                    'reference' => $task->reference,
                    'expiry_date' => $task->expiry_date
                ]);
                throw $e; // Re-throw to handle in the main loop
            }

            Log::info("Task status changed from '{$oldStatus}' to 'void' due to expiry", [
                'task_id' => $task->id,
                'reference' => $task->reference,
                'expiry_date' => $task->expiry_date
            ]);
        });
    }
}
