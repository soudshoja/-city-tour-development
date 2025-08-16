<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn(['self_charge', 'charge_type' , 'paid_by']);
            $table->decimal('self_charge', 8, 2)->nullable()->after('currency');
            $table->enum('charge_type', ['Percent', 'Flat Rate'])->default('Percent')->after('self_charge');
            $table->enum('paid_by', ['Company', 'Client'])->default('Company')->after('charge_type');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
        });
    }
};
