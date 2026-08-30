<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W6.U residual fix round 2 (w6u-verify-2.md finding 1, BLOCKING -- hold-deposit double
 * disposition). {@see \App\Services\TaskStatusService::applyHoldDepositToInvoice()} re-points a
 * task's hold-deposit `invoice_receipts` rows onto the newly-issued invoice and posts
 * `Dr CLIENT_ADVANCE(2632) / Cr RECEIVABLE_CONTROL`, but had no column marking those rows as
 * CONSUMED -- {@see \App\Services\TaskStatusService::depositHeld()} kept summing them
 * unconditionally, so a later {@see \App\Services\TaskStatusService::voidDisposition()} disposed
 * of the same deposit a second time (repro: 500 sale / 200 deposit -> after void, 2632 netted
 * -200 instead of +200, AR netted -400 instead of 0).
 *
 * `applied_at` (nullable timestamp): NULL for a deposit still unconsumed (the task is still
 * `on hold`/`confirmed`, or was never issued) -- {@see \App\Services\TaskStatusService::
 * depositHeld()} now filters on `whereNull('applied_at')`, so a consumed deposit becomes
 * invisible to it, exactly the W6.S/`cancel()` parity case (a never-issued task's deposit must
 * still read back via `depositHeld()` unchanged). Set the moment
 * `applyHoldDepositToInvoice()` re-points the row.
 *
 * `applied_transaction_id` (nullable, no DB-level FK constraint -- same convention as `task_id`/
 * `bank_account_id` on this same table, enforced in code only): the posted `deposit_apply:
 * {task_id}` JV's transaction id, an audit breadcrumb for "which document consumed this
 * deposit" (parallel to `transaction_id`, which already exists for "which document CREATED this
 * deposit").
 *
 * Both columns are additive and nullable; every existing row (and every row for a deposit that
 * is never applied through this path) is byte-for-byte unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_receipts', function (Blueprint $table) {
            $table->timestamp('applied_at')->nullable()->after('invoice_partial_id');
            $table->unsignedBigInteger('applied_transaction_id')->nullable()->after('applied_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_receipts', function (Blueprint $table) {
            $table->dropColumn(['applied_at', 'applied_transaction_id']);
        });
    }
};
