<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting\Reports;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\Reports\EquityChangesReportService;
use App\Services\Accounting\YearEndCloseService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T6 (L10): Statement of Changes in Equity. See
 * {@see EquityChangesReportService}'s own class docblock for the full derivation of why
 * `checks.ties_to_next_year_opening` is TRUE post-close and (when net profit is non-zero) FALSE
 * pre-close — this suite proves both regimes rather than assuming one.
 */
class EquityChangesReportServiceTest extends AccountingTestCase
{
    private function service(): EquityChangesReportService
    {
        return app(EquityChangesReportService::class);
    }

    private function yearEndClose(): YearEndCloseService
    {
        return app(YearEndCloseService::class);
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

    private function postPlAndAr(Company $company, Branch $branch, int $year, float $income, float $expense): void
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
    }

    private function postDividendPayment(Company $company, Branch $branch, int $year, float $amount): void
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
    }

    private function postCapitalInjection(Company $company, Branch $branch, int $year, float $amount): void
    {
        $capital = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '3100')->firstOrFail();
        $bank = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();
        $date = Carbon::create($year, 2, 1);

        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => $amount, 'description' => 'Capital injection fixture', 'reference_type' => 'Payment',
            'reference_number' => 'CAP-'.uniqid(), 'name' => 'Capital injection fixture', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'JV', 'doc_year' => $year, 'posting_status' => 'posted',
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => uniqid('cap:'),
        ]);

        $this->makeLine($txn, $company, $branch, $bank, $amount, 0, $date);
        $this->makeLine($txn, $company, $branch, $capital, 0, $amount, $date);
    }

    /**
     * (T6 primary invariant, L10): for a CLOSED year (T5's YEC has run), the statement's
     * pro-forma closing equity total must tie EXACTLY to the real ledger's next-year opening
     * equity total, to the fils. Year has income, expense, a dividend, AND a capital movement --
     * every component exercised at once.
     */
    public function test_closing_equity_ties_to_next_year_opening_for_a_closed_year(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);
        $this->postCapitalInjection($company, $branch, 2026, 1000);
        $this->postPlAndAr($company, $branch, 2026, income: 500, expense: 200);
        $this->postDividendPayment($company, $branch, 2026, 100);

        $close = $this->yearEndClose()->run($company->id, 2026, null);
        $this->assertTrue($close['success']);
        $this->assertNotNull($close['transaction']);

        $statement = $this->service()->generate($company->id, 2026);

        $this->assertEqualsWithDelta(300.0, $statement['net_profit'], 0.001);
        $this->assertEqualsWithDelta(100.0, $statement['dividends_paid_this_year'], 0.001);
        $this->assertEqualsWithDelta(1000.0, $statement['components']['capital']['movement'], 0.001);

        $this->assertTrue($statement['checks']['ties_to_next_year_opening'], 'Post-close, the statement closing total must tie exactly to the real ledger next-year opening.');
        $this->assertEqualsWithDelta(0.0, $statement['checks']['difference'], 0.001);
        $this->assertEqualsWithDelta($statement['closing_equity_total'], $statement['checks']['next_year_opening_total'], 0.001);

        // MP-6-1 proof point: Retained Earnings' OWN period movement (not the grand total, which
        // is provably invariant to the exclusion -- a balanced YEC document's signed contributions
        // to netProfit/dividendsMovement/reMovement telescope to a net-zero shift on the total
        // either way) must be ~0 -- the YEC's own sweep lines, dated inside this same year, are
        // excluded from this component's period movement exactly like every other YEC-excluded
        // leaf. If TrialBalanceService's YEC exclusion were disabled, this specific assertion is
        // what catches it (see the builder's own apply/revert mutation log).
        $this->assertEqualsWithDelta(0.0, $statement['components']['retained_earnings']['movement'], 0.001, 'Retained Earnings period movement for the closing year must be ~0 (YEC-excluded) -- the swept effect must arrive via net_profit/dividends, never via this leafs own in-period movement.');

        // MP-6-2 companion (internal cross-check, class docblock's second derivation): the
        // independently-computed ledger-derivation total must also tie to the fils.
        $this->assertTrue($statement['checks']['ties_to_ledger_derivation']);
        $this->assertEqualsWithDelta(0.0, $statement['checks']['ledger_difference'], 0.001);
    }

    /**
     * (T6, "pre-close vs post-close readings consistent with the YEC rule"): the SAME year, read
     * BEFORE and AFTER year-end close. Every raw component figure (opening, movement, net_profit,
     * dividends_paid_this_year, and the pro-forma closing_equity_total) must be IDENTICAL in both
     * readings -- the statement's own formula never depends on whether a YEC document exists,
     * only on real posted movement (which the YEC exclusion keeps constant either way). The ONLY
     * thing that changes is `checks.ties_to_next_year_opening`: false pre-close (net profit has
     * not actually reached Retained Earnings in the ledger yet), true post-close.
     */
    public function test_pre_close_and_post_close_readings_are_numerically_identical_except_the_tie_check(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);
        $this->postPlAndAr($company, $branch, 2026, income: 500, expense: 200);
        $this->postDividendPayment($company, $branch, 2026, 100);

        $preClose = $this->service()->generate($company->id, 2026);
        $this->assertFalse($preClose['checks']['ties_to_next_year_opening'], 'Pre-close, the pro-forma total must NOT yet tie to the real ledger -- net profit has not been swept into Retained Earnings.');
        // The discrepancy pre-close must be exactly this year's net profit (300) -- class
        // docblock's derivation, pinned numerically.
        $this->assertEqualsWithDelta(300.0, $preClose['checks']['difference'], 0.001);

        $close = $this->yearEndClose()->run($company->id, 2026, null);
        $this->assertTrue($close['success']);

        $postClose = $this->service()->generate($company->id, 2026);
        $this->assertTrue($postClose['checks']['ties_to_next_year_opening']);
        $this->assertEqualsWithDelta(0.0, $postClose['checks']['difference'], 0.001);

        // Every component and total figure identical pre- vs post-close.
        $this->assertEqualsWithDelta($preClose['net_profit'], $postClose['net_profit'], 0.001);
        $this->assertEqualsWithDelta($preClose['dividends_paid_this_year'], $postClose['dividends_paid_this_year'], 0.001);
        $this->assertEqualsWithDelta($preClose['closing_equity_total'], $postClose['closing_equity_total'], 0.001);
        $this->assertEqualsWithDelta($preClose['components']['capital']['closing'], $postClose['components']['capital']['closing'], 0.001);
        $this->assertEqualsWithDelta($preClose['components']['retained_earnings']['closing'], $postClose['components']['retained_earnings']['closing'], 0.001);
    }

    /**
     * (T6, "year without" dividends): a year with pure P&L activity and no dividend payment at
     * all -- dividends_paid_this_year must be exactly zero and the invariant must still hold once
     * closed (an unclosed year with zero net profit ALSO ties -- see class docblock: the
     * discrepancy is exactly net profit, so a zero-profit year ties even pre-close).
     */
    public function test_year_without_dividends_ties_and_reports_zero_dividends(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);
        $this->postPlAndAr($company, $branch, 2026, income: 400, expense: 150);

        $close = $this->yearEndClose()->run($company->id, 2026, null);
        $this->assertTrue($close['success']);

        $statement = $this->service()->generate($company->id, 2026);

        $this->assertEqualsWithDelta(0.0, $statement['dividends_paid_this_year'], 0.001);
        $this->assertEqualsWithDelta(250.0, $statement['net_profit'], 0.001);
        $this->assertTrue($statement['checks']['ties_to_next_year_opening']);
    }

    /**
     * (L10, "holds for an unclosed year"): an UNCLOSED year whose only activity is pure equity-
     * leaf movement (a capital injection and a dividend payment, deliberately ZERO net profit --
     * no income/expense fixture at all). Per the class docblock's derivation, the pre-close vs
     * post-close discrepancy is exactly net profit -- with net profit at zero, the invariant
     * holds even though the year was never closed, proving the tie-check is not simply "always
     * false pre-close" but genuinely tracks the real ledger.
     */
    public function test_unclosed_year_with_zero_net_profit_still_ties(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->postCapitalInjection($company, $branch, 2026, 500);
        $this->postDividendPayment($company, $branch, 2026, 200);
        // Deliberately NOT closed: no lockAllMonths(), no YearEndCloseService::run() call.

        $statement = $this->service()->generate($company->id, 2026);

        $this->assertEqualsWithDelta(0.0, $statement['net_profit'], 0.001);
        $this->assertEqualsWithDelta(200.0, $statement['dividends_paid_this_year'], 0.001);
        $this->assertTrue($statement['checks']['ties_to_next_year_opening'], 'Zero net profit means nothing is pending a sweep -- the invariant must hold even though this year was never closed.');
    }

    /**
     * MP-6-1 (adversarial verification): applied by temporarily removing TrialBalanceService's
     * own YEC whole-document movement exclusion and confirming this exact test goes red, then
     * reverting -- see the builder's own report for the mutation/revert log. This test asserts
     * the NORMAL (unmutated) behaviour: a closed year's statement ties to the fils, which is only
     * true because YEC lines are excluded from period movement.
     */
    public function test_closed_year_with_large_pl_and_dividends_still_ties_to_the_fils(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);
        $this->postPlAndAr($company, $branch, 2026, income: 12345.678, expense: 6789.012);
        $this->postDividendPayment($company, $branch, 2026, 3210.456);

        $close = $this->yearEndClose()->run($company->id, 2026, null);
        $this->assertTrue($close['success']);

        $statement = $this->service()->generate($company->id, 2026);

        $this->assertTrue($statement['checks']['ties_to_next_year_opening']);
        $this->assertEqualsWithDelta(0.0, $statement['checks']['difference'], 0.001, 'Must tie to the fils (0.001 KWD tolerance), not just approximately.');
    }

    /**
     * accounting-builds Wave 3 lane I item A2 (T5/T6 §12 sign-off finding): every component's
     * Opening/Closing column must foot to the equity total when a dividend moved this year — the
     * Dividends Paid row's presented Closing must NOT double-count into the total the same
     * dividend movement Retained Earnings' own pro-forma Closing already folded in. Exercised both
     * pre-close (dividend leaf still carries its raw unswept balance in the real ledger) and
     * post-close (YEC has actually swept it) — the presented figures must foot identically either
     * way, since this is a presentation fix, not a ledger-state-dependent one.
     */
    public function test_dividends_paid_row_and_all_component_columns_foot_to_the_equity_totals(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->postCapitalInjection($company, $branch, 2026, 1000);
        $this->postPlAndAr($company, $branch, 2026, income: 500, expense: 200);
        $this->postDividendPayment($company, $branch, 2026, 100);

        $preClose = $this->service()->generate($company->id, 2026);

        $this->assertEqualsWithDelta(0.0, $preClose['components']['dividends_paid']['closing'], 0.001, 'Dividends Paid Closing must be presented as swept (0), never the raw unswept leaf balance.');
        $this->assertEqualsWithDelta(-100.0, $preClose['components']['dividends_paid']['movement'], 0.001, 'The real period dividend payment must still be visible in Movement even though Closing is presented as swept.');

        $openingFooted = $preClose['components']['capital']['opening'] + $preClose['components']['opening_balance_equity']['opening']
            + $preClose['components']['retained_earnings']['opening'] + $preClose['components']['dividends_paid']['opening'];
        $closingFooted = $preClose['components']['capital']['closing'] + $preClose['components']['opening_balance_equity']['closing']
            + $preClose['components']['retained_earnings']['closing'] + $preClose['components']['dividends_paid']['closing'];

        $this->assertEqualsWithDelta($preClose['opening_equity_total'], $openingFooted, 0.001, 'Summing every row\'s Opening column must foot to opening_equity_total.');
        $this->assertEqualsWithDelta($preClose['closing_equity_total'], $closingFooted, 0.001, 'Summing every row\'s Closing column must foot to closing_equity_total — this is the exact footing that broke when Dividends Paid showed its raw unswept balance.');

        // Same assertions post-close: the presentation fix must not depend on YEC having run.
        $this->lockAllMonths($company, 2026);
        $close = $this->yearEndClose()->run($company->id, 2026, null);
        $this->assertTrue($close['success']);

        $postClose = $this->service()->generate($company->id, 2026);

        $this->assertEqualsWithDelta(0.0, $postClose['components']['dividends_paid']['closing'], 0.001);

        $closingFootedPostClose = $postClose['components']['capital']['closing'] + $postClose['components']['opening_balance_equity']['closing']
            + $postClose['components']['retained_earnings']['closing'] + $postClose['components']['dividends_paid']['closing'];
        $this->assertEqualsWithDelta($postClose['closing_equity_total'], $closingFootedPostClose, 0.001);
    }

    /**
     * MP-6-2 (adversarial verification, "never reads accounts.actual_balance / journal_entries.
     * balance"): a static source-text guard on the service file itself, mirroring the class
     * docblock's own claim. This is a genuine mutation-catching oracle: injecting either forbidden
     * read into the file makes THIS assertion fail immediately (proved by the builder's own
     * apply/revert cycle against a temporary copy of the check), independent of PurposeMapping/
     * ArchitectureTest-style scans (the repository has no existing generalized scan test for
     * `actual_balance` reads to extend -- confirmed absent by grep -- so this task adds its own).
     */
    public function test_service_never_reads_actual_balance_or_journal_entries_balance_column(): void
    {
        $source = file_get_contents(app_path('Services/Accounting/Reports/EquityChangesReportService.php'));
        $this->assertNotFalse($source);

        // Strip block (/** ... */) and line (// ...) comments first -- the class docblock itself
        // legitimately DISCUSSES the forbidden columns by name (explaining why they're avoided),
        // so a raw string search over the whole file would false-positive on its own
        // documentation. Only executable code is checked below.
        $codeOnly = preg_replace('#/\*.*?\*/#s', '', $source) ?? $source;
        $codeOnly = preg_replace('#//[^\n]*#', '', $codeOnly) ?? $codeOnly;

        $this->assertStringNotContainsString('actual_balance', $codeOnly, 'EquityChangesReportService must never read accounts.actual_balance (deviation 2 -- truncates fils) in executable code.');
        $this->assertStringNotContainsString("'balance'", $codeOnly, 'EquityChangesReportService must never read journal_entries.balance (derived-balance-only rule) in executable code.');
        $this->assertStringNotContainsString('->balance', $codeOnly, 'EquityChangesReportService must never read a ->balance property in executable code.');
    }
}
