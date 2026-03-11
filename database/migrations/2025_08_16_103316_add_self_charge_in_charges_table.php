<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {  
            $table->enum('paid_by', ['Company', 'Client'])->default('Client')->change();
            $table->enum('charge_type', ['Percent', 'Flat Rate'])->default('Percent')->comment('Type of charge: Percent or Flat Rate')->change();

            $table->decimal('self_charge', 10, 2)->nullable()->after('amount')->comment('Charge set by company');
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->enum('paid_by', ['Company', 'Client'])->nullable()->change();
            $table->dropColumn(['self_charge']);
        });
    }
};
