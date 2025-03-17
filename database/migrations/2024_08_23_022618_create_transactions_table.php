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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id');
            $table->enum('entity_type', ['company', 'branch','agent', 'client']);
            $table->string('transaction_type');
            $table->float('amount');
            $table->dateTime('date');
            $table->text('description');
            $table->foreignId('invoice_id')->nullable();
            $table->enum('reference_type', ['Invoice', 'Payment']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
