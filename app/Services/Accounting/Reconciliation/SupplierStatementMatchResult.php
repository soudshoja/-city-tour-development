<?php

declare(strict_types=1);

namespace App\Services\Accounting\Reconciliation;

/**
 * accounting-builds T8 (Lane E). Counts returned by {@see SupplierStatementMatcher::match()},
 * mirrored onto `supplier_statement_imports.counts` for the list screen.
 */
final class SupplierStatementMatchResult
{
    public function __construct(
        public readonly int $matched = 0,
        public readonly int $disputed = 0,
        public readonly int $unmatchedStatement = 0,
    ) {}

    /** @return array{matched:int,disputed:int,unmatched_statement:int} */
    public function toArray(): array
    {
        return [
            'matched' => $this->matched,
            'disputed' => $this->disputed,
            'unmatched_statement' => $this->unmatchedStatement,
        ];
    }
}
