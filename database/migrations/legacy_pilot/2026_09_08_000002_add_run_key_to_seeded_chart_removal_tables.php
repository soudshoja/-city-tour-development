<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * legacy-ledger-pilot LP1d — make `--replace-seeded`'s audit trail run-keyed.
 *
 * THE DEFECT (staging run #4a, owner item 3). `seeded_chart_removed` and
 * `seeded_chart_fk_nulled` live on the `legacy_pilot` connection, but
 * SeededDefaultChartGuard::replaceSeededChart()'s DB::transaction() wraps
 * only the DEFAULT connection. Run #4a's first attempt crashed on the
 * delete-order bug and rolled back — correctly, on the default connection:
 * all 186 accounts survived. Its legacy_pilot writes did not roll back,
 * because that connection was never in the transaction. The successful
 * second attempt then appended its own 186 rows, leaving
 * `seeded_chart_removed` holding 372 rows describing 186 accounts.
 *
 * Nothing about the ledger was wrong; the AUDIT TRAIL was, which for a verb
 * whose whole justification is "deleting more rows demands a better audit
 * trail" is the part that must not drift.
 *
 * THE FIX has two halves and this migration is the schema half:
 *
 *   run_key   Every removal attempt stamps one opaque key on every row it
 *             writes to either table. Rows are therefore attributable to a
 *             single attempt, a re-run under the same key is idempotent
 *             (the guard purges that key before re-inserting), and a
 *             half-finished attempt's rows can be identified and cleaned
 *             instead of being indistinguishable from real history.
 *
 *   seeded_chart_removal_run
 *             One row per attempt: when it started, when (or whether) it
 *             completed, how many accounts and FKs it claims, and the
 *             `accounts.level` inconsistencies found on the chart it was
 *             asked to remove. `completed_at IS NULL` is exactly "an attempt
 *             that did not finish" — the state run #4a had no way to record.
 *
 * The other half is in the guard: the legacy_pilot rows are now written
 * AFTER the default connection commits, so a failed attempt writes nothing
 * at all rather than writing an orphan trail.
 *
 * This table is also a FENCE MIGRATION MARKER (see
 * Tests\Concerns\PreparesLegacyPilotFence::FENCE_MIGRATION_MARKERS) — a
 * column-only migration would be invisible to that probe and would leave
 * every fence database migrated before LP1d permanently one migration
 * behind.
 *
 * RUN ONLY via:
 *   php artisan migrate --path=database/migrations/legacy_pilot --database=legacy_pilot
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('legacy_pilot')->create('seeded_chart_removal_run', function (Blueprint $table) {
            $table->id();
            $table->string('run_key')->unique();
            $table->unsignedBigInteger('company_id');
            $table->unsignedInteger('accounts_removed')->default(0);
            $table->unsignedInteger('fks_nulled')->default(0);
            // The accounts.level inconsistencies observed on the chart this
            // attempt removed (child.level != parent.level + 1). Reported, not
            // fixed: the delete order no longer trusts `level` at all, but a
            // wrong level is still a data defect in whatever wrote the row.
            $table->text('level_inconsistencies')->nullable();
            $table->timestamp('started_at');
            // NULL = this attempt never finished. Its run_key's rows in the
            // two audit tables are therefore not real history.
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'started_at']);
        });

        Schema::connection('legacy_pilot')->table('seeded_chart_removed', function (Blueprint $table) {
            $table->string('run_key')->nullable()->after('company_id');
            $table->index(['company_id', 'run_key']);
        });

        Schema::connection('legacy_pilot')->table('seeded_chart_fk_nulled', function (Blueprint $table) {
            $table->string('run_key')->nullable()->after('company_id');
            $table->index(['company_id', 'run_key']);
        });
    }

    public function down(): void
    {
        Schema::connection('legacy_pilot')->table('seeded_chart_fk_nulled', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'run_key']);
            $table->dropColumn('run_key');
        });

        Schema::connection('legacy_pilot')->table('seeded_chart_removed', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'run_key']);
            $table->dropColumn('run_key');
        });

        Schema::connection('legacy_pilot')->dropIfExists('seeded_chart_removal_run');
    }
};
