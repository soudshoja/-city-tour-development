<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CT-A4 — the report table `accounting:coa-linkage` writes its FLAG-ONLY findings to.
 *
 * The linkage command has two halves. One APPLIES structure repairs that provably change no
 * balance (mint a missing control leaf, map a purpose, backfill `account_type_id` /
 * `report_type` / `is_group`). The other FLAGS everything that would need an owner ruling or a
 * money-moving data migration — duplicate codes, unused leaves, accounts that carry both
 * children and journal activity, cross-company rows, rootless accounts. This table is that
 * second half's output: a durable, queryable record an operator (or the COA screen) can work
 * through, instead of a console dump that scrolls away.
 *
 * DELIBERATELY NOT a generic "accounting issues" table. It is keyed (company_id, code,
 * subject_type, subject_id) and rewritten wholesale per company on every run, so a finding that
 * has been remediated disappears on the next run rather than needing a resolution workflow — the
 * command is the source of truth, this table is its latest snapshot. Nothing in the posting
 * engine reads it; no feeder, resolver or report depends on it, so a stale or empty table can
 * never change what the ledger does.
 *
 * `details` is JSON rather than a column-per-fact because the findings are genuinely
 * heterogeneous: a duplicate-code row carries the sibling ids and the next free code, a
 * non-leaf-posting row carries a child count and a journal-row count, a cross-company row
 * carries the two company ids. Forcing them into shared columns would produce a table that is
 * mostly NULL and still needs a discriminator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coa_linkage_findings', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id')->index();

            // The finding class — 'DUPLICATE_CODE', 'UNUSED_LEAF', 'NON_LEAF_POSTING',
            // 'CROSS_COMPANY_LINE', 'ROOTLESS_ACCOUNT', 'UNRESOLVED_PURPOSE', ... Kept a plain
            // string, not an enum: the command owns the vocabulary and adding a finding class
            // must not need a migration.
            $table->string('code', 64)->index();

            // 'account' | 'journal_entry' | 'system_account' | 'purpose'. What `subject_id`
            // identifies. 'purpose' rows carry a null subject_id and name the purpose in
            // `details`.
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id')->nullable();

            // 'blocking' — the engine refuses a document because of this.
            // 'reporting' — the ledger is right, a report is not.
            // 'hygiene'   — neither, but it should not stay.
            // 'ruling'    — needs an owner decision before anything can be done.
            $table->string('severity', 16)->index();

            $table->string('summary', 255);

            $table->json('details')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'code'], 'coa_lf_company_code_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coa_linkage_findings');
    }
};
