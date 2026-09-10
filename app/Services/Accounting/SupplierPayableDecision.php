<?php

declare(strict_types=1);

namespace App\Services\Accounting;

/**
 * CT-A3 wave 1 (R-CT3). The verdict {@see SupplierPayableRule::decide()} returns: whether the
 * supplier payable is guaranteed to be due for this task right now, whether this status instead
 * UNDOES an accrual already posted, and — always — the reason, so the feeder logs a decision it
 * did not have to re-derive.
 *
 * `$shouldPost` and `$shouldReverse` are never both true. Both false is the ordinary "nothing to
 * do here" outcome (a held task, a manual-trigger supplier, a status that is simply not committed
 * yet), which is not an error and is logged at debug, not warning.
 */
final class SupplierPayableDecision
{
    public function __construct(
        public readonly bool $shouldPost,
        public readonly bool $shouldReverse,
        /** The resolved `suppliers.payable_trigger` (after the default/unknown-value fallback). */
        public readonly string $trigger,
        /** The lower-cased `tasks.status` the decision was made against. */
        public readonly string $status,
        /**
         * One of: 'committed', 'reversing_status', 'no_supplier_on_task', 'supplier_payable_hold',
         * 'trigger_manual', 'status_not_committed', 'no_voucher_raised'.
         */
        public readonly string $reason,
    ) {}

    /** @return array<string, string|bool> log-ready context, same shape on every branch. */
    public function toLogContext(): array
    {
        return [
            'should_post' => $this->shouldPost,
            'should_reverse' => $this->shouldReverse,
            'payable_trigger' => $this->trigger,
            'task_status' => $this->status,
            'reason' => $this->reason,
        ];
    }
}
