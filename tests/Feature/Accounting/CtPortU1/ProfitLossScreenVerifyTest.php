<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtPortU1;

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
 * CT port U1 - independent verifier (UNIT1-VERIFY.md).
 *
 * The U1 PR gives City Travelers' P&L screen a `date_from`/`date_to` filter (ported from Akeed
 * LP5a), but did not port the P&L cases of Akeed's ReportControllerDateRangeTest, and no CT test
 * passes `date_from`/`date_to` to the screen. These are those cases (pinned literals over a
 * one-document-per-quarter world), plus what the PR's own surface needs on CT:
 *   - a lone date_from, and reversed bounds (swapped, not answered as an empty period);
 *   - the screen renders over HTTP with CT's INLINE transition banner non-null (engine ON plus a
 *     legacy line), which is CT dev's company 1 today. Nothing else renders that banner non-null.
 */
class ProfitLossScreenVerifyTest extends TestCase
{
    use BuildsParityFixtures;
    use GrantsAccountingModule;
    use RefreshDatabase;

    private int $companyId;

    /** @var array<string, int> */
    private array $accounts = [];

    protected function tearDown(): void
    {
        Company::forgetModuleCache();
        parent::tearDown();
    }

    private function seedWorld(): Company
    {
        $this->companyId = $this->makeParityCompany();
        $company = Company::find($this->companyId);

        $assetsRoot = $this->insertAccount($this->companyId, null, null, '1000', 'Assets', 1, true);
        $incomeRoot = $this->insertAccount($this->companyId, null, null, '3000', 'Income', 1, true);

        $this->accounts['BANK'] = $this->insertAccount($this->companyId, $assetsRoot, $assetsRoot, '1010', 'BANK ACCOUNT', 2, false);
        $incomeSection = $this->insertAccount($this->companyId, $incomeRoot, $incomeRoot, '4100', 'INCOME ON SALES', 2, true);
        $this->accounts['SALES'] = $this->insertAccount($this->companyId, $incomeSection, $incomeRoot, '4101', 'TICKET SALES', 3, false);

        foreach ([['2025-02-10', 100.0], ['2025-05-10', 200.0], ['2025-08-10', 300.0], ['2025-11-10', 400.0]] as [$date, $amount]) {
            $this->postSyntheticDocument($this->companyId, 'INV', 'LEGACY_INV', $date, [
                [$this->accounts['BANK'], $amount, 0.0, null],
                [$this->accounts['SALES'], 0.0, $amount, null],
            ]);
        }

        $this->grantAccountingModule($company);
        Permission::firstOrCreate(['name' => 'view profit loss', 'group' => 'report']);
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        $admin->givePermissionTo('view profit loss');
        $this->actingAs($admin);
        session(['company_id' => $this->companyId]);

        return $company;
    }

    /** @return array<string, mixed> */
    private function profitLossData(array $query): array
    {
        return app(ReportController::class)
            ->profitLoss(Request::create('/reports/profit-loss', 'GET', $query))
            ->getData();
    }

    public function test_a_full_year_date_range_reports_the_whole_year(): void
    {
        $this->seedWorld();
        $data = $this->profitLossData(['date_from' => '2025-01-01', 'date_to' => '2025-12-31']);

        $this->assertEqualsWithDelta(1000.0, $data['totals']['net_income'], 0.0005);
        $this->assertSame('2025-01-01', $data['periodFrom']);
        $this->assertSame('2025-12-31', $data['periodTo']);
        $this->assertTrue($data['usingDateRange']);
    }

    public function test_the_date_range_wins_over_month_and_year(): void
    {
        $this->seedWorld();
        $data = $this->profitLossData(['month' => '2025-02', 'year' => '2025', 'date_from' => '2025-01-01', 'date_to' => '2025-12-31']);

        $this->assertEqualsWithDelta(1000.0, $data['totals']['net_income'], 0.0005);
        $this->assertSame('2025-01-01', $data['periodFrom']);
    }

