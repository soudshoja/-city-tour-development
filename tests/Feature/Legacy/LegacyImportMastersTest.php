<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Country;
use App\Models\User;
use App\Services\Onboarding\LegacyBranchImporter;
use App\Services\Onboarding\LegacyCsvLoader;
use App\Services\Onboarding\LegacyCurrencyMapper;
use App\Services\Onboarding\LegacyVerifyConfigReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\BuildsLegacyCsvFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP1e — `legacy:import-masters`.
 *
 * Staging run #5's whole finding, in one sentence: `map_branch` and
 * `map_currency` are READ by the replay and WRITTEN BY NOTHING except a test
 * fixture, so the dry run refused 100% of the 2025 population on
 * `legacy.branch_unmapped` and would have refused the rest on
 * `legacy.currency_unmapped` the moment branch was fixed.
 *
 * Every fixture here is SYNTHETIC. No value came from the real export; the
 * SHAPES (a four-branch master with control-account FKs, a rate history whose
 * KWD rows carry a ValidTill BEFORE their own ValidFrom, line FKs from a
 * key space the currency master does not share) mirror the export's public
 * schema and its documented defects, not its data.
 */
class LegacyImportMastersTest extends TestCase
{
    use BuildsLegacyCsvFixtures, PreparesLegacyPilotFence, RefreshDatabase;

    private int $companyId;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyPilotFence();
        $this->makeLegacyFixtureRoot();

