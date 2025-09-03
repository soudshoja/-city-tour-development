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
        Schema::create('assignment_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_token', 32)->unique()->index();
            $table->unsignedBigInteger('owner_agent_id');
            $table->unsignedBigInteger('requesting_agent_id');
            $table->unsignedBigInteger('client_id');
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'denied', 'expired'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->text('process_note')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('owner_agent_id')->references('id')->on('agents')->onDelete('cascade');
            $table->foreign('requesting_agent_id')->references('id')->on('agents')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for performance
            $table->index(['owner_agent_id', 'status']);
            $table->index(['requesting_agent_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_requests');
    }
};
