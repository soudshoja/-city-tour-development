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
            $table->enum('charge_type', ['Percent', 'Flat Rate'])->after('service_charge');
            $table->enum('paid_by', ['Client', 'Company'])->after('charge_type');
            $table->string('description')->after('paid_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::tbale('payment_methods', function (Blueprint $table) {
            $table->dropColumm('charge_type', 'paid_by', 'description');
        });
    }
};
