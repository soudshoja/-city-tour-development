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
        Schema::table('credits', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('payment_applications', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('invoice_partials', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('payment_applications', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('invoice_partials', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
