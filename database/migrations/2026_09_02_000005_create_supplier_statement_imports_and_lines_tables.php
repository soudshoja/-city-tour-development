<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * accounting-builds T8 (Lane E), migration M5 — the plan's PLAN.md §5 "Migration M5
 * create_supplier_statement_imports_and_lines_tables (imports: ...; lines: ...)" naming, exactly.
 *
 * Two tables. Both are read/state-only — nothing here is ever written by the posting engine and
 * nothing here ever writes journal_entries/transactions (L13: reconciliation is read + state only
 * this task; a matched proposal is APPROVED through the existing
 * {@see \App\Services\Accounting\ReconciliationProposalService::approve()} pipeline, which is the
 * only place `journal_entries.reconciled` legitimately flips — see ArchitectureTest's
 * test_no_post_hoc_reconciled_updates(), generalised in this same commit from T0b's
 * settlement_channel rule per MP-8-3).
 *
 * `supplier_statement_imports`:
 *   - one row per uploaded statement file (or per explicit statement_reference/period the caller
 *     supplies instead of relying on file-content hashing).
 *   - `content_hash` (L13/spec "statement identity = supplier + statement reference/period hash"):
 *     sha256 of either (a) `supplier_id|statement_reference` when the caller supplies an explicit
 *     reference/period label, or (b) `supplier_id|<ordered row projection>` derived from the parsed
 *     file content when it does not — see SupplierStatementImporter's own docblock for the exact
 *     projection. A UNIQUE (company_id, supplier_id, content_hash) index is what makes "re-importing
 *     the same statement is idempotent" (spec) a DB-enforced guarantee, not just an application
 *     convention: a second import call with the same identity finds the existing row and returns it
 *     rather than inserting a duplicate.
 *   - `column_map` (L15): the column map actually used for this import (config default merged with
 *     any per-import override), stored so a re-read of an old import shows what was matched against,
 *     not today's config.
 *   - `counts`: computed by the matcher after a match run (matched/disputed/unmatched_statement/
 *     unmatched_ledger) — cached for the list screen; always re-derivable from the lines table + a
 *     live ledger read, never a source of truth on its own.
 *
 * `supplier_statement_import_lines`:
 *   - one row per parsed statement row. `state` starts 'unmatched' and is set by
 *     SupplierStatementMatcher; 'suggested' is reserved (migration-shape parity with the plan's
 *     literal column list) but UNUSED this task — the owner-approved SPEC names exactly four
 *     outcomes (matched / unmatched-statement / unmatched-ledger / amount-disputed), and
 *     "unmatched-ledger" is a book-side gap that is never a statement line's own state (see
 *     SupplierStatementMatcher::unmatchedLedgerLines(), a live read, not a stored row) —
 *     'suggested' (a lower-confidence amount-only secondary probe) was out of the owner-approved
 *     four-state spec and is deferred, documented in the T8 review packet.
 *   - `matched_journal_entry_id` (nullable, no FK per this codebase's "soft cross-reference"
 *     convention already used by journal_entries.reconciled_ref_id/.task_id and every other new
 *     table this phase — see the reconciliation_proposals migration's own docblock precedent):
 *     the ledger payable line this statement line matched (or, for a 'disputed' line, the ledger
 *     line whose amount it was compared against).
 *   - `difference`: signed (book amount − statement amount), 0 for a clean match.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('supplier_id');
            $table->string('file_name');
            $table->string('statement_currency', 10);
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('statement_reference', 160)->nullable();
            $table->char('content_hash', 64);
            $table->json('column_map');
            $table->enum('status', ['staged', 'matched', 'closed'])->default('staged');
            $table->json('counts')->nullable();
            $table->unsignedBigInteger('imported_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'supplier_id', 'content_hash'], 'ssi_company_supplier_hash_unique');
            $table->index(['company_id', 'supplier_id'], 'ssi_company_supplier_idx');
        });

        Schema::create('supplier_statement_import_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_id');
            $table->unsignedInteger('row_no');
            $table->string('booking_ref', 160)->nullable();
            $table->string('confirmation_code', 160)->nullable();
            $table->string('guest', 191)->nullable();
            $table->date('checkin')->nullable();
            $table->date('checkout')->nullable();
            $table->decimal('amount', 15, 3);
            $table->string('currency', 10);
            $table->date('statement_date')->nullable();
            $table->string('statement_line_reference', 160)->nullable();
            $table->text('description')->nullable();
            $table->enum('state', ['unmatched', 'matched', 'disputed', 'suggested'])->default('unmatched');
            $table->unsignedBigInteger('matched_journal_entry_id')->nullable();
            $table->unsignedBigInteger('matched_task_id')->nullable();
            $table->decimal('difference', 15, 3)->default(0);
            $table->text('note')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->index(['import_id', 'state'], 'ssil_import_state_idx');
            $table->index('booking_ref', 'ssil_booking_ref_idx');
            $table->index('matched_journal_entry_id', 'ssil_matched_je_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_statement_import_lines');
        Schema::dropIfExists('supplier_statement_imports');
    }
};
