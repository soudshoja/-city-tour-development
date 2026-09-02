<?php

declare(strict_types=1);

namespace App\Services\Reminders\Generators;

use App\Models\Invoice;
use App\Models\PaymentApplication;
use App\Models\Reminder;
use App\Services\Accounting\StatementOptions;
use App\Services\Reminders\ReminderOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I): "overdue_invoice ... daily 09:00 company tz". Fires again at
 * each configured days-past-due offset ({@see ReminderOptions::overdueInvoiceOffsetsDays()},
 * default 1/3/7/14/30) rather than once per calendar day forever -- the offset itself is the
 * dedupe unit (`dedupe_key = "overdue_invoice:{invoice_id}:{offset}"`), so re-running this
 * generator on an off-day is a no-op, and the same invoice naturally gets a fresh nudge at each
 * configured milestone without ever double-firing one.
 *
 * Open-item source: `Invoice.amount` minus `SUM(PaymentApplication.amount)` -- the SAME
 * ledger-open-item mechanism {@see \App\Services\Accounting\Statements\ClientInvoiceStatementSource}
 * already uses for P2.5.H's statements (doc 11 §P5.3: "PaymentApplication is the single paid-state
 * writer"). Never reads `accounts.actual_balance` or `journal_entries.balance`.
 */
final class OverdueInvoiceReminderGenerator implements ReminderGeneratorInterface
{
    public function kind(): string
    {
        return Reminder::KIND_OVERDUE_INVOICE;
    }

    public function generate(?int $companyId): array
    {
        $today = Carbon::now()->startOfDay();
        $tolerance = StatementOptions::unsettledTolerance();

        $invoices = Invoice::query()
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->whereIn('status', ['unpaid', 'partial'])
            ->when($companyId !== null, fn (Builder $q) => $q->whereHas(
                'agent.branch',
                fn (Builder $b) => $b->where('company_id', $companyId)
            ))
            ->with('agent.branch', 'client')
            ->get();

        if ($invoices->isEmpty()) {
            return ['created' => 0, 'skipped' => 0];
        }

        $applied = PaymentApplication::whereIn('invoice_id', $invoices->pluck('id'))
            ->selectRaw('invoice_id, SUM(amount) as total')
            ->groupBy('invoice_id')
            ->pluck('total', 'invoice_id');

        $created = 0;
        $skipped = 0;

        foreach ($invoices as $invoice) {
            $settled = (float) ($applied[$invoice->id] ?? 0);
            $outstanding = round((float) $invoice->amount - min($settled, (float) $invoice->amount), 3);
            if ($outstanding <= $tolerance) {
                continue; // fully settled -- status column just hasn't caught up yet.
            }

            $resolvedCompanyId = (int) ($invoice->agent?->branch?->company_id ?? $companyId ?? 0);
            if ($resolvedCompanyId === 0 || ! ReminderOptions::enabled($resolvedCompanyId, $this->kind())) {
                $skipped++;

                continue;
            }

            $daysOverdue = (int) Carbon::parse($invoice->due_date)->startOfDay()->diffInDays($today);
            $offsets = ReminderOptions::overdueInvoiceOffsetsDays($resolvedCompanyId);
            if (! in_array($daysOverdue, $offsets, true)) {
                continue; // not one of today's configured milestones for this invoice.
            }

            if ($invoice->agent_id === null || $invoice->client_id === null) {
                $skipped++;

                continue;
            }

            $dedupeKey = "overdue_invoice:{$invoice->id}:{$daysOverdue}";
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
                    'target_type' => 'invoice',
                    'reminder_kind' => $this->kind(),
                    'invoice_id' => $invoice->id,
                    'agent_id' => $invoice->agent_id,
                    'client_id' => $invoice->client_id,
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
                $skipped++; // unique-constraint race -- another concurrent run already won.
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
