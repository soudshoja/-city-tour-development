<?php

namespace App\Console\Commands;

use App\Enums\TaskSupplierStatus;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TaskController;
use App\Models\Company;
use App\Models\Task;
use App\Services\MagicHolidayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class MagicHolidayCheckStatus extends Command
{
    protected $signature = 'magic:check-status';

    protected $description = 'Check status of magic holiday for every 12 hours and update accordingly';

    public function handle()
    {
        $companies = Company::get();

        foreach($companies as $company){
            $this->info("Checking magic holiday supplier for company: " . $company->name);

            $supplier = $company->suppliers()->where('name', 'Magic Holiday')->first();

            if(!$supplier){
                $this->info("No Magic Holiday supplier found for company: " . $company->name);
                continue;
            } 

            $tasks = Task::where('company_id', $company->id)
                ->where('supplier_id', $supplier->id)
                ->whereDoesntHave('invoiceDetail')
                ->whereDoesntHave('linkedTask')
                ->where('supplier_status', '!=', TaskSupplierStatus::MAGIC_CANCEL)
                ->whereNotIn('status', ['void'])
                ->whereDoesntHave('originalTask', function($query) use ($supplier) {
                    $query->where('supplier_id', $supplier->id);
                })
                ->get();

            if($tasks->isEmpty()){
                $this->info("No tasks found for Magic Holiday supplier in company: " . $company->name);
                continue;
            }

            $this->info("Found " . $tasks->count() . " tasks for Magic Holiday supplier in company: " . $company->name);

            $rateLimitReset = null;

            foreach($tasks as $task){
                if ($task->invoiceDetail){
                    $this->info("Task " . $task->reference . " has invoice detail, skipping.");
                    continue;
                }

                if($task->linkedTask()->exists()){
                    $this->info("Task " . $task->reference . " has linked task, skipping.");
                    continue;
                }


                $voidTaskWithSameReference = Task::where('company_id', $company->id)
                    ->where('supplier_id', $supplier->id)
                    ->where('reference', $task->reference)
                    ->where('status', 'void')
                    ->first();

                if($voidTaskWithSameReference){
                    $this->info("Task " . $task->reference . " has a void task with same reference, skipping.");
                    continue;
                }

                if($task->supplier_status == TaskSupplierStatus::MAGIC_CANCEL){
                    $this->info("Task " . $task->reference . " already has supplier status MAGIC_CANCEL, skipping.");
                    continue;
                }                

                $response = (new MagicHolidayService())->getSingleReservation($task->reference);

                if(!isset($response['status']) || !isset($response['data']) || $response['status'] != 200){
                    $this->error("Something wrong with response for task " . $task->reference, [
                        'response' => $response
                    ]);
                    continue;
                }

                $data = $response['data'];

                $responseStatus = $data['service']['status'];

                $this->info("Task  " . $task->reference . " task status: " . $task->status . ", response status : " . $responseStatus);

                if($responseStatus == 'OK' && $task->status == 'issued') continue;

                if($responseStatus == TaskSupplierStatus::MAGIC_CANCEL->value){
                    $this->handleCancelledTask($task, $company, $supplier);
                }
            }
        }
    }

    private function handleCancelledTask($originalTask, $company, $supplier)
    {
        $voidTaskData = [
            'client_id' => $originalTask->client_id,
            'agent_id' => $originalTask->agent_id,
            'company_id' => $company->id,
            'type' => $originalTask->type,
            'status' => 'void',
            'supplier_status' => TaskSupplierStatus::MAGIC_CANCEL->value,
            'client_name' => $originalTask->client_name,
            'reference' => $originalTask->reference,
            'duration' => $originalTask->duration,
            'payment_type' => $originalTask->payment_type,
            'price' => $originalTask->price,
            'tax' => $originalTask->tax,
            'surcharge' => $originalTask->surcharge,
            'total' => $originalTask->total,
            'cancellation_policy' => $originalTask->cancellation_policy,
            'cancellation_deadline' => $originalTask->cancellation_deadline,
            'additional_info' => $originalTask->additional_info,
            'supplier_id' => $supplier->id,
            'venue' => $originalTask->venue,
            'invoice_price' => $originalTask->invoice_price,
            'voucher_status' => $originalTask->voucher_status,
            'refund_date' => $originalTask->refund_date,
            'issued_date' => $originalTask->issued_date,
            'original_task_id' => $originalTask->id,
            'currency' => $originalTask->currency,
            'original_price' => $originalTask->original_price,
            'original_total' => $originalTask->original_total,
            'original_tax' => $originalTask->original_tax,
            'original_surcharge' => $originalTask->original_surcharge,
            'original_currency' => $originalTask->original_currency,
            'exchange_currency' => $originalTask->exchange_currency,
            'payment_method_account_id' => $originalTask->payment_method_account_id,
            'supplier_pay_date' => $originalTask->supplier_pay_date,
            'enabled' => true,
        ];

        if ($originalTask->type === 'hotel' && $originalTask->hotelDetails) {
            $voidTaskData['task_hotel_details'] = [
                'hotel_name' => $originalTask->hotelDetails->hotel->name,
                'hotel_country' => $originalTask->hotelDetails->hotel_country,
                'room_reference' => $originalTask->hotelDetails->room_reference,
                'booking_time' => $originalTask->hotelDetails->booking_time,
                'check_in' => $originalTask->hotelDetails->check_in,
                'check_out' => $originalTask->hotelDetails->check_out,
                'room_number' => $originalTask->hotelDetails->room_number,
                'room_type' => $originalTask->hotelDetails->room_type,
                'room_amount' => $originalTask->hotelDetails->room_amount,
                'room_details' => $originalTask->hotelDetails->room_details,
                'rate' => $originalTask->hotelDetails->rate,
                'meal_type' => $originalTask->hotelDetails->meal_type,
                'is_refundable' => $originalTask->hotelDetails->is_refundable,
            ];
        }

        $request = new Request($voidTaskData);
        $taskController = new TaskController();

        try {
            $response = $taskController->store($request);
            $responseData = json_decode($response->getContent(), true);

            if ($responseData['status'] === 'success') {
                $this->info("Created void task for original task {$originalTask->reference}");
                $originalTask->update(['supplier_status' => TaskSupplierStatus::MAGIC_CANCEL]);
                $this->info("Updated original task {$originalTask->reference} supplier status to MAGIC_CANCEL");
            } else {
                $this->error("Failed to create void task for {$originalTask->reference}: " . ($responseData['message'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            $this->error("Exception creating void task for {$originalTask->reference}: " . $e->getMessage());
        }
    }
}
