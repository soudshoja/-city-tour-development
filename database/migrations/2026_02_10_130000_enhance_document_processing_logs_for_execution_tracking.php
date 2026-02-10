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
        Schema::table('document_processing_logs', function (Blueprint $table) {
            // ERR-01: N8n Execution Logging
            $table->timestamp('started_at')->nullable()->after('status');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->unsignedInteger('duration_ms')->nullable()->after('completed_at');
            $table->json('input_payload')->nullable()->after('duration_ms')->comment('Request payload sent to N8n');
            $table->json('output_data')->nullable()->after('input_payload')->comment('Full response from N8n');

            // ERR-03: Failed Document Marking
            $table->boolean('needs_review')->default(false)->after('status');
            $table->timestamp('reviewed_at')->nullable()->after('needs_review');
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at');
            $table->text('review_notes')->nullable()->after('reviewed_by');

            // Add foreign key for reviewer
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');

            // Add indexes for filtering and performance
            $table->index('needs_review');
            $table->index('started_at');
            $table->index('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_processing_logs', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['needs_review']);
            $table->dropIndex(['started_at']);
            $table->dropIndex(['completed_at']);
            $table->dropColumn([
                'started_at',
                'completed_at',
                'duration_ms',
                'input_payload',
                'output_data',
                'needs_review',
                'reviewed_at',
                'reviewed_by',
                'review_notes',
            ]);
        });
    }
};
