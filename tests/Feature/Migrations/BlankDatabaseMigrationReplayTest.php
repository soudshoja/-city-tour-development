<?php

declare(strict_types=1);

namespace Tests\Feature\Migrations;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * CT-A10 — the blank-database replay gate, and the ONLY test in this suite that executes a
 * migration.
 *
 * ── Why it exists ──────────────────────────────────────────────────────────────────────────────
 * PR #24 (the per-artefact migration guards) was merged on the strength of a proof run **by hand**,
 * because CT has no automated gate that could have run it: `.github/workflows/tests.yml` is scoped
 * to `branches: [main, dev]` while every accounting PR targets `feat/accounting-dev-line`, so CI
 * reported **0 checks**; and the similarly-named `LegacyMigrationRatchetTest` is a filesystem check
 * that invokes no migration. This is that gate, and it is CT's half of Akeed's own
 * `BlankDatabaseMigrationReplayTest` (PLAN.md §0.6 step 3).
 *
 * ── What it covers that Akeed's does not ───────────────────────────────────────────────────────
 * Akeed's proves shape 1 — the full set replays into a genuinely blank database. This adds the shape
 * that actually motivated #24 and that nothing anywhere exercises:
 *
 *   PHASE 1  migrate from empty                  -> exit 0, nothing Pending
 *   PHASE 2  the HALF-APPLIED shape              -> drop the SECOND artefact of a multi-artefact
 *                                                   migration, delete its `migrations` row, re-run,
 *                                                   and the artefact must come back
 *   PHASE 3  complete-schema replay              -> delete the rows for the whole guarded set on an
 *                                                   already-complete schema, re-run, and the schema
 *                                                   must be byte-identical with no duplicate indexes
 *
 * Phase 2 is the one that matters. Laravel emits **one statement per added column and per index**,
 * and DDL is non-transactional on MariaDB, so a crash can leave any subset applied with no
 * `migrations` row to record it. A guard that asked only about the FIRST artefact would return early
 * on that schema and mark the migration done with the rest permanently missing — which is why #24
 * guards per artefact, and why this phase drops the *second* column rather than the first.
 *
 * ── Runtime, and why this is ONE test method rather than three ──────────────────────────────────
 * The expensive part is the single migrate-from-blank over CT's ~500-migration set; the two replays
 * after it are seconds. PHPUnit gives each test method its own fixture, so splitting the phases
 * would pay that cost three times over. All three phases therefore run in sequence against one
 * throwaway database, each with its own phase-named assertion messages so a failure still says which
 * phase broke. Measured cost is recorded in CT-A10-SUITE-HEALTH-2026-09-17.md.
 *
 * ── Safety ─────────────────────────────────────────────────────────────────────────────────────
 * Runs against a THROWAWAY pair named by suffixing whatever this run's already-fenced
 * `mysql_testing`/`mysql_map` databases resolve to with `_replay`, so it can never disturb the
 * ambient fence and inherits its per-agent isolation. It refuses to run at all unless both resolved
 * names start with `city_tour_test`, mirroring `Tests\Concerns\GuardsTestDatabaseIsolation` — belt
 * and braces over the base TestCase guard that already ran in setUp(). Deliberately does NOT use
 * `RefreshDatabase`: the ambient fence is never touched.
 */
class BlankDatabaseMigrationReplayTest extends TestCase
{
    /**
     * The guarded set PR #24 covers, and the artefacts phase 2 removes.
     *
     * `000001` is the two-column migration (`payable_trigger` then `payable_hold`) and `000021`
     * creates a table with four indexes — between them they cover both artefact kinds a partial
     * apply can strand.
     */
    private const GUARDED_MIGRATIONS = [
        '2026_09_09_000001_add_payable_trigger_to_suppliers_table',
        '2026_09_09_000002_add_settlement_channel_to_invoice_receipts_table',
        '2026_09_09_000003_add_refund_trigger_to_suppliers_table',
        '2026_09_09_000004_add_cancellation_fee_to_supplier_charge_rules',
        '2026_09_09_000020_create_coa_linkage_findings_table',
        '2026_09_09_000021_create_coa_linkage_changes_table',
        '2026_09_10_000010_widen_coa_linkage_change_values',
    ];

