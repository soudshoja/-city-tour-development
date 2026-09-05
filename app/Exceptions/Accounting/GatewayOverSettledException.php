<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Verifier fix (T7 adversarial review, defect #2 — coordinator prompt: "a payout LARGER than the
 * clearing balance (over-settlement → exception, not a negative clearing)"). Thrown by
 * {@see \App\Services\Accounting\GatewaySettlementService::post()} when a settlement's own
 * clearing drain (`gross − recognisedFee`) exceeds the DERIVED balance of the company's
 * `GATEWAY_CLEARING_{gw}` leaf — Σdebit − Σcredit over live `journal_entries`, never
 * `accounts.actual_balance`.
 *
 * Post-fix re-verification (T7, second adversarial pass) rebased this guard from the pending
 * `Payment` pool onto that derived balance. The pool version skipped itself entirely whenever the
 * pool was empty, so a payout against a gateway with no unswept payments — every one already
 * released by the daily job, or a manual/CSV entry with no local linkage — posted unconditionally
 * and drove clearing negative: the precise failure this exception exists to prevent, left wide
 * open by the very carve-out meant to keep isolated posting-shape tests green. The balance is the
 * money; the pool was only ever a proxy for it, and comparing against the money makes the
 * invariant inductive (no sequence of settlements can overdraw clearing).
 *
 * Its sibling {@see GatewaySettlementUnmatchedException} covers the other half: a payout whose
 * gross does not land on a whole-payment boundary of the pending receipt trail.
 */
final class GatewayOverSettledException extends PostingException
{
    public function __construct(
        public readonly string $gateway,
        public readonly string $payoutReference,
        public readonly float $clearingDrain,
        public readonly float $clearingBalance,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            "Settlement for gateway '%s' payout reference '%s' would take KWD %.3f out of "
            .'GATEWAY_CLEARING_%s, but only KWD %.3f is actually in it (derived from posted '
            .'journal lines) — this is an over-settlement, not a rounding difference, and posting '
            .'it would leave the clearing account negative. Correct the payout figures, or record '
            .'the missing receipts, before settling it.',
            $this->gateway,
            $this->payoutReference,
            $this->clearingDrain,
            $this->gateway,
            $this->clearingBalance
        ));
    }
}
