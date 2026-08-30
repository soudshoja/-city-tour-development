<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W6.I "Importer contract" item 3 (w6-brief.md; importer-status-contract.md Table 4 cross-cutting
 * row; Accounting Gap/22-plan-amendments.md §16.1 "Import idempotency"). Additive-only:
 * `tasks.import_key` = `ticket_no+airline_code+issue_date`, fallback `reference+passenger_name+
 * issue_date` when no ticket number exists (EMD, some bulk sources) -- computed by
 * `Task::computeImportKey()` at creation time (see that model's own docblock). Nullable and
 * BACKFILLED NULL for every existing row -- the brief is explicit that this key "only governs new
 * imports going forward, no retroactive computation" (importer-status-contract.md's own note).
 *
 * Unique per company (`(company_id, import_key)`), not globally unique -- two different companies
 * can legitimately import the same ticket number from the same airline on the same date (they are
 * different tenants' data, never the same real-world duplicate). MySQL's unique index tolerates an
 * unlimited number of NULL `import_key` rows (NULL is never considered equal to NULL for a unique
 * constraint), so every pre-existing/legacy row stays exactly as it is today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('import_key', 191)->nullable()->after('original_task_id');
            $table->unique(['company_id', 'import_key'], 'tasks_company_import_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique('tasks_company_import_key_unique');
            $table->dropColumn('import_key');
        });
    }
};