    /** The SECOND column of a two-column migration — never the first. */
    private const SECOND_COLUMN_TABLE = 'suppliers';

    private const SECOND_COLUMN = 'payable_hold';

    private const SECOND_COLUMN_MIGRATION = '2026_09_09_000001_add_payable_trigger_to_suppliers_table';

    /** An index created alongside a table, so the table survives while the index does not. */
    private const DROPPED_INDEX_TABLE = 'coa_linkage_changes';

    private const DROPPED_INDEX = 'coa_lc_subject_idx';

    private const DROPPED_INDEX_MIGRATION = '2026_09_09_000021_create_coa_linkage_changes_table';

    private string $replayDefaultDb;

    private string $replayMapDb;

    private ?string $originalDefaultDb = null;

    private ?string $originalMapDb = null;

    protected function setUp(): void
    {
        parent::setUp();

        $baseDefaultDb = (string) config('database.connections.mysql_testing.database');
        $baseMapDb = (string) config('database.connections.mysql_map.database');

        foreach ([$baseDefaultDb, $baseMapDb] as $name) {
            if (! str_starts_with($name, 'city_tour_test')) {
                $this->fail(
                    "Refusing to run: resolved test database '{$name}' does not start with "
                    ."'city_tour_test'. This ratchet only ever operates on disposable fenced "
                    .'databases derived from the current test fence.'
                );
            }
        }

        $this->replayDefaultDb = $baseDefaultDb.'_replay';
        $this->replayMapDb = $baseMapDb.'_replay';
    }

    protected function tearDown(): void
    {
        // Repoint back to the ambient fence BEFORE dropping, so the drop runs on a connection that
        // is not itself inside the database being removed.
        if ($this->originalDefaultDb !== null) {
            config(['database.connections.mysql_testing.database' => $this->originalDefaultDb]);
        }
        if ($this->originalMapDb !== null) {
            config(['database.connections.mysql_map.database' => $this->originalMapDb]);
        }
        DB::purge('mysql_testing');
        DB::purge('mysql_map');

        DB::connection('mysql_testing')->statement('DROP DATABASE IF EXISTS `'.$this->replayDefaultDb.'`');
        DB::connection('mysql_testing')->statement('DROP DATABASE IF EXISTS `'.$this->replayMapDb.'`');

        parent::tearDown();
    }

