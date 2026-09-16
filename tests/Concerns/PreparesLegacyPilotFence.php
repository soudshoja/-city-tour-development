<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * legacy-ledger-pilot LP1 test helper. Every Legacy* test uses ONLY the
 * fence's `legacy_pilot` connection (which resolves to DB_DATABASE_MAP,
 * i.e. city_tour_test_lp1_map when run per the phase brief's explicit env
 * vars) -- never a real "legacy_pilot"-named database. See
 * LegacyPathGuard::assertQuarantinedConnection(), asserted at the top of
 * setUpLegacyPilotFence() as a belt-and-braces check that a misconfigured
 * local run cannot silently touch a real database.
 */
trait PreparesLegacyPilotFence
{
    /**
     * The newest table of EACH legacy_pilot migration, newest migration last.
     *
     * The probe below cannot key on a single table. A fence database migrated
     * at some earlier point in the phase already has that table and would skip
     * the migrate call entirely, leaving every later legacy_pilot migration
     * permanently pending -- and the symptom is not "pending migration", it is
     * an unrelated test failing on a missing column. LP1 (map_purpose), LP4
     * (parity_run), LP1c (seeded_chart_removed) and LP3 (map_document) each hit
     * this in turn.
     *
     * Any table added by a LATER legacy_pilot migration must be added here for
     * the same reason.
     */
    // CD-PORT: 'ct_scope_counter' appended per this docblock's own standing instruction — it is
    // the newest legacy_pilot migration's newest table (2026_09_16_000001_create_ct_scope_tables).
    // Without it a fence migrated before that migration existed would skip the migrate call and
    // fail later on a missing table rather than on a pending migration.
    private const FENCE_MIGRATION_MARKERS = ['map_purpose', 'parity_run', 'seeded_chart_removed', 'map_document', 'seeded_chart_removal_run', 'ct_scope_counter', 'ct_scope_row'];

    /**
     * The same probe for a migration that only ADDS COLUMNS, and so introduces
     * no new table to key on. [table, column], newest migration last. LP1e
     * (map_currency.status) is the first of these.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const FENCE_MIGRATION_COLUMN_MARKERS = [['map_currency', 'status']];

    /** Every table the fence owns, truncated between tests. */
    private const FENCE_TABLES = [
        'legacy_acc_map', 'map_purpose', 'map_branch', 'map_currency', 'map_party',
        'legacy_load_audit', 'legacy_load_manifest', 'legacy_masters_audit',
        'parity_diff', 'parity_run', 'seeded_chart_removed', 'seeded_chart_fk_nulled',
        'map_document', 'map_document_line', 'map_allocation',
        'seeded_chart_removal_run',
        // CD-PORT
        'ct_scope_counter', 'ct_scope_run', 'ct_scope_row', 'ct_scope_fingerprint',
    ];

    protected function setUpLegacyPilotFence(): void
    {
        \App\Services\Onboarding\LegacyPathGuard::assertQuarantinedConnection('legacy_pilot');

        $stale = false;

        foreach (self::FENCE_MIGRATION_MARKERS as $marker) {
            if (! Schema::connection('legacy_pilot')->hasTable($marker)) {
                $stale = true;

                break;
            }
        }

        if (! $stale) {
            foreach (self::FENCE_MIGRATION_COLUMN_MARKERS as [$table, $column]) {
                if (! Schema::connection('legacy_pilot')->hasColumn($table, $column)) {
                    $stale = true;

                    break;
                }
            }
        }

        if ($stale) {
            $this->rebuildLegacyPilotFence();
        }

        foreach (self::FENCE_TABLES as $table) {
            if (Schema::connection('legacy_pilot')->hasTable($table)) {
                DB::connection('legacy_pilot')->table($table)->truncate();
            }
        }

        $stgTables = DB::connection('legacy_pilot')->select("SHOW TABLES LIKE 'stg\\_%'");
        $dbKey = 'Tables_in_'.config('database.connections.legacy_pilot.database');

        foreach ($stgTables as $row) {
            $name = $row->$dbKey ?? current((array) $row);
            Schema::connection('legacy_pilot')->dropIfExists($name);
        }
    }

    /**
     * Repair a fence whose SCHEMA and whose MIGRATION LOG disagree, then
     * migrate it.
     *
     * Plain `migrate` cannot do this. Several LP4 parity tests deliberately
     * `dropIfExists('map_document')` to exercise "what if LP3 has not run yet",
     * and nothing puts it back -- but the `migrations` row for
     * 2026_09_08_000001 is still there, so `migrate` correctly considers the
     * work done and creates nothing. Every LP3 test that runs afterwards in the
     * same fence database then fails on a missing table, and the symptom points
     * at LP3 rather than at the LP4 test that caused it. Because the two lanes
     * share one fence database and PHPUnit's file order is not fixed, whether
     * the suite is green depends on which file ran first.
     *
     * So: drop every table this path owns, delete only ITS OWN migration rows
     * (never the `migrations` table itself -- the map connection's own
     * migrations are logged there too), and re-run the path from clean.
     */
    private function rebuildLegacyPilotFence(): void
    {
        $connection = DB::connection('legacy_pilot');

        Schema::connection('legacy_pilot')->disableForeignKeyConstraints();

        foreach (self::FENCE_TABLES as $table) {
            Schema::connection('legacy_pilot')->dropIfExists($table);
        }

        Schema::connection('legacy_pilot')->enableForeignKeyConstraints();

        if (Schema::connection('legacy_pilot')->hasTable('migrations')) {
            // Derived from the directory rather than listed, so a lane adding a
            // fifth legacy_pilot migration does not have to remember this file
            // -- the failure it would cause otherwise is the same silent,
            // order-dependent one described above.
            $owned = array_map(
                static fn (string $path): string => basename($path, '.php'),
                glob(base_path('database/migrations/legacy_pilot/*.php')) ?: []
            );

            if ($owned !== []) {
                $connection->table('migrations')->whereIn('migration', $owned)->delete();
            }
        }

        Artisan::call('migrate', [
            '--database' => 'legacy_pilot',
            '--path' => 'database/migrations/legacy_pilot',
            '--force' => true,
        ]);
    }
}
