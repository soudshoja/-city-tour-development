<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes dotwai_cities.country_code nullable to support the single-call city sync.
 *
 * DOTW's getservingcities endpoint returns all cities globally when called with
 * an empty body — the response does not include a country association per city.
 * SyncStaticDataCommand::syncCities() therefore stores country_code = NULL for
 * every row upserted via the new single-call path.
 *
 * This migration must be run before dotwai:sync-static (or --cities-only) is
 * executed against a schema that has country_code as NOT NULL, otherwise the
 * upsert will fail with a constraint violation on every city row.
 *
 * NOTE: No down() implementation is provided.  Making the column NOT NULL again
 * after sync would fail immediately if any rows have country_code IS NULL.
 * Manual recovery only: backfill nulls, then create a dedicated
 * make-non-nullable migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dotwai_cities', function (Blueprint $table) {
            $table->string('country_code')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Cannot safely revert: making the column NOT NULL will fail if any
        // rows have country_code IS NULL at rollback time.
        // Manual recovery required: backfill nulls, then run a dedicated
        // make-non-nullable migration.
    }
};