    public function test_the_migration_set_replays_from_blank_and_recovers_a_half_applied_run(): void
    {
        $this->pointAtThrowawayDatabases();

        // ── PHASE 1: migrate from empty ────────────────────────────────────────────────────────
        $this->assertSame(
            [],
            DB::connection('mysql_testing')->select('SHOW TABLES'),
            "PHASE 1: throwaway database '{$this->replayDefaultDb}' was not blank before migrating; "
                .'a previous run may have failed to clean up.'
        );

        $exit = Artisan::call('migrate', ['--database' => 'mysql_testing', '--force' => true]);
        $this->assertSame(0, $exit, "PHASE 1: migrate from blank failed:\n".Artisan::output());

        Artisan::call('migrate:status', ['--database' => 'mysql_testing']);
        $this->assertStringNotContainsString(
            'Pending',
            Artisan::output(),
            'PHASE 1: migrations are still pending after a full migrate --force from blank.'
        );

        $this->assertTrue(
            $this->hasColumn(self::SECOND_COLUMN_TABLE, self::SECOND_COLUMN),
            'PHASE 1 precondition: the second column must exist before phase 2 can remove it.'
        );
        $this->assertTrue(
            $this->hasIndex(self::DROPPED_INDEX_TABLE, self::DROPPED_INDEX),
            'PHASE 1 precondition: the index must exist before phase 2 can remove it.'
        );

        // ── PHASE 2: the HALF-APPLIED shape ────────────────────────────────────────────────────
        // Drop the SECOND artefact of each migration, keeping the first — the state a crash between
        // Laravel's per-artefact statements leaves behind — and delete the `migrations` rows, which
        // a crashed run never wrote.
        $this->statement('ALTER TABLE `'.self::SECOND_COLUMN_TABLE.'` DROP COLUMN `'.self::SECOND_COLUMN.'`');
        $this->statement('ALTER TABLE `'.self::DROPPED_INDEX_TABLE.'` DROP INDEX `'.self::DROPPED_INDEX.'`');
        $this->forgetMigrations([self::SECOND_COLUMN_MIGRATION, self::DROPPED_INDEX_MIGRATION]);

        $this->assertFalse($this->hasColumn(self::SECOND_COLUMN_TABLE, self::SECOND_COLUMN), 'PHASE 2 setup: column not removed.');
        $this->assertFalse($this->hasIndex(self::DROPPED_INDEX_TABLE, self::DROPPED_INDEX), 'PHASE 2 setup: index not removed.');
        $this->assertTrue(
            $this->hasColumn(self::SECOND_COLUMN_TABLE, 'payable_trigger'),
            'PHASE 2 setup: the FIRST column must survive — that is what makes this a HALF-applied run.'
        );

        $exit = Artisan::call('migrate', ['--database' => 'mysql_testing', '--force' => true]);
        $this->assertSame(0, $exit, "PHASE 2: re-running migrate over a half-applied schema failed:\n".Artisan::output());

        $this->assertTrue(
            $this->hasColumn(self::SECOND_COLUMN_TABLE, self::SECOND_COLUMN),
            'PHASE 2: `'.self::SECOND_COLUMN_TABLE.'.'.self::SECOND_COLUMN.'` did NOT come back. A guard '
                .'that checks only the FIRST artefact returns early here and marks the migration done '
                .'with this column permanently missing — that is the defect PR #24 exists to prevent.'
        );
        $this->assertTrue(
            $this->hasIndex(self::DROPPED_INDEX_TABLE, self::DROPPED_INDEX),
            'PHASE 2: index `'.self::DROPPED_INDEX.'` did NOT come back on `'.self::DROPPED_INDEX_TABLE.'`.'
        );
        $this->assertSame(0, $this->duplicateIndexCount(self::DROPPED_INDEX_TABLE), 'PHASE 2: duplicate indexes after recovery.');

        // ── PHASE 3: complete-schema replay ────────────────────────────────────────────────────
        // Every guarded migration replayed against a schema that already has all of it must be a
        // clean no-op — this is the "someone truncated `migrations`" / re-deploy case.
        $before = $this->schemaSnapshot();
        $this->forgetMigrations(self::GUARDED_MIGRATIONS);

        $exit = Artisan::call('migrate', ['--database' => 'mysql_testing', '--force' => true]);
        $output = Artisan::output();
        $this->assertSame(0, $exit, "PHASE 3: replaying the guarded set on a complete schema failed:\n".$output);
        $this->assertStringNotContainsString('SQLSTATE', $output, "PHASE 3: a SQL error surfaced during the replay:\n".$output);

        $this->assertSame(
            $before,
            $this->schemaSnapshot(),
            'PHASE 3: replaying the guarded set on a complete schema changed the schema; it must be a clean no-op.'
        );

        foreach (['coa_linkage_findings', 'coa_linkage_changes'] as $table) {
            $this->assertSame(0, $this->duplicateIndexCount($table), "PHASE 3: duplicate indexes on `{$table}`.");
        }
    }

    // ── helpers ─────────────────────────────────────────────────────────────────────────────────

