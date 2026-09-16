<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\User;
use App\Services\Onboarding\LegacyCoaImporter;
use App\Services\Onboarding\LegacyCsvLoader;
use App\Services\Onboarding\SeededDefaultChartGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\BuildsLegacyCsvFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP1d — the three defects staging run #4a exposed.
 *
 *  1. CLASSIFICATION. 274 of 1,351 accounts landed `unclassified`, all of
 *     them payable-side party leaves with one shared cause: PAYABLE_CONTROL
 *     resolved to nothing, because its purpose was unmapped AND the
 *     structural fallback demanded that every pooled leaf share ONE parent.
 *     The fixtures below are shaped like the real buckets — payable party
 *     leaves spread over SEVERAL parent groups, plus partner-FK leaves that
 *     sit under an ASSET group — and are synthetic throughout.
 *
 *  2. DELETE ORDER. --replace-seeded deleted `sortByDesc('level')`, which is
 *     wrong the moment a row's `level` disagrees with its own parent_id
 *     (staging has exactly one such account).
 *
 *  3. AUDIT-TRAIL COHERENCE. The two legacy_pilot audit tables are on a
 *     connection the deletion's transaction cannot cover, so a crashed
 *     attempt left its rows behind.
 */
class LegacyLp1dClassificationTest extends TestCase
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
     * A chart shaped like the real one's payable side: ONE prefix-named group
     * ('206…' at the shallowest level) with SEVERAL sub-groups under it, each
     * carrying its own party leaves — plus one partner-FK leaf parked under an
     * asset group, the shape the 7 bank/receivable PARTY-ACC leaves have.
     *
     *   10000 ASSETS (root, A)
     *     1100 BANK ACCOUNTS      (A, group,  AccGroup 109020000)
     *       1101 PARTY-ACC-1101   (A, leaf, supplier FK — the bucket-B shape)
     *     10904 Customer Control  (A, group,  AccGroup 10904)
     *       1090401 party leaf    (A, leaf, customer FK)
     *   20000 LIABILITIES (root, L)
     *     2060 CURRENT LIABILITIES (L, group, AccGroup 206000000 <- the anchor)
     *       2061 HOTEL PAYABLES    (L, group, AccGroup 206010201)
     *         20611, 20612         (L, leaves, supplier FKs)
     *       2062 VISA PAYABLE      (L, group, AccGroup 206010203)
     *         20621                (L, leaf, supplier FK)
     *   30000 INCOMES / 40000 EXPENSES roots so the canonical names all exist.
     *
     * @param  bool  $secondShallowAnchor  add a SECOND group at the anchor's own
     *                                     AccLevel carrying the same prefix, to prove the
     *                                     importer refuses rather than picking one.
     */
    private function loadMultiParentPayableTree(bool $secondShallowAnchor = false): void
    {
        $rows = [
            [1, '100000000', 1, '10000', 1, 'ASSETS', '', 'A', 1, 0, '', 0, 1, '', 1, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '109020000', 2, '1100', 1, 'BANK ACCOUNTS', '', 'A', 1, 0, '', 0, 1, 1, 2, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '109020000', 3, '1101', 1, 'PARTY-ACC-1101', '', 'A', 0, 0, '', 0, 1, 2, 3, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '10904', 4, '10904', 1, 'Customer Control', '', 'A', 1, 0, '', 0, 1, 1, 2, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '10904', 5, '1090401', 1, 'PARTY-ACC-1090401', '', 'A', 0, 0, '', 0, 1, 4, 3, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],

            [1, '200000000', 10, '20000', 1, 'LIABILITIES', '', 'L', 1, 0, '', 0, 1, '', 1, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206000000', 11, '2060', 1, 'CURRENT LIABILITIES', '', 'L', 1, 0, '', 0, 1, 10, 2, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206010201', 12, '2061', 1, 'HOTEL PAYABLES', '', 'L', 1, 0, '', 0, 1, 11, 3, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206010201', 13, '20611', 1, 'PARTY-ACC-20611', '', 'L', 0, 0, '', 0, 1, 12, 4, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206010201', 14, '20612', 1, 'PARTY-ACC-20612', '', 'L', 0, 0, '', 0, 1, 12, 4, '', 'True', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206010203', 15, '2062', 1, 'VISA PAYABLE', '', 'L', 1, 0, '', 0, 1, 11, 3, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '206010203', 16, '20621', 1, 'PARTY-ACC-20621', '', 'L', 0, 0, '', 0, 1, 15, 4, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],

            [1, '300000000', 20, '30000', 1, 'INCOMES', '', 'I', 1, 0, '', 0, 1, '', 1, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '303010300', 21, '3010', 1, 'Sales Income', '', 'I', 0, 0, '', 0, 1, 20, 2, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '400000000', 22, '40000', 1, 'EXPENSES', '', 'E', 1, 0, '', 0, 1, '', 1, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [1, '428000000', 23, '4010', 1, 'Operating Expenses', '', 'E', 0, 0, '', 0, 1, 22, 2, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
        ];

        if ($secondShallowAnchor) {
            // A second '206%' group at the SAME AccLevel as the real anchor.
            $rows[] = [1, '206900000', 30, '2069', 1, 'OTHER CURRENT LIABILITIES', '', 'L', 0, 0, '', 0, 1, 10, 2, '', 'False', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'];
        }

        $this->writeLegacyCsv('tblAccount.csv', $this->accountHeader(), $rows);

        $loader = app(LegacyCsvLoader::class);
        $loader->load('tblAccount', ['table' => 'stg_account', 'rows' => count($rows)], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccount.csv');

        // Partner role FKs: three payable leaves under two different groups,
        // one customer leaf, and one supplier-role leaf parked under an asset
        // group (the bucket-B shape).
        $partners = [
            [801, 'True', 'False', 'False', 'C-801', '', 'PARTY-801', 1, 1, 1, 5, '', 1],
            [802, 'False', 'True', 'False', 'S-802', '', 'PARTY-802', 1, 1, 1, '', 13, 1],
            [803, 'False', 'True', 'False', 'S-803', '', 'PARTY-803', 1, 1, 1, '', 14, 1],
            [804, 'False', 'True', 'False', 'S-804', '', 'PARTY-804', 1, 1, 1, '', 16, 1],
            [805, 'False', 'True', 'False', 'S-805', '', 'PARTY-805', 1, 1, 1, '', 3, 1],
        ];

        $this->writeLegacyCsv('tblPartner.csv', $this->partnerHeader(), $partners);
        $loader->load('tblPartner', ['table' => 'stg_partner', 'rows' => count($partners)], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblPartner.csv');
    }

    /**
     * THE LP1d FIX, and its own mutation proof.
     *
     * The payable party leaves hang off TWO different parent groups, so the
     * pre-LP1d fallback (`count($distinctParents) === 1`) resolved nothing and
     * every one of them landed `unclassified` — exactly staging run #4a's 274.
     * Restore that rule and this test goes red on the first assertion.
     */
    public function test_payable_leaves_spread_over_several_parents_still_pool_and_leave_nothing_unclassified(): void
    {
        $this->loadMultiParentPayableTree();

        $stats = app(LegacyCoaImporter::class)->import($this->companyId);

        $this->assertSame(0, $stats['unclassified'],
            'LP1d: a payable population spread over several parent groups must still resolve a pool target.');
        // 3 x '206%' party leaves + 1 supplier-role leaf under an asset group.
        $this->assertSame(4, $stats['pooled_payable']);
        $this->assertSame(1, $stats['pooled_receivable']);
        // Neither control purpose has a legacy LEAF on this chart (both
        // prefixes name a GROUP), so R2 mints one pooled control leaf each.
        $this->assertSame(2, $stats['synthetic_controls']);

        $this->assertSame(
            0,
            DB::connection('legacy_pilot')->table('legacy_acc_map')
                ->where('company_id', $this->companyId)->where('resolution', 'unclassified')->count()
        );
    }

    /**
     * The anchor is the SHALLOWEST node carrying the configured group prefix —
     * not a leaf's parent, not the tree root, not a name match.
     */
    public function test_the_minted_payable_control_hangs_under_the_shallowest_prefix_group(): void
    {
        $this->loadMultiParentPayableTree();

        app(LegacyCoaImporter::class)->import($this->companyId);

        $synthetic = DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $this->companyId)
            ->where('resolution', 'synthetic')
            ->where('notes', 'like', '%PAYABLE_CONTROL%')
            ->first();

        $this->assertNotNull($synthetic);

        $account = Account::withoutGlobalScopes()->find($synthetic->account_id);
        $this->assertNotNull($account);

        $parent = Account::withoutGlobalScopes()->find($account->parent_id);
        $this->assertNotNull($parent);
        $this->assertSame('2060', $parent->code,
            'The pooled control leaf must hang under the group the payable prefix itself names (AccGroup 206000000), '
            .'not under one of the sub-groups its leaves happen to sit in.');
    }

    /**
     * MUTATION PROOF for "never guesses": with TWO equally-shallow groups
     * carrying the payable prefix, the importer has no single anchor and must
     * report the leaves as unclassified rather than picking one.
     */
    public function test_two_equally_shallow_prefix_groups_refuse_rather_than_guess(): void
    {
        $this->loadMultiParentPayableTree(secondShallowAnchor: true);

        $stats = app(LegacyCoaImporter::class)->import($this->companyId);

        $this->assertSame(4, $stats['unclassified'],
            'Two equally-shallow prefix anchors is an ambiguity, and an ambiguous pool target is never guessed.');
        $this->assertSame(0, $stats['pooled_payable']);
        // The RECEIVABLE side is unaffected -- its prefix still names exactly
        // one shallowest group -- so exactly one control leaf is still minted.
        $this->assertSame(1, $stats['synthetic_controls']);
    }

    /**
     * The unclassified list the command prints is derived from real rows, with
     * the reason attached — the thing staging run #4a could not show.
     */
    public function test_the_unclassified_report_names_the_rows_and_the_reason(): void
    {
        $this->loadMultiParentPayableTree(secondShallowAnchor: true);

        app(LegacyCoaImporter::class)->import($this->companyId);

        $report = app(LegacyCoaImporter::class)->unclassifiedReport($this->companyId);

        $this->assertSame(4, $report['total']);
        $this->assertSame([4], array_values($report['by_note']));
        $this->assertSame('no pool target resolved for this side', array_key_first($report['by_note']));
        $this->assertCount(4, $report['rows']);
        $this->assertSame('supplier', $report['rows'][0]->party_role);
    }

    /**
     * MUTATION PROOF for the delete order (defect 2). The child's `level`
     * equals its parent's — the staging data defect — so `sortByDesc('level')`
     * has no reason to put the child first and the raw delete would hit
     * accounts_parent_id_foreign. topologicalDeleteOrder() reads parent_id, so
     * the child comes first regardless of what `level` claims.
     */
    public function test_delete_order_is_topological_even_when_levels_lie(): void
    {
        $parent = $this->makeAccount('9000', 'HQ', null, 3);
        $child = $this->makeAccount('9001', 'HQ Child', $parent->id, 3);

        $accounts = Account::withoutGlobalScopes()->whereIn('id', [$parent->id, $child->id])->get();

        $ordered = app(SeededDefaultChartGuard::class)->topologicalDeleteOrder($accounts);
        $ids = array_map(static fn (Account $a): int => (int) $a->id, $ordered);

        $this->assertSame(
            [(int) $child->id, (int) $parent->id],
            $ids,
            'The child must be deleted before its parent even though both rows claim the same level.'
        );
    }

    public function test_replace_seeded_survives_a_level_inconsistent_chart_and_reports_it(): void
    {
        $parent = $this->makeAccount('9000', 'HQ', null, 3);
        $this->makeAccount('9001', 'HQ Child', $parent->id, 3);

        $guard = app(SeededDefaultChartGuard::class);
        $removed = $guard->replaceSeededChart($this->companyId);

        $this->assertSame(2, $removed);
        $this->assertSame(0, Account::withoutGlobalScopes()->where('company_id', $this->companyId)->count());

        $problems = $guard->lastLevelInconsistencies();
        $this->assertNotEmpty($problems, 'A level that contradicts parent_id is reported, never silently absorbed.');
        $this->assertStringContainsString('9001', implode(' ', $problems));

        $run = DB::connection('legacy_pilot')->table('seeded_chart_removal_run')
            ->where('run_key', $guard->lastRunKey())->first();

        $this->assertNotNull($run);
        $this->assertNotNull($run->completed_at, 'A finished attempt records its completion.');
        $this->assertStringContainsString('9001', (string) $run->level_inconsistencies);
    }

    public function test_a_cyclic_parent_chain_refuses_rather_than_deleting_half_a_chart(): void
    {
        $a = $this->makeAccount('9100', 'A', null, 1);
        $b = $this->makeAccount('9101', 'B', $a->id, 2);

        // Close the loop: A's parent becomes B.
        DB::table('accounts')->where('id', $a->id)->update(['parent_id' => $b->id]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/parent_id cycle/');

        app(SeededDefaultChartGuard::class)->replaceSeededChart($this->companyId);
    }

    /**
     * DEFECT 3, half one: the audit rows are written only AFTER the default
     * connection commits, so an attempt that never gets there writes nothing.
     * Before LP1d the crashed attempt's 186 rows stayed behind and the table
     * ended up describing twice as many accounts as were ever removed.
     */
    public function test_a_failed_attempt_leaves_no_audit_rows_at_all(): void
    {
        $a = $this->makeAccount('9100', 'A', null, 1);
        $b = $this->makeAccount('9101', 'B', $a->id, 2);
        DB::table('accounts')->where('id', $a->id)->update(['parent_id' => $b->id]);

        try {
            app(SeededDefaultChartGuard::class)->replaceSeededChart($this->companyId);
            $this->fail('The cyclic chart must refuse.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, DB::connection('legacy_pilot')->table('seeded_chart_removed')->count());
        $this->assertSame(0, DB::connection('legacy_pilot')->table('seeded_chart_removal_run')->count());
    }

    /**
     * DEFECT 3, half two: run_key idempotency. Re-running an attempt under the
     * SAME key replaces that key's rows instead of appending a second copy —
     * which is precisely what staging run #4a's retry did (372 rows for 186
     * accounts).
     */
    public function test_rerunning_under_the_same_run_key_replaces_rather_than_doubles_the_audit_rows(): void
    {
        $runKey = 'lp1d-fixed-key';

        $parent = $this->makeAccount('9000', 'HQ', null, 1);
        $this->makeAccount('9001', 'HQ Child', $parent->id, 2);

        $guard = app(SeededDefaultChartGuard::class);
        $guard->replaceSeededChart($this->companyId, $runKey);

        $this->assertSame(2, DB::connection('legacy_pilot')->table('seeded_chart_removed')->count());

        // A second attempt under the SAME key, on a rebuilt chart.
        $parent2 = $this->makeAccount('9010', 'HQ2', null, 1);
        $this->makeAccount('9011', 'HQ2 Child', $parent2->id, 2);
        $this->makeAccount('9012', 'HQ2 Child B', $parent2->id, 2);

        $guard->replaceSeededChart($this->companyId, $runKey);

        $this->assertSame(3, DB::connection('legacy_pilot')->table('seeded_chart_removed')->count(),
            'The second attempt under the same run_key replaces the first attempt\'s rows, never adds to them.');
        $this->assertSame(1, DB::connection('legacy_pilot')->table('seeded_chart_removal_run')
            ->where('run_key', $runKey)->count());
    }

    public function test_each_attempt_gets_its_own_run_key_and_can_be_purged_on_its_own(): void
    {
        $guard = app(SeededDefaultChartGuard::class);

        $this->makeAccount('9000', 'HQ', null, 1);
        $guard->replaceSeededChart($this->companyId);
        $firstKey = $guard->lastRunKey();

        $this->makeAccount('9010', 'HQ2', null, 1);
        $guard->replaceSeededChart($this->companyId);
        $secondKey = $guard->lastRunKey();

        $this->assertNotSame($firstKey, $secondKey);
        $this->assertSame(2, DB::connection('legacy_pilot')->table('seeded_chart_removed')->count());

        $guard->purgeRemovalAudit($this->companyId, (string) $firstKey);

        $this->assertSame(1, DB::connection('legacy_pilot')->table('seeded_chart_removed')->count());
        $this->assertSame($secondKey, DB::connection('legacy_pilot')->table('seeded_chart_removed')->value('run_key'));
        $this->assertSame([], app(SeededDefaultChartGuard::class)->incompleteRemovalRuns($this->companyId));
    }

    private function makeAccount(string $code, string $name, ?int $parentId, int $level): Account
    {
        $account = new Account([
            'name' => $name,
            'code' => $code,
            'level' => $level,
            'parent_id' => $parentId,
            'root_id' => $parentId,
            'company_id' => $this->companyId,
            'account_type' => 'Assets',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'is_group' => $parentId === null,
            'disabled' => 0,
            'actual_balance' => 0.00,
            'budget_balance' => 0.00,
            'variance' => 0.00,
        ]);
        $account->save();

        return $account;
    }
}
