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
        Schema::create('tap_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id');
            $table->string('tap_id');
            $table->string('authorization_id')->nullable();
            $table->string('timezone')->nullable();
            $table->integer('expiry_period')->nullable();
            $table->string('expiry_type')->nullable();
            $table->decimal('amount', 10, 3);
            $table->string('currency', 3)->default('KWD');
            $table->dateTime('date_created')->nullable();
            $table->dateTime('date_completed')->nullable();
            $table->dateTime('date_transaction')->nullable();
            $table->string('receipt_id')->nullable();
            $table->boolean('receipt_email')->default(false);
            $table->boolean('receipt_sms')->default(false);
            $table->timestamps();

            $table->foreign('payment_id')->references('id')->on('payments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tap_payments');
    }
};
