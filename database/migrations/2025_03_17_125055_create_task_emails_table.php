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
        Schema::create('task_emails', function (Blueprint $table) {
            $table->id();
            $table->string('email_id');
            $table->foreignId('client_id')->nullable()->constrained('clients');
            $table->string('client_name')->nullable();
            $table->foreignId('agent_id')->nullable()->constrained('agents');
            $table->string('agent_name')->nullable();
            $table->foreignId('company_id')->nullable()->constrained('companies');
            $table->string('company_name')->nullable();
            $table->enum('type', ['flight', 'hotel']);
            $table->string('status')->nullable();
            $table->string('reference')->nullable();
            $table->integer('duration')->nullable();
            $table->string('payment_type')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('tax', 10, 2)->nullable();
            $table->decimal('surcharge', 10, 2)->nullable();
            $table->decimal('total', 10, 2)->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->text('additional_info')->nullable();
            $table->string('destination')->nullable();
            $table->string('vendor_name')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers');
            $table->string('supplier_name')->nullable();
            $table->string('venue')->nullable();
            $table->decimal('invoice_price', 10, 2)->nullable();
            $table->string('voucher_status')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_emails');
    }
};
