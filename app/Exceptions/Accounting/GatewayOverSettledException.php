<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Verifier fix (T7 adversarial review, defect #2 — coordinator prompt: "a payout LARGER than the
 * clearing balance (over-settlement → exception, not a negative clearing)"). Thrown by
 * {@see \App\Services\Accounting\GatewaySettlementService::post()} when a settlement's own
 * `gross` exceeds the total of the still-pending (`completed=0`) local `Payment` rows this
 * gateway has for the company, dated on/before the payout date — i.e. the payout claims to have
 * collected more than the company's own receipt trail shows as outstanding for it. Only checked
 * when that pending pool is non-empty (`pendingTotal > 0`): a genuinely empty pool (a
 * from-scratch manual/CSV entry with no local `Payment` linkage at all — the documented L11/Q1
 * "0 when unknown" shape this service already follows for `recognisedFee`) has nothing to
 * reconcile against and is not, on its own, evidence of an over-settlement.
 */
final class GatewayOverSettledException extends PostingException
{
    public function __construct(
        public readonly string $gateway,
        public readonly string $payoutReference,
        public readonly float $gross,
        public readonly float $pendingTotal,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            "Settlement for gateway '%s' payout reference '%s' reports gross KWD %.3f, but only "
            .'KWD %.3f is pending (unswept) locally for this gateway on or before the payout date '
            .'— this is an over-settlement, not a rounding difference. Correct the payout figures, '
            .'or investigate the missing receipts before recording it.',
            $this->gateway,
            $this->payoutReference,
            $this->gross,
            $this->pendingTotal
        ));
    }
}
