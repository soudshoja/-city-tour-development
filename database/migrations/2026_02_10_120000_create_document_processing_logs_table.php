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
        Schema::create('document_processing_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('restrict');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->uuid('document_id')->unique();
            $table->enum('document_type', ['air', 'pdf', 'image', 'email']);
            $table->string('file_path', 500);
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('file_hash', 64)->nullable()->comment('SHA256 hash');
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued');
            $table->string('n8n_execution_id', 255)->nullable();
            $table->string('n8n_workflow_id', 255)->nullable();
            $table->json('extraction_result')->nullable()->comment('N8n callback data');
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->json('error_context')->nullable();
            $table->string('hmac_signature', 255)->nullable();
            $table->timestamp('callback_received_at')->nullable();
            $table->integer('processing_duration_ms')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('document_id');
            $table->index('company_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_processing_logs');
    }
};
