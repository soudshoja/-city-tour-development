<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Onboarding\Parity\LedgerFigures;
use App\Services\ProfitLossService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsParityFixtures;
use Tests\TestCase;

/**
 * LP5a (.planning/phases/legacy-ledger-pilot/LP5A-PL-SCREEN-2026-09-07.md): the P&L REPORT SCREEN
 * must produce the same net income as the legacy-ledger PARITY HARNESS over the same journals.
 *
 * The bar this file sets is deliberately not "the service returns a number I hard-coded". Every
 * assertion compares {@see ProfitLossService} against
 * {@see LedgerFigures::profitAndLoss()} — the class the parity report's `pl` check actually calls —
 * computed over the SAME synthetic ledger in the same test. A definition that drifts on either side
 * fails here, which is the only property that keeps the screen and the harness reconcilable.
 *
 * The fixture carries one of each thing that used to (or could) break the equality:
 *
 *   - an income document and an expense document, so the SIGN is exercised in both directions;
 *   - an opening journal (`sub_type = LEGACY_OJV`) that posts to a P&L account — the exclusion is
 *     mutation-proved below, not merely present;
 *   - a soft-deleted journal line;
 *   - a line whose `posting_date` is shifted forward out of the period, and one shifted forward
 *     INTO it, so the `COALESCE(posting_date, transaction_date)` basis is load-bearing in both
 *     directions;
 *   - account CODES chosen to be hostile to the bug this fix removes: the income leaves start with
 *     `4` and the expense leaf starts with `3`, the exact opposite of the old
 *     `code LIKE '4%' => income` / `code LIKE '5%' => expense` rule. A regression to prefix
 *     bucketing cannot pass this file.
 *   - P&L leaves at DIFFERENT DEPTHS (an expense leaf at level 3, income leaves at levels 3 and 5),
 *     so the old `level = 3` selection rule cannot pass either.
 */
class ProfitLossServiceParityTest extends TestCase
{
    use BuildsParityFixtures;
    use RefreshDatabase;

    private const PERIOD_START = '2025-01-01';

    private const PERIOD_END = '2025-12-31';

    /** @var array<string, int> */
    private array $accounts = [];

    private int $companyId;

    /**
     * Assets / Income / Expenses with the leaf shapes described in the class docblock.
     */
    private function seedChart(): void
    {
        $this->companyId = $this->makeParityCompany();

        $assetsRoot = $this->insertAccount($this->companyId, null, null, '1000', 'Assets', 1, true);
        $incomeRoot = $this->insertAccount($this->companyId, null, null, '3000', 'Income', 1, true);
        $expensesRoot = $this->insertAccount($this->companyId, null, null, '4000', 'Expenses', 1, true);

        // Balance-sheet side: every synthetic document needs a real contra leg, so the fixture
        // ledger balances exactly the way a posted document does.
        $this->accounts['BANK'] = $this->insertAccount($this->companyId, $assetsRoot, $assetsRoot, '1010', 'BANK ACCOUNT', 2, false);

        // Income section (level 2) -> leaf at level 3, and a second section -> ... -> leaf at
        // level 5. Codes start with '4' on purpose (see class docblock).
        $incomeSection = $this->insertAccount($this->companyId, $incomeRoot, $incomeRoot, '4100', 'INCOME ON SALES', 2, true);
        $this->accounts['TICKET_SALES'] = $this->insertAccount($this->companyId, $incomeSection, $incomeRoot, '4101', 'TICKET SALES', 3, false);

        $otherIncome = $this->insertAccount($this->companyId, $incomeRoot, $incomeRoot, '4200', 'OTHER MIS:INCOME', 2, true);
        $otherIncomeL3 = $this->insertAccount($this->companyId, $otherIncome, $incomeRoot, '4210', 'MISC GROUP', 3, true);
        $otherIncomeL4 = $this->insertAccount($this->companyId, $otherIncomeL3, $incomeRoot, '4211', 'MISC SUBGROUP', 4, true);
        $this->accounts['MISC_INCOME'] = $this->insertAccount($this->companyId, $otherIncomeL4, $incomeRoot, '4212', 'MISC INCOME LEAF', 5, false);

        // Expense section (level 2) -> leaf at level 3. Code starts with '3' on purpose.
        $expenseSection = $this->insertAccount($this->companyId, $expensesRoot, $expensesRoot, '3100', 'RENT', 2, true);
        $this->accounts['RENT'] = $this->insertAccount($this->companyId, $expenseSection, $expensesRoot, '3101', 'OFFICE RENT', 3, false);
    }

