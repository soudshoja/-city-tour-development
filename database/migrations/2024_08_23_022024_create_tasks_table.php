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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('agent_id')->constrained();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('supplier_id');
            $table->string('type');
            $table->string('status');
            $table->string('client_name');
            $table->string('reference');
            $table->string('duration');
            $table->string('payment_type');
            $table->decimal('price', 10, 2);
            $table->decimal('tax', 10, 2);
            $table->decimal('surcharge', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('cancellation_policy');
            $table->text('additional_info');
            $table->string('venue');
            $table->decimal('invoice_price', 10, 2);
            $table->string('voucher_status');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['supplier_id', 'reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
