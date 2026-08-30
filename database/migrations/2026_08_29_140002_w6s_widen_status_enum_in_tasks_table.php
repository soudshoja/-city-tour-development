<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W6.S "Hold/confirmed follow-up lifecycle" item 2 + "Per-supplier status map" item 4
 * (w6-brief.md, owner additions 2026-08-28). Additive-only widen of `tasks.status`:
 *   - 'expired': a genuinely NEW canonical state for a pre-issue lapse (on-hold/confirmed task
 *     whose deadline passed before it was ever issued/invoiced) -- distinct from 'void' (a real
 *     cancellation of an issued ticket), per TaskStatusService::expire()'s own contract. 'void'
 *     stays untouched/reserved for real voids.
 *   - 'needs_review': TaskStatusService::mapStatus()'s output when no supplier_status_maps row
 *     matches at any resolution level (company+supplier / company+channel-default /
 *     global-default) -- the task is still saved, but financial dispatch never runs for it.
 *   - 'cancelled': the hold/confirmed lifecycle's third terminal branch (on_hold/confirmed ->
 *     issued | expired | cancelled) alongside the already-existing 'void'/'refund'/etc values.
 * Every existing enum value is kept verbatim -- see 2025_08_11_160058_update_status_enum_in_tasks_table.php
 * for the baseline this widens. down() reverts to that exact baseline list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('status', [
                'refund',
                'issued',
                'reissued',
                'void',
                'ticketed',
                'confirmed',
                'emd',
                'refunded',
                'on hold',
                'expired',
                'needs_review',
                'cancelled',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('status', [
                'refund',
                'issued',
                'reissued',
                'void',
                'ticketed',
                'confirmed',
                'emd',
                'refunded',
                'on hold',
            ])->change();
        });
    }
};
