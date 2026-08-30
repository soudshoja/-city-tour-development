<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G): "unmatch refused when the period is locked" — thrown by
 * {@see \App\Services\Accounting\ReconciliationProposalService::manualUnmatch()} when the target
 * journal line's own posting period ({@see \App\Models\AccountingPeriod}, keyed off
 * `posting_date` — never `transaction_date`, same rule {@see \App\Services\Accounting\PeriodGuard}
 * uses) is `locked`. A missing `accounting_periods` row is treated as open, matching PeriodGuard's
 * own "no row = open" convention — this exception is only ever thrown against a row that actually
 * resolved to `locked`.
 */
final class ReconciliationPeriodLockedException extends \RuntimeException
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $year,
        public readonly int $month,
    ) {
        parent::__construct(sprintf(
            'Accounting period %04d-%02d for company #%d is locked; this line cannot be unmatched. Reopen the period first.',
            $year,
            $month,
            $companyId
        ));
    }
}
