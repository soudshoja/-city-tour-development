<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tap_payments', function (Blueprint $table) {
            $table->string('customer_reference')->nullable()->after('receipt_sms');
            $table->json('payload')->nullable()->after('customer_reference');
        });
    }

    public function down(): void
    {
        Schema::table('tap_payments', function (Blueprint $table) {
            $table->dropColumn(['customer_reference', 'payload']);
        });
    }
};
