<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CT-A3 wave 1 — OWNER RULING R-CT3, 2026-09-09 (`.planning/phases/citytravelers-accounting-audit/
 * PLAN.md` §0.2). Verbatim:
 *
 *   "need to pay are the one guaranteed to be paid not hold or some supplier confirmed so this
 *    needs to be done based on the status of supplier which we set on supplier aspect … from
 *    there decide add or not add? need to be paid or not paid … we need to do the same as we go
 *    through the system."
 *
 * The issuance feeder ({@see \App\Services\Accounting\TaskIssuancePayableService}) must not accrue
 * a supplier payable for work that is merely held or provisionally confirmed. Whether a payable is
 * DUE at a given task status is master data, not a code constant — so it lives here, on the
 * supplier record, and the feeder reads it.
 *
 * ── What already existed, and why it does not express this ──────────────────────────────────────
 *   - `supplier_status_maps` (W6.S, migration 2026_08_29_140003) maps a supplier's own RAW status
 *     string onto this system's canonical task-status vocabulary, per company × supplier ×
 *     channel. It answers "what status is this task in", never "is the money due at that status".
 *     The feeder consumes its OUTPUT (`tasks.status`), it does not duplicate it.
 *   - `suppliers.payment_terms` (varchar, free text, e.g. "30 days") is a SETTLEMENT term — WHEN
 *     the agency pays a payable that already exists — not an accrual trigger. Unparseable and
 *     semantically the wrong question.
 *   - `supplier_companies.account_id` carries the supplier's GL account; `supplier_charge_rules`
 *     carries fee policy. Neither expresses accrual timing.
 * Nothing existing expresses the rule, so these two columns are added.
 *
 * ── The two columns ─────────────────────────────────────────────────────────────────────────────
 *   - `payable_trigger` — the earliest task status at which this supplier's cost becomes a real,
 *     guaranteed liability. Mapped to concrete task statuses by
 *     `config('accounting.supplier_payable.triggers')`, never by a hard-coded list in a service:
 *       * `on_supplier_confirm` — payable from `confirmed` onward (a supplier who holds inventory
 *         firm on confirmation).
 *       * `on_issue`            — payable only once the task is `issued`/`reissued`/`ticketed`/
 *         `emd`. THE DEFAULT (see below).
 *       * `on_voucher`          — `on_issue` AND a voucher has actually been raised
 *         (`tasks.voucher_status` populated and not a negative value).
 *       * `manual`              — never auto-accrued; an operator raises the payable by hand.
 *
 *   - `payable_hold` — a per-supplier kill switch. When true the feeder never accrues for this
 *     supplier at any status, and says so in the log. Independent of `payable_trigger` so a
 *     dispute or an onboarding pause can be expressed without losing the configured trigger.
 *
 * ── Why `on_issue` / `false` are the defaults ───────────────────────────────────────────────────
 * They reproduce the behaviour the legacy ledger already had, so this migration changes no
 * existing number. CT-A1 §1.7 traced the only supplier-payable writer on the old path to
 * `TaskController.php:2315`, fired from `processIssuedTask()` — i.e. at status `issued` — and
 * CT-A1 §3.2 measured its output as 1,600 `unbilled_cost` debit rows against the same population.
 * No supplier on the City Travelers data has ever been held, and nothing in the schema expressed
 * a hold, so `payable_hold` starts false for everyone. Any supplier whose real rule differs is
 * changed in the supplier screen; there is no hard-coded supplier list anywhere.
 *
 * ── Scope of this column pair ───────────────────────────────────────────────────────────────────
 * Deliberately global-per-supplier, not per-company (`supplier_companies`), matching the owner's
 * own wording ("we set on supplier aspect"). If a company ever needs to diverge from another on
 * the same supplier, `supplier_companies` is the extension point — it already carries `account_id`
 * for exactly that per-company reason — and the resolver in
 * {@see \App\Services\Accounting\SupplierPayableRule} is the single place that would need to look
 * there first. Recorded here so that future lane does not have to re-derive it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->enum('payable_trigger', [
                'on_supplier_confirm',
                'on_issue',
                'on_voucher',
                'manual',
            ])->default('on_issue')->after('payment_terms');

            $table->boolean('payable_hold')->default(false)->after('payable_trigger');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['payable_trigger', 'payable_hold']);
        });
    }
};
