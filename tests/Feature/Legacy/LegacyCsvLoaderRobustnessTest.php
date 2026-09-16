<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Services\Onboarding\LegacyCsvLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsLegacyCsvFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP0.3 — the loader against the shapes the REAL export
 * files actually carry: a UTF-8 BOM, CRLF line endings, quoted commas,
 * embedded newlines inside a quoted field, and 58 MB of it.
 */
class LegacyCsvLoaderRobustnessTest extends TestCase
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

    private function path(string $file): string
    {
        return $this->legacyFixtureRoot.DIRECTORY_SEPARATOR.'ledger-export-2025-2026Q1'.DIRECTORY_SEPARATOR.$file;
    }

    /**
     * The real tblAccount.csv opens with a UTF-8 BOM and uses CRLF. A
     * narration field can contain both a quoted comma and an embedded
     * newline.
     *
     * MUTATION PROOF: remove the 3-byte BOM skip from LegacyCsvLoader and
     * the first column's header becomes "\xEF\xBB\xBFCompany_ID" — the
     * `company_id` assertion below fails.
     */
    public function test_it_parses_bom_crlf_quoted_commas_and_embedded_newlines(): void
    {
        $path = $this->path('tblAccDetail.csv');

        $csv = "\xEF\xBB\xBF".'"CompanyID","AccDetailID","Narration","Debit"'."\r\n"
            .'"1","1001","Ticket ABC, refund, and fee","1250.750"'."\r\n"
            .'"1","1002","Line one'."\r\n".'Line two","0.000"'."\r\n"
            .'"1","1003","plain","3.000"'."\r\n";

        file_put_contents($path, $csv);

        $result = app(LegacyCsvLoader::class)->load(
            'tblAccDetail',
            ['table' => 'stg_acc_detail', 'rows' => 3],
            $path
        );

        $this->assertSame('loaded', $result['status']);
        $this->assertSame(3, $result['rows']);

        $rows = DB::connection('legacy_pilot')->table('stg_acc_detail')->orderBy('stg_row_id')->get();

        // BOM stripped from the header -> the column is `companyid`, not a
        // BOM-prefixed name.
        $this->assertTrue(\Illuminate\Support\Facades\Schema::connection('legacy_pilot')->hasColumn('stg_acc_detail', 'companyid'));

        // Quoted comma survived as ONE field.
        $this->assertSame('Ticket ABC, refund, and fee', $rows[0]->narration);

        // Embedded newline survived as ONE row, not two.
        $this->assertStringContainsString('Line one', (string) $rows[1]->narration);
        $this->assertStringContainsString('Line two', (string) $rows[1]->narration);

        $this->assertSame('3.000', $rows[2]->debit);
    }

    /**
     * The real files run to 58 MB. The loader streams and chunks, so peak
     * memory must not scale with file size.
     *
     * MUTATION PROOF: replace the streaming read with file()/
     * str_getcsv-over-the-whole-file, or buffer every row before a single
     * insert, and this blows the ceiling.
     */
    public function test_a_two_hundred_thousand_line_file_loads_within_a_bounded_memory_ceiling(): void
    {
        $path = $this->path('tblAccIsApply.csv');
        $rowCount = 200000;

        $handle = fopen($path, 'w');
        fwrite($handle, "ApplyID,DocID_FK,AccID_FK,Amount,Narration\r\n");

        for ($i = 1; $i <= $rowCount; $i++) {
            fwrite($handle, sprintf("%d,%d,%d,%0.3f,\"row %d, with a quoted comma\"\r\n", $i, $i + 1000, 1090401, $i / 1000, $i));
        }

        fclose($handle);

        $before = memory_get_usage(true);
        gc_collect_cycles();

        $result = app(LegacyCsvLoader::class)->load(
            'tblAccIsApply',
            ['table' => 'stg_acc_is_apply', 'rows' => $rowCount],
            $path
        );

        $peakDelta = memory_get_peak_usage(true) - $before;

        $this->assertSame('loaded', $result['status']);
        $this->assertSame($rowCount, $result['rows']);
        $this->assertSame($rowCount, (int) DB::connection('legacy_pilot')->table('stg_acc_is_apply')->count());

        $this->assertLessThan(
            256 * 1024 * 1024,
            $peakDelta,
            'the loader must stream: peak memory grew by '.round($peakDelta / 1024 / 1024, 1).' MB on a 200k-line file'
        );
    }

    /**
     * Row-count parity is asserted against the MANIFEST number, never
     * against anything the file itself claims.
     *
     * MUTATION PROOF: make the loader compare $rowCount against its own
     * count (a tautology) and this test stops failing on a short file.
     */
    public function test_row_count_parity_uses_the_manifest_number_not_the_file(): void
    {
        $path = $this->path('tblBranch.csv');
        file_put_contents($path, "Branch_ID,BranchCode\r\n1,CO\r\n10,SH\r\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expected 4, loaded 2');

        app(LegacyCsvLoader::class)->load('tblBranch', ['table' => 'stg_branch', 'rows' => 4], $path);
    }

    /**
     * legacy-ledger-pilot LP1b defect 1 -- the real staging run failed
     * loading tblTrDetail (353 columns, fixed CHUNK_SIZE=500) with
     * "SQLSTATE[HY000]: ... 1390 Prepared statement contains too many
     * placeholders" (500 * 353 = 176,500 > MySQL/MariaDB's 65,535 ceiling).
     * This synthetic 400-column, 300-row CSV reproduces the same shape
     * (400 * 500 = 200,000 placeholders under the old fixed chunk size,
     * comfortably over the ceiling) against the real fence database, and
     * must load in full.
     *
     * MUTATION PROOF: reverting LegacyCsvLoader to insert in fixed
     * self::CHUNK_SIZE (500) row batches regardless of column count makes
     * this test fail with the same 1390 error the staging run hit.
     */
    public function test_a_400_column_300_row_csv_loads_without_exceeding_the_placeholder_ceiling(): void
    {
        // Local test fence only: this dev box's MariaDB (10.4) enforces
        // innodb_strict_mode's CREATE TABLE row-size estimate more
        // conservatively than the real staging server (MariaDB 10.11,
        // which loaded the real 353-column tblTrDetail without issue) --
        // 400 nullable TEXT columns trips that DDL-time estimate under
        // strict mode alone, independently of this test's actual subject
        // (the placeholder-count chunking fix). Relaxing it for this
        // session only reproduces the same effective DDL behaviour as
        // staging without touching LegacyCsvLoader's schema-creation code.
        DB::connection('legacy_pilot')->statement('SET SESSION innodb_strict_mode=0');

        $columnCount = 400;
        $rowCount = 300;

        $header = array_map(fn ($i) => "Col{$i}", range(1, $columnCount));

        $path = $this->path('tblWideSynthetic.csv');
        $fh = fopen($path, 'w');
        fputcsv($fh, $header);

        for ($r = 1; $r <= $rowCount; $r++) {
            fputcsv($fh, array_map(fn ($i) => "r{$r}c{$i}", range(1, $columnCount)));
        }

        fclose($fh);

        $result = app(LegacyCsvLoader::class)->load(
            'tblWideSynthetic',
            ['table' => 'stg_wide_synthetic', 'rows' => $rowCount],
            $path
        );

        $this->assertSame('loaded', $result['status']);
        $this->assertSame($rowCount, $result['rows']);
        $this->assertSame($rowCount, (int) DB::connection('legacy_pilot')->table('stg_wide_synthetic')->count());
    }
}
