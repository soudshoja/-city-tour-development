<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Turns the quasi-header `transactions` table into a real document header
     * (MF-41). Accounting Gap/11-technical-implementation-plan.md §P1.1 (L182-198).
     *
     * Every column below is a pure addition — verified against the current
     * transactions schema with no name collisions. journal_entries.transaction_id
     * stays nullable in P1 (orphan lines exist in prod; NOT NULL enforcement is a
     * P3 step after backfill) — the posting engine itself always sets it when it
     * writes.
     *
     * DEVIATION from the file 11 snippet: reversal_of_transaction_id is given as a
     * bare `foreignId(...)->nullable()` with no `->constrained()` call, unlike every
     * other FK in this codebase's convention. Added `->constrained('transactions')
     * ->nullOnDelete()` — the same self-referencing pattern already used successfully
     * by accounts.parent_id — since nothing in file 11 forbids it and house style
     * defaults to real FKs. Because the FK constraint already creates its own
     * covering index on the column, the separate `index('reversal_of_transaction_id')`
     * from the file 11 snippet is intentionally omitted to avoid a redundant
     * duplicate index on the same single column.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // INV/RV/PV/JV/CRN/DBN/OJV/REV
            $table->string('doc_type', 8)->nullable()->after('reference_type');
            $table->string('sub_type', 16)->nullable()->after('doc_type');
            $table->unsignedSmallInteger('doc_year')->nullable()->after('sub_type');

            // Existing rows = posted (legacy code always fully commits today).
            $table->enum('posting_status', ['draft', 'posted', 'reversed', 'void'])
                ->default('posted')
                ->after('doc_year');

            $table->decimal('total_debit', 18, 3)->default(0)->after('amount');
            $table->decimal('total_credit', 18, 3)->default(0)->after('total_debit');

            // Idempotency + reverse-then-apply (R4, BUG-H7).
            $table->foreignId('reversal_of_transaction_id')
                ->nullable()
                ->after('total_credit')
                ->constrained('transactions')
                ->nullOnDelete();

            $table->string('idempotency_key')->nullable()->after('reversal_of_transaction_id');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();

            // DB-level idempotency backstop (F2/F8/F9). Both columns are nullable;
            // MySQL treats NULL as distinct in unique indexes, so legacy rows with no
            // idempotency_key never collide — uniqueness only bites when a caller
            // actually sets one.
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['company_id', 'doc_type', 'doc_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'idempotency_key']);
            $table->dropIndex(['company_id', 'doc_type', 'doc_year']);
            $table->dropForeign(['reversal_of_transaction_id']);
            $table->dropColumn([
                'doc_type',
                'sub_type',
                'doc_year',
                'posting_status',
                'total_debit',
                'total_credit',
                'reversal_of_transaction_id',
                'idempotency_key',
                'created_by',
                'posted_by',
                'posted_at',
            ]);
        });
    }
};
