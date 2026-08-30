<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * W3a PROFORMA LOCK (owner decision, 2026-08-27 — binding business fact: a proforma invoice
     * in this system is shown to the client as a binding quote, not an internal-only draft).
     *
     * Once set, the invoice's amounts are immutable: converting a sent proforma into an issued
     * invoice must carry the exact same amounts verbatim, and any amount change after send
     * requires a reverse + re-send flow rather than a silent overwrite (enforced at the model
     * layer by Invoice::boot()'s `saving` guard, added alongside this migration, which throws
     * App\Exceptions\Accounting\ProformaAmountLockedException; InvoiceController::markProformaSent()
     * is the code path that sets this column).
     * Nullable timestamp: null means "never sent as a proforma" (the overwhelming majority of
     * historical rows), non-null is both the lock flag and the audit timestamp of when it fired —
     * no separate boolean is needed.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('proforma_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('proforma_sent_at');
        });
    }
};
