<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Task;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\TaskFlightDetail;
use App\Models\TaskHotelDetail;
use App\Models\TaskInsuranceDetail;
use App\Models\Company;
use App\Models\Supplier;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearTaskRelatedData extends Command
{
    protected $signature = 'tasks:clear-related-data {--force : Force the operation without confirmation} 
        {--company= : Clear tasks for specific company (ID or name)}
        {--supplier= : Clear tasks for specific supplier (ID or name) - requires --company option}
        {--no-invoice : Only delete tasks that have NOT been invoiced}';

    protected $description = 'Hard delete all data related to tasks (including soft deleted records) while preserving non-task related records. Use --supplier option only with --company option.';

    public function handle()
    {
        if(env('APP_ENV') == 'production') {
            $this->error('This command is not allowed in production environments.');
            return Command::FAILURE;
        }

        $companyFilter = $this->option('company');
        $supplierFilter = $this->option('supplier');
        $companyId = null;
        $companyName = '';
        $supplierId = null;
        $supplierName = '';

        // Validate that supplier option is only used with company option
        if ($supplierFilter && !$companyFilter) {
            $this->error('The --supplier option can only be used together with the --company option.');
            return Command::FAILURE;
        }

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

        // Handle supplier filter (only if company is selected)
        if ($supplierFilter && $companyId) {
            // Check if it's a numeric ID
            if (is_numeric($supplierFilter)) {
                $supplier = Supplier::find($supplierFilter);
                if (!$supplier) {
                    $this->error("Supplier with ID {$supplierFilter} not found.");
                    return Command::FAILURE;
                }
                $supplierId = $supplier->id;
                $supplierName = $supplier->name;
            } else {
                // Search by name using LIKE
                $suppliers = Supplier::where('name', 'LIKE', "%{$supplierFilter}%")->get();
                
                if ($suppliers->isEmpty()) {
                    $this->error("No suppliers found matching '{$supplierFilter}'.");
                    return Command::FAILURE;
                }
                
                if ($suppliers->count() > 1) {
                    $this->info("Multiple suppliers found:");
                    foreach ($suppliers as $supplier) {
                        $this->line("ID: {$supplier->id} - Name: {$supplier->name}");
                    }
                    $supplierId = $this->ask('Please enter the Supplier ID you want to use');
                    $selectedSupplier = Supplier::find($supplierId);
                    if (!$selectedSupplier) {
                        $this->error("Invalid Supplier ID selected.");
                        return Command::FAILURE;
                    }
                    $supplierName = $selectedSupplier->name;
                } else {
                    $supplier = $suppliers->first();
                    $supplierId = $supplier->id;
                    $supplierName = $supplier->name;
                }
            }
            
            $this->info("Selected Supplier: {$supplierName} (ID: {$supplierId})");
        }

        if (!$this->option('force')) {
            $confirmMessage = '';
            if ($companyFilter && $supplierFilter) {
                $confirmMessage = "This will PERMANENTLY DELETE all task-related data (including soft deleted records) for company '{$companyName}' and supplier '{$supplierName}'. Do you wish to continue?";
            } elseif ($companyFilter) {
                $confirmMessage = "This will PERMANENTLY DELETE all task-related data (including soft deleted records) for company '{$companyName}'. Do you wish to continue?";
            } else {
                $confirmMessage = 'This will PERMANENTLY DELETE all task-related data (including soft deleted records). Do you wish to continue?';
            }
                
            if (!$this->confirm($confirmMessage)) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        $infoMessage = '';
        if ($companyFilter && $supplierFilter) {
            $infoMessage = "Starting HARD DELETE of task-related data for company '{$companyName}' and supplier '{$supplierName}'...";
        } elseif ($companyFilter) {
            $infoMessage = "Starting HARD DELETE of task-related data for company '{$companyName}'...";
        } else {
            $infoMessage = 'Starting HARD DELETE of task-related data...';
        }
        $this->info($infoMessage);

        DB::beginTransaction();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        try {
            // Get all task IDs before deletion (filtered by company and/or supplier if specified)
            // Include soft deleted tasks since we want to hard delete everything
            $taskQuery = Task::withTrashed();
            if ($companyId) {
                $taskQuery->where('company_id', $companyId);
            }
            if ($supplierId) {
                $taskQuery->where('supplier_id', $supplierId);
            }
            if ($this->option('no-invoice')) {
                $taskQuery->whereDoesntHave('invoiceDetail');
            }
            $taskIds = $taskQuery->pluck('id')->toArray();
            
            $taskCountMessage = '';
            if ($companyFilter && $supplierFilter) {
                $taskCountMessage = "Found " . count($taskIds) . " tasks (including soft deleted) for company '{$companyName}' and supplier '{$supplierName}' to process.";
            } elseif ($companyFilter) {
                $taskCountMessage = "Found " . count($taskIds) . " tasks (including soft deleted) for company '{$companyName}' to process.";
            } else {
                $taskCountMessage = 'Found ' . count($taskIds) . ' tasks (including soft deleted) to process.';
            }
            $this->info($taskCountMessage);

            if (empty($taskIds)) {
                $noTasksMessage = '';
                if ($companyFilter && $supplierFilter) {
                    $noTasksMessage = "No tasks found for company '{$companyName}' and supplier '{$supplierName}'. Nothing to clear.";
                } elseif ($companyFilter) {
                    $noTasksMessage = "No tasks found for company '{$companyName}'. Nothing to clear.";
                } else {
                    $noTasksMessage = 'No tasks found. Nothing to clear.';
                }
                $this->info($noTasksMessage);
                DB::rollback();
                // return Command::SUCCESS;
            }

            $journalEntries = JournalEntry::withTrashed()->whereIn('task_id', $taskIds)->get();

            if( !empty($journalEntries)) {
                // Clear journal entries related to tasks
                $transactionsId = $journalEntries->pluck('transaction_id')->toArray();
                $transactions = Transaction::withTrashed()->whereIn('id', $transactionsId)->get();
                
                if ($transactions->isNotEmpty()) {
                    // Hard delete transactions related to journal entries
                    $transactionsCount = $transactions->count();
                    $transactions->each(function ($transaction) {
                        $transaction->forceDelete();
                    });
                    $this->info("Hard deleted {$transactionsCount} transactions related to journal entries.");
                }

                $journalEntries->each(function ($journalEntry) {
                    $journalEntry->forceDelete();
                });

                $this->info("Hard deleted " . count($journalEntries) . " journal entries related to tasks.");
            }

            // 1. Clear invoice details related to tasks
            $invoiceDetailsCount = InvoiceDetail::withTrashed()->whereIn('task_id', $taskIds)->count();
            if ($invoiceDetailsCount > 0) {
                InvoiceDetail::withTrashed()->whereIn('task_id', $taskIds)->forceDelete();
                $this->info("Hard deleted {$invoiceDetailsCount} invoice details related to tasks.");
            }

            // 2. Get invoices that have task-related invoice details (now deleted)
            // and invoices that might be empty after deletion
            $taskRelatedInvoiceIds = Invoice::withTrashed()->whereHas('invoiceDetails', function($query) use ($taskIds) {
                $query->withTrashed()->whereIn('task_id', $taskIds);
            })->pluck('id')->toArray();

            // Also get invoices that no longer have any invoice details
            $emptyInvoiceIds = Invoice::withTrashed()->whereDoesntHave('invoiceDetails')->pluck('id')->toArray();
            
            $invoiceIdsToDelete = array_unique(array_merge($taskRelatedInvoiceIds, $emptyInvoiceIds));

            if (!empty($invoiceIdsToDelete)) {
                // 3. Clear payments related to task invoices
                $paymentsCount = Payment::withTrashed()->whereIn('invoice_id', $invoiceIdsToDelete)->count();
                if ($paymentsCount > 0) {
                    Payment::withTrashed()->whereIn('invoice_id', $invoiceIdsToDelete)->forceDelete();
                    $this->info("Hard deleted {$paymentsCount} payments related to task invoices.");
                }

                // 4. Clear transactions related to task invoices
                $transactionsCount = Transaction::withTrashed()->whereIn('invoice_id', $invoiceIdsToDelete)->count();
                if ($transactionsCount > 0) {
                    Transaction::withTrashed()->whereIn('invoice_id', $invoiceIdsToDelete)->forceDelete();
                    $this->info("Hard deleted {$transactionsCount} transactions related to task invoices.");
                }

                // 5. Clear journal entries related to task invoices (if they exist)
                $journalEntriesInvoiceCount = JournalEntry::withTrashed()->whereIn('invoice_id', $invoiceIdsToDelete)->count();
                if ($journalEntriesInvoiceCount > 0) {
                    JournalEntry::withTrashed()->whereIn('invoice_id', $invoiceIdsToDelete)->forceDelete();
                    $this->info("Hard deleted {$journalEntriesInvoiceCount} journal entries related to task invoices.");
                }

                // 6. Clear invoice partials related to task invoices (if they exist)
                if (DB::getSchemaBuilder()->hasTable('invoice_partials')) {
                    $invoicePartialsCount = DB::table('invoice_partials')
                        ->whereIn('invoice_id', $invoiceIdsToDelete)
                        ->count();
                    if ($invoicePartialsCount > 0) {
                        DB::table('invoice_partials')->whereIn('invoice_id', $invoiceIdsToDelete)->delete();
                        $this->info("Hard deleted {$invoicePartialsCount} invoice partials related to task invoices.");
                    }
                }

                // 7. Delete the invoices themselves
                $invoicesCount = Invoice::withTrashed()->whereIn('id', $invoiceIdsToDelete)->count();
                if ($invoicesCount > 0) {
                    Invoice::withTrashed()->whereIn('id', $invoiceIdsToDelete)->forceDelete();
                    $this->info("Hard deleted {$invoicesCount} invoices related to tasks.");
                }
            }

            // 8. Clear task flight details
            $flightDetailsCount = TaskFlightDetail::withTrashed()->whereIn('task_id', $taskIds)->count();
            if ($flightDetailsCount > 0) {
                TaskFlightDetail::withTrashed()->whereIn('task_id', $taskIds)->forceDelete();
                $this->info("Hard deleted {$flightDetailsCount} task flight details.");
            }

            // 9. Clear task hotel details
            $hotelDetailsCount = TaskHotelDetail::withTrashed()->whereIn('task_id', $taskIds)->count();
            if ($hotelDetailsCount > 0) {
                TaskHotelDetail::withTrashed()->whereIn('task_id', $taskIds)->forceDelete();
                $this->info("Hard deleted {$hotelDetailsCount} task hotel details.");
            }

            $insuranceDetailsCount = TaskInsuranceDetail::withTrashed()->whereIn('task_id', $taskIds)->count();
            if ($insuranceDetailsCount > 0) {
                TaskInsuranceDetail::withTrashed()->whereIn('task_id', $taskIds)->forceDelete();
                $this->info("Hard deleted {$insuranceDetailsCount} task insurance details.");
            }

            // 10. Reset client credits (only for clients who had task-related invoices)
            $clientIds = Invoice::withTrashed()->whereIn('id', $invoiceIdsToDelete ?? [])->pluck('client_id')->unique()->toArray();
            if (!empty($clientIds)) {
                $clientsCount = Client::whereIn('id', $clientIds)->count();
                Client::whereIn('id', $clientIds)->update(['credit' => 0]);
                $this->info("Reset credit for {$clientsCount} clients who had task-related invoices.");
            }

            // 11. Finally, hard delete the tasks themselves
            $tasksCount = Task::withTrashed()->whereIn('id', $taskIds)->count();
            if ($tasksCount > 0) {
                Task::withTrashed()->whereIn('id', $taskIds)->forceDelete();
                $this->info("Hard deleted {$tasksCount} tasks.");
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
            $orphanedJournalEntries = JournalEntry::whereNotNull('task_id')->whereDoesntHave('task');
            $orphanedJournalEntriesCount = $orphanedJournalEntries->count(); 
            if ($orphanedJournalEntriesCount > 0) {
                $orphanedJournalEntries->delete();
                $orphanedJournalEntries->forceDelete();

                $this->info("Hard Deleted {$orphanedJournalEntriesCount} orphaned journal entries related to tasks that no longer exist.");
            }

            //13. Clean up journal entry that have task id but the task somehow does not exist
            $orphanedJournalEntries = JournalEntry::whereNotNull('task_id')->whereDoesntHave('task');
            $orphanedJournalEntriesCount = $orphanedJournalEntries->count(); 
            if ($orphanedJournalEntriesCount > 0) {
                $orphanedJournalEntries->delete();
                $this->info("Deleted {$orphanedJournalEntriesCount} orphaned journal entries related to tasks that no longer exist.");
            }

            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
            DB::commit();
            
            $successMessage = '';
            if ($companyFilter && $supplierFilter) {
                $successMessage = "✅ HARD DELETE of task-related data for company '{$companyName}' and supplier '{$supplierName}' completed successfully!";
            } elseif ($companyFilter) {
                $successMessage = "✅ HARD DELETE of task-related data for company '{$companyName}' completed successfully!";
            } else {
                $successMessage = '✅ HARD DELETE of task-related data completed successfully!';
            }
            $this->info($successMessage);

        } catch (Exception $e) {
            DB::rollback();
            $this->error('❌ Error during cleanup: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}