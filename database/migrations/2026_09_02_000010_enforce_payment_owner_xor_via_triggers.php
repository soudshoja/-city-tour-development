<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REPAIR MIGRATION for the production incident of 2026-09-02 — enforces the `payments` owner XOR
 * invariant with BEFORE INSERT / BEFORE UPDATE triggers instead of a CHECK constraint.
 *
 * WHY NOT A CHECK CONSTRAINT
 * --------------------------
 * 2026_04_01_155631_add_column_in_payments_table.php originally enforced the invariant with:
 *
 *     ALTER TABLE payments ADD CONSTRAINT chk_payment_owner CHECK(
 *         (client_id IS NOT NULL AND settlement_id IS NULL) OR
 *         (client_id IS NULL AND settlement_id IS NOT NULL))
 *
 * On prod (MariaDB 10.11.19) that raised:
 *     SQLSTATE[HY000] 1901: Function or expression 'client_id' cannot be used in the
 *     CHECK clause of `chk_payment_owner`
 *
 * MariaDB >= 10.5 refuses any CHECK constraint referencing a column that participates in a
 * foreign key with a SET NULL referential action — the referential action would write NULL past
 * the constraint, so the server rejects the constraint rather than let the table drift.
 * `payments.client_id` (FK -> clients, ON DELETE SET NULL) and `payments.settlement_id`
 * (FK -> agent_settlements, ON DELETE SET NULL) both qualify. Verified on a clean
 * `mariadb:10.11` container: the same CHECK over a plain column, or over a RESTRICT-FK column,
 * is accepted; over a SET NULL-FK column it raises 1901. This is not a syntax problem and no
 * rewrite of the boolean expression avoids it. MariaDB 10.4 (our XAMPP fence) and MySQL 8 have
 * no such validation, which is why the constraint passed locally for months.
 *
 * A trigger has no such restriction: BEFORE INSERT / BEFORE UPDATE triggers with
 * `SIGNAL SQLSTATE '45000'` are accepted and enforce identically on MySQL 8, MariaDB 10.4 AND
 * MariaDB 10.11, and — unlike an application-level guard — fire for every writer: Eloquent, the
 * query builder, raw PDO, and the `mysql`/`mariadb` CLI alike. Style follows the proven
 * 2026_08_30_150001_p25f_add_append_only_triggers_to_accounting_audit_log.php, which is already
 * running on prod's 10.11.
 *
 * OPERATIONAL CAVEATS — READ BEFORE ANY BULK LOAD OR RESTORE
 * ---------------------------------------------------------
 * `payments` NOW CARRIES BEFORE INSERT AND BEFORE UPDATE TRIGGERS. Consequences:
 *   - Every row inserted into or updated in `payments` must satisfy the XOR (exactly one of
 *     `client_id` / `settlement_id` non-NULL) or the statement aborts with SQLSTATE 45000. There
 *     is no session escape hatch, by design — no code path is allowed to violate the invariant.
 *   - BULK LOADS / DATA IMPORTS run row-by-row through the trigger: they are slower, and a
 *     single non-conforming row aborts the statement. Sanitise the data first; do not disable
 *     the triggers to push a load through.
 *   - RESTORING FROM A PRE-MIGRATE DUMP (e.g. the incident backup
 *     `storage/backups/pre-migrate-20260902-055529-akeed_app.sql.gz`) brings back a `payments`
 *     table WITHOUT these triggers and WITHOUT `settlement_id` — the dump predates both. Such a
 *     restore rolls the schema back to the pre-migration state; re-running `migrate` re-creates
 *     the column and re-installs the triggers. Conversely, restoring a POST-migrate dump into a
 *     live database that already has the triggers will run the dumped `payments` rows through
 *     them, so the dumped data must already satisfy the XOR (it will, if it came from a database
 *     where these triggers were active).
 *   - Existing rows are NOT validated at migration time (triggers are BEFORE INSERT/UPDATE only,
 *     they cannot retro-validate). On prod this is moot — `payments` has 0 rows. In any other
 *     environment, a pre-existing row violating the XOR survives untouched but will fail on its
 *     next UPDATE. Audit before deploying to such an environment.
 *
 * DEFINER / RESTORE PORTABILITY
 * -----------------------------
 * The `CREATE TRIGGER` statements below deliberately carry NO explicit `DEFINER = 'user'@'host'`
 * clause, exactly as the p25f triggers do. MySQL/MariaDB then record the trigger with
 * `DEFINER = CURRENT_USER` — the account that ran the migration — rather than a name hardcoded
 * in our source, so the same file installs cleanly under any DB account on any host (prod's
 * `akeed_app` user, a fenced local `root`, a CI container user) with no edit. That matters on
 * restore: a dump replays the definer recorded at creation time, and a hardcoded foreign account
 * would make the restore fail (or need SUPER) on a server where that account does not exist.
 * These triggers only ever read `NEW.*` and SIGNAL — they touch no other table and need no
 * privilege beyond the trigger's own execution — so whichever account ends up as definer after a
 * restore is sufficient. If a restore under a different user still trips definer errors, restore
 * with `--skip-triggers` and re-run this migration to recreate them under the new account.
 *
 * IDEMPOTENT: drops any leftover `chk_payment_owner` (present only on a pre-10.5 environment
 * where the original CHECK succeeded) and `DROP TRIGGER IF EXISTS` before each `CREATE TRIGGER`,
 * so it is safe to re-run. `down()` drops both triggers and does not attempt to restore the
 * CHECK — that constraint is unsupportable on our production server.
 */
return new class extends Migration
{
    private const TRIGGER_MESSAGE = 'payments must belong to exactly one owner: set either client_id or settlement_id, never both and never neither.';

    public function up(): void
    {
        if (! $this->isMySql()) {
            // sqlite/testing-only connections some unrelated unit tests swap in have no compatible
            // trigger syntax. Every real app/test MySQL connection (mysql, mysql_testing) is covered.
            return;
        }

        if ($this->hasCheckConstraint('payments', 'chk_payment_owner')) {
            DB::statement('ALTER TABLE payments DROP CONSTRAINT chk_payment_owner');
        }

        $message = self::TRIGGER_MESSAGE;

        DB::unprepared('DROP TRIGGER IF EXISTS payments_owner_xor_before_insert');
        DB::unprepared(<<<SQL
            CREATE TRIGGER payments_owner_xor_before_insert
            BEFORE INSERT ON payments
            FOR EACH ROW
            BEGIN
                IF NOT (
                    (NEW.client_id IS NOT NULL AND NEW.settlement_id IS NULL) OR
                    (NEW.client_id IS NULL AND NEW.settlement_id IS NOT NULL)
                ) THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = '{$message}';
                END IF;
            END
        SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS payments_owner_xor_before_update');
        DB::unprepared(<<<SQL
            CREATE TRIGGER payments_owner_xor_before_update
            BEFORE UPDATE ON payments
            FOR EACH ROW
            BEGIN
                IF NOT (
                    (NEW.client_id IS NOT NULL AND NEW.settlement_id IS NULL) OR
                    (NEW.client_id IS NULL AND NEW.settlement_id IS NOT NULL)
                ) THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = '{$message}';
                END IF;
            END
        SQL);
    }

    public function down(): void
    {
        if (! $this->isMySql()) {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS payments_owner_xor_before_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS payments_owner_xor_before_update');
    }

    private function isMySql(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
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
