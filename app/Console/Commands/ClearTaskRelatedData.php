<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Task;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Payment;
use App\Models\Transaction;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearTaskRelatedData extends Command
{
    protected $signature = 'tasks:clear-related-data {--force : Force the operation without confirmation}';

    protected $description = 'Clear all data related to tasks while preserving non-task related records';

    public function handle()
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will delete all task-related data. Do you wish to continue?')) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        $this->info('Starting task-related data cleanup...');

        DB::beginTransaction();

        try {
            // Get all task IDs before deletion
            $taskIds = Task::pluck('id')->toArray();
            $this->info('Found ' . count($taskIds) . ' tasks to process.');

            if (empty($taskIds)) {
                $this->info('No tasks found. Nothing to clear.');
                DB::rollback();
                return Command::SUCCESS;
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

            DB::commit();
            $this->info('✅ Task-related data cleanup completed successfully!');

        } catch (Exception $e) {
            DB::rollback();
            $this->error('❌ Error during cleanup: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}