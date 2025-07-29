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
    Schema::create('supplier_exchange_rates', function (Blueprint $table) {
        $table->id();
        $table->foreignId('company_id')->constrained()->onDelete('cascade');
        $table->string('currency', 3);
        $table->decimal('rate', 15, 3);
        $table->timestamps();
        $table->unique(['company_id', 'currency']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_exchange_rates');
    }
};
