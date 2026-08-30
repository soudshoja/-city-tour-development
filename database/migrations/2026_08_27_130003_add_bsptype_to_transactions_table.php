<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * W3.G (per Accounting Gap/11-technical-implementation-plan.md §2.1a) — `transactions.bsptype`.
     * Verified NOT present before this migration (grepped every transactions.* migration for
     * `bsptype`; none exist).
     *
     * Nullable string, NOT a DB enum — matching this table's own additive-column convention
     * (`transaction_type` itself is a plain `string`, not an enum, despite `reference_type` being
     * a closed enum from the original create-table migration; every column added to this table
     * SINCE that original migration, e.g. the P1 doc_type/sub_type/posting_status columns in
     * 2026_08_24_120004_add_document_columns_to_transactions_table.php, is a plain string/enum
     * chosen per-column rather than blanket-enforced). The six legal values —
     * ET|VOID|REFUND|ADM|ACM|EMD — are validated in application code, not a DB CHECK constraint,
     * matching that same file's own precedent (its `posting_status` column IS a DB enum, but its
     * `doc_type`/`sub_type` are plain strings) — bsptype is closer in spirit to the latter (an
     * open-ended BSP/IATA transaction-type label, not a small closed lifecycle state machine).
     * length 10 comfortably covers every listed value ("REFUND" is the longest at 6 chars).
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('bsptype', 10)->nullable()->after('reference_type');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('bsptype');
        });
    }
};
