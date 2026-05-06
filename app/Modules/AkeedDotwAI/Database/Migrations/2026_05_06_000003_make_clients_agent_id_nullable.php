<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes clients.agent_id nullable to support B2C anonymous bookings.
 *
 * B2C hotel bookings created through the Akeed/DOTW agent flow are not
 * associated with a human agent — agent_id must be nullable so these rows
 * can be persisted without a foreign-key violation.
 *
 * The original column was created nullable (2024_08_23_021722) and a
 * separate migration (2025_04_11_163820) already applied the same change
 * on the shared database.  This migration is included in the module's own
 * migration sequence to make the AkeedDotwAI module self-documenting and
 * safe to run on a fresh schema that has not yet had the April 2025
 * migration applied.
 *
 * NOTE: No down() implementation is provided.  Reverting to NOT NULL would
 * fail immediately if any rows have agent_id IS NULL after rollback.
 * Manual recovery is required: backfill nulls, then create a dedicated
 * make-non-nullable migration.
 *
 * IMPORTANT: Do NOT run this migration while existing client rows with
 * agent_id values are present — it is a metadata-only change (nullable flag)
 * and will not touch any data.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('agent_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Not implemented — see class docblock for recovery instructions.
     */
    public function down(): void
    {
        // Cannot safely revert: making the column NOT NULL will fail if any
        // rows have agent_id IS NULL at rollback time.
        // Manual recovery required: backfill nulls, then run a dedicated
        // make-non-nullable migration.
    }
};
