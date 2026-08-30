<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W6.U (w6-brief.md "W6.U — UI" — void-with-fee: "an override input + an approval-required flag
 * when refund_fee_override=needs_approval and the override differs from schedule (route to an
 * approve step, gated by policy, before posting)"). A small, generic queue for a fee-override that
 * needs a second person's sign-off before {@see \App\Services\TaskStatusService::void()} or
 * {@see \App\Services\TaskStatusService::reissue()} is actually called with it — mirrors
 * {@see \App\Services\Accounting\SupplierChargeOverridePendingApprovalException}'s OWN
 * 'free'|'needs_approval' Setting-key gate (W6.C), applied here to the CLIENT-side fee override
 * instead of the supplier-side one. `action` is deliberately a plain string, not an enum, so a
 * future action kind (e.g. a reissue-fee override) can reuse this same table without a migration.
 *
 * This table never posts anything itself -- {@see \App\Http\Controllers\TaskController}'s
 * approve/reject actions are the only writers of `status`/`approved_by`/`decided_at`, and only the
 * approve path goes on to call the real engine method with the stored payload.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_pending_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            // 'void_with_fee' | 'reissue_with_fee' -- string, not enum, so a future action kind
            // needs no migration (see class docblock).
            $table->string('action', 40);
            // Everything the eventual approve() call needs to actually post: fee override amount,
            // schedule fee at request time (for audit), sub_type, new_task_id for a reissue, etc.
            $table->json('payload');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_pending_actions');
    }
};
