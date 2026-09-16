<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA12;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * CT-A12 — the census: a bare `Y-m-d` bound may not meet a `datetime`/`timestamp` column.
 *
 * A source scanner, not a runtime test. It reads `app/` and fails the build on the shape that
 * silently truncated the last day of every report range:
 *
 *     ->whereBetween('transaction_date', [$from, $to])        // $to === '2026-09-30'
 *
 * against a `datetime` column, where MySQL widens the bare date to `'2026-09-30 00:00:00'`.
 *
 * ── Scoped by a PROPERTY, not by a remembered list ────────────────────────────────────────────
 * The obvious implementation hardcodes "the columns that are datetimes". That list is wrong the
 * day someone adds a column, and it rots silently — a ratchet that has stopped matching reads
 * exactly like a clean codebase. (Akeed's own rule of this shape needed three attempts for
 * precisely that reason.)
 *
 * So the at-risk column set is DERIVED, fresh, on every run, from the project's own migrations:
 * every `->dateTime('x')` and `->timestamp('x')` declaration, plus the `created_at`/`updated_at`
 * that `->timestamps()` implies. `->date('x')` declarations are deliberately NOT included — a bare
 * `Y-m-d` against a `DATE` column is exact, and calling it a defect would produce noise that gets
 * the whole ratchet switched off. Add a datetime column tomorrow and this scanner covers it
 * without anybody remembering to update it.
 *
 * ── What counts as a normalised bound ─────────────────────────────────────────────────────────
 * A bound is accepted when its expression demonstrates, in the source, that it carries a time:
 * `ReportDateRange::`, `startOfDay(`, `endOfDay(`, `endOfMonth(`, `endOfYear(`, `startOfMonth(`,
 * `startOfYear(`, `endOfWeek(`, `startOfWeek(`, or a literal `00:00:00` / `23:59:59`. `whereDate()`
 * is not scanned at all: it wraps the COLUMN in `DATE()`, which is correct (if non-sargable) and is
 * a different trade, not this defect.
 *
 * @see \App\Support\ReportDateRange
 * @see \Tests\Feature\Accounting\CtA12\DateRangeBoundaryTest  the behavioural half
 */
class DateRangeBoundaryRatchetTest extends TestCase
{
    /**
     * Sites that compare an at-risk column against a bound this scanner cannot prove is
     * normalised, and which have been reviewed and found safe for a reason the source cannot show.
     *
     * SHRINK-ONLY. Adding an entry requires the reason, in the entry.
     *
     * @var array<string, string>
     */
    private const ALLOW_LISTED = [
        // The bound is built by the caller and this method takes its Carbons literally; both
        // callers (ReportController::trialBalance()/trialBalancePdf() via ReportDateRange, and
        // PeriodCloseChecklistService via startOfDay()/endOfDay()) are themselves scanned.
        'app/Services/TrialBalanceService.php:findUnbalancedTransactions' => 'bounds normalised at both call sites; the service takes Carbons literally by contract',
        // Dead code: an unconditional `return` sits above this query, so it is unreachable.
        // Reported by CT-A12 for removal under the stale-code rule rather than fixed in place.
        'app/Http/Controllers/MobileController.php:getUserTask' => 'UNREACHABLE — an unconditional return precedes this query (CT-A12-2)',
    ];

    /** Expressions that prove a bound carries a time of day. */
    private const NORMALISED = [
        'ReportDateRange::', 'startOfDay(', 'endOfDay(', 'startOfMonth(', 'endOfMonth(',
        'startOfYear(', 'endOfYear(', 'startOfWeek(', 'endOfWeek(', '00:00:00', '23:59:59',
    ];

    public function test_no_accounting_query_compares_a_bare_date_against_a_datetime_column(): void
    {
        $atRisk = $this->atRiskColumns($this->projectRoot().'/database/migrations');

        $this->assertGreaterThan(
            20,
            count($atRisk),
            'the at-risk column set is derived from the migrations; if it has collapsed, the '
                .'derivation has broken and this ratchet is scanning for nothing'
        );
        $this->assertContains('transaction_date', $atRisk, 'the column this defect was found on must be in the derived set');
        $this->assertNotContains('invoice_date', $atRisk, 'a DATE column must NOT be in the set — a bare bound against it is exact');

        $violations = $this->scan($this->projectRoot().'/app', $atRisk, self::ALLOW_LISTED);

        $this->assertSame(
            [],
            $violations,
            "A bare date bound is being compared against a datetime/timestamp column. MySQL widens\n"
                ."'2026-09-30' to '2026-09-30 00:00:00', so the last day of the range is truncated and\n"
                ."the report silently loses rows. Normalise the bound with App\\Support\\ReportDateRange,\n"
                ."or use whereDate() if a non-sargable predicate is acceptable:\n  - ".implode("\n  - ", $violations)
        );
    }

