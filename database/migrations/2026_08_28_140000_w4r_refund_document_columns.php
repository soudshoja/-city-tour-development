<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * W4.R (w4-brief.md §4 + §5) — additive schema for the refund document. Every change here is
 * additive (new nullable columns, or a widened enum via raw MODIFY on MySQL — never a
 * Schema::create()/dropIfExists() rebuild): `refunds` has been rebuilt 3× already (ct-refund-map.md
 * §1) and the brief explicitly forbids a 4th.
 *
 * - `refunds.status`: the brief's real status workflow is
 *   draft -> approved -> posted -> completed | rejected (w4-brief.md §4, process decisions —
 *   CT's pending/approved/declined columns are dead code, not ported). The existing ENUM
 *   ('pending','approved','processed','completed','declined') cannot express 'draft'/'posted'/
 *   'rejected' and MySQL enum widening needs doctrine/dbal for Laravel's `->change()` — avoided
 *   here via a raw `MODIFY COLUMN` to a plain VARCHAR instead (this table's own `method` column
 *   stays an ENUM; only `status` — the column this wave's workflow actually extends — is
 *   converted). Existing values ('pending'/'approved'/'processed'/'completed'/'declined') are
 *   preserved as-is (a plain string holds every one of them unchanged); 'processed' is legacy-only
 *   from here on (OFF-path parity), new ON-path rows use 'draft'/'approved'/'posted'/'completed'/
 *   'rejected'.
 * - `refund_batch_id`: w4-brief.md §4 process decisions — "the system emits ONE refund document
 *   per carrying invoice, grouped by refund_batch_id ... all posted in one DB transaction".
 * - approval/posting/completion/rejection audit columns for the new status workflow.
 * - `refund_details.supplier_refund_amount`: w4-brief.md §4 process decisions — "SPLIT
 *   supplier_charge into airline_penalty (kept as supplier_charge, documented) + NEW
 *   supplier_refund_amount (nullable, defaults to cost - penalty; editable when the airline's
 *   actual refund differs)". `supplier_charge` itself is kept, unrenamed, per that same
 *   instruction — this migration does not touch it.
 * - `refund_clients.refund_id`: w4-brief.md §4 — "Fold refund_clients into the refund doc
 *   (additive migration moving rows; keep the model read-only for now, remove write paths)".
 *   `refund_clients` has NO existing FK to `refunds` or `invoices` at all (ct-refund-map.md §1:
 *   "Orphan mini-workflow"), so there is no real key to join existing rows on — inventing one
 *   would fabricate a link the data never had. This column prepares the schema for future
 *   linkage (a refund created going forward MAY populate it); existing orphan rows are left
 *   NULL and unmapped, the same "flag for manual review, don't guess" treatment the brief itself
 *   gives the 98 pending `invoice_receipts` rows (w4-brief.md "Owner answers" §4). See
 *   RefundClient's own docblock for the read-only guard.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL-specific raw MODIFY — matches this table's own driver (see phpunit.xml /
        // config/database.php: mysql_testing everywhere in this codebase's test suite).
        DB::statement("ALTER TABLE refunds MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'draft'");

        Schema::table('refunds', function (Blueprint $table) {
            $table->unsignedBigInteger('refund_batch_id')->nullable()->after('refund_invoice_id')->index();

            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('posted_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('posted_by');
            $table->foreignId('completed_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->after('completed_by');
            $table->foreignId('rejected_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');

            // w4-brief.md §4f — disposition of client net: credit|refund_out|apply, per-case
            // override of the company's invoice_overpay_cancel_policy default.
            $table->string('disposition', 20)->nullable()->after('method');
            // Populated only when disposition = 'apply' — the open invoice the CRN was applied to.
            $table->foreignId('applied_invoice_id')->nullable()->after('disposition')
                ->constrained('invoices')->nullOnDelete();

            // w4-brief.md §4e — clawback amount entered by the operator (auto from AIR RF/ADM
            // parse when present, else manual entry needing approver per the brief's own
            // "Decisions" section). Nullable: most refunds carry no airline clawback at all.
            $table->decimal('airline_clawback_amount', 15, 3)->nullable()->after('total_nett_refund');

            // w4-brief.md §4 gateway-refund listener — the id GatewayRefundStatusChanged carries,
            // so the completion handler can find the draft refund it belongs to without
            // re-deriving it from a description string.
            $table->string('gateway_refund_id', 100)->nullable()->after('airline_clawback_amount')->index();
        });

        Schema::table('refund_details', function (Blueprint $table) {
            // w4-brief.md §4 process decisions — "supplier net = supplier_refund_amount". Nullable
            // so existing rows (and any row not yet given an operator override) fall back to
            // RefundPostingService's own default (cost - penalty) rather than a false zero.
            $table->decimal('supplier_refund_amount', 15, 3)->nullable()->after('supplier_charge');
        });

        Schema::table('refund_clients', function (Blueprint $table) {
            $table->foreignId('refund_id')->nullable()->after('id')->constrained('refunds')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('refund_clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refund_id');
        });

        Schema::table('refund_details', function (Blueprint $table) {
            $table->dropColumn('supplier_refund_amount');
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->dropColumn('gateway_refund_id');
            $table->dropColumn('airline_clawback_amount');
            $table->dropConstrainedForeignId('applied_invoice_id');
            $table->dropColumn('disposition');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn('rejected_at');
            $table->dropConstrainedForeignId('completed_by');
            $table->dropColumn('completed_at');
            $table->dropConstrainedForeignId('posted_by');
            $table->dropColumn('posted_at');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
            $table->dropColumn('refund_batch_id');
        });

        DB::statement("ALTER TABLE refunds MODIFY COLUMN status ENUM('pending','approved','processed','completed','declined') NOT NULL DEFAULT 'processed'");
    }
};
