<?php

declare(strict_types=1);

namespace App\Listeners\Accounting;

use App\Events\Accounting\GatewayRefundStatusChanged;
use App\Models\Refund;
use App\Services\Accounting\AccountingLog;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PaymentIdempotencyKey;
use App\Services\Accounting\PostingSeam;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * W4.R (w4-brief.md §4 "Gateway refund: listener for event GatewayRefundStatusChanged ... one
 * txn: PostingSeam Dr 2632|AR / Cr GATEWAY_CLEARING via
 * PaymentIdempotencyKey::forGatewayRefund(gateway, refundId) ... + refunds.status=completed;
 * REJECTED -> void draft. Sync POST never flips status." — see
 * {@see GatewayRefundStatusChanged}'s own docblock for why this event class was DEFINED here
 * rather than found pre-existing).
 *
 * `ShouldQueue`: a webhook-driven completion event, same queueing convention as this codebase's
 * other webhook-adjacent listener ({@see \App\Listeners\ProcessTaskFinancials}) — must not block
 * the HTTP response the gateway is waiting on.
 */
class HandleGatewayRefundStatusChanged implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(GatewayRefundStatusChanged $event): void
    {
        $refund = Refund::find($event->refundId);

        if ($refund === null) {
            Log::error('accounting.gateway_refund_status_changed.refund_not_found', [
                'refund_id' => $event->refundId,
                'gateway' => $event->gateway,
                'gateway_refund_id' => $event->gatewayRefundId,
            ]);

            return;
        }

        if ($event->status === GatewayRefundStatusChanged::STATUS_REJECTED) {
            // "REJECTED -> void the draft" — same rule as the manual reject() action, never a
            // delete (w4-brief.md §4 process decisions).
            $refund->forceFill([
                'status' => Refund::STATUS_REJECTED,
                'rejected_at' => now(),
            ])->save();

            AccountingLog::event('gateway_refund_rejected', [
                'refund_id' => $refund->id,
                'company_id' => $refund->company_id,
                'gateway' => $event->gateway,
                'gateway_refund_id' => $event->gatewayRefundId,
            ]);

            return;
        }

        $companyId = (int) $refund->company_id;
        $idempotencyKey = PaymentIdempotencyKey::forGatewayRefund($event->gateway, $event->gatewayRefundId);
        $amount = round($event->amount, 3);
        $gatewayKey = strtoupper(trim($event->gateway));

        $legacy = function () use ($refund) {
            // No legacy equivalent exists (gateway-refund completion is a genuinely new event
            // this wave introduces) — see RefundPostingService's own class docblock for why a
            // fabricated parallel legacy body is not written for a document that never existed
            // at HEAD. Logged, not silently swallowed.
            Log::warning('accounting.gateway_refund_status_changed.engine_disabled', [
                'refund_id' => $refund->id,
            ]);
        };

        // Fix-loop (Opus review, AR-leg finding): this class's own docblock says the intended
        // shape is "Dr 2632|AR / Cr GATEWAY_CLEARING" -- TWO possible debit legs, not the single
        // always-2632 leg this method shipped with. Which leg is correct depends entirely on what
        // {@see RefundPostingService::postDisposition()} already did (or deliberately did NOT do)
        // for this refund before this async completion arrived:
        //
        //   - Plain Online refund (refund->disposition is NULL): postDisposition()'s method=Online
        //     branch returns null WITHOUT posting anything (see that method's own docblock,
        //     "Online -> nothing posted here"). But postCrnForDetail() (composed in the SAME
        //     RefundPostingService::post() call, always run unconditionally) already reversed the
        //     original sale, which -- per that method's own worked-example docblock -- leaves AR
        //     (RECEIVABLE_CONTROL) parked in a CREDIT balance ("the company now owes this client
        //     back"). THIS completion is what finally pays that receivable-parked balance out via
        //     the gateway, so it must debit AR (RECEIVABLE_CONTROL) to clear it -- the exact same
        //     polarity postDisposition() uses for its own refund_out branch (`Dr AR / Cr
        //     REFUND_PAYOUT_CASH_BANK`), just paid through the gateway's clearing leaf instead of
        //     a cash/bank leaf.
        //   - Wallet payout (refund->disposition === DISPOSITION_CREDIT): postDisposition() DOES
        //     run synchronously whenever a disposition override is present (the `$refund->
        //     disposition ?? match($method)` precedence in postDisposition() reads the override
        //     BEFORE branching on method, so an Online refund with an explicit 'credit' override
        //     already credited 2632 (CLIENT_ADVANCE) and dual-wrote the Credit row at post() time
        //     -- see that method's own docblock). This event, arriving later against the SAME
        //     refund row (refunds.gateway_refund_id is reused for a standing 2632 balance being
        //     cashed out through the gateway, not a fresh CRN), is therefore paying out an
        //     EXISTING 2632 credit balance, not clearing AR -- it must debit CLIENT_ADVANCE
        //     (2632) instead, exactly the polarity ClientController::refundProcess()'s own
        //     standing client-credit payout uses (`Dr CLIENT_ADVANCE / Cr [payout leaf]`).
        //
        // Both branches are decided purely from $refund->disposition, already loaded from the DB
        // above via Refund::find($event->refundId) -- no additive payload field is needed: the
        // refund row IS the "source context" the disposition/state machine already tracks, and
        // extending the event payload with a redundant flag would only invite the two to drift.
        $isWalletPayout = $refund->disposition === Refund::DISPOSITION_CREDIT;

        DB::transaction(function () use ($refund, $companyId, $idempotencyKey, $amount, $gatewayKey, $legacy, $isWalletPayout) {
            $debitLine = $isWalletPayout
                ? new LineDraft(
                    purposeCode: 'CLIENT_ADVANCE',
                    accountId: null,
                    side: 'debit',
                    amount: $amount,
                    currency: config('accounting.engine.base_currency'),
                    originalAmount: $amount,
                    exchangeRate: 1.0,
                    transactionType: 'REFUND_GATEWAY_PAYOUT_ADVANCE',
                    description: 'Gateway refund payout (wallet balance): '.$refund->refund_number,
                    ledgerType: 'liability',
                )
                : new LineDraft(
                    purposeCode: 'RECEIVABLE_CONTROL',
                    accountId: null,
                    side: 'debit',
                    amount: $amount,
                    currency: config('accounting.engine.base_currency'),
                    originalAmount: $amount,
                    exchangeRate: 1.0,
                    transactionType: 'REFUND_GATEWAY_PAYOUT_AR',
                    description: 'Gateway refund payout (AR clearance): '.$refund->refund_number,
                    ledgerType: 'receivable',
                );

            $draft = new DocumentDraft(
                companyId: $companyId,
                branchId: (int) ($refund->branch_id ?? 0),
                docType: 'PV',
                subType: 'REFUND_GW_PAYOUT', // transactions.sub_type is varchar(16)
                docDate: now(),
                narration: 'Gateway refund completed for '.$refund->refund_number,
                lines: [
                    $debitLine,
                    new LineDraft(
                        purposeCode: "GATEWAY_CLEARING_{$gatewayKey}",
                        accountId: null,
                        side: 'credit',
                        amount: $amount,
                        currency: config('accounting.engine.base_currency'),
                        originalAmount: $amount,
                        exchangeRate: 1.0,
                        transactionType: 'REFUND_GATEWAY_PAYOUT_CLEARING',
                        description: 'Gateway refund payout: '.$refund->refund_number,
                        ledgerType: 'asset',
                    ),
                ],
                idempotencyKey: $idempotencyKey,
            );

            app(PostingSeam::class)->post($draft, $legacy, 'refund.gateway_payout');

            // AR branch: this completion IS the refund-out settlement (the receivable-parked
            // credit the CRN left behind is now paid out), so disposition is stamped refund_out,
            // matching postDisposition()'s own refund_out disposition value for a synchronous
            // Cash/Bank equivalent. Wallet branch: disposition was ALREADY 'credit' (set when the
            // original 2632 credit was posted) and must be left untouched -- this event only pays
            // that existing balance out, it does not re-classify how the refund was disposed.
            $refund->forceFill(array_filter([
                'status' => Refund::STATUS_COMPLETED,
                'disposition' => $isWalletPayout ? null : Refund::DISPOSITION_REFUND_OUT,
                'completed_at' => now(),
            ], static fn ($value) => $value !== null))->save();
        });

        AccountingLog::event('gateway_refund_completed', [
            'refund_id' => $refund->id,
            'company_id' => $refund->company_id,
            'gateway' => $event->gateway,
            'gateway_refund_id' => $event->gatewayRefundId,
            'idempotency_key' => $idempotencyKey,
        ]);
    }
}
