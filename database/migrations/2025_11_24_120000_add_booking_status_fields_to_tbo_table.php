<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tbo', function (Blueprint $table) {
            $table->string('confirmation_no')->nullable()->after('booking_code');
            $table->string('booking_reference_id')->nullable()->after('confirmation_no');
            $table->enum('payment_status', ['paid', 'unpaid', 'pending'])->default('pending')->after('booking_reference_id');
            $table->string('supplier_status')->nullable()->after('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbo', function (Blueprint $table) {
            $table->dropColumn(['confirmation_no', 'booking_reference_id', 'payment_status', 'supplier_status']);
        });
    }
};
