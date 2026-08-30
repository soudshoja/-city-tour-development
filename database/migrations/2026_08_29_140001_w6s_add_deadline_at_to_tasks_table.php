<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W6.S "Hold/confirmed follow-up lifecycle" item 1 (w6-brief.md, owner addition 2026-08-28).
 * `deadline_at` = ticketing time limit / cancellation deadline for an `on hold`/`confirmed` task --
 * parsed from supplier data where available (per-supplier via App\Services\TaskStatusService,
 * consuming `supplier_status_maps.deadline_source`), else falls back to the existing `expiry_date`
 * column. Additive, nullable -- no backfill (existing rows have no supplier deadline payload to
 * parse retroactively; TaskStatusService::expire()'s own grace-period math treats a null
 * deadline_at task as never-expiring, matching "nothing changes for existing rows" here).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('deadline_at')->nullable()->after('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('deadline_at');
        });
    }
};
