<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CD-PORT round 2 — the row ledger, and the persisted before-picture.
 *
 * ── Why this exists: the id band is NOT ownership ───────────────────────────────────────────────
 * Round 1 deleted by `WHERE id BETWEEN <floor> AND <ceiling>`, exactly as
 * CD0-REVERSAL-MANIFEST §3 specifies. Adversarial verification proved that unsound, and proved it
 * twice on a fence:
 *
 *     legacy:unload --company=10000001 --apply
 *       -> "5 row(s) deleted across 5 table(s); 0 residual rows in the band."
 *          FOUR of the five were City Travelers' — a user, a supplier, an agent, a queued job.
 *
 *     legacy:unload --company=99999999 --apply      (a company that never existed)
 *       -> "1 row(s) deleted ... 0 residual" — a City Travelers supplier, gone.
 *
 * The mechanism is worse than an oversight: **the port manufactures the precondition itself.**
 * `legacy:scope --apply` raises every declared table's `AUTO_INCREMENT` to the floor, so from that
 * moment every row City Travelers' own dev application — and `test.citycommerce.group`, the second
 * app on the same schema — mints into `users`, `agents` or `suppliers` lands INSIDE the band. On
 * the deploy plan, days pass between arming and unloading, and that trap fills continuously. Those
 * four tables have no `company_id` column, so a company predicate cannot save them, and
 * `fingerprintOutsideBand()` computes over `id NOT BETWEEN`, so a City Travelers row INSIDE the
 * band is excluded from the "untouched" proof by construction. The round-1 evidence could not see
 * its own defect.
 *
 * ── The replacement principle: ownership by ledger, not by range ────────────────────────────────
 * `ct_scope_row` records the id of every row the load owns, per table, derived from an explicit
 * ATTRIBUTION RULE per table (see {@see \App\Services\Onboarding\Scope\LegacyRowLedger}) rather
 * than from an id range. `legacy:unload` deletes **by that list and nothing else**. The band keeps
 * its other job — keeping Como's ids out of production's reach in the `insert_only` sync, which is
 * what ruling S-A actually preserved it for — but it is no longer evidence of who owns a row.
 *
 * A row sitting inside the band that is NOT in this ledger is now a refusal that names the table
 * and the id, because that is precisely the City Travelers row round 1 would have deleted.
 *
 * ── And `ct_scope_fingerprint`: the R-CO4 post-conditions, persisted ────────────────────────────
 * Round 1's fingerprint/census checks had exactly one caller — a test. Nothing on the deployed
 * path ran them, and nothing persisted a "before" side, so after a load there was no recoverable
 * baseline to compare against: the checks could only ever be run by hand, which is how the
 * `coa_linkage_findings`/`settings` omission was found. `legacy:scope --apply` now captures the
 * before-picture into this table, and every legacy:* write command compares against it on
 * completion and refuses on a difference.
 */
return new class extends Migration
{
    private const CONNECTION = 'legacy_pilot';

    public function up(): void
    {
        Schema::connection(self::CONNECTION)->create('ct_scope_row', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('database_name', 128);
            $table->string('table_name', 128);
            // The application-database row id this load owns.
            $table->unsignedBigInteger('row_id');
            // Which attribution rule claimed it — 'company_id', 'companies.id',
            // 'companies.user_id', 'branches.user_id', 'agents.branch_id', … — so a reviewer can
            // see WHY a row is owned rather than having to trust that it is.
            $table->string('claimed_by', 64);
            $table->timestamp('claimed_at')->nullable();

            $table->unique(['company_id', 'database_name', 'table_name', 'row_id'], 'ct_scope_row_unique');
            $table->index(['company_id', 'database_name', 'table_name'], 'ct_scope_row_lookup');
        });

        Schema::connection(self::CONNECTION)->create('ct_scope_fingerprint', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('database_name', 128);
            $table->string('table_name', 128);
            // 'pre_load' — captured by `legacy:scope --apply`, never overwritten afterwards.
            $table->string('stage', 16)->default('pre_load');
            // The highest id the table held at capture time. Every later comparison is scoped to
            // `id <= max_id_at_capture`, which is what makes the check honest on a LIVE database:
            // a row that existed before the load and was then inserted-over, updated or deleted is
            // caught (that is the R-CO4 violation), while rows that legitimately APPEAR afterwards
            // — City Travelers' own dev-app writes, which land in the band once `legacy:scope`
            // has raised the counters — do not make the comparison fail for the wrong reason.
            // Those are reported separately, by name and id, as unowned-in-band.
            $table->unsignedBigInteger('max_id_at_capture')->default(0);
            $table->unsignedBigInteger('row_count');
            // BIT_XOR(CRC32(...)) over the rows at or below `max_id_at_capture`. Stored as a string: the
            // value is an unsigned 32-bit fold and is compared, never arithmetic.
            $table->string('content_xor', 32)->nullable();
            $table->timestamp('captured_at')->nullable();

            $table->unique(['company_id', 'database_name', 'table_name', 'stage'], 'ct_scope_fingerprint_unique');
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONNECTION)->dropIfExists('ct_scope_fingerprint');
        Schema::connection(self::CONNECTION)->dropIfExists('ct_scope_row');
    }
};
