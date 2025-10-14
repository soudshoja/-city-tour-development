<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_receipt', function (Blueprint $table) {
            try {
                $table->dropForeign('invoice_receipt_invoice_id_foreign');
            } catch (\Throwable $e) {
            }
        });

        Schema::table('invoice_receipt', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable()->change();
        });

        Schema::table('invoice_receipt', function (Blueprint $table) {
            $table->foreign('invoice_id')
                ->references('id')->on('invoices')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_receipt', function (Blueprint $table) {
            try {
                $table->dropForeign('invoice_receipt_invoice_id_foreign');
            } catch (\Throwable $e) {
            }
        });

        Schema::table('invoice_receipt', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable(false)->change();
        });

        Schema::table('invoice_receipt', function (Blueprint $table) {
            $table->foreign('invoice_id')
                ->references('id')->on('invoices')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }
};
