<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Http\Controllers\TaskController;
use Carbon\Carbon;

class UpdateHotelTaskWithSupplierPayDateOnly extends Command
{
    protected $signature = 'app:update-hotel-supplier-pay-date-only
                                {--supplier= : The ID of the supplier to use this command}
                            ';
    protected $description = 'Update the existing hotel task with of status issued for its supplier pay date without creating new COA';

    public function handle()
    {
        $supplierId = $this->option('supplier');

        Log::info('Starting to update the supplier_pay_date for issued Hotel tasks');

        if (!$supplierId) {
            $this->error('Supplier ID is required when using this command');
            return;
        }

        $tasks = Task::where('type', 'hotel')
            ->where('supplier_id', $supplierId)
            ->where('status', 'issued')
            ->whereNull('supplier_pay_date')
            ->get();

        if (!$tasks) {
            $this->error('No hotel task found for supplier ' . $supplierId);
            return;
        }

        $updated = 0;
        $status = $tasks->status;
        $issuedDate = $tasks->issued_date;
        $cancellationDeadline = $tasks->cancellation_deadline;
        $supplierPayDate = $tasks->supplier_pay_date;
        
        foreach ($tasks as $task) {
            $issuedDate           = $task->issued_date;
            $cancellationDeadline = $task->cancellation_deadline;

            if (empty($issuedDate)) {
                Log::warning('IssuedDate missing, skipping task', ['task_id' => $task->id, 'reference' => $task->reference]);
                continue;
            }

            if (empty($issuedDate)) {
                Log::info('IssuedDate is required. Cannot proceed the rest of the command process.');

                $this->error('IssuedDate is missing. Cannot proceed determining the SupplierPayDate.');
                return;
            } elseif (empty($cancellationDeadline)) {
                Log::info('Status is ' . $status . '. CancellationDeadline is missing. Proceed to use IssuedDate ' . $issuedDate . ' as the SupplierPayDate');

                $task->supplier_pay_date = $issuedDate;
            } elseif ($cancellationDeadline) {
                Log::info('Status is ' . $status . '. CancellationDeadline is present. Determining the SupplierPayDate based on IssuedDate ' . $issuedDate);

                if ($cancellationDeadline <= $issuedDate) {
                    Log::info('SupplierPayDate is using IssuedDate');
                    $supplierPayDate = $issuedDate;
                } elseif ($cancellationDeadline > $issuedDate) {
                    Log::info('SupplierPayDate is using CancellationDeadline');
                    $supplierPayDate = $cancellationDeadline;
                }

                $task->supplier_pay_date =  $supplierPayDate;
            }

            $task->supplier_pay_date = $supplierPayDate;
            $task->updated_at = now();
            $task->save();

            JournalEntry::where('task_id', $task->id)->update([
                'transaction_date' => $supplierPayDate,
                'updated_at'       => now(),
            ]);


            $transactionIds = JournalEntry::where('task_id', $task->id)
                ->whereNotNull('transaction_id')
                ->distinct()
                ->pluck('transaction_id');

            if ($transactionIds->isNotEmpty()) {
                Transaction::whereIn('id', $transactionIds)->update([
                    'transaction_date' => $supplierPayDate,
                    'updated_at'       => now(),
                ]);
            }

            Log::info('SupplierPayDate updated without creating new COA', [
                'task_id'               => $task->id,
                'task_reference'        => $task->reference,
                'issued_date'           => $issuedDate,
                'cancellation_deadline' => $cancellationDeadline,
                'supplier_pay_date'     => $supplierPayDate,
                'updated_at'            => $task->updated_at,
            ]);

            $updated++;
        }

        $this->info("Updated {$updated} task(s) for supplier {$supplierId}.");
    }
}
