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
        Schema::table('invoice_partials', function (Blueprint $table) {
            $table->dropColumn(['charge_payer', 'base_amount']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['charge_payer', 'base_amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_partials', function (Blueprint $table) {
            $table->enum('charge_payer', ['Company', 'Client'])->default('Company')->after('service_charge');
            $table->decimal('base_amount', 15, 2)->after('charge_payer');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('charge_payer', ['Client', 'Company'])->nullable()->default('Company')->after('service_charge');
            $table->decimal('base_amount', 10, 2)->nullable()->after('charge_payer');
        });
    }
};
