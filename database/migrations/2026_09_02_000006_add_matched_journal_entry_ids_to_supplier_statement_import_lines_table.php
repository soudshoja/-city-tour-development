<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * accounting-builds T8 (Lane E), post-fix re-verification (RV-1).
 *
 * `supplier_statement_import_lines.matched_journal_entry_id` holds ONE id — the primary of the
 * match. When one statement row summarises several payable lines (the matcher's aggregate
 * fallback: multiple room-nights / room + tax posted as separate JE credits, billed as a single
 * statement line), that row consumes EVERY candidate but could only record the primary, so the
 * non-primary lines resurfaced in the exceptions report as "unmatched-ledger" — the spec's fourth
 * state, defined as "our open DOTW payable lines ABSENT from the statement". They are not absent;
 * they are inside the row that matched. This nullable json column records the full covered set so
 * {@see \App\Services\Accounting\Reconciliation\SupplierStatementMatcher::unmatchedLedgerLines()}
 * can exclude all of it, and so an aggregate match stays auditable (which ledger lines did this
 * one statement row actually settle?).
 *
 * Additive and nullable — deliberately a separate migration rather than an edit to M5
 * (2026_09_02_000005), so any environment that already ran M5 picks the column up by migrating
 * forward instead of needing a rebuild. Soft id list, no FK, matching this table's existing
 * `matched_journal_entry_id`/`matched_task_id` soft cross-reference convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_statement_import_lines', function (Blueprint $table) {
            $table->json('matched_journal_entry_ids')->nullable()->after('matched_journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_statement_import_lines', function (Blueprint $table) {
            $table->dropColumn('matched_journal_entry_ids');
        });
    }
};
