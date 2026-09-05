<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * accounting-builds T3 (M3, Lane B / L8): one row per asset-month {@see \App\Services\Accounting\
 * FixedAssets\DepreciationRunService} posts (or would have posted, for a `--dry-run` preview row —
 * see that service's own docblock). This table is a POSTING LOG, not a balance cache: `amount` is
 * exactly what was written to `journal_entries` for that month (or previewed), never read back as
 * an authoritative NBV input — {@see \App\Services\Accounting\FixedAssets\FixedAssetService::nbv()}
 * derives strictly from `journal_entries` (L8), so a bug here can never silently corrupt the
 * reported NBV, only this table's own listing/idempotency-lookup convenience.
 *
 * `unique (fixed_asset_id, period_year, period_month)`: the DB-level backstop behind the
 * `fa-dep:{asset}:{yyyy}-{mm}` idempotency key `PostingSeam`/`PostingService` already enforce at
 * the `transactions.idempotency_key` layer — belt-and-braces, same reasoning as every other
 * uniqueness pair in this cutover that is ALSO covered by an idempotency key (e.g.
 * `gateway_settlements.payout_reference` per company+gateway, once T7 lands).
 *
 * `transaction_id`: soft cross-reference to `transactions.id` (no FK constraint, same convention
 * as `fixed_assets.acquisition_transaction_id`) — NULL for a `--dry-run` preview row that was never
 * actually posted (this service never persists preview rows at all; see its own docblock) and,
 * defensively, for any row this table's own future maintenance might need to represent without one.
 *
 * `status`: `posted` (the normal case) or `reversed` (a future reversal flow marks it so and posts
 * an offsetting document — see the class docblock's own "no explicit un-post button" scope note).
 * NBV derivation (L8) does not read this column at all — a reversal's own offsetting
 * `journal_entries` lines net to zero when summed, which is what actually keeps NBV correct; this
 * column exists purely so a listing screen can show "reversed" without re-deriving it from the
 * ledger every time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fixed_asset_id');
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->decimal('amount', 15, 3);
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->enum('status', ['posted', 'reversed'])->default('posted');
            $table->timestamps();

            $table->unique(['fixed_asset_id', 'period_year', 'period_month'], 'fixed_asset_deps_asset_period_unique');
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_depreciations');
    }
};
