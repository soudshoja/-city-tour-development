<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 5 — Resayil WhatsApp CRM: links a TravelERP company/user to its
 * Resayil account identity so the drawer/full-page embed knows who is
 * provisioned, and so App\Services\Resayil\ResayilProvisioningService can
 * enforce the auto-create cap (config('resayil.max_auto_users'), default 9)
 * idempotently.
 *
 * One row per (company, user):
 *  - The FIRST row for a company is the "admin" row (role=admin): its
 *    resayil_customer_id is the reseller-created Resayil customer for the
 *    whole company (POST /v1/resellers/customers). resayil_device_id and
 *    resayil_account_token, when populated, are the company-level WhatsApp
 *    number and account-scoped API token needed to auto-create subsequent
 *    users as Resayil "team members" (POST /v1/devices/{id}/team) — see the
 *    Module 5 report for why these two are NOT auto-obtainable today and
 *    must be set manually once available.
 *  - Every other row for the same company shares resayil_customer_id and
 *    represents one auto-provisioned (or cap-blocked) team member.
 *
 * NOT RUN on the dev server per instructions — file only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resayil_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // admin | supervisor | agent — mirrors Resayil's team-member roles.
            // The first provisioned row for a company is always 'admin'.
            $table->string('role')->default('agent');

            // Reseller-level Resayil customer id (POST /v1/resellers/customers
            // response `id`). Shared by every row for the same company.
            $table->string('resayil_customer_id')->nullable()->index();

            // Company-level WhatsApp number device id, once connected
            // (requires a human QR-scan pairing step — cannot be automated
            // purely via API). Only meaningful on the admin row; copied here
            // for query convenience on later rows once known.
            $table->string('resayil_device_id')->nullable();

            // Account-scoped Resayil API token (NOT the reseller token) that
            // POST /v1/devices/{id}/team requires. No documented reseller
            // endpoint returns this automatically as of 2026-08-24 — see the
            // Module 5 report. Encrypted at rest; populated manually per
            // company once/if obtained.
            $table->text('resayil_account_token')->nullable();

            // Per-user team-member id (POST /v1/devices/{id}/team response
            // `id`), once actually created for this user.
            $table->string('resayil_user_id')->nullable();

            // pending | provisioned | limit_reached | awaiting_admin |
            // pending_device | not_configured | error
            $table->string('status')->default('pending');

            $table->string('resayil_email')->nullable();
            $table->timestamp('provisioned_at')->nullable();

            // Raw API error bodies / diagnostic context, kept small and local
            // rather than standing up a separate log table for this.
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resayil_accounts');
    }
};
