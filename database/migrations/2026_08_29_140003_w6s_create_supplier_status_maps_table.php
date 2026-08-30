<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W6.S "Per-supplier status map (replaces hard-coded supplier branches)" (w6-brief.md, owner
 * addition 2026-08-28). Replaces the hard-coded per-supplier-name branches scattered across
 * TaskController::store(), TaskWebhook::applyStatusMapping(), and
 * TaskController::processSingleReservation() with a company-configurable table.
 *
 * Resolution order (App\Services\TaskStatusService::mapStatus()):
 *   1. company_id + supplier_id + channel + raw_status  (most specific: one supplier's own map)
 *   2. company_id + channel + raw_status, supplier_id NULL  (channel-wide default for a company)
 *   3. company_id NULL + channel + raw_status  (global default, ships with the codebase)
 * `company_id` NULL = the seeded fallback of last resort, not editable per company.
 * `supplier_id` NULL (with company_id set) = a company's own channel-wide default, applied to
 * every supplier on that channel until a supplier-specific row exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_status_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->cascadeOnDelete();
            $table->enum('channel', ['air', 'magic', 'webhook', 'ai_pdf', 'manual']);
            $table->string('raw_status', 64);
            $table->enum('canonical_status', [
                'on_hold',
                'confirmed',
                'issued',
                'reissued',
                'void',
                'refund',
                'emd',
                'cancelled',
                'needs_review',
            ]);
            $table->string('deadline_source')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'supplier_id', 'channel', 'raw_status'], 'supplier_status_maps_unique_row');
            $table->index(['channel', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_status_maps');
    }
};
