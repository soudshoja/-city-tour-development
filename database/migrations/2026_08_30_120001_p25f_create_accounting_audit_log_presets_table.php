<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2.5.F owner refinement (2026-08-30): "saved filter presets per user (table
 * accounting_audit_log_presets)". One row per saved filter set; `filters` mirrors the exact query
 * string the Log Center screen's `queryString` produces, so restoring a preset is a pure
 * "replace-my-filter-state-with-this-JSON" operation with no separate parsing rules to keep in sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_audit_log_presets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name', 120);
            $table->json('filters');
            $table->timestamps();

            $table->index(['user_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_audit_log_presets');
    }
};
