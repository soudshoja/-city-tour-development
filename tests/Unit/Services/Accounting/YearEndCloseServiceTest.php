<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\YearEndCloseService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.C (p2_5-brief.md §P2.5.C; doc 11 §P5.2): "accounting:year:close ... all 12 months locked ->
 * one YEC document closing P&L to 3400 Retained Earnings with allowLockedPeriods=true, idempotent."
 */
class YearEndCloseServiceTest extends AccountingTestCase
{
    private function service(): YearEndCloseService
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

        // Two balanced pairs on the same document: Dr AR / Cr Income for the sale (full income),
        // Dr Expense / Cr AR for the cost (full expense) -- leaves a real Income credit balance and
        // a real Expense debit balance for the year to sweep, with the whole document balanced
        // (Σdebit = income + expense = Σcredit).
        $this->makeLine($txn, $company, $branch, $ar, $income, 0, $date);
        $this->makeLine($txn, $company, $branch, $incomeAccount, 0, $income, $date);
        $this->makeLine($txn, $company, $branch, $expenseAccount, $expense, 0, $date);
        $this->makeLine($txn, $company, $branch, $ar, 0, $expense, $date);
    }

    // ── Preconditions ────────────────────────────────────────────────────────────────────────────

    public function test_refuses_when_not_every_month_is_locked(): void
    {
        [$company] = $this->makeEngineOnCompany();
        // Only 11 of 12 months locked.
        for ($m = 1; $m <= 11; $m++) {
            AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => $m, 'status' => AccountingPeriod::STATUS_LOCKED]);
        }

        $result = $this->service()->run($company->id, 2026);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['blocking']);
    }

    public function test_refuses_when_airline_memo_control_is_non_zero(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);

        $liabilities = Account::withoutGlobalScopes()->where('company_id', $company->id)->whereNull('parent_id')->where('name', 'Liabilities')->firstOrFail();
        $memo = Account::create([
            'company_id' => $company->id, 'code' => '1952', 'name' => 'Airline Memo Control', 'level' => 2,
            'is_group' => false, 'currency' => 'KWD', 'parent_id' => $liabilities->id, 'root_id' => $liabilities->id,
            'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0,
        ]);
        $ar = $this->resolver()->resolve('RECEIVABLE_CONTROL', $company->id);
        $date = Carbon::create(2026, 6, 1);
        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => 50, 'description' => 'memo', 'reference_type' => 'Invoice',
            'reference_number' => 'M-'.uniqid(), 'name' => 'memo', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'JV', 'doc_year' => 2026, 'posting_status' => 'posted',
            'total_debit' => 50, 'total_credit' => 50, 'idempotency_key' => uniqid('memo:'),
        ]);
        $this->makeLine($txn, $company, $branch, $memo, 0, 50, $date);
        $this->makeLine($txn, $company, $branch, $ar, 50, 0, $date);

        $result = $this->service()->run($company->id, 2026);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['blocking']);
    }

    // ── Successful close ─────────────────────────────────────────────────────────────────────────

    public function test_posts_a_balanced_yec_and_zeroes_the_pl_leaves_for_the_year(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);
        $this->postPlAndAr($company, $branch, 2026, income: 500, expense: 200);

        $result = $this->service()->run($company->id, 2026, null);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['already_closed']);
        $this->assertNotNull($result['transaction']);
        $this->assertEqualsWithDelta(300.0, $result['net_profit'], 0.001);
        $this->assertSame('YEC', $result['transaction']->doc_type);

        $lines = JournalEntry::withoutGlobalScopes()->where('transaction_id', $result['transaction']->id)->get();
        $this->assertGreaterThanOrEqual(3, $lines->count()); // Income leaf + Expense leaf + Retained Earnings.
        $this->assertEqualsWithDelta((float) $lines->sum('debit'), (float) $lines->sum('credit'), 0.001);

        $retainedEarnings = $this->resolver()->resolve('RETAINED_EARNINGS', $company->id);
        $reLine = $lines->firstWhere('account_id', $retainedEarnings->id);
        $this->assertNotNull($reLine);
        $this->assertEqualsWithDelta(300.0, (float) $reLine->credit, 0.001);
    }

    public function test_second_run_for_the_same_year_is_idempotent(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);
        $this->postPlAndAr($company, $branch, 2026, income: 400, expense: 100);

        $first = $this->service()->run($company->id, 2026);
        $this->assertTrue($first['success']);
        $firstTxnId = $first['transaction']->id;

        $second = $this->service()->run($company->id, 2026);

        $this->assertTrue($second['success']);
        $this->assertTrue($second['already_closed']);
        $this->assertSame($firstTxnId, $second['transaction']->id);

        // No second YEC document was posted.
        $this->assertSame(1, Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('doc_type', 'YEC')->count());
    }

    public function test_no_pl_activity_posts_nothing_and_still_succeeds(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);

        $result = $this->service()->run($company->id, 2026);

        $this->assertTrue($result['success']);
        $this->assertNull($result['transaction']);
    }

    // ── T5: dividend sweep (accounting-builds, L9) ──────────────────────────────────────────────

    /**
     * Posts a Dr 3200 (Dividends Paid) / Cr 1201 (bank) pair for the year — the ordinary "dividend
     * paid out of the bank" shape — independent of the PL fixture's own AR-only pair.
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
     * T5 (L9, MP-5-1): the dividend sweep zeroes 3200's year movement and reduces Retained
     * Earnings by the SAME amount, WITHOUT altering `net_profit` — net_profit stays the pure
     * Income-minus-Expense figure (500-200=300), and the Retained-Earnings LINE posted for it
     * (its own credit) is unchanged at 300; the dividend's separate Dr RE line (100) is what
     * actually reduces the account's balance to 200 once both lines are summed.
     */
    public function test_dividends_are_swept_and_retained_earnings_reflects_profit_minus_dividends(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);
        $this->postPlAndAr($company, $branch, 2026, income: 500, expense: 200);
        $dividendsAccount = $this->postDividendPayment($company, $branch, 2026, 100);

        $result = $this->service()->run($company->id, 2026, null);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['transaction']);
        // MP-5-1 pin: net_profit must remain the pure P&L figure, dividends never folded in.
        $this->assertEqualsWithDelta(300.0, $result['net_profit'], 0.001);

        $lines = JournalEntry::withoutGlobalScopes()->where('transaction_id', $result['transaction']->id)->get();
        $this->assertEqualsWithDelta((float) $lines->sum('debit'), (float) $lines->sum('credit'), 0.001, 'YEC document (P&L sweep + dividend sweep together) must still balance.');

        // 3200's own sweep line: credited 100 (zeroing the year's Dr 100 dividend payment).
        $dividendSweepLine = $lines->firstWhere('account_id', $dividendsAccount->id);
        $this->assertNotNull($dividendSweepLine);
        $this->assertEqualsWithDelta(100.0, (float) $dividendSweepLine->credit, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $dividendSweepLine->debit, 0.001);

        // Retained Earnings carries TWO lines on this document: +300 (net profit) and -100
        // (dividends) -- net effect +200, exactly profit minus dividends.
        $retainedEarnings = $this->resolver()->resolve('RETAINED_EARNINGS', $company->id);
        $reLines = $lines->where('account_id', $retainedEarnings->id);
        $this->assertCount(2, $reLines, 'Retained Earnings must carry two distinct lines on the YEC document -- the net-profit sweep and the dividend sweep -- not one merged line.');
        $reNet = (float) $reLines->sum('credit') - (float) $reLines->sum('debit');
        $this->assertEqualsWithDelta(200.0, $reNet, 0.001, 'Retained Earnings net effect for the year must be profit (300) minus dividends (100) = 200.');
    }

    /**
     * T5: a second run for a year with a dividend sweep is still idempotent -- no new document,
     * no new lines, on either 3200 or Retained Earnings.
     */
    public function test_dividend_sweep_second_run_is_idempotent(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);
        $this->postPlAndAr($company, $branch, 2026, income: 400, expense: 100);
        $this->postDividendPayment($company, $branch, 2026, 50);

        $first = $this->service()->run($company->id, 2026);
        $this->assertTrue($first['success']);
        $firstTxnId = $first['transaction']->id;

        $second = $this->service()->run($company->id, 2026);

        $this->assertTrue($second['success']);
        $this->assertTrue($second['already_closed']);
        $this->assertSame($firstTxnId, $second['transaction']->id);
        $this->assertSame(1, Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('doc_type', 'YEC')->count(), 'Second close run must not post a second YEC document, even with a dividend sweep involved.');
    }

    /**
     * T5: a year with dividend movement but ZERO P&L activity still gets its dividend sweep --
     * the empty-P&L short-circuit in buildClosingLines() must not also skip a real dividend sweep.
     */
    public function test_dividends_only_year_with_no_pl_activity_still_sweeps(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $this->lockAllMonths($company, 2026);
        $dividendsAccount = $this->postDividendPayment($company, $branch, 2026, 75);

        $result = $this->service()->run($company->id, 2026, null);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['transaction'], 'A dividends-only year must still post a YEC document, not be treated as "nothing to sweep".');
        $this->assertEqualsWithDelta(0.0, (float) $result['net_profit'], 0.001);

        $lines = JournalEntry::withoutGlobalScopes()->where('transaction_id', $result['transaction']->id)->get();
        $dividendSweepLine = $lines->firstWhere('account_id', $dividendsAccount->id);
        $this->assertNotNull($dividendSweepLine);
        $this->assertEqualsWithDelta(75.0, (float) $dividendSweepLine->credit, 0.001);
    }
}
