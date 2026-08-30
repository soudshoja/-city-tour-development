<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W6.V (w6-brief.md "Model": "invoices flip status via engine hooks (cancelled when all tasks
 * void, partial → 'partial refund' otherwise)"). Additive-only widen of `invoices.status`:
 * verified NOT present before this migration (grepped every existing invoices-status migration --
 * 2026_01_14_163556_add_partial_refund_status_to_invoices_table.php is the most recent, and its
 * own enum list -- 'paid'/'unpaid'/'partial'/'paid by refund'/'refunded'/'partial refund' -- has
 * no 'cancelled' value). `App\Enums\InvoiceStatus` gains a matching `CANCELLED = 'cancelled'`
 * case in the same commit (see that enum file) so `TaskStatusService::refreshInvoiceStatusAfterVoid()`
 * can write it without ever hand-typing the bare string.
 *
 * Every existing value is kept verbatim; down() reverts to the exact baseline list the migration
 * above this one left in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('status', [
                'paid',
                'unpaid',
                'partial',
                'paid by refund',
                'refunded',
                'partial refund',
                'cancelled',
            ])->default('unpaid')->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('status', [
                'paid',
                'unpaid',
                'partial',
                'paid by refund',
                'refunded',
                'partial refund',
            ])->default('unpaid')->change();
        });
    }
};
