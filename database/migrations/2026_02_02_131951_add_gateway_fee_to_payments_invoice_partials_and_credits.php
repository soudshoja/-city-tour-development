<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('gateway_fee', 10, 3)->default(0)->after('service_charge');
        });

        Schema::table('invoice_partials', function (Blueprint $table) {
            $table->decimal('gateway_fee', 10, 3)->default(0)->after('service_charge');
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->decimal('gateway_fee', 10, 3)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('gateway_fee');
        });

        Schema::table('invoice_partials', function (Blueprint $table) {
            $table->dropColumn('gateway_fee');
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->dropColumn('gateway_fee');
        });
    }
};
