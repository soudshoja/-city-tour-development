<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by {@see \App\Services\Accounting\PeriodGuard::assertOpen()} (P2.5.A — replacing the P1
 * no-op stub) when the resolved accounting period for a posting date refuses the write:
 *   - status = `locked` and `$allowLockedPeriods` was not set (reserved for the year-end close job
 *     only — see PeriodGuard's own docblock; never exposed to a controller);
 *   - status = `soft_closed` and the actor lacks `accounting.period.post-soft-closed` (or an
 *     equivalent admin/accountant tier) OR no override reason was supplied on the draft
 *     (`DocumentDraft::$overrideReason`).
 *
 * `$status` distinguishes the two refusal shapes above for a caller/test that needs to branch on
 * it, rather than parsing the message string.
 */
final class PeriodLockedException extends PostingException
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $year,
        public readonly int $month,
        public readonly string $status,
        ?string $message = null,
    ) {
        $periodLabel = $month === 0
            ? sprintf('%04d (annual)', $year)
            : sprintf('%04d-%02d', $year, $month);

        parent::__construct($message ?? sprintf(
            'Accounting period %s for company #%d is %s and refuses this posting.',
            $periodLabel,
            $companyId,
            $status
        ));
    }
}
