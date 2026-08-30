<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W5.R (w5-brief.md §W5.R) — the columns `invoice_receipts` needs to be the durable RV document
 * row driving `ReceiptVoucherController` through {@see \App\Services\Accounting\PostingSeam}.
 *
 * ── Why `transaction_id` is widened to nullable (the one non-additive-looking change here) ──────
 * `invoice_receipts.transaction_id` has been `NOT NULL` since the table's very first migration
 * (2025_09_20_060245_create_invoice_receipt_table.php). Under the engine, a `transactions` row is
 * created ONLY by {@see \App\Services\Accounting\PostingService::post()} — there is no way to have
 * a "reserved but not yet posted" `transactions` row, and inventing one would violate the engine's
 * own "exactly one balanced write" contract. RV's existing lifecycle (mirrored from
 * `RefundController`'s already-shipped draft -> approve -> post pattern, W4.R) needs a real
 * "created, awaiting approval, nothing posted yet" state — so `transaction_id` must be nullable to
 * represent it. This is a widening (NOT NULL -> NULL), not a narrowing: every existing row already
 * has a non-null value and keeps it; nothing is dropped, renamed, or made stricter.
 *
 * Every other column below is a plain additive nullable column, mirroring the same conventions
 * `2026_08_28_140000_w4r_refund_document_columns.php` established for `refunds` one wave earlier:
 *   - `company_id` / `branch_id`: resolvable BEFORE a transaction exists (draft state needs them
 *     for policy/module checks and to build the eventual DocumentDraft).
 *   - `doc_date`: the voucher's own business date, distinct from `created_at` — this IS
 *     `transactions.transaction_date`/`DocumentDraft::$docDate` once posted (BUG-C4 convention).
 *   - `client_id`: the "pay to" client, when the RV names one (nullable — an ACCOUNT-type RV may
 *     name only an `account_id`, already an existing column).
 *   - `bank_account_id`: the specific bank leaf the voucher's instrument leg targets when it is
 *     neither pure cash nor an in-transit cheque — validated via
 *     {@see \App\Services\Accounting\AccountResolver::assertUnderBankGroup()} at post time, never
 *     trusted as-is. Deliberately no DB-level FK (same convention `system_accounts.account_id`
 *     already uses — "enforced in code", per that migration's own comment) so this migration
 *     cannot fail against a differently-shaped `accounts` table in an older environment.
 *   - `cheque_no` / `cheque_date` / `cheque_clearance_date` / `bank_info` / `auth_no`: the
 *     instrument identity fields, persisted on the DOCUMENT row (not just the posted
 *     `journal_entries` legs LineDraft already carries them to) so `update()`/`clear()`/`bounce()`
 *     can rebuild an accurate DocumentDraft without re-parsing posted lines.
 *   - `allocations`: JSON array of `{invoice_id, amount}` — the RV's open-item application lines
 *     (w5-brief.md §W5.R "allocation lines: RV carries `[{invoice_id, amount}]`"). NULL for a
 *     single-invoice/legacy-shaped row (see ReceiptVoucherController::resolveAllocations()'s own
 *     fallback for the two pre-existing callers, `createReceiptVoucher()`/`autoGenerate()`, that
 *     predate this column and never populate it).
 *   - `remainder_amount` / `remainder_policy`: the disposition of any amount left over once
 *     `allocations` are applied — `remainder_policy` is one of {credit, hold, block}, resolved
 *     from the company's own option (see config('accounting.vouchers')) at store() time and frozen
 *     onto the row so a later `update()`/audit does not silently pick up a DIFFERENT company
 *     default if the setting changes after this voucher was created.
 *   - `sub_type`: the actual RV sub_type this row posts under (INVOICE/ACCOUNT/TOPUP/IMPORT) —
 *     `type` (the pre-existing column) stays the legacy vocabulary (invoice/account/credit/import)
 *     every existing reader already filters on; `sub_type` is the NEW engine vocabulary
 *     ({@see \App\Services\Accounting\VoucherSubTypeGuard}), kept as a separate column rather than
 *     overloading `type` so neither reader breaks.
 *   - `voucher_number`: a DRAFT-only display label (e.g. "RV-DRAFT-{id}") for a still-pending row
 *     that has no real accounting document number yet — {@see \App\Services\Accounting\SequenceService}
 *     mints the REAL number only inside `PostingService::post()`, never here; this column is never
 *     treated as the authoritative document number once `transaction_id` is set (read
 *     `transaction->reference_number` instead at that point).
 *   - `remarks` / `remarks_internal`: additive carry-forward of the free-text fields
 *     `ReceiptVoucherController::store()` already collects (`remarks_create`/`internal_remarks`)
 *     but the pre-W5.R schema had nowhere durable to put before a `transactions` row existed.
 *   - `status` is WIDENED (not narrowed) from `{pending, approved, rejected}` to also allow
 *     `reversed` (W5.R `delete()` on an already-posted voucher) and `bounced` (W5.R `bounce()` on a
 *     cleared cheque) — both new states this wave's lifecycle needs; every pre-existing value stays
 *     legal and unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('transaction_id')->nullable()->change();

            $table->unsignedBigInteger('company_id')->nullable()->after('id');
            $table->unsignedBigInteger('branch_id')->nullable()->after('company_id');
            $table->date('doc_date')->nullable()->after('branch_id');
            $table->unsignedBigInteger('client_id')->nullable()->after('account_id');

            $table->unsignedBigInteger('bank_account_id')->nullable()->after('client_id');
            $table->string('cheque_no', 100)->nullable()->after('bank_account_id');
            $table->date('cheque_date')->nullable()->after('cheque_no');
            $table->date('cheque_clearance_date')->nullable()->after('cheque_date');
            $table->string('bank_info', 200)->nullable()->after('cheque_clearance_date');
            $table->string('auth_no', 100)->nullable()->after('bank_info');

            $table->json('allocations')->nullable()->after('auth_no');
            $table->decimal('remainder_amount', 15, 3)->nullable()->default(0)->after('allocations');
            $table->string('remainder_policy', 20)->nullable()->after('remainder_amount');

            $table->string('sub_type', 20)->nullable()->after('type');
            $table->string('voucher_number', 40)->nullable()->after('sub_type');

            $table->text('remarks')->nullable()->after('voucher_number');
            $table->text('remarks_internal')->nullable()->after('remarks');
        });

        Schema::table('invoice_receipts', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected', 'reversed', 'bounced'])
                ->default('pending')
                ->after('amount')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_receipts', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('amount')->change();
        });

        Schema::table('invoice_receipts', function (Blueprint $table) {
            $table->dropColumn([
                'company_id', 'branch_id', 'doc_date', 'client_id',
                'bank_account_id', 'cheque_no', 'cheque_date', 'cheque_clearance_date',
                'bank_info', 'auth_no', 'allocations', 'remainder_amount', 'remainder_policy',
                'sub_type', 'voucher_number', 'remarks', 'remarks_internal',
            ]);

            $table->unsignedBigInteger('transaction_id')->nullable(false)->change();
        });
    }
};
