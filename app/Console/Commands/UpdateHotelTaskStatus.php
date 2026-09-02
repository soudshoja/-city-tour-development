<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use App\Models\Task;
use App\Services\TaskStatusService;
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

                // W7.Y fix (gate item 4, BLOCKER): was `(new TaskController())->
                // processTaskFinancial($task)` directly -- bypassing TaskStatusService::
                // dispatchFinancial()'s engine-ON interception entirely, regardless of the flag,
                // for this scheduled (every 15 min) hotel-deadline sweep. dispatchFinancial()
                // already intercepts `status === 'issued'` (set just above) when the engine is ON
                // for this task's company, routing through issue() instead; OFF falls straight
                // through to the unchanged processTaskFinancial() -- byte-identical to what this
                // call site did before. Container-resolved (matches every other W6/W7 dispatch
                // call site) rather than `new`, since (unlike ClientController::addCredit(), which
                // has a frozen `new` caller elsewhere) nothing calls this command's own handle()
                // via a bare `new`.
                try {
                    app(TaskStatusService::class)->dispatchFinancial($task);
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
