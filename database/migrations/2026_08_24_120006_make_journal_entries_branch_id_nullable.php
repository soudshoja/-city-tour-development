<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * P1 ROUND 3 fix (.planning/P1-VERIFICATION-FINDINGS.json blocker 2, still PARTIAL after
     * round 2): the journal side of the branchless/legacy-NULL-branch design.
     *
     * `journal_entries.branch_id` (this table was `general_ledgers` before
     * 2025_03_28_145526_rename_table_general_ledgers_to_journal_entries.php) has been NOT NULL
     * with a real FK to `branches` since it was created
     * (2025_03_17_103934_create_general_ledgers_table.php +
     * 2025_03_17_161405_update_foreign_in_general_ledgers_table.php) and has never been touched
     * since. `PostingService::post()` writes `App\Services\Accounting\DocumentDraft::$branchId`
     * (a non-nullable int; 0 is the engine-wide "no branch" sentinel — see
     * SequenceService::next() and the serial_schemas migration) straight into this column. Since
     * `branches.id` auto-increments from 1, a branchless post (or `reverse()` of a legacy
     * NULL-branch `transactions` row, which derives branchId as `(int) $posted->branch_id` = 0)
     * tries to insert `branch_id = 0` here and hits the FK with a raw MySQL 1452 — the exact
     * failure blocker 2 named, now confirmed to still be open for this table specifically.
     *
     * FIX: make the column nullable, keep the FK exactly as-is. A FOREIGN KEY constraint in
     * MySQL/InnoDB only validates non-NULL values — it has never rejected NULL and does not start
     * doing so here — so this is purely additive: every existing legacy INSERT/UPDATE that always
     * supplied a real branch id keeps working byte-for-byte identically. It only newly permits a
     * literal NULL where nothing could be written at all before.
     *
     * `transactions.branch_id` is DELIBERATELY left untouched by this migration: it has no FK
     * (confirmed — grepped every migration touching that table) and already accepts the 0
     * sentinel without any schema change, and it is a NULLABLE plain int column since
     * 2025_04_03_124217_update_column_in_transactions_table.php. `PostingService` therefore keeps
     * writing the 0 sentinel into `transactions.branch_id` unchanged; only the write into
     * `journal_entries.branch_id` needs to translate that sentinel to a real NULL, because this is
     * the one column of the two that structurally cannot hold 0 (see the PostingService.php edit
     * specified in this round's report — that file is out of scope for this migration/model
     * fixer to edit directly).
     *
     * Why NOT drop the FK instead (the other way to let 0 through): `branches` never has and
     * never will have an id 0 (auto-increment floor is 1), so allowing 0 through the FK would
     * just be a different way of encoding "no branch" that a future join/report could silently
     * mistake for a real (if unlikely) branch id. NULL is the unambiguous, already-idiomatic
     * value for "no branch" in this exact schema (see transactions.branch_id, already nullable
     * with no FK, and reverse()'s own docblock note that legacy rows carry a real NULL here) —
     * keeping the FK and only widening it to accept NULL preserves the FK's real protection
     * (every non-null branch_id must reference a real branches row) while finally giving the
     * column a legitimate way to say "no branch" at all.
     */
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Not reversible without a data decision this migration has no business making: any row
     * that now legitimately carries branch_id = NULL (branchless engine posts, or reversals of
     * legacy NULL-branch transactions) would need an explicit real branch id manufactured for it
     * before the column could go back to NOT NULL — silently coercing those back to 0 would
     * reintroduce the exact FK violation this migration exists to fix. Left as a no-op; a genuine
     * rollback must be a deliberate, reviewed data migration, not this file guessing.
     */
    public function down(): void
    {
        // Intentionally a no-op — see docblock above.
    }
};
