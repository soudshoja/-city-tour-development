<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\YearEndCloseService;
use App\Services\TrialBalanceService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * Conformance-audit fix: {@see YearEndCloseService} posts one idempotent `doc_type='YEC'` document
 * per (company, year), dated at fiscal year end, that sweeps every Income/Expense leaf to
 * RETAINED_EARNINGS. {@see TrialBalanceService} used to filter purely by
 * COALESCE(posting_date, transaction_date) with NO doc_type exclusion anywhere — so a trial
 * balance / P&L movement run FOR an already-closed year included the YEC's own zeroing lines and
 * the year's real income/expense activity netted to ~zero against itself, in its own report.
 *
 * Blueprint analog: SubType <> 'OJV' — "opening journal is opening balance, never movement."
 *
 * Correct semantics under test here:
 *   1. Period-movement (TrialBalanceService::generate()'s date-ranged query) EXCLUDES YEC lines —
 *      a closed year's P&L movement still shows the real trading activity.
 *   2. Opening-balance computation (getOpeningBalances(), sum of all history before the range
 *      start) INCLUDES YEC lines — next year opens with Income/Expense leaves at zero and
 *      Retained Earnings carrying the swept net forward. This must NOT regress.
 *   3. YearEndCloseService's own idempotency (second run = no-op) is unaffected — it never reads
 *      through TrialBalanceService's movement path.
 */
class YearEndCloseReportExclusionTest extends AccountingTestCase
{
    private function service(): YearEndCloseService
    {
        return app(YearEndCloseService::class);
    }

    private function trialBalance(): TrialBalanceService
    {
        return app(TrialBalanceService::class);
    }

    private function resolver(): AccountResolver
    {
        return app(AccountResolver::class);
    }

    /** @return array{0: Company, 1: Branch} */
    private function makeEngineOnCompany(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);

        config(['accounting.engine.enabled' => true]);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch];
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function lockAllMonths(Company $company, int $year): void
    {
        for ($m = 1; $m <= 12; $m++) {
            AccountingPeriod::create(['company_id' => $company->id, 'year' => $year, 'month' => $m, 'status' => AccountingPeriod::STATUS_LOCKED]);
        }
    }

    private function makeLine(Transaction $txn, Company $company, Branch $branch, Account $account, float $debit, float $credit, Carbon $date): JournalEntry
    {
        return JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $account->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Test line', 'debit' => $debit, 'credit' => $credit, 'name' => $account->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => max($debit, $credit),
            'voucher_number' => 'TST', 'type_reference_id' => $company->id,
        ]);
    }

    /**
     * Same fixture shape as YearEndCloseServiceTest::postPlAndAr() — one balanced document: Dr AR /
     * Cr Income for the sale, Dr Expense / Cr AR for the cost. Real trading activity for the year,
     * independent of anything the year-end close will later post.
     */
    private function postPlAndAr(Company $company, Branch $branch, int $year, float $income, float $expense): Account
    {
        $incomeAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '4133')->firstOrFail();
        $expenseAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5222')->firstOrFail();
        $ar = $this->resolver()->resolve('RECEIVABLE_CONTROL', $company->id);
        $date = Carbon::create($year, 6, 15);

        $total = $income + $expense;
        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => $total, 'description' => 'PL fixture', 'reference_type' => 'Invoice',
            'reference_number' => 'PL-'.uniqid(), 'name' => 'PL fixture', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'JV', 'doc_year' => $year, 'posting_status' => 'posted',
            'total_debit' => $total, 'total_credit' => $total, 'idempotency_key' => uniqid('pl:'),
        ]);

        $this->makeLine($txn, $company, $branch, $ar, $income, 0, $date);
        $this->makeLine($txn, $company, $branch, $incomeAccount, 0, $income, $date);
        $this->makeLine($txn, $company, $branch, $expenseAccount, $expense, 0, $date);
        $this->makeLine($txn, $company, $branch, $ar, 0, $expense, $date);

        return $incomeAccount;
    }

    private function yearBounds(int $year): array
    {
        return [Carbon::create($year, 1, 1), Carbon::create($year, 12, 31)];
    }

    /**
     * (1) Baseline: before close, the trial balance for the year shows the real activity.
     * (2) After year-end close, re-running TB/P&L movement for the SAME year shows IDENTICAL
     *     figures — not zeroed. This is the defect under test: pre-fix, the YEC's own zeroing
     *     lines (dated inside the same fiscal year) land inside this exact date range and net
     *     the leaves to ~zero.
     */
    public function test_year_end_close_does_not_zero_out_the_years_own_trial_balance(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);
        $incomeAccount = $this->postPlAndAr($company, $branch, 2026, income: 500, expense: 200);
        $expenseAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5222')->firstOrFail();

        [$yearStart, $yearEnd] = $this->yearBounds(2026);

        $before = $this->trialBalance()->generate($company->id, $yearStart, $yearEnd, ['show_zero' => true]);
        $incomeRowBefore = collect($before['accounts'])->firstWhere('id', $incomeAccount->id);
        $expenseRowBefore = collect($before['accounts'])->firstWhere('id', $expenseAccount->id);
        $this->assertEqualsWithDelta(500.0, (float) $incomeRowBefore->total_credit, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $incomeRowBefore->total_debit, 0.001);
        $this->assertEqualsWithDelta(200.0, (float) $expenseRowBefore->total_debit, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $expenseRowBefore->total_credit, 0.001);
        // closing_balance is generate()'s net-movement figure (opening + period movement, signed to
        // the account's own normal side) -- this is the number the defect actually zeroes out: the
        // YEC's own debit/credit lines add to BOTH total_debit and total_credit for the leaf they
        // zero, so a total_credit-only (or total_debit-only) check would miss the regression
        // entirely -- closing_balance is where the net effect surfaces.
        $this->assertEqualsWithDelta(500.0, (float) $incomeRowBefore->closing_balance, 0.001);
        $this->assertEqualsWithDelta(200.0, (float) $expenseRowBefore->closing_balance, 0.001);

        $closeResult = $this->service()->run($company->id, 2026, null);
        $this->assertTrue($closeResult['success']);
        $this->assertNotNull($closeResult['transaction']);
        $this->assertSame('YEC', $closeResult['transaction']->doc_type);

        $after = $this->trialBalance()->generate($company->id, $yearStart, $yearEnd, ['show_zero' => true]);
        $incomeRowAfter = collect($after['accounts'])->firstWhere('id', $incomeAccount->id);
        $expenseRowAfter = collect($after['accounts'])->firstWhere('id', $expenseAccount->id);

        // The point of this fix: identical movement figures for the year, not zeroed by the YEC's
        // own lines landing inside the same date range. Pre-fix, the YEC's zeroing lines (dated at
        // fiscal year end, inside this exact range) add to the OTHER side of each leaf
        // (total_debit for Income, total_credit for Expense), driving closing_balance to ~0 even
        // though total_credit/total_debit on the leaf's OWN normal side looks unchanged -- so every
        // assertion below is load-bearing, not redundant.
        $this->assertEqualsWithDelta(
            (float) $incomeRowBefore->total_credit,
            (float) $incomeRowAfter->total_credit,
            0.001,
            'Income leaf movement for the closed year must be unchanged by year-end close, not swept to zero in its own report.'
        );
        $this->assertEqualsWithDelta(
            (float) $incomeRowBefore->total_debit,
            (float) $incomeRowAfter->total_debit,
            0.001,
            'Income leaf must not pick up the YEC zeroing debit inside the same-year movement query.'
        );
        $this->assertEqualsWithDelta(
            (float) $expenseRowBefore->total_debit,
            (float) $expenseRowAfter->total_debit,
            0.001,
            'Expense leaf movement for the closed year must be unchanged by year-end close, not swept to zero in its own report.'
        );
        $this->assertEqualsWithDelta(
            (float) $expenseRowBefore->total_credit,
            (float) $expenseRowAfter->total_credit,
            0.001,
            'Expense leaf must not pick up the YEC zeroing credit inside the same-year movement query.'
        );
        $this->assertEqualsWithDelta(500.0, (float) $incomeRowAfter->total_credit, 0.001);
        $this->assertEqualsWithDelta(200.0, (float) $expenseRowAfter->total_debit, 0.001);
        $this->assertEqualsWithDelta(
            500.0,
            (float) $incomeRowAfter->closing_balance,
            0.001,
            'Income leaf net movement for the closed year must remain 500, not be swept to zero by the YEC lines landing in the same date range.'
        );
        $this->assertEqualsWithDelta(
            200.0,
            (float) $expenseRowAfter->closing_balance,
            0.001,
            'Expense leaf net movement for the closed year must remain 200, not be swept to zero by the YEC lines landing in the same date range.'
        );
    }

    /**
     * (3) Opening balances for Y+1 must be UNCHANGED by this fix: Income/Expense leaves open at
     * zero (the YEC's zeroing lines are counted, correctly, in the pre-Y+1 history sum) and
     * Retained Earnings carries the swept net forward.
     */
    public function test_opening_balances_for_next_year_still_carry_the_yec_sweep(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);
        $incomeAccount = $this->postPlAndAr($company, $branch, 2026, income: 500, expense: 200);
        $expenseAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5222')->firstOrFail();
        $retainedEarnings = $this->resolver()->resolve('RETAINED_EARNINGS', $company->id);

        $closeResult = $this->service()->run($company->id, 2026, null);
        $this->assertTrue($closeResult['success']);

        $openingForNextYear = $this->trialBalance()->getOpeningBalances($company->id, Carbon::create(2027, 1, 1));

        // Income leaf: full-history credit (500 real + 500 YEC debit-close) must leave it at a
        // net-zero opening position for 2027 — its normal (credit) side nets to zero.
        $incomeOpening = $openingForNextYear[$incomeAccount->id] ?? ['opening_debit' => 0.0, 'opening_credit' => 0.0];
        $this->assertEqualsWithDelta(
            0.0,
            $incomeOpening['opening_credit'] - $incomeOpening['opening_debit'],
            0.001,
            'Income leaf must open 2027 at zero net balance — the YEC sweep must still be counted in the opening-balance sum.'
        );

        $expenseOpening = $openingForNextYear[$expenseAccount->id] ?? ['opening_debit' => 0.0, 'opening_credit' => 0.0];
        $this->assertEqualsWithDelta(
            0.0,
            $expenseOpening['opening_debit'] - $expenseOpening['opening_credit'],
            0.001,
            'Expense leaf must open 2027 at zero net balance — the YEC sweep must still be counted in the opening-balance sum.'
        );

        // Retained Earnings carries the swept net profit (500 - 200 = 300) forward as an opening
        // credit balance.
        $reOpening = $openingForNextYear[$retainedEarnings->id] ?? ['opening_debit' => 0.0, 'opening_credit' => 0.0];
        $this->assertEqualsWithDelta(
            300.0,
            $reOpening['opening_credit'] - $reOpening['opening_debit'],
            0.001,
            'Retained Earnings must still carry the YEC-swept net profit forward into 2027 opening balances.'
        );
    }

    /**
     * (4) Year-end close idempotency is unaffected by this fix: a second run for the same year
     * still posts nothing and reports already_closed — YearEndCloseService never reads through
     * TrialBalanceService's movement path (buildClosingLines() queries journal_entries directly),
     * so the exclusion added there cannot change this behavior, but this pins it explicitly.
     */
    public function test_year_end_close_idempotency_is_unaffected(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);
        $this->postPlAndAr($company, $branch, 2026, income: 400, expense: 100);

        $first = $this->service()->run($company->id, 2026, null);
        $this->assertTrue($first['success']);
        $this->assertFalse($first['already_closed']);
        $firstTxnId = $first['transaction']->id;

        $second = $this->service()->run($company->id, 2026, null);
        $this->assertTrue($second['success']);
        $this->assertTrue($second['already_closed']);
        $this->assertSame($firstTxnId, $second['transaction']->id);

        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('doc_type', 'YEC')->count(),
            'Second close run must not post a second YEC document.'
        );
    }

    /**
     * Adversarial multi-year scenario (verification audit, not part of the builder's original
     * fixture set): activity + close in Y1 (2026), further activity in Y2 (2027) that is NEVER
     * closed. Pins four separate claims about the fix's semantics that the single-year test above
     * cannot distinguish:
     *
     *   (a) Y1 movement, queried AFTER the Y1 close, still shows Y1's real activity un-zeroed
     *       (same claim as the single-year test, repeated here so it composes with Y2 activity
     *       existing in the same ledger).
     *   (b) Y2 movement (Y2 has no YEC at all) shows ONLY Y2's own activity — Y1's activity, and
     *       critically Y1's YEC lines (dated 2026-12-31, outside the Y2 range), must not leak in.
     *   (c) Y2 opening balances: Income/Expense leaves open 2027 at zero (Y1's YEC swept them),
     *       Retained Earnings opens 2027 carrying Y1's net profit (500 - 200 = 300).
     *   (d) A range SPANNING both years (2026-01-01..2027-12-31): the YEC exclusion applies
     *       uniformly to movement regardless of range width, so this must show the SUM of both
     *       years' real activity (Y1 500+200, Y2 800+300 = 1300/500) with the 2026 YEC's own
     *       zeroing lines still excluded — not just the Y1-only or Y2-only figure, and not
     *       zeroed out by the 2026 YEC landing inside this wider range. This pins the intended
     *       cross-year behavior: "YEC excluded from movement, no matter how wide the movement
     *       range; openings (unaffected by this fix) still include all prior YECs before the
     *       range start."
     */
    public function test_multi_year_movement_and_opening_semantics_around_a_yec_boundary(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);
        $incomeAccount = $this->postPlAndAr($company, $branch, 2026, income: 500, expense: 200);
        $expenseAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5222')->firstOrFail();
        $retainedEarnings = $this->resolver()->resolve('RETAINED_EARNINGS', $company->id);

        $closeResult = $this->service()->run($company->id, 2026, null);
        $this->assertTrue($closeResult['success']);
        $this->assertNotNull($closeResult['transaction']);

        // Y2 activity — deliberately NEVER closed, so no YEC document exists for 2027 at all.
        $this->postPlAndAr($company, $branch, 2027, income: 800, expense: 300);

        [$y1Start, $y1End] = $this->yearBounds(2026);
        [$y2Start, $y2End] = $this->yearBounds(2027);

        // (a) Y1 movement, queried after both the Y1 close AND the Y2 activity exist in the ledger,
        // still shows Y1's real trading activity un-zeroed.
        $y1 = $this->trialBalance()->generate($company->id, $y1Start, $y1End, ['show_zero' => true]);
        $y1Income = collect($y1['accounts'])->firstWhere('id', $incomeAccount->id);
        $y1Expense = collect($y1['accounts'])->firstWhere('id', $expenseAccount->id);
        $this->assertEqualsWithDelta(500.0, (float) $y1Income->total_credit, 0.001, 'Y1 income movement must remain 500, not swept to zero by its own YEC.');
        $this->assertEqualsWithDelta(0.0, (float) $y1Income->total_debit, 0.001, 'Y1 income must not pick up its own YEC zeroing debit.');
        $this->assertEqualsWithDelta(200.0, (float) $y1Expense->total_debit, 0.001, 'Y1 expense movement must remain 200, not swept to zero by its own YEC.');
        $this->assertEqualsWithDelta(0.0, (float) $y1Expense->total_credit, 0.001, 'Y1 expense must not pick up its own YEC zeroing credit.');

        // Deliberate, pinned corollary of the whole-document exclusion (see TrialBalanceService's
        // docblock): Retained Earnings' OWN balancing line is part of the same YEC document, so it is
        // ALSO excluded from this same-year movement report — the closing year's own report is a
        // "pre-closing" trial balance for the whole document, not just its Income/Expense zeroing
        // side. Adversarial verification tried scoping the exclusion to Income/Expenses roots only so
        // RE would show its true swept movement here, but that broke the ledger-wide is_balanced
        // invariant (excluding only part of a balanced document unbalances the aggregate by exactly
        // the swept net profit) — rejected. The correct way to see RE's post-close balance is
        // getOpeningBalances() for a date after the YEC (proven below and in the previous test), not
        // this method's movement for a range ending on the YEC's own date.
        $y1RetainedEarnings = collect($y1['accounts'])->firstWhere('id', $retainedEarnings->id);
        $this->assertEqualsWithDelta(0.0, (float) ($y1RetainedEarnings->total_credit ?? 0.0), 0.001, 'Retained Earnings shows pre-closing (zero) movement in the closing years own report — its swept balance only appears via next years opening balance.');
        $this->assertEqualsWithDelta(0.0, (float) ($y1RetainedEarnings->total_debit ?? 0.0), 0.001, 'Retained Earnings pre-closing movement debit side must also be zero.');

        // (b) Y2 movement shows ONLY Y2's own activity — no bleed from Y1's activity or Y1's YEC
        // lines (both dated outside the Y2 range; this also incidentally proves the YEC exclusion
        // is not masking a date-range bug).
        $y2 = $this->trialBalance()->generate($company->id, $y2Start, $y2End, ['show_zero' => true]);
        $y2Income = collect($y2['accounts'])->firstWhere('id', $incomeAccount->id);
        $y2Expense = collect($y2['accounts'])->firstWhere('id', $expenseAccount->id);
        $this->assertEqualsWithDelta(800.0, (float) $y2Income->total_credit, 0.001, 'Y2 income movement must be exactly Y2 activity, no Y1 bleed-through.');
        $this->assertEqualsWithDelta(0.0, (float) $y2Income->total_debit, 0.001, 'Y2 income must show zero debit movement — no YEC exists for 2027, and Y1 YEC lines are out of range.');
        $this->assertEqualsWithDelta(300.0, (float) $y2Expense->total_debit, 0.001, 'Y2 expense movement must be exactly Y2 activity, no Y1 bleed-through.');
        $this->assertEqualsWithDelta(0.0, (float) $y2Expense->total_credit, 0.001, 'Y2 expense must show zero credit movement.');

        // (c) Y2 (2027) opening balances: Income/Expense leaves open at zero net (Y1's YEC swept
        // them, and that sweep must still be counted here since getOpeningBalances() applies no YEC
        // exclusion), Retained Earnings opens carrying Y1's net profit forward.
        $y2Opening = $this->trialBalance()->getOpeningBalances($company->id, Carbon::create(2027, 1, 1));
        $incomeOpening = $y2Opening[$incomeAccount->id] ?? ['opening_debit' => 0.0, 'opening_credit' => 0.0];
        $expenseOpening = $y2Opening[$expenseAccount->id] ?? ['opening_debit' => 0.0, 'opening_credit' => 0.0];
        $reOpening = $y2Opening[$retainedEarnings->id] ?? ['opening_debit' => 0.0, 'opening_credit' => 0.0];
        $this->assertEqualsWithDelta(0.0, $incomeOpening['opening_credit'] - $incomeOpening['opening_debit'], 0.001, 'Income must open 2027 at net zero.');
        $this->assertEqualsWithDelta(0.0, $expenseOpening['opening_debit'] - $expenseOpening['opening_credit'], 0.001, 'Expense must open 2027 at net zero.');
        $this->assertEqualsWithDelta(300.0, $reOpening['opening_credit'] - $reOpening['opening_debit'], 0.001, 'Retained Earnings must open 2027 carrying the 300 net profit swept from 2026.');

        // (d) A range SPANNING both years: movement must be the SUM of both years' real activity
        // (500+800 income, 200+300 expense) with the 2026 YEC's own zeroing lines still excluded —
        // pinning that the exclusion is unconditional on doc_type, not scoped to "only when the
        // range equals exactly one fiscal year." Opening balances for this spanning range (dateFrom
        // = 2026-01-01) must be zero for all three accounts, since nothing predates 2026 in this
        // fixture — i.e. "openings before the range start still include prior YECs" is vacuously
        // true here (there are none before 2026), and is separately proven non-vacuously by (c)
        // above using the 2027 range start.
        $spanning = $this->trialBalance()->generate($company->id, $y1Start, $y2End, ['show_zero' => true]);
        $spanIncome = collect($spanning['accounts'])->firstWhere('id', $incomeAccount->id);
        $spanExpense = collect($spanning['accounts'])->firstWhere('id', $expenseAccount->id);
        $this->assertEqualsWithDelta(1300.0, (float) $spanIncome->total_credit, 0.001, 'Spanning range income movement must be the sum of both years real activity (500+800), YEC excluded throughout.');
        $this->assertEqualsWithDelta(0.0, (float) $spanIncome->total_debit, 0.001, 'Spanning range income must not pick up the 2026 YEC zeroing debit even though that YEC date falls inside this range.');
        $this->assertEqualsWithDelta(500.0, (float) $spanExpense->total_debit, 0.001, 'Spanning range expense movement must be the sum of both years real activity (200+300), YEC excluded throughout.');
        $this->assertEqualsWithDelta(0.0, (float) $spanExpense->total_credit, 0.001, 'Spanning range expense must not pick up the 2026 YEC zeroing credit even though that YEC date falls inside this range.');

        $spanOpening = $this->trialBalance()->getOpeningBalances($company->id, Carbon::create(2026, 1, 1));
        $spanIncomeOpening = $spanOpening[$incomeAccount->id] ?? ['opening_debit' => 0.0, 'opening_credit' => 0.0];
        $this->assertEqualsWithDelta(0.0, $spanIncomeOpening['opening_credit'] - $spanIncomeOpening['opening_debit'], 0.001, 'Nothing predates 2026 in this fixture, so the spanning range must open at zero.');
    }

    /**
     * T5 (dividend sweep, L9): posts a Dr 3200 (Dividends Paid) / Cr 1201 (bank) pair for the
     * year, same fixture shape as postPlAndAr(). Reused by every dividend-exclusion test below.
     */
    private function postDividendPayment(Company $company, Branch $branch, int $year, float $amount): Account
    {
        $dividends = $this->resolver()->resolve('DIVIDENDS_PAID', $company->id);
        $bank = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();
        $date = Carbon::create($year, 9, 1);

        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => $amount, 'description' => 'Dividend payment fixture', 'reference_type' => 'Payment',
            'reference_number' => 'DIV-'.uniqid(), 'name' => 'Dividend payment fixture', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'JV', 'doc_year' => $year, 'posting_status' => 'posted',
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => uniqid('div:'),
        ]);

        $this->makeLine($txn, $company, $branch, $dividends, $amount, 0, $date);
        $this->makeLine($txn, $company, $branch, $bank, 0, $amount, $date);

        return $dividends;
    }

    /**
     * T5 (L9): (1) 3200's own real year movement (the Dr 100 dividend payment) must still show,
     * un-zeroed, in the closing year's OWN trial-balance movement -- exactly the same whole-
     * document YEC exclusion the Income/Expense leaves already get, now proven for the dividend
     * leaf too. (2) Opening balances for the NEXT year must show 3200 back at zero (the sweep IS
     * included in getOpeningBalances(), same as the Income/Expense zeroing) -- "3200 has zero
     * carried balance into the new year" from the task spec, pinned directly.
     */
    public function test_dividend_sweep_movement_excluded_from_same_year_report_but_included_in_next_year_opening(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);
        $this->postPlAndAr($company, $branch, 2026, income: 500, expense: 200);
        $dividendsAccount = $this->postDividendPayment($company, $branch, 2026, 100);

        [$yearStart, $yearEnd] = $this->yearBounds(2026);

        $before = $this->trialBalance()->generate($company->id, $yearStart, $yearEnd, ['show_zero' => true]);
        $dividendRowBefore = collect($before['accounts'])->firstWhere('id', $dividendsAccount->id);
        $this->assertEqualsWithDelta(100.0, (float) $dividendRowBefore->total_debit, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $dividendRowBefore->total_credit, 0.001);

        $closeResult = $this->service()->run($company->id, 2026, null);
        $this->assertTrue($closeResult['success']);
        $this->assertNotNull($closeResult['transaction']);

        // (1) Same-year movement, re-queried after close: 3200's real Dr 100 must still show
        // un-zeroed -- the YEC's own Cr 100 sweep line, dated inside this same year, is excluded
        // as part of the whole YEC document, exactly like the Income/Expense leaves.
        $after = $this->trialBalance()->generate($company->id, $yearStart, $yearEnd, ['show_zero' => true]);
        $dividendRowAfter = collect($after['accounts'])->firstWhere('id', $dividendsAccount->id);
        $this->assertEqualsWithDelta(100.0, (float) $dividendRowAfter->total_debit, 0.001, 'Dividends Paid movement for the closed year must remain 100, not swept to zero by the YEC lines landing in the same date range.');
        $this->assertEqualsWithDelta(0.0, (float) $dividendRowAfter->total_credit, 0.001, 'Dividends Paid must not pick up its own YEC zeroing credit inside the same-year movement query.');

        // (2) Opening balances for 2027: 3200 must be back at net zero -- the sweep IS counted in
        // getOpeningBalances() (no YEC exclusion there, same rule the Income/Expense leaves rely
        // on for their own zero opening).
        $openingForNextYear = $this->trialBalance()->getOpeningBalances($company->id, Carbon::create(2027, 1, 1));
        $dividendOpening = $openingForNextYear[$dividendsAccount->id] ?? ['opening_debit' => 0.0, 'opening_credit' => 0.0];
        $this->assertEqualsWithDelta(
            0.0,
            $dividendOpening['opening_debit'] - $dividendOpening['opening_credit'],
            0.001,
            'Dividends Paid must carry a ZERO balance into 2027 opening -- the year-end sweep must zero it out, same as every other swept leaf.'
        );

        // Retained Earnings opening for 2027 must now be net profit (300) minus dividends (100) = 200.
        $retainedEarnings = $this->resolver()->resolve('RETAINED_EARNINGS', $company->id);
        $reOpening = $openingForNextYear[$retainedEarnings->id] ?? ['opening_debit' => 0.0, 'opening_credit' => 0.0];
        $this->assertEqualsWithDelta(200.0, $reOpening['opening_credit'] - $reOpening['opening_debit'], 0.001, 'Retained Earnings must open 2027 carrying profit (300) minus the dividend sweep (100) = 200.');
    }

    /**
     * T5: multi-year case -- Y1 (2026) has P&L + a dividend, closed; Y2 (2027) has its own,
     * different dividend and is ALSO closed. Pins that each year's own dividend sweep is
     * independent -- Y2's sweep does not disturb Y1's already-swept 3200/RE figures, and Y2's own
     * opening for 2028 reflects the CUMULATIVE effect of both years' dividends.
     */
    public function test_multi_year_dividend_sweep_is_independent_per_year(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);
        $this->postPlAndAr($company, $branch, 2026, income: 500, expense: 200);
        $dividendsAccount = $this->postDividendPayment($company, $branch, 2026, 100);
        $y1Close = $this->service()->run($company->id, 2026, null);
        $this->assertTrue($y1Close['success']);

        $this->lockAllMonths($company, 2027);
        $this->postPlAndAr($company, $branch, 2027, income: 800, expense: 300);
        $this->postDividendPayment($company, $branch, 2027, 150);
        $y2Close = $this->service()->run($company->id, 2027, null);
        $this->assertTrue($y2Close['success']);
        $this->assertEqualsWithDelta(500.0, $y2Close['net_profit'], 0.001, 'Y2 net_profit (800-300) must be unaffected by Y1s already-closed dividend sweep.');

        $retainedEarnings = $this->resolver()->resolve('RETAINED_EARNINGS', $company->id);
        $opening2028 = $this->trialBalance()->getOpeningBalances($company->id, Carbon::create(2028, 1, 1));

        // 3200 must be back at zero going into 2028 -- both years' dividend sweeps applied.
        $dividendOpening2028 = $opening2028[$dividendsAccount->id] ?? ['opening_debit' => 0.0, 'opening_credit' => 0.0];
        $this->assertEqualsWithDelta(0.0, $dividendOpening2028['opening_debit'] - $dividendOpening2028['opening_credit'], 0.001, 'Dividends Paid must carry zero into 2028 -- both years dividend sweeps applied.');

        // Retained Earnings cumulative: Y1 (300-100=200) + Y2 (500-150=350) = 550.
        $reOpening2028 = $opening2028[$retainedEarnings->id] ?? ['opening_debit' => 0.0, 'opening_credit' => 0.0];
        $this->assertEqualsWithDelta(550.0, $reOpening2028['opening_credit'] - $reOpening2028['opening_debit'], 0.001, 'Retained Earnings 2028 opening must be the cumulative net of both years profit-minus-dividends: (300-100)+(500-150)=550.');
    }
}
