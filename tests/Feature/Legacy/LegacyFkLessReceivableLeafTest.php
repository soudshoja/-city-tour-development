<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\Company;
use App\Models\Country;
use App\Models\User;
use App\Services\Onboarding\LegacyCoaImporter;
use App\Services\Onboarding\LegacyCsvLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsLegacyCsvFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP1e, ruling R-arleaves — a receivable-side leaf pools
 * ONLY if a `tblPartner` role FK actually points at it. The exact mirror of
 * O8-refunds on the payable side.
 *
 * Staging run #5: `legacy:import-coa` imported correctly and then exited 1 on
 * `6 pooled party leaf/leaves carry no party_id`. Those six are
 * customer-prefix leaves with no `tblPartner` row anywhere in the export. The
 * old reasoning was that requiring an FK there "would mint six phantom GL
 * leaves"; what it actually produced was six control-account positions that
 * LP4 check 3 cannot decompose per party — a pooled position with no party is
 * a position that can never be compared to the 109-leaf AR anchor. They are
 * ordinary GL leaves. They import `direct`.
 *
 * Fixtures are SYNTHETIC. The SHAPE — a customer-prefix group holding both
 * FK-carrying and FK-less leaves — mirrors the real chart's, not its data.
 */
class LegacyFkLessReceivableLeafTest extends TestCase
{
    use BuildsLegacyCsvFixtures, PreparesLegacyPilotFence, RefreshDatabase;

    /** How many FK-less customer leaves the fixture carries — the staging population's own size. */
    private const FK_LESS_LEAVES = 6;

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

