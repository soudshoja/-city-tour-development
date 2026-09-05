<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * accounting-builds T0b (M1, L12): reconciliation needs to match a bank/gateway statement line
 * against the RAIL a receipt/payment actually moved through (`tap:knet`, `hesabe:card`,
 * `myfatoorah:knet`, `bank:transfer`, `cash`, …) — the clearing account (GATEWAY_CLEARING_{gw})
 * already encodes WHICH GATEWAY but not the rail within it. Additive only: nullable, no default,
 * no backfill — every existing row simply has no settlement_channel recorded yet, which is the
 * truth (this column did not exist when they were posted).
 *
 * Written ONLY by App\Services\Accounting\PostingService (the sole engine writer — see
 * ArchitectureTest's new `test_no_post_hoc_settlement_channel_updates` rule, added in this same
 * task), NEVER a post-hoc `->update(['settlement_channel' => ...])` anywhere else — the
 * `TaskStatusService` `reason_tag` post-hoc pattern is explicitly NOT to be copied (L12).
 *
 * varchar(24): comfortably covers every planned value (`myfatoorah:knet` is the longest at 15
 * chars) with headroom, same sizing discipline as the pre-existing `reason_tag varchar(16)` /
 * `cheque_no varchar(100)` columns on this same table. Composite index (company_id,
 * settlement_channel): the reconciliation matcher's own primary lookup shape — "this company's
 * unreconciled lines on this channel" — same convention as this table's existing
 * (company_id, account_id) usage pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('settlement_channel', 24)->nullable()->after('reconciled_amount');
            $table->index(['company_id', 'settlement_channel'], 'journal_entries_company_settlement_channel_index');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('journal_entries_company_settlement_channel_index');
            $table->dropColumn('settlement_channel');
        });
    }
};
