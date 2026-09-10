<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * CT-A3 wave 2 (W2-3). A `refund_details` row whose task was never invoiced: there is no
 * `invoice_details` row, therefore no sale document, therefore nothing for the credit note to
 * reverse.
 *
 * ── Why this is its own named exception ─────────────────────────────────────────────────────────
 * It was a bare `\RuntimeException`, which is correct behaviour (refusing loudly beats inventing a
 * sale) but a useless label: `accounting:replay` groups refusals by exception class, so on the
 * City Travelers scratch database **26 of the 33 refunds** collapsed into one bucket called
 * `RuntimeException` alongside anything else that happened to throw the same class. A refusal an
 * operator has to read a message to identify is a refusal they will misread at scale.
 *
 * ── What it actually means, and why it is not a defect ──────────────────────────────────────────
 * CT-A1 §2.1 recorded that all 33 `refunds` rows carry `posted_at = NULL` and that the refund
 * document table has no ledger link at all; CT-A1 §0 measured 5,495 of 8,706 issued tasks (63%)
 * with no invoice. A refund of a task that was never invoiced is therefore the ORDINARY shape on
 * this data, not an error in the refund.
 *
 * Such a task's supplier cost sits in `1430 Unbilled Supplier Cost` as an issuance accrual, and it
 * is settled by the accrual path, not by this document — {@see \App\Services\Accounting\
 * TaskIssuancePayableService::settleAccrualOnRefund()}, which consults the same
 * {@see \App\Services\Accounting\SupplierRefundRule} and either reverses the accrual (the supplier
 * refunds) or reclassifies it onto `SUPPLIER_REFUND_LOSS` (it does not). The client side of such a
 * refund has no receivable to reverse either, because no invoice was ever raised.
 *
 * So: refuse this document, name the reason precisely, and point at the path that does carry it.
 */
final class RefundWithoutInvoiceDetailException extends PostingException
{
    public function __construct(
        public readonly int $refundDetailId,
        public readonly ?int $taskId,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'refund_detail #%d (task #%s) has no invoice_detail — the task was never invoiced, so '
            .'there is no sale document to reverse and no receivable to credit. This is not a '
            .'defect in the refund: an uninvoiced task\'s supplier cost sits in 1430 as an issuance '
            .'accrual and is settled by TaskIssuancePayableService::settleAccrualOnRefund() under '
            .'the same supplier-refund rule, not by a credit note.',
            $this->refundDetailId,
            $this->taskId === null ? 'null' : (string) $this->taskId
        ));
    }
}
