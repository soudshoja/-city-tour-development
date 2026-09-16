<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Services\Onboarding\Parity\ParityHarness;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsParityFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP4 -- the doc_counts check against the map_document
 * schema LP3 ACTUALLY CREATES.
 *
 * WHY THIS FILE EXISTS SEPARATELY FROM LegacyParityChecksTest. That file's
 * `seedCensusTables()` builds map_document from column names LP4 invented
 * for itself (`legacy_sub_type`, `legacy_doc_dt`). LP3's migration
 * (database/migrations/legacy_pilot/2026_09_08_000001_create_legacy_replay_
 * tables.php on feat/lp3-legacy-replay) names those columns `sub_type` and
 * `doc_dt`. A fixture that agrees with the reader and not with the writer
 * proves nothing: the census only ever ran against a table LP4 built for
 * itself, and would have thrown SQLSTATE[42S22] "Unknown column
 * 'legacy_sub_type'" the first time LP3 landed -- aborting the entire
 * parity run, not merely skipping one check.
 *
 * Every fixture below therefore mirrors LP3's real column names, including
 * the `engine_sub_type` / `sub_type` distinction that LP3's own docblock
 * warns about (`sub_type` is the LEGACY token 'INV'; `engine_sub_type` is
 * the Akeed 'LEGACY_INV'), and the `skipped_unposted` status LP3 writes for
 * a Posted=0 header.
 */
