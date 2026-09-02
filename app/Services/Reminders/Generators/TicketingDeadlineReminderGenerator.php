<?php

declare(strict_types=1);

namespace App\Services\Reminders\Generators;

use App\Models\Reminder;
use Illuminate\Support\Facades\Artisan;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I): "ticketing_deadline every 15 min, sourced from tasks.deadline_at
 * and hold_reminder_offsets_hours (W6.S)". W6.U already shipped exactly this generator as its own
 * standalone command, {@see \App\Console\Commands\GenerateHoldDeadlineReminders}
 * ("reminder:generate-deadlines") -- idempotent per (task_id, offset), company-scoped, tested
 * (GenerateHoldDeadlineRemindersTest). Rather than re-implement the same offset/idempotency logic
 * a second time under this interface, this generator delegates to that command via `Artisan::call`
 * so `reminder:generate --kind=ticketing_deadline` (and `--kind=all`) reach the SAME, already
 * -verified code path the dedicated 15-minute schedule entry also calls directly -- both are kept
 * in routes/console.php (see that file's own P2.5.I block) rather than only reachable through this
 * dispatcher, since `reminder:generate-deadlines` is its own documented, independently-tested
 * command and this wave does not remove it.
 */
final class TicketingDeadlineReminderGenerator implements ReminderGeneratorInterface
{
    public function kind(): string
    {
        return Reminder::KIND_TICKETING_DEADLINE;
    }

    public function generate(?int $companyId): array
    {
        $before = (int) Reminder::where('reminder_kind', $this->kind())->max('id');

        Artisan::call('reminder:generate-deadlines', $companyId !== null ? ['--company' => $companyId] : []);

        $created = Reminder::where('reminder_kind', $this->kind())->where('id', '>', $before)->count();

        return ['created' => $created, 'skipped' => 0];
    }
}
