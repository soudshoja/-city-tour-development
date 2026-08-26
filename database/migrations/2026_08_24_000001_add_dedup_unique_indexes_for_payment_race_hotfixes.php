<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part of the P0 launch hotfixes (HF-4 / HF-5): the application-level fixes
 * (Sequence::lockForUpdate() claim, Payment::lockForUpdate() completion guard)
 * close the *race window*, but a unique index is the database-level backstop
 * that makes a duplicate voucher number or a double-posted gateway completion
 * impossible even if a future code path reintroduces the race.
 *
 * DO NOT run this migration until the data has been audited for existing
 * duplicates under these keys (e.g.
 *   SELECT company_id, voucher_number, COUNT(*) FROM payments
 *     GROUP BY company_id, voucher_number HAVING COUNT(*) > 1;
 * and the equivalent for transactions(payment_id, reference_type)) --
 * adding a unique index over rows that already violate it will fail the
 * migration outright. See "Accounting Gap" hotfix HF-4/HF-5 notes.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // A voucher number only needs to be unique within a company (each
            // company's sequence starts at 1), so the index is composite.
            $table->unique(['company_id', 'voucher_number'], 'payments_company_id_voucher_number_unique');
        });

        Schema::table('transactions', function (Blueprint $table) {
            // At most one journal-posting Transaction per (payment, reference_type)
            // -- e.g. one 'Payment' transaction per gateway completion. NULL
            // payment_id rows (transactions not tied to a payment) are exempt:
            // MySQL/Postgres do not enforce uniqueness among NULLs.
            $table->unique(['payment_id', 'reference_type'], 'transactions_payment_id_reference_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_company_id_voucher_number_unique');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_payment_id_reference_type_unique');
        });
    }
};
