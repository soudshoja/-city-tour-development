<?php

declare(strict_types=1);

namespace App\Events\Accounting;

/**
 * XBRL X9 (PLAN.md L13, X9; H2 N-m3): the year-end reserve appropriation of one fiscal year was
 * recorded by {@see \App\Services\Accounting\ReserveAppropriationService}: a journal was posted, or
 * a nil appropriation was recorded for a loss or zero-profit year.
 *
 * Dispatched synchronously, inside the appropriation's own transaction, and only when something
 * changed (a re-run that changes nothing dispatches nothing). The host's own durable record is the
 * `reserve_appropriation` row in `accounting_audit_log`; the XBRL module listens and writes its
 * filing-trail event (`appropriation_posted` / `nil_appropriation`, `filing_id` NULL), which the
 * close checklist reads. The host never imports the module: this event is the whole coupling.
 *
 * Every amount is integer fils.
 */
final class ReserveAppropriationRecorded
{
    public const OUTCOME_POSTED = 'posted';

    public const OUTCOME_NIL = 'nil';

    public const OUTCOME_CAP = 'cap_reached';

    public function __construct(
        public readonly int $companyId,
        public readonly int $fiscalYear,
        /** posted | nil | cap_reached */
        public readonly string $outcome,
        /** The year's profit (loss negative), pre-closing, in fils. */
        public readonly int $profitFils,
        /** The year's statutory transfer after this run, in fils (0 for nil). */
        public readonly int $statutoryFils,
        /** The year's voluntary transfer after this run, in fils (0 for nil). */
        public readonly int $voluntaryFils,
        /** `loss` / `zero_profit` for a nil appropriation, else null. */
        public readonly ?string $nilReason,
        /** The journal posted by this run (a correction when a figure changed), or null. */
        public readonly ?int $transactionId,
        public readonly ?int $userId,
    ) {}

    public function totalFils(): int
    {
        return $this->statutoryFils + $this->voluntaryFils;
    }
}
