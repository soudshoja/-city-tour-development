<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * PROPOSED NAME (W2c build, residual R-g fix). Thrown by
 * {@see \App\Services\Accounting\CreditApplicationDraftBuilder::build()} when the invoice's
 * `agent->branch_id` chain cannot be resolved to a real, positive branch id.
 *
 * Guards against silently casting a null/unresolved branch chain to the integer sentinel `0`
 * (the old `(int) $invoice->agent?->branch_id` shape) and posting anyway:
 * `PostingService::post()`'s own document-numbering step (`SequenceService::next()`) reserves a
 * real sequence value keyed by `(docType, companyId, branchId, docDate)` — a `0` branch id would
 * silently reserve numbers under a phantom "branch 0" sequence rather than failing loudly at the
 * one place that already knows the chain is broken. See W2b lead report §5, residual R-g.
 */
final class UnresolvedBranchException extends PostingException
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly int $companyId,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Cannot resolve a branch for invoice #%d (company #%d): $invoice->agent->branch_id is '
            .'null or not a positive integer. Refusing to post a credit-application document with '
            .'the 0 sentinel.',
            $this->invoiceId,
            $this->companyId
        ));
    }
}
