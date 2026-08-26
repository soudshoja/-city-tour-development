<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Purpose-code registry that replaces ~237 name-string account lookups
     * scattered across the app (fix vehicle for R7.3 / BUG-H6 / T-Finding-1's
     * account half). `App\Services\Accounting\AccountResolver` reads this
     * table exclusively; nothing else creates or reads it in P1.
     *
     * See Accounting Gap/11-technical-implementation-plan.md §P1.0 (L88-99).
     */
    public function up(): void
    {
        Schema::create('system_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            // e.g. RECEIVABLE_CONTROL, PAYABLE_CONTROL, GATEWAY_CLEARING_*, RETAINED_EARNINGS,
            // FX_GAIN_LOSS, VAT_OUTPUT, SUSPENSE, SERVICE_REVENUE, SERVICE_PAYABLE, SERVICE_COST.
            $table->string('purpose_code', 64);

            // Task type for per-service revenue/payable/cost (flight, hotel, visa, insurance,
            // tour, cruise, car, rail, esim, event, lounge, ferry); NULL for global controls.
            $table->string('service_type', 32)->nullable();

            // Must be a leaf, same company as company_id above — enforced in code
            // (SystemAccountsSeeder / AccountResolver), not by a DB constraint.
            $table->foreignId('account_id')->constrained('accounts');

            $table->timestamps();

            $table->unique(['company_id', 'purpose_code', 'service_type']);
            $table->index(['company_id', 'purpose_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_accounts');
    }
};
