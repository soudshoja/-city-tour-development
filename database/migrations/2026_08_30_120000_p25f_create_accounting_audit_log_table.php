<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2.5.F (p2_5-brief.md §P2.5.F; doc 11 §400): the Accounting Log Center's append-only store —
 * "the audit trail for people; the ledger is the audit trail for money." One row per: (a) an engine
 * document post/reverse/repost ({@see \App\Services\Accounting\PostingService}), (b) a
 * Gate::authorize-guarded accounting mutation (period close/reopen, unlock, reconcile/unreconcile,
 * refund approve/reject, company option changes), or (c) one of the 15 `accounting.*` Log::info()
 * events already emitted across the accounting engine (verified count against the working tree
 * 2026-08-30 — see {@see \App\Services\Accounting\AccountingLog}'s own docblock for the full list
 * and which of the two writer paths each one now takes).
 *
 * Column set matches the brief's literal list. `subject_type` deliberately stores the SAME short,
 * friendly strings {@see \App\Services\Accounting\AuditLogLinker} (a P2.5.E deliverable, shipped
 * ahead of this migration) already commits to in its own docblock ('invoice', 'payment',
 * 'invoice_receipt', 'transaction', 'journal_entry', 'accounting_period', ...) — never a raw PHP
 * FQCN — so a filter/URL built against one of those strings needs no separate mapping table.
 *
 * `subject_id` is nullable: a handful of the 15 mirrored events (e.g. `period_locked_override`,
 * fired before any row exists to attach to) have no single numeric row id to name — see
 * AccountingLog's own docblock for exactly which.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_audit_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->enum('actor_type', ['user', 'system', 'webhook'])->default('system');
            $table->string('action', 48);
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('route', 191)->nullable();
            // yyyy-mm — a plain string, not a date: it names an accounting PERIOD, never a
            // calendar day, matching every other posting_period value in this wave
            // (journal_entries/transactions carry a real posting_date; this is the derived label).
            $table->string('posting_period', 7)->nullable();
            $table->timestamp('created_at')->nullable();

            // "add the indexes the filters need (action, posting_period, created_at, actor_type)"
            // (owner refinement 2026-08-30) plus the brief's own literal four:
            $table->index(['company_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('transaction_id');
            $table->index('actor_id');
            $table->index('action');
            $table->index('posting_period');
            $table->index('created_at');
            $table->index('actor_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_audit_log');
    }
};
