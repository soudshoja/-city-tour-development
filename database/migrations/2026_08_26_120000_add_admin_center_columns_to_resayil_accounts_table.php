<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resayil Admin Center — data-model delta (plan .planning/specs/RESAYIL-ADMIN-CENTER.md §4.2).
 *
 * All seven columns live on the ADMIN row of resayil_accounts (role='admin'),
 * which is the single per-company record that owns the Resayil workspace
 * identity. Member rows keep them null.
 *
 * Slice 1 (Overview + billing read + operator pause/resume) uses three of
 * them — subscription_cache, device_health, health_checked_at — to render
 * the §8 D-1 degraded state: when the reseller API is unreachable we show
 * the last known good snapshot plus an amber "showing last known status
 * from {time}" line, instead of an error page. The other four are added in
 * the same migration so later slices need no second schema change:
 *   - resayil_webhook_id / webhook_nonce  -> slice 5 webhook registration
 *   - device_paired_at                    -> slice 3 pairing wizard state
 *   - key_source                          -> slice 2 ('auto' silent capture
 *                                            vs 'pasted' recovery card)
 *
 * NOTE: no secret is added here. subscription_cache holds ONLY the
 * price-free, token-free projection built by ResayilAdminService (see
 * §9.1, and owner decision D-1 — no Resayil price is ever stored in it or
 * rendered client-side).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resayil_accounts')) {
            return;
        }

        Schema::table('resayil_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('resayil_accounts', 'resayil_webhook_id')) {
                $table->string('resayil_webhook_id')->nullable()->after('resayil_user_id');
            }
            if (! Schema::hasColumn('resayil_accounts', 'webhook_nonce')) {
                $table->string('webhook_nonce')->nullable()->after('resayil_webhook_id');
            }
            if (! Schema::hasColumn('resayil_accounts', 'key_source')) {
                $table->string('key_source')->nullable()->after('webhook_nonce');
            }
            if (! Schema::hasColumn('resayil_accounts', 'device_paired_at')) {
                $table->timestamp('device_paired_at')->nullable()->after('key_source');
            }
            if (! Schema::hasColumn('resayil_accounts', 'device_health')) {
                $table->string('device_health')->nullable()->after('device_paired_at');
            }
            if (! Schema::hasColumn('resayil_accounts', 'health_checked_at')) {
                $table->timestamp('health_checked_at')->nullable()->after('device_health');
            }
            if (! Schema::hasColumn('resayil_accounts', 'subscription_cache')) {
                $table->json('subscription_cache')->nullable()->after('health_checked_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('resayil_accounts')) {
            return;
        }

        Schema::table('resayil_accounts', function (Blueprint $table) {
            foreach ([
                'resayil_webhook_id',
                'webhook_nonce',
                'key_source',
                'device_paired_at',
                'device_health',
                'health_checked_at',
                'subscription_cache',
            ] as $column) {
                if (Schema::hasColumn('resayil_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
