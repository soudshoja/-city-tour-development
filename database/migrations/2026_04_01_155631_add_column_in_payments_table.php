<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `payments.settlement_id` (nullable FK to `agent_settlements`, ON DELETE SET NULL) so a
 * payment row can belong EITHER to a client (`client_id`) OR to an agent settlement
 * (`settlement_id`) — never both, never neither.
 *
 * ============================================================================================
 * PRODUCTION INCIDENT 2026-09-02 — THIS MIGRATION HALF-APPLIED ON PROD, AND IS NOW DESIGNED TO
 * COMPLETE FROM THAT STATE.
 * ============================================================================================
 * The original version of this file ended with:
 *
 *     ALTER TABLE payments ADD CONSTRAINT chk_payment_owner CHECK(
 *         (client_id IS NOT NULL AND settlement_id IS NULL) OR
 *         (client_id IS NULL AND settlement_id IS NOT NULL))
 *
 * On prod (MariaDB 10.11.19, database `akeed_app`) that statement failed with:
 *
 *     SQLSTATE[HY000] 1901: Function or expression 'client_id' cannot be used in the
 *     CHECK clause of `chk_payment_owner`
 *
 * ROOT CAUSE (reproduced on a clean `mariadb:10.11` container, 2026-09-02): MariaDB >= 10.5
 * REJECTS any CHECK constraint that references a column participating in a foreign key with a
 * SET NULL referential action. The referential action would have to write NULL into the column
 * behind the CHECK's back, so the server refuses the constraint outright rather than allow the
 * table to become unenforceable. BOTH columns named by this CHECK qualify:
 *   - `client_id`     — added 2025_04_21_165738 with `->constrained('clients')->nullOnDelete()`
 *   - `settlement_id` — added below with `->constrained('agent_settlements')->nullOnDelete()`
 * The isolating repro: on 10.11 a CHECK over a plain column, or over a column in a RESTRICT
 * (default) FK, is ACCEPTED; the identical CHECK over a SET NULL FK column raises 1901.
 * MariaDB 10.4.32 (our XAMPP fence) has no such validation and accepted it silently for months,
 * which is why this only surfaced on prod. MySQL 8 likewise accepts it. The rejection is
 * 10.5+-specific, NOT a syntax error and NOT fixable by rewriting the expression.
 *
 * DDL is non-transactional on MariaDB, so the failing ALTER left prod half-applied:
 *   - `payments.client_id` and `payments.settlement_id` EXIST (plus settlement_id's FK/index)
 *   - `chk_payment_owner` does NOT exist
 *   - this migration's row is NOT recorded in `migrations`
 * A naive re-run of the original file therefore died on a duplicate column, not on the CHECK.
 *
 * THE FIX (this file): every DDL step is guarded so the migration is idempotent and completes
 * correctly from BOTH shapes of the world —
 *   (a) HALF-APPLIED prod: columns + FK already present, constraint absent, row unrecorded —
 *       every guard short-circuits, the migration records itself and moves on;
 *   (b) VIRGIN environment: neither column present — the guards fall through and the column,
 *       index and FK are created exactly as originally intended.
 * The `ADD CONSTRAINT ... CHECK` is REMOVED entirely. The XOR invariant it was meant to enforce
 * is now enforced by BEFORE INSERT / BEFORE UPDATE triggers created in the repair migration
 * 2026_09_02_000010_enforce_payment_owner_xor_via_triggers.php — see that file for the full
 * rationale and for the operational caveats those triggers introduce.
 *
 * `down()` tolerates either state: it drops the FK and column only if they are actually there,
 * and also drops a legacy `chk_payment_owner` if some pre-10.5 environment still carries one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'settlement_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('settlement_id')
                    ->nullable()
                    ->after('agent_id')
                    ->constrained('agent_settlements')
                    ->nullOnDelete();
            });

            return;
        }

        // Half-applied path: the column is already there. `foreignId()->constrained()` above
        // creates the column, its index and its FK in one shot, so on the half-applied prod all
        // three landed together before the CHECK blew up. Re-add the FK only in the (unexpected)
        // case that the column exists WITHOUT it — e.g. a partially hand-repaired database.
        if ($this->isMySql() && ! $this->hasForeignKeyOn('payments', 'settlement_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreign('settlement_id')
                    ->references('id')
                    ->on('agent_settlements')
                    ->nullOnDelete();
            });
        }

        // NOTE: no `ADD CONSTRAINT chk_payment_owner CHECK(...)` here, by design — MariaDB 10.11
        // rejects it (error 1901, see the class docblock). The XOR is enforced by triggers in
        // database/migrations/2026_09_02_000010_enforce_payment_owner_xor_via_triggers.php.
    }

    public function down(): void
    {
        // A legacy environment (MariaDB 10.4 / MySQL 8) may still carry the original CHECK.
        if ($this->isMySql() && $this->hasCheckConstraint('payments', 'chk_payment_owner')) {
            DB::statement('ALTER TABLE payments DROP CONSTRAINT chk_payment_owner');
        }

        if (! Schema::hasColumn('payments', 'settlement_id')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if ($this->isMySql() && $this->hasForeignKeyOn('payments', 'settlement_id')) {
                $table->dropForeign(['settlement_id']);
            }

            $table->dropColumn('settlement_id');
        });
    }

    private function isMySql(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
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

    private function hasCheckConstraint(string $table, string $constraint): bool
    {
        return DB::selectOne(
            'SELECT 1 AS found FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ? LIMIT 1',
            [$table, $constraint, 'CHECK']
        ) !== null;
    }
};
