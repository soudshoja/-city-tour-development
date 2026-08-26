<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by PostingService::post() (pipeline step 4 — header rule R2 / BUG-C1 check 1.1) when
 * abs(Σdebit − Σcredit) >= 0.0005 for a document about to be written. Nothing is written; the
 * whole DB::transaction() rolls back.
 */
final class UnbalancedDocumentException extends PostingException
{
    /**
     * Context-first, message-last — matches every call site in
     * PostingService::post()/reverse() (e.g. `new UnbalancedDocumentException($totalDebit,
     * $totalCredit)` and `new UnbalancedDocumentException(0.0, 0.0, 'message')`), and the same
     * convention already used by ProtectedLineException/UnmappedPurposeException.
     */
    public function __construct(
        public readonly ?float $totalDebit = null,
        public readonly ?float $totalCredit = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Document is unbalanced: total debit %s does not equal total credit %s.',
            $this->totalDebit !== null ? number_format($this->totalDebit, 3) : 'unknown',
            $this->totalCredit !== null ? number_format($this->totalCredit, 3) : 'unknown'
        ));
    }
}
