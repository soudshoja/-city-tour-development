<?php

namespace Tests\Feature\Accounting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AccountingTestCase;

/**
 * Regression cover for the production incident of 2026-09-02, in which
 * `php artisan migrate --force` halted on migration
 * 2026_04_01_155631_add_column_in_payments_table against MariaDB 10.11.19 with:
 *
 *     SQLSTATE[HY000] 1901: Function or expression 'client_id' cannot be used in the
 *     CHECK clause of `chk_payment_owner`
 *
 * MariaDB >= 10.5 rejects any CHECK constraint (and any generated-column expression) that
 * references a column participating in a foreign key with a SET NULL referential action.
 * `payments.client_id` and `payments.settlement_id` are both such columns. The CHECK was
 * therefore removed from that migration and the same XOR invariant is now enforced by
 * BEFORE INSERT / BEFORE UPDATE triggers installed by
 * 2026_09_02_000010_enforce_payment_owner_xor_via_triggers.
 *
 * These tests run against the fenced test database and cover two things:
 *   1. the triggers enforce the XOR for every writer (raw query builder is used deliberately —
 *      a trigger, unlike a model guard, fires below Eloquent);
 *   2. the two migrations complete cleanly from BOTH shapes of the world — the HALF-APPLIED
 *      state prod was left in (columns present, constraint absent, migration row unrecorded)
 *      and a VIRGIN state (neither column present).
 */
class PaymentOwnerXorTriggerMigrationTest extends AccountingTestCase
{
    private const OWNER_MIGRATION = '2026_04_01_155631_add_column_in_payments_table';

