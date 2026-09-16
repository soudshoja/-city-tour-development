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
 * ── CT-A13: four holes this scanner had, and what closed them ─────────────────────────────────
 * CT-A12 shipped this ratchet, and CT-A13 then found three more LIVE sites of the same defect that
 * it had scanned straight past. Each one was a hole in the scanner rather than a gap in the
 * enumeration, so each is closed here and proved by its own synthetic test below.
 *
 *  1. **`DB::raw()` column expressions were invisible.** Both regexes required a STRING LITERAL
 *     first argument, so every predicate written as
 *     `->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', $to)` was never
 *     examined — and that is the form the accounting code uses almost everywhere, because
 *     P2.5.B/BUG-C4 made "the period a document lands in" a coalesce rather than a column. The
 *     blind spot is what hid `SupplierController::getTotalDebitCredit` (a live supplier balance)
 *     and `BankStatementMatcher::reconciliationReport` (the bank reconciliation's `difference`).
 *     The column argument is now matched in either form, and a raw expression is at risk when ANY
 *     identifier inside it is — which is also the correct rule for a coalesce, since
 *     `COALESCE(DATE, DATETIME)` promotes the whole expression to DATETIME.
 *  2. **Any earlier normalisation whitewashed the rest of the function.** `isNormalised()` scanned
 *     forward from the start of the function and returned true on the FIRST assignment carrying a
 *     marker, whatever branch it was in. CT-A12's own fix introduced exactly that whitewash:
 *     `ReportController` line 947 normalises `$endDate` inside `if ($startDate == null && ...)`,
 *     and from there on every later use of `$endDate` in that method read as normalised — strip
 *     `->endOfDay()` off the both-dates branch and the ratchet stayed green. It now resolves the
 *     NEAREST PRECEDING assignment and judges that one, so a later branch is judged on its own.
 *  3. **Only the first line of a signature was read.** `$lines[$fnStart]` is the `function` line,
 *     so a declaration whose parameters sit on their own lines (`TrialBalanceService::
 *     findUnbalancedTransactions`) lost its `?Carbon $dateTo` and had to be allow-listed for what
 *     was really a scanner bug. The declaration is now joined up to its `{`.
 *  4. **`<` and `>` were not recognised.** Only `<=`/`>=` were matched, so the exclusive spelling
 *     of the same cutoff went unscanned.
 *
 * Three smaller changes come with them, each forced by a site the closed holes then exposed.
 *
 *  - A bound whose nearest assignment came from `$request->`, `->input(` or `->toDateString()`
 *    counts as a report bound even when it is not NAMED like one. That is what makes `$asOf`
 *    scannable — the name both of CT-A13's missed service-layer sites happened to use, and a name
 *    the original rule explicitly classified as a moving cutoff.
 *  - The markers are matched CASE-INSENSITIVELY. `ProfileController` line 111 spells it
 *    `->endOfmonth()`, which PHP resolves fine (method names are case-insensitive) and a
 *    `str_contains` does not. CT-A12 hit the same class of bug on `->datetime()` vs `->dateTime()`
 *    in the migration scan and fixed it there only.
 *  - An assignment can prove its value is an INSTANT rather than a date, which is a different
 *    claim from proving it was normalised. `$to = $latestTask->created_at` reads a datetime column
 *    (derived from the migrations, like everything else here) and `$from = $to->copy()->subDays(7)`
 *    is Carbon arithmetic on one; neither is a bare `Y-m-d` and neither can be. A date-only
 *    rendering anywhere in the assignment — `->toDateString()`, `->format('Y-m-d')` — DEFEATS that
 *    evidence, because collapsing an instant to a date is this defect performed in one call; that
 *    is what keeps `$windowEnd = $valueDate->copy()->addDays($n)->toDateString()` reported.
 *
 * Hole 2 needed one more thing than the nearest-assignment rule to actually close. A
 * `whereBetween` was judged as ONE string, so a normalised LOWER end vouched for the upper one —
 * and `ReportController` line 960 normalises `$startDate` immediately above line 961's
 * `[$startDate, $endDate]`. Strip `->endOfDay()` off `$endDate` there and the nearest-assignment
 * rule alone still read green, because `$startDate` answered for it. A range is now judged on its
 * UPPER end alone, which is the only end that can carry this defect: a bare `Y-m-d` lower bound
 * widens to 00:00:00, and that is exactly what "from the 1st" means. `DotwAI\StatementService` is
 * the site that makes the asymmetry necessary rather than merely tidy — it pairs a bare
 * `string $dateFrom` with `$dateTo . ' 23:59:59'`, and it is correct.
 *
 * ── The allow-list must bite ──────────────────────────────────────────────────────────────────
 * {@see self::test_every_allow_list_entry_is_load_bearing()} removes each entry in turn and fails
 * if the census stays green without it. CT-A12 claimed both of its entries were "confirmed
 * load-bearing by emptying it"; emptying the WHOLE list proves only that at LEAST ONE entry
 * matters, and one of those two was in fact dead weight — its only predicate was a
 * `whereBetween(DB::raw(...))` the scanner could not match in the first place. Per-entry is the
 * only form of that proof that means anything, and it is mechanical here rather than a claim.
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
     * SHRINK-ONLY. Adding an entry requires the reason, in the entry, AND the entry must be
     * load-bearing — {@see self::test_every_allow_list_entry_is_load_bearing()} fails the build on
     * an entry whose removal changes nothing.
     *
     * The key is `file:functionName`, and the scanner tracks where a function STARTS, never where
     * it ends. An entry therefore suppresses its function AND everything below it in that file
     * until the next `function` line. That is tolerable at one entry and shrinking; it would not
     * be if the list grew, and this caveat is the reason it must not.
     *
     * @var array<string, string>
     */
    private const ALLOW_LISTED = [
        // Dead code: an unconditional `return` sits above this query at MobileController:1244, at
        // the method's own top level, so lines 1258-1261 can never run. The route that reaches it
        // (POST /test-user-task/{userId}, routes/api.php:90) therefore returns only the two echoed
        // dates. Reported by CT-A12 (finding CT-A12-2) for removal under the stale-code rule
        // rather than fixed in place; OpenAiController::getUserTask is a SEPARATE, correct copy
        // (it appends ' 00:00:00' / ' 23:59:59' to its bounds) and is not affected.
        'app/Http/Controllers/MobileController.php:getUserTask' => 'UNREACHABLE — an unconditional return precedes this query (CT-A12-2)',
    ];

    /** Expressions that prove a bound carries a time of day. */
    private const NORMALISED = [
        'ReportDateRange::', 'startOfDay(', 'endOfDay(', 'startOfMonth(', 'endOfMonth(',
        'startOfYear(', 'endOfYear(', 'startOfWeek(', 'endOfWeek(', '00:00:00', '23:59:59',
    ];

    /**
     * Expressions showing a bound was BUILT from user input or from a date-only rendering, and is
     * therefore one end of a report range even when it is not named like one.
     *
     * `->toDateString()` is the strongest of the three: it produces a bare `Y-m-d` by definition,
     * which is this defect in a single call. Deliberately NOT included: `->format('Y-m-d H:i:s')`,
     * which renders an instant complete with its time and is the opposite of this defect.
     */
    private const RANGE_PROVENANCE = ['$request->', '->input(', 'toDateString('];

    /**
     * Expressions proving an assignment produced an INSTANT, not a date — a claim the scanner has
     * to be able to make separately from "this bound was normalised".
     *
     * `->copy()` and Carbon's day arithmetic only exist on a Carbon, and arithmetic on an instant
     * keeps that instant's time of day. `Carbon::parse(` is deliberately absent: parsing a bare
     * `Y-m-d` is precisely how this defect is usually spelled.
     */
    private const INSTANT_EVIDENCE = ['->copy()', 'subDays(', 'addDays(', 'subDay(', 'addDay('];

    /**
     * Renderings that collapse an instant back to a bare date, defeating {@see self::INSTANT_EVIDENCE}.
     *
     * `$valueDate->copy()->addDays(3)->toDateString()` is Carbon arithmetic AND this defect; the
     * last call is the one that decides. `format('Y-m-d H:i:s')` is deliberately not listed — it
     * renders the time too, which is the opposite of the defect.
     */
    private const DATE_ONLY_RENDERING = ['toDateString(', "format('Y-m-d')", 'format("Y-m-d")'];

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
     *
     * CT-A13 adds its own three sites to the same list. Two of them are whole SNIPPETS rather than
     * single lines, because what made them invisible was not only the `DB::raw()` column but the
     * assignment above the query: `$asOf = $import->statement_to?->toDateString()` is what turns an
     * innocuously-named variable into one end of a report range.
     */
    public function test_the_ratchet_would_have_caught_the_real_defect(): void
    {
        $atRisk = $this->atRiskColumns($this->projectRoot().'/database/migrations');

        // The exact source lines as they stood before the fix, one per repaired site.
        $preFix = [
            'ReportController::accountsReconciliationReport' => "->whereBetween('journal_entries.transaction_date', [\$from, \$to])",
            'ReportController::accountsReconciliationReport (2)' => "->whereBetween('transaction_date', [\$from, \$to])",
            'JournalEntryController::exportPdf' => "->whereBetween('transaction_date', [\$dateFrom, \$dateTo])",
            'SupplierController::ledgerByDateRange' => "->whereBetween('supplier_pay_date', [\$fromDate, \$toDate])",
            'AccountingController::filterLedgers' => "->where('transaction_date', '<=', \$toDate)",
            'BankPaymentController::fetchPaymentsByDate bound' => "->whereBetween('journal_entries.transaction_date', [\$request->from, \$request->to])",

            // CT-A13. All three are DB::raw() COALESCE predicates — the shape CT-A12's scanner
            // could not see at all.
            'SupplierController::getTotalDebitCredit (CT-A13)' => "    public function getTotalDebitCredit(\$supplierId, \$endDate)\n"
                ."        \$endDate = new DateTime(\$endDate);\n"
                ."            ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', \$endDate)",
            'BankStatementMatcher::reconciliationReport (CT-A13)' => "    public function reconciliationReport(BankStatementImport \$import): array\n"
                ."        \$asOf = \$import->statement_to?->toDateString() ?? now()->toDateString();\n"
                ."            ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', \$asOf)",
            'BankStatementMatcher tier-3 window (CT-A13)' => "    private function matchLine(\$line): array\n"
                ."        \$windowEnd = \$valueDate->copy()->addDays(\$windowDays)->toDateString();\n"
                ."            ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', \$windowEnd)",
        ];

        foreach ($preFix as $label => $line) {
            $this->assertNotSame(
                [],
                $this->scanSource('replay/'.$label.'.php', $line, $atRisk, []),
                "REPLAY FAILED: the ratchet does not flag the real pre-fix shape at {$label}. "
                    .'A census that would not have caught the defect it was written for is decorative.'
            );
        }

        // And the repaired shapes must be clean — otherwise the ratchet would fail the build
        // forever and get switched off.
        $postFix = [
            "->whereBetween('transaction_date', [ReportDateRange::start(\$dateFrom), ReportDateRange::end(\$dateTo)])",
            "->where('transaction_date', '<=', ReportDateRange::end(\$toDate))",
            "->whereBetween('transaction_date', [\$from->startOfDay(), \$to->endOfDay()])",
            "        \$endDate = ReportDateRange::end(\$endDate);\n"
                ."            ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', \$endDate)",
        ];

        foreach ($postFix as $line) {
            $this->assertSame([], $this->scanSource('replay/fixed.php', $line, $atRisk, []), "a repaired shape must be clean: {$line}");
        }
    }

    /**
     * ── CT-A13 HOLE 1: a `DB::raw()` column expression ──────────────────────────────────────────
     * The single most valuable of the four. Nearly every accounting date predicate in this codebase
     * is written against `COALESCE(posting_date, transaction_date)` rather than a bare column, and
     * CT-A12's scanner could not see one of them.
     *
     * Both halves are asserted: the raw COALESCE must be REPORTED (because `transaction_date`
     * inside it is a datetime, and the coalesce promotes the whole expression to datetime), and a
     * raw expression whose identifiers are all DATE columns must NOT be.
     */
    public function test_the_ratchet_sees_through_a_db_raw_column_expression(): void
    {
        $atRisk = ['transaction_date', 'created_at'];

        $flagged = $this->scanSource(
            'app/Probe.php',
            "        \$endDate = new DateTime(\$endDate);\n"
                ."            ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', \$endDate)",
            $atRisk,
            []
        );

        $this->assertNotSame([], $flagged, 'a DB::raw() COALESCE naming a datetime column must be scanned');
        $this->assertStringContainsString('COALESCE(posting_date, transaction_date)', $flagged[0]);

        $this->assertSame(
            [],
            $this->scanSource('app/Probe.php', "->where(DB::raw('COALESCE(posting_date, invoice_date)'), '<=', \$endDate)", $atRisk, []),
            'a raw expression built only from DATE columns is exact and must NOT be reported'
        );

        $this->assertSame(
            [],
            $this->scanSource('app/Probe.php', "->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', ReportDateRange::end(\$endDate))", $atRisk, []),
            'the repaired form of the same predicate must be clean'
        );

        $this->assertNotSame(
            [],
            $this->scanSource('app/Probe.php', "->whereBetween(DB::raw('COALESCE(t.posting_date, t.transaction_date)'), [\$dateFrom, \$dateTo])", $atRisk, []),
            'whereBetween() must see through DB::raw() too, including table-qualified identifiers'
        );
    }

    /**
     * ── CT-A13 HOLE 2: normalisation in an EARLIER BRANCH ───────────────────────────────────────
     * This is the mutation, run the way the defect occurs. `ReportController::
     * unpaidaccountsPayableReceivableReport()` has three mutually exclusive branches; CT-A12 fixed
     * the first one, and that fix then vouched for the other two. The synthetic below is that
     * method's shape with `->endOfDay()` stripped off the LAST branch — the old scanner returned
     * green on it, and this test is what makes it red.
     */
    public function test_an_earlier_branchs_normalisation_does_not_whitewash_a_later_one(): void
    {
        $atRisk = ['transaction_date'];

        $mutated = "    public function report(\$startDate, \$endDate)\n"
            ."        if (\$startDate == null && \$endDate !== null) {\n"
            ."            \$endDate = ReportDateRange::end(\$endDate);\n"
            ."            \$payableQuery->where('transaction_date', '<=', \$endDate);\n"
            ."        }\n"
            ."        if (\$startDate && \$endDate) {\n"
            ."            \$endDate = Carbon::parse(\$endDate);\n"
            ."            \$payableQuery->where('transaction_date', '<=', \$endDate);\n"
            .'        }';

        $violations = $this->scanSource('app/Probe.php', $mutated, $atRisk, []);

        $this->assertCount(
            1,
            $violations,
            'the SECOND branch re-assigns $endDate without normalising it and must be reported on '
                .'its own merits; the first branch normalising the same variable is not a licence. '
                .'Reported: '.implode(' | ', $violations)
        );
        $this->assertStringContainsString(':8', $violations[0], 'the violation must name the LATER branch, not the fixed one');

        // The real, unmutated shape of that method — all three branches normalised — stays clean.
        $real = str_replace('Carbon::parse($endDate);', 'Carbon::parse($endDate)->endOfDay();', $mutated);

        $this->assertSame([], $this->scanSource('app/Probe.php', $real, $atRisk, []), 'the real three-branch shape must be clean');

        // And the same mutation in the shape it actually takes in ReportController: a whereBetween
        // whose LOWER end is normalised on the line above. This is the half the nearest-assignment
        // rule does not catch on its own — `$startDate` answers for `$endDate` unless the upper end
        // is judged alone.
        $pairMutated = implode("\n", [
            '    public function report($startDate, $endDate)',
            '        if ($startDate && $endDate) {',
            '            $startDate = Carbon::parse($startDate)->startOfDay();',
            '            $endDate = Carbon::parse($endDate);',
            "            \$payableQuery->whereBetween('transaction_date', [\$startDate, \$endDate]);",
            '        }',
        ]);

        $this->assertCount(
            1,
            $this->scanSource('app/Probe.php', $pairMutated, $atRisk, []),
            'a normalised LOWER bound must not vouch for an un-normalised upper one'
        );

        $pairReal = str_replace('Carbon::parse($endDate);', 'Carbon::parse($endDate)->endOfDay();', $pairMutated);

        $this->assertSame([], $this->scanSource('app/Probe.php', $pairReal, $atRisk, []), 'the real pair must be clean');

        // The control for the asymmetry: a bare LOWER bound beside a normalised upper one is
        // CORRECT — `>= '2026-09-01'` is midnight on the 1st, which is what the range means — and
        // must not be reported. DotwAI\StatementService is the live instance of this shape.
        $bareLower = implode("\n", [
            '    public function getStatement(string $dateFrom, string $dateTo): array',
            "        \$dateToEnd = \$dateTo . ' 23:59:59';",
            "        ->whereBetween('transaction_date', [\$dateFrom, \$dateToEnd])",
        ]);

        $this->assertSame(
            [],
            $this->scanSource('app/Probe.php', $bareLower, $atRisk, []),
            'a bare `Y-m-d` LOWER bound is exact and must not be reported'
        );
    }

    /**
     * ── CT-A13 HOLE 3: a signature that does not fit on one line ────────────────────────────────
     * `TrialBalanceService::findUnbalancedTransactions(int $companyId, ?Carbon $dateFrom = null,
     * ?Carbon $dateTo = null)` declares its parameters one per line. Reading only `$lines[$fnStart]`
     * lost both Carbon types, and the site was allow-listed to compensate — an allow-list entry
     * standing in for a scanner bug, which is the worst kind because it looks like a decision.
     */
    public function test_the_ratchet_reads_a_parameter_declared_on_its_own_line(): void
    {
        $atRisk = ['transaction_date'];

        $multiLine = "    public function findUnbalancedTransactions(\n"
            ."        int \$companyId,\n"
            ."        ?Carbon \$dateFrom = null,\n"
            ."        ?Carbon \$dateTo = null\n"
            ."    ): Collection {\n"
            .'        $query->whereBetween(DB::raw(\'COALESCE(t.posting_date, t.transaction_date)\'), [$dateFrom, $dateTo]);';

        $this->assertSame(
            [],
            $this->scanSource('app/Probe.php', $multiLine, $atRisk, []),
            'a Carbon-typed parameter on its own line means the CALLER normalised, and the callers '
                .'are scanned themselves — this must not be reported'
        );

        // Control: the same declaration with the types removed IS a violation, so the test above
        // is passing because the types were read, not because the whole shape is unmatched.
        $untyped = str_replace('?Carbon $dateTo = null', '$dateTo = null', str_replace('?Carbon $dateFrom = null,', '$dateFrom = null,', $multiLine));

        $this->assertNotSame(
            [],
            $this->scanSource('app/Probe.php', $untyped, $atRisk, []),
            'with the Carbon types gone, nothing shows the bound carries a time and it must be reported'
        );
    }

    /**
     * ── CT-A13 HOLE 4: `<` and `>` ──────────────────────────────────────────────────────────────
     * The exclusive spelling of a cutoff is the same defect. `->where('transaction_date', '<',
     * '2026-10-01')` is in fact the CORRECT way to write "everything up to the end of September",
     * but only because the bound was moved to the next day; write it as `'<', '2026-09-30'` and the
     * whole of the 30th disappears rather than most of it.
     */
    public function test_the_ratchet_sees_a_strict_inequality(): void
    {
        $atRisk = ['transaction_date'];

        foreach (['<', '>'] as $op) {
            $this->assertNotSame(
                [],
                $this->scanSource('app/Probe.php', "->where('transaction_date', '{$op}', \$toDate)", $atRisk, []),
                "a `{$op}` bound is the same defect as `{$op}=` and must be scanned"
            );
        }

        $this->assertSame(
            [],
            $this->scanSource('app/Probe.php', "->where('transaction_date', '<', ReportDateRange::end(\$toDate))", $atRisk, []),
            'a normalised strict bound must still be clean'
        );
    }

    /**
     * ── THE ALLOW-LIST MUST BITE ────────────────────────────────────────────────────────────────
     * Every entry is removed in turn and the census re-run. If the census stays green without an
     * entry, that entry is dead weight: it documents a decision the scanner never actually reaches,
     * and it will quietly outlive the code it describes.
     *
     * CT-A12 shipped two entries and asserted both were load-bearing, having proved it by emptying
     * the list ALL AT ONCE — which demonstrates only that at least one of them mattered.
     * `TrialBalanceService::findUnbalancedTransactions` was in fact never reported by that scanner
     * at all (its only predicate is a `whereBetween(DB::raw(...))`), so the entry was decoration.
     * This test is the rule that stops that happening again, mechanically.
     */
    public function test_every_allow_list_entry_is_load_bearing(): void
    {
        $atRisk = $this->atRiskColumns($this->projectRoot().'/database/migrations');

        $this->assertNotSame([], self::ALLOW_LISTED, 'if the allow-list is ever empty, delete it and this test together');

        foreach (self::ALLOW_LISTED as $key => $reason) {
            $this->assertNotSame('', trim($reason), "allow-list entry {$key} carries no reason");

            [$file, $fn] = explode(':', $key, 2);

            $without = self::ALLOW_LISTED;
            unset($without[$key]);

            $hits = array_values(array_filter(
                $this->scan($this->projectRoot().'/app', $atRisk, $without),
                static fn (string $v): bool => str_starts_with($v, $file.':') && str_contains($v, '('.$fn.')')
            ));

            $this->assertNotSame(
                [],
                $hits,
                "ALLOW-LIST ENTRY IS DEAD WEIGHT: {$key}\n"
                    ."Removing it left the census green, so the scanner never reports that site and the\n"
                    ."entry suppresses nothing. Either the site was fixed (delete the entry) or the\n"
                    ."scanner cannot see it (fix the scanner). An allow-list of things the ratchet was\n"
                    .'never going to catch is worse than no allow-list: it reads like a reviewed decision.'
            );
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

            foreach ($this->predicatesOn($line) as [$column, $op, $bound]) {
                if (! $this->isAtRisk($column, $atRisk)) {
                    continue;
                }

                if (! $this->isRangeBound($bound, $lines, $fnStart, $i)) {
                    continue;
                }

                // Only the UPPER end of a range can suffer this defect: a bare `Y-m-d` LOWER bound
                // widens to 00:00:00, which is exactly what "from the 1st" means. So a
                // `whereBetween` is judged on its second argument alone. Judging the pair as one
                // string — CT-A12's form — let a normalised lower end vouch for an un-normalised
                // upper one, which is how `ReportController` line 961 survived losing its
                // `->endOfDay()` while line 960's `$startDate = ...->startOfDay()` sat above it.
                $upper = $op === 'between' ? $this->upperBoundOf($bound) : $bound;

                if ($this->isNormalised($upper, $lines, $fnStart, $i, $atRisk)) {
                    continue;
                }

                $out[] = $op === 'between'
                    ? sprintf('%s:%d (%s) whereBetween on `%s` with an un-normalised bound', $rel, $i + 1, $fn ?? '?', $column)
                    : sprintf('%s:%d (%s) where(`%s` %s) with an un-normalised bound', $rel, $i + 1, $fn ?? '?', $column, $op);
            }
        }

        return $out;
    }

    /**
     * The date predicates on one line, as `[columnExpression, operator, boundExpression]`.
     *
     * The column argument is accepted in BOTH spellings — a string literal, and a `DB::raw()`
     * expression. CT-A12 matched only the first, which is why the ~25 accounting predicates
     * written against `COALESCE(posting_date, transaction_date)` were never scanned (CT-A13 hole
     * 1). `->orWhere()` is deliberately not matched: no date RANGE bound in this codebase is
     * written that way, and matching it would need the same or-group analysis that would make this
     * scanner a parser.
     *
     * @return array<int, array{0:string,1:string,2:string}>
     */
    private function predicatesOn(string $line): array
    {
        $column = '(?:[\'"](?P<lit>[A-Za-z0-9_.]+)[\'"]|DB::raw\(\s*[\'"](?P<raw>[^\'"]+)[\'"]\s*\))';
        $out = [];

        if (preg_match('/whereBetween\(\s*'.$column.'\s*,\s*\[(?P<bound>[^\]]*)\]/', $line, $m) === 1) {
            $out[] = [$this->columnOf($m), 'between', $m['bound']];
        }

        // `<` and `>` as well as `<=`/`>=` — CT-A13 hole 4.
        if (preg_match('/->where\(\s*'.$column.'\s*,\s*[\'"](?P<op><=|>=|<|>)[\'"]\s*,\s*(?P<bound>.+)$/', $line, $m) === 1) {
            $out[] = [$this->columnOf($m), $m['op'], $this->untilCloseParen($m['bound'])];
        }

        return $out;
    }

    /** @param array<string, string> $m */
    private function columnOf(array $m): string
    {
        return ($m['lit'] ?? '') !== '' ? $m['lit'] : ($m['raw'] ?? '');
    }

    /**
     * Is any identifier in this column expression an at-risk column?
     *
     * For a bare column name that is the old question unchanged. For a raw expression it is the
     * RIGHT question: `COALESCE(DATE, DATETIME)` promotes to DATETIME in MySQL, so a coalesce
     * naming one datetime column is a datetime expression however many DATE columns sit beside it
     * — which is precisely why an engine row (`posting_date` set) survived a bare bound by luck
     * while a legacy row (`posting_date` NULL) fell through to the raw datetime and was lost.
     *
     * @param  string[]  $atRisk
     */
    private function isAtRisk(string $columnExpression, array $atRisk): bool
    {
        if (preg_match_all('/[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*/', $columnExpression, $m) === 0) {
            return false;
        }

        foreach ($m[0] as $token) {
            $base = str_contains($token, '.') ? substr($token, strrpos($token, '.') + 1) : $token;

            if (in_array($base, $atRisk, true)) {
                return true;
            }
        }

        return false;
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
     * Three kinds of evidence are accepted, in order: a marker in the bound expression itself, a
     * `Carbon`-typed parameter in the (whole) signature, and the NEAREST PRECEDING assignment to
     * one of the bound's variables — either normalised, or shown to hold an instant.
     *
     * @param  string[]  $lines
     * @param  string[]  $atRisk
     */
    private function isNormalised(string $bounds, array $lines = [], int $fnStart = 0, int $upTo = 0, array $atRisk = []): bool
    {
        if ($this->hasMarker($bounds, self::NORMALISED)) {
            return true;
        }

        if ($lines === [] || preg_match_all('/\$([A-Za-z_][A-Za-z0-9_]*)/', $bounds, $vars) === 0) {
            return false;
        }

        $signature = $this->signatureAt($lines, $fnStart);

        foreach ($vars[1] as $v) {
            // `Carbon`, `CarbonInterface` and `CarbonImmutable` all mean "the caller supplied an
            // instant". Matching only the bare `Carbon` missed ReconciliationService's own widened
            // signature and reported it as a violation — the scanner has to know the type family,
            // not one spelling of it.
            if (preg_match('/\??Carbon(?:Interface|Immutable)?\s+\$'.preg_quote($v, '/').'\b/', $signature) === 1) {
                return true;
            }
        }

        foreach ($vars[1] as $v) {
            // NEAREST, not any (CT-A13 hole 2). Scanning forward for the first normalising
            // assignment anywhere in the function let one branch's fix vouch for every branch
            // below it — which is exactly what CT-A12's own fix then did to the two branches
            // under it in ReportController::unpaidaccountsPayableReceivableReport().
            $assignment = $this->nearestAssignment($v, $lines, $fnStart, $upTo);

            if ($assignment === null) {
                continue;
            }

            if ($this->hasMarker($assignment, self::NORMALISED) || $this->carriesAnInstant($assignment, $atRisk)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does this assignment hold an INSTANT — a value that cannot be a bare `Y-m-d`?
     *
     * Two ways to show it: Carbon arithmetic, or reading a column the migrations declare as a
     * datetime (`$to = $latestTask->created_at`). `$request->x` is excluded from the second — a
     * request attribute is a string the user typed, whatever it happens to be named.
     *
     * A date-only rendering anywhere in the assignment defeats both, and is checked first.
     *
     * @param  string[]  $atRisk
     */
    private function carriesAnInstant(string $assignment, array $atRisk): bool
    {
        if ($this->hasMarker($assignment, self::DATE_ONLY_RENDERING)) {
            return false;
        }

        if ($this->hasMarker($assignment, self::INSTANT_EVIDENCE)) {
            return true;
        }

        if (str_contains($assignment, '$request->')) {
            return false;
        }

        foreach ($atRisk as $column) {
            if (preg_match('/->'.preg_quote($column, '/').'\b/', $assignment) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Case-insensitively, because PHP method names are.
     *
     * `ProfileController` line 111 spells it `->endOfmonth()` — correct at runtime, invisible to a
     * `str_contains`. CT-A12 hit this on `->datetime()` vs `->dateTime()` in the migration scan and
     * fixed it only there.
     *
     * @param  string[]  $markers
     */
    private function hasMarker(string $haystack, array $markers): bool
    {
        foreach ($markers as $marker) {
            if (stripos($haystack, $marker) !== false) {
                return true;
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
     * `$cutoff`, `$startTime`, `$today` — is a cutoff and is not scanned. Scoping this way took the
     * scan from 58 reported sites, nearly all of them correct moving cutoffs, down to the ones that
     * are genuinely this defect. A ratchet that cries wolf 58 times gets switched off, and then it
     * protects nothing.
     *
     * CT-A13 adds PROVENANCE to the name test, because naming turned out to be too weak a proxy.
     * `$asOf` reads like a moving cutoff and was excluded by name — but
     * `$asOf = $import->statement_to?->toDateString()` is a bare `Y-m-d` built from stored data,
     * which is this defect exactly. A variable whose nearest assignment came from `$request->`,
     * `->input(` or `->toDateString()` is a report bound whatever it is called.
     *
     * @param  string[]  $lines
     */
    private function isRangeBound(string $bounds, array $lines = [], int $fnStart = 0, int $upTo = 0): bool
    {
        if (preg_match('/[\'"]\d{4}-\d{1,2}-\d{1,2}[\'"]/', $bounds) === 1) {
            return true;
        }

        if (str_contains($bounds, '$request->') || str_contains($bounds, '->input(')) {
            return true;
        }

        if (preg_match('/\$(?:date)?(?:from|to|start|end)(?:date|_date)?\b/i', $bounds) === 1) {
            return true;
        }

        if ($lines === [] || preg_match_all('/\$([A-Za-z_][A-Za-z0-9_]*)/', $bounds, $vars) === 0) {
            return false;
        }

        foreach ($vars[1] as $v) {
            $assignment = $this->nearestAssignment($v, $lines, $fnStart, $upTo);

            if ($assignment === null) {
                continue;
            }

            if ($this->hasMarker($assignment, self::RANGE_PROVENANCE)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The nearest assignment to `$var` above line `$upTo`, or null.
     *
     * Comment lines are skipped: this file and several of the repaired sites quote the defective
     * assignment verbatim in a `// CT-A12:` note directly above the fixed one.
     *
     * @param  string[]  $lines
     */
    private function nearestAssignment(string $var, array $lines, int $fnStart, int $upTo): ?string
    {
        for ($j = $upTo - 1; $j >= $fnStart; $j--) {
            $line = $lines[$j] ?? '';
            $trimmed = ltrim($line);

            if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/*')) {
                continue;
            }

            if (preg_match('/\$'.preg_quote($var, '/').'\s*=[^=]/', $line) === 1) {
                return $line;
            }
        }

        return null;
    }

    /**
     * The whole declaration of the function starting at `$fnStart`, joined up to its `{`.
     *
     * CT-A13 hole 3: reading only `$lines[$fnStart]` loses every parameter of a signature broken
     * across lines, which is how a `?Carbon $dateTo` became an allow-list entry.
     *
     * @param  string[]  $lines
     */
    private function signatureAt(array $lines, int $fnStart): string
    {
        $signature = '';
        $stop = min($fnStart + 20, count($lines));

        for ($j = $fnStart; $j < $stop; $j++) {
            $signature .= ' '.$lines[$j];

            if (str_contains($lines[$j], '{') || str_contains($lines[$j], ';')) {
                break;
            }
        }

        return $signature;
    }

    /**
     * The LAST top-level argument of a `whereBetween` bound list — its upper end.
     *
     * Splitting on top-level commas, so that `[$from, Carbon::parse($to, 'UTC')]` is two parts and
     * not three. A single-part bound is returned unchanged, which is also the safe answer for any
     * shape this cannot decompose.
     */
    private function upperBoundOf(string $bounds): string
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $quote = null;

        for ($i = 0, $n = strlen($bounds); $i < $n; $i++) {
            $c = $bounds[$i];

            if ($quote !== null) {
                $current .= $c;

                if ($c === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($c === "'" || $c === '"') {
                $quote = $c;
                $current .= $c;

                continue;
            }

            if ($c === '(' || $c === '[') {
                $depth++;
            } elseif ($c === ')' || $c === ']') {
                $depth--;
            } elseif ($c === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $c;
        }

        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts === [] ? trim($bounds) : (string) end($parts);
    }

    /**
     * Everything up to the `)` that closes the `where(` call, respecting nesting and quotes.
     *
     * The old `([^)]+)\)` stopped at the first `)` of ANY kind, so `$to->endOfDay()` was captured
     * as `$to->endOfDay(` — it happened to still contain the marker, but a bound ending in any
     * other call would have been truncated mid-expression.
     */
    private function untilCloseParen(string $rest): string
    {
        $out = '';
        $depth = 0;
        $quote = null;

        for ($i = 0, $n = strlen($rest); $i < $n; $i++) {
            $c = $rest[$i];

            if ($quote !== null) {
                $out .= $c;

                if ($c === $quote && ($i === 0 || $rest[$i - 1] !== '\\')) {
                    $quote = null;
                }

                continue;
            }

            if ($c === "'" || $c === '"') {
                $quote = $c;
                $out .= $c;

                continue;
            }

            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                if ($depth === 0) {
                    break;
                }

                $depth--;
            }

            $out .= $c;
        }

        return trim($out);
    }
}
