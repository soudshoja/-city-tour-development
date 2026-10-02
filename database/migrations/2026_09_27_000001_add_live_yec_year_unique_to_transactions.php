<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * XBRL-X9r (X9R-VERIFY B-1): at most ONE live year-end close (YEC) per company and fiscal year,
 * enforced by the database.
 *
 * The verifier ran two `accounting:reclose-year` processes at once and got two live YECs
 * (`:rev1` and `:rev2`): Retained Earnings 12,000 instead of 6,000, the balance sheet still
 * footing, nothing flagged. The application now serialises the close (a row lock on the year's
 * December period) and uses a deterministic re-close key; this index is the last line: a second
 * live YEC for the same (company, year) cannot be inserted at all.
 *
 * `live_yec_year` is a STORED generated column: the fiscal year (`doc_year`) for a YEC header that
 * is not soft-deleted and not `reversed`, NULL for every other row. PostingService::reverse()
 * stamps `posting_status = 'reversed'` on the original, so a reversed YEC leaves the index and a
 * re-close can post the replacement. MySQL/MariaDB unique indexes admit any number of NULLs, so
 * every non-YEC row is unconstrained. None of the columns in the expression takes part in a
 * foreign key with a SET NULL action, which is what made MariaDB 10.11 refuse the generated
 * column in migration 2026_08_24_000002 (checked against the docker MariaDB 10.11.19).
 *
 * If an existing database already holds two live YECs for one year, adding the index FAILS, and
 * that is intended: it is real misstated equity to be resolved, not a migration to be forced.
 */
return new class extends Migration
{
    private const COLUMN = 'live_yec_year';

    private const INDEX = 'transactions_company_live_yec_year_unique';

    /** The generated expression: the fiscal year of a live YEC header, NULL for every other row. */
    private const EXPRESSION = "IF(`doc_type` = 'YEC' AND `deleted_at` IS NULL AND `posting_status` <> 'reversed', `doc_year`, NULL)";

    /**
     * Deliberately UNGUARDED (PortRatchetsTest's guarded-migration rule: a partial guard is worse
     * than none). If a run dies between the column and the index, the re-run fails loudly on the
     * existing column instead of marking a half-applied schema DONE.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedSmallInteger(self::COLUMN)->nullable()->storedAs(self::EXPRESSION);
            $table->unique(['company_id', self::COLUMN], self::INDEX);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(self::INDEX);
            $table->dropColumn(self::COLUMN);
        });
    }
};