    /**
     * ── THE SYNTHETIC TWIN ──────────────────────────────────────────────────────────────────────
     * The scanner is pointed at a throwaway tree carrying the forbidden shape and must report it.
     * Without this, a regex that quietly stopped matching would read as a clean codebase — which is
     * the failure mode this whole ratchet exists to prevent, so it must not be able to have it.
     */
    public function test_the_ratchet_actually_bites_a_synthetic_violation(): void
    {
        $root = sys_get_temp_dir().'/cta12-ratchet-'.bin2hex(random_bytes(6));
        @mkdir($root.'/app', 0777, true);
        @mkdir($root.'/database/migrations', 0777, true);

        file_put_contents($root.'/database/migrations/2026_01_01_000000_create_probe.php', <<<'PHP'
<?php
return new class {
    public function up(): void {
        Schema::create('probe', function ($table) {
            $table->dateTime('settled_at');
            $table->date('booked_on');
        });
    }
};
PHP);

        file_put_contents($root.'/app/BadReport.php', <<<'PHP'
<?php
class BadReport {
    public function run($from, $to) {
        return DB::table('probe')->whereBetween('settled_at', [$from, $to])->get();
    }
}
PHP);

        // The control: the SAME query shape against the DATE column must NOT be reported.
        file_put_contents($root.'/app/FineReport.php', <<<'PHP'
<?php
class FineReport {
    public function run($from, $to) {
        return DB::table('probe')->whereBetween('booked_on', [$from, $to])->get();
    }
}
PHP);

        // The second control: a normalised bound on the datetime column must NOT be reported.
        file_put_contents($root.'/app/FixedReport.php', <<<'PHP'
<?php
class FixedReport {
    public function run($from, $to) {
        return DB::table('probe')
            ->whereBetween('settled_at', [ReportDateRange::start($from), ReportDateRange::end($to)])
            ->get();
    }
}
PHP);

        try {
            $atRisk = $this->atRiskColumns($root.'/database/migrations');

            $this->assertContains('settled_at', $atRisk, 'dateTime() must be derived as at-risk');
            $this->assertNotContains('booked_on', $atRisk, 'date() must NOT be derived as at-risk');

            $violations = $this->scan($root.'/app', $atRisk, []);

            $this->assertCount(1, $violations, 'exactly one of the three synthetic files is a violation');
            $this->assertStringContainsString('BadReport.php', $violations[0]);
            $this->assertStringContainsString('settled_at', $violations[0]);
        } finally {
            foreach (['BadReport.php', 'FineReport.php', 'FixedReport.php'] as $f) {
                @unlink($root.'/app/'.$f);
            }
            @unlink($root.'/database/migrations/2026_01_01_000000_create_probe.php');
            @rmdir($root.'/app');
            @rmdir($root.'/database/migrations');
            @rmdir($root.'/database');
            @rmdir($root);
        }
    }

    /**
     * ── THE REPLAY ──────────────────────────────────────────────────────────────────────────────
     * A synthetic twin proves the scanner can bite something. It does not prove it would have
     * bitten THIS defect — the one that was actually live, in this repo, on these files. So the
     * real pre-fix source of each repaired site is reconstructed and re-scanned.
     *
     * If someone reverts a fix, this test says which file and which shape, by name.
     */
    public function test_the_ratchet_would_have_caught_the_real_defect(): void
    {
        $atRisk = $this->atRiskColumns($this->projectRoot().'/database/migrations');

        // The exact source lines as they stood before CT-A12, one per repaired site.
        $preFix = [
            'ReportController::accountsReconciliationReport' => "->whereBetween('journal_entries.transaction_date', [\$from, \$to])",
            'ReportController::accountsReconciliationReport (2)' => "->whereBetween('transaction_date', [\$from, \$to])",
            'JournalEntryController::exportPdf' => "->whereBetween('transaction_date', [\$dateFrom, \$dateTo])",
            'SupplierController::ledgerByDateRange' => "->whereBetween('supplier_pay_date', [\$fromDate, \$toDate])",
            'AccountingController::filterLedgers' => "->where('transaction_date', '<=', \$toDate)",
            'BankPaymentController::fetchPaymentsByDate bound' => "->whereBetween('journal_entries.transaction_date', [\$request->from, \$request->to])",
        ];

        foreach ($preFix as $label => $line) {
            $this->assertNotSame(
                [],
                $this->scanSource('replay/'.$label.'.php', $line, $atRisk, []),
                "REPLAY FAILED: the ratchet does not flag the real pre-CT-A12 shape at {$label}. "
                    .'A census that would not have caught the defect it was written for is decorative.'
            );
        }

        // And the repaired shapes must be clean — otherwise the ratchet would fail the build
        // forever and get switched off.
        $postFix = [
            "->whereBetween('transaction_date', [ReportDateRange::start(\$dateFrom), ReportDateRange::end(\$dateTo)])",
            "->where('transaction_date', '<=', ReportDateRange::end(\$toDate))",
            "->whereBetween('transaction_date', [\$from->startOfDay(), \$to->endOfDay()])",
        ];

        foreach ($postFix as $line) {
            $this->assertSame([], $this->scanSource('replay/fixed.php', $line, $atRisk, []), "a repaired shape must be clean: {$line}");
        }
    }

