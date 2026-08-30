<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * P2.5.B (p2_5-brief.md §P2.5.B; period-lock-design.md §8.1): thrown by
 * {@see \App\Services\Accounting\PeriodGuard::earliestOpenOnOrAfter()} when a normal posting
 * attempt's own period is not open (`soft_closed`/`locked`) AND no company-initialised period
 * from that point forward, within a bounded lookahead window, is open either.
 *
 * This is NOT the everyday shape — `accounting_periods` treats a missing row as `open` (see
 * {@see \App\Models\AccountingPeriod} / PeriodGuard's own docblock), so any company with even one
 * un-created future period row already has somewhere open to land, and this exception should be
 * effectively unreachable in real operation. It exists only to fail loudly, rather than loop
 * forever or silently keep the document's original (non-open) date, in the pathological case
 * where an operator has locked/soft_closed every period `accounting:periods:init` has ever
 * generated for a company, with no open row anywhere ahead.
 */
final class NoOpenPeriodFoundException extends PostingException
{
    public function __construct(
        public readonly int $companyId,
        public readonly \DateTimeInterface $searchedFrom,
        public readonly int $lookaheadPeriods,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'No open accounting period found for company #%d within %d periods on or after %s. '
                .'Every period this company has initialised in that window is soft_closed or locked.',
            $companyId,
            $lookaheadPeriods,
            $searchedFrom->format('Y-m-d')
        ));
    }
}
