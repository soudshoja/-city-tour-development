<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * accounting-builds T1 (Lane A — realised FX on apply), coordinator steer 2026-09-02, point 2.
 * Thrown by {@see \App\Services\Accounting\RealisedFxService::compute()} when the SOURCE line's
 * `original_currency` differs from the APPLIED (invoice) line's `original_currency`.
 *
 * `AccountResolver::resolve()` has no currency dimension — per-currency separation in this engine
 * lives on the LINE (`journal_entries.original_currency`), never on the leaf — so nothing else in
 * the posting pipeline refuses a payment recorded in one currency being applied against an invoice
 * denominated in a DIFFERENT one. That is a genuinely different, disallowed scenario from "same
 * currency, different rate at two points in time" (which IS realised FX, the whole point of this
 * class): subtracting `a·r_s − a·r_t` across two different currencies would not be a real-world FX
 * gain/loss at all, just noise. Cross-currency applies are not allowed — the payer must record the
 * payment in the invoice's own currency; this is a loud, rejected data error, never a silent skip.
 */
final class CrossCurrencyApplyException extends PostingException
{
    public function __construct(
        public readonly int $sourceLineId,
        public readonly int $appliedLineId,
        public readonly string $sourceCurrency,
        public readonly string $appliedCurrency,
        public readonly string $idSource,
        public readonly int $id,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Cross-currency apply refused (apply %s:%d): source line #%d is in %s but applied '
            .'line #%d is in %s. The payer must record the payment in the invoice\'s own currency.',
            $this->idSource,
            $this->id,
            $this->sourceLineId,
            $this->sourceCurrency,
            $this->appliedLineId,
            $this->appliedCurrency
        ));
    }
}
