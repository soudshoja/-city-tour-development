<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Services\Onboarding\LegacyCsvLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\BuildsLegacyCsvFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP0.3 -- legacy:load / LegacyCsvLoader.
 */
class LegacyLoadCommandTest extends TestCase
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

    public function test_it_loads_a_csv_into_a_stg_table_matching_the_expected_row_count(): void
    {
        $this->writeLegacyCsv('tblAccYear.csv', ['SlNo', 'FromDt', 'ToDt'], [
            [1, '2025-01-01', '2025-12-31'],
            [2, '2026-01-01', '2026-12-31'],
        ]);

        $loader = app(LegacyCsvLoader::class);
        $result = $loader->load('tblAccYear', ['table' => 'stg_acc_year', 'rows' => 2], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccYear.csv');

        $this->assertSame('loaded', $result['status']);
        $this->assertSame(2, $result['rows']);
        $this->assertSame(2, DB::connection('legacy_pilot')->table('stg_acc_year')->count());

        $audit = DB::connection('legacy_pilot')->table('legacy_load_audit')->where('table_key', 'tblAccYear')->first();
        $this->assertNotNull($audit);
        $this->assertSame('loaded', $audit->status);
        $this->assertSame(2, $audit->loaded_rows);
    }

    /**
     * MUTATION PROOF: deleting the row-count comparison in
     * LegacyCsvLoader::load() (i.e. always treating the load as successful)
     * makes this test fail, because it currently throws.
     */
    public function test_row_count_mismatch_fails_the_load_and_names_the_table(): void
    {
        $this->writeLegacyCsv('tblAccYear.csv', ['SlNo', 'FromDt', 'ToDt'], [
            [1, '2025-01-01', '2025-12-31'],
        ]);

        $loader = app(LegacyCsvLoader::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/tblAccYear/');

        $loader->load('tblAccYear', ['table' => 'stg_acc_year', 'rows' => 99], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccYear.csv');
    }

    public function test_row_count_mismatch_records_a_failed_audit_row(): void
    {
        $this->writeLegacyCsv('tblAccYear.csv', ['SlNo', 'FromDt', 'ToDt'], [
            [1, '2025-01-01', '2025-12-31'],
        ]);

        $loader = app(LegacyCsvLoader::class);

        try {
            $loader->load('tblAccYear', ['table' => 'stg_acc_year', 'rows' => 99], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccYear.csv');
        } catch (RuntimeException) {
            // expected
        }

        $audit = DB::connection('legacy_pilot')->table('legacy_load_audit')->where('table_key', 'tblAccYear')->first();
        $this->assertNotNull($audit);
        $this->assertSame('failed', $audit->status);
    }

    /**
     * MUTATION PROOF: removing validateOrRecordHeader()'s comparison (i.e.
     * always accepting whatever header shows up) makes this test fail.
     */
    public function test_header_mismatch_against_the_first_load_baseline_fails_loudly(): void
    {
        $this->writeLegacyCsv('tblAccYear.csv', ['SlNo', 'FromDt', 'ToDt'], [
            [1, '2025-01-01', '2025-12-31'],
        ]);

        $loader = app(LegacyCsvLoader::class);
        $loader->load('tblAccYear', ['table' => 'stg_acc_year', 'rows' => 1], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccYear.csv');

        // Second file: same table key, DIFFERENT header shape.
        $this->writeLegacyCsv('tblAccYear.csv', ['SlNo', 'FromDt', 'ToDt', 'ExtraColumn'], [
            [1, '2025-01-01', '2025-12-31', 'x'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/header mismatch/');

        $loader->load('tblAccYear', ['table' => 'stg_acc_year', 'rows' => 1], $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccYear.csv', true);
    }

    public function test_rerunning_against_a_byte_identical_file_is_a_no_op(): void
    {
        $this->writeLegacyCsv('tblAccYear.csv', ['SlNo', 'FromDt', 'ToDt'], [
            [1, '2025-01-01', '2025-12-31'],
        ]);

        $loader = app(LegacyCsvLoader::class);
        $path = $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccYear.csv';

        $first = $loader->load('tblAccYear', ['table' => 'stg_acc_year', 'rows' => 1], $path);
        $second = $loader->load('tblAccYear', ['table' => 'stg_acc_year', 'rows' => 1], $path);

        $this->assertSame('loaded', $first['status']);
        $this->assertSame('skipped_identical', $second['status']);
        $this->assertSame(1, DB::connection('legacy_pilot')->table('stg_acc_year')->count());
    }

    public function test_a_changed_file_truncates_and_reloads(): void
    {
        $path = $this->legacyFixtureRoot.'/ledger-export-2025-2026Q1/tblAccYear.csv';

        $this->writeLegacyCsv('tblAccYear.csv', ['SlNo', 'FromDt', 'ToDt'], [
            [1, '2025-01-01', '2025-12-31'],
        ]);

        $loader = app(LegacyCsvLoader::class);
        $loader->load('tblAccYear', ['table' => 'stg_acc_year', 'rows' => 1], $path);

        $this->writeLegacyCsv('tblAccYear.csv', ['SlNo', 'FromDt', 'ToDt'], [
            [1, '2025-01-01', '2025-12-31'],
            [2, '2026-01-01', '2026-12-31'],
        ]);

        $result = $loader->load('tblAccYear', ['table' => 'stg_acc_year', 'rows' => 2], $path);

        $this->assertSame('loaded', $result['status']);
        $this->assertSame(2, DB::connection('legacy_pilot')->table('stg_acc_year')->count());
    }

    public function test_it_refuses_a_path_outside_the_allowed_root(): void
    {
        $outside = sys_get_temp_dir().'/lp1_outside_root_'.bin2hex(random_bytes(4)).'.csv';
        file_put_contents($outside, "SlNo,FromDt,ToDt\n1,2025-01-01,2025-12-31\n");

        try {
            $loader = app(LegacyCsvLoader::class);

            $this->expectException(RuntimeException::class);

            $loader->load('tblAccYear', ['table' => 'stg_acc_year', 'rows' => 1], $outside);
        } finally {
            @unlink($outside);
        }
    }

    public function test_the_command_reports_failure_and_exits_non_zero_on_mismatch(): void
    {
        $this->writeLegacyCsv('tblAccYear.csv', ['SlNo', 'FromDt', 'ToDt'], [
            [1, '2025-01-01', '2025-12-31'],
        ]);

        config(['legacy_pilot.tables' => [
            'tblAccYear' => ['file' => 'tblAccYear.csv', 'table' => 'stg_acc_year', 'rows' => 99],
        ]]);

        $this->artisan('legacy:load')->assertExitCode(1);
    }

    /**
     * legacy-ledger-pilot LP1b defect 4 -- the command always appended
     * config('legacy_pilot.export_dir') to `--root`, so passing the export
     * directory itself (instead of its parent) doubled the subfolder and
     * failed every table with "path does not exist". This was hit for
     * real on the first staging run. `--root` must accept EITHER form.
     *
     * MUTATION PROOF: removing normaliseRoot()'s call in handle() makes
     * this test fail — it would look for
     * ".../ledger-export-2025-2026Q1/ledger-export-2025-2026Q1/tblAccYear.csv".
     */
    public function test_root_accepts_the_parent_directory_form(): void
    {
        $this->writeLegacyCsv('tblAccYear.csv', ['SlNo', 'FromDt', 'ToDt'], [
            [1, '2025-01-01', '2025-12-31'],
        ]);

        config(['legacy_pilot.tables' => [
            'tblAccYear' => ['file' => 'tblAccYear.csv', 'table' => 'stg_acc_year', 'rows' => 1],
        ]]);

        // The parent of the export dir -- the historically-documented shape.
        $this->artisan('legacy:load', ['--root' => $this->legacyFixtureRoot])
            ->assertExitCode(0);

        $this->assertSame(1, DB::connection('legacy_pilot')->table('stg_acc_year')->count());
    }

    /**
     * Same fixture, but `--root` names the export directory itself
     * (D:\akeedac\ledger-export-2025-2026Q1 rather than D:\akeedac) — the
     * form that broke on the real staging run.
     */
    public function test_root_also_accepts_the_export_directory_itself(): void
    {
        $this->writeLegacyCsv('tblAccYear.csv', ['SlNo', 'FromDt', 'ToDt'], [
            [1, '2025-01-01', '2025-12-31'],
        ]);

        config(['legacy_pilot.tables' => [
            'tblAccYear' => ['file' => 'tblAccYear.csv', 'table' => 'stg_acc_year', 'rows' => 1],
        ]]);

        $exportDirPath = $this->legacyFixtureRoot.DIRECTORY_SEPARATOR.'ledger-export-2025-2026Q1';

        $this->artisan('legacy:load', ['--root' => $exportDirPath])
            ->assertExitCode(0);

        $this->assertSame(1, DB::connection('legacy_pilot')->table('stg_acc_year')->count());
    }
}
