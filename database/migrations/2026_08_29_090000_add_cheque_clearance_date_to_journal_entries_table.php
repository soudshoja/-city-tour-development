<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W5.L (w5-brief.md §W5.L, doc 22 §2.3 "02-10/MF-22, amended"): the ONE new column this wave owns.
 * `journal_entries.cheque_no` / `.cheque_date` / `.bank_info` / `.auth_no` already exist (migration
 * 2025_03_25_085713_add_columns_to_general_ledgers_table.php) but the instrument's CLEARANCE date
 * — when a posted-not-yet-cleared cheque (Dr 1215 Cheques In Hand on receipt, or held on
 * 2215 Cheques Issued Not Cleared on payment) actually clears through the bank — has no column of
 * its own anywhere in the schema. Additive only: nullable, no default, no backfill needed (every
 * existing row simply has no clearance date recorded yet, which is the truth).
 *
 * Deliberately a plain `date`, not the `timestamp ... useCurrent()` shape `cheque_date` uses next
 * to it — see LineDraft::$chequeClearanceDate's own docblock: a clearance either has a real,
 * explicitly-recorded date or it hasn't happened yet (NULL); there is no legacy call site relying
 * on a "today" default the way `cheque_date`'s `useCurrent()` accidentally does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->date('cheque_clearance_date')->nullable()->after('cheque_date');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn('cheque_clearance_date');
        });
    }
};