    public function test_month_and_year_still_work_unchanged_without_a_range(): void
    {
        $this->seedWorld();
        $data = $this->profitLossData(['month' => '2025-05', 'year' => '2025']);

        $this->assertEqualsWithDelta(200.0, $data['totals']['net_income'], 0.0005);
        $this->assertSame('2025-05-01', $data['periodFrom']);
        $this->assertSame('2025-05-31', $data['periodTo']);
        $this->assertFalse($data['usingDateRange']);
    }

    public function test_date_to_alone_widens_to_the_start_of_its_own_year(): void
    {
        $this->seedWorld();
        $data = $this->profitLossData(['date_to' => '2025-06-30']);

        $this->assertSame('2025-01-01', $data['periodFrom']);
        $this->assertSame('2025-06-30', $data['periodTo']);
        $this->assertEqualsWithDelta(300.0, $data['totals']['net_income'], 0.0005);
    }

    public function test_date_from_alone_is_a_one_day_range_on_that_day(): void
    {
        $this->seedWorld();
        $data = $this->profitLossData(['date_from' => '2025-05-10']);

        $this->assertSame('2025-05-10', $data['periodFrom']);
        $this->assertSame('2025-05-10', $data['periodTo']);
        $this->assertEqualsWithDelta(200.0, $data['totals']['net_income'], 0.0005);
    }

    public function test_reversed_bounds_are_swapped_not_answered_as_an_empty_period(): void
    {
        $this->seedWorld();
        $data = $this->profitLossData(['date_from' => '2025-12-31', 'date_to' => '2025-04-01']);

        $this->assertSame('2025-04-01', $data['periodFrom']);
        $this->assertSame('2025-12-31', $data['periodTo']);
        $this->assertEqualsWithDelta(900.0, $data['totals']['net_income'], 0.0005);
    }

    public function test_the_monthly_chart_follows_the_requested_year_and_reconciles(): void
    {
        $this->seedWorld();
        $data = $this->profitLossData(['date_from' => '2025-01-01', 'date_to' => '2025-03-31']);

        $this->assertSame('2025', (string) $data['year']);
        $this->assertEqualsWithDelta(100.0, $data['monthlyProfits'][1], 0.0005);
        $this->assertEqualsWithDelta(1000.0, array_sum($data['monthlyProfits']), 0.0005);
    }

    /**
     * CT dev's company 1 is engine ON with legacy rows still on the ledger, so its P&L renders
     * CT's inline banner. A legacy header (doc_type NULL) with one 77.000 sale: the banner must
     * print and the total must exclude it (engine rows only).
     */
    public function test_the_screen_renders_over_http_with_the_transition_banner_and_excludes_legacy_rows(): void
    {
        $this->seedWorld();

        $legacyTx = (int) DB::table('transactions')->insertGetId([
            'company_id' => $this->companyId, 'entity_id' => $this->companyId, 'entity_type' => 'company',
            'transaction_type' => 'journal', 'amount' => 77, 'description' => 'legacy writer row',
            'reference_type' => 'Invoice', 'transaction_date' => '2025-05-12 00:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([[$this->accounts['BANK'], 77.0, 0.0], [$this->accounts['SALES'], 0.0, 77.0]] as [$acc, $dr, $cr]) {
            DB::table('journal_entries')->insert([
                'company_id' => $this->companyId, 'transaction_id' => $legacyTx, 'account_id' => $acc,
                'debit' => $dr, 'credit' => $cr, 'amount' => $dr ?: $cr, 'exchange_rate' => 1, 'currency' => 'KWD',
                'name' => 'legacy', 'description' => 'legacy line', 'transaction_date' => '2025-05-12 00:00:00',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $response = $this->get(route('reports.profit-loss', ['date_from' => '2025-05-01', 'date_to' => '2025-05-31']));

        $response->assertOk();
        $response->assertSee('Ledger in transition', false);
        $response->assertSee('This profit and loss shows ENGINE rows only.', false);
        $this->assertEqualsWithDelta(200.0, (float) $response->viewData('totals')['net_income'], 0.0005, 'the legacy 77.000 must not reach the engine-ON P&L');
    }
}