    /**
     * The documents. Returns the opening journal's transaction id so a mutation test can reach it.
     */
    private function seedLedger(): int
    {
        // Ordinary trading: 1,000 of ticket sales, banked.
        $this->postSyntheticDocument($this->companyId, 'INV', 'LEGACY_INV', '2025-06-15', [
            [$this->accounts['BANK'], 1000.0, 0.0, null],
            [$this->accounts['TICKET_SALES'], 0.0, 1000.0, null],
        ]);

        // A deep (level-5) income leaf, so depth cannot be a selection criterion.
        $this->postSyntheticDocument($this->companyId, 'INV', 'LEGACY_INV', '2025-07-01', [
            [$this->accounts['BANK'], 250.0, 0.0, null],
            [$this->accounts['MISC_INCOME'], 0.0, 250.0, null],
        ]);

        // Expense: 300 of rent paid.
        $this->postSyntheticDocument($this->companyId, 'PV', 'LEGACY_PV', '2025-07-10', [
            [$this->accounts['RENT'], 300.0, 0.0, null],
            [$this->accounts['BANK'], 0.0, 300.0, null],
        ]);

        // A 2024-dated document whose posting_date was SHIFTED INTO 2025: belongs to 2025.
        $this->postSyntheticDocument($this->companyId, 'INV', 'LEGACY_INV', '2024-12-20', [
            [$this->accounts['BANK'], 40.0, 0.0, null],
            [$this->accounts['TICKET_SALES'], 0.0, 40.0, null],
        ], postingDate: '2025-01-05');

        // A 2025-dated document whose posting_date was SHIFTED OUT to 2026: belongs to 2026.
        $this->postSyntheticDocument($this->companyId, 'INV', 'LEGACY_INV', '2025-12-28', [
            [$this->accounts['BANK'], 70.0, 0.0, null],
            [$this->accounts['TICKET_SALES'], 0.0, 70.0, null],
        ], postingDate: '2026-01-04');

        // The opening journal, dated INSIDE the period (LedgerFigures' "OJV date trap"). It posts
        // 700 of credit to an INCOME leaf, so if either side stopped excluding it the divergence
        // would be 700, not a rounding wobble.
        $openingId = $this->postSyntheticDocument($this->companyId, 'OJV', 'LEGACY_OJV', '2025-01-01', [
            [$this->accounts['BANK'], 700.0, 0.0, null],
            [$this->accounts['TICKET_SALES'], 0.0, 700.0, null],
        ]);

        // A soft-deleted line on an income leaf: reversed/voided, must count for nothing.
        $this->postSyntheticDocument($this->companyId, 'INV', 'LEGACY_INV', '2025-05-05', [
            [$this->accounts['BANK'], 999.0, 0.0, null],
            [$this->accounts['TICKET_SALES'], 0.0, 999.0, null],
        ]);
        DB::table('journal_entries')
            ->where('company_id', $this->companyId)
            ->where('account_id', $this->accounts['TICKET_SALES'])
            ->where('credit', 999.0)
            ->update(['deleted_at' => now()]);

        return $openingId;
    }

    private function screenNetIncome(): float
    {
        return app(ProfitLossService::class)
            ->generate($this->companyId, Carbon::parse(self::PERIOD_START), Carbon::parse(self::PERIOD_END))['totals']['net_income'];
    }

    private function parityNetIncome(): float
    {
        $movement = (new LedgerFigures)->profitAndLoss(
            $this->companyId,
            CarbonImmutable::parse(self::PERIOD_START),
            CarbonImmutable::parse(self::PERIOD_END),
            'LEGACY_OJV'
        );

        // The harness sums net_income over the accounts its anchor names -- the P&L accounts. Here
        // the whole synthetic chart is known, so the equivalent sum is taken over the same P&L
        // leaves the screen reports on, which is exactly what makes the two figures comparable.
        $plAccountIds = [$this->accounts['TICKET_SALES'], $this->accounts['MISC_INCOME'], $this->accounts['RENT']];

        $net = 0.0;

        foreach ($plAccountIds as $accountId) {
            $net += $movement[$accountId]['net_income'] ?? 0.0;
        }

        return $net;
    }

