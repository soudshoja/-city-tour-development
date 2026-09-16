<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\SystemAccount;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Onboarding\LegacyCoaImporter;
use App\Services\Onboarding\LegacyCsvLoader;
use App\Services\Onboarding\SystemPurposeMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsLegacyCsvFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP1 amendments demanded by the LP2 mapping design
 * (MAPPING-RULES.md §11 decisions O8-refunds, O9-close, O12-purposes).
 */
class LegacyMappingAmendmentsTest extends TestCase
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
            [1, '10904', 2, '10904', 1, 'CUSTOMER CONTROL', '', 'A', 'True', '', '', '', 1025, 1, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '10904', 3, '1090401', 1, 'PARTY-ACC-3', '', 'A', '', '', '', '', 1025, 2, 3, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],

            [1, '200000000', 4, '20000', 1, 'LIABILITIES', '', 'L', 'True', '', '', '', 1025, '', 1, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206010100', 5, '206', 1, 'SUPPLIER CONTROL', '', 'L', 'True', '', '', '', 1025, 4, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206010101', 6, '20601', 1, 'PARTY-ACC-6', '', 'L', '', '', '', '', 1025, 5, 3, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            // O8: a '206%' leaf with NO partner FK -- a named airline payable.
            [1, '206010400', 7, '211141', 1, 'GARUDA INDONESIAN AIRWAYS', '', 'L', '', '', '', '', 1025, 5, 3, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            // O8: the per-branch REFUNDS PAYABLE leaves, group 206010300.
            [1, '206010300', 8, '20204', 1, 'REFUNDS PAYABLE - HEADOFFICE', '', 'L', '', '', '', '', 1025, 5, 3, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206010300', 9, '20206', 1, 'REFUNDS PAYABLE - SHUHADA BRANCH', '', 'L', '', '', '', '', 1025, 5, 3, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],

            [1, '300000000', 10, '30000', 1, 'INCOMES', '', 'I', 'True', '', '', '', 1025, '', 1, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '303010300', 11, '303010300', 1, 'MAIN TRAVEL INCOME', '', 'I', '', '', '', '', 1025, 10, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '400000000', 12, '40000', 1, 'EXPENSES', '', 'E', 'True', '', '', '', 1025, '', 1, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '428000000', 13, '428000000', 1, 'CARD FEES', '', 'E', '', '', '', '', 1025, 12, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '600000000', 14, '60000', 1, 'EQUITY', '', 'L', 'True', '', '', '', 1025, '', 1, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '601010000', 15, '601010000', 1, 'RETAINED EARNINGS', '', 'L', 'True', '', '', '', 1025, 14, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '601010001', 16, '212479', 1, 'Profit & Loss Account 2025', '', 'L', '', '', '', '', 1025, 15, 3, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            // A real control LEAF for PAYABLE_CONTROL to point at. NOTE the
            // shape this fixture is modelling: once O8 keeps the
            // refunds-payable and airline leaves as their own accounts, the
            // legacy '206' SUPPLIER CONTROL node still has children, i.e. it
            // stays a GROUP and cannot itself be a posting target. LP1.2 on
            // real staging must therefore point PAYABLE_CONTROL at a genuine
            // leaf — which is exactly what the LP1.5 gate below enforces.
            [1, '209000000', 17, '209000000', 1, 'TRADE PAYABLES CONTROL', '', 'L', '', '', '', '', 1025, 4, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
        ];

        $this->writeLegacyCsv('tblAccount.csv', $this->accountHeader(), $accounts);
        app(LegacyCsvLoader::class)->load('tblAccount', ['table' => 'stg_account', 'rows' => count($accounts)], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccount.csv');

        // Only acc_id 3 (customer) and 6 (supplier) are real party leaves.
        $partners = [
            [501, 'True', 'False', 'False', 'C-501', '', 'PARTY-501', 1, 1, 1, 3, '', 1025],
            [502, 'False', 'True', 'False', 'S-502', '', 'PARTY-502', 1, 1, 1, '', 6, 1025],
        ];

        $this->writeLegacyCsv('tblPartner.csv', $this->partnerHeader(), $partners);
        app(LegacyCsvLoader::class)->load('tblPartner', ['table' => 'stg_partner', 'rows' => count($partners)], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblPartner.csv');
    }

    /**
     * O8-refunds. MUTATION PROOF: drop the pool_excluded_group_prefixes /
     * partner-FK conditions from LegacyCoaImporter and the two REFUNDS
     * PAYABLE leaves (and the airline payable) vanish into PAYABLE_CONTROL —
     * every assertion below fails, and with them the 75-leaf AP anchor and
     * the two-step refund model MAPPING-RULES.md §2.5 builds CRN on.
     */
    public function test_refunds_payable_and_non_party_206_leaves_are_never_pooled(): void
    {
        $this->loadTree();

        $stats = app(LegacyCoaImporter::class)->import($this->companyId);

        foreach (['20204', '20206', '211141'] as $code) {
            $account = Account::where('company_id', $this->companyId)->where('code', $code)->first();
            $this->assertNotNull($account, "'{$code}' must keep its own account, never fold into PAYABLE_CONTROL");
        }

        foreach ([8, 9, 7] as $accId) {
            $map = DB::connection('legacy_pilot')->table('legacy_acc_map')
                ->where('company_id', $this->companyId)->where('acc_id', $accId)->first();

            $this->assertSame('direct', $map->resolution, "acc_id {$accId}");
            $this->assertNull($map->party_id);
        }

        // Only the ONE real partner-FK payable leaf pooled.
        $this->assertSame(1, $stats['pooled_payable']);
        $this->assertSame(1, $stats['pooled_receivable']);
    }

    /**
     * O9-close. YearEndCloseService selects P&L leaves by root.name matched
     * exactly against 'Income' / 'Expenses'; the legacy roots are INCOMES /
     * EXPENSES, so importing them verbatim makes the LP6 close sweep zero
     * leaves and report net profit 0.000 — a vacuous green.
     *
     * MUTATION PROOF: keep the legacy root names and this test fails.
     */
    public function test_income_and_expense_roots_carry_the_names_the_year_end_close_looks_for(): void
    {
        $this->loadTree();

        app(LegacyCoaImporter::class)->import($this->companyId);

        foreach (['Income', 'Expenses'] as $rootName) {
            $root = Account::where('company_id', $this->companyId)
                ->whereNull('parent_id')
                ->where('name', $rootName)
                ->first();

            $this->assertNotNull($root, "YearEndCloseService looks up the root named exactly '{$rootName}'");
        }

        // ...and the close actually finds leaves under them (a root with no
        // reachable leaf is the same vacuous green by another route).
        $leafRootNames = Account::where('company_id', $this->companyId)
            ->whereDoesntHave('children')
            ->get()
            ->map(fn ($a) => Account::where('id', $a->root_id)->value('name'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->assertContains('Income', $leafRootNames);
        $this->assertContains('Expenses', $leafRootNames);

        // The legacy names are not lost — they are recorded on the map row.
        $incomeRootMap = DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $this->companyId)->where('acc_id', 10)->first();
        $this->assertStringContainsString('canonical root', (string) $incomeRootMap->notes);
    }

    /**
     * O12-purposes. MUTATION PROOF: remove the SystemAccount::updateOrCreate
     * from SystemPurposeMapper and AccountResolver keeps resolving nothing —
     * the first assertion fails; remove the between-phases hook and the pool
     * target silently reverts to the structural fallback, failing the second.
     */
    public function test_a_mapped_purpose_is_written_to_system_accounts_so_the_resolver_can_see_it(): void
    {
        $this->loadTree();

        $parameters = [
            ['AR_CONTROL', '2'],   // -> legacy acc_id 2, CUSTOMER CONTROL
            ['AP_CONTROL', '17'],  // -> legacy acc_id 17, TRADE PAYABLES CONTROL (a LEAF)
            ['RE_ACCOUNT', '15'],  // -> legacy acc_id 15, RETAINED EARNINGS
            // LP1c ruling R2 added FX_GAIN_LOSS to the LP1.5 gate, so it has
            // to resolve here too or (3) below is red for the right reason.
            ['FX_ACCOUNT', '13'],  // -> legacy acc_id 13, CARD FEES (a LEAF)
        ];

        $this->writeLegacyCsv('tblSystemParameters.csv', $this->systemParametersHeader(), $parameters);
        app(LegacyCsvLoader::class)->load('tblSystemParameters', ['table' => 'stg_system_parameters', 'rows' => count($parameters)], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblSystemParameters.csv');

        $map = [
            'RECEIVABLE_CONTROL' => 'AR_CONTROL',
            'PAYABLE_CONTROL' => 'AP_CONTROL',
            'RETAINED_EARNINGS' => 'RE_ACCOUNT',
            'FX_GAIN_LOSS' => 'FX_ACCOUNT',
        ];

        $importer = app(LegacyCoaImporter::class);

        $importer->import($this->companyId, function (int $cid) use ($map) {
            app(SystemPurposeMapper::class)->map($cid, $map);
        });

        // (1) system_accounts, not just map_purpose -- AccountResolver reads
        //     system_accounts and nothing else.
        foreach ($map as $purposeCode => $_) {
            $this->assertDatabaseHas('system_accounts', [
                'company_id' => $this->companyId,
                'purpose_code' => $purposeCode,
            ]);

            $resolved = app(AccountResolver::class)->resolve($purposeCode, $this->companyId);
            $this->assertNotNull($resolved);
        }

        // (2) phase 2 used the PURPOSE, not the structural fallback: the
        //     receivable pool target is the account the purpose resolves to.
        $receivable = app(AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $this->companyId);
        $party = DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $this->companyId)->where('acc_id', 3)->first();

        $this->assertSame($receivable->id, (int) $party->account_id);

        // (3) LP1.5 gate is green.
        $this->assertSame([], $importer->assertPoolPurposesResolve($this->companyId));
    }

    /**
     * MUTATION PROOF for the LP1.5 gate: point a pool purpose at a GROUP and
     * the gate must go red rather than let LP2 start on an unpostable target.
     */
    public function test_the_pool_purpose_gate_refuses_a_non_leaf_target(): void
    {
        $this->loadTree();

        $importer = app(LegacyCoaImporter::class);
        $importer->import($this->companyId);

        // SUPPLIER CONTROL (code '206') still has children here (the
        // refunds-payable and airline leaves), i.e. it is a GROUP.
        $group = Account::where('company_id', $this->companyId)->where('code', '206')->first();
        $this->assertTrue($group->children()->exists());

        SystemAccount::updateOrCreate(
            ['company_id' => $this->companyId, 'purpose_code' => 'PAYABLE_CONTROL', 'service_type' => null],
            ['account_id' => $group->id]
        );

        $failures = $importer->assertPoolPurposesResolve($this->companyId);

        $this->assertNotEmpty($failures);
        $this->assertStringContainsString('PAYABLE_CONTROL', implode(' ', $failures));
    }
}
