<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_partials', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_partials', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
        });
    }
};
