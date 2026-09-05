<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Post-fix re-verification (T7, second adversarial pass): a settlement whose `gross` does not land
 * exactly on a boundary of the pending `Payment` trail it is supposed to release.
 *
 * `Payment.completed` — the SAME flag `PaymentReleaseToCompanyBankAccProcess` uses to decide what
 * it still has to sweep from clearing to bank — is a BOOLEAN. A payment can be released or not
 * released; it can never be half-released. So when a payout's `gross` falls between two payment
 * boundaries (pending 100 / 200 / 300, payout 250) there is no assignment of that flag that tells
 * the truth:
 *
 *   - stop at the last payment that FITS (mark 100): the settlement drains 250 from clearing but
 *     leaves 500 still `completed = 0`, so the daily job later releases 500 more — 750 out of a
 *     clearing account that only ever received 600;
 *   - include the overshooting payment (mark 300): the settlement drains 250 while marking 300 as
 *     released, orphaning 50 in clearing that nothing will ever move again.
 *
 * Both directions silently corrupt the clearing account, which is precisely what the owner-approved
 * spec forbids: "unmatched/over-settled cases produce exceptions, never silent absorption". A
 * non-aligned payout IS the unmatched case — the payout's composition does not agree with the
 * company's own receipt trail — so it is refused and an operator reconciles it (correct the
 * figures, split the payout, or record the missing receipts) rather than the engine guessing.
 *
 * Only raised when a pending pool actually exists for the gateway: with no local `Payment` linkage
 * at all (a from-scratch manual/CSV payout) there is nothing to align against, and the money is
 * protected instead by the derived clearing-balance guard
 * ({@see GatewayOverSettledException}).
 */
final class GatewaySettlementUnmatchedException extends PostingException
{
    public function __construct(
        public readonly string $gateway,
        public readonly string $payoutReference,
        public readonly float $gross,
        public readonly float $coveredTotal,
        public readonly float $pendingTotal,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            "Settlement for gateway '%s' payout reference '%s' reports gross KWD %.3f, but the "
            .'pending receipt trail for this gateway (KWD %.3f outstanding) has no combination of '
            .'whole payments summing to it — the closest oldest-first coverage is KWD %.3f. A '
            .'payment cannot be part-released, so this payout would either strand or double-move '
            .'the difference. Split the payout, correct its figures, or record the missing '
            .'receipts before settling it.',
            $this->gateway,
            $this->payoutReference,
            $this->gross,
            $this->pendingTotal,
            $this->coveredTotal
        ));
    }
}
