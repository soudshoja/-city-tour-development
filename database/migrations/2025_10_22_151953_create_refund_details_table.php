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
        Schema::create('refund_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained('refunds')->onDelete('cascade');
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->foreignId('refund_invoice_id')->nullable()->constrained('invoices')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->string('task_description')->nullable();
            $table->decimal('original_invoice_price', 15, 3)->nullable();
            $table->decimal('original_task_cost', 15, 3)->nullable();
            $table->decimal('original_task_profit', 15, 3)->nullable();
            $table->decimal('refund_fee_to_client', 15, 3)->default(0);
            $table->decimal('refund_task_supplier_charge', 15, 3)->default(0);
            // $table->decimal('refund_task_cost_price', 15, 3)->default(0);
            $table->decimal('new_task_profit', 15, 3)->default(0);
            $table->decimal('total_refund_to_client', 15, 3)->default(0);
            $table->decimal('net_refund', 15, 3)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });               
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refund_details');
    }
};
