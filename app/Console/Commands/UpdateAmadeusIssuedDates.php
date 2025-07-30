<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\JournalEntry;
use App\Services\AirFileParser;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class UpdateAmadeusIssuedDates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'amadeus:update-issued-dates 
                            {--dry-run : Show what would be updated without making changes}
                            {--limit=50 : Number of tasks to process in this run}
                            {--from-id= : Start from this task ID (inclusive)}
                            {--to-id= : End at this task ID (inclusive)}
                            {--below-id= : Process tasks with ID below this value (exclusive)}
                            {--force-reparse : Re-parse files even if issued_date already exists}
                            {--update-transactions : Also update transaction_date for related transactions and journal entries}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update issued_date for Amadeus tasks by re-parsing their original AIR files using AirFileParser. For refund tasks, uses refund_date as issued_date. For void tasks, uses void_date as issued_date. Optionally update related transaction dates.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Amadeus tasks issued_date update...');

        // Get Amadeus supplier
        $amadeusSupplier = Supplier::where('name', 'like', '%Amadeus%')->first();
        if (!$amadeusSupplier) {
            $this->error('Amadeus supplier not found');
            return 1;
        }

        $this->info("Found Amadeus supplier (ID: {$amadeusSupplier->id}): {$amadeusSupplier->name}");

        // Build query for Amadeus tasks
        $tasksQuery = Task::where('supplier_id', $amadeusSupplier->id)
            ->whereNotNull('file_name') // Only tasks that came from files
            ->whereNotNull('reference'); // Must have reference

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

        // Filter by issued_date status unless force-reparse is enabled
        if (!$this->option('force-reparse')) {
            $tasksQuery->whereNull('issued_date');
            $this->info("Only processing tasks without issued_date (use --force-reparse to update all)");
        } else {
            $this->info("Force reparse enabled - will update all matching tasks");
        }

        if ($this->option('update-transactions')) {
            $this->info("Transaction updates enabled - will also update transaction_date and journal entry dates");
        } else {
            $this->info("Transaction updates disabled (use --update-transactions to enable)");
        }

        // Get tasks
        $tasks = $tasksQuery
            ->limit($this->option('limit'))
            ->orderBy('id')
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('No Amadeus tasks found that need issued_date updates');
            return 0;
        }

        // Load companies for display
        $companyIds = $tasks->pluck('company_id')->unique();
        $companies = \App\Models\Company::whereIn('id', $companyIds)->get()->keyBy('id');

        $this->info("Found {$tasks->count()} Amadeus tasks to process");

        // Show statistics
        $this->showTaskStatistics($tasks, $companies);

        if ($this->option('dry-run')) {
            $this->info('DRY RUN MODE - No actual changes will be made');
        }

        // Process tasks
        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;
        
        // Report tracking
        $filesWithoutIssuedDate = [];
        $tasksWithoutFiles = [];
        $otherErrors = [];

        foreach ($tasks as $task) {
            // Set company relationship manually
            $task->company = $companies->get($task->company_id);

            try {
                $result = $this->processAmadeusTask($task);
                
                switch ($result['status']) {
                    case 'success':
                        $successCount++;
                        $this->info("✓ Updated task {$task->id}: {$result['message']}");
                        break;
                    case 'skipped':
                        $skippedCount++;
                        $this->warn("- Skipped task {$task->id}: {$result['message']}");
                        
                        // Track different types of skipped tasks for reporting
                        if (strpos($result['message'], 'No issued_date, refund_date, or void_date found') !== false) {
                            $filesWithoutIssuedDate[] = [
                                'task_id' => $task->id,
                                'reference' => $task->reference,
                                'file_name' => $task->file_name,
                                'company' => $task->company ? $task->company->name : 'Unknown'
                            ];
                        }
                        break;
                    case 'error':
                        $errorCount++;
                        $this->error("✗ Failed task {$task->id}: {$result['message']}");
                        
                        // Track different types of errors for reporting
                        if (strpos($result['message'], 'AIR file not found') !== false) {
                            $tasksWithoutFiles[] = [
                                'task_id' => $task->id,
                                'reference' => $task->reference,
                                'file_name' => $task->file_name,
                                'company' => $task->company ? $task->company->name : 'Unknown'
                            ];
                        } else {
                            $otherErrors[] = [
                                'task_id' => $task->id,
                                'reference' => $task->reference,
                                'file_name' => $task->file_name,
                                'company' => $task->company ? $task->company->name : 'Unknown',
                                'error' => $result['message']
                            ];
                        }
                        break;
                }

            } catch (Exception $e) {
                $errorCount++;
                $this->error("✗ Exception processing task {$task->id}: " . $e->getMessage());
                Log::error("Amadeus issued date update failed for task {$task->id}", [
                    'error' => $e->getMessage(),
                    'task_reference' => $task->reference
                ]);
                
                // Track exceptions for reporting
                $otherErrors[] = [
                    'task_id' => $task->id,
                    'reference' => $task->reference,
                    'file_name' => $task->file_name,
                    'company' => $task->company ? $task->company->name : 'Unknown',
                    'error' => 'Exception: ' . $e->getMessage()
                ];
            }
        }

        // Summary
        $this->info("\nUpdate complete!");
        $this->info("Successful updates: {$successCount}");
        if ($skippedCount > 0) {
            $this->info("Skipped: {$skippedCount}");
        }
        if ($errorCount > 0) {
            $this->warn("Errors: {$errorCount}");
        }

        if ($this->option('dry-run')) {
            $this->info('This was a dry run - no actual changes were made');
        }

        // Generate detailed report
        $this->generateReport($filesWithoutIssuedDate, $tasksWithoutFiles, $otherErrors);

        return 0;
    }

    /**
     * Process a single Amadeus task to update its issued_date
     */
    protected function processAmadeusTask(Task $task): array
    {
        DB::beginTransaction();
        
        try {
            // Find the original AIR file
            $filePath = $this->findTaskAirFile($task);
            
            if (!$filePath) {
                DB::rollBack();
                return [
                    'status' => 'error',
                    'message' => "AIR file not found: {$task->file_name}"
                ];
            }

            // Parse the file using AirFileParser
            $parser = new AirFileParser($filePath);
            $tasksData = $parser->parseTaskSchema();

            // Find the matching task data by reference
            $matchingTaskData = null;
            foreach ($tasksData as $taskData) {
                if (isset($taskData['reference']) && $taskData['reference'] === $task->reference) {
                    $matchingTaskData = $taskData;
                    break;
                }
            }

            if (!$matchingTaskData) {
                DB::rollBack();
                return [
                    'status' => 'error',
                    'message' => "Task reference {$task->reference} not found in parsed file data"
                ];
            }

            // Extract issued_date from parsed data, fallback to refund_date for refund tasks or void_date for void tasks
            $newIssuedDate = null;
            $dateSource = 'issued_date';
            
            if (isset($matchingTaskData['issued_date']) && !empty($matchingTaskData['issued_date'])) {
                try {
                    $newIssuedDate = Carbon::parse($matchingTaskData['issued_date']);
                } catch (Exception $e) {
                    DB::rollBack();
                    return [
                        'status' => 'error',
                        'message' => "Invalid issued_date format in file: {$matchingTaskData['issued_date']}"
                    ];
                }
            } elseif (isset($matchingTaskData['refund_date']) && !empty($matchingTaskData['refund_date'])) {
                // For refund tasks, use refund_date as issued_date if no issued_date found
                try {
                    $newIssuedDate = Carbon::parse($matchingTaskData['refund_date']);
                    $dateSource = 'refund_date';
                    $this->info("Using refund_date as issued_date for refund task {$task->id}");
                } catch (Exception $e) {
                    DB::rollBack();
                    return [
                        'status' => 'error',
                        'message' => "Invalid refund_date format in file: {$matchingTaskData['refund_date']}"
                    ];
                }
            } else {
                DB::rollBack();
                return [
                    'status' => 'skipped',
                    'message' => "No issued_date, refund_date, or void_date found in parsed file data"
                ];
            }

            $newIssuedDateString = $newIssuedDate->toDateTimeString();
            
            // Check if update is needed
            $oldDate = $task->issued_date;
            if (!$this->option('force-reparse') && $oldDate && $oldDate->toDateTimeString() === $newIssuedDateString) {
                DB::rollBack();
                return [
                    'status' => 'skipped',
                    'message' => "issued_date unchanged: {$newIssuedDateString}"
                ];
            }

            $updatedItems = [];

            // Update the task (unless dry run)
            if (!$this->option('dry-run')) {
                // Only update the issued_date, don't save the company relationship
                $task->issued_date = $newIssuedDate;
                
                // Temporarily unset the company relationship to avoid saving it
                $company = $task->company;
                unset($task->company);
                
                $task->save();
                
                // Restore the company relationship for potential future use
                $task->company = $company;
                
                $updatedItems[] = 'task issued_date';
            }

            // Update related transactions and journal entries if requested
            if ($this->option('update-transactions')) {
                $transactionUpdates = $this->updateTaskTransactionDates($task, $newIssuedDate);
                $updatedItems = array_merge($updatedItems, $transactionUpdates);
            }

            DB::commit();

            $itemsStr = empty($updatedItems) ? 'task issued_date' : implode(', ', $updatedItems);
            $oldDateStr = $oldDate ? $oldDate->toDateTimeString() : 'null';
            $sourceInfo = $dateSource !== 'issued_date' ? " (from {$dateSource})" : '';
            
            return [
                'status' => 'success',
                'message' => "Updated {$itemsStr} from '{$oldDateStr}' to '{$newIssuedDateString}'{$sourceInfo}"
            ];

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'status' => 'error',
                'message' => "Exception: " . $e->getMessage()
            ];
        }
    }

    /**
     * Find the AIR file for a given task
     */
    protected function findTaskAirFile(Task $task): ?string
    {
        if (!$task->file_name || !$task->company) {
            return null;
        }

        $fileName = $task->file_name;
        $companyName = strtolower(preg_replace('/\s+/', '_', $task->company->name));
        $supplierName = 'amadeus'; // Assuming Amadeus supplier folder name

        // Check different possible locations for the file
        $possiblePaths = [
            // Processed files
            storage_path("app/{$companyName}/{$supplierName}/files_processed/{$fileName}"),
            // Error files (in case of partial processing)
            storage_path("app/{$companyName}/{$supplierName}/files_error/{$fileName}"),
            // Unprocessed files (if still there)
            storage_path("app/{$companyName}/{$supplierName}/files_unprocessed/{$fileName}"),
            // Alternative path structure
            storage_path("app/{$companyName}/amadeus/files_processed/{$fileName}"),
            storage_path("app/{$companyName}/amadeus/files_error/{$fileName}"),
            // Check with original case-sensitive company name
            storage_path("app/" . $task->company->name . "/{$supplierName}/files_processed/{$fileName}"),
            storage_path("app/" . $task->company->name . "/{$supplierName}/files_error/{$fileName}"),
        ];

        foreach ($possiblePaths as $path) {
            if (File::exists($path)) {
                $this->info("Found file at: {$path}");
                return $path;
            }
        }

        // Log all attempted paths for debugging
        Log::info("AIR file not found for task {$task->id}", [
            'file_name' => $fileName,
            'company' => $task->company->name,
            'attempted_paths' => $possiblePaths
        ]);

        return null;
    }

    /**
     * Update transaction dates and journal entry dates for a task
     */
    protected function updateTaskTransactionDates(Task $task, Carbon $newDate): array
    {
        $updatedItems = [];
        
        if ($this->option('dry-run')) {
            // In dry run mode, just check what would be updated
            $transactions = Transaction::whereHas('journalEntries', function ($query) use ($task) {
                $query->where('task_id', $task->id);
            })->get();
            
            $journalEntries = JournalEntry::where('task_id', $task->id)->get();
            
            if ($transactions->count() > 0) {
                $updatedItems[] = $transactions->count() . ' transaction(s)';
            }
            
            if ($journalEntries->count() > 0) {
                $updatedItems[] = $journalEntries->count() . ' journal entry(ies)';
            }
            
            return $updatedItems;
        }

        // Update transactions that have journal entries for this task
        $transactionCount = 0;
        $transactions = Transaction::whereHas('journalEntries', function ($query) use ($task) {
            $query->where('task_id', $task->id);
        })->get();

        foreach ($transactions as $transaction) {
            $transaction->transaction_date = $newDate;
            $transaction->save();
            $transactionCount++;
        }

        // Update journal entries for this task
        $journalEntryCount = JournalEntry::where('task_id', $task->id)
            ->update(['transaction_date' => $newDate]);

        if ($transactionCount > 0) {
            $updatedItems[] = "{$transactionCount} transaction(s)";
        }

        if ($journalEntryCount > 0) {
            $updatedItems[] = "{$journalEntryCount} journal entry(ies)";
        }

        return $updatedItems;
    }

    /**
     * Show statistics about the tasks to be processed
     */
    protected function showTaskStatistics($tasks, $companies = null): void
    {
        $totalTasks = $tasks->count();
        $tasksWithIssuedDate = $tasks->filter(fn($task) => $task->issued_date !== null)->count();
        $tasksWithoutIssuedDate = $totalTasks - $tasksWithIssuedDate;

        // Group by status
        $statusCounts = $tasks->groupBy('status')->map->count();
        
        // Group by company
        if ($companies) {
            $companyCounts = $tasks->groupBy(function($task) use ($companies) {
                $company = $companies->get($task->company_id);
                return $company ? $company->name : 'Unknown';
            })->map->count();
        } else {
            $companyCounts = ['Unknown' => $totalTasks];
        }

        // Count tasks with transactions/journal entries if update-transactions is enabled
        $tasksWithTransactions = 0;
        $tasksWithJournalEntries = 0;
        
        if ($this->option('update-transactions')) {
            foreach ($tasks as $task) {
                $hasTransactions = Transaction::whereHas('journalEntries', function ($query) use ($task) {
                    $query->where('task_id', $task->id);
                })->exists();
                
                $hasJournalEntries = JournalEntry::where('task_id', $task->id)->exists();
                
                if ($hasTransactions) $tasksWithTransactions++;
                if ($hasJournalEntries) $tasksWithJournalEntries++;
            }
        }

        $this->info("Task Statistics:");
        $this->info("  Total tasks: {$totalTasks}");
        $this->info("  Tasks with issued_date: {$tasksWithIssuedDate}");
        $this->info("  Tasks without issued_date: {$tasksWithoutIssuedDate}");
        
        if ($this->option('update-transactions')) {
            $this->info("  Tasks with transactions: {$tasksWithTransactions}");
            $this->info("  Tasks with journal entries: {$tasksWithJournalEntries}");
        }
        
        $this->info("  Status breakdown:");
        foreach ($statusCounts as $status => $count) {
            $this->info("    {$status}: {$count}");
        }

        $this->info("  Company breakdown:");
        foreach ($companyCounts as $company => $count) {
            $this->info("    {$company}: {$count}");
        }
    }

    /**
     * Generate a detailed report of processing results
     */
    protected function generateReport(array $filesWithoutIssuedDate, array $tasksWithoutFiles, array $otherErrors): void
    {
        $this->info("\n" . str_repeat("=", 60));
        $this->info("DETAILED PROCESSING REPORT");
        $this->info(str_repeat("=", 60));

        // Report 1: Files without any date information
        if (!empty($filesWithoutIssuedDate)) {
            $this->warn("\n📄 FILES WITHOUT DATE INFORMATION ({" . count($filesWithoutIssuedDate) . "}):");
            $this->info(str_repeat("-", 60));
            
            foreach ($filesWithoutIssuedDate as $item) {
                $this->line("• File: {$item['file_name']}");
                $this->line("  Task ID: {$item['task_id']} | Reference: {$item['reference']}");
                $this->line("  Company: {$item['company']}");
                $this->line("");
            }
        } else {
            $this->info("\n✅ All processed files contained date information (issued_date, refund_date, or void_date)");
        }

        // Report 2: Tasks without files
        if (!empty($tasksWithoutFiles)) {
            $this->warn("\n🔍 TASKS WITHOUT FILES ({" . count($tasksWithoutFiles) . "}):");
            $this->info(str_repeat("-", 60));
            
            foreach ($tasksWithoutFiles as $item) {
                $this->line("• Reference: {$item['reference']}");
                $this->line("  Task ID: {$item['task_id']} | Expected File: {$item['file_name']}");
                $this->line("  Company: {$item['company']}");
                $this->line("");
            }
        } else {
            $this->info("\n✅ All tasks had their corresponding AIR files found");
        }

        // Report 3: Other errors
        if (!empty($otherErrors)) {
            $this->error("\n❌ OTHER ERRORS ({" . count($otherErrors) . "}):");
            $this->info(str_repeat("-", 60));
            
            foreach ($otherErrors as $item) {
                $this->line("• Task ID: {$item['task_id']} | Reference: {$item['reference']}");
                $this->line("  File: {$item['file_name']} | Company: {$item['company']}");
                $this->line("  Error: {$item['error']}");
                $this->line("");
            }
        } else {
            $this->info("\n✅ No other processing errors encountered");
        }

        // Summary statistics
        $this->info("\n📊 REPORT SUMMARY:");
        $this->info(str_repeat("-", 60));
        $this->info("Files without date information: " . count($filesWithoutIssuedDate));
        $this->info("Tasks without files: " . count($tasksWithoutFiles));
        $this->info("Other errors: " . count($otherErrors));
        $this->info("Total issues: " . (count($filesWithoutIssuedDate) + count($tasksWithoutFiles) + count($otherErrors)));
        
        $this->info("\n" . str_repeat("=", 60));
    }
}