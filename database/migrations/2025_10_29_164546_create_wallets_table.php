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
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_id')->nullable();
            $table->string('iata_number')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('wallet_balance', 15, 3)->nullable();
            $table->decimal('opening_balance', 15, 3)->nullable();
            $table->decimal('closing_balance', 15, 3)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
