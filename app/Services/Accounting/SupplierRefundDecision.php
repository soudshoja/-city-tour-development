<?php

declare(strict_types=1);

namespace App\Services\Accounting;

/**
 * CT-A3 wave 2 (W2-3), R-CT3 pattern. The verdict {@see SupplierRefundRule::decide()} returns:
 * whether the supplier's money is treated as recoverable for THIS refund detail, how much of the
 * original cost that is, and always the reason.
 *
 * Shaped like {@see SupplierPayableDecision} and {@see ReceiptPostingDecision} — one verdict, the
 * inputs it was made from, the reason, and a `toLogContext()` with the same shape on every branch.
 *
 * `$recoverableAmount` is the part of `original_task_cost` the supplier is giving back; the
 * remainder is not recoverable. When `$shouldRecover` is false the recoverable amount is always
 * 0.000 and the WHOLE cost is the agency's loss.
 */
final class SupplierRefundDecision
{
    public function __construct(
        public readonly bool $shouldRecover,
        /** The resolved `suppliers.refund_trigger` (after the default/unknown-value fallback). */
        public readonly string $trigger,
        /** The lower-cased `tasks.status` the decision was made against. */
        public readonly string $status,
        /** How much of `original_task_cost` the supplier is returning. 0.000 when not recovering. */
        public readonly float $recoverableAmount,
        /** `original_task_cost` - `recoverableAmount`: the part the agency bears. */
        public readonly float $nonRecoverableAmount,
        /** True when the recoverable figure came from an operator's explicit
         *  `refund_details.supplier_refund_amount`, rather than from the rule's own default. */
        public readonly bool $explicitAmount,
        /**
         * One of: 'supplier_confirmed_refund', 'operator_set_amount', 'no_supplier_on_task',
         * 'supplier_refund_hold', 'trigger_never', 'trigger_manual_no_amount',
         * 'status_not_refund_confirmed', 'no_cost_to_recover'.
         */
        public readonly string $reason,
    ) {}

    /** @return array<string, string|bool|float> log-ready context, same shape on every branch. */
    public function toLogContext(): array
    {
        return [
            'should_recover' => $this->shouldRecover,
            'refund_trigger' => $this->trigger,
            'task_status' => $this->status,
            'recoverable_amount' => $this->recoverableAmount,
            'non_recoverable_amount' => $this->nonRecoverableAmount,
            'explicit_amount' => $this->explicitAmount,
            'reason' => $this->reason,
        ];
    }
}
