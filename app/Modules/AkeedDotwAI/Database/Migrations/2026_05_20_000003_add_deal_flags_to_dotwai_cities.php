<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add luxury, top-deal, and special-deal flag columns to dotwai_cities.
 *
 * NOTE: getservingcities sample responses not present in repo. Flags default false
 * and are populated by CatalogSyncService only if the live response carries them.
 * Verifier confirms exact XML attribute names on first sync run (Phase 34 acceptance
 * gate — F4 write-through per CONTEXT decision 6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dotwai_cities', function (Blueprint $table) {
            // NOTE: getservingcities sample responses not present in repo. Flags default false
            // and are populated by CatalogSyncService only if the live response carries them.
            $table->boolean('is_luxury')->default(false)->index();
            $table->boolean('is_top_deal')->default(false);
            $table->boolean('is_special_deal')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('dotwai_cities', function (Blueprint $table) {
            $table->dropIndex(['is_luxury']);
            $table->dropColumn(['is_luxury', 'is_top_deal', 'is_special_deal']);
        });
    }
};
