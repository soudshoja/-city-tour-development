<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CT-A3 wave 2, item W2-2 — OWNER RULING R-CT3 applied to money coming IN: *"we need to do the
 * same as we go through the system."*
 *
 * The receipt-voucher feeder used to pick its cash/bank leaf like this
 * (`ReceiptVoucherController::resolveInstrumentLeg()` before this wave):
 *
 *   post-dated cheque -> CHEQUES_IN_HAND
 *   explicit bank_account_id -> that leaf
 *   **otherwise -> CASH_IN_HAND**
 *
 * That last line is the code constant R-CT3 forbids. Every card, gateway, transfer and KNET
 * receipt raised without an explicit `bank_account_id` landed in cash-in-hand regardless of which
 * payment method the money actually arrived through — the receipt equivalent of resolving a
 * supplier payable from a supplier NAME rather than from configured status.
 *
 * ── What already existed (inventoried before this column was added) ─────────────────────────────
 *   - `charges.acc_bank_id` — the bank account an operator configures PER PAYMENT METHOD (the
 *     Payment Methods / gateway screen). This is already the right master data, and
 *     `PaymentController::createInvoicePaymentCOA()` already resolves the gateway fee accounts
 *     from the same row. Nothing on the receipt path consulted it.
 *   - `payment_methods.charge_id` — the link from a method to that `charges` row.
 *   - `invoice_partials.payment_gateway` — the gateway string, but on the PARTIAL, not on the
 *     receipt, and only ever populated for invoice-allocated payments.
 *   - `invoice_receipts.bank_account_id` — the operator's explicit override. Kept, and still wins.
 *
 * What was missing was only the link FROM a receipt TO its payment method. Hence one column, not
 * an account copy:
 *
 *   `invoice_receipts.settlement_channel` — varchar(24), nullable. WHICH payment method / gateway
 *   this money came in through. `ReceiptVoucherController::createReceiptVoucher()` already
 *   receives exactly this string as its `$gateway` argument (default 'Cash') and threw it away;
 *   it now persists it.
 *
 * The ACCOUNT is deliberately NOT copied onto the receipt. It stays on the `charges` row, so an
 * operator who re-points a payment method at a different bank account changes it in one place and
 * every future receipt follows — the same division wave 1 drew between `suppliers.payable_trigger`
 * (the choice) and `config('accounting.supplier_payable.triggers')` (the vocabulary). Already-
 * posted receipts are NOT re-pointed by such a change: a posted line is never rewritten, exactly
 * as a supplier rule change does not retro-post (CT-A3-WAVE1 §1, R-CT3).
 *
 * ── Width, and why 24 ───────────────────────────────────────────────────────────────────────────
 * `varchar(24)`, matching `Accounting\ReconciliationController`'s own `settlement_channel`
 * validation (`'max:24'`) and `journal_entries.settlement_channel`, so the same channel token can
 * be carried end to end from a receipt through to its reconciliation without a truncation step.
 *
 * ── Nullable, with no backfill ──────────────────────────────────────────────────────────────────
 * NULL means "no channel recorded" — which is what every one of the 109 existing City Travelers
 * rows genuinely is, and it resolves through the configured fallback purpose exactly as those
 * rows already did. Guessing a channel for a historical receipt from a description string is the
 * kind of retro-fabrication CT-A1 §1.7 already found twenty variants of; this migration does not
 * add a twenty-first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_receipts', function (Blueprint $table) {
            $table->string('settlement_channel', 24)->nullable()->after('bank_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_receipts', function (Blueprint $table) {
            $table->dropColumn('settlement_channel');
        });
    }
};
