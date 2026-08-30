<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W5.U (w5-brief.md §W5.U "cheque image upload control ... reuse an existing attachment component
 * if one exists, check before adding a new uploader"). A repo-wide check found no reusable generic
 * attachment/upload component (only page-specific, hand-rolled file inputs wired to bespoke JS —
 * e.g. `TaskController::clientPassport()`'s `uploads/` disk convention, reused here) and no existing
 * `cheque_image`/`cheque_photo` column anywhere in the schema — a brand new, additive, nullable
 * column on both voucher tables is therefore the correct minimal fix, not a gap this sub-wave can
 * silently skip: shipping the upload control in the view with nowhere on either row to persist the
 * result would leave it exactly as dead as an unwired company-option UI (the same failure this
 * whole wave's W4.U/W5.U decision note exists to prevent, just inverted).
 *
 * `cheque_image_path` stores the relative path returned by `UploadedFile::storeAs('uploads/cheques',
 * ..., 'public')` (matches `TaskController::clientPassport()`'s existing `storeAs('uploads', ...,
 * 'public')` convention one level deeper) -- NOT the account/transaction ledger. This is document
 * metadata only: nothing on the posting path (`LineDraft`/`DocumentDraft`/`PostingService`) reads or
 * writes it, and it carries no accounting meaning of its own -- purely evidentiary, for a human
 * reviewing a cheque-based voucher later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_receipts', function (Blueprint $table) {
            $table->string('cheque_image_path', 255)->nullable()->after('cheque_clearance_date');
        });

        Schema::table('bank_payments', function (Blueprint $table) {
            $table->string('cheque_image_path', 255)->nullable()->after('cheque_clearance_date');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_receipts', function (Blueprint $table) {
            $table->dropColumn('cheque_image_path');
        });

        Schema::table('bank_payments', function (Blueprint $table) {
            $table->dropColumn('cheque_image_path');
        });
    }
};
