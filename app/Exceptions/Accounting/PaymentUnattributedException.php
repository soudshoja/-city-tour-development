<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * PROPOSED NAME (W2.1 build, residual 13 fix). Thrown by PaymentController::publicReceiptNotice()
 * when `$payment->agent?->branch?->company_id` cannot be resolved -- payments.agent_id is
 * nullable()->nullOnDelete(), so deleting/unlinking an Agent leaves this chain broken on an
 * otherwise-live Payment.
 *
 * Deliberately NOT a PostingException subclass: this is a receipt/notification-routing failure,
 * not a document-posting-pipeline violation, and it fires from a method with no ledger side
 * effects of its own. Mirrors the existing "D5" guard pattern already used inline in
 * handleTapCallback/handleKnetResponse (`Log::critical('accounting.payment_unattributed', ...)`
 * before skipping the ledger write) -- this class exists because publicReceiptNotice() runs
 * BEFORE both of those guards on every call, so the unattributed chain used to crash there first,
 * swallowed generically by the caller's own outer catch(Throwable), and the critical log for the
 * case it was built for never fired.
 */
final class PaymentUnattributedException extends \RuntimeException
{
    public function __construct(public readonly ?int $paymentId, ?string $message = null)
    {
        parent::__construct($message ?? sprintf(
            'Payment id=%s cannot be attributed to a company: agent->branch->company_id chain unresolved.',
            $this->paymentId !== null ? (string) $this->paymentId : 'NULL'
        ));
    }
}
