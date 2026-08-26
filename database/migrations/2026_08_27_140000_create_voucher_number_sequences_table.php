<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Voucher Templates plan §14.6 / §3.4: "serial_schemas is accounting-owned;
// voucher numbering builds its own per-company sequence." This is that
// sequence, kept in its OWN new table rather than reusing the existing
// accounting-adjacent `sequences` table PaymentController::nextVoucherNumber()
// already uses for receipt-voucher numbers (VOU-YYYY-NNNNN) — sharing that
// row per company_id would race two unrelated numbering schemes against the
// same lockForUpdate() row with no discriminator column, corrupting BOTH
// sequences. This table exists solely for VCH-{seq} travel-voucher numbers
// (Step 4, plan §16) and is never read by anything accounting-owned.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->unsignedInteger('current_sequence')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_number_sequences');
    }
};
