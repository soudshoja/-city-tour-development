<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W6.S audit trail for STATUS-ONLY events that have no ledger document to anchor an audit row to
 * (expire(), a needs_review classification, per-supplier-status-map admin writes, hold
 * extend/cancel/note actions -- see w6-brief.md's W6.S hold-lifecycle and per-supplier-status-map
 * sections, each of which says "writes an audit row"). Deliberately NOT the same mechanism as the
 * engine's own `Log::info('accounting.*', ...)` convention (PostingSeam, RefundPostingService,
 * etc.) -- these events never touch journal_entries/transactions, so there is nothing for the
 * engine's audit channel to correlate them to, and w6-brief.md's "do not build a third audit
 * mechanism" instruction is specifically about the LEDGER audit trail (W6.I point 6: issue/EMD/
 * commission postings route through the existing accounting.* Log:: convention or the future
 * accounting_audit_log table -- untouched here). A small, queryable, testable table is the
 * pragmatic choice for a fact ("this task's status/mapping changed for this reason") that a human
 * (the W6.U follow-up tab / supplier-status-map screen) needs to list back, which a log line does
 * not support.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->string('event', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->string('channel', 16)->nullable();
            $table->string('raw_status', 64)->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['company_id', 'event']);
            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_status_events');
    }
};