    public function test_screen_net_income_equals_the_parity_harness_figure(): void
    {
        $this->seedChart();
        $this->seedLedger();

        $expected = $this->parityNetIncome();

        // Income 1000 + 250 + 40 (shifted in) = 1290; expense 300. The OJV's 700, the soft-deleted
        // 999 and the shifted-out 70 all count for nothing on BOTH sides.
        $this->assertEqualsWithDelta(990.0, $expected, 0.0005, 'The parity harness itself must see 990 over this fixture.');
        $this->assertEqualsWithDelta($expected, $this->screenNetIncome(), 0.0005);
    }

    /**
     * The sign, stated as an accounting identity rather than a magic number: income comes back
     * credit-positive, expense debit-positive, and net = income - expense. A sign flip anywhere
     * moves one of these three assertions.
     */
    public function test_income_and_expense_are_both_positive_and_net_is_their_difference(): void
    {
        $this->seedChart();
        $this->seedLedger();

        $totals = app(ProfitLossService::class)
            ->generate($this->companyId, Carbon::parse(self::PERIOD_START), Carbon::parse(self::PERIOD_END))['totals'];

        $this->assertEqualsWithDelta(1290.0, $totals['income'], 0.0005);
        $this->assertEqualsWithDelta(300.0, $totals['expense'], 0.0005);
        $this->assertEqualsWithDelta(990.0, $totals['net_income'], 0.0005);
        $this->assertEqualsWithDelta($totals['income'] - $totals['expense'], $totals['net_income'], 0.0005);
    }

    /**
     * A loss-making period must render NEGATIVE, not a positive number with the sign eaten by an
     * `abs()`. This is the direct regression guard for the old view, which rendered
     * `abs($totalExpense)` and summed the wrong column into the income side.
     */
    public function test_a_loss_making_period_produces_a_negative_net_income(): void
    {
        $this->seedChart();

        $this->postSyntheticDocument($this->companyId, 'PV', 'LEGACY_PV', '2025-03-01', [
            [$this->accounts['RENT'], 800.0, 0.0, null],
            [$this->accounts['BANK'], 0.0, 800.0, null],
        ]);
        $this->postSyntheticDocument($this->companyId, 'INV', 'LEGACY_INV', '2025-03-02', [
            [$this->accounts['BANK'], 200.0, 0.0, null],
            [$this->accounts['TICKET_SALES'], 0.0, 200.0, null],
        ]);

        $totals = app(ProfitLossService::class)
            ->generate($this->companyId, Carbon::parse(self::PERIOD_START), Carbon::parse(self::PERIOD_END))['totals'];

        $this->assertEqualsWithDelta(-600.0, $totals['net_income'], 0.0005);
        $this->assertEqualsWithDelta($this->parityNetIncome(), $totals['net_income'], 0.0005);
    }

    /**
     * MUTATION PROOF for the opening-journal exclusion. Re-stamping the SAME document's sub_type to
     * an ordinary one — nothing else about the fixture changes — must move the reported figure by
     * exactly the 700 that document posts to an income leaf. If the figure does not move, the
     * exclusion is decorative and this test says so.
     */
    public function test_the_opening_journal_exclusion_is_load_bearing(): void
    {
        $this->seedChart();
        $openingId = $this->seedLedger();

        $withExclusion = $this->screenNetIncome();
        $this->assertEqualsWithDelta(990.0, $withExclusion, 0.0005);

        DB::table('transactions')->where('id', $openingId)->update([
            'doc_type' => 'INV',
            'sub_type' => 'LEGACY_INV',
        ]);

        $this->assertEqualsWithDelta(1690.0, $this->screenNetIncome(), 0.0005, 'Re-stamping the opening journal as an ordinary document must add its 700 back.');
        $this->assertEqualsWithDelta($this->parityNetIncome(), $this->screenNetIncome(), 0.0005, 'The harness must move by the same 700 — both sides share one definition.');
    }

    /**
     * MUTATION PROOF for the year-end-close exclusion, which travels with the opening-journal one:
     * a YEC document's own zeroing lines must not net a closed year's trading activity against
     * itself.
     */
    public function test_year_end_close_documents_are_excluded_from_period_movement(): void
    {
        $this->seedChart();
        $this->seedLedger();

        $yecId = $this->postSyntheticDocument($this->companyId, 'YEC', 'LEGACY_YEC', '2025-12-31', [
            [$this->accounts['TICKET_SALES'], 1290.0, 0.0, null],
            [$this->accounts['BANK'], 0.0, 1290.0, null],
        ]);

        $this->assertEqualsWithDelta(990.0, $this->screenNetIncome(), 0.0005, 'A YEC must leave the closing year\'s own P&L untouched.');

        DB::table('transactions')->where('id', $yecId)->update(['doc_type' => 'JV']);

        $this->assertEqualsWithDelta(-300.0, $this->screenNetIncome(), 0.0005, 'Re-stamped as an ordinary JV, the same lines DO reduce income — proving the exclusion did the work.');
    }

