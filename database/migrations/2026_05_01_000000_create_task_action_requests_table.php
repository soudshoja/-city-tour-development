<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks owner-acknowledgment requests for refund/void/reissue tasks performed
 * by an agent OTHER than the original task's agent. Companion to the
 * client_assignment_requests workflow but for cross-agent ticket actions:
 * Approve credits the new task's sale to the actor; Deny flips it back to
 * the owner. See app/Console/Commands/ProcessAirFiles.php hook.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_action_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_token', 32)->unique()->index();
            $table->unsignedBigInteger('task_id')->comment('refund/void/reissue task — the new task created by actor');
            $table->unsignedBigInteger('original_task_id')->comment('parent issuance task');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('owner_agent_id')->comment('agent_id of original_task');
            $table->unsignedBigInteger('actor_agent_id')->comment('agent who performed the refund/reissue');
            $table->enum('action_type', ['refund', 'void', 'reissue']);
            // bundled_task_ids: array of additional task IDs grouped under this single request
            // (multi-passenger AIR file produces N tasks per client; one request covers all of them).
            $table->json('bundled_task_ids')->nullable();
            // notify-only when actor IS in client_agents pivot for the client; no approve/deny needed.
            $table->boolean('notify_only')->default(false);
            $table->enum('status', ['pending', 'approved', 'denied', 'auto_approved', 'expired'])->default('pending');
            $table->timestamp('escalated_at')->nullable()->comment('when admin/accountant were notified for >2-day pending');
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->string('processed_via', 32)->nullable()->comment('whatsapp|web|api|admin_override|cron');
            $table->text('process_note')->nullable();
            $table->timestamps();

            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('original_task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('owner_agent_id')->references('id')->on('agents')->onDelete('cascade');
            $table->foreign('actor_agent_id')->references('id')->on('agents')->onDelete('cascade');
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['owner_agent_id', 'status']);
            $table->index(['actor_agent_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->index('escalated_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_action_requests');
    }
};
