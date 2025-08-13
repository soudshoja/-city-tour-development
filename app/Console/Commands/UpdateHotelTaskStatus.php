<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use App\Models\Task;
use App\Http\Controllers\TaskController;
class UpdateHotelTaskStatus extends Command
{
    protected $signature = 'app:update-hotel-status';
    protected $description = 'Update status of hotel tasks based on cancellation deadline';

    public function handle()
    {
        $tasks = Task::where('type', 'hotel')
            ->where('status', '!=', 'issued')
            ->whereNotNull('cancellation_deadline')
            ->get();

        foreach ($tasks as $task) {
            $deadlineRaw = $task->cancellation_deadline;

            // Skip if null, empty, or not a valid datetime
            if (empty($deadlineRaw)) {
                Log::info("Task ID {$task->id} skipped — empty cancellation_deadline.");
                continue;
            }

            try {
                $cancellationDeadline = Date::parse($deadlineRaw);
            } catch (\Exception $e) {
                Log::warning("Task ID {$task->id} skipped — invalid cancellation_deadline: {$deadlineRaw}");
                continue;
            }

            Log::info("Checking cancellation deadline for Task ID {$task->id}: {$cancellationDeadline->toDateTimeString()}");

            if (Date::now()->greaterThanOrEqualTo($cancellationDeadline)) {
                $task->status = 'issued';
                Log::info("Task ID {$task->id} - Deadline passed. Marked as issued.");

                $task->updated_at = now();
                $task->save();
            
                $response = new TaskController();
                try {
                    $response->processTaskFinancial($task);
                    Log::info("Processed COA for Task ID {$task->id}");
                } catch (\Throwable $e) {
                    Log::error("Failed to process COA for Task ID {$task->id}: ".$e->getMessage());
                }
            } else {
                $task->status = 'confirmed';
                Log::info("Task ID {$task->id} - Deadline still valid. Marked as confirmed.");
            }

        }

        $this->info("Hotel task statuses updated.");
    }
}
