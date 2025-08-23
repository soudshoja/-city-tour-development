<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\TaskController;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\Supplier;
use App\Models\JournalEntry;
use Carbon\Carbon;

class UpdateTaskWithSupplierPayDate extends Command 
{
    protected $signature = 'app:update-task-with-supplier-pay-date
                                {--supplier= : The ID of the supplier to filter the mechanism operation}
                            ';
    protected $description = 'Update the existing task of other type than hotel with supplier pay date';

    public function handle() 
    {
        $supplierId = $this->option('supplier');

        Log::info('Updating task from supplier ' . $supplierId . ' with supplier_pay_date');

        if (!$supplierId) {
            $this->error('Supplier ID is required when using this command');
            return;
        }

        if ($this->option('supplier')) {
            $tasksQuery = Task::query()
                ->where('type', '!=', 'hotel')
                ->where('supplier_id', $supplierId)
                ->whereNull('supplier_pay_date');

            $tasks = $tasksQuery->get();

            if ($tasks->isEmpty()) {
                $this->warn("No tasks found for supplier {$supplierId} with NULL supplier_pay_date.");
                return;
            }
        }
        $this->info("Found {$tasks->count()} task(s) to update for supplier {$supplierId}.");

        $updated = 0;

        foreach ($tasks as $task) {
            $issuedDate = $task->issued_date;

            if(!$issuedDate) {
                Log::warning('IssuedDate is missing for task ' . $task->reference);
                continue;
            }

            $supplierPayDate = $issuedDate;

            $task->supplier_pay_date = $supplierPayDate;
            $task->updated_at = now();
            $task->save();

            JournalEntry::where('task_id', $task->id)
                        ->update([
                            'transaction_date' => $supplierPayDate,
                            'updated_at' => now(),
                        ]);
            
            $transactionId = JournalEntry::where('task_id', $task->id)
                                ->whereNotNull('transaction_id')
                                ->distinct()
                                ->pluck('transaction_id');
            
            if ($transactionId) {
                Transaction::whereIn('id', $transactionId)
                            ->update([
                                'transaction_date' => $supplierPayDate,
                                'updated_at' => now(),
                            ]);
            }

            Log::info('SupplierPayDate updated for supplier ' . $supplierId, [
                'task_id' => $task->id,
                'task_reference' => $task->reference,
                'task_status' => $task->status,
                'issued_date' => $issuedDate,
                'supplier_pay_date' => $supplierPayDate,
                'updated_at' => $task->updated_at,
            ]);

            $updated++;
        }

        $this->info('Updated ' . $updated . ' task(s) for supplier ' . $supplierId);
    }
}