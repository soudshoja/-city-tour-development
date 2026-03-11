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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('agent_id')->constrained();
            $table->string('currency');
            $table->decimal('sub_amount', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['paid', 'unpaid', 'partial']);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->timestamp('paid_date')->nullable();
            $table->string('label')->nullable();
            $table->string('account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('swift_no')->nullable();
            $table->string('iban_no')->nullable();
            $table->foreignId('country_id')->nullable();
            $table->decimal('tax', 15, 2)->nullable();
            $table->decimal('discount', 15, 2)->nullable();
            $table->decimal('shipping', 15, 2)->nullable();
            $table->string('accept_payment')->nullable();
            $table->string('payment_type')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice');
    }
};
