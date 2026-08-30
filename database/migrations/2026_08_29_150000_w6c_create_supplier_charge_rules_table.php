<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W6.C "Supplier-side charges" (w6-brief.md; source supplier-charges-design.md Table 4, binding).
 * Config table (not a task-time snapshot) describing a fee a SUPPLIER charges the agency — IATA/
 * BSP fees, rounding fees, airline service/booking fees, consolidator fees, hotel resort/booking
 * fees, card surcharges paid to a supplier — mirror of the client-side 4130 fee family, but
 * cost-side.
 *
 * Resolution order (App\Services\Accounting\SupplierChargeRuleResolver::resolveApplicable()),
 * same shape as W6.S's `supplier_status_maps`:
 *   1. company_id + supplier_id + service_type  (most specific)
 *   2. company_id + supplier_id, service_type NULL
 *   3. company_id + service_type, supplier_id NULL
 *   4. company_id, supplier_id NULL, service_type NULL  (company-wide default)
 * `channel` is an ADDITIONAL filter (must match exactly when set on the rule; NULL matches any
 * channel) — it does not participate in the four precedence tiers above, per w6-brief.md's own
 * "W6.C" section ("precedence supplier+service_type row > supplier row > service_type row >
 * company-wide row").
 *
 * `cost_account` carries an OPTIONAL explicit purpose-code override; when null, the line builder
 * defaults to `SERVICE_COST`/{type} on principal basis or `SUPPLIER_CHARGE_EXPENSE` (5128) on
 * agent basis — see App\Services\Accounting\SupplierChargeLineBuilder.
 *
 * `legacy_supplier_surcharge_id` + `label` are additive fields beyond w6-brief.md's own field list,
 * needed for the idempotent one-time backfill from `supplier_surcharges`
 * (App\Console\Commands\BackfillSupplierChargeRules) — traceability only, never read by the line
 * builder or the resolver's precedence logic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_charge_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->cascadeOnDelete();
            $table->string('service_type', 20)->nullable();
            $table->string('channel', 20)->nullable();
            $table->enum('charge_kind', [
                'iata_fee',
                'rounding',
                'service_fee',
                'booking_fee',
                'card_surcharge',
                'resort_fee',
                'other',
            ]);
            $table->enum('basis', [
                'fixed',
                'percent_of_fare',
                'percent_of_total',
                'per_passenger',
                'per_segment',
            ])->default('fixed');
            $table->decimal('amount', 10, 3)->default(0);
            $table->string('currency', 3)->nullable();
            // Explicit purpose-code override; NULL = default resolution by posting basis (see
            // class docblock). Never an account id -- purpose codes only, matching this whole
            // engine's own "feeders name accounts by purpose, never by string" rule.
            $table->string('cost_account', 64)->nullable();
            $table->enum('recharge_policy', ['absorb', 'recharge_client', 'recharge_agent'])->default('absorb');
            $table->boolean('commissionable')->default(false);
            $table->string('tax_code', 20)->nullable();
            $table->string('rounding_rule', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('once_per_reference')->default(false);
            // Backfill traceability only (see class docblock) -- not part of w6-brief.md's own
            // field list, additive.
            $table->string('label', 100)->nullable();
            $table->unsignedBigInteger('legacy_supplier_surcharge_id')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'supplier_id', 'service_type', 'channel', 'active'], 'supplier_charge_rules_lookup');
            $table->unique('legacy_supplier_surcharge_id', 'supplier_charge_rules_legacy_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_charge_rules');
    }
};
