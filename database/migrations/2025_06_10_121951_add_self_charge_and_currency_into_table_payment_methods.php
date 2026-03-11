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
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('currency')->nullable()->after('is_active');
            $table->decimal('self_charge')->nullable()->after('service_charge');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table)
        {
            $table->dropColumn('currency', 'self_charge');
        });
    }
};
