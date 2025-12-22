<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->string('status')->nullable()->comment('status from payment gateway');
            $table->string('url')->nullable();
            $table->unsignedBigInteger('payment_gateway_id')->nullable();
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->string('track_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->dateTime('expiry_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
            $table->foreign('payment_gateway_id')->references('id')->on('charges')->onDelete('set null');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
