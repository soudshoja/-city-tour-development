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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number');
            $table->string('payment_reference');
            $table->foreignId('invoice_id')->nullable();
            $table->foreignId('account_id')->nullable();
            $table->string('from');
            $table->string('pay');
            $table->decimal('amount', 10, 2);
            $table->string('currency');
            $table->datetime('payment_date');
            $table->string('payment_method');
            $table->string('status');
            $table->string('account_number');
            $table->string('bank_name');
            $table->string('swift_no');
            $table->string('iban_no');
            $table->string('country');
            $table->decimal('tax', 10, 2);
            $table->decimal('shipping', 10, 2);

            $table->foreign('invoice_id')->references('id')->on('invoices');
            $table->foreign('account_id')->references('id')->on('accounts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
