<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\ReportController;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsParityFixtures;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
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
 *
 * LP5a UPDATE (.planning/phases/legacy-ledger-pilot/LP5A-PL-SCREEN-2026-09-07.md): the fixture and
 * the assertions moved to the report's NEW population rule, and the change is not cosmetic. This
 * test used to build a single parentless account with `report_type = 'profit loss'`, `level = 3`
 * and code `'4001'`, because that was literally what the old implementation selected on: a level-3
 * account whose code began with `4`. That shape does not describe any real account — a real P&L
 * account is a LEAF under the positional `Income` or `Expenses` root, at whatever depth the chart
 * puts it, and its code is an imported legacy code carrying no meaning. The report now selects on
 * exactly that (see {@see \App\Services\ProfitLossService}), so the fixture is now a real
 * root -> section -> leaf chain, and the assertions read the totals rather than an account-id-keyed
 * map the report no longer returns. The posting-date property under test is unchanged.
 *
 * CT port (U1, XBRL X16 item 1): taken from Akeed with the P&L screen, plus City Travelers'
 * accounting-module grant (CT's ReportPolicy gates on the module before the permission).
 */
class ReportControllerProfitLossPostingDateTest extends TestCase
{
    use BuildsParityFixtures;
    use GrantsAccountingModule;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Company::forgetModuleCache();
        parent::tearDown();
    }

    private function makeAuthorizedAdmin(Company $company): User
    {
        // CT: ReportPolicy::viewProfitLoss() gates on Modules::ACCOUNTING first (fails closed with
        // no module.accounting Setting row) -- see GrantsAccountingModule's own docblock.
        $this->grantAccountingModule($company);

        Permission::firstOrCreate(['name' => 'view profit loss', 'group' => 'report']);
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        $admin->givePermissionTo('view profit loss');

        $this->actingAs($admin);
        session(['company_id' => $company->id]);

        return $admin;
    }

    /**
     * An Income LEAF under the Income root — the shape profitLoss() now reports on. The code
     * deliberately starts with `4`, which under the old prefix rule would have made it an
     * "expense"; under the positional rule its ROOT decides, and it is income.
     */
    private function makeIncomeLeaf(Company $company): int
    {
        $root = $this->insertAccount((int) $company->id, null, null, '3000', 'Income', 1, true);
        $section = $this->insertAccount((int) $company->id, $root, $root, '4000', 'INCOME ON SALES', 2, true);

        return $this->insertAccount((int) $company->id, $section, $root, '4001', 'TICKET SALES', 3, false);
    }

    private function insertJournalEntry(
        Company $company,
        int $accountId,
        \DateTimeInterface $transactionDate,
        \DateTimeInterface $postingDate,
        float $credit
    ): void {
        DB::table('journal_entries')->insert([
            'name' => 'TICKET SALES',
            'transaction_id' => null,
            'company_id' => $company->id,
            'account_id' => $accountId,
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

    private function netIncomeForMonth(string $month): float
    {
        return app(ReportController::class)
            ->profitLoss(Request::create('/reports/profit-loss', 'GET', ['month' => $month]))
            ->getData()['totals']['net_income'];
    }

    public function test_february_dated_entry_shifted_to_march_appears_in_marchs_profit_loss_not_februarys(): void
    {
        $company = Company::factory()->create();
        $accountId = $this->makeIncomeLeaf($company);
        $this->makeAuthorizedAdmin($company);

        $this->insertJournalEntry(
            $company,
            $accountId,
            transactionDate: \Carbon\Carbon::create(2026, 2, 10),
            postingDate: \Carbon\Carbon::create(2026, 3, 15), // shifted forward past Feb's close
            credit: 500.00,
        );

        $this->assertEqualsWithDelta(
            0.0,
            $this->netIncomeForMonth('2026-02'),
            0.0005,
            'A posting_date-shifted-to-March entry must NOT count toward February\'s P&L.'
        );
        $this->assertEqualsWithDelta(500.00, $this->netIncomeForMonth('2026-03'), 0.0005);
    }

    /**
     * Sanity control for the test above: an ordinary, unshifted entry (transaction_date ==
     * posting_date, both February) appears in February and NOT in March -- proving the fixture and
     * assertions genuinely discriminate on month, not just always-pass/always-fail.
     */
    public function test_unshifted_february_entry_appears_only_in_february(): void
    {
        $company = Company::factory()->create();
        $accountId = $this->makeIncomeLeaf($company);
        $this->makeAuthorizedAdmin($company);

        $this->insertJournalEntry(
            $company,
            $accountId,
            transactionDate: \Carbon\Carbon::create(2026, 2, 10),
            postingDate: \Carbon\Carbon::create(2026, 2, 10),
            credit: 300.00,
        );

        $this->assertEqualsWithDelta(300.00, $this->netIncomeForMonth('2026-02'), 0.0005);
        $this->assertEqualsWithDelta(0.0, $this->netIncomeForMonth('2026-03'), 0.0005);
    }
}
