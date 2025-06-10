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
            $table->text('self_charge')->nullable()->change();
            $table->text('charge_type')->nullable()->change();
            $table->text('paid_by')->nullable()->change();
            $table->text('description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->text('self_charge')->nullable(false)->change();
            $table->text('charge_type')->nullable(false)->change();
            $table->text('paid_by')->nullable(false)->change();
            $table->text('description')->nullable(false)->change();

        });
    }
};
