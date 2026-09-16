<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\Company;
use App\Models\Country;
use App\Models\User;
use App\Services\Onboarding\LegacyCsvLoader;
use App\Services\Onboarding\SeededDefaultChartGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\BuildsLegacyCsvFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP1.2/LP1.5 — the gates that decide whether
 * `legacy:import-coa` exits 0.
 *
 * PLAN.md §5.1 R9: every gate in this phase reads OUTPUT, never an exit
 * code, because every seeder in this codebase exits 0 while achieving
 * nothing. That cuts both ways: a command that reports "83 purposes
 * unmapped" and still exits 0 is the same vacuous green from the other
 * side, and CI has no way to see it.
 */
class LegacyImportCoaGatesTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        $this->cleanupLegacyFixtureRoot();

        parent::tearDown();
    }

    private function loadTree(): void
    {
        $accounts = [
            [1, '100000000', 1, '10000', 1, 'ASSETS', '', 'A', 'True', '', '', '', 1025, '', 1, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '109010000', 2, '109010000', 1, 'CASH IN HAND', '', 'A', '', '', '', '', 1025, 1, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '200000000', 3, '20000', 1, 'LIABILITIES', '', 'L', 'True', '', '', '', 1025, '', 1, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206010100', 4, '206', 1, 'SUPPLIER CONTROL', '', 'L', '', '', '', '', 1025, 3, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '300000000', 5, '30000', 1, 'INCOMES', '', 'I', 'True', '', '', '', 1025, '', 1, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '303010300', 6, '303010300', 1, 'MAIN TRAVEL INCOME', '', 'I', '', '', '', '', 1025, 5, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '400000000', 7, '40000', 1, 'EXPENSES', '', 'E', 'True', '', '', '', 1025, '', 1, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '428000000', 8, '428000000', 1, 'CARD FEES', '', 'E', '', '', '', '', 1025, 7, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
        ];

        $this->writeLegacyCsv('tblAccount.csv', $this->accountHeader(), $accounts);
        app(LegacyCsvLoader::class)->load('tblAccount', ['table' => 'stg_account', 'rows' => count($accounts)], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccount.csv');
    }

    /**
     * MUTATION PROOF: revert the exit code to self::SUCCESS on unmapped
     * purposes and this test fails. A purpose that resolves to nothing is a
     * posting leg the engine cannot place; PLAN.md §4 LP1.2 accepts the step
     * only at "0 unexplained skips".
     */
    public function test_unmapped_purposes_make_the_command_exit_non_zero(): void
    {
        $this->loadTree();

        $this->artisan('legacy:import-coa', ['--company' => $this->companyId])
            ->assertExitCode(1);
    }

    public function test_allow_unmapped_is_the_only_way_to_exit_zero_with_a_gap(): void
    {
        $this->loadTree();

        $this->artisan('legacy:import-coa', ['--company' => $this->companyId, '--allow-unmapped' => true])
            ->assertExitCode(0);
    }

    /**
     * MUTATION PROOF for the APP_ENV pin: delete
     * SeededDefaultChartGuard::assertNonProductionEnvironment() and a verb
     * whose whole job is DELETING accounts becomes runnable on production.
     */
    public function test_replace_seeded_refuses_outside_local_testing_and_staging(): void
    {
        config(['app.env' => 'production']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("APP_ENV='production'");

        app(SeededDefaultChartGuard::class)->replaceSeededChart($this->companyId);
    }

    public function test_replace_seeded_is_permitted_under_staging(): void
    {
        config(['app.env' => 'staging']);

        // No accounts exist for this company, so nothing is removed -- the
        // point is only that the environment pin does not refuse.
        $this->assertSame(0, app(SeededDefaultChartGuard::class)->replaceSeededChart($this->companyId));
    }
}