class LegacyParityLp3ContractTest extends TestCase
{
    use BuildsParityFixtures, PreparesLegacyPilotFence, RefreshDatabase;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyPilotFence();
        $this->companyId = $this->makeParityCompany();
        $this->buildBalancedParityWorld($this->companyId);
    }

    private function docCounts(): array
    {
        $result = app(ParityHarness::class)->run($this->companyId, CarbonImmutable::parse('2025-12-31'), ParityHarness::ANCHOR_ALL);

        foreach ($result['checks'] as $check) {
            if ($check['key'] === 'doc_counts') {
                return $check;
            }
        }

        $this->fail('No doc_counts check.');
    }

    /**
     * DEFECT 1 (schema drift). The census must read the columns LP3 writes.
     *
     * MUTATION PROOF: point DocumentCensus::akeedCensus() back at
     * `legacy_sub_type` / `legacy_doc_dt` and this test dies with
     * SQLSTATE[42S22] Unknown column -- an exception, not a skip, so the
     * whole `legacy:parity` run aborts and every other check is lost.
     */
    public function test_the_census_reads_the_map_document_columns_lp3_creates(): void
    {
        $this->seedLp3CensusTables(
            legacy: [
                ['subtype' => 'INV', 'docdt' => '2025-06-15', 'posted' => 'True', 'docid' => '900'],
                ['subtype' => 'INV', 'docdt' => '2025-07-02', 'posted' => 'True', 'docid' => '901'],
            ],
            ours: [
                ['sub_type' => 'INV', 'engine_sub_type' => 'LEGACY_INV', 'doc_dt' => '2025-06-15', 'status' => 'posted', 'legacy_doc_id' => 900],
                ['sub_type' => 'INV', 'engine_sub_type' => 'LEGACY_INV', 'doc_dt' => '2025-07-02', 'status' => 'posted', 'legacy_doc_id' => 901],
            ],
        );

        $check = $this->docCounts();

        $this->assertSame('pass', $check['status'], json_encode($check['diffs']));
        $this->assertSame(2, $check['summary']['legacy_total']);
        $this->assertSame(2, $check['summary']['akeed_total']);
    }

    /**
     * The census must group on LP3's LEGACY token (`sub_type` = 'INV'), not
     * on its Akeed-side twin (`engine_sub_type` = 'LEGACY_INV'), or every
     * bucket key misses the stg_acc_header side and every month fails.
     */
    public function test_the_census_groups_on_the_legacy_sub_type_not_the_engine_one(): void
    {
        $this->seedLp3CensusTables(
            legacy: [['subtype' => 'INV', 'docdt' => '2025-06-15', 'posted' => 'True', 'docid' => '900']],
            ours: [['sub_type' => 'INV', 'engine_sub_type' => 'LEGACY_INV', 'doc_dt' => '2025-06-15', 'status' => 'posted', 'legacy_doc_id' => 900]],
        );

        $check = $this->docCounts();

        $this->assertSame('pass', $check['status'], json_encode($check['diffs']));
        $this->assertSame('INV', $check['diffs'] === [] ? 'INV' : $check['diffs'][0]['detail']);
    }

    /**
     * DEFECT 2 (the unposted double standard). PLAN.md §5.2 O11 excludes the
     * 37 Posted=0 headers from the census; LP3 nonetheless records each of
     * them as a `skipped_unposted` row in map_document (that is what
     * "exclude, COUNT, report" means). Summing every map_document status
     * into "accounted for" while filtering the legacy side to Posted-only
     * therefore over-counts our side by exactly the unposted population,
     * and every month holding one fails with a +1 delta on a replay that is
     * completely correct.
     *
     * MUTATION PROOF: drop the unposted-status exclusion from
     * DocumentCensus::compare() and this test fails with delta +1 on
     * INV 2025-07.
     */
    public function test_an_unposted_header_reconciles_against_lp3s_skipped_unposted_row(): void
    {
        $this->seedLp3CensusTables(
            legacy: [
                ['subtype' => 'INV', 'docdt' => '2025-07-02', 'posted' => 'True', 'docid' => '900'],
                ['subtype' => 'INV', 'docdt' => '2025-07-03', 'posted' => 'False', 'docid' => '901'],
            ],
            ours: [
                ['sub_type' => 'INV', 'engine_sub_type' => 'LEGACY_INV', 'doc_dt' => '2025-07-02', 'status' => 'posted', 'legacy_doc_id' => 900],
                ['sub_type' => 'INV', 'engine_sub_type' => 'LEGACY_INV', 'doc_dt' => '2025-07-03', 'status' => 'skipped_unposted', 'legacy_doc_id' => 901],
            ],
        );

        $check = $this->docCounts();

        $this->assertSame('pass', $check['status'], json_encode($check['diffs']));
        $this->assertSame(1, $check['summary']['legacy_total'], 'O11: the Posted=0 header is out of the posted census.');
        $this->assertSame(1, $check['summary']['akeed_total'], 'Our skipped_unposted row is excluded from the same side of the identity.');
        $this->assertSame(1, $check['summary']['unposted_total'], 'O11 says exclude, COUNT and report — the count must still be visible.');
    }

    /**
     * "Exclude and count" must be a comparison, not a shrug: a header the
     * legacy side says is unposted and that LP3 never recorded at all is a
     * document that vanished, and the census is the only check that can see
     * it.
     */
    public function test_an_unposted_header_lp3_never_recorded_is_a_failure(): void
    {
        $this->seedLp3CensusTables(
            legacy: [
                ['subtype' => 'INV', 'docdt' => '2025-07-02', 'posted' => 'True', 'docid' => '900'],
                ['subtype' => 'INV', 'docdt' => '2025-07-03', 'posted' => 'False', 'docid' => '901'],
            ],
            ours: [
                ['sub_type' => 'INV', 'engine_sub_type' => 'LEGACY_INV', 'doc_dt' => '2025-07-02', 'status' => 'posted', 'legacy_doc_id' => 900],
            ],
        );

        $check = $this->docCounts();

        $this->assertSame('fail', $check['status']);

        $details = implode(' ', array_column($check['diffs'], 'detail'));
        $this->assertStringContainsString('unposted', $details);
        $this->assertStringContainsString('INV 2025-07', $details);
    }

    /**
     * A map_document that carries NEITHER naming is a contract the harness
     * cannot honour. It must SKIP with a reason that names the columns it
     * looked for — never throw (which loses the other six checks), and
     * never silently pass.
     */
    public function test_a_map_document_with_neither_column_naming_is_skipped_with_a_reason(): void
    {
        $this->seedLp3CensusTables(legacy: [['subtype' => 'INV', 'docdt' => '2025-06-15', 'posted' => 'True', 'docid' => '900']], ours: []);

        Schema::connection('legacy_pilot')->dropIfExists('map_document');
        Schema::connection('legacy_pilot')->create('map_document', function ($table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('status');
        });

        $check = $this->docCounts();

        $this->assertSame('skipped', $check['status']);
        $this->assertStringContainsString('sub_type', $check['summary']['reason']);
        $this->assertStringContainsString('doc_dt', $check['summary']['reason']);
    }

    /**
     * @param  list<array<string, mixed>>  $legacy
     * @param  list<array<string, mixed>>  $ours
     */
    private function seedLp3CensusTables(array $legacy, array $ours): void
    {
        Schema::connection('legacy_pilot')->dropIfExists('stg_acc_header');
        Schema::connection('legacy_pilot')->create('stg_acc_header', function ($table) {
            $table->id();
            $table->text('docid')->nullable();
            $table->text('subtype')->nullable();
            $table->text('docdt')->nullable();
            $table->text('posted')->nullable();
        });
        DB::connection('legacy_pilot')->table('stg_acc_header')->insert($legacy);

        // Mirrors feat/lp3-legacy-replay's
        // 2026_09_08_000001_create_legacy_replay_tables.php, columns only.
        Schema::connection('legacy_pilot')->dropIfExists('map_document');
        Schema::connection('legacy_pilot')->create('map_document', function ($table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('legacy_doc_id');
            $table->string('legacy_doc_no')->nullable();
            $table->string('sub_type', 32)->nullable();
            $table->string('engine_sub_type', 32)->nullable();
            $table->string('legacy_doc_type', 32)->nullable();
            $table->date('doc_dt')->nullable();
            $table->string('status', 32);
            $table->string('refusal_class', 32)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
        });

        if ($ours !== []) {
            DB::connection('legacy_pilot')->table('map_document')->insert(array_map(
                fn ($row) => $row + ['company_id' => $this->companyId],
                $ours,
            ));
        }
    }

    protected function tearDown(): void
    {
        foreach (['stg_acc_header', 'map_document'] as $table) {
            Schema::connection('legacy_pilot')->dropIfExists($table);
        }

        parent::tearDown();
    }
}
