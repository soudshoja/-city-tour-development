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
            $table->json('created_by_list')->nullable(); // e.g. ["KWIKT2727"]
            $table->json('agent_ids')->nullable(); // e.g. [12, 25]
            $table->json('issued_by_list')->nullable();  // e.g. ["Ahmad", "Ali"]
            $table->foreignId('client_id')->constrained('clients');
            $table->decimal('add_amount', 10, 3)->default(1);
            $table->string('gateway')->nullable();
            $table->string('method')->nullable();
            $table->time('invoice_time_company');
            $table->time('invoice_time_system');
            $table->string('timezone')->nullable();
            $table->boolean('auto_send_whatsapp')->default(false);
            $table->boolean('active')->default(true);
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
