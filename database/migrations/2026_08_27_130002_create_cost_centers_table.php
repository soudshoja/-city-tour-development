<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * W3.F (per Accounting Gap/11-technical-implementation-plan.md §2.1a) — the `cost_centers`
     * master table. Verified NOT present before this migration (no `create_cost_centers_table`
     * or similar in database/migrations/).
     *
     * Deliberately a lightweight master table only, in this lane: no FK from
     * journal_entries.cost_center_id back to this table (see the previous migration's own
     * docblock — matches this codebase's existing convention of soft, unenforced
     * cross-reference columns rather than a hard FK), and no data-entry UI/controller wiring —
     * "branch/cost-center is a DIMENSION column on every line, never a separate account" (LOCKED
     * decision) is what this table exists to serve, not a full cost-center management feature.
     * Company-scoped (`company_id`) to match the multi-tenant pattern every other master table in
     * this schema uses (accounts, charges, ...). `code` is unique PER COMPANY (composite unique
     * with company_id), not globally, for the same reason `accounts.code` is company-scoped
     * rather than globally unique.
     */
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('code', 32);
            $table->string('name');
            $table->boolean('disabled')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_centers');
    }
};
