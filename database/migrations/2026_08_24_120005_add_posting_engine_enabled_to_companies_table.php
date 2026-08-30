<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SYNTHESIZED per Accounting Gap/11-technical-implementation-plan.md §C2 (L58:
     * "A per-company boolean companies.posting_engine_enabled (migration in P1)")
     * and the P1 Rollback section (L340: "P1 ships behind the flag
     * (posting_engine_enabled = false for all companies)") — no migration snippet is
     * given verbatim for this column, but both references place it inside P1.
     *
     * Per-company kill-switch for the posting engine. LIVE as of the W0 kill-switch
     * fix: PostingService::post() reads this column (via Company::find()) before
     * DB::transaction() opens, and refuses (PostingEngineDisabledException) if it is
     * false, in addition to (not instead of) the global kill-switch
     * config('accounting.engine.enabled') / env ACCOUNTING_ENGINE_ENABLED (see
     * config/accounting.php).
     *
     * The column IS in Company::$fillable and Company::$casts (boolean) — added
     * alongside this migration specifically because mass-assignment
     * ($company->update(['posting_engine_enabled' => false])) previously silently
     * no-op'd without either of those (Eloquent mass-assignment protection, not a DB
     * constraint), which made an emergency operator rollback issued that way return
     * success while the engine kept writing. The operator-facing lever is
     * `php artisan accounting:engine {company} --enable|--disable|--status`
     * (App\Console\Commands\AccountingEngine), which uses mass-assignment and
     * therefore depends on both of those Company changes.
     *
     * Plain additive column — no ->after() dependency on anything in the do-not-touch
     * list, so no ordering conflict with the companies table's existing columns.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('posting_engine_enabled')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('posting_engine_enabled');
        });
    }
};
