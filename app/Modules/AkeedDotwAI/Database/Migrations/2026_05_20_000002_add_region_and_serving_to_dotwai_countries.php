<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add region columns and is_serving flag to dotwai_countries.
 *
 * NOTE: getallcountries response (verified 2026-05-20) returns only <name> + <code>.
 * region_name / region_code columns are nullable + populated by a future manual
 * seed; CatalogSyncService writes them ONLY if a future DOTW response carries them.
 *
 * is_serving is populated by Phase 34's CatalogSyncService by cross-referencing
 * getservingcountries against the existing dotwai_countries rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dotwai_countries', function (Blueprint $table) {
            // NOTE: getallcountries response (verified 2026-05-20) returns only <name> + <code>.
            // region_name / region_code columns are nullable + populated by a future manual
            // seed; CatalogSyncService writes them ONLY if a future DOTW response carries them.
            $table->string('region_name', 128)->nullable()->after('nationality_name');
            $table->string('region_code', 32)->nullable()->after('region_name');
            $table->boolean('is_serving')->default(false)->index()->after('region_code');
        });
    }

    public function down(): void
    {
        Schema::table('dotwai_countries', function (Blueprint $table) {
            $table->dropIndex(['is_serving']);
            $table->dropColumn(['region_name', 'region_code', 'is_serving']);
        });
    }
};