    private const TRIGGER_MIGRATION = '2026_09_02_000010_enforce_payment_owner_xor_via_triggers';

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Trigger-based XOR enforcement is MySQL/MariaDB only.');
        }
    }

    public function test_the_chk_payment_owner_check_constraint_is_not_used_anywhere(): void
    {
        // The constraint MariaDB 10.11 refuses must never come back — the triggers replace it.
        $this->assertFalse(
            $this->hasCheckConstraint('payments', 'chk_payment_owner'),
            'chk_payment_owner must not exist: MariaDB 10.11 rejects it (error 1901).'
        );

        $this->assertTrue($this->hasTrigger('payments_owner_xor_before_insert'));
        $this->assertTrue($this->hasTrigger('payments_owner_xor_before_update'));
    }

    public function test_insert_with_both_owners_null_is_rejected_by_the_trigger(): void
    {
        $this->assertRejected(fn () => $this->insertPayment(null, null));
    }

    public function test_insert_with_both_owners_set_is_rejected_by_the_trigger(): void
    {
        [$clientId, $settlementId] = $this->makeOwners();

        $this->assertRejected(fn () => $this->insertPayment($clientId, $settlementId));
    }

    public function test_insert_with_exactly_one_owner_is_accepted(): void
    {
        [$clientId, $settlementId] = $this->makeOwners();

        $clientPaymentId = $this->insertPayment($clientId, null);
        $settlementPaymentId = $this->insertPayment(null, $settlementId);

        $this->assertSame(
            $clientId,
            (int) DB::table('payments')->where('id', $clientPaymentId)->value('client_id')
        );
        $this->assertSame(
            $settlementId,
            (int) DB::table('payments')->where('id', $settlementPaymentId)->value('settlement_id')
        );
    }

    public function test_update_that_would_break_the_xor_is_rejected_by_the_trigger(): void
    {
        [$clientId, $settlementId] = $this->makeOwners();
        $paymentId = $this->insertPayment($clientId, null);

        // Setting the second owner on an already client-owned row breaks the XOR.
        $this->assertRejected(
            fn () => DB::table('payments')->where('id', $paymentId)->update(['settlement_id' => $settlementId])
        );

        // So does clearing the only owner it has.
        $this->assertRejected(
            fn () => DB::table('payments')->where('id', $paymentId)->update(['client_id' => null])
        );

        // Swapping one owner for the other keeps the XOR and must succeed.
        DB::table('payments')->where('id', $paymentId)->update([
            'client_id' => null,
            'settlement_id' => $settlementId,
        ]);

        $this->assertNull(DB::table('payments')->where('id', $paymentId)->value('client_id'));
    }

    /**
     * The incident scenario: prod was left with `payments.settlement_id` already created (the
     * failing ALTER's column add had implicitly committed) but with no `chk_payment_owner` and
     * no `migrations` row, so a naive re-run died on a duplicate column. Rerunning both
     * migrations' up() against exactly that state must now be a clean no-op-plus-triggers.
     */
    public function test_migrations_complete_from_the_half_applied_production_state(): void
    {
        $this->assertTrue(Schema::hasColumn('payments', 'settlement_id'));
        $this->assertTrue(Schema::hasColumn('payments', 'client_id'));

        // Reproduce the half-applied shape: columns present, no constraint, no triggers, and the
        // migration rows removed so the migrator considers both pending again.
        DB::unprepared('DROP TRIGGER IF EXISTS payments_owner_xor_before_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS payments_owner_xor_before_update');
        DB::table('migrations')->whereIn('migration', [self::OWNER_MIGRATION, self::TRIGGER_MIGRATION])->delete();

        $this->assertFalse($this->hasTrigger('payments_owner_xor_before_insert'));

        $this->runMigration(self::OWNER_MIGRATION);
        $this->runMigration(self::TRIGGER_MIGRATION);

        // Columns survived untouched, and the triggers are back and enforcing.
        $this->assertTrue(Schema::hasColumn('payments', 'settlement_id'));
        $this->assertTrue($this->hasTrigger('payments_owner_xor_before_insert'));
        $this->assertTrue($this->hasTrigger('payments_owner_xor_before_update'));
        $this->assertFalse($this->hasCheckConstraint('payments', 'chk_payment_owner'));
        $this->assertRejected(fn () => $this->insertPayment(null, null));

        // The DDL above implicitly committed, breaking RefreshDatabase's per-test transaction,
        // so the deleted `migrations` rows must be put back by hand or the shared fenced test
        // database would be left thinking both migrations are pending.
        $this->restoreMigrationRows();
    }

    /**
     * The other path the same file must handle: a VIRGIN database where `settlement_id` does not
     * exist at all. down() then up() must recreate the column, its foreign key and the triggers.
     */
    public function test_migrations_complete_from_a_virgin_state(): void
    {
        $this->downMigration(self::TRIGGER_MIGRATION);
        $this->downMigration(self::OWNER_MIGRATION);

        $this->assertFalse(Schema::hasColumn('payments', 'settlement_id'), 'down() must drop the column.');
        $this->assertFalse($this->hasTrigger('payments_owner_xor_before_insert'), 'down() must drop the triggers.');

        $this->runMigration(self::OWNER_MIGRATION);
        $this->runMigration(self::TRIGGER_MIGRATION);

        $this->assertTrue(Schema::hasColumn('payments', 'settlement_id'));
        $this->assertTrue($this->hasForeignKeyOn('payments', 'settlement_id'), 'up() must recreate the foreign key.');
        $this->assertTrue($this->hasTrigger('payments_owner_xor_before_insert'));
        $this->assertRejected(fn () => $this->insertPayment(null, null));
    }

    /** up() must be safe to run twice in a row — no duplicate column, no duplicate trigger. */
    public function test_both_migrations_are_idempotent_when_run_twice(): void
    {
        $this->runMigration(self::OWNER_MIGRATION);
        $this->runMigration(self::OWNER_MIGRATION);
        $this->runMigration(self::TRIGGER_MIGRATION);
        $this->runMigration(self::TRIGGER_MIGRATION);

        $this->assertTrue(Schema::hasColumn('payments', 'settlement_id'));
        $this->assertTrue($this->hasTrigger('payments_owner_xor_before_update'));
    }

    /**
     * Re-record both migrations after a test has deliberately un-recorded them. See the note in
     * the half-applied test: these are DDL-adjacent writes on a shared fenced database that
     * RefreshDatabase's transaction can no longer roll back for us.
     */
    private function restoreMigrationRows(): void
    {
        $batch = (int) DB::table('migrations')->max('batch');

        foreach ([self::OWNER_MIGRATION, self::TRIGGER_MIGRATION] as $migration) {
            if (! DB::table('migrations')->where('migration', $migration)->exists()) {
                DB::table('migrations')->insert(['migration' => $migration, 'batch' => $batch]);
            }
        }
    }

    // ---------------------------------------------------------------- helpers

    private function migrationFile(string $name): string
    {
        return database_path("migrations/{$name}.php");
    }

    private function runMigration(string $name): void
    {
        (require $this->migrationFile($name))->up();
    }

    private function downMigration(string $name): void
    {
        (require $this->migrationFile($name))->down();
    }

    private function insertPayment(?int $clientId, ?int $settlementId): int
    {
        return DB::table('payments')->insertGetId([
            'amount' => 10,
            'client_id' => $clientId,
            'settlement_id' => $settlementId,
        ]);
    }

    /**
     * The XOR trigger fires BEFORE INSERT, ahead of any foreign-key evaluation, so these tests
     * only need owner ids that are non-NULL — not real `clients` / `agent_settlements` rows,
     * which would each drag in a company/branch/agent fixture tree that proves nothing extra
     * here. FOREIGN_KEY_CHECKS is a session variable (not DDL), so toggling it is safe inside
     * RefreshDatabase's transaction and is restored immediately after the write.
     *
     * @return array{0:int,1:int} [clientId, settlementId]
     */
    private function makeOwners(): array
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        return [901, 902];
    }

    private function assertRejected(callable $write): void
    {
        try {
            $write();
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString(
                'payments must belong to exactly one owner',
                $e->getMessage(),
                'The rejection must come from the XOR trigger, not some unrelated constraint.'
            );

            return;
        }

        $this->fail('The XOR trigger should have rejected this write.');
    }

    private function hasTrigger(string $trigger): bool
    {
        return DB::selectOne(
            'SELECT 1 AS found FROM information_schema.TRIGGERS
             WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ? LIMIT 1',
            [$trigger]
        ) !== null;
    }

    private function hasCheckConstraint(string $table, string $constraint): bool
    {
        return DB::selectOne(
            'SELECT 1 AS found FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ? LIMIT 1',
            [$table, $constraint, 'CHECK']
        ) !== null;
    }

    private function hasForeignKeyOn(string $table, string $column): bool
    {
        return DB::selectOne(
            'SELECT 1 AS found FROM information_schema.KEY_COLUMN_USAGE
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1',
            [$table, $column]
        ) !== null;
    }
}
