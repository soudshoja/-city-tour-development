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
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_id')->nullable()->after('invoice_id');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');

            $table->text('remarks_internal')->nullable()->comment('Used in payment voucher')->change();
            $table->text('remarks_fl')->nullable()->comment('Used in payment voucher')->change();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('set null');

            $table->dropColumn('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropColumn('payment_id');

            $table->text('remarks_internal')->nullable()->comment()->change();
            $table->text('remarks_fl')->nullable()->comment()->change();

            $table->dropForeign(['invoice_id']);

            $table->dateTime('date')->nullable()->after('amount');
        });
    }
};
