<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * W7.H (.planning/accounting-waves/w7/w7-brief.md §W7.H). PRE-EXISTING DEFECT FIX, found while
 * cutting app/Modules/DotwAI/Services/AccountingService.php over to the posting engine (not a new
 * design decision): `invoice_details.task_id` was `bigint(20) unsigned NOT NULL` with a real FK to
 * `tasks.id` and no default -- meaning NEITHER of AccountingService's two public methods
 * (`createCancellationEntries()` for a booking never routed through the Task-bound
 * ConfirmBookingAfterPaymentJob path, `createAutoInvoiceForDeadline()`) could ever have completed
 * a real `InvoiceDetail::create()` call, on EITHER the legacy or the engine path -- both always
 * omitted `task_id` (there is genuinely no Task row for a DOTW booking invoiced through this raw,
 * non-Task path; see that class's own docblock for when this path is reached vs. the Task-bound
 * one). This was never a working INSERT to begin with, so loosening the constraint is a
 * prerequisite unblock, not a behaviour change for any row that already exists (every EXISTING
 * invoice_details row was necessarily created with a real, non-null task_id -- nothing about this
 * migration touches or reinterprets those rows).
 *
 * Additive only: widens a constraint (NOT NULL -> NULLable), does not drop the FK (a non-null
 * task_id is still validated against `tasks.id` exactly as before -- only NULL is now permitted).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_details')) {
            return;
        }

        DB::statement('ALTER TABLE `invoice_details` MODIFY `task_id` bigint(20) unsigned DEFAULT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoice_details')) {
            return;
        }

        // Irreversible if any row was written with task_id NULL by this fix's own new callers --
        // deleting those rows here would be a destructive migration:down(), which this codebase's
        // convention (additive migrations, never drop/recreate -- see w4-brief.md's own "refunds
        // table rebuilt 3x -- write additive migration, never drop/recreate" trap) explicitly
        // avoids. Left as a documented no-op rollback rather than a silent data-loss statement.
    }
};
