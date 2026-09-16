<?php

declare(strict_types=1);

namespace Tests\Unit\Legacy;

use App\Services\Onboarding\Replay\LegacyAmount;
use App\Services\Onboarding\Replay\LegacyDocumentRefused;
use PHPUnit\Framework\TestCase;

/**
 * legacy-ledger-pilot LP3 -- {@see LegacyAmount}, the class every replayed
 * money value passes through.
 *
 * MAPPING-RULES.md §5.3 mandates that per-document sums be decided on the
 * STAGED DECIMALS before any PHP float touches them. LP1's loader stages every
 * stg_* column as `text` (deliberately -- see LegacyCsvLoader), so the staged
 * values are decimal STRINGS and this class is what keeps the decision exact:
 * `(float) '0.001'` is not 0.001, and summing 179,421 such floats to decide
 * whether a document balances is precisely the float-drift canary §5.3 warns
 * about.
 *
 * That makes it the most load-bearing 130 lines in LP3 and it shipped with no
 * tests at all. Everything below is a behaviour the replay depends on:
 * a mis-parse here does not throw, it silently posts the wrong number.
 */
class LegacyAmountTest extends TestCase
{
    /**
     * The exactness the whole design rests on. `0.001` is one thousandth --
     * the O5 pass line itself (0.001 KWD per account).
     *
     * @dataProvider exactValues
     */
    public function test_an_exact_three_decimal_string_becomes_exact_thousandths(mixed $raw, int $expected): void
    {
        $this->assertSame($expected, LegacyAmount::millis($raw, 'test'));
    }

    /** @return array<string, array{0: mixed, 1: int}> */
    public static function exactValues(): array
    {
        return [
            'the pass line itself' => ['0.001', 1],
            'plain integer' => ['5', 5000],
            'trailing zeros' => ['5.000', 5000],
            'two decimals' => ['12.34', 12340],
            'three decimals' => ['123.456', 123456],
            'explicit positive sign' => ['+1.000', 1000],
            'negative' => ['-2.500', -2500],
            // MAPPING-RULES §1.2 (a) drops zero-amount lines rather than
            // posting them; both spellings of zero must read as the same zero,
            // and -0.000 must NOT come back as a negative that later flips a
            // sign comparison.
            'negative zero is zero' => ['-0.000', 0],
            'plain zero' => ['0', 0],
            // The export writes an unset money column as an empty cell.
            'empty cell' => ['', 0],
            'null' => [null, 0],
            'literal NULL token' => ['NULL', 0],
            'lowercase null token' => ['null', 0],
            // Staged text can carry the CSV's own padding.
            'surrounding whitespace' => ["  7.250\t", 7250],
            'whitespace-only cell' => ['   ', 0],
            // The closing anchor is 35,859,419.537 KWD; int64 holds its
            // thousandths (3.6e10) with eleven digits to spare, so no sum in
            // this dataset can overflow.
            'the closing anchor magnitude' => ['35859419.537', 35859419537],
            'more zeros than scale is not precision loss' => ['1.5000000', 1500],
        ];
    }

    /**
     * "Refuse, never default" (§0.3). Each of these would, under a `(float)`
     * cast or a `?? 0`, produce a plausible wrong number instead of an error.
     *
     * @dataProvider refusedValues
     */
    public function test_a_value_that_cannot_be_represented_exactly_is_refused(mixed $raw, string $because): void
    {
        $this->expectException(LegacyDocumentRefused::class);

        LegacyAmount::millis($raw, $because);
    }

    /** @return array<string, array{0: mixed, 1: string}> */
    public static function refusedValues(): array
    {
        return [
            // (float) '1e3' is 1000.0 -- a thousand KWD conjured from a token
            // that is not a decimal amount at all.
            'scientific notation' => ['1e3', 'scientific notation is not an exact decimal'],
            // (float) '1,234.500' is 1.0 in PHP: the comma truncates. A
            // thousands separator arriving here means the export contract
            // changed, and reading it as 1.000 KWD would be catastrophic and
            // silent.
            'thousands separator' => ['1,234.500', 'a grouped number is not a staged decimal'],
            'four significant decimals' => ['0.0001', 'rounding to 3 dp is the precision loss O6 exists to prevent'],
            'four significant decimals with a leading value' => ['12.3456', 'same, with an integer part'],
            'alphabetic' => ['abc', 'not a number'],
            'currency symbol' => ['KWD 5.000', 'not a number'],
            'bare decimal point' => ['.5', 'no integer part'],
            'double sign' => ['--1.000', 'not a number'],
            'internal space' => ['1 000.000', 'not a number'],
            'hex' => ['0x10', 'not a number'],
        ];
    }

