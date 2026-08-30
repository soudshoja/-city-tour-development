<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Models\Task;
use App\Services\TaskStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * W6.U "Reminders" (owner addition, 2026-08-28). CREATE-only -- {@see \App\Console\Commands\SendReminders}
 * ("process:reminder") keeps owning the SEND step, unchanged. Scans `on hold`/`confirmed` tasks
 * with `deadline_at` set and, for each configured offset in the company option
 * `hold_reminder_offsets_hours` (default "24,2"), creates one `Reminder` row idempotently keyed on
 * `(task_id, offset)` -- a second run never duplicates.
 */
class GenerateHoldDeadlineReminders extends Command
{
    protected $signature = 'reminder:generate-deadlines {--company= : Limit to one company id}';

    protected $description = 'Create ticketing-deadline reminders for on-hold/confirmed tasks approaching their deadline_at (idempotent, create-only).';

    public function handle(TaskStatusService $service): int
    {
        $query = Task::query()
            ->whereIn('status', ['on hold', 'confirmed'])
            ->whereNotNull('deadline_at');

        if ($companyId = $this->option('company')) {
            $query->where('company_id', (int) $companyId);
        }

        $tasks = $query->get();
        $created = 0;
        $skipped = 0;

        foreach ($tasks as $task) {
            $companyId = (int) $task->company_id;
            $offsets = $service->holdReminderOffsetsHours($companyId);
            $nudgeClient = $service->holdClientNudge($companyId);
            $deadline = Carbon::parse($task->deadline_at);

            foreach ($offsets as $offsetHours) {
                // Idempotency key: (task_id, offset) -- the offset itself is not a stored column,
                // so it is derived back from `scheduled_at = deadline_at - offset hours`, the
                // exact value this same command always writes for a given (task, offset) pair.
                // deadline_at can move (follow-up tab's "extend deadline" action), so a stale row
                // from a NOW-obsolete deadline must not suppress a fresh one for the current
                // deadline -- hence the explicit scheduled_at match rather than a bare
                // (task_id, reminder_kind) existence check.
                $scheduledAt = $deadline->copy()->subHours($offsetHours);

                $exists = Reminder::where('task_id', $task->id)
                    ->where('reminder_kind', 'ticketing_deadline')
                    ->where('scheduled_at', $scheduledAt)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                if ($task->agent_id === null || $task->client_id === null) {
                    Log::warning('reminder.generate_deadlines_skipped_missing_party', [
                        'task_id' => $task->id,
                        'agent_id' => $task->agent_id,
                        'client_id' => $task->client_id,
                    ]);
                    $skipped++;

                    continue;
                }

                Reminder::create([
                    'target_type' => 'task',
                    'reminder_kind' => 'ticketing_deadline',
                    'task_id' => $task->id,
                    'agent_id' => $task->agent_id,
                    'client_id' => $task->client_id,
                    'send_to_agent' => true,
                    'send_to_client' => $nudgeClient,
                    'frequency' => 'once',
                    'scheduled_at' => $scheduledAt,
                    'status' => 'pending',
                    'is_active' => true,
                ]);

                $created++;
            }
        }

        $this->info("Created {$created} reminder(s), skipped {$skipped} already-existing offset(s).");
        Log::info('reminder.generate_deadlines_completed', ['created' => $created, 'skipped' => $skipped]);

        return 0;
    }
}
