<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G): the FIX-NOW drill-down panel's backing store. A row here is a
 * DRAFT correcting document -- clicking "fix-now" writes ONE of these rows and nothing else; it
 * NEVER calls PostingService directly (brief: "actions that DRAFT a correcting document for the
 * normal approval path -- never posted directly"). A second, distinct action
 * (ReconciliationFixDraftService::post()) is the actual "normal approval path" step that turns a
 * `draft` row into a real posted transaction -- see that service's own docblock for the
 * threshold-gated auto-vs-hold split (VoucherOptions::approvalThreshold(), the same option every
 * other voucher-with-a-draft-state in this codebase already consults).
 *
 * `kind` is one of the four the brief names literally: bank_charge_pv | gateway_timing_jv |
 * unapply_reapply_receipt | writeoff_proposal. `target_purpose_code`/`target_account_code` record
 * WHICH leaf the draft would credit/debit against the gap account (resolved via AccountResolver
 * purpose code when one is registered, else a literal company-scoped `accounts.code` lookup --
 * never by name; see ReconciliationFixDraftService::resolveTargetAccount()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_fix_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedBigInteger('proposal_id')->nullable();
            $table->unsignedBigInteger('account_id'); // the gap-bearing leaf this draft addresses
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('kind', 40);
            $table->string('doc_type', 10); // PV|JV
            $table->decimal('amount', 15, 3);
            $table->text('narration');
            $table->string('target_purpose_code', 60)->nullable();
            $table->string('target_account_code', 20)->nullable();
            $table->enum('status', ['draft', 'posted', 'discarded'])->default('draft');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->text('reason')->nullable(); // discard reason, when status = discarded
            $table->timestamps();

            $table->index(['company_id', 'account_id', 'status']);
            $table->index('proposal_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_fix_drafts');
    }
};
