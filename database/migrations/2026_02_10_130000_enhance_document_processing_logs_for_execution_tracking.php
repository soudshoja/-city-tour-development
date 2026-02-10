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
            if (!Schema::hasColumn('document_processing_logs', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('document_processing_logs', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('started_at');
            }
            if (!Schema::hasColumn('document_processing_logs', 'duration_ms')) {
                $table->unsignedInteger('duration_ms')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('document_processing_logs', 'input_payload')) {
                $table->json('input_payload')->nullable()->after('duration_ms')->comment('Request payload sent to N8n');
            }
            if (!Schema::hasColumn('document_processing_logs', 'output_data')) {
                $table->json('output_data')->nullable()->after('input_payload')->comment('Full response from N8n');
            }

            // ERR-03: Failed Document Marking
            if (!Schema::hasColumn('document_processing_logs', 'needs_review')) {
                $table->boolean('needs_review')->default(false)->after('status');
            }
            if (!Schema::hasColumn('document_processing_logs', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('needs_review');
            }
            if (!Schema::hasColumn('document_processing_logs', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at');
            }
            if (!Schema::hasColumn('document_processing_logs', 'review_notes')) {
                $table->text('review_notes')->nullable()->after('reviewed_by');
            }

            // Add foreign key for reviewer - only if column exists and FK doesn't
            if (Schema::hasColumn('document_processing_logs', 'reviewed_by')) {
                try {
                    $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
                } catch (\Exception $e) {
                    // Foreign key might already exist, continue
                }
            }

            // Add indexes for filtering and performance - wrapped in try-catch
            try {
                $table->index('needs_review');
            } catch (\Exception $e) {
                // Index might already exist
            }
            try {
                $table->index('started_at');
            } catch (\Exception $e) {
                // Index might already exist
            }
            try {
                $table->index('completed_at');
            } catch (\Exception $e) {
                // Index might already exist
            }
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