    // ── the scanner ─────────────────────────────────────────────────────────────────────────────

    private function projectRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    /**
     * Column names declared as `dateTime()` or `timestamp()` anywhere in the migrations, plus the
     * pair `timestamps()` implies. Derived, never listed.
     *
     * @return string[]
     */
    private function atRiskColumns(string $migrationsDir): array
    {
        $cols = [];

        if (is_dir($migrationsDir)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($migrationsDir, RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($it as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $src = (string) file_get_contents($file->getPathname());

                // `->datetime()` and `->dateTime()` are both used in this repo, and `supplier_pay_date`
                // is declared with the LOWERCASE spelling inside a `->change()`. The REPLAY test
                // caught this: a case-sensitive regex silently missed one of the three columns the
                // defect was actually found on, which is the exact failure mode a census must not
                // be able to have.
                if (preg_match_all('/->(?:datetime|timestamp)\(\s*[\'"]([A-Za-z0-9_]+)[\'"]/i', $src, $m)) {
                    $cols = array_merge($cols, $m[1]);
                }

                if (str_contains($src, '->timestamps(')) {
                    $cols[] = 'created_at';
                    $cols[] = 'updated_at';
                }
            }
        }

        // A name declared ANYWHERE as a plain `date()` is ambiguous across tables; treat it as safe
        // only when it is never declared as a datetime. That keeps the set conservative in the
        // direction that produces a refusal rather than a miss.
        return array_values(array_unique($cols));
    }

    /**
     * @param  string[]  $atRisk
     * @param  array<string, string>  $allowList
     * @return string[]
     */
    private function scan(string $appDir, array $atRisk, array $allowList): array
    {
        $violations = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appDir, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($it as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen(dirname($appDir)) + 1));
            $src = (string) file_get_contents($file->getPathname());

            foreach ($this->scanSource($rel, $src, $atRisk, $allowList) as $v) {
                $violations[] = $v;
            }
        }

        sort($violations);

