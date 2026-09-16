<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\User;
use App\Services\Onboarding\LegacyCoaImporter;
use App\Services\Onboarding\LegacyCsvLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLegacyCsvFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP1.1 -- LegacyCoaImporter.
 */
class LegacyCoaImporterTest extends TestCase
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
     * A minimal but structurally realistic tree. Root AccCodes are the real
     * export's (10000/20000/30000/40000) because
     * config('legacy_pilot.root_canonical_names') is keyed on them -- an
     * unmapped root is refused, never guessed.
     *
     *   10000 ASSETS (A, root)
     *     1100 Cash In Hand (A)
     *     10904 Customer Control (A, GROUP -- shares the customer prefix with its own leaves)
     *       1090401 PARTY-ACC-4 (A, leaf, customer)
     *       1090402 PARTY-ACC-5 (A, leaf, customer)
     *   2000 Liabilities (L, root)
     *     206 Supplier Control (L, GROUP)
     *       20601 PARTY-ACC-8 (L, leaf, payable)
     *     3401 "Profit & Loss Account 2018" (L, leaf -- yearly RE fold target)
     *   4000 Income (I, root)
     *     4100 Sales Income (I)
     *   5000 Expenses (E, root)
     *     5100 Operating Expenses (E, AccTransType='bank' -- the dirty-flag trap)
     */
    private function loadMinimalTree(): void
    {
        $rows = [
            [1, '100000000', 1, '10000', 1, 'ASSETS', '', 'A', 1, 0, '', 0, 1, '', 1, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '109010000', 2, '1100', 1, 'Cash In Hand', '', 'A', 0, 0, '', 0, 1, 1, 2, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '10904', 3, '10904', 1, 'Customer Control', '', 'A', 1, 0, 'bank', 0, 1, 1, 2, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '10904', 4, '1090401', 1, 'PARTY-ACC-4', '', 'A', 0, 0, '', 0, 1, 3, 3, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '10904', 5, '1090402', 1, 'PARTY-ACC-5', '', 'A', 0, 0, '', 0, 1, 3, 3, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '200000000', 6, '20000', 1, 'LIABILITIES', '', 'L', 1, 0, '', 0, 1, '', 1, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206010100', 7, '206', 1, 'Supplier Control', '', 'L', 1, 0, '', 0, 1, 6, 2, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206010101', 8, '20601', 1, 'PARTY-ACC-8', '', 'L', 0, 0, '', 0, 1, 7, 3, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '601010000', 9, '3400', 1, 'Retained Earnings', '', 'L', 0, 0, '', 0, 1, 6, 2, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '601010001', 10, '3401', 1, 'Profit & Loss Account 2018', '', 'L', 0, 0, '', 0, 1, 6, 2, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '300000000', 11, '30000', 1, 'INCOMES', '', 'I', 1, 0, '', 0, 1, '', 1, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '303010300', 12, '4100', 1, 'Sales Income', '', 'I', 0, 0, '', 0, 1, 11, 2, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '400000000', 13, '40000', 1, 'EXPENSES', '', 'E', 1, 0, '', 0, 1, '', 1, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '428000000', 14, '5100', 1, 'Operating Expenses', '', 'E', 0, 0, 'bank', 0, 1, 13, 2, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
        ];

        $this->writeLegacyCsv('tblAccount.csv', $this->accountHeader(), $rows);

        $loader = app(LegacyCsvLoader::class);
        $loader->load('tblAccount', ['table' => 'stg_account', 'rows' => count($rows)], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccount.csv');

        // O8-refunds: a '206%' leaf pools ONLY if a tblPartner role FK points
        // at it, so the payable party leaf needs its partner row.
        //
        // LP1e (R-arleaves) put the SAME requirement on the receivable side,
        // so BOTH customer leaves need one too — a customer-prefix leaf with
        // no partner FK now imports `direct` (see
        // Tests\Feature\Legacy\LegacyFkLessReceivableLeafTest, which is where
        // that case belongs). This fixture is about pooling, so both leaves
        // are genuine parties.
        $partners = [
            [701, 'True', 'False', 'False', 'C-701', '', 'PARTY-701', 1, 1, 1, 4, '', 1],
            [702, 'False', 'True', 'False', 'S-702', '', 'PARTY-702', 1, 1, 1, '', 8, 1],
            [703, 'True', 'False', 'False', 'C-703', '', 'PARTY-703', 1, 1, 1, 5, '', 1],
        ];

        $this->writeLegacyCsv('tblPartner.csv', $this->partnerHeader(), $partners);
        $loader->load('tblPartner', ['table' => 'stg_partner', 'rows' => count($partners)], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblPartner.csv');
    }

    public function test_every_ordinary_leaf_and_group_becomes_exactly_one_account(): void
    {
        $this->loadMinimalTree();

        $stats = app(LegacyCoaImporter::class)->import($this->companyId);

        // 1000,1100,10904(group),2000,206(group),3400,4000,4100,5000,5100 = 10 direct accounts.
        $this->assertSame(10, $stats['created']);
        // The remaining 4 legacy rows (4,5,8,10) are party/RE leaves, folded
        // in the tests below rather than becoming their own Account rows.
        $this->assertSame(4, $stats['pooled_receivable'] + $stats['pooled_payable'] + $stats['folded_retained_earnings'] + $stats['unclassified']);
    }

    /**
     * MUTATION PROOF: classification is by COA position (AccGroup path),
     * never by the dirty AccTransType flag. Account 14 (5100, Expenses) is
     * deliberately flagged AccTransType='bank' but sits under 'Expenses'
     * (5xxx) -- it MUST classify as Expense. If the importer were changed
     * to branch on acctranstype, this account would misclassify as an
     * Asset/Bank account and this assertion would fail.
     */
    public function test_classification_ignores_the_dirty_acctranstype_flag(): void
    {
        $this->loadMinimalTree();

        app(LegacyCoaImporter::class)->import($this->companyId);

        $account = Account::where('company_id', $this->companyId)->where('code', '5100')->first();

        $this->assertNotNull($account);
        $this->assertSame('Expenses', $account->account_type);
    }

    public function test_customer_leaves_pool_onto_a_shared_target_never_as_their_own_gl_leaf(): void
    {
        $this->loadMinimalTree();

        app(LegacyCoaImporter::class)->import($this->companyId);

        // Leaves 4 and 5 (customer group) must NOT become their own Account rows.
        $this->assertNull(Account::where('company_id', $this->companyId)->where('code', '1090401')->first());
        $this->assertNull(Account::where('company_id', $this->companyId)->where('code', '1090402')->first());

        $map4 = \DB::connection('legacy_pilot')->table('legacy_acc_map')->where('company_id', $this->companyId)->where('acc_id', 4)->first();
        $map5 = \DB::connection('legacy_pilot')->table('legacy_acc_map')->where('company_id', $this->companyId)->where('acc_id', 5)->first();

        $this->assertSame('pooled_receivable', $map4->resolution);
        $this->assertSame('pooled_receivable', $map5->resolution);
        // Both leaves share the SAME parent (10904) -> the structural
        // fallback resolves them to the SAME pool target.
        $this->assertSame($map4->account_id, $map5->account_id);
        $this->assertNotNull($map4->account_id);
    }

    /**
     * LP1c ruling R2 changed this outcome deliberately. The structural
     * fallback still finds the legacy AP group ('206') as the anchor, but a
     * GROUP is not postable, so the importer now mints a synthetic pooled
     * control LEAF UNDER it and pools the AP party leaf onto that leaf.
     * Pre-R2 this assertion read `assertSame($supplierControl->id, ...)` —
     * i.e. it asserted that every supplier line pooled onto an unpostable
     * node, which is exactly the defect R2 exists to fix.
     */
    public function test_payable_leaf_pools_onto_a_synthetic_control_leaf_under_the_legacy_ap_group(): void
    {
        $this->loadMinimalTree();

        $stats = app(LegacyCoaImporter::class)->import($this->companyId);

        $map8 = \DB::connection('legacy_pilot')->table('legacy_acc_map')->where('company_id', $this->companyId)->where('acc_id', 8)->first();

        $this->assertSame('pooled_payable', $map8->resolution);
        $this->assertNotNull($map8->account_id);

        $supplierControl = Account::where('company_id', $this->companyId)->where('code', '206')->first();
        $this->assertNotSame($supplierControl->id, $map8->account_id, 'a pooled AP leaf must never land on the group itself');

        $pool = Account::find($map8->account_id);
        $this->assertSame('Suppliers Control (legacy pooled)', $pool->name);
        $this->assertSame($supplierControl->id, $pool->parent_id);
        $this->assertFalse((bool) $pool->is_group);
        // Two: this fixture maps NEITHER control purpose, so the receivable
        // side falls back to its own legacy group (10904) and mints its
        // mirror leaf as well. On the real export RECEIVABLE_CONTROL's
        // parameter names a real leaf (2520004), so only the payable side
        // mints.
        $this->assertSame(2, $stats['synthetic_controls']);
    }

    public function test_yearly_profit_and_loss_leaves_fold_to_retained_earnings_when_resolvable(): void
    {
        $this->loadMinimalTree();

        app(LegacyCoaImporter::class)->import($this->companyId);

        $map10 = \DB::connection('legacy_pilot')->table('legacy_acc_map')->where('company_id', $this->companyId)->where('acc_id', 10)->first();

        $this->assertSame('folded_retained_earnings', $map10->resolution);
    }

    public function test_coa_tree_integrity_every_childs_parent_exists_and_levels_are_consistent(): void
    {
        $this->loadMinimalTree();

        app(LegacyCoaImporter::class)->import($this->companyId);

        $accounts = Account::where('company_id', $this->companyId)->get()->keyBy('id');

        foreach ($accounts as $account) {
            if ($account->parent_id !== null) {
                $this->assertTrue($accounts->has($account->parent_id), "parent {$account->parent_id} missing for account {$account->id}");
                $parent = $accounts->get($account->parent_id);
                $this->assertSame($parent->level + 1, $account->level);
            }
        }
    }

    public function test_it_never_writes_actual_balance_to_anything_other_than_zero(): void
    {
        $this->loadMinimalTree();

        app(LegacyCoaImporter::class)->import($this->companyId);

        $nonZero = Account::where('company_id', $this->companyId)->where('actual_balance', '!=', 0)->count();
        $this->assertSame(0, $nonZero);
    }

    public function test_it_refuses_to_run_when_staging_is_empty(): void
    {
        $this->expectException(\RuntimeException::class);

        app(LegacyCoaImporter::class)->import($this->companyId);
    }
}
