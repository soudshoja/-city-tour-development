<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W5.P (w5-brief.md §W5.P) — the durable Payment Voucher document row `BankPaymentController` needs
 * to drive itself through {@see \App\Services\Accounting\PostingSeam}, mirroring the pending ->
 * approved -> posted lifecycle `invoice_receipts` already carries for RV (migration
 * 2026_08_29_100000_w5r_receipt_voucher_document_columns.php) — but as a BRAND NEW table, not an
 * add-column migration, because (w5-state.md §1 "Dedicated tables") PV has NEVER had a row of its
 * own: HEAD's `BankPaymentController::store()` writes a bare `Transaction` + `JournalEntry` pair per
 * submitted item, with no durable "this is one payment voucher" row at all. One `bank_payments` row
 * = one PV document = (once approved) one `transactions` row, exactly the same 1:1 shape HEAD's own
 * "one item -> one Transaction" loop already produces — this table just gives that unit of work a
 * name and a pending/approved/reversed lifecycle before it is posted.
 *
 * Column rationale (mirrors invoice_receipts' own W5.R column list one wave over):
 *   - `company_id` / `branch_id` / `doc_date`: resolvable BEFORE a transaction exists, for policy/
 *     module checks and to build the eventual DocumentDraft — same reason RV needs them pre-post.
 *   - `sub_type`: SUPPLIER|ACCOUNT|BONUS|REFUND_OUT|BY_DATE (config('accounting.sub_types')['PV'],
 *     w5-brief.md §W5.L item 3) — the actual PV kind this row posts under.
 *   - `pay_from_account_id`: the bank/cash leaf paid FROM — HEAD's existing `pay_from_account`
 *     request field (`accounts.id`, validated `required|exists:accounts,id`), now persisted on the
 *     document row itself. No DB-level FK, same convention `system_accounts.account_id` and RV's own
 *     `bank_account_id` already use ("enforced in code") — this migration must not fail against a
 *     differently-shaped `accounts` table in an older environment.
 *   - `target_account_id`: the payee leg (HEAD's `items[].account_id`) — the account this voucher
 *     debits (reduces a payable/expense/asset). Required by controller-level validation for every
 *     sub_type (see BankPaymentController's own docblock for why the pre-existing "refund with no
 *     account_id posts only one leg" gap is closed here rather than reproduced).
 *   - `agent_id`: BONUS sub_type only — the agent `App\Models\BonusAgent` side-record still needs,
 *     kept on the document row so update()/repost can rebuild it without re-deriving from a posted
 *     line.
 *   - `bank_charge_amount`: NEW, additive (w5-brief.md §W5.P "optional manual bank-charge line Dr
 *     BANK_CHARGES_EXPENSE / Cr bank") — HEAD has no equivalent field; nullable, adds a third dr/cr
 *     pair onto the SAME balanced document when set, never its own document.
 *   - `cheque_no` / `cheque_date` / `cheque_clearance_date` / `bank_info` / `auth_no`: the instrument
 *     identity fields, persisted on the DOCUMENT row (not just the posted `journal_entries` legs
 *     LineDraft already carries them to — see that class's own W5.L docblock) so update()/clear() can
 *     rebuild an accurate DocumentDraft without re-parsing posted lines. Same five columns RV's own
 *     migration already added to `invoice_receipts`.
 *   - `reconcile_journal_entry_ids`: JSON array of PRE-EXISTING `journal_entries.id` values this
 *     voucher's own BY_DATE fast path marks `reconciled = 1` / `reconciled_ref_id = <this voucher's
 *     new line>` against (HEAD's `items[].transaction_id` field, a misleadingly-named CSV/array of
 *     journal_entries ids despite the name) — kept as a raw column write against those OTHER,
 *     already-posted documents (w5-brief.md's own Traps section: "reconciled/reconciled_ref_id...
 *     move behind a service method; P5.10 replaces" is noted as a future concern, not this wave's
 *     to fix); this voucher's OWN new line's `reconciled = 2` flag, by contrast, is set via
 *     {@see \App\Services\Accounting\LineDraft::$reconciled} (an engine line flag), never a raw
 *     post-insert `->update()` — see BankPaymentController::buildVoucherDraft() and PostingService's
 *     own W5.P docblock note.
 *   - `voucher_number`: a DRAFT-only display label ("PV-DRAFT-{id}") for a still-pending row with no
 *     real accounting document number yet — {@see \App\Services\Accounting\SequenceService} mints
 *     the real number only inside `PostingService::post()`, never here.
 *   - `reference_ref` / `remarks` / `remarks_internal` / `remarks_fl`: additive carry-forward of the
 *     free-text fields `BankPaymentController::store()` already collects
 *     (`bankpaymentref`/`remarks_create`/`internal_remarks`/`remarks_fl`) but HEAD had nowhere
 *     durable to put before a `transactions` row existed. `reference_ref` is display/audit ONLY,
 *     never used for the real document number (SequenceService owns that, per w5-brief.md §W5.L).
 *   - `status`: {pending, approved, reversed} — no `rejected`/`bounced` states (unlike RV): PV has no
 *     approval-rejection UX today and no client-facing cheque-bounce flow (bounce is RV-only — a
 *     bank bounces money coming IN; a PV's own cheque simply goes uncleared until manually cleared,
 *     w5-brief.md §W5.P "Cheque issued not cleared: Cr 2215 until manual clear").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->date('doc_date')->nullable();
            $table->string('sub_type', 20)->nullable();

            $table->unsignedBigInteger('pay_from_account_id')->nullable();
            $table->unsignedBigInteger('target_account_id')->nullable();
            $table->unsignedBigInteger('agent_id')->nullable();

            $table->decimal('amount', 15, 3)->default(0);
            $table->decimal('bank_charge_amount', 15, 3)->nullable();

            $table->string('cheque_no', 100)->nullable();
            $table->date('cheque_date')->nullable();
            $table->date('cheque_clearance_date')->nullable();
            $table->string('bank_info', 200)->nullable();
            $table->string('auth_no', 100)->nullable();

            $table->json('reconcile_journal_entry_ids')->nullable();

            $table->string('voucher_number', 40)->nullable();
            $table->string('reference_ref', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->text('remarks_internal')->nullable();
            $table->text('remarks_fl')->nullable();

            $table->enum('status', ['pending', 'approved', 'reversed'])->default('pending');
            $table->unsignedBigInteger('transaction_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_payments');
    }
};
