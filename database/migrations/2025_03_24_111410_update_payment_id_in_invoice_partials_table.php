<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_partials', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_partials', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_id')->nullable(false)->change();
        });
    }
};
