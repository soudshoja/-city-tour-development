<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * CT-A3 **R3-3** — VERIFY-CT-A3-STACK-R2 §3.2 finding **V3**. A credit note asked to credit MORE
 * than the sale it is raised against is still carrying.
 *
 * ── Why this exists ─────────────────────────────────────────────────────────────────────────────
 * `RefundController::store()` validated `tasks.*.original_invoice_price` and
 * `tasks.*.total_refund_to_client` as bare `['required','numeric']` — no `max`, no relation to the
 * sale. CT-A3 R2-1 then computed the sale's outstanding sell from its live document family and
 * wrote `partial_credit_requested` into the audit log, deliberately not enforcing it, because the
 * open half of the ruling was `credited < outstanding` (a partial credit). The verification found
 * the SAME gap in the other direction, and that one is not a deferred ruling — it is an operator
 * typo with money attached.
 *
 * Measured (`Rv2ProbeTest::test_probe2_…`) on a live sale of 100 with a credit of 500 requested:
 *
 *   | leaf                     | end       |
 *   |--------------------------|----------:|
 *   | `SERVICE_REVENUE`/flight |     0.000 |  (the CRN reversed exactly the live 100 — correct)
 *   | `RECEIVABLE_CONTROL`     | **+500.000** |
 *   | `CLIENT_ADVANCE`         | **−500.000** |
 *
 * The client's NET position is still zero, so the trial balance is right and this is a reclass
 * rather than a P&L loss. But both control leaves are inflated by the over-credit — which is
 * exactly what AR ageing, the client statement, the credit-limit check and every process that
 * reads one leaf without the other will report.
 *
 * ── The rule, and its owner ruling ──────────────────────────────────────────────────────────────
 * The default is **REFUSE**, tagged to owner ruling **R-CT6** (the clamp question: whether an
 * over-refund is a genuine commercial outcome or a data defect). Refusing is the reversible
 * choice — an operator who meant it can correct the figure and re-submit, whereas a silently
 * clamped or silently accepted over-credit is money already on the ledger. If the owner rules that
 * clamping is right, this exception becomes the point where the clamp is applied; nothing else has
 * to move.
 *
 * `outstanding` is the R2-1 computation, unchanged: Cr − Dr on the revenue lines of every sale
 * document for this invoice detail that is STILL LIVE, read from posted rows and never from a
 * stored balance column.
 */
final class RefundExceedsOutstandingException extends PostingException
{
    /** Context-first, message-last — see UnbalancedDocumentException's docblock for why. */
    public function __construct(
        public readonly int $refundDetailId,
        public readonly int $invoiceDetailId,
        public readonly float $requestedCredit,
        public readonly float $outstandingSell,
        public readonly ?int $taskId = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'refund_detail #%d (invoice_detail #%d, task #%s): a credit of %s was requested against an '
            .'outstanding sell of %s — %s more than the sale is still carrying. REFUSED (owner ruling '
            .'R-CT6 default: refuse, do not clamp). Crediting it would leave the receivable control and '
            .'the client-advance leaf each inflated by the difference while the client\'s net position '
            .'stays zero, so no trial balance and no aggregate check would ever show it. Correct the '
            .'refund amount to at most the outstanding sell, or reverse whatever already credited this '
            .'sale, and re-submit.',
            $this->refundDetailId,
            $this->invoiceDetailId,
            $this->taskId === null ? 'null' : (string) $this->taskId,
            number_format($this->requestedCredit, 3),
            number_format($this->outstandingSell, 3),
            number_format($this->requestedCredit - $this->outstandingSell, 3)
        ));
    }
}
