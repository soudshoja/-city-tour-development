<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security fix (sec/resayil-webhook): incoming_media rows were written with
 * no company attribution at all — the sender was resolved from the webhook
 * BODY (Agent::where('phone_number', $phone)), a cross-tenant identifier.
 * Company now comes from which Resayil account/secret delivered the
 * webhook (see VerifyResayilWebhookSecret), so every row can and must
 * carry that company_id going forward.
 *
 * Nullable: pre-existing rows (and any row written before an account has a
 * webhook_secret provisioned) have no reliable company and are left null
 * rather than guessed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('incoming_media')) {
            return;
        }

        Schema::table('incoming_media', function (Blueprint $table) {
            if (! Schema::hasColumn('incoming_media', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('phone')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('incoming_media')) {
            return;
        }

        Schema::table('incoming_media', function (Blueprint $table) {
            if (Schema::hasColumn('incoming_media', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });
    }
};
