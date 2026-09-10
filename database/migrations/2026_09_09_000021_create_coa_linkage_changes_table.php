<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CT-A3 R2-5 — VERIFY-CT-A3-STACK-R1 §3.2 **D14**: *"`accounting:coa-linkage --apply` rewrites
 * `report_type` on accounts that already have one, changing historical reported profit, with no way
 * back."*
 *
 * `CoaLinkage::backfillReportType()` skips a row only when its `report_type` already EQUALS what
 * the root implies; otherwise it overwrites, in both directions. `ReportController` selects the
 * P&L by that column, so the 87 accounts CT-A4 measured change reported profit for every historical
 * period on the next render. The change was dry-runnable — and NOT REVERSIBLE FROM THE COMMAND'S
 * OWN OUTPUT: it was summarised as a count, with no ids and no before-values, and nothing was
 * written to `coa_linkage_findings` for `SET_REPORT_TYPE`.
 *
 * This table is the missing before-image. One row per (run, account, column) the command actually
 * changed, carrying the value that was there BEFORE and the value written AFTER, so
 * `accounting:coa-linkage --rollback=<run_id>` can put every one of them back exactly.
 *
 * ── Why a separate table and not more `coa_linkage_findings` rows ───────────────────────────────
 * `coa_linkage_findings` is documented as "the latest snapshot, rewritten wholesale per company on
 * every run" — a finding that has been remediated must VANISH on the next run. A before-image has
 * the opposite lifetime: it must survive every later run, or the run that made a change can never
 * be undone once another has been executed. Two lifetimes, two tables.
 *
 * ── What it is deliberately NOT ─────────────────────────────────────────────────────────────────
 * Not an audit log of the ledger. Nothing here touches `journal_entries` or `transactions`; the
 * three columns recorded (`report_type`, `is_group`, `account_type_id`) are CLASSIFICATION columns
 * on `accounts`, and the linkage command is forbidden from moving money. Nothing in the posting
 * engine reads this table, so a stale or empty one can never change what the ledger does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coa_linkage_changes', function (Blueprint $table) {
            $table->id();

            // One id per `accounting:coa-linkage --apply` INVOCATION (not per company): an
            // operator who ran one command over three companies undoes one command, not three.
            // A ULID rather than an auto-increment so the value an operator copies out of the
            // console cannot be confused with an account id or a company id.
            $table->string('run_id', 40)->index();

            $table->unsignedBigInteger('company_id')->index();

            // Always 'accounts' today. Present because the alternative — encoding the table in the
            // column name — is what makes a second subject type need a migration later.
            $table->string('subject_table', 32)->default('accounts');
            $table->unsignedBigInteger('subject_id');

            // 'report_type' | 'is_group' | 'account_type_id'.
            $table->string('column_name', 32);

            // Stringified on purpose: the three columns are a varchar, a tinyint and a bigint, and
            // a shared value column that is honest about NULL beats three mostly-NULL typed
            // columns plus a discriminator. The rollback casts back per column_name.
            $table->string('before_value', 64)->nullable();
            $table->string('after_value', 64)->nullable();

            // Set when a --rollback run has put this row's before_value back, so the same run
            // cannot be rolled back twice and a partially-rolled-back run is visible as such.
            $table->timestamp('rolled_back_at')->nullable();

            $table->timestamps();

            $table->index(['run_id', 'company_id'], 'coa_lc_run_company_idx');
            $table->index(['subject_table', 'subject_id'], 'coa_lc_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coa_linkage_changes');
    }
};
