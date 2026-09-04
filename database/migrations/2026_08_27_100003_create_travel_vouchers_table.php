<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Issued vouchers, plan §3.3 + §13-BIS. A voucher is an EVENT, not a live
// query: once produced it must not silently change because someone edited
// the task the next day, hence the frozen `snapshot`. `token` (64-char
// random) is the ONLY public handle — never the id (§11.1). `status`
// covers the full lifecycle the owner specified in §13-BIS: reissue/refund
// keep the original around annotated and spawn a new voucher
// (superseded_by_id); void keeps the same number+token either updated
// silently in place (§13-BIS.B) or, after the 7-day grace window with
// nothing qualifying arriving, expired to "Cancel V" (§13-BIS.C).
// `snapshot_history` is the operator-only audit trail of what a voucher
// looked like before a silent in-place void/re-issue update — never
// client-facing.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');

            $table->string('voucher_number'); // per-company: VCH-000123 (§14.6)
            $table->foreignId('voucher_template_id')->constrained('voucher_templates');

            // subject_type/subject_id -> App\Models\Task or App\Models\TaskPackage.
            // morphs() also creates the standard subject_type+subject_id index,
            // so no separate manual index is added (plan §3.3 lists one; this
            // is the same index Laravel names automatically).
            $table->morphs('subject');

            $table->string('language', 3)->default('EN');
            $table->char('token', 64)->unique(); // the ONLY public handle, §11.1

            $table->json('snapshot'); // full resolved variable payload at issue time (§14.7)
            $table->json('snapshot_history')->nullable(); // append-only, operator-only audit (§13-BIS.B)

            $table->unsignedSmallInteger('version')->default(1);

            $table->string('status', 20)->default('issued')->comment(
                'issued | reissued | refunded | void_pending | cancelled (Cancel V, internal label) | superseded'
            );
            $table->foreignId('superseded_by_id')->nullable()->constrained('travel_vouchers')->nullOnDelete();

            $table->string('pdf_path')->nullable();          // storage/app/vouchers/{company_id}/... private disk only
            $table->string('resayil_file_id')->nullable();   // upload cache, PaymentReceiptService pattern
            $table->string('sent_to_phone')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['company_id', 'voucher_number'], 'travel_vouchers_company_number_unique');
            $table->index('status', 'travel_vouchers_status_idx'); // void_pending grace-window sweeps, staff filters
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_vouchers');
    }
};