    /**
     * MUTATION PROOF for the soft-delete predicate: un-deleting the voided 999 line must move the
     * figure by exactly 999.
     */
    public function test_soft_deleted_lines_are_excluded(): void
    {
        $this->seedChart();
        $this->seedLedger();

        $this->assertEqualsWithDelta(990.0, $this->screenNetIncome(), 0.0005);

        DB::table('journal_entries')
            ->where('company_id', $this->companyId)
            ->where('credit', 999.0)
            ->update(['deleted_at' => null]);

        $this->assertEqualsWithDelta(1989.0, $this->screenNetIncome(), 0.0005);
    }

    /**
     * The date basis, both directions: a line shifted INTO the period counts; a line shifted OUT of
     * it does not. Asserted through the monthly series so the chart's own bucketing is covered too
     * — the shifted-in line is dated 2024-12-20 but must appear in JANUARY 2025.
     */
    public function test_posting_date_decides_the_period_and_the_month_bucket(): void
    {
        $this->seedChart();
        $this->seedLedger();

        $monthly = app(ProfitLossService::class)->monthlyNetIncome($this->companyId, 2025);

        $this->assertEqualsWithDelta(40.0, $monthly[1], 0.0005, 'The 2024-dated line posted on 2025-01-05 belongs to January 2025.');
        $this->assertEqualsWithDelta(1000.0, $monthly[6], 0.0005);
        $this->assertEqualsWithDelta(-50.0, $monthly[7], 0.0005, '250 of income less 300 of rent.');
        $this->assertEqualsWithDelta(0.0, $monthly[12], 0.0005, 'The 2025-12-28 line posted on 2026-01-04 belongs to 2026.');
        $this->assertEqualsWithDelta(990.0, array_sum($monthly), 0.0005, 'The twelve buckets must reconcile to the year figure.');
    }

    /**
     * The population rule, stated against the two things the old implementation got wrong: an
     * income leaf whose code starts with `4` still lands in INCOME, and a P&L leaf at level 5 is
     * still reported. Both are asserted through the rendered sections, not just the totals.
     */
    public function test_accounts_are_classified_by_positional_root_not_by_code_prefix_or_level(): void
    {
        $this->seedChart();
        $this->seedLedger();

        $report = app(ProfitLossService::class)
            ->generate($this->companyId, Carbon::parse(self::PERIOD_START), Carbon::parse(self::PERIOD_END));

        $incomeSectionCodes = array_map(fn ($section) => $section['account']->code, $report['income']);
        $expenseSectionCodes = array_map(fn ($section) => $section['account']->code, $report['expenses']);

        // '4100'/'4200' are INCOME sections despite the leading 4; '3100' is an EXPENSE section
        // despite the leading 3.
        $this->assertSame(['4100', '4200'], $incomeSectionCodes);
        $this->assertSame(['3100'], $expenseSectionCodes);

        // The level-5 leaf is reported, under its level-2 section.
        $misc = collect($report['income'])->firstWhere(fn ($section) => $section['account']->code === '4200');
        $this->assertNotNull($misc);
        $this->assertEqualsWithDelta(250.0, $misc['amount'], 0.0005);
        $this->assertSame(['4212'], array_map(fn ($child) => $child['account']->code, $misc['children']));
    }

    /**
     * Balance-sheet accounts never reach the P&L, whatever their code looks like. The BANK leaf
     * carries the largest movement in the fixture, so a population rule that leaked would be
     * impossible to miss.
     */
    public function test_balance_sheet_accounts_are_never_reported(): void
    {
        $this->seedChart();
        $this->seedLedger();

        $report = app(ProfitLossService::class)
            ->generate($this->companyId, Carbon::parse(self::PERIOD_START), Carbon::parse(self::PERIOD_END));

        $reportedCodes = [];

        foreach ([...$report['income'], ...$report['expenses']] as $section) {
            $reportedCodes[] = $section['account']->code;

            foreach ($section['children'] as $child) {
                $reportedCodes[] = $child['account']->code;
            }
        }

        $this->assertNotContains('1010', $reportedCodes);
    }
}
