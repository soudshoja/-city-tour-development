<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resayil Admin Center — Team panel email alias (plan: the redesign that
 * folds the WhatsApp inbox into Settings, §4/§5).
 *
 * WHY THIS EXISTS. The Team panel matches a Resayil WhatsApp agent to a
 * TravelERP portal user by email, case-insensitively — but the two systems
 * do not always agree on WHICH email belongs to the same human. City
 * Travelers' admin is a proven example: they sign into TravelERP as
 * saeid@citytravelers.co, but their Resayil WhatsApp seat is registered
 * under shoja@citytravelers.co. Plain email matching (even case-insensitive)
 * cannot reconcile that — it is two different addresses, not a casing
 * difference.
 *
 * WHY A COLUMN ON `users`, NOT `resayil_accounts`. `resayil_accounts`
 * already has a `resayil_email` column, but that table exists to track
 * accounts THIS APP auto-provisioned (one admin row per company, member
 * rows only for users we created a Resayil login for) — it is not
 * guaranteed to have a row for every TravelERP user, and automatic team
 * provisioning was removed entirely (see ResayilProvisioningService
 * class docblock). A plain nullable column on `users` is the general
 * mechanism: any user can be given a WhatsApp-side alias regardless of
 * whether they were ever auto-provisioned, and the matching query is a
 * simple COALESCE(users.resayil_email, users.email) — no join, no
 * dependency on provisioning history.
 *
 * The seed below sets the one alias known today. It is keyed by the
 * TravelERP email (not a hardcoded user id, which is not guaranteed to be
 * the same number on every environment) so this migration is a safe no-op
 * anywhere that user doesn't exist, and idempotent everywhere they do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'resayil_email')) {
                $table->string('resayil_email')->nullable()->after('email');
            }
        });

        // Owner-confirmed alias, 2026-08-26: "admin email is different in
        // portal, it's saeid@citytravelers.co, so link to that." Same
        // person is shoja@citytravelers.co in Resayil.
        DB::table('users')
            ->whereRaw('LOWER(email) = ?', ['saeid@citytravelers.co'])
            ->whereNull('resayil_email')
            ->update(['resayil_email' => 'shoja@citytravelers.co']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'resayil_email')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('resayil_email');
            });
        }
    }
};
