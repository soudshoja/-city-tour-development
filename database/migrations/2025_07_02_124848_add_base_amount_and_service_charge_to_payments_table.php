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
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('service_charge', 8, 2)->nullable()->after('created_by');
            $table->enum('charge_payer', ['Client', 'Company'])->nullable()->default('Company')->after('service_charge');
            $table->decimal('base_amount', 10, 2)->nullable()->after('charge_payer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['base_amount', 'service_charge', 'charge_payer']);
        });
    }
};
