<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P2.5.F fix-round (2026-08-30, per verify findings — CONFIRMED #1): the append-only guarantee
 * ({@see \App\Models\AccountingAuditLog}'s `booted()` guard) only intercepts per-instance Eloquent
 * lifecycle events (`$model->update()`, `$model->delete()`, `Model::destroy($id)` — which internally
 * retrieves-then-deletes each row through the same per-instance path). It never fires for:
 *   - `AccountingAuditLog::where(...)->update([...])` / `::where(...)->delete()` — Eloquent's
 *     query-builder bulk paths, which never hydrate or fire model events at all;
 *   - `DB::table('accounting_audit_log')->where(...)->update([...])` — raw query-builder SQL with
 *     no Eloquent involved whatsoever.
 * The brief's own literal text offers two acceptable enforcement mechanisms — "a DB trigger OR
 * model boot guard" — precisely because a boot guard alone cannot close those two paths; only a
 * DB-level trigger, which fires on every UPDATE/DELETE regardless of which PHP code path issued it
 * (including a future raw `mysql` client or an as-yet-unwritten reporting query), can. This
 * migration adds that trigger layer ON TOP OF the existing boot guard (kept — it gives an
 * instant, catchable `\RuntimeException` for the common per-instance path instead of surfacing a
 * generic `QueryException` first) rather than replacing it, closing the compliance-grade
 * tamper-evidence gap without weakening the friendlier in-app error for ordinary callers.
 *
 * Uses `SIGNAL SQLSTATE '45000'` inside `BEFORE UPDATE`/`BEFORE DELETE` triggers — the standard
 * MySQL/MariaDB mechanism to abort the statement with a custom error message, working identically
 * whether the mutating statement came from Eloquent, the query builder, or a raw PDO/CLI `UPDATE`/
 * `DELETE`. `DB::unprepared()` sends the entire multi-statement trigger body as one PDO exec call —
 * MySQL's `CREATE TRIGGER ... BEGIN ... END` accepts internal semicolons natively over the wire;
 * `DELIMITER` is a `mysql` CLI client-side directive only, never needed (and not understood) by PDO.
 *
 * Additive and reversible: `down()` drops both triggers, leaving the boot guard as the sole
 * enforcement (matching pre-migration behaviour) rather than leaving an orphaned trigger behind on
 * a rollback.
 *
 * DELETE ESCAPE HATCH — {@see \App\Console\Commands\AccountingAuditLogPurge}: the brief's own
 * retention clause ("archival job only when set") is one, narrow, EXPLICIT exception to
 * append-only — a company that has opted into `audit_log_retention_months` gets its rows CSV-
 * exported then deleted past the retention window. That command already documented itself as
 * "the one, explicit, documented exception" to the model's boot guard (raw query-builder delete(),
 * bypassing Eloquent) — this migration's own first version (same day) made that exception
 * literally impossible by blocking EVERY delete unconditionally, breaking the already-shipped
 * purge command outright. The DELETE trigger below is therefore gated on a session-scoped MySQL
 * user variable, `@accounting_audit_log_allow_delete`, that ONLY the purge command sets (to 1,
 * immediately before its delete, and back to 0 immediately after — see that command's own updated
 * docblock): every other caller — Eloquent, the query builder, a raw client — still hits the
 * unconditional SIGNAL, because the variable defaults to NULL/unset on every fresh connection and
 * nothing else in the codebase ever sets it. The UPDATE trigger has no such gate — no code path,
 * documented or otherwise, is ever allowed to update a row, so it stays unconditional.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->isMySql()) {
            // sqlite/testing-only connections some unrelated unit tests may swap in have no
            // trigger syntax compatible with this DDL; the boot guard alone still applies there.
            // Every real app/test MySQL connection (mysql, mysql_testing) gets the trigger.
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER accounting_audit_log_no_update
            BEFORE UPDATE ON accounting_audit_log
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'accounting_audit_log is append-only: rows may never be updated.';
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER accounting_audit_log_no_delete
            BEFORE DELETE ON accounting_audit_log
            FOR EACH ROW
            BEGIN
                IF @accounting_audit_log_allow_delete IS NULL OR @accounting_audit_log_allow_delete <> 1 THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'accounting_audit_log is append-only: rows may never be deleted.';
                END IF;
            END
        SQL);
    }

    public function down(): void
    {
        if (! $this->isMySql()) {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS accounting_audit_log_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS accounting_audit_log_no_delete');
    }

    private function isMySql(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }
};
