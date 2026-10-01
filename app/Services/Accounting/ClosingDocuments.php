<?php

declare(strict_types=1);

namespace App\Services\Accounting;

/**
 * XBRL-X9r (PLAN.md L25, H2 N-B2; X9R-VERIFY M-2): the ONE definition of the two document
 * families that are never period MOVEMENT. Every reader that excludes them (ProfitLossService,
 * TrialBalanceService, GeneralLedgerService, LedgerFigures, EquityChangesReportService,
 * ClosedYearAdjustmentService) calls this class; `ClosingDocumentsRatchetTest` fails if a reader
 * writes its own `doc_type = 'YEC'` comparison again, which is how the GL and the parity oracle
 * drifted from the P&L before.
 *
 * 1. **The year-end close family:** a `YEC` document, and a reversal of one (`doc_type = 'REV'`
 *    with `sub_type = 'YEC'`; {@see PostingService::reverse()} always stamps the reversed
 *    document's own doc_type into the reversal's `sub_type`). A YEC and its reversal are an exact
 *    line-by-line mirror, and a pre-closing movement excludes the whole closing family. A reader
 *    that excluded the YEC but counted its reversal counted the year's profit twice: on a fence,
 *    a reopened FY2025 with profit 5,000 plus a 1,000 adjustment read 11,000 instead of 6,000.
 *
 * 2. **The prior-period adjustment family:** a `PPA` document (L15 route (ii), IAS 8), dated the
 *    first day of the current year, which is part of the OPENING position and never current-year
 *    movement; and a reversal of one (`REV` / `sub_type = 'PPA'`).
 *
 * NULL-safety: the family test is written on `COALESCE(doc_type, '')` and `COALESCE(sub_type, '')`,
 * so a legacy header (doc_type NULL) or a reversal with no sub_type is simply NOT in the family,
 * and the negated form keeps it; a bare `doc_type <> 'YEC'` would drop it (UNKNOWN, not TRUE).
 * A REV-of-REV (`sub_type = 'REV'`) is not in the family: PostingService::reverse() refuses to
 * reverse a closing-family reversal, so that shape cannot be posted.
 */
final class ClosingDocuments
{
    public const YEAR_END_CLOSE = 'YEC';

    public const PRIOR_PERIOD_ADJUSTMENT = 'PPA';

    public const REVERSAL = 'REV';

    /** `$alias` is in the year-end close family. */
    public static function whereYearEndCloseFamily($query, string $alias)
    {
        return $query->whereRaw(self::familySql($alias, self::YEAR_END_CLOSE));
    }

    /** `$alias` is NOT in the year-end close family (NULL-safe: a headerless/legacy row is kept). */
    public static function whereNotYearEndCloseFamily($query, string $alias)
    {
        return $query->whereRaw('NOT '.self::familySql($alias, self::YEAR_END_CLOSE));
    }

    /** `$alias` is in the prior-period adjustment family. */
    public static function wherePriorPeriodAdjustmentFamily($query, string $alias)
    {
        return $query->whereRaw(self::familySql($alias, self::PRIOR_PERIOD_ADJUSTMENT));
    }

    /**
     * Excludes, whole-document, every journal line whose header is in the year-end close family.
     * `$transactionIdColumn` is the journal line's own `transaction_id` column (for example
     * `je.transaction_id`). A line with no header (transaction_id NULL) is kept.
     */
    public static function excludeYearEndCloseFamily($query, string $transactionIdColumn)
    {
        return self::excludeFamily($query, $transactionIdColumn, self::YEAR_END_CLOSE);
    }

    /** As {@see self::excludeYearEndCloseFamily()}, for the prior-period adjustment family. */
    public static function excludePriorPeriodAdjustmentFamily($query, string $transactionIdColumn)
    {
        return self::excludeFamily($query, $transactionIdColumn, self::PRIOR_PERIOD_ADJUSTMENT);
    }

    /** Is a header with this doc_type/sub_type in the family? The PHP twin of familySql(). */
    public static function isInFamily(?string $docType, ?string $subType, string $family): bool
    {
        return $docType === $family || ($docType === self::REVERSAL && $subType === $family);
    }

    private static function excludeFamily($query, string $transactionIdColumn, string $docType)
    {
        return $query->whereNotExists(function ($sub) use ($transactionIdColumn, $docType) {
            $sub->selectRaw('1')
                ->from('transactions as closing_family_t')
                ->whereColumn('closing_family_t.id', $transactionIdColumn)
                ->whereRaw(self::familySql('closing_family_t', $docType));
        });
    }

    /**
     * THE definition. `$alias` is a trusted, code-supplied table alias (never user input);
     * `$docType` is one of this class's constants.
     */
    private static function familySql(string $alias, string $docType): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias) !== 1) {
            throw new \InvalidArgumentException("ClosingDocuments: not a table alias: {$alias}");
        }

        $d = "COALESCE({$alias}.doc_type, '')";
        $s = "COALESCE({$alias}.sub_type, '')";

        return "({$d} = '{$docType}' OR ({$d} = '".self::REVERSAL."' AND {$s} = '{$docType}'))";
    }
}
