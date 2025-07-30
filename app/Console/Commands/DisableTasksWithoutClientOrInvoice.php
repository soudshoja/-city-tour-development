<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\InvoiceDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DisableTasksWithoutClientOrInvoice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:disable-without-client-or-invoice 
                           {--dry-run : Show what would be disabled without actually doing it}
                           {--company= : Filter by specific company ID}
                           {--limit= : Limit the number of tasks to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find and disable tasks that have not been invoiced and do not have a client assigned';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $companyId = $this->option('company');
        $limit = $this->option('limit');

        $this->info('Finding tasks without client and without invoice...');

        try {
            // Build the query to find tasks that:
            // 1. Do not have an invoice (no invoice detail)
            // 2. Do not have a client assigned
            // 3. Are currently enabled
            $query = Task::withoutGlobalScope('enabled')
                ->whereDoesntHave('invoiceDetail')
                ->whereNull('client_id')
                ->where('enabled', true);

            // Filter by company if specified
            if ($companyId) {
                $query->where('company_id', $companyId);
                $this->info("Filtering by company ID: {$companyId}");
            }

            // Apply limit if specified
            if ($limit) {
                $query->limit($limit);
                $this->info("Limiting to {$limit} tasks");
            }

            $tasksToDisable = $query->get();

            if ($tasksToDisable->isEmpty()) {
                $this->info('No tasks found that match the criteria.');
                return 0;
            }

            $this->info("Found {$tasksToDisable->count()} tasks to disable:");

            // Display tasks in a table
            $tableData = $tasksToDisable->map(function ($task) {
                return [
                    'ID' => $task->id,
                    'Reference' => $task->reference ?? 'N/A',
                    'Company ID' => $task->company_id,
                    'Type' => $task->type ?? 'N/A',
                    'Status' => $task->status ?? 'N/A',
                    'Client Name' => $task->client_name ?? 'N/A',
                    'Agent ID' => $task->agent_id ?? 'N/A',
                    'Created' => $task->created_at ? $task->created_at->format('Y-m-d H:i:s') : 'N/A'
                ];
            })->toArray();

            $this->table([
                'ID', 'Reference', 'Company ID', 'Type', 'Status', 
                'Client Name', 'Agent ID', 'Created'
            ], $tableData);

            if ($dryRun) {
                $this->warn('DRY RUN: No tasks were actually disabled.');
                $this->info('To actually disable these tasks, run the command without --dry-run');
                return 0;
            }

            // Confirm before proceeding
            if (!$this->confirm("Do you want to disable these {$tasksToDisable->count()} tasks?")) {
                $this->info('Operation cancelled.');
                return 0;
            }

            // Disable the tasks
            DB::beginTransaction();

            try {
                $disabledCount = 0;
                
                foreach ($tasksToDisable as $task) {
                    $task->enabled = false;
                    $task->save();
                    $disabledCount++;

                    Log::info('Task disabled by command', [
                        'task_id' => $task->id,
                        'reference' => $task->reference,
                        'reason' => 'No client assigned and not invoiced',
                        'command' => 'tasks:disable-without-client-or-invoice'
                    ]);
                }

                DB::commit();

                $this->info("Successfully disabled {$disabledCount} tasks.");
                
                // Summary
                $this->info("\nSummary:");
                $this->info("- Tasks found: {$tasksToDisable->count()}");
                $this->info("- Tasks disabled: {$disabledCount}");
                $this->info("- Reason: No client assigned and not invoiced");

            } catch (\Exception $e) {
                DB::rollback();
                $this->error("Error disabling tasks: " . $e->getMessage());
                Log::error('Error in disable tasks command', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("Error finding tasks: " . $e->getMessage());
            Log::error('Error in disable tasks command query', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}
