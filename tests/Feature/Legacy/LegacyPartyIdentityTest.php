<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\User;
use App\Services\Onboarding\LegacyCoaImporter;
use App\Services\Onboarding\LegacyCsvLoader;
use App\Services\Onboarding\LegacyPartyMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsLegacyCsvFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP1.1/LP1.3 — the pooling fold must be REVERSIBLE.
 *
 * PLAN.md §4 LP1.1 rules that party leaves pool onto RECEIVABLE_CONTROL /
 * PAYABLE_CONTROL with `partyAccountRef` on the line, never as per-party
 * GL leaves. That is a many-to-one fold, and LP4 check 3 nonetheless has
 * to reproduce a PER-LEAF position at 2025-12-31 against ar_balances_*
 * (109 customer leaves) and ap_balances_* (75 payable leaves).
 *
 * A fold you cannot invert is data loss dressed up as a design decision.
 * These tests prove the inversion exists and is exact:
 *
 *   1. a party leaf round-trips to (control account id, party id, role);
 *   2. the control account's total equals the SUM of its party leaves, and
 *      decomposing the control's lines by party_id reproduces each leaf's
 *      own balance exactly.
 */
class LegacyPartyIdentityTest extends TestCase
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

    private function loadFixtures(): void
    {
        $accounts = [
            [1, '100000000', 1, '10000', 1, 'ASSETS', '', 'A', 'True', '', '', '', 1025, '', 1, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '10904', 2, '10904', 1, 'CUSTOMER CONTROL', '', 'A', 'True', '', '', '', 1025, 1, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '10904', 3, '1090401', 1, 'PARTY-ACC-3', '', 'A', '', '', '', '', 1025, 2, 3, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '10904', 4, '1090402', 1, 'PARTY-ACC-4', '', 'A', '', '', '', '', 1025, 2, 3, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '200000000', 5, '20000', 1, 'LIABILITIES', '', 'L', 'True', '', '', '', 1025, '', 1, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206', 6, '206', 1, 'SUPPLIER CONTROL', '', 'L', 'True', '', '', '', 1025, 5, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206', 7, '20601', 1, 'PARTY-ACC-7', '', 'L', '', '', '', '', 1025, 6, 3, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '300000000', 8, '30000', 1, 'INCOMES', '', 'I', 'True', '', '', '', 1025, '', 1, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '304000000', 9, '304000000', 1, 'SALES', '', 'I', '', '', '', '', 1025, 8, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '400000000', 10, '40000', 1, 'EXPENSES', '', 'E', 'True', '', '', '', 1025, '', 1, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '428000000', 11, '428000000', 1, 'CARD FEES', '', 'E', '', '', '', '', 1025, 10, 2, '', 'False', '', '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
        ];

        $this->writeLegacyCsv('tblAccount.csv', $this->accountHeader(), $accounts);
        app(LegacyCsvLoader::class)->load('tblAccount', ['table' => 'stg_account', 'rows' => count($accounts)], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccount.csv');

        // Partner 501 is a CUSTOMER (leaf 3); partner 502 is DUAL-ROLE --
        // customer on leaf 4 and supplier on leaf 7 -- one party, two role
        // account FKs (PLAN.md §4 LP1.3).
        $partners = [
            [501, 'True', 'False', 'False', 'C-501', '', 'PARTY-501', 1, 1, 1, 3, '', 1025],
            [502, 'True', 'True', 'False', 'C-502', '', 'PARTY-502', 1, 1, 1, 4, 7, 1025],
        ];

        $this->writeLegacyCsv('tblPartner.csv', $this->partnerHeader(), $partners);
        app(LegacyCsvLoader::class)->load('tblPartner', ['table' => 'stg_partner', 'rows' => count($partners)], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblPartner.csv');
    }

    /**
     * MUTATION PROOF: stop writing legacy_acc_map.party_id (or stop writing
     * map_party) and the pooled leaf becomes anonymous inside its control
     * account — this test fails, and with it LP4 check 3.
     */
    public function test_a_pooled_party_leaf_round_trips_to_a_control_account_and_a_party(): void
    {
        $this->loadFixtures();

        app(LegacyCoaImporter::class)->import($this->companyId);
        $partyStats = app(LegacyPartyMapper::class)->map($this->companyId);

        $this->assertSame(2, $partyStats['parties']);
        $this->assertSame(1, $partyStats['dual_role']);
        $this->assertSame(0, $partyStats['unlinked_leaves']);

        // LP1c ruling R2: the pool targets are the synthetic control LEAVES
        // minted under the legacy 10904 / 206 groups, not the groups
        // themselves (a group is not postable) -- so they are resolved the
        // way the replay will resolve them, through the purpose.
        $customerControl = $this->control('RECEIVABLE_CONTROL');
        $supplierControl = $this->control('PAYABLE_CONTROL');

        // The leaves themselves are NOT accounts (PLAN.md: never per-party GL leaves).
        foreach (['1090401', '1090402', '20601'] as $code) {
            $this->assertNull(Account::where('company_id', $this->companyId)->where('code', $code)->first());
        }

        $expected = [
            3 => ['pooled_receivable', $customerControl->id, 501, 'customer'],
            4 => ['pooled_receivable', $customerControl->id, 502, 'customer'],
            7 => ['pooled_payable', $supplierControl->id, 502, 'supplier'],
        ];

        foreach ($expected as $accId => [$resolution, $accountId, $partyId, $role]) {
            $map = DB::connection('legacy_pilot')->table('legacy_acc_map')
                ->where('company_id', $this->companyId)->where('acc_id', $accId)->first();

            $this->assertSame($resolution, $map->resolution, "acc_id {$accId}");
            $this->assertSame($accountId, (int) $map->account_id, "acc_id {$accId}");
            $this->assertSame($partyId, (int) $map->party_id, "acc_id {$accId} must remember WHICH party it is");
            $this->assertSame($role, $map->party_role, "acc_id {$accId}");
        }

        // The dual-role party is ONE party carrying both role FKs.
        $dual = DB::connection('legacy_pilot')->table('map_party')
            ->where('company_id', $this->companyId)->where('partner_id_fk', 502)->first();

        $this->assertTrue((bool) $dual->is_customer);
        $this->assertTrue((bool) $dual->is_supplier);
        $this->assertSame(4, (int) $dual->cust_acc_id_fk);
        $this->assertSame(7, (int) $dual->supp_acc_id_fk);
        $this->assertSame(1, DB::connection('legacy_pilot')->table('map_party')->where('partner_id_fk', 502)->count());
    }

    /**
     * TB-per-account parity for a CONTROL account = the sum of its party
     * leaves, and the per-leaf decomposition is exact.
     *
     * This is the arithmetic LP4 check 3 depends on, exercised at the
     * mapping layer: take staged legacy lines addressed to the individual
     * party leaves, remap every line through legacy_acc_map exactly as the
     * replay will, and assert (a) they ALL land on the one control account
     * and (b) grouping the control account's lines by party_id reproduces
     * each original leaf's own net position to the fils.
     *
     * MUTATION PROOF: drop party_id from the map and the decomposition
     * collapses into a single anonymous bucket — assertion (b) fails.
     */
    public function test_the_control_accounts_total_equals_the_sum_of_its_party_leaves(): void
    {
        $this->loadFixtures();

        app(LegacyCoaImporter::class)->import($this->companyId);
        app(LegacyPartyMapper::class)->map($this->companyId);

        // Staged legacy lines, per party leaf (Debit/Credit in KWD, 3dp).
        $lines = [
            // acc_id => [[debit, credit], ...]
            3 => [[1250.750, 0], [0, 300.250]],
            4 => [[9800.125, 0]],
            7 => [[0, 4500.500], [120.000, 0]],
        ];

        $perLeafNet = [];
        $remapped = [];

        foreach ($lines as $accId => $rows) {
            $map = DB::connection('legacy_pilot')->table('legacy_acc_map')
                ->where('company_id', $this->companyId)->where('acc_id', $accId)->first();

            foreach ($rows as [$debit, $credit]) {
                $perLeafNet[$accId] = round(($perLeafNet[$accId] ?? 0) + $debit - $credit, 3);

                // Exactly what the LP3 replay does: the line's account
                // becomes the mapped control account, and the party rides
                // along as partyAccountRef.
                $remapped[] = [
                    'account_id' => (int) $map->account_id,
                    'party_id' => (int) $map->party_id,
                    'net' => round($debit - $credit, 3),
                ];
            }
        }

        $customerControl = $this->control('RECEIVABLE_CONTROL');
        $supplierControl = $this->control('PAYABLE_CONTROL');

        // (a) every party line landed on exactly one of the two controls.
        $this->assertSame(
            [$customerControl->id, $supplierControl->id],
            collect($remapped)->pluck('account_id')->unique()->sort()->values()->all()
        );

        // (a) the control's total is the sum of the leaves that pooled onto it.
        $customerTotal = round(collect($remapped)->where('account_id', $customerControl->id)->sum('net'), 3);
        $supplierTotal = round(collect($remapped)->where('account_id', $supplierControl->id)->sum('net'), 3);

        $this->assertSame(round($perLeafNet[3] + $perLeafNet[4], 3), $customerTotal);
        $this->assertSame(round($perLeafNet[7], 3), $supplierTotal);

        // (b) decomposing the control back out by party reproduces each
        // leaf's own position exactly -- the fold is invertible.
        $byParty = collect($remapped)->groupBy('party_id')->map(fn ($g) => round($g->sum('net'), 3));

        $this->assertSame($perLeafNet[3], $byParty[501]);
        // Party 502 is dual-role: its customer leaf and its supplier leaf
        // are DIFFERENT positions on DIFFERENT controls, and stay separable
        // by (account_id, party_id) -- never merged into one number.
        $byPartyAndControl = collect($remapped)
            ->groupBy(fn ($r) => $r['account_id'].':'.$r['party_id'])
            ->map(fn ($g) => round($g->sum('net'), 3));

        $this->assertSame($perLeafNet[4], $byPartyAndControl[$customerControl->id.':502']);
        $this->assertSame($perLeafNet[7], $byPartyAndControl[$supplierControl->id.':502']);
    }

    /**
     * The postable control leaf a purpose resolves to (R2: minted under the
     * legacy group when the legacy chart offers no leaf of its own).
     */
    private function control(string $purposeCode): Account
    {
        return app(\App\Services\Accounting\AccountResolver::class)->resolve($purposeCode, $this->companyId);
    }
}
