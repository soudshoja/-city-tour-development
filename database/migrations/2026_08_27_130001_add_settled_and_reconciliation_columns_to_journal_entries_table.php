<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * W3a (P1 posting-engine cutover, InvoiceController lane). Verified NOT present before this
     * migration: grepped every existing journal_entries migration
     * (2025_05_13_015314_add_reconciled_to_journal_entries_table.php only adds a plain tinyint
     * `reconciled` flag + `reconciled_ref_id`, a DIFFERENT pair of columns from the ones below —
     * neither collides with these names).
     *
     * - settled_amount / reconciled_amount: decimal(15,3), matching the exact precision the
     *   engine already uses for every other money column on this table (see
     *   2025_10_12_111941_update_decimal_points_in_journal_entries_table.php: debit/credit/
     *   balance/amount are all decimal(15,3)) — never decimal(10,2) or any other scale, so a
     *   partial reconciliation/settlement can never silently lose the 3rd decimal a KWD fils
     *   amount needs.
     * - reconciled_date: plain nullable `date` — a calendar date the reconciliation happened on,
     *   not a timestamp (this table's own pre-existing `reconciled`/`reconciled_ref_id` pair from
     *   2025-05-13 carries no date at all today).
     * - cost_center_id: nullable unsignedBigInteger, NO foreign key — matching this table's own
     *   existing convention for optional cross-reference columns (`task_id`, added by
     *   2025_05_14_074153_add_task_id_to_journal_entries_table.php, and `reconciled_ref_id`
     *   above, are both plain unsignedBigInteger with no FK). The `cost_centers` table this
     *   column will eventually reference is created by the very next migration in this same
     *   batch (2026_08_27_130002_create_cost_centers_table.php) — a hard FK here would work
     *   today given migration ordering, but is deliberately skipped anyway to stay consistent
     *   with every other soft cross-reference already on this table rather than introduce the
     *   only FK-enforced one.
     * - reason_tag: varchar(16) exactly, per the LOCKED accounting decision this column's value
     *   is one of exactly 8 strings: loan|service|adm|fee|loss|settlement|writeoff|advance
     *   ("settlement" is the longest at 10 chars — 16 leaves headroom without over-allocating).
     *   Enforced in application code (LineDraft/PostingService), not a DB CHECK constraint —
     *   matching how every other closed-vocabulary column on this table (e.g. `type`) is
     *   already handled.
     */
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->decimal('settled_amount', 15, 3)->nullable()->after('balance');
            $table->date('reconciled_date')->nullable()->after('reconciled_ref_id');
            $table->decimal('reconciled_amount', 15, 3)->nullable()->after('reconciled_date');
            $table->unsignedBigInteger('cost_center_id')->nullable()->after('task_id');
            $table->string('reason_tag', 16)->nullable()->after('cost_center_id');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['settled_amount', 'reconciled_date', 'reconciled_amount', 'cost_center_id', 'reason_tag']);
        });
    }
};
