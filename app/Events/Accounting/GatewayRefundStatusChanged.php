<?php

declare(strict_types=1);

namespace App\Events\Accounting;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * W4.R (w4-brief.md §4 "Gateway refund: listener for event GatewayRefundStatusChanged (built by
 * the other terminal -- find it; if the event class does not exist yet, define the listener
 * against the agreed name/signature and mark it in your report)").
 *
 * SEARCHED for an existing definition before writing this: `MyFatoorahController`,
 * `app/Support/PaymentGateway/MyFatoorah.php`, and a codebase-wide grep for
 * "GatewayRefundStatusChanged" turned up NOTHING outside this wave's own planning documents
 * (`w4-brief.md`, `target-spec.md`) — the other terminal building the MyFatoorah refund-webhook
 * side (`MYFATOORAH-KUWAIT-PLAN.md` §0.1/§0.3, referenced by target-spec.md §B) had not landed
 * this class as of this build. Defined here against the AGREED name and the exact fields
 * target-spec.md §B's "Gateway refund event" paragraph specifies the handler needs:
 * gateway name, the gateway's own refund id (the key {@see \App\Services\Accounting\
 * PaymentIdempotencyKey::forGatewayRefund()} is keyed on), the amount, the refund this
 * completion belongs to, and the terminal status (`completed` or `rejected` — target-spec.md
 * §B: "On REJECTED it voids the draft instead").
 *
 * **FLAGGED FOR THE OTHER TERMINAL**: if their own webhook handler already defines (or later
 * defines) a `GatewayRefundStatusChanged` event with a different shape, this class and
 * {@see \App\Listeners\Accounting\HandleGatewayRefundStatusChanged} must be reconciled against
 * theirs, not the other way around — the payment/webhook side is their subsystem's source of
 * truth for what actually fired.
 */
class GatewayRefundStatusChanged
{
    use Dispatchable, SerializesModels;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public function __construct(
        public readonly string $gateway,
        public readonly string $gatewayRefundId,
        public readonly float $amount,
        public readonly int $refundId,
        public readonly string $status,
    ) {}
}
