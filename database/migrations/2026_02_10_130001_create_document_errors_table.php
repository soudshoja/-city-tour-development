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
        Schema::create('document_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_processing_log_id')
                ->constrained('document_processing_logs')
                ->onDelete('cascade');

            // Error classification
            $table->enum('error_type', [
                'transient',
                'non_transient',
                'system'
            ])->comment('Error category for retry strategy');

            $table->string('error_code', 50)->index()->comment('ERR_* code from registry');
            $table->text('error_message');
            $table->text('stack_trace')->nullable();
            $table->json('input_context')->nullable()->comment('Request data at time of error');

            // Retry tracking
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('last_retry_at')->nullable();

            // Resolution tracking
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->text('resolution_notes')->nullable();

            $table->timestamps();

            // Foreign key for resolver
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for queries
            $table->index('error_type');
            $table->index('error_code');
            $table->index(['resolved_at', 'error_type']); // Composite for unresolved errors
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_errors');
    }
};