    /**
     * The one crossing into floating point, and the exact string the
     * DECIMAL(18,3) audit columns take. `toDecimalString()` must never
     * round-trip through a float -- that is what it exists to avoid.
     */
    public function test_millis_render_back_as_the_exact_decimal_string(): void
    {
        $this->assertSame('0.001', LegacyAmount::toDecimalString(1));
        $this->assertSame('5.000', LegacyAmount::toDecimalString(5000));
        $this->assertSame('123.456', LegacyAmount::toDecimalString(123456));
        $this->assertSame('-2.500', LegacyAmount::toDecimalString(-2500));
        $this->assertSame('0.000', LegacyAmount::toDecimalString(0));
        $this->assertSame('35859419.537', LegacyAmount::toDecimalString(35859419537));
    }

    /** Round-tripping must be the identity, or the audit row disagrees with the ledger. */
    public function test_parse_and_render_round_trip_exactly(): void
    {
        foreach (['0.001', '5.000', '123.456', '-2.500', '0.000', '35859419.537'] as $raw) {
            $this->assertSame($raw, LegacyAmount::toDecimalString(LegacyAmount::millis($raw, 'round trip')));
        }
    }

    /**
     * §5.3's actual claim: summing the staged decimals as integers is exact
     * where summing them as floats is not. `0.1 + 0.2 !== 0.3` in binary
     * floating point, and at 179,421 lines the drift is not theoretical.
     */
    public function test_integer_summation_is_exact_where_float_summation_is_not(): void
    {
        $staged = array_fill(0, 1000, '0.001');

        $millis = 0;
        $float = 0.0;

        foreach ($staged as $raw) {
            $millis += LegacyAmount::millis($raw, 'sum');
            $float += (float) $raw;
        }

        $this->assertSame(1000, $millis);
        $this->assertSame('1.000', LegacyAmount::toDecimalString($millis));
        // The float path does not land on 1.0 exactly -- which is the whole
        // reason millis() exists. If this ever becomes false, PHP's arithmetic
        // changed and the docblock's justification needs revisiting.
        $this->assertNotSame(1.0, $float);
    }

    /**
     * §1.2 (c): FC amounts and exchange rates are NOT 3-dp quantities
     * (`FcExchRate` is decimal(18,12) legacy-side) and never take part in a
     * balance decision, so they go through the free-precision path instead.
     */
    public function test_free_precision_values_take_the_decimal_float_path(): void
    {
        $this->assertSame(0.303951367781, LegacyAmount::toDecimalFloat('0.303951367781', 'rate'));
        $this->assertSame(0.0, LegacyAmount::toDecimalFloat(null, 'rate'));
        $this->assertSame(0.0, LegacyAmount::toDecimalFloat('', 'rate'));
        $this->assertSame(0.0, LegacyAmount::toDecimalFloat('NULL', 'rate'));
        $this->assertSame(1234.5678, LegacyAmount::toDecimalFloat('1234.5678', 'fc amount'));
    }

    public function test_a_non_numeric_rate_is_refused_rather_than_defaulted(): void
    {
        $this->expectException(LegacyDocumentRefused::class);

        LegacyAmount::toDecimalFloat('n/a', 'rate');
    }

    /** The refusal has to name the document, or an error queue of 37k rows is unusable. */
    public function test_a_refusal_carries_its_context_and_document_id(): void
    {
        try {
            LegacyAmount::millis('1e3', 'doc 4242 line 99 Debit', 4242);
            $this->fail('expected a refusal');
        } catch (LegacyDocumentRefused $e) {
            $this->assertSame(4242, $e->legacyDocId);
            $this->assertStringContainsString('doc 4242 line 99 Debit', $e->getMessage());
            $this->assertSame('legacy.amount_unparseable', $e->failureCode);
        }
    }

    public function test_a_precision_refusal_is_classified_separately_from_an_unparseable_one(): void
    {
        try {
            LegacyAmount::millis('0.0001', 'ctx', 7);
            $this->fail('expected a refusal');
        } catch (LegacyDocumentRefused $e) {
            $this->assertSame('legacy.amount_precision', $e->failureCode);
        }
    }
}
