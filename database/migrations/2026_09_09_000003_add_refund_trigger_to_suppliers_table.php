<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CT-A3 wave 2, item W2-3 — OWNER RULING R-CT3 applied to the OTHER direction of the supplier
 * relationship: getting money BACK.
 *
 * Wave 1 answered "is this supplier payable guaranteed to be DUE at this task status?" with
 * `suppliers.payable_trigger` / `payable_hold`. The mirror question — *"is the supplier actually
 * going to refund us, or is this cost ours to eat?"* — was answered by nothing at all, and the
 * ledger showed it.
 *
 * ── The finding this closes: CT-A1 CT-F11 ───────────────────────────────────────────────────────
 * *"Refunds reverse the wrong side, and never reverse revenue. KWD 57,891.068 credited to COGS
 * that should have credited the 1430 asset; KWD 1,768.750 of revenue on refunded tasks never
 * reversed. Count: 319 + 367."* And the wrong form was still being written: 2024 -> 3, 2025 ->
 * 140, **2026 -> 176**.
 *
 * The engine's own `RefundPostingService::postSupplierCreditForDetail()` carried the same
 * assumption from the other side: it credited `SERVICE_COST` for the FULL original cost
 * unconditionally, on every refund, whether or not the supplier ever gave a fils back. Combined
 * with `supplierRefundAmount()`'s default of `original_task_cost - supplier_charge`, the engine
 * assumed **every** supplier refunds unless an operator typed a number to say otherwise. A refund
 * the supplier refused therefore erased a cost the agency had genuinely borne.
 *
 * ── What already existed (inventoried before these columns were added) ──────────────────────────
 *   - `refund_details.supplier_refund_amount` (nullable, migration 2026_08_28_140000) — the
 *     operator's EXPLICIT figure for what the supplier actually returned. Real, and kept: an
 *     explicit figure is always honoured, because a human who typed it knows more than any rule.
 *     But nullable-means-"use the default" is precisely what made "no data" look like "full
 *     refund".
 *   - `refund_details.supplier_charge` — the penalty the supplier KEPT out of a refund it did
 *     make. Answers how much, never whether.
 *   - `refunds.status` (draft/approved/posted/completed/rejected) — OUR workflow state, not the
 *     supplier's.
 *   - `tasks.status` (refund, refunded, …) — already normalised through `supplier_status_maps`
 *     (W6.S), and the right INPUT: `refund` is "we have asked", `refunded` is "it came back".
 *     Nothing joined that distinction to a per-supplier policy.
 *   - `supplier_charge_rules` — fee policy, per company x supplier x service. Not recovery.
 * Nothing expressed the rule, so two columns are added, exactly mirroring wave 1's pair.
 *
 * ── The two columns ─────────────────────────────────────────────────────────────────────────────
 *   - `refund_trigger` — the earliest state at which this supplier's money is treated as
 *     recoverable, mapped onto concrete task statuses by
 *     `config('accounting.supplier_refund.triggers')`, never by a list in a service:
 *       * `on_supplier_refund_confirmed` — recoverable only once the task actually reaches
 *         `refunded`. THE DEFAULT (see below).
 *       * `on_refund_request` — recoverable as soon as the refund is raised (`refund` status too)
 *         — a supplier with a standing, reliable refund agreement.
 *       * `manual` — never automatic; recovery happens only for the amount an operator explicitly
 *         types into `refund_details.supplier_refund_amount`.
 *       * `never` — this supplier does not refund. The cost stays with us, always.
 *
 *   - `refund_hold` — the per-supplier kill switch, independent of the trigger, for a supplier in
 *     dispute or under investigation: nothing is treated as recoverable at any status while it is
 *     set, and the feeder says so in the log.
 *
 * ── Why `on_supplier_refund_confirmed` is the default ───────────────────────────────────────────
 * It is the conservative choice, and conservatism is the whole point of the finding: the defect
 * was the system ASSUMING recovery. A cost is only removed from the books when the supplier has
 * actually confirmed the refund (task status `refunded`), or when an operator has explicitly typed
 * the amount. Everything else keeps the cost — reclassified out of cost-of-sales into
 * `SUPPLIER_REFUND_LOSS` (5131), where a refund the agency ate is visible as exactly that instead
 * of hiding inside COGS or, worse, vanishing.
 *
 * Note this default does NOT reproduce the legacy ledger, and that is deliberate — unlike wave 1's
 * `on_issue`, which did. CT-F11 says the legacy behaviour was wrong; reproducing it would be
 * preserving the defect. The change is bounded, visible per supplier, and every affected document
 * is a new posting rather than a rewrite of an old one.
 *
 * ── Scope ───────────────────────────────────────────────────────────────────────────────────────
 * Global-per-supplier, same as wave 1's pair and for the same reason (the owner's "we set on
 * supplier aspect"); `supplier_companies` remains the extension point if a company ever needs to
 * diverge, and {@see \App\Services\Accounting\SupplierRefundRule} is the single place that would
 * look there first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->enum('refund_trigger', [
                'on_supplier_refund_confirmed',
                'on_refund_request',
                'manual',
                'never',
            ])->default('on_supplier_refund_confirmed')->after('payable_hold');

            $table->boolean('refund_hold')->default(false)->after('refund_trigger');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['refund_trigger', 'refund_hold']);
        });
    }
};
