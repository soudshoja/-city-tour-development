<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\ReportController;
use App\Models\Account;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * P2.5.B (p2_5-brief.md §P2.5.B; BUG-C4, doc 08): pins the fix to
 * {@see ReportController::profitLoss()} — it used to bucket journal_entries by `created_at` (when
 * the row was inserted) instead of `posting_date` (which accounting period the entry actually
 * belongs to). This is exactly the brief's own required scenario: "report grouping on a Feb-dated
 * doc entered in March after Feb close" — a journal entry whose `transaction_date` is February but
 * whose `posting_date` was shifted to March (by `PeriodGuard`'s posting-date-shift mechanism; see
 * PostingDateShiftTest for the shift mechanism itself) must appear in March's P&L, not February's,
 * regardless of when the row was physically inserted (`created_at`).
 *
 * Journal entry rows are inserted directly via `DB::table('journal_entries')->insert()` (the same
 * fixture convention `TrialBalanceServiceLedgerBalanceTest::insertJournalEntry()` already
 * establishes) — the point under test is the report's own read-side query, not the posting engine
 * that would normally produce this posting_date/transaction_date split.
 */
class ReportControllerProfitLossPostingDateTest extends TestCase
{
    use RefreshDatabase;

    private function makeAuthorizedAdmin(Company $company): User
    {
        Permission::firstOrCreate(['name' => 'view profit loss', 'group' => 'report']);
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        $admin->givePermissionTo('view profit loss');

        $this->actingAs($admin);
        session(['company_id' => $company->id]);

        return $admin;
    }

    /** A level-3 PROFIT_LOSS-report_type Income leaf — exactly the shape profitLoss() groups by. */
    private function makeIncomeAccount(Company $company): Account
    {
        return Account::factory()->create([
            'company_id' => $company->id,
            'level' => 3,
            'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
            'code' => '4001',
            'parent_id' => null,
        ]);
    }

    private function insertJournalEntry(
        Company $company,
        Account $account,
        \DateTimeInterface $transactionDate,
        \DateTimeInterface $postingDate,
        float $credit
    ): void {
        DB::table('journal_entries')->insert([
            'name' => $account->name,
            'transaction_id' => null,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'branch_id' => null,
            'transaction_date' => $transactionDate,
            'posting_date' => $postingDate,
            'description' => 'ReportControllerProfitLossPostingDateTest fixture line',
            'debit' => 0,
            'credit' => $credit,
            'balance' => null,
            'voucher_number' => null,
            'currency' => 'KWD',
            'exchange_rate' => 1.0,
            'amount' => $credit,
            'reconciled' => 0,
            'original_currency' => 'KWD',
            'original_amount' => $credit,
            // Deliberately set to a THIRD, unrelated date -- if profitLoss() ever regresses back to
            // bucketing on created_at instead of posting_date, this value would put the entry in
            // NEITHER of the two months this test queries, making a created_at-based regression
            // impossible to miss as a false "still balanced" pass.
            'created_at' => '2020-01-01 00:00:00',
            'updated_at' => now(),
        ]);
    }

    public function test_february_dated_entry_shifted_to_march_appears_in_marchs_profit_loss_not_februarys(): void
    {
        $company = Company::factory()->create();
        $account = $this->makeIncomeAccount($company);
        $this->makeAuthorizedAdmin($company);

        $this->insertJournalEntry(
            $company,
            $account,
            transactionDate: \Carbon\Carbon::create(2026, 2, 10),
            postingDate: \Carbon\Carbon::create(2026, 3, 15), // shifted forward past Feb's close
            credit: 500.00,
        );

        $controller = app(ReportController::class);

        // profitLoss() builds one $grouped row per level-3 account regardless of whether anything
        // posted to it that month (a zero-amount row, not an absent key) -- so the assertion is on
        // the AMOUNT, not key presence.
        $februaryView = $controller->profitLoss(Request::create('/reports/profit-loss', 'GET', ['month' => '2026-02']));
        $februaryIncome = $februaryView->getData()['incomeAccounts']->toArray();
        $this->assertSame(
            0.0,
            (float) $februaryIncome[$account->id]['amount'],
            'A posting_date-shifted-to-March entry must NOT count toward February\'s P&L.'
        );

        $marchView = $controller->profitLoss(Request::create('/reports/profit-loss', 'GET', ['month' => '2026-03']));
        $marchIncome = $marchView->getData()['incomeAccounts']->toArray();
        $this->assertEqualsWithDelta(500.00, $marchIncome[$account->id]['amount'], 0.001);
    }

    /**
     * Sanity control for the test above: an ordinary, unshifted entry (transaction_date ==
     * posting_date, both February) appears in February and NOT in March -- proving the fixture and
     * assertions genuinely discriminate on month, not just always-pass/always-fail.
     */
    public function test_unshifted_february_entry_appears_only_in_february(): void
    {
        $company = Company::factory()->create();
        $account = $this->makeIncomeAccount($company);
        $this->makeAuthorizedAdmin($company);

        $this->insertJournalEntry(
            $company,
            $account,
            transactionDate: \Carbon\Carbon::create(2026, 2, 10),
            postingDate: \Carbon\Carbon::create(2026, 2, 10),
            credit: 300.00,
        );

        $controller = app(ReportController::class);

        $februaryIncome = $controller->profitLoss(Request::create('/reports/profit-loss', 'GET', ['month' => '2026-02']))
            ->getData()['incomeAccounts']->toArray();
        $this->assertEqualsWithDelta(300.00, $februaryIncome[$account->id]['amount'], 0.001);

        $marchIncome = $controller->profitLoss(Request::create('/reports/profit-loss', 'GET', ['month' => '2026-03']))
            ->getData()['incomeAccounts']->toArray();
        $this->assertSame(0.0, (float) $marchIncome[$account->id]['amount']);
    }
}
