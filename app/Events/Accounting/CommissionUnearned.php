<?php

declare(strict_types=1);

namespace App\Events\Accounting;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I): "commission_unearned event-driven from W4.R's un-earn post."
 * Dispatched by {@see \App\Services\Accounting\RefundPostingService::post()} right after a live
 * commission document is successfully reversed for a refund detail
 * ({@see \App\Services\Accounting\RefundPostingService::postCommissionUnearnForDetail()}) --
 * additive, does not change any posting/ledger behaviour of that method.
 *
 * A plain data event (no ShouldQueue) -- {@see \App\Listeners\Reminders\CreateCommissionUnearnedReminder}
 * runs synchronously in the same request/job that posted the refund, matching every other
 * reminder-row writer in this codebase (none of which are queued).
 */
final class CommissionUnearned
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $agentId,
        public readonly int $clientId,
        public readonly ?int $invoiceId,
        public readonly int $transactionId,
        public readonly float $amount,
    ) {}
}
