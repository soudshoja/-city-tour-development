<?php

namespace  App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use App\Models\Task;
use App\Http\Controllers\TaskController;
use App\Models\Transaction;
use App\Models\Supplier;
use Carbon\Carbon;

use function Laravel\Prompts\error;

class UpdateHotelTaskWithSupplierPayDateCOA extends Command
{
    protected $signature = 'app:update-hotel-supplier-pay-date-with-coa
                                { --supplier= : The ID of the supplier to filter the mechanism operation.}
                                { --reference= : The reference of the hotel task within the supplier}
                            ';
    protected $description = 'Update hotel task status and its supplier-pay-date then touch the COA';

    public function handle()
    {
        $supplierId = $this->option('supplier');
        $reference  = $this->option('reference');

        Log::info('Starting to update Hotel task with reference ' . $reference . ' from supplier ' . $supplierId . ' with supplier_pay_date');

        if (!$supplierId) {
            $this->error('Supplier ID is required when using this command');
            return;
        }

        if (!$reference) {
            $this->error('Task reference is required when using this command');
            return;
        }

        if ($this->option('supplier') && $this->option('reference')) {
            $task = Task::where('type', 'hotel')
                ->where('supplier_id', $supplierId)
                ->where('reference', $reference)
                ->first();

            if (!$task) {
                $this->error("No hotel task found for supplier {$supplierId} with reference {$reference}");
                return;
            }

            $status = $task->status;
            $issuedDate = $task->issued_date;
            $cancellationDeadline = $task->cancellation_deadline;
            $supplierPayDate = $task->supplier_pay_date;

            if ($status == 'issued') {
                Log::info('Task status is issued. Cannot proceed the rest of the command process.');
                $this->error('Status is issued. Cannot proceed determining the SupplierPayDate.');
                return;
            }

            if (empty($supplierPayDate)) {
                Log::info('SupplierPayDate is missing for task '. $task->reference . '. Checking IssuedDate and CancellationDeadline.');

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
                $task->status = 'issued';
                $task->updated_at = now();
                $task->save();

                $response = new TaskController();
                
                try {
                    $response->processTaskFinancial($task);
                    Log::info('Processed COA for Task ID ' . $task->id);
                } catch (\Throwable $e) {
                    Log::error('Failed to process COA for Task ID ' . $task->id . ' : ' . $e->getMessage());
                }

                Log::info('Task without SupplierPayDate has been updated: ', [
                    'TaskID'               => $task->id,
                    'TaskReference'        => $task->reference,
                    'Status'               => $task->status,
                    'IssuedDate'           => $task->issued_date,
                    'CancellationDeadline' => $task->cancellation_deadline,
                    'SupplierPayDate'      => $task->supplier_pay_date,
                ]);
            } else {
                Log::info('SupplierPayDate is not null. Cannot proceed the rest of the command process.');
                $this->error('SupplierPayDate is already exist for task ' . $task->reference);
                return;
            }
        }
        $this->info('Hotel with task reference ' . $task->reference . ' has its SupplierPayDate updated to the mechanism.');
    }
}
