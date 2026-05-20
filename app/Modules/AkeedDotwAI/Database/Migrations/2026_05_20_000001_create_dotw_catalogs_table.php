<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the dotw_catalogs snapshot table.
 *
 * This table stores every DOTW V4 reference list (classifications, chains,
 * locations, amenities, leisure, business, preferences, meal plans, room types,
 * specials, and salutations) in a single flat table keyed by (type, code).
 *
 * The UNIQUE constraint on (type, code) makes every sync run idempotent:
 * updateOrInsert only advances synced_at, never duplicates rows.
 *
 * Used by: CatalogSyncService (Phase 34), StarRatingResolver (Phase 35),
 * AgentFilterResolver (Phase 37).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dotw_catalogs', function (Blueprint $table) {
            $table->id();
            $table->enum('type', [
                'classification',
                'chain',
                'location',
                'amenity',
                'leisure',
                'business',
                'preference',
                'meal_plan',
                'room_type',
                'special',
                'salutation',
            ])->index();
            $table->string('code', 64);
            $table->string('name');
            $table->string('category', 32)->nullable();
            $table->json('payload')->nullable();
            $table->dateTime('synced_at');
            $table->timestamps();

            $table->unique(['type', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dotw_catalogs');
    }
};
