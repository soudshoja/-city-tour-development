<?php

declare(strict_types=1);

namespace App\Services\Accounting;

/**
 * accounting-builds T1 (Lane A). Result of {@see RealisedFxService::compute()}: the two balanced
 * lines of one realised-FX document, plus the facts a caller needs to log/assert without
 * re-deriving them (leaf hit, sign, magnitude). Never posted by this class itself — see
 * {@see RealisedFxService::postForApply()}.
 */
final class RealisedFxDraft
{
    public function __construct(
        /** The party's own line — explicit accountId (the SOURCE line's own account), side flips
         *  per the DC-aware mapping (PLAN.md §2 spec). */
        public readonly LineDraft $partyLine,
        /** The FX_GAIN_REALISED or FX_LOSS_REALISED line — opposite side of $partyLine, same
         *  amount, so the two-line document is self-balancing by construction. */
        public readonly LineDraft $fxLine,
        /** abs(D) in KWD, 3dp — the magnitude both lines carry. */
        public readonly float $amount,
        /** True when this is a realised GAIN (FX_GAIN_REALISED hit); false for a LOSS
         *  (FX_LOSS_REALISED hit). */
        public readonly bool $isGain,
        /** The source line's own side ('debit'|'credit') — what the DC-aware mapping keyed off. */
        public readonly string $sourceSide,
        /** Signed D = round(a*sourceRate - a*appliedRate, 3), before abs() — kept for logging/
         *  assertions; the two LineDraft objects above already encode the correct sides. */
        public readonly float $signedDifference,
    ) {}
}
