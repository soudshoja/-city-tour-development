<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Makes the account tree machine-trustworthy (Accounting Gap/11-technical-implementation-plan.md
     * §P1.0, L101-112).
     *
     * SCOPE NOTE: this migration adds ONLY the three columns below. It deliberately
     * does NOT add the CoaSeeder de-dup unique constraints
     * (unique[company_id,code] / unique[company_id,parent_id,name]) and does NOT run
     * the is_group backfill UPDATE — both are explicitly deferred to a later, data-touching
     * phase. is_group already exists (tinyint(1) default 1) but stays unreliable until that
     * backfill runs. account_type stays free-text varchar(255) — no schema change here; it
     * only becomes derived+non-fillable behaviorally via AccountService, not via a migration.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // BUG-H3: stop hard-delete of ledger history. accounts had no soft-delete
            // support before this migration (unlike journal_entries/transactions, which
            // already use the SoftDeletes trait).
            $table->softDeletes();

            // MF-13 / R8 audit attribution.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['deleted_at', 'created_by', 'updated_by']);
        });
    }
};
