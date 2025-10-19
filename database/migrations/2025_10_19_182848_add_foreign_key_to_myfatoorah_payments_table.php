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
        Schema::table('myfatoorah_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_int_id')->change();
            $table->foreign('payment_int_id')->references('id')->on('payments')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('myfatoorah_payments', function (Blueprint $table) {
            $table->dropForeign(['payment_int_id']);
        });
    }
};
