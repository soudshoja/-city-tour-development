<?php

namespace App\Listeners;

use App\Events\CheckConfirmedOrIssuedTask;
use App\Http\Controllers\TaskController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessTaskFinancials implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(CheckConfirmedOrIssuedTask $event): void
    {
        $task = $event->task;
        $reason = $event->reason;

        Log::info("Processing task financials via event", [
            'task_id' => $task->id,
            'reference' => $task->reference,
            'status' => $task->status,
            'reason' => $reason
        ]);

        try {
            // Only process financials for specific statuses
            if (in_array($task->status, ['issued', 'reissued', 'void', 'refund', 'emd'])) {
                
                // Create a temporary controller instance to access the financial processing method
                $controller = new TaskController();
                
                // Use reflection to call the private method
                $reflection = new \ReflectionClass($controller);
                $method = $reflection->getMethod('processTaskFinancial');
                $method->setAccessible(true);
                
                $method->invoke($controller, $task);
                
                Log::info("Successfully processed task financials", [
                    'task_id' => $task->id,
                    'reference' => $task->reference,
                    'status' => $task->status
                ]);
            } else {
                Log::info("Skipping financial processing - status not eligible", [
                    'task_id' => $task->id,
                    'reference' => $task->reference,
                    'status' => $task->status
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to process task financials", [
                'task_id' => $task->id,
                'reference' => $task->reference,
                'status' => $task->status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Re-throw the exception so the job can be retried
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(CheckConfirmedOrIssuedTask $event, \Throwable $exception): void
    {
        Log::error("Task financial processing job failed permanently", [
            'task_id' => $event->task->id,
            'reference' => $event->task->reference,
            'error' => $exception->getMessage()
        ]);
    }
}
