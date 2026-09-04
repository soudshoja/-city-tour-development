<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security fix (sec/resayil-webhook): the Resayil webhook register-webhook
 * body is `{name, device, url, events}` — there is no signature, no secret,
 * no HMAC (see the resayil-whatsapp-api skill, references/webhooks.md).
 * Company identity for an inbound webhook must therefore come from WHICH
 * URL delivered it, not from any request field.
 *
 * This adds a per-company webhook secret to the admin row of
 * resayil_accounts (the single row per company that owns the Resayil
 * workspace identity — see ResayilAccount::adminFor()). Only the SHA-256
 * digest of the secret is stored (see ResayilAccount::ensureWebhookSecret());
 * the plaintext is only ever available once, at generation time, for
 * building the webhook URL to register with Resayil.
 *
 * No backfill: existing rows get a secret lazily via ensureWebhookSecret()
 * on next provisioning/save. Until then their webhook URL has no valid
 * secret and VerifyResayilWebhookSecret 404s any request to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resayil_accounts')) {
            return;
        }

        Schema::table('resayil_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('resayil_accounts', 'webhook_secret')) {
                $table->string('webhook_secret', 64)->nullable()->unique()->after('webhook_nonce');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('resayil_accounts')) {
            return;
        }

        Schema::table('resayil_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('resayil_accounts', 'webhook_secret')) {
                $table->dropColumn('webhook_secret');
            }
        });
    }
};
