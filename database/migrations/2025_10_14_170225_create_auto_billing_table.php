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
        Schema::create('auto_billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('created_by')->nullable();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('issued_by')->nullable();
            $table->foreignId('client_id')->constrained('clients');
            $table->decimal('add_amount', 10, 3)->default(1);
            $table->foreignId('gateway_id')->nullable()->constrained('charges')->nullOnDelete();
            $table->foreignId('method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->time('invoice_time_company');
            $table->time('invoice_time_system');
            $table->string('timezone')->nullable();
            $table->boolean('auto_send_whatsapp')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_billings');
    }
};
