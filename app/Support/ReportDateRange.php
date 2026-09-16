<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * CT-A12 — the two ends of a report's date range, normalised once.
 *
 * ── The defect this exists to stop ────────────────────────────────────────────────────────────
 * A report asked for "1 September to 30 September" was built as
 *
 *     ->whereBetween('transaction_date', [$from, $to])        // $to === '2026-09-30'
 *
 * against a **`datetime`** column. MySQL widens the bare `Y-m-d` to `'2026-09-30 00:00:00'`, so
 * every document timed after midnight on the last day of the range is **silently excluded**. The
 * report renders, foots, and is wrong.
 *
 * It is not a corner case here. Measured read-only on the LIVE database (2026-09-17):
 *
 *     transactions.transaction_date      678 rows, KWD 156,661.492 lost   (2.29 % of all rows)
 *     journal_entries.transaction_date   497 lines, Dr 54,552.409 / Cr 53,215.409
 *     tasks.supplier_pay_date             18 tasks, KWD   4,701.499
 *
 * — and 21,330 of 43,485 transactions (49.1 %) carry a non-midnight `transaction_date`, so the
 * population that CAN fall off the end of a range is about half the ledger.
 *
 * ── Why the bound is normalised, and not the column ───────────────────────────────────────────
 * The three candidate fixes are `whereDate()`, wrapping the column in `DATE(...)`, and normalising
 * the bound. The first two put a function on the **column**, which makes the predicate
 * non-sargable — MySQL can no longer use an index on `transaction_date`, and `journal_entries` is
 * the largest table these reports touch. Normalising the bound changes only the literal, so the
 * index is still used and the query plan is unchanged.
 *
 * It is also what the sites in this codebase that were already CORRECT do —
 * `TrialBalanceService::generate()`, `GeneralLedgerService`, `YearEndCloseService`,
 * `PeriodCloseChecklistService`, `ReportController::profitLoss()` and the `rangeSales*` family all
 * normalise with `startOfDay()` / `endOfDay()`. Using the same shape everywhere means there is one
 * thing to look for rather than three, which is what makes
 * {@see \Tests\Feature\Accounting\DateRangeBoundaryRatchetTest} able to scan for it at all.
 *
 * ── The trap on the upper bound, handled here rather than at each call site ───────────────────
 * `->endOfDay()` on a Carbon that already carries a meaningful time **overwrites** it: a caller who
 * deliberately asked for "up to 2026-09-15 12:00" would silently get the whole day. So
 * {@see self::end()} only widens a bound that is a **bare date** — a `Y-m-d` string, or a Carbon
 * sitting exactly at midnight. Anything carrying a time of day is passed through untouched.
 *
 * A Carbon at exactly `00:00:00` is treated as a bare date on purpose. As an UPPER bound that value
 * is the defect itself (it is what `Carbon::parse('2026-09-30')` produces), and a report that
 * genuinely means "up to and including midnight exactly, excluding the rest of the day" does not
 * exist in this application. The judgement is recorded here rather than left implicit.
 *
 * This is a helper for REPORT RANGE bounds built from user input. It is not a general datetime
 * clamp, and it must not be used to normalise a stored value.
 */
final class ReportDateRange
{
    /**
     * The lower bound: the very start of the given day.
     *
     * Normalising this end is usually a no-op (`Carbon::parse('2026-09-01')` is already midnight),
     * and it is done explicitly anyway so that a caller who passes a datetime cannot silently lose
     * the earlier part of the first day — the mirror of the defect this class exists for.
     */
    public static function start(string|CarbonInterface|null $value): ?Carbon
    {
        $parsed = self::parse($value);

        return $parsed?->startOfDay();
    }

    /**
     * The upper bound: the end of the given day, when the caller supplied a bare date.
     *
     * A value that already carries a time of day is returned as-is — see the class docblock.
     */
    public static function end(string|CarbonInterface|null $value): ?Carbon
    {
        $parsed = self::parse($value);

        if ($parsed === null) {
            return null;
        }

        return self::isBareDate($value, $parsed) ? $parsed->endOfDay() : $parsed;
    }

    private static function parse(string|CarbonInterface|null $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value)->copy();
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** A `Y-m-d` string, or any value that parsed to exactly midnight. */
    private static function isBareDate(string|CarbonInterface|null $value, Carbon $parsed): bool
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', trim($value)) === 1) {
            return true;
        }

        return $parsed->format('H:i:s.u') === '00:00:00.000000';
    }
}