    private function pointAtThrowawayDatabases(): void
    {
        $this->originalDefaultDb = (string) config('database.connections.mysql_testing.database');
        $this->originalMapDb = (string) config('database.connections.mysql_map.database');

        DB::connection('mysql_testing')->statement(
            'CREATE DATABASE IF NOT EXISTS `'.$this->replayDefaultDb.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
        DB::connection('mysql_testing')->statement(
            'CREATE DATABASE IF NOT EXISTS `'.$this->replayMapDb.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        config([
            'database.connections.mysql_testing.database' => $this->replayDefaultDb,
            'database.connections.mysql_map.database' => $this->replayMapDb,
        ]);
        DB::purge('mysql_testing');
        DB::purge('mysql_map');
    }

    private function statement(string $sql): void
    {
        DB::connection('mysql_testing')->statement($sql);
    }

    /** @param string[] $migrations */
    private function forgetMigrations(array $migrations): void
    {
        DB::connection('mysql_testing')->table('migrations')->whereIn('migration', $migrations)->delete();
    }

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::connection('mysql_testing')->hasColumn($table, $column);
    }

    private function hasIndex(string $table, string $index): bool
    {
        return array_key_exists($index, $this->indexesOf($table));
    }

    /**
     * Index name => ordered column list, from `information_schema`.
     *
     * Deliberately NOT `SHOW INDEX`: MariaDB does not accept bound parameters inside a `SHOW`
     * statement (`SHOW COLUMNS ... LIKE ?` fails with a 1064), and interpolating names into DDL-ish
     * SQL in a test is the habit worth not forming. `information_schema` binds properly and gives a
     * deterministic order for free.
     *
     * @return array<string, list<string>>
     */
    private function indexesOf(string $table): array
    {
        $rows = DB::connection('mysql_testing')->select(
            'SELECT INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS '
                .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$table]
        );

        $byName = [];

        foreach ($rows as $row) {
            $byName[$row->INDEX_NAME][] = $row->COLUMN_NAME;
        }

        return $byName;
    }

    /** Indexes whose own column list repeats a column — the signature of a doubly-applied index. */
    private function duplicateIndexCount(string $table): int
    {
        $duplicates = 0;

        foreach ($this->indexesOf($table) as $columns) {
            if (count($columns) !== count(array_unique($columns))) {
                $duplicates++;
            }
        }

        return $duplicates;
    }

    /**
     * Every artefact the guarded set is responsible for: the six columns it adds, both created
     * tables' full index sets, and the two column types `000010` widens. Ordered deterministically
     * so the phase-3 comparison is a plain equality.
     *
     * @return array<string, string>
     */
    private function schemaSnapshot(): array
    {
        $snapshot = [];

        foreach ([
            ['suppliers', 'payable_trigger'], ['suppliers', 'payable_hold'],
            ['suppliers', 'refund_trigger'], ['suppliers', 'refund_hold'],
            ['invoice_receipts', 'settlement_channel'],
            ['supplier_charge_rules', 'cancellation_fee_percent'],
            ['coa_linkage_changes', 'before_value'], ['coa_linkage_changes', 'after_value'],
        ] as [$table, $column]) {
            $definition = DB::connection('mysql_testing')->select(
                'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS '
                    .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            );

            $snapshot["column:{$table}.{$column}"] = $definition === []
                ? 'ABSENT'
                : $definition[0]->COLUMN_TYPE.'|null='.$definition[0]->IS_NULLABLE
                    .'|default='.var_export($definition[0]->COLUMN_DEFAULT, true);
        }

        foreach (['coa_linkage_findings', 'coa_linkage_changes'] as $table) {
            $byName = $this->indexesOf($table);
            ksort($byName);

            foreach ($byName as $name => $columns) {
                $snapshot["index:{$table}.{$name}"] = implode(',', $columns);
            }

            $snapshot["indexcount:{$table}"] = (string) count($byName);
        }

        return $snapshot;
    }
}
