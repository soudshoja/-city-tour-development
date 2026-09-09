<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\RefundDetail;
use App\Models\Supplier;
use App\Models\Task;

/**
 * CT-A3 wave 2, item W2-3 — OWNER RULING R-CT3, the recovery direction.
 *
 * The single place that answers:
 *
 *   "This booking is being refunded to the client. Is the SUPPLIER actually giving us our money
 *    back — and if so, how much of it?"
 *
 * ── Why this class exists ───────────────────────────────────────────────────────────────────────
 * CT-A1 CT-F11: *"Refunds reverse the wrong side, and never reverse revenue."* 319 legacy refund
 * lines (KWD 57,891.068) credited a COGS leaf while the cost actually sat in asset 1430, and 367
 * live refunded tasks carried KWD 1,768.750 of revenue credit against 0.000 of revenue debit. The
 * wrong form was still being written in 2026 (176 rows).
 *
 * {@see RefundPostingService} carried the same assumption from the engine side:
 * `postSupplierCreditForDetail()` credited `SERVICE_COST` for the FULL original cost on EVERY
 * refund, and `supplierRefundAmount()` defaulted to `original_task_cost - supplier_charge` — so
 * "the operator has not told us what the supplier did" was silently read as "the supplier refunded
 * in full". A refund the supplier refused therefore erased a cost the agency had genuinely borne,
 * and the P&L looked better than the bank.
 *
 * ── The pattern, unchanged from wave 1 ──────────────────────────────────────────────────────────
 * The trigger and the account come from configured master-data status — never from a code
 * constant, never from a supplier name or id. Two halves, joined here and nowhere else:
 *
 *   1. `suppliers.refund_trigger` + `suppliers.refund_hold` — the per-supplier CHOICE, editable in
 *      the supplier screen (migration 2026_09_09_000003).
 *   2. `config('accounting.supplier_refund.triggers')` — the VOCABULARY mapping each trigger onto
 *      the `tasks.status` values at which the supplier's money counts as recoverable.
 *
 * `tasks.status` is itself the normalised OUTPUT of `supplier_status_maps` (W6.S), so a supplier
 * who spells their refund state 'RFND' or 'OK-REF' has been normalised long before this class
 * runs. Nothing here ever reads a raw supplier status, and nothing here reads a supplier NAME.
 *
 * ── The one thing that always wins ──────────────────────────────────────────────────────────────
 * An operator's explicit `refund_details.supplier_refund_amount`. A human who typed a figure has
 * the supplier's advice in front of them and knows more than any rule; the rule exists for the
 * case where nobody typed anything, which is where the old default did its damage.
 *
 * ── What it deliberately does NOT do ────────────────────────────────────────────────────────────
 *   - It does not post. {@see RefundPostingService} owns the documents.
 *   - It does not decide WHICH account the cost is credited back to — that is a question about
 *     where the cost currently SITS, answered from the ledger by
 *     {@see RefundPostingService::costCarrierPurposeFor()}, not from configuration.
 *   - It does not retro-post. A supplier whose rule changes after a refund posted is CT-A5's
 *     sweep, exactly as wave 1 ruled for the payable trigger.
 *   - It does not read `Auth` — queue/console/replay safe.
 */
final class SupplierRefundRule
{
    public const TRIGGER_ON_SUPPLIER_REFUND_CONFIRMED = 'on_supplier_refund_confirmed';

    public const TRIGGER_ON_REFUND_REQUEST = 'on_refund_request';

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_NEVER = 'never';

    /**
     * The configured trigger for a supplier, falling back to
     * `config('accounting.supplier_refund.default_trigger')` when the column is null (a row that
     * predates the migration) or carries a value this build does not recognise. Never throws — an
     * unrecognised value degrades to the documented default rather than breaking a refund.
     */
    public function triggerFor(?Supplier $supplier): string
    {
        $default = (string) config('accounting.supplier_refund.default_trigger', self::TRIGGER_ON_SUPPLIER_REFUND_CONFIRMED);
        $configured = $supplier?->refund_trigger;

        if (! is_string($configured) || $configured === '') {
            return $default;
        }

        $known = array_keys((array) config('accounting.supplier_refund.triggers', []));

        return in_array($configured, $known, true) ? $configured : $default;
    }

