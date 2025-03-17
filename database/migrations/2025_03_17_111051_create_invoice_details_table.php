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
        Schema::create('invoice_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained();
            $table->string('invoice_number');
            $table->foreignId('task_id')->constrained();
            $table->string('task_description');
            $table->string('task_remark');
            $table->string('client_notes');
            $table->decimal('task_price', 10, 2);
            $table->decimal('supplier_price', 10, 2);
            $table->decimal('markup_price', 10, 2);
            $table->boolean('paid');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_details');
    }
};