    /**
     * ASSETS
     *   10904 Customer Control      (A, group, AccGroup 10904 — the customer prefix)
     *     1090401                   (A, leaf, customer FK  -> pools)
     *     1302024 … 1406005         (A, leaves, NO partner FK -> direct under R-arleaves)
     * LIABILITIES
     *   2060 CURRENT LIABILITIES    (L, group, AccGroup 206000000 — the payable anchor)
     *     2061 HOTEL PAYABLES       (L, group, AccGroup 206010201)
     *       20611                   (L, leaf, supplier FK -> pools)
     * plus the INCOMES / EXPENSES roots so every canonical root name exists.
     */
    private function loadChartWithFkLessReceivableLeaves(): void
    {
        $rows = [
            [1, '100000000', 1, '10000', 1, 'ASSETS', '', 'A', 1, 0, '', 0, 1, '', 1, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '10904', 4, '10904', 1, 'Customer Control', '', 'A', 1, 0, '', 0, 1, 1, 2, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '10904', 5, '1090401', 1, 'PARTY-ACC-1090401', '', 'A', 0, 0, '', 0, 1, 4, 3, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
        ];

        // The six FK-less customer-prefix leaves. Ids mirror staging's own
        // Acc_IDs so the report reads the same on both sides.
        foreach ([1302024, 1302030, 1401048, 1403007, 1403042, 1406005] as $accId) {
            $rows[] = [1, '10904', $accId, (string) $accId, 1, 'PARTY-ACC-'.$accId, '', 'A', 0, 0, '', 0, 1, 4, 3, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'];
        }

        $rows = array_merge($rows, [
            [1, '200000000', 10, '20000', 1, 'LIABILITIES', '', 'L', 1, 0, '', 0, 1, '', 1, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206000000', 11, '2060', 1, 'CURRENT LIABILITIES', '', 'L', 1, 0, '', 0, 1, 10, 2, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206010201', 12, '2061', 1, 'HOTEL PAYABLES', '', 'L', 1, 0, '', 0, 1, 11, 3, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206010201', 13, '20611', 1, 'PARTY-ACC-20611', '', 'L', 0, 0, '', 0, 1, 12, 4, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '300000000', 20, '30000', 1, 'INCOMES', '', 'I', 1, 0, '', 0, 1, '', 1, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '303010300', 21, '3010', 1, 'Sales Income', '', 'I', 0, 0, '', 0, 1, 20, 2, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '400000000', 22, '40000', 1, 'EXPENSES', '', 'E', 1, 0, '', 0, 1, '', 1, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '428000000', 23, '4010', 1, 'Operating Expenses', '', 'E', 0, 0, '', 0, 1, 22, 2, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
        ]);

        $this->writeLegacyCsv('tblAccount.csv', $this->accountHeader(), $rows);

        $loader = app(LegacyCsvLoader::class);
        $loader->load('tblAccount', ['table' => 'stg_account', 'rows' => count($rows)], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccount.csv');

        // Exactly two partners: one customer (Acc_ID 5), one supplier (13).
        // NONE of the six FK-less leaves is claimed by any partner row.
        $partners = [
            [801, 'True', 'False', 'False', 'C-801', '', 'PARTY-801', 1, 1, 1, 5, '', 1],
            [802, 'False', 'True', 'False', 'S-802', '', 'PARTY-802', 1, 1, 1, '', 13, 1],
        ];

        $this->writeLegacyCsv('tblPartner.csv', $this->partnerHeader(), $partners);
        $loader->load('tblPartner', ['table' => 'stg_partner', 'rows' => count($partners)], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblPartner.csv');
    }

    /** @return array<int, string> Acc_ID => resolution */
    private function resolutions(): array
    {
        return DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $this->companyId)
            ->pluck('resolution', 'acc_id')
            ->map(static fn ($r): string => (string) $r)
            ->all();
    }

    public function test_a_customer_prefix_leaf_with_no_partner_fk_imports_direct(): void
    {
        $this->loadChartWithFkLessReceivableLeaves();

        $stats = app(LegacyCoaImporter::class)->import($this->companyId);

        $this->assertSame(0, $stats['unclassified']);
        $this->assertSame(1, $stats['pooled_receivable'], 'only the leaf a tblPartner CustAccID_FK actually points at pools');
        $this->assertSame(1, $stats['pooled_payable']);

        $resolutions = $this->resolutions();

        foreach ([1302024, 1302030, 1401048, 1403007, 1403042, 1406005] as $accId) {
            $this->assertSame('direct', $resolutions[$accId] ?? null,
                "Acc_ID {$accId} carries no partner FK and must keep its own account.");
        }

        $this->assertSame('pooled_receivable', $resolutions[5]);

        // A `direct` row owns a real account; a pooled one does not.
        $direct = DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $this->companyId)->where('acc_id', 1302024)->first();
        $this->assertNotNull($direct->account_id);
        $this->assertNull($direct->party_id);
    }

    /**
     * The gate that made staging run #5 exit 1. With the six leaves imported
     * `direct`, no pooled leaf is left without a party, so `import-coa`
     * reaches SUCCESS on a chart that used to fail at the very last check.
     */
    public function test_import_coa_exits_zero_on_a_chart_carrying_the_six_fk_less_ar_leaves(): void
    {
        $this->loadChartWithFkLessReceivableLeaves();

        $this->artisan('legacy:import-coa', ['--company' => $this->companyId, '--allow-unmapped' => true])
            ->assertExitCode(0);

        $this->assertSame(
            0,
            DB::connection('legacy_pilot')->table('legacy_acc_map')
                ->where('company_id', $this->companyId)
                ->whereIn('resolution', ['pooled_receivable', 'pooled_payable'])
                ->whereNull('party_id')
                ->count(),
            'no pooled leaf may be left without a party_id — that is the LP4 check-3 gate.'
        );
    }

    /**
     * MUTATION PROOF. Turn the rule off and the six pool again, every one of
     * them without a party_id — which is precisely the failure staging run #5
     * reported, reproduced here as a red test rather than as a paragraph.
     */
    public function test_without_the_rule_the_six_pool_and_carry_no_party_id(): void
    {
        config(['legacy_pilot.receivable_pooling_requires_partner_fk' => false]);

        $this->loadChartWithFkLessReceivableLeaves();

        $stats = app(LegacyCoaImporter::class)->import($this->companyId);

        $this->assertSame(1 + self::FK_LESS_LEAVES, $stats['pooled_receivable']);

        $this->assertSame(
            self::FK_LESS_LEAVES,
            DB::connection('legacy_pilot')->table('legacy_acc_map')
                ->where('company_id', $this->companyId)
                ->where('resolution', 'pooled_receivable')
                ->whereNull('party_id')
                ->count()
        );

        $this->artisan('legacy:import-coa', ['--company' => $this->companyId, '--allow-unmapped' => true])
            ->assertExitCode(1);
    }

    /**
     * The payable side is untouched by this change — O8's own rule still
     * governs it, and a supplier-FK leaf still pools.
     */
    public function test_the_payable_side_is_unchanged(): void
    {
        $this->loadChartWithFkLessReceivableLeaves();

        app(LegacyCoaImporter::class)->import($this->companyId);

        $this->assertSame('pooled_payable', $this->resolutions()[13]);
    }
}
