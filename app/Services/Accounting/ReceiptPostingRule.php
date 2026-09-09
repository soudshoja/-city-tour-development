<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Charge;
use App\Models\InvoiceReceipt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * CT-A3 wave 2, item W2-2 — receipts under OWNER RULING R-CT3.
 *
 * Wave 1 established the pattern on the supplier payable and said so explicitly: *"The trigger and
 * the account come from configured master-data status, never from a code constant and never from a
 * supplier name or id — this is the pattern wave 2 (receipts, refunds, who-to-pay) follows."*
 * ({@see SupplierPayableRule}, and CT-A3-WAVE1 §1.) This class is that pattern applied to money
 * coming IN. It answers two questions and nothing else:
 *
 *   1. **WHEN** — does a receipt document belong on the ledger at THIS receipt's status, or does
 *      this status take one off? Decided by `config('accounting.receipt.posting_statuses')` and
 *      `…reversing_statuses`, never by an `if ($r->status === 'approved')` in a controller.
 *   2. **WHICH ACCOUNT** — which cash/bank leaf the instrument leg debits. Decided by the
 *      CONFIGURED payment-method account (`charges.acc_bank_id`, the account an operator sets on
 *      the payment method in the Payment Methods screen), never by a hard-coded `CASH_IN_HAND`.
 *
 * ── What was there before, and what was actually wrong ──────────────────────────────────────────
 * Inventoried before anything was designed:
 *
 * | What existed | What it did | Why it was not enough |
 * |---|---|---|
 * | `InvoiceReceipt::STATUS_*` (pending/approved/rejected/reversed/bounced) | five real statuses, all persisted | only `pending` and `approved` ever affected posting; `bounced` reversed the CLEARANCE JV but never the receipt itself |
 * | `ReceiptVoucherController::approve()` | `isPending()` -> post | the transition was hard-coded, so "which statuses post" could not be configured or audited |
 * | `ReceiptVoucherController::bounce()` | reverses `rv-clear:{id}`, flips status to `bounced` | **the defect W2-2 fixes**: the receipt document `rv:{id}` (Dr cheque-in-hand / Cr AR) stayed on the ledger and the invoice stayed `paid`, so a bounced cheque left the receivable collected and the client's debt gone |
 * | `resolveInstrumentLeg()` | post-dated cheque -> `CHEQUES_IN_HAND`; `bank_account_id` -> that leaf; **else `CASH_IN_HAND`** | the `else` is the constant the ruling forbids: every gateway, card and transfer receipt with no explicit `bank_account_id` landed in cash-in-hand regardless of which payment method the money actually arrived through |
 * | `charges.acc_bank_id` | the bank account configured per payment method / gateway | **already the right master data** — nothing consulted it from the receipt path |
 * | `invoice_partials.payment_gateway` | the gateway string on the payment | a string on a different row; the receipt itself recorded no channel |
 *
 * So one column was added (migration `2026_09_09_000002_add_settlement_channel_to_invoice_receipts_table`):
 * `invoice_receipts.settlement_channel` (varchar 24, nullable) — WHICH payment method the money
 * came in through, written by `createReceiptVoucher()` from its own `$gateway` argument and by the
 * voucher form. The account itself is NOT copied onto the receipt: it stays on the `charges` row,
 * so an operator who re-points a payment method at a different bank account changes it in one
 * place. That is the same "status/master data decides, the feeder only reads it" division wave 1
 * drew between `suppliers.payable_trigger` and `config('accounting.supplier_payable.triggers')`.
 *
 * ── What it deliberately does not do ────────────────────────────────────────────────────────────
 *   - It does not post. The controller and {@see Replay\ReceiptReplaySource} own the documents.
 *   - It does not mutate the receipt row.
 *   - It does not read `Auth` — queue/console/replay safe, same convention as {@see AccountResolver}.
 */
final class ReceiptPostingRule
{
    public function __construct(private readonly AccountResolver $accounts) {}

    /** The decision for a receipt row, from its own current status. */
    public function decideFor(InvoiceReceipt $receipt): ReceiptPostingDecision
    {
        return $this->decide((string) $receipt->status);
    }

    /**
     * The decision for one status value. Kept status-in rather than row-in as well, because the
     * live `approve()` path has to ask about the status it is about to MOVE TO, not the one the
     * row currently carries.
     */
    public function decide(?string $status): ReceiptPostingDecision
    {
        $status = strtolower(trim((string) $status));

        if ($this->isReversing($status)) {
            return new ReceiptPostingDecision(false, true, $status, 'status_reverses');
        }

        if (in_array($status, $this->configuredStatuses('posting_statuses'), true)) {
            return new ReceiptPostingDecision(true, false, $status, 'status_posts');
        }

        if (in_array($status, $this->configuredStatuses('draft_statuses'), true)) {
            return new ReceiptPostingDecision(false, false, $status, 'status_is_draft');
        }

        return new ReceiptPostingDecision(false, false, $status, 'status_not_configured');
    }

