<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * P1 ROUND 4 fix (owner decision 2026-08-24, blocker 1): closes the numbering-collision hole
     * at the database level, not just in application code. `App\Services\Accounting\SequenceService`
     * now renders a branch-scoped number for every doc_type/branch/year combination (see its class
     * docblock's "BRANCH-SCOPED NUMBERING" section), but nothing before this migration stopped a
     * bug in that service — or a future write path that bypasses it entirely, e.g. a raw
     * `Transaction::create()` — from writing the same (company_id, doc_type, reference_number)
     * tuple twice. This is the database backstop: additive only, mirrors the pattern already used
     * for `unique(company_id, idempotency_key)` in 2026_08_24_120004_add_document_columns_to_
     * transactions_table.php.
     *
     * NULL-safety / legacy-collision analysis (why this is safe to add now, against real data,
     * not just against the empty disposable test DB):
     *
     *   - `doc_type` is a brand-new nullable column (2026_08_24_120004, no backfill) and the ONLY
     *     write path that has ever set it is `PostingService::post()` — the P1 posting engine,
     *     confirmed flag-OFF and wired to nothing (grepped: no other call site sets
     *     `transactions.doc_type`, and `serial_schemas`/engine-internal `doc_type` writes are a
     *     DIFFERENT table). Every pre-existing `transactions` row therefore has `doc_type IS NULL`.
     *   - MySQL/InnoDB unique indexes do NOT enforce uniqueness across rows where ANY indexed
     *     column is NULL (NULL is never equal to NULL for this purpose) — the exact same property
     *     `unique(company_id, idempotency_key)` already relies on for legacy rows with no
     *     idempotency_key (see that migration's own comment). So even though many legacy rows
     *     plainly DO share duplicate `reference_number` values per company (the pre-engine
     *     generators were never collision-proof — that's the whole reason this build exists), this
     *     constraint cannot reject any of them: their shared `doc_type IS NULL` exempts every one
     *     of them from the uniqueness check, individually and against each other.
     *   - `company_id` on `transactions` is ALSO nullable (added 2025_04_03_124217, retrofitted
     *     onto a table that predates it) — a second, independent reason many legacy rows are
     *     exempt even if some future backfill ever populates `doc_type` without also populating
     *     `company_id`.
     *   - Net effect: this migration is safe to run against real legacy data as-is. It becomes
     *     "live" — actually enforcing anything — only for rows where the P1 engine (or a future
     *     equivalent write path) has populated both `company_id` and `doc_type`, which today is
     *     zero rows, growing only once P2 cutover actually turns the engine on. That is the
     *     intended behavior, not a gap: this constraint exists to catch a future collision, not a
     *     historical one.
     *
     * NOT manually applied to any shared/persistent environment as part of this change — additive
     * migration file only, per this round's explicit instruction (it is exercised automatically by
     * the disposable `city_tour_test` DB whenever the test suite's RefreshDatabase runs the full
     * migration set, same as every other migration in this repo — that is not "running" it in the
     * sense the instruction means). `reference_number` is `VARCHAR(20)` (2025_03_25_085421) and was
     * not widened by this migration; see SequenceService's class docblock "COLUMN-WIDTH CONSTRAINT"
     * section for the length budget the `{BRANCH}` token now renders within.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unique(['company_id', 'doc_type', 'reference_number'], 'transactions_company_doctype_refnum_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_company_doctype_refnum_unique');
        });
    }
};
