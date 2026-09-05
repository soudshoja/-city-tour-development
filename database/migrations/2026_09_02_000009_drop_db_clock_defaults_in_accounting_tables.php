<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timezone-safety fix (accounting-builds phase, read-only audit finding): prod `APP_TIMEZONE` is
 * now `Asia/Kuwait`, but MySQL/MariaDB's own `NOW()`/`CURRENT_TIMESTAMP` clock is UTC — the two
 * clocks disagree by 3 hours. Any column that defaults to the DB's own clock therefore drifts from
 * every other date/time this app writes (which all go through PHP's `now()`, already
 * timezone-aware). Two columns in the accounting surface are affected:
 *
 *   1. `journal_entries.cheque_date` — `timestamp('cheque_date')->nullable()->useCurrent()`,
 *      added by `2025_03_25_085713_add_columns_to_general_ledgers_table.php:20` (the table was
 *      `general_ledgers` at the time; renamed to `journal_entries` in
 *      `2025_03_28_145526_rename_table_general_ledgers_to_journal_entries.php`). The author of
 *      `2026_08_29_090000_add_cheque_clearance_date_to_journal_entries_table.php` already flagged
 *      this `useCurrent()` as accidental (see that migration's own docblock: "there is no legacy
 *      call site relying on a 'today' default the way `cheque_date`'s `useCurrent()` accidentally
 *      does"). `PostingService::post()` step 8 already writes this column explicitly on every
 *      engine-posted line (see `LineDraft`'s "W5.L FIX ROUND" docblock), so the DB default has been
 *      dead weight since W5.L shipped — but it is still live schema, so a future raw/legacy insert
 *      that omits the column would silently pick up the wrong (UTC) clock. Dropped here.
 *   2. `transactions.transaction_date` — currently `TIMESTAMP` (converted from `DATE` by
 *      `2025_07_29_152349_update_transaction_date_in_transactions_table.php:15`), while
 *      `journal_entries.transaction_date` (same conceptual column, sibling table) is `DATETIME`
 *      (`2025_03_17_103934_create_general_ledgers_table.php:24`). TIMESTAMP is UTC-normalising on
 *      write/read under MySQL/MariaDB's `time_zone` session variable — DATETIME is not. Left
 *      inconsistent, a future session-timezone change (e.g. wiring the DB session tz to
 *      `Asia/Kuwait` to match `APP_TIMEZONE`) would silently reinterpret every stored
 *      `transactions.transaction_date` value while leaving `journal_entries.transaction_date`
 *      untouched — a latent bug, not yet triggered because nothing sets the session tz today.
 *      Converted to `DATETIME` here so both tables agree.
 *
 * ── Populated-database caveat (this DB is empty in every environment this ships to today, but the
 *    migration is written to be safe on a populated one regardless) ──────────────────────────────
 * Changing a column's DEFAULT clause (item 1) never rewrites existing rows — MySQL/MariaDB only
 * consult a column default at INSERT time when the column is omitted from the statement, so
 * dropping `useCurrent()` here is a pure schema-metadata change with zero effect on stored values,
 * populated or not.
 *
 * The TIMESTAMP -> DATETIME conversion (item 2) is NOT similarly risk-free on a populated table:
 * MariaDB converts a `TIMESTAMP` column's stored (UTC-normalised-on-write) value into `DATETIME`
 * by first reinterpreting it through the CONNECTION's OWN SESSION `time_zone`, then storing that
 * wall-clock result verbatim (DATETIME has no zone of its own). If this migration's DB session runs
 * under a different `time_zone` than the session that ORIGINALLY WROTE the TIMESTAMP rows, the
 * converted DATETIME values will be shifted by the difference between those two zones — silently,
 * with no error. On a populated database this migration MUST run with the session time zone set to
 * whatever zone the existing rows were written under (prod today: UTC — nothing has ever set a
 * non-default `time_zone` session variable, so every prior write used the server default, UTC).
 * Running it under any other session tz on a populated table would corrupt every existing
 * `transactions.transaction_date` value. This DB is empty in every environment this change ships
 * to, so the conversion is a no-op on real data today, but the constraint above is recorded here
 * for whoever runs this migration next against a populated database.
 *
 * `down()` restores both original shapes (including the `useCurrent()` default, so a rollback is
 * byte-identical to the pre-migration schema) — same populated-DB session-tz caveat applies in
 * reverse for the DATETIME -> TIMESTAMP leg.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            // Drop the DB-clock default; keep the column itself (type, nullability, position)
            // exactly as-is. No `useCurrent()` call at all -- Laravel's grammar then emits a plain
            // `DATETIME NULL` with no DEFAULT clause, MariaDB-10.11-safe (no implicit-default
            // caveat: an explicit ->nullable() column with no default() never gets one auto-added).
            $table->datetime('cheque_date')->nullable()->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            // TIMESTAMP -> DATETIME, same nullability (nullable) as today. See class docblock's
            // populated-DB caveat for the session-tz requirement this conversion carries.
            $table->datetime('transaction_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('transaction_date')->nullable()->change();
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->timestamp('cheque_date')->nullable()->useCurrent()->change();
        });
    }
};
