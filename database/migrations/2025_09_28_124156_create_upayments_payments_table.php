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
        Schema::create('upayments_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_int_id')->nullable()->index();
            $table->string('payment_id')->nullable()->index();
            $table->string('order_id')->nullable()->index();
            $table->string('invoice_id')->nullable()->index();
            $table->string('track_id')->nullable()->index();
            $table->string('status')->nullable();
            $table->string('payment_type')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('total_price', 12, 3)->nullable();
            $table->timestamp('payment_date')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upayments_payments');
    }
};
