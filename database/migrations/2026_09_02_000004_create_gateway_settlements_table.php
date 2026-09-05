<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * accounting-builds T7 (Lane D, M4): one row per real gateway payout batch — "the gateway
 * actually paid this much (net) into our bank, having collected this much (gross) and charged
 * this much (fee)". {@see \App\Services\Accounting\GatewaySettlementService::record()} is the
 * ONLY writer of this table's status/transaction_id columns (never a raw `->update()` from a
 * controller/command) — same draft-then-post discipline
 * {@see \App\Services\Accounting\ReconciliationFixDraftService} already established for this
 * phase's other "record something, then attempt to post it through the seam" feeders.
 *
 * `payout_reference` unique PER (company_id, gateway) — not globally unique — because the same
 * literal payout reference string from two DIFFERENT gateways (or, in principle, two different
 * companies sharing a gateway account) must never collide into one row (MP-7-3: keying on
 * (gateway, date) alone would collapse two same-day payouts; keying on the full
 * (company, gateway, payout_reference) tuple is the actual idempotency key
 * {@see \App\Services\Accounting\GatewaySettlementService} derives its posting idempotency key
 * from, one-to-one).
 *
 * `status`: 'recorded' = persisted, not yet posted (either the engine was OFF for this company at
 * record time, or posting hasn't been retried yet); 'posted' = a real GWS document exists,
 * `transaction_id` set; 'failed' = record() itself refused (e.g. gross != net + fee) — a failed
 * row is never silently dropped, it stays visible in the reconciliation-center list as an
 * exception (L: "never silent absorption").
 *
 * `raw` (nullable json): the CSV/API payload this settlement was recorded from, INCLUDING an
 * optional `payout_items` array (one entry per underlying charge in the payout batch — auth_no
 * or payment_reference, amount, date) that
 * {@see \App\Services\Accounting\ReconciliationAutoMatchService::run()}'s gateway-settlement-item
 * detector reads to propose per-line matches against unreconciled GATEWAY_CLEARING_{gw} lines.
 * Purely informational for a 'manual' source with no CSV — null in that case.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('gateway', 20); // one of config('accounting.purpose_codes.gateways') keys, e.g. 'TAP'
            $table->string('settlement_channel', 24)->nullable(); // journal_entries.settlement_channel
            // shape this settlement's own posted lines carry (L12), e.g. 'tap:knet'
            $table->string('payout_reference', 100);
            $table->date('payout_date');
            $table->decimal('gross', 15, 3);
            $table->decimal('fee', 15, 3);
            $table->decimal('net', 15, 3);
            $table->decimal('recognised_fee', 15, 3)->default(0);
            // the fee ALREADY posted to GATEWAY_FEE_EXPENSE_{gw} at receipt time for the
            // charges behind this payout — 0 when unknown (L11/Q1's documented fallback; see
            // GatewaySettlementService's own docblock for why 0 is the safe default and how a
            // caller who genuinely knows this figure supplies it).
            $table->string('currency', 3)->default('KWD');
            $table->unsignedBigInteger('bank_account_id'); // validated under the Bank Accounts group (assertUnderBankGroup)
            $table->enum('status', ['recorded', 'posted', 'failed'])->default('recorded');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('imported_by')->nullable();
            $table->enum('source', ['manual', 'csv', 'api'])->default('manual');
            $table->json('raw')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'gateway', 'payout_reference'], 'gateway_settlements_company_gateway_payout_ref_unique');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'gateway', 'payout_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_settlements');
    }
};
