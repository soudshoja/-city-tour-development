<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * accounting-builds T2 (M2, Lane B / L7-L8): the fixed-asset register. One row per capitalised
 * asset — `asset_class` is the key into `config('accounting.purpose_codes.fixed_asset_classes')`
 * (e.g. `CAPITAL_EQUIPMENT`), NOT a free-text field, so {@see \App\Services\Accounting\FixedAssets\
 * FixedAssetService} can resolve `FA_COST_{class}` / `FA_ACCUM_DEP_{class}` purposes without a
 * second lookup table.
 *
 * No `nbv`/`current_value`/`accumulated_depreciation` column anywhere on this table (L8: NBV is
 * ALWAYS derived — cost minus the sum of posted depreciation lines read back from
 * `journal_entries`, never cached). `cost`/`salvage`/`disposal_proceeds` are the only money
 * columns this table itself owns; everything else money-shaped lives in `journal_entries` via the
 * documents this asset's lifecycle posts (`fa-acq:{id}`, `fa-dep:{id}:{yyyy}-{mm}`,
 * `fa-dsp:{id}`).
 *
 * `acquisition_transaction_id` / `disposal_transaction_id`: soft cross-references to
 * `transactions.id` (no FK constraint), same convention every other accounting table in this
 * cutover uses for a same-schema reference outside `company_id` itself (see e.g.
 * `reconciliation_proposals.book_journal_entry_id`) — a capitalisation/disposal document is
 * OPTIONAL (an asset can be registered against a cost already posted through an existing PV/JV;
 * see FixedAssetService::capitalise()'s own docblock), so these must tolerate NULL.
 *
 * `supplier_id`: no FK constraint either, same soft-reference convention — an asset's supplier is
 * informational (who it was bought from), never itself a posting target.
 *
 * decimal(15,3): matches this whole cutover's KWD 3-decimal-place convention (`journal_entries.
 * debit`/`.credit`, `reconciliation_proposals.amount`, …) — see DepreciationRunService's own
 * docblock for the rounding rule this feeds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('asset_class', 40); // key into config('accounting.purpose_codes.fixed_asset_classes')
            $table->string('name', 160);
            $table->string('code', 60)->nullable(); // caller's own asset tag/barcode; informational only
            $table->decimal('cost', 15, 3);
            $table->decimal('salvage', 15, 3)->default(0);
            $table->date('acquisition_date');
            $table->date('in_service_date');
            $table->unsignedSmallInteger('useful_life_months');
            $table->enum('method', ['straight_line'])->default('straight_line');
            $table->enum('status', ['draft', 'active', 'fully_depreciated', 'disposed'])->default('draft');
            $table->unsignedBigInteger('acquisition_transaction_id')->nullable();
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_proceeds', 15, 3)->nullable();
            $table->unsignedBigInteger('disposal_transaction_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'asset_class']);
            $table->index('acquisition_transaction_id');
            $table->index('disposal_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
