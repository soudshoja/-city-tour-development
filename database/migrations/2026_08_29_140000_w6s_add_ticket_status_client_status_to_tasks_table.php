<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * W6.S item (2) (w6-brief.md "Consolidation + fixes" / ct-void-map.md §Model). Additive: two new
 * columns that split the single legacy `tasks.status` into its two independent legs (see
 * w6-brief.md "## Model"):
 *   - ticket_status: the SUPPLIER side (issued|void|refunded|reissued|emd) -- reversed via the
 *     engine's reverse() targeting (task_id, doc_type).
 *   - client_status: the CLIENT side (open|credited|refunded|rebilled) -- driven by CRN/DBN
 *     documents against the invoice that carries the task.
 * `tasks.status` is KEPT for legacy readers (Traps: "tasks.status enum stays for legacy readers").
 * Backfilled from the existing `status` column in up(); nothing downstream reads these two new
 * columns yet in this sub-wave (W6.V/W6.R/W6.I own the actual ticket/client-leg posting) -- this
 * migration only creates and backfills them so later sub-waves have a stable column to write to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('ticket_status', ['issued', 'void', 'refunded', 'reissued', 'emd'])
                ->nullable()
                ->after('status');
            $table->enum('client_status', ['open', 'credited', 'refunded', 'rebilled'])
                ->nullable()
                ->after('ticket_status');
        });

        // Best-effort backfill from the legacy `status` column. Deliberately conservative: only
        // maps the cases ct-void-map.md's own ticket_status vocabulary can represent unambiguously
        // from a single legacy status value; every task.status value it cannot cleanly place stays
        // NULL rather than guessing (a NULL ticket_status/client_status is a legitimate "not yet
        // classified" state -- nothing today reads these columns, so leaving them null is safe).
        DB::table('tasks')->where('status', 'issued')->update([
            'ticket_status' => 'issued',
            'client_status' => 'open',
        ]);
        DB::table('tasks')->where('status', 'reissued')->update([
            'ticket_status' => 'reissued',
            'client_status' => 'open',
        ]);
        DB::table('tasks')->where('status', 'emd')->update([
            'ticket_status' => 'emd',
            'client_status' => 'open',
        ]);
        DB::table('tasks')->where('status', 'void')->update([
            'ticket_status' => 'void',
            'client_status' => 'credited',
        ]);
        DB::table('tasks')->whereIn('status', ['refund', 'refunded'])->update([
            'ticket_status' => 'refunded',
            'client_status' => 'refunded',
        ]);
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['ticket_status', 'client_status']);
        });
    }
};
