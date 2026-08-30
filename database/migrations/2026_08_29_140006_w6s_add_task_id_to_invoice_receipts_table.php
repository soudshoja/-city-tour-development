<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W6.S "Hold/confirmed follow-up lifecycle" item 3 (w6-brief.md, owner addition 2026-08-28) --
 * fix round. "A receipt taken against an on hold/confirmed task posts Cr 2632 client advance
 * ... via the existing W5 RV path" requires the RV document row to be able to name WHICH task a
 * deposit belongs to (so it can be auto-applied to that task's invoice on issue() in W6.I, and so
 * the W6.U follow-up tab's "deposit held" column can sum it). `invoice_receipts` has no such
 * column today -- this is a plain additive nullable FK-shaped column, same convention as
 * `client_id`/`bank_account_id` in 2026_08_29_100000_w5r_receipt_voucher_document_columns.php
 * (no DB-level FK constraint, enforced in code -- same reasoning that migration's own docblock
 * already gives for `bank_account_id`). NULL for every existing/legacy row and for every RV that
 * is not a task deposit (invoice payment, plain account receipt, historical import, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('task_id')->nullable()->after('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_receipts', function (Blueprint $table) {
            $table->dropColumn('task_id');
        });
    }
};
