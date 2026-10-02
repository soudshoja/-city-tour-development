<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Http\Controllers\Accounting\BalanceSheetController;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\BalanceSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsParityFixtures;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\TestCase;

/**
 * LP6a (.planning/phases/legacy-ledger-pilot/PLAN.md): BalanceSheetService must produce
 * Assets = Liabilities + Equity EXACTLY, with the not-yet-closed net profit injected under Equity
 * — the same shape as the real Akeed 2 pilot ledger, where 2025 has not been closed by
 * YearEndCloseService yet (LP5A-PL-SCREEN-2026-09-07.md). Since XBRL-X1 that line is the cumulative
 * Income/Expenses balance, not a calendar-year P&L; the unclosed-prior-year, gap-year and YEC cases
 * live in {@see BalanceSheetUnclosedYearTest}.
 */
class BalanceSheetServiceTest extends TestCase
{
    use GrantsAccountingModule;
    use BuildsParityFixtures;
    use RefreshDatabase;

    private function seedWorld(): int
    {
        $companyId = $this->makeParityCompany();
        $accounts = $this->seedParityChart($companyId);

        // Opening position (a prior, already-closed year): balanced across A/L only.
        $this->postSyntheticDocument($companyId, 'OJV', 'LEGACY_OJV', '2024-01-01', [
            [$accounts['BANK'], 1000.000, 0.0, null],
            [$accounts['AP_CONTROL'], 0.0, 1000.000, null],
        ]);

        // Current-year trading (2025, not yet closed): a sale on credit and its collection.
        $this->postSyntheticDocument($companyId, 'INV', 'LEGACY_INV', '2025-03-01', [
            [$accounts['AR_CONTROL'], 400.000, 0.0, null],
            [$accounts['SALES'], 0.0, 400.000, null],
        ]);
        $this->postSyntheticDocument($companyId, 'RCT', 'LEGACY_RCT', '2025-04-01', [
            [$accounts['BANK'], 250.000, 0.0, null],
            [$accounts['AR_CONTROL'], 0.0, 250.000, null],
        ]);

        return $companyId;
    }

    public function test_assets_equal_liabilities_plus_equity_with_current_period_profit(): void
    {
        $companyId = $this->seedWorld();

        $service = new BalanceSheetService;
        $balanceSheet = $service->generate($companyId, Carbon::parse('2025-12-31'));

        // BANK: 1000 (opening) + 250 (collection) = 1250.000 Dr
        // AR_CONTROL: 400 - 250 = 150.000 Dr
        // Assets total = 1400.000
        $this->assertEqualsWithDelta(1400.000, $balanceSheet['totals']['assets'], 0.0005);

        // AP_CONTROL: 1000.000 Cr — untouched by the 2025 activity.
        $this->assertEqualsWithDelta(1000.000, $balanceSheet['totals']['liabilities'], 0.0005);

        // Current period (2025) net income = 400 (Sales, credit-normal) = +400.000, and there is
        // no other real Equity leaf in this fixture, so Equity total = 400.000.
        $this->assertEqualsWithDelta(400.000, $balanceSheet['net_profit'], 0.0005);
        $this->assertEqualsWithDelta(400.000, $balanceSheet['totals']['equity'], 0.0005);

        // 1000 (Liabilities) + 400 (Equity) = 1400 = Assets.
        $this->assertEqualsWithDelta(0.0, $balanceSheet['totals']['difference'], 0.0005);
        $this->assertTrue($balanceSheet['totals']['is_balanced']);
    }

    /**
     * Mutation-style structural check: Income/Expenses leaves must never appear as their own
     * balance-sheet line items — only the single synthetic "Current Period Profit" Equity line
     * represents them. This is what LP5a's P&L bug (classifying by report_type/level rather than
     * positional root) would have broken had it leaked into this screen too.
     */
    public function test_income_and_expense_leaves_are_not_listed_as_balance_sheet_accounts(): void
    {
        $companyId = $this->seedWorld();

        $service = new BalanceSheetService;
        $balanceSheet = $service->generate($companyId, Carbon::parse('2025-12-31'));

        // XBRL-X1 (verifier m-3): this used to compare against the string 'SALES', but the SALES
        // leaf's CODE is '3001' ('SALES' is only the fixture's array key), so it could never fail.
        // It now compares account ids, and first proves the leaf has a balance worth listing.
        $salesId = $this->parityAccounts['SALES'];
        $this->assertEqualsWithDelta(400.0, $balanceSheet['net_profit'], 0.0005, 'fixture: the SALES leaf carries 400.000');

        $listedIds = [];

        foreach (['Assets', 'Liabilities', 'Equity'] as $rootName) {
            foreach ($balanceSheet['sections'][$rootName]['groups'] as $group) {
                foreach ($group['accounts'] as $account) {
                    $listedIds[] = $account->id;
                }
            }
        }

        $this->assertNotContains($salesId, $listedIds, 'The Income leaf must never appear as a balance-sheet line item.');
        $this->assertNotEmpty(array_filter($listedIds), 'fixture: real balance-sheet accounts are listed');
    }

    private function grantPermission(int $companyId, string $permission): User
    {
        Permission::firstOrCreate(['name' => $permission, 'group' => 'report']);
        $user = User::factory()->create(['role_id' => Role::ADMIN]);
        $user->givePermissionTo($permission);
        $this->actingAs($user);
        session(['company_id' => $companyId]);

        return $user;
    }

    public function test_view_balance_sheet_route_is_denied_without_the_permission(): void
    {
        $companyId = $this->seedWorld();

        $user = User::factory()->create(['role_id' => Role::ADMIN]);
        $this->actingAs($user);
        session(['company_id' => $companyId]);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        // CT port (U1): City Travelers serves the balance sheet from its own
        // Accounting\BalanceSheetController (CT-A6-4), not ReportController::balanceSheet().
        app(BalanceSheetController::class)->show(Request::create('/accounting/reports/balance-sheet'));
    }

    public function test_view_balance_sheet_route_succeeds_with_the_permission(): void
    {
        $companyId = $this->seedWorld();
        $user = $this->grantPermission($companyId, 'view balance sheet');

        // A real HTTP round trip — the view renders x-app-layout, which needs the auth/session
        // middleware pipeline a bare Request::create()/controller call bypasses entirely.
        // CT port (U1): CT's route is accounting.reports.balance-sheet behind `module:accounting`.
        $this->grantAccountingModule(Company::findOrFail($companyId));
        $response = $this->actingAs($user)->withSession(['company_id' => $companyId])->get(
            route('accounting.reports.balance-sheet', ['as_of' => '2025-12-31'])
        );

        $response->assertOk();
    }
}
