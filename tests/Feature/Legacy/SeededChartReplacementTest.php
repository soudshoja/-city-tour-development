<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\Account;
use App\Models\Agent;
use App\Models\Company;
use App\Models\Country;
use App\Models\User;
use App\Services\Onboarding\SeededDefaultChartGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP1c — coordinator ruling R1: `--replace-seeded`
 * removes the WHOLE seeder-born chart when the chart is provably unused,
 * records every removal, and nulls (never dangles) every dependent FK.
 *
 * The fixture reproduces what the stock DatabaseSeeder actually leaves on a
 * fresh company, INCLUDING the 9 party/gateway accounts that CoaSeeder never
 * declares and that made the old narrow allow-list refuse every staging run
 * (STAGING-README.md run #3) — three supplier leaves sharing the duplicate
 * code 2131 among them.
 */
class SeededChartReplacementTest extends TestCase
{
    use PreparesLegacyPilotFence, RefreshDatabase;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyPilotFence();

        $country = Country::factory()->create();
        $user = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $user->id, 'country_id' => $country->id]);
        $this->companyId = $company->id;

        config(['app.env' => 'staging']);
    }

    /**
     * A CoaSeeder-shaped chart PLUS the 9 accounts the rest of the stock
     * seeder chain mints:
     *   - 1 agent party leaf     (EntitySeeder, code 1361)
     *   - 3 supplier payable leaves, ALL coded 2131
     *     (SupplierCompanyController::activateSupplierProcess() derives a
     *      leaf's code from its PARENT's code, not its siblings)
     *   - 5 payment-gateway leaves (1301/1302/1303 EntitySeeder,
     *     1311/1312 the KNET/uPayment pair)
     */
    private function seedStockLikeChart(): void
    {
        $assets = $this->account('Assets', '1000', 1, null, true);
        $receivable = $this->account('Accounts Receivable', '1100', 2, $assets, true);
        $branch = $this->account('City Travelers HQ', '1351', 3, $receivable, true);
        $gatewayParent = $this->account('Payment Gateway', '1300', 2, $assets, true);

        $liabilities = $this->account('Liabilities', '2000', 1, null, true);
        $hotels = $this->account('Suppliers (Hotels)', '2130', 2, $liabilities, true);

        // A code CoaSeeder / EnsureSystemLeaves genuinely declares, so the
        // report can show recognised and unrecognised rows side by side.
        $this->account('Deferred Revenue', '2215', 2, $liabilities, false);

        // ── the 9 ────────────────────────────────────────────────────────
        $this->account('Soud Shoja', '1361', 4, $branch, false);

        foreach (['Amadeus', 'Magic Holiday', 'TBO Holiday'] as $supplier) {
            $this->account($supplier, '2131', 3, $hotels, false);
        }

        foreach (['Tap' => '1301', 'MyFatoorah' => '1302', 'Hesabe' => '1303', 'Knet' => '1311', 'uPayment' => '1312'] as $name => $code) {
            $this->account($name, $code, 3, $gatewayParent, false);
        }
    }

    private function account(string $name, string $code, int $level, ?Account $parent, bool $isGroup): Account
    {
        return Account::create([
            'name' => $name,
            'code' => $code,
            'level' => $level,
            'parent_id' => $parent?->id,
            'root_id' => $parent?->root_id ?? $parent?->id,
            'company_id' => $this->companyId,
            'account_type' => 'Assets',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'is_group' => $isGroup,
            'disabled' => 0,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
        ]);
    }

    public function test_it_removes_the_whole_freshly_seeded_chart_including_the_nine_extra_accounts(): void
    {
        $this->seedStockLikeChart();
        $total = Account::where('company_id', $this->companyId)->count();

        $removed = app(SeededDefaultChartGuard::class)->replaceSeededChart($this->companyId);

        $this->assertSame($total, $removed);
        $this->assertSame(0, Account::where('company_id', $this->companyId)->count());

        $recorded = DB::connection('legacy_pilot')->table('seeded_chart_removed')
            ->where('company_id', $this->companyId)->get();

        $this->assertCount($total, $recorded);

        // The 9 that the OLD allow-list refused on are all present, and all
        // recorded as NOT recognised defaults — which is exactly why the
        // narrow rule could never clear a fresh staging company.
        foreach (['1361', '2131', '1301', '1302', '1303', '1311', '1312'] as $code) {
            $row = $recorded->firstWhere('code', $code);
            $this->assertNotNull($row, "removed-account record missing for code {$code}");
            $this->assertFalse((bool) $row->recognised_default);
        }

        $this->assertTrue((bool) $recorded->firstWhere('code', '2215')->recognised_default);
    }

    /**
     * The duplicate code is REPORTED, never repaired here: it comes from
     * SupplierCompanyController, not from this pilot.
     */
    public function test_it_reports_the_duplicate_supplier_code_rather_than_fixing_it(): void
    {
        $this->seedStockLikeChart();

        app(SeededDefaultChartGuard::class)->replaceSeededChart($this->companyId);

        $this->assertSame(
            ['2131' => 3],
            app(SeededDefaultChartGuard::class)->duplicateRemovedCodes($this->companyId)
        );
    }

    /**
     * MUTATION PROOF for R1's activity refusal: delete the
     * assertNoLedgerActivity() call (or narrow it back to "no reasons
     * matter") and this test fails — the chart would be deleted out from
     * under a real journal line.
     */
    public function test_it_refuses_when_a_single_journal_entry_exists(): void
    {
        $this->seedStockLikeChart();
        $account = Account::where('company_id', $this->companyId)->where('code', '2215')->first();

        // Inserted through the query builder rather than the model: the
        // ledger's sole-writer ratchet (ArchitectureTest rule 1) reserves
        // JournalEntry writes for App\Services\Accounting, and this fixture
        // only needs the ROW to exist.
        DB::table('journal_entries')->insert([
            'company_id' => $this->companyId,
            'account_id' => $account->id,
            'name' => 'LP1c activity fixture',
            'description' => 'LP1c activity fixture',
            'transaction_date' => now(),
            'exchange_rate' => 1,
            'amount' => 10,
            'debit' => 10,
            'credit' => 0,
            'is_locked' => 0,
        ]);

        try {
            app(SeededDefaultChartGuard::class)->replaceSeededChart($this->companyId);
            $this->fail('replaceSeededChart must refuse while a journal entry exists.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('journal_entries', $e->getMessage());
        }

        $this->assertGreaterThan(0, Account::where('company_id', $this->companyId)->count());
        $this->assertSame(0, DB::connection('legacy_pilot')->table('seeded_chart_removed')->count());
    }

    /**
     * MUTATION PROOF for the second half of R1's refusal: a PAYMENT is not a
     * journal entry, and the pre-LP1c guard checked only journal_entries and
     * transactions. Drop `payments` from
     * config('legacy_pilot.import.activity_tables') and this test fails.
     */
    public function test_it_refuses_when_a_payment_references_an_account_of_the_company(): void
    {
        $this->seedStockLikeChart();
        $account = Account::where('company_id', $this->companyId)->where('code', '2215')->first();

        // company_id deliberately left NULL so the refusal can only come from
        // the account-reference scan, not from the company-scoped shortcut.
        // client_id satisfies the payments owner-XOR trigger; it is not what
        // the assertion turns on.
        $clientId = DB::table('clients')->insertGetId(['first_name' => 'LP1c', 'phone' => '+96500000000']);
        DB::table('payments')->insert(['amount' => 12.500, 'account_id' => $account->id, 'client_id' => $clientId]);

        try {
            app(SeededDefaultChartGuard::class)->replaceSeededChart($this->companyId);
            $this->fail('replaceSeededChart must refuse while a payment references one of the accounts.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('payments.account_id', $e->getMessage());
        }

        $this->assertGreaterThan(0, Account::where('company_id', $this->companyId)->count());
    }

    public function test_a_dependent_foreign_key_is_nulled_and_listed_for_relinking(): void
    {
        $this->seedStockLikeChart();
        $account = Account::where('company_id', $this->companyId)->where('code', '1361')->first();

        $agent = Agent::factory()->create(['account_id' => $account->id]);

        app(SeededDefaultChartGuard::class)->replaceSeededChart($this->companyId);

        $agent->refresh();
        $this->assertNull($agent->account_id, 'a dependent FK must be nulled, never left dangling');

        $row = DB::connection('legacy_pilot')->table('seeded_chart_fk_nulled')
            ->where('company_id', $this->companyId)
            ->where('table_name', 'agents')
            ->where('column_name', 'account_id')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame($agent->id, (int) $row->row_id);
        $this->assertSame($account->id, (int) $row->old_account_id);
        $this->assertSame('1361', $row->old_account_code);
        $this->assertSame('nulled', $row->status);

        // …and it surfaces in the "needs re-linking at go-live" report.
        $pending = app(SeededDefaultChartGuard::class)->pendingRelinks($this->companyId);
        $this->assertNotEmpty($pending);
    }

    /**
     * Re-pointing a nulled FK by NAME is forbidden — the export is
     * pseudonymised — so nothing is re-linked automatically while no
     * dependent row carries a legacy identity. The row must stay 'nulled'
     * and stay reported, never quietly guessed back into place.
     */
    public function test_relink_never_guesses_and_leaves_the_reference_pending(): void
    {
        $this->seedStockLikeChart();
        $account = Account::where('company_id', $this->companyId)->where('code', '1361')->first();
        Agent::factory()->create(['account_id' => $account->id]);

        $guard = app(SeededDefaultChartGuard::class);
        $guard->replaceSeededChart($this->companyId);

        $result = $guard->relinkNulledReferences($this->companyId);

        $this->assertSame(0, $result['relinked']);
        $this->assertSame(1, $result['pending']);
        $this->assertCount(1, $guard->pendingRelinks($this->companyId));
    }

    public function test_an_empty_chart_is_a_no_op_rather_than_an_error(): void
    {
        $this->assertSame(0, app(SeededDefaultChartGuard::class)->replaceSeededChart($this->companyId));
    }
}
