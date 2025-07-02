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
            $table->enum('charge_payer', ['Company', 'Client'])->default('Company')->after('service_charge');
            $table->decimal('base_amount', 15, 2)->after('charge_payer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_partials', function (Blueprint $table) {
            $table->dropColumn('base_amount', 'charge_payer');
        });
    }
};
