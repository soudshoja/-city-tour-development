<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        // `default_group` -- see class docblock for the "at most one default per
        // (supplier, currency)" mechanism this backs.
        //
        // 2026-09-02 PORTABILITY FIX: this was a STORED GENERATED column. MariaDB 10.11.19
        // (production, reproduced on a clean `mariadb:10.11` container) rejects it with
        //     SQLSTATE[HY000] 1901: Function or expression
        //     'if(`is_default` = 1 and `is_active` = 1,concat(`supplier_id`,'-',`currency`),NULL)'
        //     cannot be used in the GENERATED ALWAYS AS clause of `default_group`
        // The offending reference is `currency`, a CHAR(3) column: MariaDB >= 10.5 refuses any
        // generated-column expression that reads a CHAR(n) column, because CHAR trailing-space
        // semantics depend on the session's sql_mode (PAD_CHAR_TO_FULL_LENGTH), which would make
        // the stored value non-deterministic across sessions. Isolated on the container: the
        // identical expression over a VARCHAR column is ACCEPTED, over a CHAR column REJECTED,
        // with no FK or function involved. MariaDB 10.4 (our XAMPP fence) and MySQL 8 do not
        // enforce this, which is why it passed locally and only surfaced against 10.11. This is
        // a DIFFERENT 10.11 rule from the one that broke `chk_payment_owner` on `payments` and
        // `payment_ref_dedup_key` on `transactions` (those reference columns carrying an
        // ON DELETE SET NULL foreign key) -- same error number, unrelated cause.
        //
        // `currency` deliberately stays CHAR(3) (the currency-code convention used across this
        // schema). Instead, `default_group` is now an ORDINARY nullable column kept in lockstep
        // with the identical expression by BEFORE INSERT / BEFORE UPDATE triggers that assign
        // `NEW.default_group` unconditionally -- so no application code can write it, exactly as
        // with a generated column, and the UNIQUE index below still does all the enforcing under
        // its unchanged name. Same technique as the two migrations cited above.
        // OPERATIONAL NOTE: `supplier_bank_details` therefore carries BEFORE INSERT/UPDATE
        // triggers -- bulk loads run row-by-row through them, and a dump/restore replays them as
        // recorded. No explicit DEFINER is set, so they are created as CURRENT_USER and install
        // under any DB account.
        if (! Schema::hasColumn('supplier_bank_details', 'default_group')) {
            Schema::table('supplier_bank_details', function (Blueprint $table) {
                $table->string('default_group', 80)
                    ->nullable()
                    ->after('is_active');
            });
        }

        if ($this->isMySql()) {
            $this->installTriggers();

            // Keep an already-populated table consistent with what the generated column would
            // have produced. A no-op on a virgin database.
            DB::statement(
                'UPDATE `supplier_bank_details` SET `default_group` = '
                .self::defaultGroupExpression('`supplier_bank_details`.')
            );
        }

        if (! Schema::hasIndex('supplier_bank_details', 'supplier_bank_details_default_group_unique')) {
            Schema::table('supplier_bank_details', function (Blueprint $table) {
                $table->unique('default_group', 'supplier_bank_details_default_group_unique');
            });
        }
    }

    public function down(): void
    {
        if ($this->isMySql()) {
            DB::unprepared('DROP TRIGGER IF EXISTS supplier_bank_details_default_group_before_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS supplier_bank_details_default_group_before_update');
        }

        Schema::dropIfExists('supplier_bank_details');
    }

    /**
     * The default-group expression, verbatim from the original generated-column definition,
     * rendered against a caller-supplied column prefix ('NEW.' in the triggers, the table name in
     * the backfill UPDATE) so trigger bodies and backfill can never drift apart.
     */
    private static function defaultGroupExpression(string $prefix): string
    {
        return "IF({$prefix}`is_default` = 1 AND {$prefix}`is_active` = 1, "
            ."CONCAT({$prefix}`supplier_id`, '-', {$prefix}`currency`), NULL)";
    }

    /**
     * Idempotent: DROP TRIGGER IF EXISTS before each CREATE, and no explicit DEFINER clause, so
     * the triggers are recorded as CURRENT_USER and reinstall cleanly under any DB account.
     */
    private function installTriggers(): void
    {
        $expr = self::defaultGroupExpression('NEW.');

        foreach (['insert', 'update'] as $event) {
            $name = "supplier_bank_details_default_group_before_{$event}";

            DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
            DB::unprepared(
                "CREATE TRIGGER {$name} BEFORE ".strtoupper($event).' ON `supplier_bank_details` '
                ."FOR EACH ROW SET NEW.`default_group` = {$expr}"
            );
        }
    }

    private function isMySql(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }
};
