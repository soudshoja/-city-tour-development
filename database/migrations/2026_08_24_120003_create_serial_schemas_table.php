<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Atomic, per-tenant document numbering backing
     * `App\Services\Accounting\SequenceService::next()` (BUG-H10, F6/F10/F13).
     *
     * See Accounting Gap/11-technical-implementation-plan.md §P1.0 (L124-135).
     *
     * branch_id design (FIX for P1-VERIFICATION-FINDINGS blocker 2, 2026-08-24):
     * file 11's own snippet gives this column as a bare `foreignId('branch_id')
     * ->nullable()` — i.e. a bigint-unsigned column, deliberately with NO
     * `->constrained()` call. An earlier revision of this migration added
     * `->constrained('branches')->nullOnDelete()` anyway, reasoning that a real FK
     * is "strictly better" per house style — that reasoning was wrong for this
     * column specifically, and is corrected here.
     *
     * SequenceService::next() normalizes a null/absent branch to the sentinel 0
     * rather than storing true NULL, because this column is part of the row's
     * uniqueness key (company_id, branch_id, doc_type, doc_year) and MySQL's
     * unique index treats every NULL as distinct from every other NULL — two
     * concurrent "no branch" callers could each pass the existence check and both
     * insert a NULL-branch row, giving a company two live counters for the same
     * (doc_type, doc_year) and, downstream, two documents issued with the same
     * number. The 0 sentinel closes that race by making "no branch" a real,
     * comparable, lockable value. See SequenceService::next() for the full
     * rationale — this is a deliberate, documented improvement on file 11's
     * verbatim snippet, not an oversight.
     *
     * Given that, a `->constrained('branches')` FK is actively wrong here:
     * `branches.id` auto-increments from 1, so 0 never exists as a real branch and
     * the FK would reject every branchless `next()` call and every `reverse()` of
     * a legacy NULL-branch transaction with a raw error 1452 — which
     * `SequenceService::isDuplicateKeyViolation()` used to mistake for a lost
     * create race (both are SQLSTATE 23000), masking the real cause entirely.
     * `transactions.branch_id` (this column's direct source — see
     * 2025_04_03_124217_update_column_in_transactions_table.php) has never carried
     * a branches FK either, so dropping it here also matches that table's own
     * convention rather than deviating from it.
     *
     * The column is therefore NOT NULL with a default of 0 and NO foreign key:
     * every row unambiguously carries either a real branch id or the "no branch"
     * sentinel, and nothing — not even a future writer bypassing
     * SequenceService — can insert a true NULL that would reopen the race above.
     */
    public function up(): void
    {
        Schema::create('serial_schemas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');

            // No FK — see the class docblock above. 0 = "no branch" sentinel,
            // never a real branches.id (those start at 1). Kept NOT NULL so the
            // sentinel convention can't be silently bypassed with a true NULL.
            $table->unsignedBigInteger('branch_id')->default(0);

            // INV, RV, PV, JV, CRN, DBN, OJV, REV…
            $table->string('doc_type', 8);
            $table->unsignedSmallInteger('doc_year');
            // P1 ROUND 4: references SequenceService::DEFAULT_MASK rather than a duplicated
            // literal — both insert sites (SequenceService::createSchemaRow(),
            // SerialSchema::seedFromLegacyMax()) already do the same for the same reason: a second
            // hardcoded copy of this string is exactly how it silently drifts from the mask
            // SequenceService::format() is actually built to understand (it did, briefly, when
            // the {BRANCH} token was added and this literal was not updated in step). This column
            // default is a defense-in-depth backstop only — every current write path sets 'mask'
            // explicitly — for any future insert that doesn't.
            $table->string('mask')->default(\App\Services\Accounting\SequenceService::DEFAULT_MASK);
            $table->unsignedBigInteger('last_serial')->default(0);
            $table->unsignedInteger('increment')->default(1);
            $table->timestamps();

            $table->unique(['company_id', 'branch_id', 'doc_type', 'doc_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('serial_schemas');
    }
};
