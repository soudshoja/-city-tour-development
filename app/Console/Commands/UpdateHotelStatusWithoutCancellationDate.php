<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use App\Models\Task;
use App\Http\Controllers\TaskController;
use App\Models\Transaction;

class UpdateHotelStatusWithoutCancellationDate extends Command{
    protected $signature = 'app:update-hotel-without-cancellation-date';
    protected $description = 'Update status of hotel tasks but of those without cancellation date';

    public function handle()
    {
        $tasks = Task::where('type', 'hotel')
            ->where('status', '!=', 'issued')
            ->whereNull('cancellation_deadline')
            ->get();

        foreach ( $tasks as $task)  {
            $deadlineRaw = $task->cancellation_deadline;

            if($deadlineRaw) {
                Log::info("Task ID {$task->id} skipped - there's cancellation deadline.");
                continue;
            }

            Log::info("Checking cancellation deadline for Task ID {$task->id}: {$deadlineRaw}");

            if(empty($deadlineRaw)) {
                $task->status = 'issued';
                Log::info("Task ID {$task->id} - Deadline passed. Marked as issued and has created COA.");

                $task->updated_at = now();
                $task->save();

                $response = new TaskController();

                try {
                    $response->processTaskFinancial($task);
                    Log::info("Processed COA for Task ID {$task->id}");
                } catch (\Throwable $e) {
                    Log::error("Failed to process COA for Task ID {$task->id}: " . $e->getMessage());
                }
                Log::info("Tasks without Cancellation Date: ", [
                'task_id' => $task->id,
                'reference' => $task->reference,
                'status' => $task->status,
                'issued_date' => $task->issued_date,
            ]);
            }
        }

        $this->info("Hotel status for emptied cancellation date has been updated");
    }
}


