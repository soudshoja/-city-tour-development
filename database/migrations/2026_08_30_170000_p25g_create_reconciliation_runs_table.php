<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G; reconciliation-design.md §6/§7/§9): the "AUTO RUN + TRAIL" run-
 * status panel's backing store — one row per `accounting:reconcile --auto` execution (scheduled
 * nightly, per §9) or per manual "Run-now" click (permission `accounting.reconcile`, queued job,
 * `withoutOverlapping` — the brief's own literal words).
 *
 * `trigger` distinguishes the two (nightly vs manual) so the run-status panel can show "last
 * nightly run" separately from an operator's own manual runs. `triggered_by` is nullable because
 * a nightly/system run has no acting user. Counts (`proposals_created`, `auto_matched_pending`,
 * `exceptions_count`) are the exact fields the brief's run-status panel names: "last nightly run
 * time, proposals created, auto-matched-pending-approval count, exceptions, duration."
 *
 * No FK constraint on `triggered_by` -- same soft-cross-reference convention every other
 * accounting table in this wave already uses for a `users` reference outside `company_id` itself
 * (see e.g. `accounting_audit_log.actor_id`, `accounting_periods.closed_by`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->enum('trigger', ['nightly', 'manual'])->default('manual');
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('proposals_created')->default(0);
            $table->unsignedInteger('auto_matched_pending')->default(0);
            $table->unsignedInteger('exceptions_count')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'trigger', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
    }
};
