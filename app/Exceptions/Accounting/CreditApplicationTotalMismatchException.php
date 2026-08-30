<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * PROPOSED NAME (W2b build, KEY: draft-builder — design call E1). Thrown by
 * {@see \App\Services\Accounting\CreditApplicationDraftBuilder::build()} when the caller's
 * claimed total does not equal the sum of the debit lines the builder actually posted (i.e.
 * after skipping any zero/negative {@see \App\Services\Accounting\CreditApplicationInput}).
 *
 * This is a loud DATA error, not a balancing decision the builder is allowed to make silently:
 * the credit leg is always built for exactly the posted-debit sum (never the caller's own
 * total), so a discrepancy here means the caller's own bookkeeping (its `$totalAmount` /
 * `$creditApplied` variable) disagrees with the applications it handed the builder. Posting
 * anyway would either produce an unbalanced document or silently substitute the builder's total
 * for the caller's, masking the very data error that needs to surface instead. See the W2
 * orchestrator lead report §7, trap 3.
 */
final class CreditApplicationTotalMismatchException extends PostingException
{
    /** Context-first, message-last — see UnbalancedDocumentException's docblock for why. */
    public function __construct(
        public readonly int $invoiceId,
        public readonly float $callerTotalAmount,
        public readonly float $postedDebitTotal,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Credit application total mismatch for invoice #%d: caller total %s does not equal '
            .'the sum of posted debits %s.',
            $this->invoiceId,
            number_format($this->callerTotalAmount, 3),
            number_format($this->postedDebitTotal, 3)
        ));
    }
}
