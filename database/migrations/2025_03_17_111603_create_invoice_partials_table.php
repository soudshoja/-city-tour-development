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
        Schema::create('invoice_partials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id');
            $table->string('invoice_number');
            $table->foreignId('client_id');
            $table->decimal('amount', 15, 2);
            $table->string('status');
            $table->date('expiry_date');
            $table->string('type');
            $table->string('payment_gateway');
            $table->foreignId('payment_id');
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices');
            $table->foreign('client_id')->references('id')->on('clients');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_partials');
    }
};
