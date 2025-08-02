<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_exchange_rates', function (Blueprint $table) {
            $table->decimal('exchange_rate', 16, 6)->change();
        });

        Schema::table('currency_exchanges', function (Blueprint $table) {
            $table->decimal('exchange_rate', 16, 6)->change();
        });

        Schema::table('exchange_rate_histories', function (Blueprint $table) {
            $table->decimal('old_rate', 16, 6)->change();
            $table->decimal('new_rate', 16, 6)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_exchange_rates', function (Blueprint $table) {
            $table->decimal('exchange_rate', 10, 4)->change();
        });

        Schema::table('currency_exchanges', function (Blueprint $table) {
            $table->decimal('exchange_rate', 10, 4)->change();
        });

        Schema::table('exchange_rate_histories', function (Blueprint $table) {
            $table->decimal('old_rate', 16, 3)->change();
            $table->decimal('new_rate', 16, 3)->change();
        });
    }
};
