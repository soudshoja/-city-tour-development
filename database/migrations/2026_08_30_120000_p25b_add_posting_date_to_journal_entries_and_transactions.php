<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * P2.5.B (p2_5-brief.md §P2.5.B; period-lock-design.md §8.1 — the three-date model): adds the
 * SECOND of the three dates, `posting_date`, to `journal_entries` and `transactions` — the field
 * `PeriodGuard`, `TrialBalanceService`, and every P&L/report query actually periodize on from this
 * wave forward. Distinct from both columns already on these tables:
 *   - `transaction_date` — the document's own date (`DocumentDraft::$docDate`), what actually
 *     happened, printed on the document. Never altered by this migration or by anything this wave
 *     builds (see `PostingService::post()`'s own P2.5.B docblock at its step-5 call site).
 *   - `created_at` — entry/audit timestamp only. Never a period signal (BUG-C4 is exactly what
 *     happens when a report reads this column as if it were one).
 *
 * Nullable (additive-only convention, same as every other engine-era migration this wave sits
 * alongside): a legacy writer that still bypasses `PostingService` (there are 131 of them per doc
 * 11 §C2's own census; this wave does not touch any of them) leaves `posting_date` NULL on the
 * rows it writes going forward, exactly as it already leaves other engine-only columns NULL today.
 * The backfill below populates every EXISTING row, engine or legacy, so nothing already in the
 * table needs special-casing by a reader from this point on.
 *
 * Indexed `(company_id, posting_date)` per the brief's own column spec — every report query this
 * wave repoints at `posting_date` (TrialBalanceService's three query methods,
 * ReportController::profitLoss(), see those files' own P2.5.B notes) filters by exactly this pair.
 *
 * ── Backfill rule (brief: "backfill = transaction_date else created_at (report counts of each)") ──
 * For each table: `posting_date = DATE(transaction_date)` wherever `transaction_date` is not NULL,
 * else `DATE(created_at)`. `journal_entries.transaction_date` is NOT NULL at the schema level (see
 * `2025_03_17_103934_create_general_ledgers_table.php`), so in practice every `journal_entries` row
 * backfills from `transaction_date` and the `created_at` fallback should report zero rows — kept
 * anyway because a raw INSERT bypassing that constraint is not physically impossible, and the
 * brief asks for both counts unconditionally, not only "if needed". `transactions.transaction_date`
 * IS nullable (`2025_07_28_171758_add_transaction_date_to_transactions_table.php`), so that
 * table's `created_at` fallback is expected to fire for any legacy row that never got a
 * transaction_date backfilled by the migrations named in that file's own docblock. Counts are
 * logged via `Log::info('accounting.posting_date_backfill', ...)` — `Illuminate\Database\Query
 * \Builder::update()` returns the affected-row count directly, so both numbers are exact, not
 * estimated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->date('posting_date')->nullable()->after('transaction_date');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->date('posting_date')->nullable()->after('transaction_date');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->index(['company_id', 'posting_date'], 'journal_entries_company_id_posting_date_index');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['company_id', 'posting_date'], 'transactions_company_id_posting_date_index');
        });

        $this->backfill('journal_entries');
        $this->backfill('transactions');
    }

    /**
     * @param  'journal_entries'|'transactions'  $table
     */
    private function backfill(string $table): void
    {
        $fromTransactionDate = DB::table($table)
            ->whereNull('posting_date')
            ->whereNotNull('transaction_date')
            ->update(['posting_date' => DB::raw('DATE(transaction_date)')]);

        $fromCreatedAt = DB::table($table)
            ->whereNull('posting_date')
            ->whereNotNull('created_at')
            ->update(['posting_date' => DB::raw('DATE(created_at)')]);

        Log::info('accounting.posting_date_backfill', [
            'table' => $table,
            'from_transaction_date' => $fromTransactionDate,
            'from_created_at' => $fromCreatedAt,
        ]);
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('journal_entries_company_id_posting_date_index');
            $table->dropColumn('posting_date');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_company_id_posting_date_index');
            $table->dropColumn('posting_date');
        });
    }
};
