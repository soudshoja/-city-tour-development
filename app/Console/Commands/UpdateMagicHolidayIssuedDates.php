<?php

namespace App\Console\Commands;

use App\Http\Controllers\SupplierController;
use App\Http\Traits\HttpRequestTrait;
use App\Models\Supplier;
use App\Models\Task;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateMagicHolidayIssuedDates extends Command
{
    use HttpRequestTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'magic-holiday:update-issued-dates 
                            {--limit=50 : Number of tasks to process in this run}
                            {--dry-run : Show what would be updated without making changes}
                            {--from-id= : Start from this task ID (inclusive)}
                            {--to-id= : End at this task ID (inclusive)}
                            {--below-id= : Process tasks with ID below this value (exclusive)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update issued_date for Magic Holiday hotel tasks by fetching from API. Supports ID range filtering.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Magic Holiday issued dates update...');

        // Get Magic Holiday supplier
        $supplier = Supplier::where('name', 'Magic Holiday')->first();
        if (!$supplier) {
            $this->error('Magic Holiday supplier not found');
            return 1;
        }

        $this->info("Found Magic Holiday supplier (ID: {$supplier->id})");

        // Show statistics about all Magic Holiday hotel tasks
        $allTasks = Task::where('supplier_id', $supplier->id)
            ->where('type', 'hotel')
            ->count();
        
        $tasksWithIssuedDate = Task::where('supplier_id', $supplier->id)
            ->where('type', 'hotel')
            ->whereNotNull('issued_date')
            ->count();
            
        $tasksWithoutReference = Task::where('supplier_id', $supplier->id)
            ->where('type', 'hotel')
            ->whereNull('reference')
            ->count();

        $this->info("Magic Holiday hotel tasks statistics:");
        $this->info("  Total hotel tasks: {$allTasks}");
        $this->info("  Tasks with issued_date: {$tasksWithIssuedDate}");
        $this->info("  Tasks without reference: {$tasksWithoutReference}");
        $this->info("  Processing ALL tasks with valid references (assuming all issued dates need updating)");

        // Get ALL Magic Holiday hotel tasks (assuming all issued dates need updating)
        $tasksQuery = Task::where('supplier_id', $supplier->id)
            ->where('type', 'hotel')
            ->whereNotNull('reference');
            
        // Apply ID range filters
        $fromId = $this->option('from-id');
        $toId = $this->option('to-id');
        $belowId = $this->option('below-id');
        
        if ($fromId !== null) {
            $tasksQuery->where('id', '>=', $fromId);
            $this->info("Filtering: ID >= {$fromId}");
        }
        
        if ($toId !== null) {
            $tasksQuery->where('id', '<=', $toId);
            $this->info("Filtering: ID <= {$toId}");
        }
        
        if ($belowId !== null) {
            $tasksQuery->where('id', '<', $belowId);
            $this->info("Filtering: ID < {$belowId}");
        }
        
        // Apply limit and get results
        $tasks = $tasksQuery->limit($this->option('limit'))->get();

        if ($tasks->isEmpty()) {
            $this->info('No Magic Holiday hotel tasks found with valid references');
            return 0;
        }

        $this->info("Found {$tasks->count()} tasks to process");

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No actual changes will be made');
        }

        $successCount = 0;
        $errorCount = 0;
        $supplierController = new SupplierController();

        foreach ($tasks as $task) {
            try {
                $this->info("Processing task ID: {$task->id}, Reference: {$task->reference}");

                // Fetch reservation data from Magic Holiday API
                $response = $supplierController->getMagicHoliday($task->reference);
                $data = json_decode($response->getContent(), true);

                if (isset($data['status']) && $data['status'] == 'error') {
                    $this->warn("API error for task {$task->id}: " . ($data['message'] ?? 'Unknown error'));
                    $errorCount++;
                    continue;
                }

                if (!isset($data['data'])) {
                    $this->warn("No data found for task {$task->id}");
                    $errorCount++;
                    continue;
                }

                $reservation = $data['data'];

                // Extract the issued date from the API response
                if (isset($reservation['added']['time'])) {
                    $newIssuedDate = Carbon::parse($reservation['added']['time'])->toDateTimeString();
                    
                    if ($this->option('dry-run')) {
                        $this->line("DRY RUN - Would update task {$task->id} issued_date from '{$task->issued_date}' to '{$newIssuedDate}'");
                    } else {
                        $oldDate = $task->issued_date;
                        $task->issued_date = $newIssuedDate;
                        $task->save();
                        
                        $this->line("Updated task {$task->id} issued_date from '{$oldDate}' to '{$newIssuedDate}'");
                        
                        Log::channel('magic_holidays')->info('Updated issued_date for task', [
                            'task_id' => $task->id,
                            'reference' => $task->reference,
                            'old_issued_date' => $oldDate,
                            'new_issued_date' => $newIssuedDate
                        ]);
                    }
                    
                    $successCount++;
                } else {
                    $this->warn("No 'added.time' found in API response for task {$task->id}");
                    $errorCount++;
                }

                // Add a small delay to avoid hitting API rate limits
                usleep(100000); // 0.1 seconds

            } catch (Exception $e) {
                $this->error("Error processing task {$task->id}: " . $e->getMessage());
                Log::channel('magic_holidays')->error('Error updating issued_date for task', [
                    'task_id' => $task->id,
                    'reference' => $task->reference,
                    'error' => $e->getMessage()
                ]);
                $errorCount++;
            }
        }

        $this->info("\nUpdate complete!");
        $this->info("Successful updates: {$successCount}");
        $this->info("Errors: {$errorCount}");

        if ($this->option('dry-run')) {
            $this->info("This was a dry run - no actual changes were made");
        }

        return 0;
    }
}
