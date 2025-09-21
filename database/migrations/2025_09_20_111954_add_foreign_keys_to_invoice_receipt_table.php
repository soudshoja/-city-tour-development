<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoice_receipt', function (Blueprint $table) {
            // أضف الـ Foreign Keys
            $table->foreign('invoice_id')
                  ->references('id')->on('invoices')
                  ->onDelete('cascade');

            $table->foreign('transaction_id')
                  ->references('id')->on('transactions')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_receipt', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['transaction_id']);
        });
    }
};