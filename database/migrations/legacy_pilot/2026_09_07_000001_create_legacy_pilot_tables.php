<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * legacy-ledger-pilot LP0/LP1.
 *
 * Fixed mapping/audit tables for the quarantined `legacy_pilot` connection.
 * The 30 stg_<table> landing tables are NOT created here -- they are
 * created dynamically by App\Services\Onboarding\LegacyCsvLoader from each
 * CSV's own header row on first load (see that class's docblock for why:
 * several of the 30 export files run to 250+ untyped legacy columns, and
 * hand-authoring every one of them here would duplicate exactly the
 * information the CSV header already carries).
 *
 * RUN ONLY via:
 *   php artisan migrate --path=database/migrations/legacy_pilot --database=legacy_pilot
 * NEVER via the default `php artisan migrate` -- see
 * tests/Feature/Legacy/LegacyMigrationRatchetTest.php, which asserts this
 * file (and any sibling under this directory) is invisible to the default
 * migration path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('legacy_pilot')->create('legacy_load_audit', function (Blueprint $table) {
            $table->id();
            $table->string('table_key');           // e.g. "tblAccHeader"
            $table->string('stg_table');            // e.g. "stg_acc_header"
            $table->string('source_file');
            $table->unsignedBigInteger('expected_rows');
            $table->unsignedBigInteger('loaded_rows')->default(0);
            $table->string('status');               // loaded | skipped_identical | failed
            $table->string('file_hash', 64)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // Canonical header captured on first successful load of each file;
        // every subsequent load validates the CSV's header row against this
        // baseline (LP0.3 "header validation") and refuses on drift.
        Schema::connection('legacy_pilot')->create('legacy_load_manifest', function (Blueprint $table) {
            $table->id();
            $table->string('table_key')->unique();
            $table->longText('header_json');
            $table->string('file_hash', 64)->nullable();
            $table->timestamps();
        });

        // LP1.1: legacy Acc_ID/AccCode -> Akeed account_id, plus the
        // classification trail (why this leaf mapped where it did).
        Schema::connection('legacy_pilot')->create('legacy_acc_map', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('acc_id');           // legacy Acc_ID
            $table->string('acc_code')->nullable();          // legacy AccCode
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('account_id')->nullable(); // NULL only for a pooled/folded leaf
            $table->string('resolution');                    // direct | pooled_receivable | pooled_payable | folded_retained_earnings | unclassified
            $table->string('account_type_code', 1)->nullable(); // A/L/I/E as DECLARED by the row (tblAccount.AccType)
            // A/L/I/E derived from the row's POSITION (the AccType of the
            // root its parent chain ends at). Classification uses THIS one;
            // account_type_code is kept only so the declared-type split
            // (429/454/332/136) still reconciles and so declared-vs-position
            // dirt can be reported rather than silently absorbed.
            $table->string('position_type_code', 1)->nullable();
            $table->boolean('type_dirt')->default(false);    // declared != position
            $table->boolean('legacy_is_freeze')->default(false); // tblAccount.IsFreeze, preserved never dropped
            // LP1.3: for a POOLED party leaf, the party this leaf IS. Pooling
            // onto a control account is only reversible -- i.e. per-party AR/AP
            // parity is only reproducible -- if the leaf -> party identity
            // survives the fold. This column plus map_party is that identity.
            $table->unsignedBigInteger('party_id')->nullable();
            $table->string('party_role')->nullable();        // customer | supplier
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'acc_id']);
        });

        // LP1.2: tblSystemParameters control-account pointer -> Akeed
        // purpose_code, or an honest "unmapped" report row. Never invents a
        // mapping -- see App\Services\Onboarding\SystemPurposeMapper.
        Schema::connection('legacy_pilot')->create('map_purpose', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('purpose_code');
            $table->string('legacy_parameter_name')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('status');   // mapped | unmapped | skipped_non_leaf
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'purpose_code']);
        });

        // LP1.4: branches -> tags.
        Schema::connection('legacy_pilot')->create('map_branch', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id_fk');  // legacy Branch_ID
            $table->string('branch_code')->nullable();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('akeed_branch_id')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'branch_id_fk']);
        });

        // LP1.4: currencies actually used by an in-window line -> Akeed
        // currencies, EXCLUDING poison rows (LegacyPathGuard-adjacent audit
        // in App\Services\Onboarding\CurrencyAuditor).
        Schema::connection('legacy_pilot')->create('map_currency', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('curr_id_fk');
            $table->string('curr_code')->nullable();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('akeed_currency_id')->nullable();
            $table->boolean('is_poison')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'curr_id_fk']);
        });

        // LP0.4/LP1.4: legacy:audit-masters report rows -- one per check,
        // machine-readable (status + JSON detail) so a re-run can diff
        // against the previous audit as well as printing to the console.
        Schema::connection('legacy_pilot')->create('legacy_masters_audit', function (Blueprint $table) {
            $table->id();
            $table->string('check_key');   // e.g. currency_poison, unposted_headers, ojv_inventory
            $table->string('status');       // pass | fail | info
            $table->longText('detail_json')->nullable();
            $table->timestamp('run_at');
            $table->timestamps();
        });

        // LP1.3: partners -> party role account refs (dual role = one row
        // per role, never a duplicated party).
        Schema::connection('legacy_pilot')->create('map_party', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id_fk');
            $table->unsignedBigInteger('company_id');
            $table->boolean('is_customer')->default(false);
            $table->boolean('is_supplier')->default(false);
            $table->unsignedBigInteger('cust_acc_id_fk')->nullable();
            $table->unsignedBigInteger('supp_acc_id_fk')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'partner_id_fk']);
        });
    }

    public function down(): void
    {
        Schema::connection('legacy_pilot')->dropIfExists('map_party');
        Schema::connection('legacy_pilot')->dropIfExists('legacy_masters_audit');
        Schema::connection('legacy_pilot')->dropIfExists('map_currency');
        Schema::connection('legacy_pilot')->dropIfExists('map_branch');
        Schema::connection('legacy_pilot')->dropIfExists('map_purpose');
        Schema::connection('legacy_pilot')->dropIfExists('legacy_acc_map');
        Schema::connection('legacy_pilot')->dropIfExists('legacy_load_manifest');
        Schema::connection('legacy_pilot')->dropIfExists('legacy_load_audit');
    }
};
