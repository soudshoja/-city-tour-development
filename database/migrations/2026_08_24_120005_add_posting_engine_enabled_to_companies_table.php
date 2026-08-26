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
     * Per-company kill-switch for the posting engine. Nothing reads or writes this
     * column in P1 (no feeder is wired to the engine yet) — it exists purely so P2's
     * per-company strangler cutover has the flag ready on day one. Sits alongside the
     * global kill-switch config('accounting.engine.enabled') / env
     * ACCOUNTING_ENGINE_ENABLED (see config/accounting.php).
     *
     * IMPORTANT: app/Models/Company.php is out of scope for this build (do-not-touch
     * list) — this column is NOT added to Company::$fillable or Company::$casts.
     * Direct property access ($company->posting_engine_enabled = true; $company->save();)
     * and raw query-builder access (DB::table('companies')) both bypass $fillable
     * (which only gates mass-assignment), so the column is still fully usable; nothing
     * in P1 reads or writes it either way.
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
