<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by {@see \App\Services\Accounting\PeriodCloseService::reopen()} (P2.5.C; period-lock-
 * design.md §8.2's dependency-aware unlock, applied to Layer 2 — a whole period rather than one
 * manually-pinned record): a chronologically LATER period for this company is still
 * `soft_closed`/`locked`, and reopening an earlier period would leapfrog it — "never leapfrogged"
 * per the design doc's own wording. The caller must reopen `$blockingYear`-`$blockingMonth` first.
 *
 * Deliberately NOT a {@see PostingException} subclass: this is a period-CONTROL violation (the
 * close/reopen action itself), never something the posting pipeline (PostingService/AccountResolver)
 * throws while writing a document — same family separation {@see PostingException}'s own docblock
 * already draws for AccountValidationException.
 */
final class PeriodDependencyBlockedException extends \RuntimeException
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $year,
        public readonly int $month,
        public readonly int $blockingYear,
        public readonly int $blockingMonth,
        public readonly string $blockingStatus,
    ) {
        parent::__construct(sprintf(
            'Cannot reopen %04d-%02d for company #%d: %04d-%02d is still %s and must be reopened first (never leapfrogged).',
            $year,
            $month,
            $companyId,
            $blockingYear,
            $blockingMonth,
            $blockingStatus
        ));
    }
}
