<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('voucher_number')->nullable()->change();
            $table->string('payment_reference')->nullable()->change();
            $table->string('from')->nullable()->change();
            $table->renameColumn('pay', 'pay_to');
            $table->string('pay_to')->nullable()->change();
            $table->string('currency')->nullable()->change();
            $table->datetime('payment_date')->nullable()->change();
            $table->string('status')->nullable()->change();
            $table->string('account_number')->nullable()->change();
            $table->string('bank_name')->nullable()->change();
            $table->string('swift_no')->nullable()->change();
            $table->string('iban_no')->nullable()->change();
            $table->string('country')->nullable()->change();
            $table->decimal('tax', 10, 2)->nullable()->change();
            $table->decimal('shipping', 10, 2)->nullable()->change();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('voucher_number')->nullable(false)->change();
            $table->string('payment_reference')->nullable(false)->change();
            $table->string('from')->nullable(false)->change();
            $table->renameColumn('pay_to', 'pay');
            $table->string('pay_to')->nullable(false)->change();
            $table->string('currency')->nullable(false)->change();
            $table->datetime('payment_date')->nullable(false)->change();
            $table->string('status')->nullable(false)->change();
            $table->string('account_number')->nullable(false)->change();
            $table->string('bank_name')->nullable(false)->change();
            $table->string('swift_no')->nullable(false)->change();
            $table->string('iban_no')->nullable(false)->change();
            $table->string('country')->nullable(false)->change();
            $table->decimal('tax', 10, 2)->nullable(false)->change();
            $table->decimal('shipping', 10, 2)->nullable(false)->change();
            $table->dropTimestamps();
        });
    }
};