        return $violations;
    }

    /**
     * @param  string[]  $atRisk
     * @param  array<string, string>  $allowList
     * @return string[]
     */
    private function scanSource(string $rel, string $src, array $atRisk, array $allowList): array
    {
        $out = [];
        $lines = explode("\n", $src);
        $fn = null;
        $fnStart = 0;

        foreach ($lines as $i => $line) {
            if (preg_match('/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $line, $m)) {
                $fn = $m[1];
                $fnStart = $i;
            }

            $key = $rel.':'.$fn;

            if ($fn !== null && array_key_exists($key, $allowList)) {
                continue;
            }

            // Documentation is not code. Both this class and ReportDateRange quote the defective
            // shape verbatim in their docblocks, and a scanner that flagged its own explanation of
            // the defect would be unusable.
            $trimmed = ltrim($line);

            if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/*')) {
                continue;
            }

            // whereBetween('col', [a, b])
            if (preg_match('/whereBetween\(\s*[\'"]([A-Za-z0-9_.]+)[\'"]\s*,\s*\[([^\]]*)\]/', $line, $m)) {
                if ($this->isAtRisk($m[1], $atRisk) && $this->isRangeBound($m[2]) && ! $this->isNormalised($m[2], $lines, $fnStart, $i)) {
                    $out[] = sprintf('%s:%d (%s) whereBetween on `%s` with an un-normalised bound', $rel, $i + 1, $fn ?? '?', $m[1]);
                }
            }

            // ->where('col', '<=' | '>=', expr)
            if (preg_match('/->where\(\s*[\'"]([A-Za-z0-9_.]+)[\'"]\s*,\s*[\'"](<=|>=)[\'"]\s*,\s*([^)]+)\)/', $line, $m)) {
                if ($this->isAtRisk($m[1], $atRisk) && $this->isRangeBound($m[3]) && ! $this->isNormalised($m[3], $lines, $fnStart, $i)) {
                    $out[] = sprintf('%s:%d (%s) where(`%s` %s) with an un-normalised bound', $rel, $i + 1, $fn ?? '?', $m[1], $m[2]);
                }
            }
        }

        return $out;
    }

    /** @param string[] $atRisk */
    private function isAtRisk(string $column, array $atRisk): bool
    {
        $base = str_contains($column, '.') ? substr($column, strrpos($column, '.') + 1) : $column;

        return in_array($base, $atRisk, true);
    }

    /**
     * Is this bound demonstrably carrying a time of day?
     *
     * The check is deliberately NOT line-local, because the normalisation almost never is.
     * `$dateToEnd = $dateTo . ' 23:59:59';` sits twenty lines above the query that uses it, and a
     * `Carbon $from` parameter means the CALLER normalised — and the caller is scanned too. A
     * line-local version of this scanner reported 39 sites, of which all but four were already
     * correct somewhere above the line. That is the noise level at which a ratchet gets switched
     * off, so the resolution is part of the rule rather than an allow-list of its own false
     * positives.
     *
     * Each `$variable` in the bound expression is resolved against (a) a `Carbon`-typed parameter
     * in the signature, and (b) any assignment to it earlier in the same function whose right-hand
     * side is normalised. Both are properties read out of the source, not remembered entries.
     *
     * @param  string[]  $lines
     */
    private function isNormalised(string $bounds, array $lines = [], int $fnStart = 0, int $upTo = 0): bool
    {
        foreach (self::NORMALISED as $marker) {
            if (str_contains($bounds, $marker)) {
                return true;
            }
        }

        if ($lines === [] || ! preg_match_all('/\$([A-Za-z_][A-Za-z0-9_]*)/', $bounds, $vars)) {
            return false;
        }

        $signature = $lines[$fnStart] ?? '';

        foreach ($vars[1] as $v) {
            // `Carbon`, `CarbonInterface` and `CarbonImmutable` all mean "the caller supplied an
            // instant". Matching only the bare `Carbon` missed ReconciliationService's own widened
            // signature and reported it as a violation — the scanner has to know the type family,
            // not one spelling of it.
            if (preg_match('/\??Carbon(?:Interface|Immutable)?\s+\$'.preg_quote($v, '/').'\b/', $signature) === 1) {
                return true;
            }
        }

        for ($j = $fnStart; $j < $upTo; $j++) {
            $prev = $lines[$j];

            foreach ($vars[1] as $v) {
                if (preg_match('/\$'.preg_quote($v, '/').'\s*=[^=]/', $prev) !== 1) {
                    continue;
                }

                foreach (self::NORMALISED as $marker) {
                    if (str_contains($prev, $marker)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Is this bound one end of a REPORT DATE RANGE, as opposed to a moving cutoff?
     *
     * This is the property that makes the ratchet usable rather than noisy.
     * `->where('created_at', '>=', now()->subDays(7))` compares a timestamp column against a Carbon
     * INSTANT: correct, and nothing to do with this defect. `->whereBetween('transaction_date',
     * [$from, $to])` compares it against the two ends of a range a user typed, which is where a
     * bare `Y-m-d` gets in.
     *
     * The two idioms are told apart by what the bound IS: a range end is a bare `Y-m-d` literal, a
     * request input, or a variable named like one end of a range. Anything else — `now()`,
     * `$cutoff`, `$asOf`, `$startTime`, `$today` — is a cutoff and is not scanned. Scoping this way
     * took the scan from 58 reported sites, nearly all of them correct moving cutoffs, down to the
     * ones that are genuinely this defect. A ratchet that cries wolf 58 times gets switched off,
     * and then it protects nothing.
     */
    private function isRangeBound(string $bounds): bool
    {
        if (preg_match('/[\'"]\d{4}-\d{1,2}-\d{1,2}[\'"]/', $bounds) === 1) {
            return true;
        }

        if (str_contains($bounds, '$request->') || str_contains($bounds, '->input(')) {
            return true;
        }

        return preg_match('/\$(?:date)?(?:from|to|start|end)(?:date|_date)?\b/i', $bounds) === 1;
    }
}
