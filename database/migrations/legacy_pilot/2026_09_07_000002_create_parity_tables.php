<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * legacy-ledger-pilot LP4 -- the parity harness's own two tables, on the
 * quarantined `legacy_pilot` connection.
 *
 * These are DELIBERATELY not root migrations: a parity run is staging
 * evidence about a throwaway legacy load, and PLAN.md §6 rules that every
 * stg_ and map_ artefact "stays staging-only, permanently". Landing them in
 * database/migrations/ (root) would put them on prod's pending-migration
 * list. tests/Feature/Legacy/LegacyMigrationRatchetTest.php holds that line.
 *
 * RUN ONLY via:
 *   php artisan migrate --path=database/migrations/legacy_pilot --database=legacy_pilot
 *
 * WHY PERSIST A PARITY RUN AT ALL, when the command also writes a JSON and
 * a markdown report? Because PLAN.md §LP4 requires drill-down from an
 * account diff to the contributing documents, and LP6 gate 1 requires
 * "every residual delta named and classified". A file on disk answers
 * neither "which run was this" nor "did this account's delta change since
 * the last run"; two tables do, and they are queryable next to the
 * map_document/map_document_line rows the drill-down joins against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('legacy_pilot')->create('parity_run', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('run_key', 64)->unique();     // ULID-ish; appears in every report header
            $table->date('as_of');
            $table->string('anchor', 16);                 // opening | closing | pl | ar | ap | all
            $table->string('status', 8);                  // pass | fail
            $table->unsignedInteger('checks_total')->default(0);
            $table->unsignedInteger('checks_failed')->default(0);
            $table->unsignedInteger('accounts_compared')->default(0);
            $table->unsignedInteger('accounts_out_of_tolerance')->default(0);
            $table->longText('summary_json')->nullable(); // the machine-readable result, verbatim
            $table->string('report_path')->nullable();
            $table->string('json_path')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // One row per compared unit that did not match, PLUS one row per
        // non-account-level check that failed. Deliberately NOT one row per
        // compared account: a green run over 532 accounts would otherwise
        // write 532 rows every time and bury the five that matter.
        Schema::connection('legacy_pilot')->create('parity_diff', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parity_run_id')->index();
            $table->string('check_key', 48);              // tb_opening | tb_closing | pl | ar | ap | doc_counts | posting_date | verify_scope | aggregate
            $table->string('anchor', 16)->nullable();
            $table->string('acc_code')->nullable();        // THEIR code -- the only account identity a legacy reader has
            $table->unsignedBigInteger('legacy_acc_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();  // OUR account
            $table->unsignedBigInteger('party_id')->nullable();    // pooled leaves only; rendered as PARTY-<id>
            $table->string('dimension', 24)->nullable();   // opening | period_dr | period_cr | closing | net_income | count
            $table->decimal('legacy_value', 20, 3)->nullable();
            $table->decimal('akeed_value', 20, 3)->nullable();
            $table->decimal('delta', 20, 3)->nullable();
            // WHY a free-text classification and not an enum: PLAN.md §5.2
            // O4 fixes three refusal classes for DOCUMENTS (engine_gap /
            // mapping_defect / legacy_data_defect), but an account-level
            // residual can also be structural (missing_in_akeed,
            // missing_in_legacy, unmapped_account, undecomposable_party).
            // An enum here would have to be widened by a migration every
            // time a new residual shape is found -- exactly the churn a
            // pilot harness must not impose. Zero unclassified rows is
            // still the pass line (O5); the column is NOT NULL for that
            // reason.
            $table->string('classification', 40);
            $table->longText('detail_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('legacy_pilot')->dropIfExists('parity_diff');
        Schema::connection('legacy_pilot')->dropIfExists('parity_run');
    }
};
