<?php

declare(strict_types=1);

namespace App\Services\Accounting\Reconciliation;

/**
 * accounting-builds T9 (Wave 2). Counts returned by {@see BankStatementMatcher::match()}, mirrored
 * onto `bank_statement_imports.counts` for the list screen. Adds per-tier counts (spec: "each
 * matching tier ... with precedence proven") on top of T8's `SupplierStatementMatchResult` shape.
 */
final class BankStatementMatchResult
{
    public function __construct(
        public readonly int $matched = 0,
        public readonly int $disputed = 0,
        public readonly int $unmatchedStatement = 0,
        public readonly int $matchedByAuthNo = 0,
        public readonly int $matchedByReference = 0,
        public readonly int $matchedByAmountAndDate = 0,
    ) {}

    /**
     * @return array{matched:int,disputed:int,unmatched_statement:int,matched_by_auth_no:int,
     *               matched_by_reference:int,matched_by_amount_and_date:int}
     */
    public function toArray(): array
    {
        return [
            'matched' => $this->matched,
            'disputed' => $this->disputed,
            'unmatched_statement' => $this->unmatchedStatement,
            'matched_by_auth_no' => $this->matchedByAuthNo,
            'matched_by_reference' => $this->matchedByReference,
            'matched_by_amount_and_date' => $this->matchedByAmountAndDate,
        ];
    }
}
