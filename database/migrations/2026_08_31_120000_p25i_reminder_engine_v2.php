<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P2.5.I "Reminder engine v2" (p2_5-brief.md §P2.5.I; doc 22 §16.7). Additive-only against the
 * EXISTING `reminders` table -- verified 2026-08-29 baseline, re-verified 2026-08-31 against the
 * current working tree before writing this migration:
 *
 *   - `reminder_kind` varchar(40) nullable ALREADY EXISTS (W6.U,
 *     2026_08_30_100001_w6u_add_task_fields_to_reminders_table.php) -- wider than the brief's own
 *     varchar(32) ask, so nothing to add here; existing rows keep their value untouched.
 *   - `task_id` nullable FK to `tasks` ALREADY EXISTS (same W6.U migration).
 *   - `target_type` ALREADY includes `'task'` (same W6.U migration's enum widen). No further widen
 *     needed -- the brief's own target_type list (invoice|payment|client|agent|task) is already
 *     the live enum.
 *   - `number_of_reminder` ALREADY EXISTS (2025_12_23_131655_add_number_of_reminder_to_reminders_table.php).
 *
 * What is genuinely new here, per the brief's remaining list:
 *   - `channel` enum('whatsapp','email','both') default 'whatsapp'.
 *   - `error_message` text nullable -- markAsFailed() already WRITES this key today (SendReminders
 *     ::markAsFailed()) but the column does not exist, so Eloquent's fillable guard silently drops
 *     it (App\Models\Reminder::$fillable has never listed it) -- this migration + the model fillable
 *     addition together make that write land for the first time.
 *   - `dedupe_key` varchar(120) nullable UNIQUE -- generator idempotency key (kind + target +
 *     offset/date-slot, built by the generator layer, see App\Services\Reminders\*Generator).
 *   - `company_id` nullable FK to `companies` -- did not exist on this table at all (verified:
 *     grep across every reminders migration finds no company_id column). Backfilled best-effort
 *     from each row's agent's branch->company_id where resolvable; left null where not (e.g. an
 *     orphaned/legacy row with no live agent) -- a nullable column, so this is safe and does not
 *     block any existing read path (every pre-existing query on this table filters by agent_id/
 *     client_id, never company_id).
 *   - `status` enum widened to add `'cancelled'` -- P2.5.I's "stale pending -> cancelled" repair
 *     and the new group `number_of_reminder` cap enforcement both need a terminal state distinct
 *     from `sent`/`failed` (neither of which means "we deliberately did not send this").
 *
 * Indexes added for the new generator/sender query shapes: (status, scheduled_at) -- the exact
 * predicate SendReminders' due-reminders query already filters on; (reminder_kind, status) -- the
 * new reminder:generate dispatcher's per-kind stale-scan and the settings/log screen's per-kind
 * filter both key off this pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->enum('channel', ['whatsapp', 'email', 'both'])->default('whatsapp')->after('reminder_kind');
            $table->text('error_message')->nullable()->after('status');
            $table->string('dedupe_key', 120)->nullable()->unique()->after('error_message');
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
            $table->index(['status', 'scheduled_at'], 'reminders_status_scheduled_at_idx');
            $table->index(['reminder_kind', 'status'], 'reminders_kind_status_idx');
        });

        // Additive enum widen -- every existing value kept, 'cancelled' appended. Existing row
        // values are untouched (MODIFY COLUMN redefines the column's type only, never rewrites
        // data) -- same technique and same safety argument as the target_type widen in
        // 2026_08_30_100001_w6u_add_task_fields_to_reminders_table.php.
        DB::statement("ALTER TABLE reminders MODIFY COLUMN status ENUM('sent', 'pending', 'failed', 'cancelled') NOT NULL DEFAULT 'pending'");

        // Best-effort backfill: company_id via agent -> branch -> company_id. Left NULL where the
        // agent (or its branch) no longer resolves -- nullable column, no read path depends on it
        // being populated for a pre-existing row.
        DB::statement(
            'UPDATE reminders r '
            .'JOIN agents a ON a.id = r.agent_id '
            .'JOIN branches b ON b.id = a.branch_id '
            .'SET r.company_id = b.company_id '
            .'WHERE r.company_id IS NULL'
        );
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropIndex('reminders_status_scheduled_at_idx');
            $table->dropIndex('reminders_kind_status_idx');
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['channel', 'error_message', 'dedupe_key']);
        });

        DB::statement("ALTER TABLE reminders MODIFY COLUMN status ENUM('sent', 'pending', 'failed') NOT NULL DEFAULT 'pending'");
    }
};
