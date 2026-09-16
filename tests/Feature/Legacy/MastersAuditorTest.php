<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Services\Onboarding\LegacyCsvLoader;
use App\Services\Onboarding\MastersAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsLegacyCsvFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP0.4/LP1.4 — MastersAuditor / legacy:audit-masters.
 */
class MastersAuditorTest extends TestCase
{
    use BuildsLegacyCsvFixtures, PreparesLegacyPilotFence, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyPilotFence();
        $this->makeLegacyFixtureRoot();
    }

    protected function tearDown(): void
    {
        $this->cleanupLegacyFixtureRoot();

        parent::tearDown();
    }

    private function loadCurrencyAndDetail(array $currencyRows, array $accDetailRows): void
    {
        $this->writeLegacyCsv('tblCurrency.csv', $this->currencyHeader(), $currencyRows);
        app(LegacyCsvLoader::class)->load('tblCurrency', ['table' => 'stg_currency', 'rows' => count($currencyRows)], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblCurrency.csv');

        $this->writeLegacyCsv('tblAccDetail.csv', $this->accDetailHeader(), $accDetailRows);
        app(LegacyCsvLoader::class)->load('tblAccDetail', ['table' => 'stg_acc_detail', 'rows' => count($accDetailRows)], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccDetail.csv');
    }

    public function test_poison_currency_rows_are_flagged(): void
    {
        $this->loadCurrencyAndDetail(
            [
                [1, 1, 'KWD', 'Kuwaiti Dinar', 'Fils', 1, 3, '2020-01-01', '', 1, '2020-01-01', 1],
                [2, 2, 'XYZ', 'Junk', '', 2500, 3, '2020-01-01', '', 1, '2020-01-01', 1],
            ],
            []
        );

        $result = app(MastersAuditor::class)->run();

        $this->assertSame(1, $result['currency_poison']['detail']['poison_count']);
        $this->assertSame('pass', $result['currency_poison']['status']); // no in-window hits yet
    }

    /**
     * MUTATION PROOF: removing the in-window-hit check (treating poison
     * detection as informational only, never a failure) would make this
     * test fail — a line referencing the poison currency id MUST fail the
     * check, not just get logged.
     */
    public function test_an_in_window_line_referencing_a_poison_currency_fails_the_check(): void
    {
        $this->loadCurrencyAndDetail(
            [
                [1, 1, 'KWD', 'Kuwaiti Dinar', 'Fils', 1, 3, '2020-01-01', '', 1, '2020-01-01', 1],
                [2, 2, 'XYZ', 'Junk', '', 2500, 3, '2020-01-01', '', 1, '2020-01-01', 1],
            ],
            [
                [1, 1001, 5001, 'DOC1', 'INV', 'INV', '2025-03-01', 1, 'T', 10, 0, 0, 2, 2500, 100, 0, 'D'],
            ]
        );

        $result = app(MastersAuditor::class)->run();

        $this->assertSame('fail', $result['currency_poison']['status']);
        $this->assertNotEmpty($result['currency_poison']['detail']['in_window_hits']);
    }

    /**
     * PLAN.md §5.1 R9 — vacuous green.
     *
     * In the REAL export, tblCurrency.Curr_ID runs 0..11 (it is a rate-HISTORY
     * table) while tblAccDetail.FcCurrID_FK runs 1023..142973 — they are
     * different key spaces, because the currency master those line FKs point
     * at (tblMaster) is deliberately excluded from the export. Comparing them
     * yields a PASS that proves nothing, and could equally yield a false FAIL
     * (Curr_ID 5 carries both a KWD row and a poison code-"2" row). The check
     * must say so rather than issue a clean bill of health.
     *
     * MUTATION PROOF: delete the key-space overlap test and this check
     * reports 'pass' on data it cannot speak to — this test fails.
     */
    public function test_the_poison_check_reports_inconclusive_when_the_key_spaces_do_not_join(): void
    {
        $this->loadCurrencyAndDetail(
            [
                [1, 5, 'KWD', 'Kuwaiti Dinar', 'Fils', 1, 3, '2020-01-01', '', 1, '2020-01-01', 1],
                [2, 5, '2', 'UQ', '', 0, 2, '2020-01-01', '', 1, '2020-01-01', 1],
                [3, 9, 'XYZ', 'Junk', '', 2500, 3, '2020-01-01', '', 1, '2020-01-01', 1],
            ],
            [
                // Line currency ids from the REAL export's key space -- none
                // of which appears in stg_currency at all.
                [1, 1001, 5001, 'DOC1', 'INV', 'INV', '2025-03-01', 1, 'T', 10, 0, 0, 1025, 1, 100, 0, 'D'],
                [1, 1002, 5001, 'DOC1', 'INV', 'INV', '2025-03-01', 2, 'T', 11, 0, 0, 1038, 0.236, 0, 100, 'C'],
            ]
        );

        $result = app(MastersAuditor::class)->run();

        $this->assertSame('info', $result['currency_poison']['status'], 'must not claim PASS on a comparison it cannot make');
        $this->assertSame(0, $result['currency_poison']['detail']['key_space_overlap']);
        $this->assertStringContainsString('INCONCLUSIVE', $result['currency_poison']['detail']['note']);
        $this->assertStringContainsString('do NOT read this as a clean bill of health', $result['currency_poison']['detail']['note']);
    }

    public function test_unposted_header_count_is_reported(): void
    {
        $this->writeLegacyCsv('tblAccHeader.csv', $this->accHeaderHeader(), [
            [1, 1, 100, 'D100', 'INV', 'INV', '2025-01-01', '', '', '', '', '', '', '', 1, 1, 2025, 0, 0, '', '', '', 1, '2020-01-01', 1, '2020-01-01', '', '', '', '', '', ''],
            [1, 1, 101, 'D101', 'INV', 'INV', '2025-01-02', '', '', '', '', '', '', '', 0, 1, 2025, 0, 0, '', '', '', 1, '2020-01-01', 1, '2020-01-01', '', '', '', '', '', ''],
        ]);
        app(LegacyCsvLoader::class)->load('tblAccHeader', ['table' => 'stg_acc_header', 'rows' => 2], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccHeader.csv');

        $result = app(MastersAuditor::class)->run();

        $this->assertSame(1, $result['unposted_headers']['detail']['unposted_count']);
        $this->assertSame(1, $result['unposted_headers']['detail']['posted_count']);
    }

    public function test_ojv_inventory_reports_one_per_year_as_pass(): void
    {
        $this->writeLegacyCsv('tblAccHeader.csv', $this->accHeaderHeader(), [
            [1, 1, 200, 'D200', 'JV', 'OJV', '2018-01-01', '', '', '', '', '', '', '', 1, 1, 2018, 0, 0, '', '', '', 1, '2020-01-01', 1, '2020-01-01', '', '', '', '', '', ''],
            [1, 1, 201, 'D201', 'JV', 'OJV', '2025-01-01', '', '', '', '', '', '', '', 1, 1, 2025, 0, 0, '', '', '', 1, '2020-01-01', 1, '2020-01-01', '', '', '', '', '', ''],
        ]);
        app(LegacyCsvLoader::class)->load('tblAccHeader', ['table' => 'stg_acc_header', 'rows' => 2], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccHeader.csv');

        $result = app(MastersAuditor::class)->run();

        $this->assertSame('pass', $result['ojv_inventory']['status']);
        $this->assertSame(1, $result['ojv_inventory']['detail']['by_year']['2018']);
        $this->assertSame(1, $result['ojv_inventory']['detail']['by_year']['2025']);
    }

    public function test_ojv_inventory_flags_a_year_with_more_than_one(): void
    {
        $this->writeLegacyCsv('tblAccHeader.csv', $this->accHeaderHeader(), [
            [1, 1, 200, 'D200', 'JV', 'OJV', '2018-01-01', '', '', '', '', '', '', '', 1, 1, 2018, 0, 0, '', '', '', 1, '2020-01-01', 1, '2020-01-01', '', '', '', '', '', ''],
            [1, 1, 202, 'D202', 'JV', 'OJV', '2018-06-01', '', '', '', '', '', '', '', 1, 1, 2018, 0, 0, '', '', '', 1, '2020-01-01', 1, '2020-01-01', '', '', '', '', '', ''],
        ]);
        app(LegacyCsvLoader::class)->load('tblAccHeader', ['table' => 'stg_acc_header', 'rows' => 2], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccHeader.csv');

        $result = app(MastersAuditor::class)->run();

        $this->assertNotSame('pass', $result['ojv_inventory']['status']);
        $this->assertArrayHasKey('2018', $result['ojv_inventory']['detail']['years_not_exactly_one']);
    }

    public function test_it_writes_one_report_row_per_check(): void
    {
        app(MastersAuditor::class)->run();

        $count = DB::connection('legacy_pilot')->table('legacy_masters_audit')->count();

        $this->assertGreaterThanOrEqual(6, $count);
    }

    /**
     * legacy-ledger-pilot LP1b defect 2 -- the real export stores `Posted`
     * as the literal TEXT 'True'/'False', not '1'/'0'. A literal-equality
     * filter matched nothing against that shape, and the real staging run
     * reported 37,134 "unposted" (every staged row) instead of the true
     * count of 37 (config('legacy_pilot.posted_false_count')).
     *
     * MUTATION PROOF: reverting auditUnpostedHeaders() to
     * `->where('posted', '0')->orWhere('posted', 0)->orWhereNull('posted')`
     * makes this test fail — it would report 3 unposted instead of 1 (the
     * 'False'/'false' rows would not be recognised as unposted).
     */
    public function test_unposted_header_count_is_reported_when_posted_is_text_true_false(): void
    {
        $this->writeLegacyCsv('tblAccHeader.csv', $this->accHeaderHeader(), [
            [1, 1, 100, 'D100', 'INV', 'INV', '2025-01-01', '', '', '', '', '', '', '', 'True', 1, 2025, 0, 0, '', '', '', 1, '2020-01-01', 1, '2020-01-01', '', '', '', '', '', ''],
            [1, 1, 101, 'D101', 'INV', 'INV', '2025-01-02', '', '', '', '', '', '', '', 'False', 1, 2025, 0, 0, '', '', '', 1, '2020-01-01', 1, '2020-01-01', '', '', '', '', '', ''],
            [1, 1, 102, 'D102', 'INV', 'INV', '2025-01-03', '', '', '', '', '', '', '', 'true', 1, 2025, 0, 0, '', '', '', 1, '2020-01-01', 1, '2020-01-01', '', '', '', '', '', ''],
        ]);
        app(LegacyCsvLoader::class)->load('tblAccHeader', ['table' => 'stg_acc_header', 'rows' => 3], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccHeader.csv');

        $result = app(MastersAuditor::class)->run();

        $this->assertSame(1, $result['unposted_headers']['detail']['unposted_count']);
        $this->assertSame(2, $result['unposted_headers']['detail']['posted_count']);
    }

    /**
     * legacy-ledger-pilot LP1b defect 2 -- census_2025 reported 0 for
     * every SubType against real staging data because its `posted`
     * filter used the same broken literal-equality comparison as
     * auditUnpostedHeaders(). This proves the census counts a 'True'
     * 2025-dated document, and reports a 'False' one SEPARATELY rather than
     * dropping it (LP1c ruling R3).
     *
     * MUTATION PROOF: reverting auditCensus2025() to
     * `->where('posted', '1')->orWhere('posted', 1)` makes this test fail
     * (posted would be 0, not 1).
     */
    public function test_census_2025_counts_a_posted_document_when_posted_is_text_true(): void
    {
        // R3: the manifest counts ALL 2025 headers, posted or not.
        config(['legacy_pilot.census_2025' => ['INV' => 2]]);

        $this->writeLegacyCsv('tblAccHeader.csv', $this->accHeaderHeader(), [
            [1, 1, 100, 'D100', 'INV', 'INV', '2025-06-15', '', '', '', '', '', '', '', 'True', 1, 2025, 0, 0, '', '', '', 1, '2020-01-01', 1, '2020-01-01', '', '', '', '', '', ''],
            [1, 1, 101, 'D101', 'INV', 'INV', '2025-06-16', '', '', '', '', '', '', '', 'False', 1, 2025, 0, 0, '', '', '', 1, '2020-01-01', 1, '2020-01-01', '', '', '', '', '', ''],
        ]);
        app(LegacyCsvLoader::class)->load('tblAccHeader', ['table' => 'stg_acc_header', 'rows' => 2], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccHeader.csv');

        $result = app(MastersAuditor::class)->run();

        $this->assertSame(1, $result['census_2025']['detail']['posted_counts']['INV']);
        $this->assertSame(1, $result['census_2025']['detail']['unposted_counts']['INV']);
        $this->assertSame('pass', $result['census_2025']['status']);
    }

    /**
     * LP1c ruling R3, reproducing the real FRV/ADM shortfall in miniature:
     * the manifest's expected count includes UNPOSTED headers, so a
     * posted-only comparison reports a permanent, wrong-looking shortfall on
     * exactly the SubTypes that carry unposted 2025 documents (FRV 4,298 vs
     * 4,325 and ADM 301 vs 302 on the real export — 4,298 + 27 = 4,325 and
     * 301 + 1 = 302, no residual).
     *
     * MUTATION PROOF: restore the posted-only comparison — `$actual =
     * $counts[$subtype] ?? 0` against the manifest figure — and this test
     * fails: FRV would report a diff and the whole check would go to 'info'.
     */
    public function test_census_2025_passes_when_posted_plus_unposted_equals_the_manifest_count(): void
    {
        config(['legacy_pilot.census_2025' => ['INV' => 2, 'FRV' => 3]]);

        $this->writeLegacyCsv('tblAccHeader.csv', $this->accHeaderHeader(), [
            [1, 1, 100, 'D100', 'INV', 'INV', '2025-06-15', '', '', '', '', '', '', '', 'True', 1, 2025, 0, 0, '', '', '', 1, '2020-01-01', 1, '2020-01-01', '', '', '', '', '', ''],
            [1, 1, 101, 'D101', 'INV', 'INV', '2025-06-16', '', '', '', '', '', '', '', 'True', 1, 2025, 0, 0, '', '', '', 1, '2020-01-01', 1, '2020-01-01', '', '', '', '', '', ''],
            [1, 1, 102, 'D102', 'FRV', 'FRV', '2025-06-17', '', '', '', '', '', '', '', 'True', 1, 2025, 0, 0, '', '', '', 1, '2020-01-01', 1, '2020-01-01', '', '', '', '', '', ''],
            [1, 1, 103, 'D103', 'FRV', 'FRV', '2025-06-18', '', '', '', '', '', '', '', 'False', 1, 2025, 0, 0, '', '', '', 1, '2020-01-01', 1, '2020-01-01', '', '', '', '', '', ''],
            [1, 1, 104, 'D104', 'FRV', 'FRV', '2025-06-19', '', '', '', '', '', '', '', 'False', 1, 2025, 0, 0, '', '', '', 1, '2020-01-01', 1, '2020-01-01', '', '', '', '', '', ''],
        ]);
        app(LegacyCsvLoader::class)->load('tblAccHeader', ['table' => 'stg_acc_header', 'rows' => 5], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccHeader.csv');

        $detail = app(MastersAuditor::class)->run()['census_2025'];

        $this->assertSame('pass', $detail['status']);
        $this->assertSame([], $detail['detail']['diffs_vs_expected']);

        // Posted and unposted are reported SEPARATELY — the posted split is
        // what LP3 actually replays, so collapsing them would hide it.
        $this->assertSame(['INV' => 2, 'FRV' => 1], $detail['detail']['posted_counts']);
        $this->assertSame(['FRV' => 2], $detail['detail']['unposted_counts']);
        $this->assertSame(['INV' => 2, 'FRV' => 3], $detail['detail']['posted_plus_unposted']);
    }

    /**
     * A genuine shortfall must still be reported: posted + unposted short of
     * the manifest is real missing data, not an accounting convention.
     */
    public function test_census_2025_still_reports_a_diff_when_posted_plus_unposted_is_short(): void
    {
        config(['legacy_pilot.census_2025' => ['INV' => 3]]);

        $this->writeLegacyCsv('tblAccHeader.csv', $this->accHeaderHeader(), [
            [1, 1, 100, 'D100', 'INV', 'INV', '2025-06-15', '', '', '', '', '', '', '', 'True', 1, 2025, 0, 0, '', '', '', 1, '2020-01-01', 1, '2020-01-01', '', '', '', '', '', ''],
            [1, 1, 101, 'D101', 'INV', 'INV', '2025-06-16', '', '', '', '', '', '', '', 'False', 1, 2025, 0, 0, '', '', '', 1, '2020-01-01', 1, '2020-01-01', '', '', '', '', '', ''],
        ]);
        app(LegacyCsvLoader::class)->load('tblAccHeader', ['table' => 'stg_acc_header', 'rows' => 2], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccHeader.csv');

        $detail = app(MastersAuditor::class)->run()['census_2025'];

        $this->assertSame('info', $detail['status']);
        $this->assertSame(
            ['expected' => 3, 'posted' => 1, 'unposted' => 1, 'posted_plus_unposted' => 2],
            $detail['detail']['diffs_vs_expected']['INV']
        );
    }

    /**
     * legacy-ledger-pilot LP1b defect 3 (owner ruling) -- a frozen account
     * with in-window (2025) activity is legitimate legacy history, not a
     * phase blocker: the check must report INFO with counts and the
     * affected account ids, never FAIL.
     *
     * MUTATION PROOF: reverting auditFrozenAccountActivity() to
     * `'status' => empty($hits) ? 'pass' : 'fail'` makes this test fail
     * (status would be 'fail', not 'info').
     */
    public function test_frozen_account_activity_is_info_not_fail_when_frozen_accounts_have_in_window_lines(): void
    {
        $this->writeLegacyCsv('tblAccount.csv', $this->accountHeader(), [
            [1, '206010100', 5001, 'AC5001', 1, 'Frozen Payable', '', 'L', 0, 0, '', 0, 1, null, 1, 1, 'True', 0, '', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
        ]);
        app(LegacyCsvLoader::class)->load('tblAccount', ['table' => 'stg_account', 'rows' => 1], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccount.csv');

        $this->writeLegacyCsv('tblAccDetail.csv', $this->accDetailHeader(), [
            [1, 1, 9001, 'DOC9001', 'INV', 'INV', '2025-04-01', 1, 'T', 5001, 0, 0, 1, 1, 100, 0, 'D'],
        ]);
        app(LegacyCsvLoader::class)->load('tblAccDetail', ['table' => 'stg_acc_detail', 'rows' => 1], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccDetail.csv');

        $result = app(MastersAuditor::class)->run();

        $this->assertSame('info', $result['frozen_account_activity']['status']);
        $this->assertSame(1, $result['frozen_account_activity']['detail']['frozen_accounts']);
        $this->assertSame(1, $result['frozen_account_activity']['detail']['with_in_window_activity']);
        $this->assertSame([5001], $result['frozen_account_activity']['detail']['frozen_account_ids_with_activity']);
    }
}
