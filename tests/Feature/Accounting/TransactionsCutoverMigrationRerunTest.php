<?php

namespace Tests\Feature\Accounting;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Rerun-safety coverage for the transactions-cutover split migrations:
 *   - 2026_08_24_000001_add_dedup_unique_indexes_for_payment_race_hotfixes.php (payments half)
 *   - 2026_08_24_000002_add_post_cutover_dedup_key_to_transactions_table.php (transactions half)
 *
 * WHY THIS DELIBERATELY DOES NOT EXTEND AccountingTestCase / use RefreshDatabase, unlike the rest
 * of the accounting suite. Both migrations' up()/down() run real DDL (ALTER TABLE ... ADD
 * COLUMN/INDEX, DROP COLUMN/INDEX). MySQL/MariaDB DDL statements implicit-commit — running one
 * mid-test inside RefreshDatabase's per-test transaction wrapper would silently commit that
 * wrapper's transaction out from under it, leaving tearDown()'s rollback attempting to roll back a
 * transaction that no longer exists. This repo already has an established, working pattern for
 * testing real migration DDL for exactly this reason —
 * tests/Unit/Modules/DotwAI/WidenZipCodeMigrationTest.php — this file follows the same shape
 * (extend the base TestCase directly, no RefreshDatabase, real DDL against the real connection).
 * Unlike that file, this one does not need to manually bootstrap the app or hand-roll a throwaway
 * table: `Tests\TestCase` already boots the full application per Laravel's own
 * Illuminate\Foundation\Testing\TestCase, and `payments`/`transactions` already exist because
 * `php artisan migrate:fresh` is run once, out of band, before this suite (see the isolated test
 * DB pair convention in phpunit.xml's own top-of-file comment: DB_TEST_DATABASE/DB_DATABASE_MAP).
 *
 * The safety rail this still gets for free: Tests\TestCase::setUp() calls
 * guardTestDatabaseIsolation() before anything else, so this test (like every other test in this
 * suite) refuses to run at all unless the resolved mysql_testing/mysql_map/accounting_audit
 * connections all resolve to a disposable "city_tour_test..." database.
 */
class TransactionsCutoverMigrationRerunTest extends TestCase
{
    private const MIGRATION_1_PATH = 'database/migrations/2026_08_24_000001_add_dedup_unique_indexes_for_payment_race_hotfixes.php';

    private const MIGRATION_2_PATH = 'database/migrations/2026_08_24_000002_add_post_cutover_dedup_key_to_transactions_table.php';

    private const MIGRATION_1_NAME = '2026_08_24_000001_add_dedup_unique_indexes_for_payment_race_hotfixes';

    private const MIGRATION_2_NAME = '2026_08_24_000002_add_post_cutover_dedup_key_to_transactions_table';

    private const PAYMENTS_INDEX = 'payments_company_id_voucher_number_unique';

    private const TRANSACTIONS_COLUMN = 'payment_ref_dedup_key';

    private const TRANSACTIONS_INDEX = 'transactions_payment_ref_dedup_key_unique';

    private function loadMigration(string $relativePath): Migration
    {
        // Plain require (matching Illuminate\Database\Migrations\Migrator::resolve(), which also
        // uses a plain require, not require_once): each call re-executes the file and returns a
        // FRESH anonymous-class instance, safe to call multiple times in one process.
        return require base_path($relativePath);
    }

    /**
     * Restores whatever rows this test's own manipulation of the `migrations` table removed, so
     * this test's cleanup leaves the schema/bookkeeping consistent for anything that runs after
     * it in the same process (e.g. a later `php artisan migrate` in the same suite run).
     */
    private function ensureMigrationsTableRowsPresent(): void
    {
        $missing = array_diff(
            [self::MIGRATION_1_NAME, self::MIGRATION_2_NAME],
            DB::table('migrations')->whereIn('migration', [self::MIGRATION_1_NAME, self::MIGRATION_2_NAME])->pluck('migration')->all()
        );

        if ($missing === []) {
            return;
        }

        $nextBatch = ((int) DB::table('migrations')->max('batch')) + 1;
        DB::table('migrations')->insert(array_map(
            fn (string $migration) => ['migration' => $migration, 'batch' => $nextBatch],
            $missing
        ));
    }

    protected function tearDown(): void
    {
        // Best-effort restoration -- if a test left the schema in a state other than "everything
        // present" (it should not, but a failing assertion could abort a test mid-sequence), make
        // sure both migrations' schema objects exist again so later tests in this same process are
        // not affected by this file's own destructive-DDL testing.
        $migration1 = $this->loadMigration(self::MIGRATION_1_PATH);
        $migration2 = $this->loadMigration(self::MIGRATION_2_PATH);
        $migration1->up();
        $migration2->up();
        $this->ensureMigrationsTableRowsPresent();

        parent::tearDown();
    }

    /**
     * Baseline sanity check: `php artisan migrate:fresh`, run out of band before this suite (per
     * this file's own class docblock), must have left BOTH migrations' schema objects present and
     * BOTH migrations' rows recorded. If this fails, every other test below is testing recovery
     * from the wrong starting state.
     */
    public function test_baseline_both_migrations_schema_objects_and_rows_are_present_after_migrate_fresh(): void
    {
        $this->assertTrue(Schema::hasIndex('payments', self::PAYMENTS_INDEX));
        $this->assertTrue(Schema::hasColumn('transactions', self::TRANSACTIONS_COLUMN));
        $this->assertTrue(Schema::hasIndex('transactions', self::TRANSACTIONS_INDEX));
        $this->assertSame(
            [self::MIGRATION_1_NAME, self::MIGRATION_2_NAME],
            DB::table('migrations')
                ->whereIn('migration', [self::MIGRATION_1_NAME, self::MIGRATION_2_NAME])
                ->orderBy('migration')
                ->pluck('migration')
                ->all()
        );
    }

    /**
     * Plain rerun-safety: calling up() again on a fully-applied schema, with the migrations-table
     * rows still present, must not error and must leave the schema exactly as it was.
     */
    public function test_up_is_idempotent_on_a_fully_applied_schema(): void
    {
        $migration1 = $this->loadMigration(self::MIGRATION_1_PATH);
        $migration2 = $this->loadMigration(self::MIGRATION_2_PATH);

        $migration1->up();
        $migration1->up();
        $migration2->up();
        $migration2->up();

        $this->assertTrue(Schema::hasIndex('payments', self::PAYMENTS_INDEX));
        $this->assertTrue(Schema::hasColumn('transactions', self::TRANSACTIONS_COLUMN));
        $this->assertTrue(Schema::hasIndex('transactions', self::TRANSACTIONS_INDEX));
    }

    /**
     * THE EXACT HISTORICAL PARTIAL-FAILURE SHAPE this split was built to survive: the `payments`
     * index (migration 000001) landed and its migrations-table row is intact; migration 000002's
     * schema objects are entirely ABSENT and its migrations-table row was never recorded (a batch
     * that failed before 000002's own DDL ever ran) -- reproduced here by down()ing only migration
     * 000002 and deleting BOTH rows from `migrations` (mirroring the real symptom: a failed batch
     * records no row for anything in that batch, including the migration that DID succeed).
     *
     * Migration 000001's up(), rerun with no migrations-table row and its index already present,
     * must be a safe no-op. Migration 000002's up(), rerun against its own fully-absent schema
     * state, must recreate both the column and the index cleanly. A THIRD round of up() calls,
     * immediately after recovery, must still be a no-op -- proving the guard holds on both sides
     * of the recovery, not just before it broke.
     */
    public function test_recovers_from_the_original_partial_failure_shape_payments_landed_transactions_missing_no_rows(): void
    {
        $migration1 = $this->loadMigration(self::MIGRATION_1_PATH);
        $migration2 = $this->loadMigration(self::MIGRATION_2_PATH);

        $migration2->down();
        DB::table('migrations')->whereIn('migration', [self::MIGRATION_1_NAME, self::MIGRATION_2_NAME])->delete();

        $this->assertTrue(Schema::hasIndex('payments', self::PAYMENTS_INDEX), 'payments index must still be present after down()ing only migration 000002');
        $this->assertFalse(Schema::hasColumn('transactions', self::TRANSACTIONS_COLUMN));
        $this->assertFalse(Schema::hasIndex('transactions', self::TRANSACTIONS_INDEX));

        // Recovery round 1: 000001 must no-op cleanly despite having no migrations-table row.
        $migration1->up();
        $this->assertTrue(Schema::hasIndex('payments', self::PAYMENTS_INDEX));

        // Recovery round 1: 000002 must recreate both schema objects from a fully-absent state.
        $migration2->up();
        $this->assertTrue(Schema::hasColumn('transactions', self::TRANSACTIONS_COLUMN));
        $this->assertTrue(Schema::hasIndex('transactions', self::TRANSACTIONS_INDEX));

        // Recovery round 2 (immediately after): both must now be pure no-ops.
        $migration1->up();
        $migration2->up();
        $this->assertTrue(Schema::hasIndex('payments', self::PAYMENTS_INDEX));
        $this->assertTrue(Schema::hasColumn('transactions', self::TRANSACTIONS_COLUMN));
        $this->assertTrue(Schema::hasIndex('transactions', self::TRANSACTIONS_INDEX));

        $this->ensureMigrationsTableRowsPresent();
    }

    /**
     * down() is symmetric and equally idempotent: dropping twice must not error either.
     */
    public function test_down_is_idempotent(): void
    {
        $migration1 = $this->loadMigration(self::MIGRATION_1_PATH);
        $migration2 = $this->loadMigration(self::MIGRATION_2_PATH);

        $migration2->down();
        $migration2->down();
        $migration1->down();
        $migration1->down();

        $this->assertFalse(Schema::hasIndex('payments', self::PAYMENTS_INDEX));
        $this->assertFalse(Schema::hasColumn('transactions', self::TRANSACTIONS_COLUMN));
        $this->assertFalse(Schema::hasIndex('transactions', self::TRANSACTIONS_INDEX));

        // Restored in tearDown() via the unconditional up() calls there, in addition to this
        // explicit restoration -- belt and braces, since this test method deliberately leaves the
        // schema in its most-torn-down state of anything in this file.
        $migration1->up();
        $migration2->up();
    }

    /**
     * Artisan-level rerun-safety, from an operator's point of view: once every migration is
     * genuinely applied, a second `migrate` with nothing pending must exit cleanly with no error.
     */
    public function test_artisan_migrate_twice_with_nothing_pending_is_a_clean_no_op(): void
    {
        $first = Artisan::call('migrate', ['--force' => true]);
        $this->assertSame(0, $first);

        $second = Artisan::call('migrate', ['--force' => true]);
        $this->assertSame(0, $second);
        $this->assertStringContainsString('Nothing to migrate', Artisan::output());
    }
}
