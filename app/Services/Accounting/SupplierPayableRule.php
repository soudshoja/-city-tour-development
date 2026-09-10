<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Supplier;
use App\Models\Task;

/**
 * CT-A3 wave 1 — OWNER RULING R-CT3, 2026-09-09. The single place that answers:
 *
 *   "Given THIS task at THIS status and THIS supplier's configured rule, is the supplier payable
 *    guaranteed to be due — add it, or not?"
 *
 * Owner, verbatim: "need to pay are the one guaranteed to be paid not hold or some supplier
 * confirmed so this needs to be done based on the status of supplier which we set on supplier
 * aspect … from there decide add or not add? need to be paid or not paid … we need to do the same
 * as we go through the system."
 *
 * ── The pattern this class establishes ──────────────────────────────────────────────────────────
 * Every automatic posting in this engine takes its TRIGGER and its ACCOUNT from configured
 * master-data status — never from a constant compiled into a feeder, and never from a supplier
 * name or id. Two halves, joined here and nowhere else:
 *
 *   1. `suppliers.payable_trigger` + `suppliers.payable_hold` — the per-supplier CHOICE, editable
 *      in the supplier screen (migration 2026_09_09_000001_add_payable_trigger_to_suppliers_table).
 *   2. `config('accounting.supplier_payable.triggers')` — the VOCABULARY mapping each trigger onto
 *      the `tasks.status` values it treats as committed.
 *
 * `tasks.status` is itself already the normalised OUTPUT of
 * {@see \App\Services\TaskStatusService::mapStatus()} resolving the supplier's own raw status
 * string through `supplier_status_maps` (W6.S). So a supplier whose confirmed state is spelled
 * 'OK' or 'RQ' has been normalised long before this class runs; nothing here ever reads a raw
 * supplier status.
 *
 * ── Why a hold/unconfirmed task must not accrue ─────────────────────────────────────────────────
 * CT-A1 §2.1 counted 424 `confirmed` (not yet issued) tasks carrying KWD 21,542.960 of revenue
 * credit on the legacy ledger, and CT-A1 §3.3 recorded AP standing at KWD 1,105,646.220 across 99
 * leaves "with essentially no settlement". Accruing a payable the agency is not yet committed to
 * is exactly how an AP control becomes unusable. This class is the gate that stops it.
 *
 * ── What it deliberately does NOT do ────────────────────────────────────────────────────────────
 *   - It does not post anything. {@see TaskIssuancePayableService} owns the document.
 *   - It does not retro-post. When a supplier's rule CHANGES, tasks already past their new trigger
 *     are NOT swept — that is a data migration with real money attached and belongs to CT-A5. The
 *     feeder logs `accounting.supplier_payable.rule_changed_not_backfilled` instead of silently
 *     inventing history.
 *   - It does not read `Auth` (queue/webhook/console-safe, same convention as AccountResolver).
 */
final class SupplierPayableRule
{
    public const TRIGGER_ON_SUPPLIER_CONFIRM = 'on_supplier_confirm';

    public const TRIGGER_ON_ISSUE = 'on_issue';

    public const TRIGGER_ON_VOUCHER = 'on_voucher';

    public const TRIGGER_MANUAL = 'manual';

    /**
     * The configured trigger for a supplier, falling back to
     * `config('accounting.supplier_payable.default_trigger')` when the column is null (a row that
     * predates the migration) or carries a value this build does not recognise. Never throws — an
     * unrecognised value must degrade to the documented default, not break issuance.
     */
    public function triggerFor(?Supplier $supplier): string
    {
        $default = (string) config('accounting.supplier_payable.default_trigger', self::TRIGGER_ON_ISSUE);
        $configured = $supplier?->payable_trigger;

        if (! is_string($configured) || $configured === '') {
            return $default;
        }

        $known = array_keys((array) config('accounting.supplier_payable.triggers', []));

        return in_array($configured, $known, true) ? $configured : $default;
    }

    /**
     * True when this supplier is explicitly on hold — no accrual at any status, whatever the
     * trigger says. Independent of `payable_trigger` so a dispute or an onboarding pause can be
     * expressed without losing the configured trigger.
     */
    public function isOnHold(?Supplier $supplier): bool
    {
        return (bool) ($supplier?->payable_hold ?? false);
    }

    /**
     * The decision. Returns a {@see SupplierPayableDecision} carrying both the verdict and the
     * REASON, so the feeder can log why it did or did not post without re-deriving anything.
     */
    public function decide(Task $task, ?Supplier $supplier): SupplierPayableDecision
    {
        $trigger = $this->triggerFor($supplier);
        $status = strtolower(trim((string) $task->status));

        if ($this->isReversing($status)) {
            return new SupplierPayableDecision(false, true, $trigger, $status, 'reversing_status');
        }

        if ($supplier === null) {
            return new SupplierPayableDecision(false, false, $trigger, $status, 'no_supplier_on_task');
        }

        if ($this->isOnHold($supplier)) {
            return new SupplierPayableDecision(false, false, $trigger, $status, 'supplier_payable_hold');
        }

        if ($trigger === self::TRIGGER_MANUAL) {
            return new SupplierPayableDecision(false, false, $trigger, $status, 'trigger_manual');
        }

        $committedStatuses = (array) config('accounting.supplier_payable.triggers.'.$trigger, []);

        if (! in_array($status, $committedStatuses, true)) {
            return new SupplierPayableDecision(false, false, $trigger, $status, 'status_not_committed');
        }

        if ($trigger === self::TRIGGER_ON_VOUCHER && ! $this->hasVoucher($task)) {
            return new SupplierPayableDecision(false, false, $trigger, $status, 'no_voucher_raised');
        }

        return new SupplierPayableDecision(true, false, $trigger, $status, 'committed');
    }

    /**
     * A status that UNDOES an accrual already posted. Checked before every other rule, because a
     * voided task must reverse regardless of what the supplier's trigger says today (the trigger
     * could have been edited between the accrual and the void).
     */
    public function isReversing(string $status): bool
    {
        $reversing = (array) config('accounting.supplier_payable.reversing_statuses', []);

        return in_array(strtolower(trim($status)), $reversing, true);
    }

    /**
     * `tasks.voucher_status` is a free-text column (varchar 255) written by several importers, so
     * "a voucher exists" is decided by exclusion against a configured negative list rather than by
     * matching a positive vocabulary this codebase does not own.
     */
    private function hasVoucher(Task $task): bool
    {
        $voucher = strtolower(trim((string) $task->voucher_status));

        if ($voucher === '') {
            return false;
        }

        $negative = array_map(
            static fn ($v) => strtolower(trim((string) $v)),
            (array) config('accounting.supplier_payable.voucher_negative_statuses', [])
        );

        return ! in_array($voucher, $negative, true);
    }
}