    /**
     * A status that UNDOES a receipt already on the ledger — `bounced` (the cheque did not clear,
     * so the money never arrived), `reversed`, `rejected`. Checked before every other branch, so a
     * receipt that reaches one of these is reversed whatever the posting list says today.
     */
    public function isReversing(?string $status): bool
    {
        return in_array(strtolower(trim((string) $status)), $this->configuredStatuses('reversing_statuses'), true);
    }

    /**
     * The account the instrument (money-in) leg debits, in a precedence every step of which is
     * configured master data:
     *
     *   1. A **post-dated cheque** — a cheque dated after the voucher — is not in any bank yet, so
     *      it lands on the configured `CHEQUES_IN_HAND` purpose. This is an instrument STATE, not
     *      a payment method, and it wins because it is true regardless of which method was used.
     *   2. The operator's **explicit `bank_account_id`** on the voucher, asserted under the
     *      company's bank group. An explicit choice always beats a default.
     *   3. The **configured payment-method account** — `charges.acc_bank_id` for the receipt's
     *      `settlement_channel`, asserted under the bank group. This is the step that did not
     *      exist before wave 2 and the reason a card receipt used to land in cash-in-hand.
     *   4. The **configured fallback purpose**, `config('accounting.receipt.instrument.fallback_purpose')`
     *      (default `CASH_IN_HAND`). Reached only when the row names no channel and no bank
     *      account — a genuine over-the-counter cash receipt — and logged as
     *      `accounting.receipt.instrument.fallback_used` so an operator can find the payment
     *      methods that still have no account configured, which is exactly the audit CT-A4 needs.
     *
     * A `settlement_channel` that names a `charges` row whose `acc_bank_id` is null, or points
     * outside the bank group, does NOT silently fall through to cash: it is logged at warning and
     * the fallback is used, because inventing a bank leaf for a misconfigured payment method is
     * how money ends up in the wrong account quietly.
     */
    public function instrumentAccountFor(InvoiceReceipt $receipt, Carbon $docDate, int $companyId): Account
    {
        if ($receipt->cheque_no && $receipt->cheque_date && Carbon::parse($receipt->cheque_date)->gt($docDate)) {
            return $this->accounts->resolve('CHEQUES_IN_HAND', $companyId);
        }

        if ($receipt->bank_account_id) {
            return $this->accounts->assertUnderBankGroup((int) $receipt->bank_account_id, $companyId);
        }

        $channelAccount = $this->paymentMethodAccountFor($receipt, $companyId);

        if ($channelAccount !== null) {
            return $channelAccount;
        }

        $fallback = (string) config('accounting.receipt.instrument.fallback_purpose', 'CASH_IN_HAND');

        Log::debug('accounting.receipt.instrument.fallback_used', [
            'invoice_receipt_id' => $receipt->id,
            'company_id' => $companyId,
            'settlement_channel' => $receipt->settlement_channel,
            'fallback_purpose' => $fallback,
        ]);

        return $this->accounts->resolve($fallback, $companyId);
    }

    /**
     * The configured account for this receipt's payment method, or null when the receipt names no
     * channel / the channel is not configured. Matched on `charges.name` case-insensitively
     * (the same string `createReceiptVoucher()` is handed as its `$gateway` argument) and always
     * scoped to the receipt's own company — a channel is never allowed to resolve an account
     * belonging to another tenant.
     */
    private function paymentMethodAccountFor(InvoiceReceipt $receipt, int $companyId): ?Account
    {
        $channel = trim((string) ($receipt->settlement_channel ?? ''));

        if ($channel === '') {
            return null;
        }

        $charge = Charge::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [strtolower($channel)])
            ->first();

        if ($charge === null) {
            Log::warning('accounting.receipt.instrument.unknown_channel', [
                'invoice_receipt_id' => $receipt->id,
                'company_id' => $companyId,
                'settlement_channel' => $channel,
            ]);

            return null;
        }

        if ($charge->acc_bank_id === null) {
            Log::warning('accounting.receipt.instrument.channel_has_no_account', [
                'invoice_receipt_id' => $receipt->id,
                'company_id' => $companyId,
                'settlement_channel' => $channel,
                'charge_id' => $charge->id,
            ]);

            return null;
        }

        try {
            return $this->accounts->assertUnderBankGroup((int) $charge->acc_bank_id, $companyId);
        } catch (\Throwable $e) {
            Log::warning('accounting.receipt.instrument.channel_account_rejected', [
                'invoice_receipt_id' => $receipt->id,
                'company_id' => $companyId,
                'settlement_channel' => $channel,
                'charge_id' => $charge->id,
                'account_id' => $charge->acc_bank_id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** @return string[] */
    private function configuredStatuses(string $key): array
    {
        return array_map(
            static fn ($v) => strtolower(trim((string) $v)),
            (array) config('accounting.receipt.'.$key, [])
        );
    }
}
