<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2.5.F fix-round (2026-08-30, per verify findings): the Log Center's client/agent filter
 * (§P2.5.F owner refinement — "client / agent / supplier (resolved through the subject or the
 * linked transaction)") resolves through `transactions.entity_type`/`entity_id` when a document's
 * header names the client/agent directly (entity_type IN ('client','agent')). That pair had no
 * covering index — every other filter column named in the brief ("add the indexes the filters
 * need ... each filter must be covered by an index or a documented reason why not") already does.
 *
 * `invoices.client_id` / `invoices.agent_id` (the other client/agent resolution path, used when a
 * transaction's `invoice_id` links to an invoice, or when the audit row's own subject_type is
 * 'invoice') are each already covered by the FK index Laravel's `foreignId()->constrained()`
 * creates in the original create_invoices_table migration — no new index needed there.
 *
 * `tasks.supplier_id` (the supplier resolution path — resolved only through the subject, when
 * subject_type='task') is already covered as the leading column of the existing composite unique
 * index `tasks_supplier_id_reference_unique` (`unique(['supplier_id', 'reference'])` in
 * create_tasks_table) — MySQL can use a composite index's leftmost prefix for an equality lookup on
 * `supplier_id` alone, so no new index is needed there either.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['entity_type', 'entity_id'], 'transactions_entity_type_entity_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_entity_type_entity_id_index');
        });
    }
};
