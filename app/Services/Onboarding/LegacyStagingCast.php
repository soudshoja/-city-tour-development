<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * legacy-ledger-pilot LP1b -- one coercion helper for reading the
 * deliberately all-TEXT `legacy_pilot.stg_*` staging columns (see
 * LegacyCsvLoader's docblock: the loader never types a column, everything
 * lands as nullable TEXT).
 *
 * Reading a staging TEXT column with a naive literal comparison
 * (`->where('posted', '1')`) is a real bug, not a style choice: the real
 * export stores boolean-shaped columns as the literal strings 'True' /
 * 'False' (not '1'/'0'), so a literal-equality filter silently matches
 * nothing. `MastersAuditor::auditUnpostedHeaders()` reported 37,134
 * "unposted" headers against real staging data (every row -- the true
 * count is 37, `config('legacy_pilot.posted_false_count')`) and
 * `auditCensus2025()` reported 0 actual documents for every SubType,
 * because both queries filtered on `posted` with a literal '1'/'0'/null
 * comparison instead of coercing the text value first.
 *
 * Every audit query (and anything else in App\Services\Onboarding that
 * reads stg_* columns) should go through this class rather than repeating
 * a bespoke `lower(trim(...))` comparison per call site.
 */
final class LegacyStagingCast
{
    /**
     * Case-insensitive, whitespace-trimmed truthy tokens the legacy export
     * uses for boolean-shaped TEXT columns. Covers both the real export's
     * 'True'/'False' and the plain numeric '1'/'0' shape older fixtures and
     * some staged columns already use.
     */
    private const TRUE_VALUES = ['true', '1', 'yes', 't', 'y'];

    /**
     * Coerce a single staging TEXT value to bool, in PHP (no query
     * builder involved) -- for values already fetched into memory.
     */
    public static function toBool(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), self::TRUE_VALUES, true);
    }

    /**
     * Coerce a single staging TEXT value to int, tolerating null/blank.
     */
    public static function toInt(mixed $value): ?int
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        return (int) $trimmed;
    }

    /**
     * Coerce a staging TEXT amount to a base-currency decimal, ROUNDED TO
     * $decimals (3 for KWD -- config('accounting.currency.decimals')'s
     * value, and the exact precision PLAN.md §5.2 O5's ratified pass line
     * is stated at).
     *
     * WHY A DEDICATED HELPER AND NOT `(float)`. Two failure modes, both
     * seen in this export family:
     *
     *   1. `(float) 'n/a'` is `0.0` in PHP -- and so is `(float) ''`,
     *      `(float) 'NULL'` and `(float) '#DIV/0!'`. Casting an unreadable
     *      anchor cell to a plausible 0.000 turns a broken load into a
     *      parity result that looks fine. Anything non-numeric that is not
     *      an explicit blank/NULL token throws.
     *   2. Some numeric columns in this export family carry thousands
     *      separators ("1,234.500") and a leading/trailing space. Both are
     *      normalised before the numeric test, not after -- `is_numeric`
     *      rejects "1,234.500" outright.
     *
     * $context is echoed in the exception so a refusal names the table,
     * account and column rather than just "bad number".
     */
    public static function toDecimal(mixed $value, int $decimals = 3, string $context = 'staging value'): float
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '' || strcasecmp($trimmed, 'null') === 0) {
            // A blank amount is a genuine zero throughout this export. A
            // blank is NOT the same as garbage, and only the blank case is
            // allowed to become 0.000.
            return 0.0;
        }

        $normalised = str_replace([',', ' '], '', $trimmed);

        if (! is_numeric($normalised)) {
            throw new \RuntimeException("{$context}: '{$trimmed}' is not a number — refusing to cast it to 0.000.");
        }

        return round((float) $normalised, $decimals);
    }

    /**
     * Extract a 4-digit calendar year from the front of a staging TEXT
     * date-shaped value (e.g. '2025-03-01', '2025-03-01 00:00:00'). Returns
     * null when the value does not start with a 4-digit year.
     */
    public static function toYear(mixed $value): ?int
    {
        $trimmed = trim((string) $value);

        if (preg_match('/^(\d{4})/', $trimmed, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * LP3 addition. Coerce a staging TEXT value to a trimmed non-empty string,
     * or null. Distinguishing "blank cell" from "the string ' '" matters at
     * every replay call site (a blank DocNo must not become a voucher_number of
     * one space).
     */
    public static function toString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * LP3 addition. Parse a staging TEXT date-shaped value into a real date.
     *
     * {@see self::toYear()} is enough for the audits, which only bucket by year.
     * The replay needs the whole date: it orders the run chronologically by
     * (DocDt, DocID) and stamps `transactions.transaction_date` from it, and a
     * SQL comparison over a TEXT date is a lexicographic sort that only
     * accidentally works for `YYYY-MM-DD`. Formats are tried most-specific
     * first, each with a round-trip check so `!Y-m-d` cannot silently swallow
     * `2025-03-01 09:00:00`.
     *
     * Returns null when the cell is blank or in no recognised shape -- the
     * caller decides what that means, because this class must never guess.
     */
    public static function toDate(mixed $value): ?\Carbon\CarbonImmutable
    {
        $token = self::toString($value);

        if ($token === null) {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            try {
                // Carbon runs in strict mode here, so a non-matching format
                // THROWS rather than returning false: the throw is this loop's
                // "try the next shape", not an error.
                $parsed = \Carbon\CarbonImmutable::rawCreateFromFormat($format, $token);
            } catch (\Carbon\Exceptions\InvalidFormatException) {
                continue;
            }

            if ($parsed !== null && $parsed->format(ltrim($format, '!')) === $token) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * Date shapes seen across the export's own columns, most specific first.
     * `!` resets unparsed fields so a date-only string does not inherit the
     * current clock.
     */
    private const DATE_FORMATS = [
        '!Y-m-d H:i:s',
        '!Y-m-d\TH:i:s',
        '!Y-m-d H:i',
        '!Y-m-d',
        '!d/m/Y H:i:s',
        '!d/m/Y',
        '!m/d/Y H:i:s',
        '!m/d/Y',
        '!d-M-Y H:i:s',
        '!d-M-Y',
    ];

    /**
     * Constrain a query builder to rows where $column is truthy per
     * self::TRUE_VALUES, at the SQL level (lower/trim applied in-database
     * so it works whether the underlying value is 'True', 'true', '1', or
     * padded with whitespace).
     */
    public static function whereTruthy(Builder $query, string $column): Builder
    {
        return $query->whereIn(DB::raw("lower(trim({$column}))"), self::TRUE_VALUES);
    }

    /**
     * Constrain a query builder to rows where $column is NOT truthy per
     * self::TRUE_VALUES (including null/blank), at the SQL level.
     */
    public static function whereFalsy(Builder $query, string $column): Builder
    {
        return $query->where(function (Builder $q) use ($column) {
            $q->whereNull($column)
                ->orWhereNotIn(DB::raw("lower(trim({$column}))"), self::TRUE_VALUES);
        });
    }
}
