<?php

declare(strict_types=1);

namespace App\Services\Accounting;

/**
 * CT-A3 wave 2 (W2-2), R-CT3 pattern. The verdict {@see ReceiptPostingRule::decide()} returns for
 * ONE receipt status: whether a receipt document belongs on the ledger at that status, whether the
 * status instead UNDOES a document already posted, and always the reason.
 *
 * Shaped deliberately like {@see SupplierPayableDecision} — same two booleans, same
 * never-both-true invariant, same `toLogContext()`. Wave 1 said the supplier-payable rule "is the
 * pattern wave 2 (receipts, refunds, who-to-pay) follows"; following it means the shape too, so an
 * operator reading `accounting.receipt.*` log lines does not have to learn a second vocabulary.
 */
final class ReceiptPostingDecision
{
    public function __construct(
        public readonly bool $shouldPost,
        public readonly bool $shouldReverse,
        /** The lower-cased `invoice_receipts.status` the decision was made against. */
        public readonly string $status,
        /**
         * One of: 'status_posts', 'status_reverses', 'status_is_draft', 'status_not_configured'.
         */
        public readonly string $reason,
    ) {}

    /** @return array<string, string|bool> log-ready context, same shape on every branch. */
    public function toLogContext(): array
    {
        return [
            'should_post' => $this->shouldPost,
            'should_reverse' => $this->shouldReverse,
            'receipt_status' => $this->status,
            'reason' => $this->reason,
        ];
    }
}
