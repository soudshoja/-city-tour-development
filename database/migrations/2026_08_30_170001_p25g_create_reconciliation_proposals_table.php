<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G; reconciliation-design.md §6/§9): the PROPOSALS queue every
 * Reconciliation Center row's first drill-down panel reads. v0 scope is `source = 'internal'`
 * only (reconciliation-design.md §6: "no statement import" until P5.10 v1) -- the column still
 * carries `external` as a legal future value so P5.10 lands as an additive value, not a schema
 * change.
 *
 * `kind` names WHICH internal check produced the row (brief's own three: receipt<->invoice
 * consistency, sub-ledger vs control, clearing roll-forward) plus `manual` for an operator's own
 * match (App\Services\Accounting\ReconciliationCenterService::manualMatch()) recorded through the
 * same table so the row's HISTORY drawer has one source of truth for every match, auto or manual.
 *
 * `confidence` mirrors reconciliation-design.md §2's three-tier vocabulary (exact/tolerance/
 * suggested) plus `manual` for an operator-entered match.
 *
 * No FK constraints on `account_id`/`book_journal_entry_id`/`matched_journal_entry_id` -- same
 * soft-cross-reference convention `journal_entries.reconciled_ref_id`/`.task_id` and this wave's
 * other new tables already use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedBigInteger('run_id')->nullable();
            $table->unsignedBigInteger('account_id');
            $table->enum('source', ['internal', 'external'])->default('internal');
            $table->string('kind', 40); // receipt_invoice_consistency|sub_ledger_vs_control|clearing_rollforward|manual
            $table->enum('confidence', ['exact', 'tolerance', 'suggested', 'manual'])->default('suggested');
            $table->unsignedBigInteger('book_journal_entry_id')->nullable();
            $table->unsignedBigInteger('matched_journal_entry_id')->nullable();
            $table->string('matched_reference', 160)->nullable();
            $table->decimal('amount', 15, 3)->default(0);
            $table->decimal('difference_amount', 15, 3)->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedSmallInteger('period_year')->nullable();
            $table->unsignedTinyInteger('period_month')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'account_id', 'status']);
            $table->index('run_id');
            $table->index('status');
            $table->index('book_journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_proposals');
    }
};
