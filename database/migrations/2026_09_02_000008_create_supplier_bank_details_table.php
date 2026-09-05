<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T14 "Supplier bank details per currency" (accounting-builds PLAN.md §5 T14; locked decision L18,
 * owner ruling 2026-09-02): one-to-many supplier -> bank remittance details keyed by currency.
 * A supplier paid in EUR carries its own EUR receiving-account row (bank, IBAN/account no.,
 * SWIFT/BIC, beneficiary name, bank country, optional intermediary bank, optional notes), paid in
 * USD another. Master data only -- no `journal_entries`/`transactions` write ever originates here,
 * no PostingSeam involvement.
 *
 * "At most one row marked DEFAULT per (supplier, currency)" is enforced at the DB layer, not only
 * in app code (L18/T2's own guarded-mint discipline), via the MariaDB/MySQL "at most one true per
 * group" generated-column trick: `default_group` is a nullable STORED generated column that is
 * NULL unless a row is BOTH `is_default` and `is_active`, in which case it holds
 * `"{supplier_id}-{currency}"`. A UNIQUE index over that column then rejects a second
 * default-and-active row for the same supplier+currency outright (MySQL/MariaDB unique indexes
 * permit unlimited NULLs, so every non-default / inactive row is exempt). Same `->storedAs()`
 * pattern already proven on this MariaDB version by migration
 * 2026_08_24_000002_add_post_cutover_dedup_key_to_transactions_table.php.
 *
 * Soft deletes (`deleted_at`) are the hard-delete escape hatch only; the everyday "retire a row"
 * action is `is_active = false` (never a hard delete per L18's own text) -- both exist because
 * `is_active` is what the payment-voucher selection and the generated-column constraint read,
 * while `deleted_at` stays available for the rare true removal (e.g. a row entered in error).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_bank_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->char('currency', 3);
            $table->string('bank_name', 191);
            $table->string('beneficiary_name', 191);
            $table->string('account_number', 100)->nullable();
            $table->string('iban', 50)->nullable();
            $table->string('swift_bic', 20);
            $table->string('bank_country', 100);
            $table->string('intermediary_bank_name', 191)->nullable();
            $table->string('intermediary_swift_bic', 20)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'supplier_id']);
            $table->index(['supplier_id', 'currency']);
        });

        // Nullable STORED generated column -- see class docblock for the "at most one default per
        // (supplier, currency)" mechanism this backs. Kept as its own Schema::table() call (not
        // inlined into the create() above) to match this repo's own established convention for a
        // storedAs() column that also needs a named unique index (see the transactions
        // dedup-key migration cited above), and so a rerun after a partial failure can detect the
        // column/index independently via Schema::hasColumn()/hasIndex().
        if (! Schema::hasColumn('supplier_bank_details', 'default_group')) {
            Schema::table('supplier_bank_details', function (Blueprint $table) {
                $table->string('default_group', 80)
                    ->nullable()
                    ->storedAs(
                        "IF(`is_default` = 1 AND `is_active` = 1, CONCAT(`supplier_id`, '-', `currency`), NULL)"
                    )
                    ->after('is_active');
            });
        }

        if (! Schema::hasIndex('supplier_bank_details', 'supplier_bank_details_default_group_unique')) {
            Schema::table('supplier_bank_details', function (Blueprint $table) {
                $table->unique('default_group', 'supplier_bank_details_default_group_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_bank_details');
    }
};
