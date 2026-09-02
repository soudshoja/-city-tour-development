<?php

declare(strict_types=1);

namespace App\Listeners\Reminders;

use App\Events\Accounting\CommissionUnearned;
use App\Models\Reminder;
use App\Services\Reminders\ReminderOptions;
use Illuminate\Support\Facades\Log;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I) -- the ONE event-driven reminder generator (every other kind is
 * swept by {@see \App\Console\Commands\GenerateReminders}). Idempotent on `dedupe_key =
 * "commission_unearned:{transaction_id}"` -- the un-earn reversal's own transaction id, which by
 * construction can only exist once (PostingService::reverse() never reverses the same
 * transaction twice -- see that class's own idempotency guard), so this listener firing more than
 * once for the same event (e.g. a queued retry, were this ever moved onto a queue) never creates
 * a duplicate row.
 */
final class CreateCommissionUnearnedReminder
{
    public function handle(CommissionUnearned $event): void
    {
        if (! ReminderOptions::enabled($event->companyId, Reminder::KIND_COMMISSION_UNEARNED)) {
            return;
        }

        $dedupeKey = 'commission_unearned:'.$event->transactionId;
        if (Reminder::where('dedupe_key', $dedupeKey)->exists()) {
            return;
        }

        $scheduledAt = ReminderOptions::shiftForQuietHours($event->companyId, now());

        try {
            Reminder::create([
                'company_id' => $event->companyId,
                'target_type' => 'agent',
                'reminder_kind' => Reminder::KIND_COMMISSION_UNEARNED,
                'agent_id' => $event->agentId,
                'client_id' => $event->clientId,
                'invoice_id' => $event->invoiceId,
                'send_to_agent' => true,
                'send_to_client' => false,
                'frequency' => 'once',
                // `message` is repurposed here to carry the snapshotted amount as a plain decimal
                // string (not free-form notes, unlike every other kind's `message` use) --
                // `value`/`unit` are integer/enum columns unfit for a 3-decimal KWD amount, and
                // ReminderMessageRegistry::commissionUnearnedParams() reads it back the same way.
                // See that method's own docblock note.
                'message' => number_format($event->amount, 3, '.', ''),
                'channel' => ReminderOptions::channel($event->companyId, Reminder::KIND_COMMISSION_UNEARNED),
                'scheduled_at' => $scheduledAt,
                'status' => Reminder::STATUS_PENDING,
                'is_active' => true,
                'dedupe_key' => $dedupeKey,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique-constraint race on dedupe_key (two concurrent posts of the same reversal,
            // which PostingService::reverse() itself should already prevent) -- log and move on
            // rather than let a reminder-row failure block the caller's refund post.
            Log::warning('reminder.commission_unearned_dedupe_race', ['dedupe_key' => $dedupeKey, 'error' => $e->getMessage()]);
        }
    }
}
