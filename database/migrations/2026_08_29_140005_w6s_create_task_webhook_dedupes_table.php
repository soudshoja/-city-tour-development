<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W6.S item (4) (w6-brief.md "Consolidation + fixes" -- "Add payload_hash dedupe alongside" the
 * webhook signature check). Deliberately a SEPARATE table from `webhook_audit_logs` (which the
 * pre-existing, previously-unwired App\Http\Middleware\VerifyWebhookSignature already writes to on
 * every request whether verified or not): that table's `payload_hash` is a traceability field only
 * ("SHA256 of payload for traceability" -- see its own migration comment) and it is not this
 * sub-wave's to touch or repurpose (VerifyWebhookSignature/WebhookAuditLog belong to the other
 * track's HMAC work). This table is written ONLY by TaskWebhook::webhook(), only after a request
 * has passed signature verification and been fully processed, and is the single source of truth
 * for "have we already accepted this exact payload from this webhook client" -- a redelivery of
 * the identical payload short-circuits to a 200 idempotent no-op before any Task is created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_webhook_dedupes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_client_id')->nullable()->constrained('webhook_clients')->nullOnDelete();
            $table->string('payload_hash', 64);
            $table->unsignedBigInteger('task_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['webhook_client_id', 'payload_hash'], 'task_webhook_dedupes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_webhook_dedupes');
    }
};
