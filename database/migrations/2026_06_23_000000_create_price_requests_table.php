<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('price_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('agent_id');
            $table->string('pnr')->nullable();
            $table->string('phone')->nullable();
            $table->string('country_code')->nullable();
            $table->decimal('amount', 12, 3)->nullable();
            $table->enum('status', ['pending', 'asked', 'answered', 'cancelled', 'expired'])->default('pending');
            $table->timestamp('asked_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
            $table->index(['agent_id', 'status']);
            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_requests');
    }
};
