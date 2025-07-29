<?php

namespace App\Jobs;

use App\Http\Controllers\SupplierController;
use App\Models\Task;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateMagicHolidayIssuedDateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $task;
    
    public $timeout = 60; // 1 minute timeout
    public $tries = 3; // Retry up to 3 times

    /**
     * Create a new job instance.
     */
    public function __construct(Task $task)
    {
        $this->task = $task;
        $this->onQueue('magic_holiday_updates'); // Use a dedicated queue
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::channel('magic_holidays')->info('Processing issued date update for task', [
                'task_id' => $this->task->id,
                'reference' => $this->task->reference
            ]);

            $supplierController = new SupplierController();
            
            // Fetch reservation data from Magic Holiday API
            $response = $supplierController->getMagicHoliday($this->task->reference);
            $data = json_decode($response->getContent(), true);

            if (isset($data['status']) && $data['status'] == 'error') {
                throw new Exception('API error: ' . ($data['message'] ?? 'Unknown error'));
            }

            if (!isset($data['data'])) {
                throw new Exception('No data found in API response');
            }

            $reservation = $data['data'];

            // Extract the issued date from the API response
            if (isset($reservation['added']['time'])) {
                $newIssuedDate = Carbon::parse($reservation['added']['time'])->toDateTimeString();
                $oldDate = $this->task->issued_date;
                
                $this->task->issued_date = $newIssuedDate;
                $this->task->save();
                
                Log::channel('magic_holidays')->info('Successfully updated issued_date for task', [
                    'task_id' => $this->task->id,
                    'reference' => $this->task->reference,
                    'old_issued_date' => $oldDate,
                    'new_issued_date' => $newIssuedDate
                ]);
            } else {
                throw new Exception('No added.time found in API response');
            }

        } catch (Exception $e) {
            Log::channel('magic_holidays')->error('Failed to update issued_date for task', [
                'task_id' => $this->task->id,
                'reference' => $this->task->reference,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);
            
            // Re-throw the exception to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::channel('magic_holidays')->error('Job failed permanently for task issued_date update', [
            'task_id' => $this->task->id,
            'reference' => $this->task->reference,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
