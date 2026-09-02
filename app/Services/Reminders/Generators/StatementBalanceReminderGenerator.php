<?php

declare(strict_types=1);

namespace App\Services\Reminders\Generators;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Reminder;
use App\Services\Accounting\StatementOptions;
use App\Services\Accounting\StatementService;
use App\Services\Reminders\ReminderOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I): "statement_balance ... daily 09:00 company tz". One reminder
 * per client per day while `net_outstanding` (open items minus unapplied receipts/credits, the
 * same {@see \App\Services\Accounting\StatementService} P2.5.H's own statement screen reads --
 * never `accounts.actual_balance`) stays above tolerance. `dedupe_key` includes the calendar day,
 * so re-running this generator the same day is a no-op; a genuinely new day naturally allows a
 * fresh nudge, capped at one per day by construction.
 *
 * Candidate pool: clients with at least one invoice due on/before today (the same population
 * {@see OverdueInvoiceReminderGenerator} would ever consider "open"), scoped to a single company
 * pass rather than looping StatementService per client system-wide.
 */
final class StatementBalanceReminderGenerator implements ReminderGeneratorInterface
{
    public function __construct(private readonly StatementService $statements) {}

    public function kind(): string
    {
        return Reminder::KIND_STATEMENT_BALANCE;
    }

    public function generate(?int $companyId): array
    {
        $today = Carbon::now()->startOfDay();
        $tolerance = StatementOptions::unsettledTolerance();

        $clientIds = Invoice::query()
            ->where('due_date', '<=', $today)
            ->when($companyId !== null, fn (Builder $q) => $q->whereHas(
                'agent.branch',
                fn (Builder $b) => $b->where('company_id', $companyId)
            ))
            ->distinct()
            ->pluck('client_id')
            ->filter()
            ->values();

        if ($clientIds->isEmpty()) {
            return ['created' => 0, 'skipped' => 0];
        }

        $clients = Client::query()->whereIn('id', $clientIds)->with('agent.branch')->get();

        $created = 0;
        $skipped = 0;

        foreach ($clients as $client) {
            $resolvedCompanyId = (int) ($client->agent?->branch?->company_id ?? $companyId ?? 0);
            if ($resolvedCompanyId === 0 || ! ReminderOptions::enabled($resolvedCompanyId, $this->kind())) {
                $skipped++;

                continue;
            }

            $statement = $this->statements->generate($resolvedCompanyId, StatementService::PARTY_CLIENT, $client->id, $today);
            $netOutstanding = (float) ($statement['totals']['net_outstanding'] ?? 0);
            if ($netOutstanding <= $tolerance) {
                continue; // paid off -- nothing to nudge about.
            }

            if ($client->agent_id === null) {
                $skipped++;

                continue;
            }

            $dedupeKey = "statement_balance:{$client->id}:{$today->toDateString()}";
            if (Reminder::where('dedupe_key', $dedupeKey)->exists()) {
                $skipped++;

                continue;
            }

            $scheduledAt = ReminderOptions::shiftForQuietHours(
                $resolvedCompanyId,
                Carbon::parse($today->toDateString().' '.ReminderOptions::dailyRunTime($resolvedCompanyId))
            );

            try {
                Reminder::create([
                    'company_id' => $resolvedCompanyId,
                    'target_type' => 'client',
                    'reminder_kind' => $this->kind(),
                    'client_id' => $client->id,
                    'agent_id' => $client->agent_id,
                    'send_to_client' => true,
                    'send_to_agent' => true,
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
