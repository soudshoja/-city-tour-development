<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds company_id and resayil_message_id columns to dotw_prebooks table.
     *
     * These columns support BLOCK-08: single-active-prebook-per-user constraint —
     * only one active (non-expired) prebook is allowed per (company_id, resayil_message_id) pair.
     *
     * No FK on company_id — DOTW module is standalone per MOD-06, consistent with
     * dotw_audit_logs migration approach.
     */
    public function up(): void
    {
        Schema::table('dotw_prebooks', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')
                ->nullable()
                ->after('id')
                ->comment('Company that created this prebook (for BLOCK-08 single-active constraint)');

            $table->string('resayil_message_id')
                ->nullable()
                ->after('company_id')
                ->comment('WhatsApp user proxy — one active prebook per (company, resayil_message_id)');

            $table->index(
                ['company_id', 'resayil_message_id', 'expired_at'],
                'dotw_prebooks_company_user_expiry_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dotw_prebooks', function (Blueprint $table) {
            $table->dropIndex('dotw_prebooks_company_user_expiry_idx');
            $table->dropColumn(['company_id', 'resayil_message_id']);
        });
    }
};
