<?php

declare(strict_types=1);

namespace App\Services\Reminders\Generators;

use App\Models\Payment;
use App\Models\Reminder;
use App\Services\Reminders\ReminderOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I): "payment_link_uninvoiced twice daily". A payment is an open
 * item here in the same ledger-open-item sense the rest of this wave uses the term: `completed`
 * (money actually received) but `invoice_id IS NULL` (never turned into an invoice) -- read
 * directly off `payments`, never `accounts.actual_balance`. Nudges the AGENT, not the client (the
 * client already paid; it is the agent's own invoicing step that is outstanding).
 *
 * `dedupe_key` includes a half-day slot (AM/PM by the current hour) so the twice-daily cadence
 * can fire both runs on a genuinely still-uninvoiced payment without either run duplicating the
 * other's row.
 */
final class PaymentLinkUninvoicedReminderGenerator implements ReminderGeneratorInterface
{
    public function kind(): string
    {
        return Reminder::KIND_PAYMENT_LINK_UNINVOICED;
    }

    public function generate(?int $companyId): array
    {
        $now = Carbon::now();
        $slot = $now->hour < 12 ? 'AM' : 'PM';

        $payments = Payment::query()
            ->where('status', 'completed')
            ->whereNull('invoice_id')
            ->when($companyId !== null, fn (Builder $q) => $q->where('company_id', $companyId))
            ->when($companyId === null, fn (Builder $q) => $q->whereNotNull('company_id'))
            ->with('client.agent.branch')
            ->get();

        if ($payments->isEmpty()) {
            return ['created' => 0, 'skipped' => 0];
        }

        $created = 0;
        $skipped = 0;

        foreach ($payments as $payment) {
            $resolvedCompanyId = (int) ($payment->company_id ?? $companyId ?? 0);
            if ($resolvedCompanyId === 0 || ! ReminderOptions::enabled($resolvedCompanyId, $this->kind())) {
                $skipped++;

                continue;
            }

            if ($payment->agent_id === null || $payment->client_id === null) {
                $skipped++;

                continue;
            }

            $dedupeKey = "payment_link_uninvoiced:{$payment->id}:{$now->toDateString()}:{$slot}";
            if (Reminder::where('dedupe_key', $dedupeKey)->exists()) {
                $skipped++;

                continue;
            }

            $scheduledAt = ReminderOptions::shiftForQuietHours($resolvedCompanyId, $now->copy());

            try {
                Reminder::create([
                    'company_id' => $resolvedCompanyId,
                    'target_type' => 'payment',
                    'reminder_kind' => $this->kind(),
                    'payment_id' => $payment->id,
                    'agent_id' => $payment->agent_id,
                    'client_id' => $payment->client_id,
                    'send_to_agent' => true,
                    'send_to_client' => false,
                    'frequency' => 'once',
                    'channel' => ReminderOptions::channel($resolvedCompanyId, $this->kind()),
                    'scheduled_at' => $scheduledAt,
                    'status' => Reminder::STATUS_PENDING,
                    'is_active' => true,
                    'dedupe_key' => $dedupeKey,
                ]);
                $created++;
            } catch (\Illuminate\Database\QueryException) {
                $skipped++;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
