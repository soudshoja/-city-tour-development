<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Task;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Company;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearTaskRelatedData extends Command
{
    protected $signature = 'tasks:clear-related-data {--force : Force the operation without confirmation} {--company= : Clear tasks for specific company (ID or name)}';

    protected $description = 'Clear all data related to tasks while preserving non-task related records';

    public function handle()
    {
        $companyFilter = $this->option('company');
        $companyId = null;
        $companyName = '';

        // Handle company filter
        if ($companyFilter) {
            // Check if it's a numeric ID
            if (is_numeric($companyFilter)) {
                $company = Company::find($companyFilter);
                if (!$company) {
                    $this->error("Company with ID {$companyFilter} not found.");
                    return Command::FAILURE;
                }
                $companyId = $company->id;
                $companyName = $company->name;
            } else {
                // Search by name using LIKE
                $companies = Company::where('name', 'LIKE', "%{$companyFilter}%")->get();
                
                if ($companies->isEmpty()) {
                    $this->error("No companies found matching '{$companyFilter}'.");
                    return Command::FAILURE;
                }
                
                if ($companies->count() > 1) {
                    $this->info("Multiple companies found:");
                    foreach ($companies as $company) {
                        $this->line("ID: {$company->id} - Name: {$company->name}");
                    }
                    $companyId = $this->ask('Please enter the Company ID you want to use');
                    $selectedCompany = Company::find($companyId);
                    if (!$selectedCompany) {
                        $this->error("Invalid Company ID selected.");
                        return Command::FAILURE;
                    }
                    $companyName = $selectedCompany->name;
                } else {
                    $company = $companies->first();
                    $companyId = $company->id;
                    $companyName = $company->name;
                }
            }
            
            $this->info("Selected Company: {$companyName} (ID: {$companyId})");
        }

        if (!$this->option('force')) {
            $confirmMessage = $companyFilter ? 
                "This will delete all task-related data for company '{$companyName}'. Do you wish to continue?" :
                'This will delete all task-related data. Do you wish to continue?';
                
            if (!$this->confirm($confirmMessage)) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        $this->info($companyFilter ? 
            "Starting task-related data cleanup for company '{$companyName}'..." :
            'Starting task-related data cleanup...');

        DB::beginTransaction();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        try {
            // Get all task IDs before deletion (filtered by company if specified)
            $taskQuery = Task::query();
            if ($companyId) {
                $taskQuery->where('company_id', $companyId);
            }
            $taskIds = $taskQuery->pluck('id')->toArray();
            
            $taskCountMessage = $companyFilter ? 
                "Found " . count($taskIds) . " tasks for company '{$companyName}' to process." :
                'Found ' . count($taskIds) . ' tasks to process.';
            $this->info($taskCountMessage);

            if (empty($taskIds)) {
                $noTasksMessage = $companyFilter ? 
                    "No tasks found for company '{$companyName}'. Nothing to clear." :
                    'No tasks found. Nothing to clear.';
                $this->info($noTasksMessage);
                DB::rollback();
                // return Command::SUCCESS;
            }

            $journalEntries = JournalEntry::whereIn('task_id', $taskIds)->get();

            if( !empty($journalEntries)) {
                // Clear journal entries related to tasks
                $transactionsId = $journalEntries->pluck('transaction_id')->toArray();
                $transactions = Transaction::whereIn('id', $transactionsId)->get();
                
                if ($transactions->isNotEmpty()) {
                    // Delete transactions related to journal entries
                    $transactionsCount = $transactions->count();
                    $transactions->each(function ($transaction) {
                        $transaction->delete();
                    });
                    $this->info("Deleted {$transactionsCount} transactions related to journal entries.");
                }

                $journalEntries->each(function ($journalEntry) {
                    $journalEntry->delete();
                });

                $this->info("Deleted " . count($journalEntries) . " journal entries related to tasks.");
            }

            // 1. Clear invoice details related to tasks
            $invoiceDetailsCount = InvoiceDetail::whereIn('task_id', $taskIds)->count();
            if ($invoiceDetailsCount > 0) {
                InvoiceDetail::whereIn('task_id', $taskIds)->delete();
                $this->info("Deleted {$invoiceDetailsCount} invoice details related to tasks.");
            }

            // 2. Get invoices that have task-related invoice details (now deleted)
            // and invoices that might be empty after deletion
            $taskRelatedInvoiceIds = Invoice::whereHas('invoiceDetails', function($query) use ($taskIds) {
                $query->whereIn('task_id', $taskIds);
            })->pluck('id')->toArray();

            // Also get invoices that no longer have any invoice details
            $emptyInvoiceIds = Invoice::whereDoesntHave('invoiceDetails')->pluck('id')->toArray();
            
            $invoiceIdsToDelete = array_unique(array_merge($taskRelatedInvoiceIds, $emptyInvoiceIds));

            if (!empty($invoiceIdsToDelete)) {
                // 3. Clear payments related to task invoices
                $paymentsCount = Payment::whereIn('invoice_id', $invoiceIdsToDelete)->count();
                if ($paymentsCount > 0) {
                    Payment::whereIn('invoice_id', $invoiceIdsToDelete)->delete();
                    $this->info("Deleted {$paymentsCount} payments related to task invoices.");
                }

                // 4. Clear transactions related to task invoices
                $transactionsCount = Transaction::whereIn('invoice_id', $invoiceIdsToDelete)->count();
                if ($transactionsCount > 0) {
                    Transaction::whereIn('invoice_id', $invoiceIdsToDelete)->delete();
                    $this->info("Deleted {$transactionsCount} transactions related to task invoices.");
                }

                // 5. Clear journal entries related to task invoices (if they exist)
                if (DB::getSchemaBuilder()->hasTable('journal_entries')) {
                    $journalEntriesCount = DB::table('journal_entries')
                        ->whereIn('invoice_id', $invoiceIdsToDelete)
                        ->count();
                    if ($journalEntriesCount > 0) {
                        DB::table('journal_entries')->whereIn('invoice_id', $invoiceIdsToDelete)->delete();
                        $this->info("Deleted {$journalEntriesCount} journal entries related to task invoices.");
                    }
                }

                // 6. Clear invoice partials related to task invoices (if they exist)
                if (DB::getSchemaBuilder()->hasTable('invoice_partials')) {
                    $invoicePartialsCount = DB::table('invoice_partials')
                        ->whereIn('invoice_id', $invoiceIdsToDelete)
                        ->count();
                    if ($invoicePartialsCount > 0) {
                        DB::table('invoice_partials')->whereIn('invoice_id', $invoiceIdsToDelete)->delete();
                        $this->info("Deleted {$invoicePartialsCount} invoice partials related to task invoices.");
                    }
                }

                // 7. Delete the invoices themselves
                $invoicesCount = Invoice::whereIn('id', $invoiceIdsToDelete)->count();
                if ($invoicesCount > 0) {
                    Invoice::whereIn('id', $invoiceIdsToDelete)->delete();
                    $this->info("Deleted {$invoicesCount} invoices related to tasks.");
                }
            }

            // 8. Clear task flight details
            $flightDetailsCount = DB::table('task_flight_details')->whereIn('task_id', $taskIds)->count();
            if ($flightDetailsCount > 0) {
                DB::table('task_flight_details')->whereIn('task_id', $taskIds)->delete();
                $this->info("Deleted {$flightDetailsCount} task flight details.");
            }

            // 9. Clear task hotel details
            $hotelDetailsCount = DB::table('task_hotel_details')->whereIn('task_id', $taskIds)->count();
            if ($hotelDetailsCount > 0) {
                DB::table('task_hotel_details')->whereIn('task_id', $taskIds)->delete();
                $this->info("Deleted {$hotelDetailsCount} task hotel details.");
            }

            // 10. Reset client credits (only for clients who had task-related invoices)
            $clientIds = Invoice::whereIn('id', $invoiceIdsToDelete ?? [])->pluck('client_id')->unique()->toArray();
            if (!empty($clientIds)) {
                $clientsCount = Client::whereIn('id', $clientIds)->count();
                Client::whereIn('id', $clientIds)->update(['credit' => 0]);
                $this->info("Reset credit for {$clientsCount} clients who had task-related invoices.");
            }

            // 11. Finally, delete the tasks themselves
            $tasksCount = Task::whereIn('id', $taskIds)->count();
            if ($tasksCount > 0) {
                Task::whereIn('id', $taskIds)->delete();
                $this->info("Deleted {$tasksCount} tasks.");
            }

            // 12. Clean up invoice sequence if needed (only if it's task-related)
            if (DB::getSchemaBuilder()->hasTable('invoice_sequence')) {
                // Only clear if there are no remaining invoices, or implement more specific logic
                $remainingInvoices = Invoice::count();
                if ($remainingInvoices === 0) {
                    DB::table('invoice_sequence')->truncate();
                    $this->info("Reset invoice sequence (no invoices remaining).");
                }
            }

            //13. Clean up journal entry that have task id but the task somehow does not exist
            $orphanedJournalEntries = JournalEntry::whereNotNull('task_id');
            $orphanedJournalEntriesCount = $orphanedJournalEntries->count(); 
            if ($orphanedJournalEntriesCount > 0) {
                $orphanedJournalEntries->delete();
                $this->info("Deleted {$orphanedJournalEntriesCount} orphaned journal entries related to tasks that no longer exist.");
            }

            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
            DB::commit();
            
            $successMessage = $companyFilter ? 
                "✅ Task-related data cleanup for company '{$companyName}' completed successfully!" :
                '✅ Task-related data cleanup completed successfully!';
            $this->info($successMessage);

        } catch (Exception $e) {
            DB::rollback();
            $this->error('❌ Error during cleanup: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}