    /**
     * True when this supplier is explicitly held — nothing is treated as recoverable at any
     * status, whatever the trigger says. Independent of `refund_trigger` so a dispute can be
     * expressed without losing the configured policy.
     */
    public function isOnHold(?Supplier $supplier): bool
    {
        return (bool) ($supplier?->refund_hold ?? false);
    }

    /**
     * The decision, for one refund detail.
     *
     * @param  Task|null  $task  the task being refunded; its (already normalised) status is the input
     * @param  Supplier|null  $supplier  the task's supplier
     * @param  RefundDetail|null  $detail  carries `original_task_cost`, `supplier_charge` (the penalty
     *                                     the supplier KEPT out of a refund it did make) and the
     *                                     operator's explicit `supplier_refund_amount`. NULL for a
     *                                     task that reached a refund status with no refund document
     *                                     behind it at all -- on the City Travelers data that is the
     *                                     common shape: CT-A1 §2.1 counted 368 live `refund`-status
     *                                     tasks against 33 `refunds` rows. The cost then comes from
     *                                     `tasks.total` and there is no penalty and no explicit
     *                                     figure, which is exactly the "nobody recorded what the
     *                                     supplier did" case this rule exists for.
     */
    public function decide(?Task $task, ?Supplier $supplier, ?RefundDetail $detail = null): SupplierRefundDecision
    {
        $status = strtolower(trim((string) ($task->status ?? '')));
        $trigger = $this->triggerFor($supplier);
        $fullCost = $detail !== null
            ? round((float) ($detail->original_task_cost ?? 0.0), 3)
            : round((float) ($task->total ?? 0.0), 3);

        $none = fn (string $reason) => new SupplierRefundDecision(
            false, $trigger, $status, 0.0, $fullCost, false, $reason
        );

        if ($fullCost <= 0) {
            return new SupplierRefundDecision(false, $trigger, $status, 0.0, 0.0, false, 'no_cost_to_recover');
        }

        // An operator's explicit figure always wins, at any trigger and any status — including
        // `manual` (which is the trigger that REQUIRES one) and including `never`/`hold`, because
        // an operator who types a figure is recording something that has actually happened. A hold
        // is a default-suppressor, not a veto over recorded fact.
        if ($detail?->supplier_refund_amount !== null) {
            $recoverable = round(min(max(0.0, (float) $detail->supplier_refund_amount), $fullCost), 3);

            return new SupplierRefundDecision(
                $recoverable > 0,
                $trigger,
                $status,
                $recoverable,
                round($fullCost - $recoverable, 3),
                true,
                'operator_set_amount'
            );
        }

        if ($supplier === null) {
            return $none('no_supplier_on_task');
        }

        if ($this->isOnHold($supplier)) {
            return $none('supplier_refund_hold');
        }

        if ($trigger === self::TRIGGER_NEVER) {
            return $none('trigger_never');
        }

        if ($trigger === self::TRIGGER_MANUAL) {
            // No explicit amount was typed (handled above), so there is nothing to recover: that
            // is what `manual` means.
            return $none('trigger_manual_no_amount');
        }

        $recoveringStatuses = (array) config('accounting.supplier_refund.triggers.'.$trigger, []);

        if (! in_array($status, $recoveringStatuses, true)) {
            return $none('status_not_refund_confirmed');
        }

        // The supplier is refunding, less whatever penalty it kept — the pre-existing
        // `supplier_charge` semantics, unchanged. Clamped at both ends: a penalty larger than the
        // cost cannot produce a negative recovery, and a negative penalty cannot recover more than
        // was ever paid.
        $penalty = round(max(0.0, (float) ($detail?->supplier_charge ?? 0.0)), 3);
        $recoverable = round(max(0.0, min($fullCost, $fullCost - $penalty)), 3);

        return new SupplierRefundDecision(
            $recoverable > 0,
            $trigger,
            $status,
            $recoverable,
            round($fullCost - $recoverable, 3),
            false,
            'supplier_confirmed_refund'
        );
    }
}
