<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create dotw_audit_logs table
 *
 * Creates the audit log table for all DOTW API operations.
 * This table stores sanitized request and response payloads
 * for every DOTW GraphQL operation (search, rates, block, book).
 *
 * Design decisions:
 * - No foreign key to companies table — module stays standalone (MOD-06)
 * - No updated_at column — audit logs are append-only
 * - company_id is nullable — company context may not always be resolved at log time
 * - request_payload and response_payload stored as longText (JSON strings)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dotw_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Company context — nullable because DOTW module is standalone (MOD-06)
            // No foreign key constraint to preserve module independence
            $table->unsignedBigInteger('company_id')->nullable()->index();

            // WhatsApp message tracking — links audit log to conversational context
            $table->string('resayil_message_id', 255)->nullable();
            $table->string('resayil_quote_id', 255)->nullable();

            // Operation classification — search/rates/block/book
            $table->enum('operation_type', ['search', 'rates', 'block', 'book']);

            // Sanitized payloads — stored as JSON strings (credentials stripped by DotwAuditService)
            $table->longText('request_payload')->nullable();
            $table->longText('response_payload')->nullable();

            // Append-only audit log — only created_at, no updated_at
            $table->timestamp('created_at')->useCurrent();
        });

        // Composite index for efficient querying by company and operation type
        Schema::table('dotw_audit_logs', function (Blueprint $table) {
            $table->index(['company_id', 'operation_type'], 'dotw_audit_logs_company_operation_idx');

            // Index for linking to WhatsApp conversations
            $table->index('resayil_message_id', 'dotw_audit_logs_message_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dotw_audit_logs');
    }
};
