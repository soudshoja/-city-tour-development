<?php

declare(strict_types=1);

namespace App\Services\Accounting;

/**
 * PROPOSED NAME (W2b build, KEY: draft-builder — design call E1; source discriminator added
 * W2c, orchestrator ruling B-2). One applied payment/credit for
 * {@see CreditApplicationDraftBuilder::build()} — the "set of applied payment_applications (id,
 * source type/id, amount_applied)" the shared credit-apply engine draft builder consumes (W2
 * orchestrator lead report §7).
 *
 * Maps directly to one `payment_applications` row (see {@see \App\Models\PaymentApplication}),
 * but is deliberately its own value object rather than the Eloquent model itself: the builder is
 * a function over its inputs and must not depend on, or accidentally trigger, any Eloquent
 * relation/global-scope side effect of the row it describes — the same reasoning DocumentDraft
 * and LineDraft already apply to everything they carry. (It is NOT a pure function in the strict
 * sense — {@see CreditApplicationDraftBuilder::build()} reads `config()`, calls `now()`, and
 * traverses `$invoice->agent?->branch_id` — see that class's own docblock; this class's own
 * contract is only that IT does not perform any such side effect.)
 *
 * ── W2c fix (B-2): the idempotency key needs a SOURCE, not just an id ─────────────────────────
 * W2b shipped two feeders that fed {@see PaymentIdempotencyKey::forCreditApplication()} the SAME
 * key namespace from TWO DIFFERENT tables' auto-increment ids —
 * `PaymentApplicationService::createCreditPaymentCOA()` supplied real `payment_applications.id`
 * values, `InvoiceController::createCreditPaymentCOA()` supplied `invoice_partials.id` values —
 * and small ids from two independent sequences collide routinely, silently dropping a real
 * second credit-application event (W2b lead report §5, B-2). `$idSource` makes the table
 * EXPLICIT and TYPED so the key can namespace on it (`…:pa:…` vs `…:partial:…`) instead of
 * assuming every caller's `$id` came from the same table.
 *
 *   - `self::SOURCE_PAYMENT_APPLICATION` ('pa') — `$id` is a real `payment_applications.id`.
 *     Use this whenever the flow that produced this application actually created a
 *     `PaymentApplication` row (every producer in this codebase today: `PaymentApplicationService::
 *     applyPaymentsToInvoice()` and `::linkPaymentsToInvoicePartial()` both do).
 *   - `self::SOURCE_PARTIAL` ('partial') — `$id` is an `invoice_partials.id`. Use this ONLY where
 *     the flow genuinely creates no `PaymentApplication` row at all (today: `InvoiceController::
 *     savePartial()`'s "no specific allocations" legacy fallback branch, which creates a bare
 *     `Credit` and nothing else).
 *   - `$id` must be a real, positive id from the table `$idSource` names — NEVER a `credit_id`
 *     (a standing credit is reused across separate apply-events and would collapse them onto one
 *     key) and NEVER the `0` sentinel (constructor-enforced below).
 */
final class CreditApplicationInput
{
    public const SOURCE_PAYMENT_APPLICATION = 'pa';
    public const SOURCE_PARTIAL = 'partial';

    public function __construct(
        /**
         * Which table {@see self::$id} identifies — one of {@see self::SOURCE_PAYMENT_APPLICATION}
         * / {@see self::SOURCE_PARTIAL}. See class docblock's "W2c fix (B-2)" section.
         */
        public readonly string $idSource,
        /**
         * The real, positive row id in the table `$idSource` names — the ONLY thing
         * {@see PaymentIdempotencyKey::forCreditApplication()} keys on. Every application in the
         * caller's set must be represented here, even one this builder goes on to SKIP for being
         * zero/negative (see $amountApplied below): the business event is "this invoice had this
         * SET of applications submitted", not merely "...the ones that turned into a debit line".
         */
        public readonly int $id,
        /**
         * `payment_applications.amount` (or the equivalent raw applied figure) for this row, IN
         * THE INVOICE'S OWN CURRENCY — never pre-converted to base by the caller. A value <= 0 is
         * skipped by the builder (no debit line is built for it, and its id/source is still
         * folded into the idempotency key — see above) — the same tolerance both legacy
         * `createCreditPaymentCOA()` implementations apply
         * (`if ($amountApplied <= 0) continue;`), reproduced here so the ON path stays
         * behaviourally aligned with HEAD on this one rule.
         */
        public readonly float $amountApplied,
        /**
         * Informational audit trail only ("source type/id" from the design call) — mirrors
         * {@see DocumentDraft::$sourceType}/{@see DocumentDraft::$sourceId}'s own "never a
         * linkage instruction" convention. Typically 'payment', 'refund', or 'credit'. Not
         * consulted by the builder's own line-construction logic. Deliberately a SEPARATE
         * vocabulary from {@see self::$idSource} above — this field describes where the MONEY
         * came from, `$idSource` describes which TABLE `$id` belongs to.
         */
        public readonly ?string $sourceType = null,
        public readonly ?int $sourceId = null,
        /**
         * `payment_applications.invoice_partial_id` — carried through for a future line-level
         * attribution pass. NOT yet written onto the debit {@see LineDraft} this builder
         * produces: the two legacy implementations diverge on whether/how a partial id
         * ultimately reaches the journal row (see the builder class docblock's parity note), so
         * wiring it is deliberately deferred to whichever caller cuts over first rather than
         * guessed here.
         */
        public readonly ?int $invoicePartialId = null,
        /**
         * Display label for this application's debit-line description ("Apply Client Credit
         * from {label}"). Legacy already resolves this at the call site — a payment's own
         * voucher_number, a refund's refund_number, or a literal fallback ('Client Credit' /
         * 'TOPUP' / 'REFUND' depending on which legacy copy) — so this builder does not
         * re-derive it from $sourceType/$sourceId. Falls back to the builder's own
         * `$defaultVoucherLabel` parameter when null.
         */
        public readonly ?string $voucherLabel = null,
    ) {
        if (! in_array($idSource, [self::SOURCE_PAYMENT_APPLICATION, self::SOURCE_PARTIAL], true)) {
            throw new \InvalidArgumentException(sprintf(
                "CreditApplicationInput::\$idSource must be one of '%s'/'%s'; got %s.",
                self::SOURCE_PAYMENT_APPLICATION,
                self::SOURCE_PARTIAL,
                var_export($idSource, true)
            ));
        }

        if ($id <= 0) {
            throw new \InvalidArgumentException(sprintf(
                'CreditApplicationInput::$id must be a real, positive %s id; got %d. Never the 0 '
                .'sentinel, and never a credit_id (see class docblock).',
                $idSource === self::SOURCE_PAYMENT_APPLICATION ? 'payment_applications' : 'invoice_partials',
                $id
            ));
        }
    }
}
