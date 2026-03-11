<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class RemoveConfirmedTaskTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:remove-confirmed-task-transactions 
                            {--dry-run : Show what would be removed without making changes}
                            {--task-id= : Remove transactions for specific task ID only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove transactions and journal entries for tasks with status = confirmed (they should not have financial records)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $specificTaskId = $this->option('task-id');
        
        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
        }
        
        $this->info('Finding confirmed tasks with transactions/journal entries...');
        
        try {
            // Find confirmed tasks that have transactions/journal entries
            $tasksWithTransactions = $this->findConfirmedTasksWithTransactions($specificTaskId);
            
            if ($tasksWithTransactions->isEmpty()) {
                $this->info('No confirmed tasks found with transactions/journal entries.');
                return 0;
            }
            
            $this->info("Found {$tasksWithTransactions->count()} confirmed tasks with financial records:");
            
            // Display summary
            $this->table(
                ['Task ID', 'Reference', 'Status', 'Total', 'Journal Entries', 'Supplier'],
                $tasksWithTransactions->map(function ($task) {
                    return [
                        $task->id,
                        $task->reference,
                        $task->status,
                        $task->total ?? 'N/A',
                        $task->journal_entries_count ?? 0,
                        $task->supplier->name ?? 'N/A'
                    ];
                })->toArray()
            );
            
            if ($dryRun) {
                $this->info('DRY RUN complete - no changes made');
                return 0;
            }
            
            if (!$this->confirm('Do you want to proceed with removing transactions and journal entries for these confirmed tasks?')) {
                $this->info('Operation cancelled by user.');
                return 0;
            }
            
            // Process each task
            $processed = 0;
            $errors = 0;
            
            foreach ($tasksWithTransactions as $task) {
                try {
                    $removedData = $this->removeTaskTransactions($task);
                    $processed++;
                    $this->info("✓ Removed {$removedData['transactions']} transactions and {$removedData['journal_entries']} journal entries for task: {$task->reference}");
                } catch (Exception $e) {
                    $errors++;
                    $this->error("✗ Failed to remove transactions for task {$task->reference}: " . $e->getMessage());
                    Log::error("Transaction removal failed: {$task->reference}", ['error' => $e->getMessage()]);
                }
            }
            
            $this->info("\nRemoval complete:");
            $this->info("Successfully processed: {$processed} tasks");
            if ($errors > 0) {
                $this->warn("Errors encountered: {$errors} tasks");
            }
            
            return 0;
            
        } catch (Exception $e) {
            $this->error('Command failed: ' . $e->getMessage());
            Log::error('Remove confirmed task transactions command failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }
    
    /**
     * Find confirmed tasks that have transactions or journal entries
     */
    private function findConfirmedTasksWithTransactions($specificTaskId = null)
    {
        $query = Task::with(['supplier'])
            ->where('status', 'confirmed')
            ->has('journalEntries') // Tasks that have journal entries
            ->withCount(['journalEntries'])
            ->orderBy('id', 'asc');
            
        if ($specificTaskId) {
            $query->where('id', $specificTaskId);
        }
        
        return $query->get();
    }
    
    /**
     * Remove transactions and journal entries for a specific task
     */
    private function removeTaskTransactions(Task $task)
    {
        DB::beginTransaction();
        
        try {
            $removedTransactions = 0;
            $removedJournalEntries = 0;
            
            // Get all transactions for this task
            $transactions = Transaction::whereHas('journalEntries', function ($query) use ($task) {
                $query->where('task_id', $task->id);
            })->get();
            
            foreach ($transactions as $transaction) {
                // Remove journal entries for this task within this transaction
                $journalEntryCount = JournalEntry::where('transaction_id', $transaction->id)
                    ->where('task_id', $task->id)
                    ->count();
                    
                JournalEntry::where('transaction_id', $transaction->id)
                    ->where('task_id', $task->id)
                    ->delete();
                    
                $removedJournalEntries += $journalEntryCount;
                
                // Check if transaction has any remaining journal entries
                $remainingEntries = JournalEntry::where('transaction_id', $transaction->id)->count();
                
                if ($remainingEntries === 0) {
                    // Remove the transaction if it has no remaining journal entries
                    $transaction->delete();
                    $removedTransactions++;
                } else {
                    $this->warn("Transaction {$transaction->id} still has other journal entries, not removing transaction");
                }
            }
            
            // Also remove any direct journal entries for this task (without transaction relationship)
            $directJournalEntries = JournalEntry::where('task_id', $task->id)->count();
            if ($directJournalEntries > 0) {
                JournalEntry::where('task_id', $task->id)->delete();
                $removedJournalEntries += $directJournalEntries;
            }
            
            DB::commit();
            
            Log::info("Removed transactions for confirmed task", [
                'task_id' => $task->id,
                'reference' => $task->reference,
                'status' => $task->status,
                'transactions_removed' => $removedTransactions,
                'journal_entries_removed' => $removedJournalEntries
            ]);
            
            return [
                'transactions' => $removedTransactions,
                'journal_entries' => $removedJournalEntries
            ];
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
