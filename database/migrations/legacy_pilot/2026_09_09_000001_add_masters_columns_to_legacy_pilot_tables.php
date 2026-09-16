<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * legacy-ledger-pilot LP1e — the columns `legacy:import-masters` needs.
 *
 * Staging run #5 stopped on `legacy.branch_unmapped` for 100% of the 2025
 * population because `map_branch` and `map_currency` are read by
 * {@see App\Services\Onboarding\Replay\LegacyDocumentMapper} and written by
 * NOTHING in production code — only by a test fixture. LP1e adds the
 * populators; this migration adds the four columns they need beyond what
 * 2026_09_07_000001 created.
 *
 * map_branch
 *   The legacy branch master (`tblBranch`) carries five control-account FKs
 *   (BranchAccID_FK, CashAccID_FK, CashControlAccID_FK, BankAccID_FK,
 *   DiscountAcc) and an IsFreeze flag. Akeed's own `branches` table has NO
 *   such columns — a branch owns an account through `accounts.branch_id`, not
 *   the other way round — so R-branch's "attach the branch's control-account
 *   FKs to mapped accounts where the app's branch model has such fields" has
 *   no field to attach to. They are RECORDED here instead, already resolved
 *   through `legacy_acc_map` where the leaf was imported, so nothing from the
 *   master is silently dropped and LP6 can wire them the day `branches` grows
 *   those columns.
 *
 * map_currency
 *   `status` is the load-bearing addition. The currency master these FKs
 *   point at (`tblMaster`) was never exported, so a code can only be DERIVED
 *   from line usage (PLAN.md §1.1). A derivation that finds nothing is an
 *   honest `unresolved` row — NOT an absent row, which the mapper cannot tell
 *   apart from "this FK was never seen" — and R-currency rules that an
 *   unresolved currency is metadata, not a refusal, because the posting is in
 *   KWD either way.
 *
 * map_document_line.metadata_currency / map_document.currency_unresolved_line_count
 *   Where that metadata lands, and its count per document.
 *
 * RUN ONLY via:
 *   php artisan migrate --path=database/migrations/legacy_pilot --database=legacy_pilot
 * NEVER via the default `php artisan migrate` — see
 * tests/Feature/Legacy/LegacyMigrationRatchetTest.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('legacy_pilot')->table('map_branch', function (Blueprint $table) {
            // Structural name assigned by the importer (never the legacy
            // BranchName — a branch name is business data the pilot does not
            // republish into the app database).
            $table->string('branch_name')->nullable()->after('branch_code');
            $table->boolean('legacy_is_freeze')->default(false)->after('akeed_branch_id');

            // The legacy master's own control-account pointers, plus the Akeed
            // account each resolves to through legacy_acc_map (NULL when the
            // legacy leaf pooled, folded or was never imported).
            $table->unsignedBigInteger('legacy_branch_acc_id_fk')->nullable();
            $table->unsignedBigInteger('legacy_cash_acc_id_fk')->nullable();
            $table->unsignedBigInteger('legacy_cash_control_acc_id_fk')->nullable();
            $table->unsignedBigInteger('legacy_bank_acc_id_fk')->nullable();
            $table->unsignedBigInteger('legacy_discount_acc_id_fk')->nullable();
            $table->unsignedBigInteger('branch_account_id')->nullable();
            $table->unsignedBigInteger('cash_account_id')->nullable();
            $table->unsignedBigInteger('cash_control_account_id')->nullable();
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->unsignedBigInteger('discount_account_id')->nullable();
        });

        Schema::connection('legacy_pilot')->table('map_currency', function (Blueprint $table) {
            // mapped | unresolved. A `poison` code is still `mapped` — it
            // resolved, it is simply quarantined; is_poison carries that.
            $table->string('status', 32)->default('unresolved')->after('is_poison');
            // rate_history | kwd_identity | none
            $table->string('derivation', 32)->nullable()->after('status');
            $table->decimal('matched_rate', 18, 7)->nullable()->after('derivation');
            $table->unsignedInteger('line_count')->default(0)->after('matched_rate');
            $table->text('notes')->nullable()->after('line_count');
        });

        Schema::connection('legacy_pilot')->table('map_document_line', function (Blueprint $table) {
            // R-currency: `legacy_curr_<fk>` on a line whose FC currency could
            // not be derived. posted_currency stays the base currency, because
            // that is genuinely what was posted.
            $table->string('metadata_currency', 32)->nullable()->after('posted_exchange_rate');
        });

        Schema::connection('legacy_pilot')->table('map_document', function (Blueprint $table) {
            $table->unsignedInteger('currency_unresolved_line_count')->default(0)->after('fc_rebased_line_count');
        });
    }

    public function down(): void
    {
        Schema::connection('legacy_pilot')->table('map_document', function (Blueprint $table) {
            $table->dropColumn('currency_unresolved_line_count');
        });

        Schema::connection('legacy_pilot')->table('map_document_line', function (Blueprint $table) {
            $table->dropColumn('metadata_currency');
        });

        Schema::connection('legacy_pilot')->table('map_currency', function (Blueprint $table) {
            $table->dropColumn(['status', 'derivation', 'matched_rate', 'line_count', 'notes']);
        });

        Schema::connection('legacy_pilot')->table('map_branch', function (Blueprint $table) {
            $table->dropColumn([
                'branch_name', 'legacy_is_freeze',
                'legacy_branch_acc_id_fk', 'legacy_cash_acc_id_fk', 'legacy_cash_control_acc_id_fk',
                'legacy_bank_acc_id_fk', 'legacy_discount_acc_id_fk',
                'branch_account_id', 'cash_account_id', 'cash_control_account_id',
                'bank_account_id', 'discount_account_id',
            ]);
        });
    }
};
