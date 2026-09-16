<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * legacy-ledger-pilot LP1c — coordinator ruling R1 (2026-09-07).
 *
 * `--replace-seeded` used to remove only accounts it could positively
 * identify as CoaSeeder-created and refuse on anything else. Staging run #3
 * proved that rule too narrow: the stock DatabaseSeeder chain ALSO mints 9
 * party/gateway accounts (3 supplier payable leaves via
 * SupplierCompanyController::activateSupplierProcess(), 5 payment-gateway
 * leaves and 1 agent leaf via EntitySeeder), none of which is in CoaSeeder's
 * literal code list — so the guard refused and LP1 could not proceed.
 *
 * R1's replacement rule removes the ENTIRE chart when the chart is provably
 * unused. Deleting more rows demands a better audit trail, not a worse one,
 * so these two tables are the trail:
 *
 *   seeded_chart_removed   one row per Account deleted, with its identity and
 *                          its owner FKs, so a human can see exactly what the
 *                          run took away.
 *   seeded_chart_fk_nulled one row per dependent foreign key the removal had
 *                          to NULL (a supplier/agent/gateway/user row that
 *                          pointed at a removed account). These are the
 *                          "needs re-linking at go-live" list: re-pointing
 *                          them automatically by NAME is forbidden (the
 *                          legacy export is pseudonymised, so a name match
 *                          would be a fabricated mapping), so they are nulled
 *                          -- never left dangling -- and reported.
 *
 * RUN ONLY via:
 *   php artisan migrate --path=database/migrations/legacy_pilot --database=legacy_pilot
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('legacy_pilot')->create('seeded_chart_removed', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            // The app-side accounts.id this row USED to be. Deliberately not a
            // FK: the row it names is gone by the time this is committed.
            $table->unsignedBigInteger('account_id');
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->string('account_type')->nullable();
            $table->string('report_type')->nullable();
            $table->unsignedInteger('level')->nullable();
            $table->boolean('is_group')->default(false);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('root_id')->nullable();
            // Owner FKs carried ON the account row itself (accounts.branch_id /
            // agent_id / supplier_company_id / reference_id). Recorded so the
            // go-live re-link can tell a gateway leaf from an agent leaf
            // without guessing from the name.
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('supplier_company_id')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            // Whether the OLD narrow allow-list would have recognised this row
            // as a CoaSeeder/EnsureSystemLeaves default. Kept as a report
            // column only -- it no longer decides anything.
            $table->boolean('recognised_default')->default(false);
            $table->timestamp('removed_at');
            $table->timestamps();
            $table->index(['company_id', 'code']);
        });

        Schema::connection('legacy_pilot')->create('seeded_chart_fk_nulled', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('table_name');
            $table->string('column_name');
            $table->unsignedBigInteger('row_id');
            $table->unsignedBigInteger('old_account_id');
            $table->string('old_account_code')->nullable();
            $table->string('old_account_name')->nullable();
            // 'nulled'          -- FK cleared, awaiting a go-live re-link.
            // 'relinked'        -- re-pointed at an imported legacy leaf that a
            //                      map_party row positively identified.
            $table->string('status')->default('nulled');
            $table->unsignedBigInteger('relinked_account_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'table_name']);
        });
    }

    public function down(): void
    {
        Schema::connection('legacy_pilot')->dropIfExists('seeded_chart_fk_nulled');
        Schema::connection('legacy_pilot')->dropIfExists('seeded_chart_removed');
    }
};
