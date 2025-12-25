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
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->enum('target_type', ['invoice', 'payment', 'client', 'agent']);
            $table->foreignId('agent_id')->constrained('agents')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->onDelete('cascade');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->onDelete('cascade');
            $table->text('message')->nullable();
            $table->uuid('group_id')->nullable();
            $table->boolean('send_to_client')->default(false);
            $table->boolean('send_to_agent')->default(false);
            $table->enum('frequency', ['once', 'auto'])->default('once');
            $table->integer('value')->nullable();
            $table->enum('unit', ['hours', 'days', 'weekly', 'monthly'])->default('hours');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->enum('status', ['sent', 'pending', 'failed'])->default('pending');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
