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
        Schema::create('general_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('transaction_id')->nullable();
            $table->foreignId('company_id');
            $table->foreignId('account_id');
            $table->foreignId('branch_id');
            $table->foreignId('invoice_id')->nullable();
            $table->foreignId('invoice_detail_id')->nullable();
            $table->foreignId('type_reference_id')->nullable();
            $table->datetime('transaction_date');
            $table->string('description');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('voucher_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_ledgers');
    }
};
