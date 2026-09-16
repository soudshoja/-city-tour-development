<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Onboarding\LegacyCoaImporter;
use App\Services\Onboarding\LegacyCsvLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsLegacyCsvFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP1c — coordinator ruling R2, at the gate.
 *
 * PAYABLE_CONTROL has no legacy parameter and its structural anchor is a
 * GROUP, so the importer mints a synthetic pooled control LEAF under that
 * group and pools every AP party leaf onto it. The LP1.5 gate is fixed at
 * exactly four purposes — RECEIVABLE_CONTROL, PAYABLE_CONTROL,
 * RETAINED_EARNINGS, FX_GAIN_LOSS — and SUSPENSE (whose legacy target does
 * not exist in the export at all) is deliberately NOT among them.
 */
class LegacyControlPurposeGateTest extends TestCase
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

    /**
     * ASSETS / LIABILITIES roots, a customer group with two party leaves and
     * a payable group ('206') with one partner-backed party leaf.
     */
    private function loadTree(): void
    {
        $rows = [
            [1, '100000000', 1, '10000', 1, 'ASSETS', '', 'A', 1, 0, '', 0, 1, '', 1, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '10904', 2, '10904', 1, 'Customer Control', '', 'A', 1, 0, '', 0, 1, 1, 2, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '10904', 3, '1090401', 1, 'PARTY-ACC-3', '', 'A', 0, 0, '', 0, 1, 2, 3, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '200000000', 4, '20000', 1, 'LIABILITIES', '', 'L', 1, 0, '', 0, 1, '', 1, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206010100', 5, '206', 1, 'Supplier Control', '', 'L', 1, 0, '', 0, 1, 4, 2, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206010101', 6, '20601', 1, 'PARTY-ACC-6', '', 'L', 0, 0, '', 0, 1, 5, 3, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206010101', 7, '20602', 1, 'PARTY-ACC-7', '', 'L', 0, 0, '', 0, 1, 5, 3, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
        ];

        $this->writeLegacyCsv('tblAccount.csv', $this->accountHeader(), $rows);

        $loader = app(LegacyCsvLoader::class);
        $loader->load('tblAccount', ['table' => 'stg_account', 'rows' => count($rows)], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccount.csv');

        $partners = [
            [801, 'True', 'False', 'False', 'C-801', '', 'PARTY-801', 1, 1, 1, 3, '', 1],
            [802, 'False', 'True', 'False', 'S-802', '', 'PARTY-802', 1, 1, 1, '', 6, 1],
            [803, 'False', 'True', 'False', 'S-803', '', 'PARTY-803', 1, 1, 1, '', 7, 1],
        ];

        $this->writeLegacyCsv('tblPartner.csv', $this->partnerHeader(), $partners);
        $loader->load('tblPartner', ['table' => 'stg_partner', 'rows' => count($partners)], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblPartner.csv');

        // The parameter table the real export carries. SuspenseControlAccount
        // deliberately points at an Acc_ID that is NOT in this tree -- the
        // real export has exactly that gap (1305003 exists nowhere in the
        // 1,351 staged rows).
        $parameters = [['SuspenseControlAccount', '1305003']];

        $this->writeLegacyCsv('tblSystemParameters.csv', $this->systemParametersHeader(), $parameters);
        $loader->load('tblSystemParameters', ['table' => 'stg_system_parameters', 'rows' => count($parameters)], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblSystemParameters.csv');
    }

    /**
     * MUTATION PROOF for R2's synthetic leaf: delete
     * createSyntheticControlLeaf() (or make resolvePoolTarget() return the
     * group as it did pre-LP1c) and this test fails on all three counts —
     * the pool target would be the group itself, AccountResolver would raise
     * NonLeafAccountException, and PAYABLE_CONTROL would be back in the
     * gate's failure list.
     */
    public function test_a_synthetic_payable_control_leaf_is_minted_under_the_legacy_group_and_used_by_pooled_leaves(): void
    {
        $this->loadTree();

        $importer = app(LegacyCoaImporter::class);
        $stats = $importer->import($this->companyId);

        $group = Account::where('company_id', $this->companyId)->where('code', '206')->first();

        $pool = app(AccountResolver::class)->resolve('PAYABLE_CONTROL', $this->companyId);

        $this->assertSame('Suppliers Control (legacy pooled)', $pool->name);
        $this->assertSame($group->id, $pool->parent_id);
        $this->assertFalse($pool->children()->exists(), 'the pooled control must be a postable LEAF');

        // Every pooled AP party leaf lands on it — not on the group.
        $pooled = DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $this->companyId)
            ->where('resolution', 'pooled_payable')
            ->pluck('account_id')
            ->unique();

        $this->assertCount(1, $pooled);
        $this->assertSame($pool->id, (int) $pooled->first());
        $this->assertSame(2, $stats['pooled_payable']);

        // Recorded as synthetic, so LP4 parity can tell a minted node from an
        // imported one, with a code drawn from the configured range.
        $synthetic = DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $this->companyId)
            ->where('resolution', 'synthetic')
            ->where('account_id', $pool->id)
            ->first();

        $this->assertNotNull($synthetic);
        $this->assertGreaterThanOrEqual((int) config('legacy_pilot.import.synthetic_code_range.start'), (int) $pool->code);
        $this->assertLessThanOrEqual((int) config('legacy_pilot.import.synthetic_code_range.end'), (int) $pool->code);

        $this->assertNotContains(
            'PAYABLE_CONTROL',
            array_map(fn (string $f) => explode(':', $f)[0], $importer->assertPoolPurposesResolve($this->companyId))
        );
    }

    public function test_the_synthetic_code_comes_from_config_not_a_literal(): void
    {
        config(['legacy_pilot.import.synthetic_code_range' => ['start' => 880000, 'end' => 880010]]);

        $this->loadTree();
        app(LegacyCoaImporter::class)->import($this->companyId);

        // Receivable resolves first, so it takes the first free code in the
        // range and payable the next -- both from config, neither a literal.
        $this->assertSame('880000', app(AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $this->companyId)->code);
        $this->assertSame('880001', app(AccountResolver::class)->resolve('PAYABLE_CONTROL', $this->companyId)->code);
    }

    /**
     * MUTATION PROOF for the gate's membership: add a fifth purpose (SUSPENSE
     * is the tempting one) or drop FX_GAIN_LOSS, and this fails. SUSPENSE's
     * legacy target Acc_ID does not exist anywhere in the export, and O6
     * forbids plugging a suspense account, so gating it would make a
     * documented, correct gap a permanent red.
     */
    public function test_the_control_gate_requires_exactly_four_purposes(): void
    {
        $failures = app(LegacyCoaImporter::class)->assertPoolPurposesResolve($this->companyId);

        $purposes = array_map(fn (string $f) => explode(':', $f)[0], $failures);
        sort($purposes);

        $this->assertSame(
            ['FX_GAIN_LOSS', 'PAYABLE_CONTROL', 'RECEIVABLE_CONTROL', 'RETAINED_EARNINGS'],
            $purposes
        );
    }

    /**
     * R2: --allow-unmapped exists precisely to accept a non-control gap like
     * SUSPENSE, whose legacy target (Acc_ID 1305003) is absent from the
     * export. The gap is still REPORTED — it is never silently plugged.
     */
    public function test_an_unmapped_suspense_is_tolerated_with_allow_unmapped_and_still_reported(): void
    {
        $this->loadTree();

        $this->artisan('legacy:import-coa', [
            '--company' => $this->companyId,
            '--allow-unmapped' => true,
            '--purpose-map' => json_encode(['SUSPENSE' => 'SuspenseControlAccount']),
        ])->assertExitCode(0);

        $row = DB::connection('legacy_pilot')->table('map_purpose')
            ->where('company_id', $this->companyId)
            ->where('purpose_code', 'SUSPENSE')
            ->first();

        $this->assertSame('unmapped', $row->status);
        $this->assertNull($row->account_id);
    }
}
