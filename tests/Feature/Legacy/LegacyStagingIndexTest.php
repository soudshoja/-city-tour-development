<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Services\Onboarding\LegacyCsvLoader;
use App\Services\Onboarding\LegacyStagingIndexes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsLegacyCsvFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP4b -- the staging indexes.
 *
 * `LegacyCsvLoader` creates each `stg_*` table from the CSV header alone,
 * with no key on any column. At 179,421 detail rows the replay's
 * per-document line fetch (`WHERE docid_fk = ?`, once per each of 30,584
 * documents) became a full scan plus a filesort, and MariaDB 10.11
 * SEGFAULTED in it. Staging run #6 only completed because the two indexes
 * were added by hand -- which meant nothing in the repository would have
 * stopped the next bring-up from crashing the same way.
 */
class LegacyStagingIndexTest extends TestCase
{
    use BuildsLegacyCsvFixtures, PreparesLegacyPilotFence, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyPilotFence();
    }

    private function makeStagingTable(string $table, array $columns): void
    {
        Schema::connection('legacy_pilot')->dropIfExists($table);

        Schema::connection('legacy_pilot')->create($table, function ($blueprint) use ($columns) {
            $blueprint->id('stg_row_id');

            foreach ($columns as $column) {
                $blueprint->text($column)->nullable();
            }
        });
    }

    /**
     * @return list<array{index:string,column:string,seq:int}>
     */
    private function indexesOn(string $table): array
    {
        $database = (string) DB::connection('legacy_pilot')->getDatabaseName();

        return DB::connection('legacy_pilot')->table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->orderBy('index_name')
            ->get(['index_name', 'column_name', 'seq_in_index'])
            ->map(fn ($row) => [
                'index' => (string) $row->index_name,
                'column' => (string) $row->column_name,
                'seq' => (int) $row->seq_in_index,
            ])->all();
    }

    private function explainType(string $sql): string
    {
        $plan = DB::connection('legacy_pilot')->select('EXPLAIN '.$sql);

        return (string) ($plan[0]->type ?? '');
    }

    /**
     * MUTATION PROOF, and the whole point of the change: BEFORE the indexes
     * exist an equality lookup on `docid_fk` is a full scan (`ALL`); after,
     * it is a `ref`. Delete the CREATE INDEX and this test fails on the plan,
     * not on a timing guess.
     */
    public function test_the_hot_lookups_go_from_a_full_scan_to_an_index_ref(): void
    {
        $this->makeStagingTable('stg_acc_detail', ['docid_fk', 'accid_fk', 'debit']);
        $this->makeStagingTable('stg_acc_header', ['docid', 'posted']);

        $this->assertSame('ALL', $this->explainType("SELECT * FROM stg_acc_detail WHERE docid_fk = '1'"));
        $this->assertSame('ALL', $this->explainType("SELECT * FROM stg_acc_detail WHERE accid_fk = '1'"));
        $this->assertSame('ALL', $this->explainType("SELECT * FROM stg_acc_header WHERE docid = '1'"));

        $created = LegacyStagingIndexes::ensure();

        $this->assertEqualsCanonicalizing(
            ['stg_acc_detail.docid_fk', 'stg_acc_detail.accid_fk', 'stg_acc_header.docid'],
            $created,
        );

        $this->assertSame('ref', $this->explainType("SELECT * FROM stg_acc_detail WHERE docid_fk = '1'"));
        $this->assertSame('ref', $this->explainType("SELECT * FROM stg_acc_detail WHERE accid_fk = '1'"));
        $this->assertSame('ref', $this->explainType("SELECT * FROM stg_acc_header WHERE docid = '1'"));
    }

    /**
     * Every staging column is TEXT, which MariaDB cannot index whole. The
     * key is a PREFIX -- and a short one, because these columns hold numeric
     * ids written as text.
     */
    public function test_the_key_is_a_short_prefix_of_the_text_column(): void
    {
        $this->makeStagingTable('stg_acc_detail', ['docid_fk', 'accid_fk']);

        LegacyStagingIndexes::ensure();

        $database = (string) DB::connection('legacy_pilot')->getDatabaseName();

        $subPart = DB::connection('legacy_pilot')->table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'stg_acc_detail')
            ->where('index_name', LegacyStagingIndexes::indexName('stg_acc_detail', 'docid_fk'))
            ->value('sub_part');

        $this->assertSame(LegacyStagingIndexes::PREFIX_LENGTH, (int) $subPart);
    }

    /**
     * The migration re-runs on a staging database that has been loaded for
     * weeks. Creating the same index twice is an error, so a second call must
     * be a no-op.
     */
    public function test_it_is_idempotent(): void
    {
        $this->makeStagingTable('stg_acc_detail', ['docid_fk', 'accid_fk']);

        $this->assertCount(2, LegacyStagingIndexes::ensure());
        $this->assertSame([], LegacyStagingIndexes::ensure());
    }

    /**
     * Staging still carries the hand-made `idx_run6_*` pair from run #6. A
     * second index on the same leading column would double the write cost and
     * the disk for no gain, so an existing leading index is left alone --
     * and `down()` must not drop someone else's index either.
     */
    public function test_it_does_not_duplicate_a_hand_made_index_and_never_drops_one(): void
    {
        $this->makeStagingTable('stg_acc_detail', ['docid_fk', 'accid_fk']);

        DB::connection('legacy_pilot')->statement('CREATE INDEX `idx_run6_docid_fk` ON `stg_acc_detail` (`docid_fk`(768))');

        $this->assertSame(['stg_acc_detail.accid_fk'], LegacyStagingIndexes::ensure(), 'docid_fk already leads an index.');

        LegacyStagingIndexes::drop();

        $names = array_values(array_unique(array_column($this->indexesOn('stg_acc_detail'), 'index')));

        $this->assertContains('idx_run6_docid_fk', $names, 'down() must not drop an index it did not create.');
        $this->assertNotContains(LegacyStagingIndexes::indexName('stg_acc_detail', 'accid_fk'), $names);
    }

    /**
     * The migration runs at bring-up, BEFORE `legacy:load` has created a
     * single staging table. It must not create one, and must not throw.
     */
    public function test_an_unstaged_table_is_skipped_not_created(): void
    {
        Schema::connection('legacy_pilot')->dropIfExists('stg_acc_detail');
        Schema::connection('legacy_pilot')->dropIfExists('stg_acc_header');

        $this->assertSame([], LegacyStagingIndexes::ensure());
        $this->assertFalse(Schema::connection('legacy_pilot')->hasTable('stg_acc_detail'));
    }

    /**
     * The other half of "one spec, two entry points": a table the LOADER has
     * only just created is indexed on the spot, so a fresh bring-up never
     * depends on anyone remembering to re-run the migration afterwards.
     */
    public function test_the_loader_indexes_a_table_it_creates(): void
    {
        $this->makeLegacyFixtureRoot();

        try {
            $path = $this->legacyFixtureRoot.DIRECTORY_SEPARATOR.'ledger-export-2025-2026Q1'.DIRECTORY_SEPARATOR.'tblAccDetail.csv';

            file_put_contents($path, "CompanyID,AccDetailID,DocID_FK,AccID_FK,Debit\n1,1001,7,42,1.000\n");

            $result = app(LegacyCsvLoader::class)->load(
                'tblAccDetail',
                ['table' => 'stg_acc_detail', 'rows' => 1],
                $path,
            );

            $this->assertSame('loaded', $result['status']);
            $this->assertSame('ref', $this->explainType("SELECT * FROM stg_acc_detail WHERE docid_fk = '7'"));
        } finally {
            $this->cleanupLegacyFixtureRoot();
        }
    }
}