        $country = Country::factory()->create();
        $user = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $user->id, 'country_id' => $country->id]);

        $this->companyId = $company->id;
        $this->userId = $user->id;

        config(['legacy_pilot.default_company_id' => $this->companyId]);
    }

    protected function tearDown(): void
    {
        $this->cleanupLegacyFixtureRoot();

        parent::tearDown();
    }

    // ─── fixtures ────────────────────────────────────────────────────────────

    /**
     * The four-branch shape: codes CO/SH/MN/BY on legacy Branch_IDs 1/10/11/12,
     * each carrying the master's five control-account FKs.
     */
    private function stageBranches(): void
    {
        $rows = [
            // Branch_ID, Company_ID, BranchCode, BranchName, ..., BranchAccID_FK, IsFreeze, CashAccID_FK, CashControlAccID_FK, BankAccID_FK, DiscountAcc, ...
            [1, 1, 'CO', 'LEGACY-BRANCH-1', '', '', '', 'False', '', 5001, 'False', 5002, 5003, 5004, 5005, 1, '2020-01-01', 1, '2020-01-01'],
            [10, 1, 'SH', 'LEGACY-BRANCH-10', '', '', '', 'False', '', 5001, 'False', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [11, 1, 'MN', 'LEGACY-BRANCH-11', '', '', '', 'False', '', '', 'False', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
            [12, 1, 'BY', 'LEGACY-BRANCH-12', '', '', '', 'False', '', '', 'True', '', '', '', '', 1, '2020-01-01', 1, '2020-01-01'],
        ];

        $this->writeLegacyCsv('tblBranch.csv', $this->branchHeader(), $rows);

        app(LegacyCsvLoader::class)->load(
            'tblBranch',
            ['table' => 'stg_branch', 'rows' => count($rows)],
            $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblBranch.csv'
        );
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows  raw tblCurrency rows
     */
    private function stageCurrencyMaster(array $rows): void
    {
        $this->writeLegacyCsv('tblCurrency.csv', $this->currencyHeader(), $rows);

        app(LegacyCsvLoader::class)->load(
            'tblCurrency',
            ['table' => 'stg_currency', 'rows' => count($rows)],
            $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblCurrency.csv'
        );
    }

    /**
     * The export's real currency master shape, defects included: an open-ended
     * AUS rate, an open-ended USD rate that COLLIDES with one of AUS's own
     * historical rates, a poison XYZ row at 2500, and two KWD rows whose
     * ValidTill (1900-01-02) precedes their own ValidFrom.
     */
    private function stageRealisticCurrencyMaster(): void
    {
        $this->stageCurrencyMaster([
            [1, 1, 'AUS', 'Aus Dollar', '', '0.2360000', 3, '2016-01-01 00:00:00', '2016-06-01 00:00:00', 1, '2016-01-01', 1],
            [2, 1, 'AUS', 'Aus Dollar', '', '0.2345000', 3, '2016-06-01 00:00:00', '', 1, '2016-06-01', 1],
            [3, 2, 'USD', 'US Dollar', '', '0.2360000', 3, '2016-01-01 00:00:00', '', 1, '2016-01-01', 1],
            [4, 5, 'KWD', 'Kuwaiti Dinar', '', '1.0000000', 3, '2016-09-26 21:27:00', '1900-01-02 00:00:00', 1, '2016-09-26', 1],
            [5, 9, 'XYZ', 'junk', '', '2500.0000000', 2, '2016-09-29 18:42:00', '', 1, '2016-09-29', 1],
            [6, 0, '', 'blank code', '', '0.0000000', 0, '1900-01-01 00:00:00', '1900-01-01 00:00:00', 1, '1900-01-01', 1],
        ]);
    }

    /**
     * @param  array<int, array{curr:int|string,rate:string,fc:string,lc:string,date?:string}>  $lines
     */
    private function stageDetailLines(array $lines): void
    {
        $rows = [];
        $id = 1;

        foreach ($lines as $line) {
            $rows[] = [
                1, $id, 900, 'D-'.$id, 'JV', 'JV', $line['date'] ?? '2025-06-01', '', '',
                4001, $line['fc'], '0.000', $line['curr'], $line['rate'], $line['lc'], '0.000', 'D',
            ];
            $id++;
        }

        $this->writeLegacyCsv('tblAccDetail.csv', $this->accDetailHeader(), $rows);

        app(LegacyCsvLoader::class)->load(
            'tblAccDetail',
            ['table' => 'stg_acc_detail', 'rows' => count($rows)],
            $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccDetail.csv'
        );
    }

    /** @return array<string, object> curr_id_fk => map_currency row */
    private function currencyRows(): array
    {
        $rows = [];

        foreach (DB::connection('legacy_pilot')->table('map_currency')->where('company_id', $this->companyId)->get() as $row) {
            $rows[(string) $row->curr_id_fk] = $row;
        }

        return $rows;
    }

    // ─── R-branch ────────────────────────────────────────────────────────────

    public function test_the_four_legacy_branches_become_four_akeed_branches_and_four_map_rows(): void
    {
        $this->stageBranches();

        $stats = app(LegacyBranchImporter::class)->import($this->companyId, $this->userId);

        $this->assertSame(4, $stats['staged']);
        $this->assertSame(4, $stats['branches_created']);
        $this->assertSame(4, $stats['mapped']);

        $this->assertSame(4, DB::connection('legacy_pilot')->table('map_branch')->where('company_id', $this->companyId)->count());

        $mapped = DB::connection('legacy_pilot')->table('map_branch')
            ->where('company_id', $this->companyId)
            ->orderBy('branch_id_fk')
            ->pluck('branch_code', 'branch_id_fk')
            ->all();

        $this->assertSame([1 => 'CO', 10 => 'SH', 11 => 'MN', 12 => 'BY'], $mapped);

        // Names are STRUCTURAL — derived from the code, never the legacy
        // BranchName, which is business data this pilot does not republish.
        $names = Branch::where('company_id', $this->companyId)->orderBy('id')->pluck('name')->all();
        $this->assertSame(['Legacy Branch CO', 'Legacy Branch SH', 'Legacy Branch MN', 'Legacy Branch BY'], $names);

        foreach ($names as $name) {
            $this->assertStringNotContainsString('LEGACY-BRANCH-', $name);
        }

        // Every map row points at a real branch of THIS company.
        foreach (DB::connection('legacy_pilot')->table('map_branch')->where('company_id', $this->companyId)->get() as $row) {
            $this->assertNotNull($row->akeed_branch_id);
            $this->assertNotNull(Branch::where('company_id', $this->companyId)->find((int) $row->akeed_branch_id));
        }
    }

    /** MUTATION PROOF for idempotency: a second run must not mint a fifth branch. */
    public function test_a_second_run_updates_the_same_four_branches_and_never_creates_a_fifth(): void
    {
        $this->stageBranches();

        app(LegacyBranchImporter::class)->import($this->companyId, $this->userId);
        $first = Branch::where('company_id', $this->companyId)->pluck('id')->sort()->values()->all();

        $stats = app(LegacyBranchImporter::class)->import($this->companyId, $this->userId);

        $this->assertSame(0, $stats['branches_created']);
        $this->assertSame(4, $stats['branches_updated']);
        $this->assertSame(4, $stats['mapped']);
        $this->assertSame($first, Branch::where('company_id', $this->companyId)->pluck('id')->sort()->values()->all());
        $this->assertSame(4, DB::connection('legacy_pilot')->table('map_branch')->where('company_id', $this->companyId)->count());
    }

    /**
     * The legacy master's control-account FKs are RESOLVED through
     * legacy_acc_map and recorded on the map row — `branches` has no columns
     * to attach them to (the relationship runs the other way,
     * `accounts.branch_id`), and dropping them silently was not an option.
     */
    public function test_branch_control_account_fks_are_recorded_and_resolved_through_legacy_acc_map(): void
    {
        $this->stageBranches();

        $bank = Account::factory()->create(['company_id' => $this->companyId, 'name' => 'BANK ACCOUNTS']);

        DB::connection('legacy_pilot')->table('legacy_acc_map')->insert([
            'company_id' => $this->companyId,
            'acc_id' => 5004,
            'acc_code' => '5004',
            'account_id' => $bank->id,
            'resolution' => 'direct',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stats = app(LegacyBranchImporter::class)->import($this->companyId, $this->userId);

        // 5 FKs on branch 1 + 1 on branch 10.
        $this->assertSame(6, $stats['control_fk_recorded']);
        $this->assertSame(1, $stats['control_fk_resolved']);

        $co = DB::connection('legacy_pilot')->table('map_branch')
            ->where('company_id', $this->companyId)->where('branch_id_fk', 1)->first();

        $this->assertSame(5001, (int) $co->legacy_branch_acc_id_fk);
        $this->assertSame(5004, (int) $co->legacy_bank_acc_id_fk);
        $this->assertSame($bank->id, (int) $co->bank_account_id);
        // 5001 was never imported, so it resolves to NULL rather than to
        // "whatever account happens to be nearby".
        $this->assertNull($co->branch_account_id);

        $by = DB::connection('legacy_pilot')->table('map_branch')
            ->where('company_id', $this->companyId)->where('branch_id_fk', 12)->first();
        $this->assertTrue((bool) $by->legacy_is_freeze, 'IsFreeze on the branch master is preserved on the map row.');
    }

    public function test_an_empty_branch_master_is_refused_rather_than_leaving_map_branch_empty(): void
    {
        $this->writeLegacyCsv('tblBranch.csv', $this->branchHeader(), []);
        app(LegacyCsvLoader::class)->load('tblBranch', ['table' => 'stg_branch', 'rows' => 0], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblBranch.csv');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/empty/');

        app(LegacyBranchImporter::class)->import($this->companyId, $this->userId);
    }

    // ─── R-currency ──────────────────────────────────────────────────────────

    public function test_a_currency_is_derived_from_the_rate_history_and_its_validity_window(): void
    {
        $this->stageRealisticCurrencyMaster();
        $this->stageDetailLines([
            // FK 4318 — the open-ended AUS rate, on a 2025 line.
            ['curr' => 4318, 'rate' => '0.234500000000', 'fc' => '100.000', 'lc' => '23.450'],
        ]);

        $stats = app(LegacyCurrencyMapper::class)->map($this->companyId);

        $this->assertSame(1, $stats['distinct_fks']);
        $this->assertSame(1, $stats['mapped']);
        $this->assertSame(1, $stats['by_rate_history']);
        $this->assertSame(0, $stats['unresolved']);

        $row = $this->currencyRows()['4318'];
        $this->assertSame('AUS', $row->curr_code);
        $this->assertSame('mapped', $row->status);
        $this->assertSame('rate_history', $row->derivation);
        $this->assertFalse((bool) $row->is_poison);
        $this->assertSame(1, (int) $row->line_count);
    }

    /**
     * The rule that resolves the export's 170,577-line bulk. Its FK belongs to
     * a key space `stg_currency` does not share at all, so there is nothing to
     * join on — but FC == LC at rate 1 IS a base-currency line by the legacy
     * kernel's own rule 3, and the ValidTill-before-ValidFrom KWD rows mean a
     * literal window read would not have found it either.
     */
    public function test_a_base_currency_fk_resolves_by_identity_with_no_usable_master_row(): void
    {
        $this->stageRealisticCurrencyMaster();
        $this->stageDetailLines([
            ['curr' => 1025, 'rate' => '1.000000000000', 'fc' => '10.000', 'lc' => '10.000'],
            ['curr' => 1025, 'rate' => '1.000000000000', 'fc' => '25.500', 'lc' => '25.500'],
        ]);

        $stats = app(LegacyCurrencyMapper::class)->map($this->companyId);

        $this->assertSame(1, $stats['by_identity']);

        $row = $this->currencyRows()['1025'];
        $this->assertSame('KWD', $row->curr_code);
        $this->assertSame('mapped', $row->status);
        $this->assertSame('kwd_identity', $row->derivation);
        $this->assertSame(2, (int) $row->line_count);
    }

    /**
     * MUTATION PROOF for "EVERY line". One line whose FC is not its LC
     * disqualifies the identity rule for the whole FK, which then has to earn
     * its answer from the rate history instead. Relax the rule to "any line"
     * and the derivation below stays `kwd_identity` and this goes red.
     */
    public function test_the_identity_rule_requires_every_line_to_have_the_shape(): void
    {
        $this->stageRealisticCurrencyMaster();
        $this->stageDetailLines([
            ['curr' => 1025, 'rate' => '1.000000000000', 'fc' => '10.000', 'lc' => '10.000'],
            ['curr' => 1025, 'rate' => '0.087000000000', 'fc' => '100.000', 'lc' => '8.700'],
        ]);

        app(LegacyCurrencyMapper::class)->map($this->companyId);

        $row = $this->currencyRows()['1025'];
        $this->assertSame('rate_history', $row->derivation,
            'The identity rule needs every line; this FK falls through to the rate history.');
        // The rate-1 line still matches the master's own KWD row, so the two
        // rules corroborate here rather than one covering for the other.
        $this->assertSame('KWD', $row->curr_code);
        $this->assertSame(2, (int) $row->line_count);
    }

    /**
     * An FK nothing matches is a ROW, not an absent row. The mapper cannot
     * tell "derivation failed" from "never seen" if the row is simply missing,
     * and R-currency's whole ruling depends on telling them apart.
     */
    public function test_an_underivable_fk_is_recorded_unresolved_rather_than_omitted(): void
    {
        $this->stageRealisticCurrencyMaster();
        $this->stageDetailLines([
            ['curr' => 1026, 'rate' => '0.087629100000', 'fc' => '100.000', 'lc' => '8.763'],
        ]);

        $stats = app(LegacyCurrencyMapper::class)->map($this->companyId);

        $this->assertSame(1, $stats['unresolved']);
        $this->assertSame(0, $stats['mapped']);

        $row = $this->currencyRows()['1026'];
        $this->assertSame('unresolved', $row->status);
        $this->assertNull($row->curr_code);
        $this->assertSame('none', $row->derivation);
        $this->assertStringContainsString('tblMaster', (string) $row->notes);
    }

    /** R3: a derived code that is one of the tblCurrency junk rows is quarantined, and the replay refuses on it. */
    public function test_a_poison_code_match_is_quarantined(): void
    {
        $this->stageRealisticCurrencyMaster();
        $this->stageDetailLines([
            ['curr' => 4444, 'rate' => '2500.000000000000', 'fc' => '1.000', 'lc' => '2500.000'],
        ]);

        $stats = app(LegacyCurrencyMapper::class)->map($this->companyId);

        $this->assertSame(1, $stats['poison']);

        $row = $this->currencyRows()['4444'];
        $this->assertSame('XYZ', $row->curr_code);
        $this->assertSame('mapped', $row->status);
        $this->assertTrue((bool) $row->is_poison);
    }

    /**
     * MUTATION PROOF for "never guesses". 0.2360000 is carried by BOTH the
     * historical AUS row and the open-ended USD row, so the window admits two
     * codes and the FK must stay unresolved rather than pick the first.
     */
    public function test_a_rate_matching_two_codes_is_unresolved_rather_than_guessed(): void
    {
        $this->stageRealisticCurrencyMaster();
        $this->stageDetailLines([
            ['curr' => 4321, 'rate' => '0.236000000000', 'fc' => '100.000', 'lc' => '23.600', 'date' => '2016-03-01'],
        ]);

        app(LegacyCurrencyMapper::class)->map($this->companyId);

        $row = $this->currencyRows()['4321'];
        $this->assertSame('unresolved', $row->status);
        $this->assertStringContainsString('ambiguous', (string) $row->notes);
    }

    /**
     * The validity window is real: the same rate outside AUS's historical
     * window matches only the open-ended USD row, so it resolves — and inside
     * it, it does not (the test above). One rate, two answers, decided by date.
     */
    public function test_the_validity_window_decides_which_code_a_shared_rate_belongs_to(): void
    {
        $this->stageRealisticCurrencyMaster();
        $this->stageDetailLines([
            ['curr' => 4321, 'rate' => '0.236000000000', 'fc' => '100.000', 'lc' => '23.600', 'date' => '2025-06-01'],
        ]);

        app(LegacyCurrencyMapper::class)->map($this->companyId);

        $row = $this->currencyRows()['4321'];
        $this->assertSame('mapped', $row->status);
        $this->assertSame('USD', $row->curr_code, 'The AUS row carrying this rate expired in 2016; only USD\'s open-ended row covers a 2025 line.');
    }

    public function test_the_currency_map_is_idempotent(): void
    {
        $this->stageRealisticCurrencyMaster();
        $this->stageDetailLines([
            ['curr' => 1025, 'rate' => '1.000000000000', 'fc' => '10.000', 'lc' => '10.000'],
        ]);

        app(LegacyCurrencyMapper::class)->map($this->companyId);
        app(LegacyCurrencyMapper::class)->map($this->companyId);

        $this->assertSame(1, DB::connection('legacy_pilot')->table('map_currency')->where('company_id', $this->companyId)->count());
    }

    // ─── R-verify ────────────────────────────────────────────────────────────

    public function test_the_verify_report_names_the_env_keys_and_checks_them_against_the_imported_chart(): void
    {
        $bank = Account::factory()->create(['company_id' => $this->companyId, 'name' => 'BANK ACCOUNTS']);
        Account::factory()->create(['company_id' => $this->companyId, 'name' => 'A BANK LEAF', 'parent_id' => $bank->id]);

        $report = app(LegacyVerifyConfigReporter::class)->report($this->companyId);

        $this->assertFalse($report['ok'], 'The stock config carries the SEEDED chart names, so an un-overridden pilot instance must report NEEDS SETTING.');

        $keys = array_column($report['entries'], 'env_key');
        $this->assertSame(['ACCOUNTING_BANK_GROUP_NAME', 'ACCOUNTING_CASH_GROUP_NAME'], $keys);

        $bankEntry = $report['entries'][0];
        $this->assertSame('accounting.engine.bank_group_name', $bankEntry['config_key']);
        $this->assertSame('Bank Accounts', $bankEntry['current']);
        $this->assertSame('BANK ACCOUNTS', $bankEntry['required']);
        $this->assertTrue($bankEntry['present_in_chart'], 'BANK ACCOUNTS is a real group in this chart.');

        // CASH ACCOUNTS is not in this chart at all -- reported, not assumed.
        $this->assertFalse($report['entries'][1]['present_in_chart']);
        $this->assertSame(['BANK ACCOUNTS'], $report['candidates']['bank']);
        $this->assertSame([], $report['candidates']['cash']);
    }

    public function test_the_report_goes_green_once_the_env_keys_are_in_effect(): void
    {
        config([
            'accounting.engine.bank_group_name' => 'BANK ACCOUNTS',
            'accounting.engine.cash_group_name' => 'CASH ACCOUNTS',
        ]);

        $this->assertTrue(app(LegacyVerifyConfigReporter::class)->report($this->companyId)['ok']);
    }

    // ─── the command ─────────────────────────────────────────────────────────

    public function test_the_command_populates_both_maps_and_reports_the_verify_keys(): void
    {
        $this->stageBranches();
        $this->stageRealisticCurrencyMaster();
        $this->stageDetailLines([
            ['curr' => 1025, 'rate' => '1.000000000000', 'fc' => '10.000', 'lc' => '10.000'],
            ['curr' => 1026, 'rate' => '0.087629100000', 'fc' => '100.000', 'lc' => '8.763'],
        ]);

        $this->artisan('legacy:import-masters', ['--company' => $this->companyId, '--user' => $this->userId])
            ->expectsOutputToContain('ACCOUNTING_BANK_GROUP_NAME')
            ->assertExitCode(0);

        $this->assertSame(4, DB::connection('legacy_pilot')->table('map_branch')->where('company_id', $this->companyId)->count());
        $this->assertSame(2, DB::connection('legacy_pilot')->table('map_currency')->where('company_id', $this->companyId)->count());
    }

    public function test_the_command_fails_when_the_branch_master_was_never_staged(): void
    {
        $this->artisan('legacy:import-masters', ['--company' => $this->companyId, '--user' => $this->userId])
            ->assertExitCode(1);
    }
}
