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
        Schema::create('system_exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency');
            $table->string('exchange_currency');
            $table->decimal('exchange_rate', 10, 4);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_exchange_rates');
    }
};
