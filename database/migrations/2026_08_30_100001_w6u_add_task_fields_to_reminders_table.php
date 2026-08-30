<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * W6.U "Reminders" (owner addition, 2026-08-28). Additive to the EXISTING `reminders` table (see
 * w6-brief.md's own "facts found" section: `target_type` enum today is `invoice, payment, client,
 * agent`; no `reminder_kind` column, no `task_id` column exist anywhere in the codebase).
 *
 * - `reminder_kind` (nullable string): existing rows stay NULL ("general", the brief's own words),
 *   only new `reminder:generate-deadlines` rows ever set it to `ticketing_deadline`.
 * - `task_id` (nullable FK to `tasks`): same nullable-FK convention `invoice_id`/`payment_id`
 *   already use on this table.
 * - `target_type` widened to add `'task'` -- a MySQL enum widen (redefine the column with every
 *   existing value plus the one new value; no existing row's value changes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->string('reminder_kind', 40)->nullable()->after('target_type');
            $table->foreignId('task_id')->nullable()->after('payment_id')->constrained('tasks')->nullOnDelete();
        });

        // Additive enum widen -- every existing value kept, 'task' appended. Existing row values
        // are untouched (MODIFY COLUMN does not rewrite data, only the column's own type
        // definition).
        DB::statement("ALTER TABLE reminders MODIFY COLUMN target_type ENUM('invoice', 'payment', 'client', 'agent', 'task') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE reminders MODIFY COLUMN target_type ENUM('invoice', 'payment', 'client', 'agent') NOT NULL");

        Schema::table('reminders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_id');
            $table->dropColumn('reminder_kind');
        });
    }
};
