<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * accounting-builds T9 (Wave 2), migration M6 — PLAN.md §5's "Migration M6
 * create_bank_statement_imports_and_lines_tables (imports: ...; lines: ...)" naming. Sequenced
 * 2026_09_02_000007 (not _000006) per the coordinator's explicit T9 task briefing: T8's own
 * re-verify loop (commit 98918057) already claimed _000006 for
 * `add_matched_journal_entry_ids_to_supplier_statement_import_lines_table` on the shared phase
 * branch this lane forked from — see that migration's own docblock. §6's migration inventory ID
 * (M6) is therefore a plan-label, not a literal filename timestamp.
 *
 * Two tables, structurally mirroring T8's `supplier_statement_imports`/`_lines` pair (same
 * read/state-only discipline: L13 — reconciliation is read + state only this task; a matched
 * proposal is APPROVED through the existing
 * {@see \App\Services\Accounting\ReconciliationProposalService::approve()} pipeline, the only
 * place `journal_entries.reconciled` legitimately flips — ArchitectureTest::
 * test_no_post_hoc_reconciled_updates() polices this file's importer/matcher exactly like it
 * polices SupplierStatementMatcher.php).
 *
 * `bank_statement_imports`:
 *   - `bank_account_id`: the ONE bank leaf this statement belongs to (must pass
 *     {@see \App\Services\Accounting\AccountResolver::assertUnderBankGroup()} at import time) —
 *     this is what makes "a KWD bank statement never matches a USD bank leaf's lines" structural
 *     rather than a runtime filter: every candidate query below is scoped to THIS leaf's
 *     `account_id`, and the importer refuses up front when `statement_currency` does not match
 *     the leaf's own `accounts.currency` (falling back to `config('accounting.engine.
 *     base_currency')` for a leaf with no currency recorded, i.e. base-currency KWD leaves).
 *   - `content_hash` (spec: "idempotent re-import (bank leaf + statement period/reference
 *     hash)"): a DELIBERATE improvement over T8's shape (T8's own review packet §9/advisory:
 *     "idempotent re-import by explicit statement_reference silently keeps the first import when
 *     a same-reference file's content differs, with no warning surfaced" — flagged there as
 *     advisory, not fixed). Here `content_hash` is ALWAYS the row-content hash (never
 *     reference-derived) — see BankStatementImporter's own docblock for the two-step identity
 *     resolution this enables: same file re-imported (with or without an explicit reference) is
 *     idempotent by content; the SAME reference reused with DIFFERENT content is a raised
 *     conflict, not a silent keep — closing the exact gap T8 left open, per this task's own
 *     explicit test requirement ("changed re-import under same reference = conflict/warn").
 *   - `column_map` (L15): the column map actually used for this import.
 *   - `opening_balance`/`closing_balance`: operator-entered from the physical statement header/
 *     footer when the file itself does not carry them; `closing_balance` falls back to the last
 *     parsed line's `running_balance` when omitted. Compared against the ledger-DERIVED bank-leaf
 *     balance at the statement's end date (from `journal_entries`, never `accounts.
 *     actual_balance`) by the reconciliation report — see BankStatementMatcher::
 *     reconciliationReport().
 *   - `counts`: computed by the matcher after a match run — cached for the list screen, always
 *     re-derivable from the lines table + a live ledger read, never a source of truth on its own.
 *
 * `bank_statement_import_lines`:
 *   - one row per parsed statement row. `state` starts 'unmatched' and is set by
 *     BankStatementMatcher; the owner-approved four-state vocabulary this task reuses verbatim
 *     from T8 (matched / unmatched-statement / unmatched-ledger / amount-disputed) maps onto this
 *     column exactly as it does on `supplier_statement_import_lines` — 'unmatched-ledger' is
 *     never a statement line's own state (a live read over `journal_entries`, see
 *     BankStatementMatcher::unmatchedLedgerLines()); 'suggested' is reserved (migration-shape
 *     parity with T8, and with the plan's own literal column list for this table), unused by the
 *     matcher this task.
 *   - `auth_no`/`reference`/`cheque_no`: the three matching-tier keys (spec precedence: auth_no
 *     exact wins, then reference exact, then amount+date window) — `auth_no` is ALSO the column
 *     `journal_entries.auth_no` already carries on receipts (captured, previously unused as a
 *     match key — see JournalEntry's own `auth_no` column, migration
 *     2026_08_29_090000_add_cheque_clearance_date_to_journal_entries_table's docblock) and
 *     `cheque_no` mirrors `journal_entries.cheque_no` the same way.
 *   - `matched_journal_entry_id` (nullable, no FK — the same soft cross-reference convention
 *     every other new table this phase uses, see the T8 migration's own precedent): the ledger
 *     bank-leaf line this statement line matched (or, for a 'disputed' line, the ledger line
 *     whose amount it was compared against).
 *   - `difference`: signed (book amount − statement amount), 0 for a clean match.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('bank_account_id');
            $table->string('file_name');
            $table->string('statement_currency', 10);
            $table->date('statement_from')->nullable();
            $table->date('statement_to')->nullable();
            $table->decimal('opening_balance', 15, 3)->nullable();
            $table->decimal('closing_balance', 15, 3)->nullable();
            $table->string('statement_reference', 160)->nullable();
            $table->char('content_hash', 64);
            $table->json('column_map');
            $table->enum('status', ['staged', 'matched', 'closed'])->default('staged');
            $table->json('counts')->nullable();
            $table->unsignedBigInteger('imported_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'bank_account_id', 'content_hash'], 'bsi_company_bank_hash_unique');
            $table->index(['company_id', 'bank_account_id'], 'bsi_company_bank_idx');
            $table->index(['company_id', 'bank_account_id', 'statement_reference'], 'bsi_company_bank_ref_idx');
        });

        Schema::create('bank_statement_import_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_id');
            $table->unsignedInteger('row_no');
            $table->date('value_date');
            $table->date('posting_date')->nullable();
            $table->text('description')->nullable();
            $table->string('reference', 160)->nullable();
            $table->string('auth_no', 100)->nullable();
            $table->string('cheque_no', 100)->nullable();
            $table->decimal('debit', 15, 3)->default(0);
            $table->decimal('credit', 15, 3)->default(0);
            $table->decimal('running_balance', 15, 3)->nullable();
            $table->enum('state', ['unmatched', 'matched', 'disputed', 'suggested'])->default('unmatched');
            $table->unsignedBigInteger('matched_journal_entry_id')->nullable();
            $table->decimal('difference', 15, 3)->default(0);
            $table->text('note')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->index(['import_id', 'state'], 'bsil_import_state_idx');
            $table->index('reference', 'bsil_reference_idx');
            $table->index('auth_no', 'bsil_auth_no_idx');
            $table->index('matched_journal_entry_id', 'bsil_matched_je_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_import_lines');
        Schema::dropIfExists('bank_statement_imports');
    }
};
