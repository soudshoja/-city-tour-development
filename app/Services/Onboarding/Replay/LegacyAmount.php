<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Replay;

/**
 * legacy-ledger-pilot LP3 -- exact 3-decimal arithmetic over the STAGED
 * STRINGS.
 *
 * MAPPING-RULES.md §5.3 mandates staging Debit/Credit/FCDebit/FCCredit as
 * `DECIMAL(18,3)` so "LP0.4 asserts per-document sums IN SQL, on the staged
 * decimals, before any PHP float touches them". LP1 did not do that:
 * {@see \App\Services\Onboarding\LegacyCsvLoader::ensureTable()} creates EVERY
 * stg_* column as `text` (deliberately -- see that class's docblock: an earlier
 * numeric-inference revision broke on real legacy data). The staged values are
 * therefore decimal STRINGS.
 *
 * That makes this class load-bearing rather than a convenience. `(float)
 * '0.001'` is not 0.001, and summing 179,421 such floats to decide whether a
 * document balances is precisely the "float-drift canary" §5.3 warns about.
 * Every amount the replay reasons about is converted here, exactly, to an
 * integer number of thousandths (millis); balance decisions, staged sums and
 * the per-document staged-vs-posted comparison are all integer comparisons.
 * The float conversion happens exactly once, at the last possible moment, when
 * a LineDraft is constructed.
 *
 * A value this class cannot parse EXACTLY is a refusal, never a `?? 0`.
 */
final class LegacyAmount
{
    public const SCALE = 3;

    /**
     * Parse a staged decimal string into an exact integer number of
     * thousandths. Accepts an empty/NULL cell as zero (a legacy CSV writes an
     * empty cell for an unset money column); refuses anything else it cannot
     * represent exactly at 3 dp.
     *
     * @throws LegacyDocumentRefused when the value is not an exact 3-dp decimal
     */
    public static function millis(mixed $value, string $context, ?int $legacyDocId = null): int
    {
        if ($value === null) {
            return 0;
        }

        $raw = trim((string) $value);

        if ($raw === '' || $raw === 'NULL' || $raw === 'null') {
            return 0;
        }

        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d*))?$/', $raw, $m)) {
            throw new LegacyDocumentRefused(
                'legacy.amount_unparseable',
                $legacyDocId,
                sprintf('%s: cannot parse "%s" as an exact decimal amount.', $context, $raw)
            );
        }

        $fraction = $m[3] ?? '';

        // More than 3 decimals would have to be rounded, and a rounded staged
        // amount is exactly the silent precision loss O6 exists to prevent.
        if (rtrim(substr($fraction, self::SCALE), '0') !== '') {
            throw new LegacyDocumentRefused(
                'legacy.amount_precision',
                $legacyDocId,
                sprintf('%s: "%s" carries more than %d significant decimals.', $context, $raw, self::SCALE)
            );
        }

        $fraction = str_pad(substr($fraction, 0, self::SCALE), self::SCALE, '0');
        $millis = (int) ($m[2].$fraction);

        return $m[1] === '-' ? -$millis : $millis;
    }

    /**
     * Millis -> the float a LineDraft/DocumentDraft carries. The ONE place the
     * replay crosses into floating point.
     */
    public static function toFloat(int $millis): float
    {
        return round($millis / 1000, self::SCALE);
    }

    /**
     * Millis -> the exact decimal string map_document's DECIMAL(18,3) columns
     * take, so the audit row never round-trips through a float either.
     */
    public static function toDecimalString(int $millis): string
    {
        $sign = $millis < 0 ? '-' : '';
        $abs = abs($millis);

        return sprintf('%s%d.%03d', $sign, intdiv($abs, 1000), $abs % 1000);
    }

    /**
     * A free-precision decimal (an FC amount or an exchange rate) as a float.
     * FC amounts and rates are NOT 3-dp quantities -- `FcExchRate` is
     * decimal(18,12) legacy-side -- so they cannot go through millis(). They
     * never participate in a balance decision either (MAPPING-RULES §5.1: "the
     * balance check and the totals are base-currency only"), which is what
     * makes a float safe here and unsafe above.
     *
     * @throws LegacyDocumentRefused when the value is not numeric at all
     */
    public static function toDecimalFloat(mixed $value, string $context, ?int $legacyDocId = null): float
    {
        if ($value === null) {
            return 0.0;
        }

        $raw = trim((string) $value);

        if ($raw === '' || $raw === 'NULL' || $raw === 'null') {
            return 0.0;
        }

        if (! is_numeric($raw)) {
            throw new LegacyDocumentRefused(
                'legacy.amount_unparseable',
                $legacyDocId,
                sprintf('%s: cannot parse "%s" as a number.', $context, $raw)
            );
        }

        return (float) $raw;
    }
}
