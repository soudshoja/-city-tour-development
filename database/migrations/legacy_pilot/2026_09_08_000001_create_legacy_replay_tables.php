<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * legacy-ledger-pilot LP3 -- the per-document / per-line replay audit, plus
 * the allocation replay register.
 *
 * MAPPING-RULES.md §8.2 specifies map_document and map_document_line
 * column-for-column; this migration is that specification.
 *
 * map_allocation is LP3's own addition and a DOCUMENTED DEVIATION from
 * MAPPING-RULES.md §3.2, which says the allocations are replayed "through
 * Akeed's apply service". They cannot be: Akeed's only allocation model is
 * `payment_applications` (payment_id -> payments, invoice_id -> invoices,
 * both real FKs), and MAPPING-RULES §1.2 (d) itself fixes `invoiceId` /
 * `taskId` at NULL on every replayed line because "Akeed's invoice/task
 * tables hold nothing for this data". A legacy allocation is a
 * journal-line-to-journal-line link (§3.2 (2): "(SourceDocID_FK,
 * SourceAccDetailID_FK) -> our journal_entries.id"), a shape
 * payment_applications cannot express at all. Since §3.3 also establishes
 * that "allocations carry no ledger money; a mismatched allocation cannot
 * move a trial balance", the faithful implementation is to record the
 * replayed link in the pilot's own quarantined connection -- which is
 * exactly what LP4 check 6 reads. Nothing about this touches the ledger.
 *
 * RUN ONLY via:
 *   php artisan migrate --path=database/migrations/legacy_pilot --database=legacy_pilot
 * NEVER via the default `php artisan migrate` -- see
 * tests/Feature/Legacy/LegacyMigrationRatchetTest.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MAPPING-RULES.md §8.2 -- the per-document replay audit row. One row
        // per in-window legacy header the runner has SEEN, whatever happened
        // to it, so that
        //   replayed + skipped(no lines) + skipped(all-zero) + excluded(unposted) + refused
        // = the LP0.4 census (LP4 check 4) is a query, not a reconstruction.
        Schema::connection('legacy_pilot')->create('map_document', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('legacy_doc_id');
            $table->string('legacy_doc_no')->nullable();

            // ── NAMING, coordinated with LP4 (2026-09-08) ─────────────────────
            // MAPPING-RULES §8.2 sketches these three as `legacy_sub_type`,
            // `legacy_doc_dt` and `our_transaction_id`. LP4's parity harness
            // (`legacy:parity` / `legacy:parity-diff`, branch
            // feat/lp4-legacy-parity) already reads `map_document` as a plain
            // table under the names below, and it is the only consumer, so the
            // names are aligned to it rather than to the sketch. The VALUES are
            // unchanged and still exactly what §8.2 describes.
            //
            // `sub_type` is the LEGACY SubType token ('INV', 'OJV', ...), which
            // is what LP4 asked for. Because "sub_type" is also the name of the
            // Akeed column holding 'LEGACY_INV'/'LEGACY_OJV', and confusing the
            // two would silently break LP4's own opening/movement partition,
            // `engine_sub_type` carries the Akeed-side value alongside it. Any
            // query that means "the opening journal document" should use
            // `engine_sub_type`, which matches `transactions.sub_type` exactly.
            $table->string('sub_type', 32)->nullable();
            $table->string('engine_sub_type', 32)->nullable();
            $table->string('legacy_doc_type', 32)->nullable();
            $table->date('doc_dt')->nullable();
            $table->unsignedBigInteger('legacy_branch_id')->nullable();
            $table->string('legacy_ref_type', 64)->nullable();
            $table->string('legacy_ref_no')->nullable();
            $table->string('legacy_ref_code')->nullable();
            $table->boolean('legacy_posted')->default(true);
            $table->string('idempotency_key')->nullable();

            // posted | skipped_no_lines | skipped_all_zero | skipped_unposted
            // | skipped_withheld_ojv | refused | stopped_type
            $table->string('status', 32);
            // engine_gap | mapping_defect | legacy_data_defect (NULL when not refused)
            $table->string('refusal_class', 32)->nullable();
            // The mapper's own class token (legacy.account_unmapped, ...) or the
            // seam exception's short class name. NULL when posted.
            $table->string('failure_code', 64)->nullable();
            $table->string('exception_class')->nullable();
            $table->text('exception_message')->nullable();

            $table->unsignedBigInteger('document_id')->nullable();   // the posted engine document (transactions.id)
            $table->string('our_reference_number')->nullable();

            // Staged sums are the DECIMAL truth read straight off the staged
            // strings (MAPPING-RULES §5.3's float-drift canary); posted sums are
            // what the engine actually wrote.
            $table->decimal('staged_debit_sum', 18, 3)->default(0);
            $table->decimal('staged_credit_sum', 18, 3)->default(0);
            $table->decimal('posted_debit_sum', 18, 3)->nullable();
            $table->decimal('posted_credit_sum', 18, 3)->nullable();

            $table->unsignedInteger('staged_line_count')->default(0);
            $table->unsignedInteger('posted_line_count')->default(0);
            $table->unsignedInteger('dropped_zero_line_count')->default(0);
            $table->unsignedInteger('dc_mismatch_line_count')->default(0);
            $table->unsignedInteger('fc_rebased_line_count')->default(0);
            $table->unsignedInteger('off_branch_line_count')->default(0);

            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('run_id', 64)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'legacy_doc_id']);
            $table->index(['company_id', 'sub_type', 'status']);
        });

        // MAPPING-RULES.md §8.2 -- the per-line audit. Without this table §3's
        // allocation replay, §4.2's line-count assertion and LP4's
        // account-diff -> document drill-down are all impossible.
        Schema::connection('legacy_pilot')->create('map_document_line', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('legacy_acc_detail_id');
            $table->unsignedBigInteger('legacy_doc_id');
            $table->unsignedBigInteger('our_journal_entry_id')->nullable();

            $table->unsignedBigInteger('legacy_acc_id_fk')->nullable();
            $table->string('resolution', 32)->nullable();
            $table->unsignedBigInteger('our_account_id')->nullable();
            $table->unsignedBigInteger('party_account_ref')->nullable();

            $table->string('legacy_dc', 1)->nullable();
            $table->string('derived_side', 8)->nullable();
            $table->boolean('dc_mismatch')->default(false);
            $table->decimal('amount', 18, 3)->default(0);

            // The dropped per-line dimensions (MAPPING-RULES §1.2 (e) / O11-branch).
            $table->unsignedBigInteger('legacy_branch_id_fk')->nullable();
            $table->date('legacy_doc_dt')->nullable();

            $table->unsignedBigInteger('legacy_fc_curr_id_fk')->nullable();
            $table->decimal('legacy_fc_exch_rate', 18, 12)->nullable();
            $table->boolean('fc_rebased_to_kwd')->default(false);
            $table->string('posted_currency', 3)->nullable();
            $table->decimal('posted_original_amount', 18, 6)->nullable();
            $table->decimal('posted_exchange_rate', 18, 12)->nullable();

            $table->string('legacy_transaction_dtl_no')->nullable();
            $table->string('legacy_transaction_type', 64)->nullable();

            // Allocation cross-check only -- never posted (MAPPING-RULES §1.2 (e)).
            $table->decimal('legacy_debit_adj', 18, 3)->nullable();
            $table->decimal('legacy_credit_adj', 18, 3)->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'legacy_acc_detail_id']);
            $table->index(['company_id', 'legacy_doc_id']);
        });

        // MAPPING-RULES.md §3 -- one row per replayed tblAccIsApply row. See
        // this migration's class docblock for why the pilot keeps its own
        // register rather than writing payment_applications.
        Schema::connection('legacy_pilot')->create('map_allocation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('idempotency_key');          // legacy:apply:<src>:<applied>
            $table->unsignedBigInteger('source_doc_id')->nullable();
            $table->unsignedBigInteger('source_acc_detail_id')->nullable();
            $table->unsignedBigInteger('applied_doc_id')->nullable();
            $table->unsignedBigInteger('applied_acc_detail_id')->nullable();
            $table->unsignedBigInteger('legacy_acc_id_fk')->nullable();
            $table->decimal('amount', 18, 3)->default(0);
            $table->string('source_dc', 1)->nullable();
            $table->dateTime('legacy_mod_dt')->nullable();

            $table->unsignedBigInteger('source_journal_entry_id')->nullable();
            $table->unsignedBigInteger('applied_journal_entry_id')->nullable();

            $table->string('status', 32);               // applied | tagged
            $table->string('failure_code', 64)->nullable();
            $table->text('note')->nullable();
            $table->string('run_id', 64)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('legacy_pilot')->dropIfExists('map_allocation');
        Schema::connection('legacy_pilot')->dropIfExists('map_document_line');
        Schema::connection('legacy_pilot')->dropIfExists('map_document');
    }
};
