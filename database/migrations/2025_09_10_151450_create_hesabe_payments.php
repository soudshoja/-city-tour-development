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
        Schema::create('hesabe_payments', function (Blueprint $table) {
            $table->id();
            $table->integer('payment_int_id')->nullable();
            $table->string('status')->nullable();
            $table->string('payment_token')->nullable();
            $table->string('payment_id')->nullable();
            $table->string('order_reference_number')->nullable();
            $table->string('auth_code')->nullable();
            $table->string('track_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('invoice_id')->nullable();
            $table->datetime('paid_on')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hesabe_payments');
    }
};
