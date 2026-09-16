<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Onboarding\LegacyCsvLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLegacyCsvFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP1.1 pre-flight — "Akeed 2" staging is NOT empty
 * (LP0 fact: the stock DatabaseSeeder already ran CoaSeeder + a default
 * chart). legacy:import-coa must refuse, or replace ONLY the recognised
 * seeder-created default chart, per SeededDefaultChartGuard's contract.
 */
class LegacyImportCoaCommandTest extends TestCase
{
    use BuildsLegacyCsvFixtures, PreparesLegacyPilotFence, RefreshDatabase;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyPilotFence();
        $this->makeLegacyFixtureRoot();

        $country = Country::factory()->create();
        $user = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $user->id, 'country_id' => $country->id]);
        $this->companyId = $company->id;

        config(['legacy_pilot.default_company_id' => $this->companyId]);
    }

    protected function tearDown(): void
    {
        $this->cleanupLegacyFixtureRoot();

        parent::tearDown();
    }

    private function loadTinyTree(): void
    {
        $rows = [
            [1, '100000000', 1, '10000', 1, 'ASSETS', '', 'A', 0, 0, '', 0, 1, '', 1, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '200000000', 2, '20000', 1, 'LIABILITIES', '', 'L', 0, 0, '', 0, 1, '', 1, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
        ];

        $this->writeLegacyCsv('tblAccount.csv', $this->accountHeader(), $rows);

        app(LegacyCsvLoader::class)->load('tblAccount', ['table' => 'stg_account', 'rows' => count($rows)], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccount.csv');
    }

    /** Simulates the stock CoaSeeder default chart: one account with a code the seeder genuinely declares. */
    private function seedDefaultChartAccount(): Account
    {
        return Account::create([
            'name' => 'Assets',
            'code' => '1000',
            'level' => 1,
            'company_id' => $this->companyId,
            'account_type' => 'Assets',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'is_group' => true,
            'disabled' => 0,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
        ]);
    }

    public function test_it_refuses_on_a_non_empty_chart_without_replace_seeded(): void
    {
        $this->seedDefaultChartAccount();
        $this->loadTinyTree();

        $this->artisan('legacy:import-coa')->assertExitCode(1);

        // Nothing was imported.
        $this->assertSame(1, Account::where('company_id', $this->companyId)->count());
    }

    public function test_replace_seeded_removes_the_recognised_default_and_imports_the_legacy_chart(): void
    {
        $this->seedDefaultChartAccount();
        $this->loadTinyTree();

        // --allow-unmapped: this fixture configures no tblSystemParameters
        // map, so LP1.2 legitimately reports every purpose unmapped and the
        // command exits 1 without it (see LegacyImportCoaGatesTest).
        $this->artisan('legacy:import-coa', ['--replace-seeded' => true, '--allow-unmapped' => true])->assertExitCode(0);

        // The seeder default (code 1000, no legacy AccName 'Assets' collision aside)
        // is gone and replaced by the legacy-imported '10000'/'20000' pair.
        $this->assertSame(2, Account::where('company_id', $this->companyId)->count());
        $this->assertNotNull(Account::where('company_id', $this->companyId)->where('code', '20000')->first());
    }

    /**
     * MUTATION PROOF: removing the ledger-activity check from
     * SeededDefaultChartGuard::replaceSeededChart() (i.e. always allowing a
     * replace) makes this test fail, because a journal entry exists and the
     * command must still refuse.
     */
    public function test_replace_seeded_refuses_when_ledger_activity_exists(): void
    {
        $account = $this->seedDefaultChartAccount();
        $this->loadTinyTree();

        JournalEntry::create([
            'transaction_id' => null,
            'company_id' => $this->companyId,
            'account_id' => $account->id,
            'name' => 'test entry',
            'transaction_date' => now(),
            'posting_date' => now(),
            'description' => 'test',
            'debit' => 10,
            'credit' => 0,
        ]);

        $this->artisan('legacy:import-coa', ['--replace-seeded' => true])->assertExitCode(1);

        $this->assertSame(1, Account::where('company_id', $this->companyId)->count());
    }

    /**
     * LP1c ruling R1 REPLACED this behaviour deliberately. Pre-R1 an account
     * the guard's narrow allow-list could not name refused the whole
     * replace — which is what blocked every staging run, because the stock
     * seeder chain mints 9 accounts CoaSeeder never declares. An unused
     * chart is now removed whole; the row is recorded, not preserved.
     */
    public function test_replace_seeded_removes_an_unrecognised_account_on_an_unused_chart_and_records_it(): void
    {
        Account::create([
            'name' => 'Some Hand Entered Account',
            'code' => 'CUSTOM-999',
            'level' => 1,
            'company_id' => $this->companyId,
            'account_type' => 'Assets',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'is_group' => false,
            'disabled' => 0,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
        ]);
        $this->loadTinyTree();

        $this->artisan('legacy:import-coa', ['--replace-seeded' => true, '--allow-unmapped' => true])->assertExitCode(0);

        $this->assertNull(Account::where('company_id', $this->companyId)->where('code', 'CUSTOM-999')->first());

        $record = \Illuminate\Support\Facades\DB::connection('legacy_pilot')->table('seeded_chart_removed')
            ->where('company_id', $this->companyId)
            ->where('code', 'CUSTOM-999')
            ->first();

        $this->assertNotNull($record, 'a removed account is never deleted without an audit record');
        $this->assertFalse((bool) $record->recognised_default);
    }

    public function test_it_imports_directly_on_an_empty_chart(): void
    {
        $this->loadTinyTree();

        $this->artisan('legacy:import-coa', ['--allow-unmapped' => true])->assertExitCode(0);

        $this->assertSame(2, Account::where('company_id', $this->companyId)->count());
    }
}
