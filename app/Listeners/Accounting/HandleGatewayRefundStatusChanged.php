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

        DB::transaction(function () use ($refund, $companyId, $idempotencyKey, $amount, $gatewayKey, $legacy) {
            $draft = new DocumentDraft(
                companyId: $companyId,
                branchId: (int) ($refund->branch_id ?? 0),
                docType: 'PV',
                subType: 'REFUND_GW_PAYOUT', // transactions.sub_type is varchar(16)
                docDate: now(),
                narration: 'Gateway refund completed for '.$refund->refund_number,
                lines: [
                    new LineDraft(
                        purposeCode: 'CLIENT_ADVANCE',
                        accountId: null,
                        side: 'debit',
                        amount: $amount,
                        currency: config('accounting.engine.base_currency'),
                        originalAmount: $amount,
                        exchangeRate: 1.0,
                        transactionType: 'REFUND_GATEWAY_PAYOUT_ADVANCE',
                        description: 'Gateway refund payout: '.$refund->refund_number,
                        ledgerType: 'liability',
                    ),
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

            $refund->forceFill([
                'status' => Refund::STATUS_COMPLETED,
                'disposition' => Refund::DISPOSITION_REFUND_OUT,
                'completed_at' => now(),
            ])->save();
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
