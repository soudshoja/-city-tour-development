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
        Schema::create('exchange_rate_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('currency_exchange_id');
            $table->string('base_currency');
            $table->string('exchange_currency');
            $table->decimal('old_rate', 16, 3)->nullable();
            $table->decimal('new_rate', 16, 3);
            $table->string('method')->default('manual'); // manual/auto
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();

            $table->foreign('currency_exchange_id')->references('id')->on('currency_exchanges')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_histories');
    }
};
