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

class UpdateHotelTaskWithSupplierPayDate extends Command
{
    protected $signature = 'app:update-hotel-supplier-pay-date
                                { --supplier= : The ID of the supplier to filter the mechanism operation.}
                                { --reference= : The reference of the hotel task within the supplier}
                            ';
    protected $description = 'Update hotel task status and its supplier-pay-date then touch the COA';

    public function handle()
    {

        $supplierId = $this->option('supplier');
        $reference  = $this->option('reference');

        if (!$supplierId) {
            $this->error('Supplier ID is required when using this operation');
            return;
        }

        if (!$reference) {
            $this->error('Task reference is required when using this operation');
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

            if ($status = 'issued') {
                Log::info('Task status is issued. Cancel the operation.');
                $this->error('Status is issued. No need to proceed.');
            }

            if (!$supplierPayDate) {
                Log::info('Empty SupplierPayDate. Checking Issued Date and Cancellation Deadline');

                if (!$issuedDate) {
                    Log::info('Checking missing data: ', [
                        'Issued Date: ' => $issuedDate,
                    ]);

                    $this->error('Issued date is missing. Cannot proceed determining the supplier pay date');
                } elseif (!$cancellationDeadline) {
                    Log::info('Cancellation Deadline is missing. Proceed to use Issued Date as the Supplier Pay Date');
                    
                    $supplierPayDate = $issuedDate;
                    $task->status = 'issued';
                    $task->updated_at = now();

                    $task->save();
                    
                } elseif ($cancellationDeadline) {
                    Log::info('Checking the crucial data: ', [
                        'Status: ' => $status,
                        'Issued Date: ' => $issuedDate,
                        'Cancellation Deadline: ' => $cancellationDeadline,
                        'Supplier Pay Date: ' => $supplierPayDate,
                    ]);

                    if ($cancellationDeadline <= $issuedDate) {
                        $supplierPayDate = $issuedDate;
                    } elseif ($cancellationDeadline > $issuedDate) {
                        $supplierPayDate = $cancellationDeadline;
                    }

                    $task->status = 'issued';
                    $task->supplier_pay_date =  $supplierPayDate;
                    $task->updated_at = now();

                    $task->save();
                }
                    Log::info('Date in table tasks has been updated.', [
                        'Status: ' => $status,
                        'Issued Date: ' => $issuedDate,
                        'Cancellation Deadline: ' => $cancellationDeadline,
                        'Supplier Pay Date: ' => $supplierPayDate,
                    ]);

                    $response = new TaskController();

                    try {
                        $response->processTaskFinancial($task);
                        Log::info("Processed COA for Task ID {$task->id}");
                    } catch (\Throwable $e) {
                        Log::error("Failed to process COA for Task ID {$task->id}: " . $e->getMessage());
                    }

                    Log::info("Tasks without Supplier Pay Date has been updated: ", [
                        'task_id' => $task->id,
                        'reference' => $task->reference,
                        'status' => $task->status,
                        'issued_date' => $task->issued_date,
                        'cancellation_deadline' => $task->cancellation_deadline,
                        'supplier_pay_date' => $task->supplier_pay_date,
                    ]);

                }
            }
            $this->info("Hotel tasks has been updated to the latest mechanism");
        }
    }
