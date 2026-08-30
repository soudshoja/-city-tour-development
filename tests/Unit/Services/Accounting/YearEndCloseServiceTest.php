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
}
