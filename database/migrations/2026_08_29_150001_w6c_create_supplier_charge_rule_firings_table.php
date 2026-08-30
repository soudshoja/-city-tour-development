<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W6.C companion table for `once_per_reference` dedup (w6-brief.md: "`once_per_reference=true`
 * fires once across a reissue chain sharing the same `reference` (second occurrence is a no-op,
 * logged)"). NOT a revival of the retired `supplier_surcharge_references` table -- that table
 * carried `charge_behavior` + `is_charged` config semantics that this build collapses onto
 * `supplier_charge_rules.once_per_reference` itself (see the backfill command); this table is a
 * pure, minimal FIRING LEDGER (one row per rule+reference actually posted), consulted by
 * App\Services\Accounting\SupplierChargeRuleResolver::hasAlreadyFired() before a rule is included
 * in a sale's LineDraft[], and written by ::recordFiring() -- which the eventual caller
 * (W6.I's TaskStatusService::issue()) must invoke inside the SAME DB transaction as the
 * PostingSeam::post() call it guards, AFTER that post succeeds, so a rolled-back post never
 * leaves a stale firing record behind. See that class's own docblock for the full contract.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_charge_rule_firings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_charge_rule_id')->constrained('supplier_charge_rules')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('reference');
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->timestamp('fired_at');
            $table->timestamps();

            $table->unique(['supplier_charge_rule_id', 'reference'], 'supplier_charge_rule_firings_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_charge_rule_firings');
    }
};
