<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * CT-A3 R2-1 — VERIFY-CT-A3-STACK-R1 §3.2 D5. A refund detail whose invoice detail has NOTHING
 * LEFT TO CREDIT: every sale document for it is already reversed, or its legacy sale has already
 * been credited by an earlier refund.
 *
 * ── Why this exists ─────────────────────────────────────────────────────────────────────────────
 * `RefundPostingService::postCrnForDetail()` used to look the sale up by the fixed key
 * `invoice-detail:{id}:sale` in ANY status and hand it to `PostingService::reverse()`, which
 * short-circuits on any pre-existing reversal and returns that reversal. The credit note then
 * returned SOMEBODY ELSE'S reversal as its own — a success message, a balanced trial balance, and
 * no revenue reversed at all. Three ordinary sequences reached it: a refund after an invoice price
 * correction (the live sale is under a revision key, the corpse still owns the base key), a second
 * refund on the same task, and a refund raised against the original task of a reissue.
 *
 * Money impact, as the report worked it: sale 100, penalty 20, fee 5, credit disposition — revenue
 * stays +100 un-reversed while AR reaches +200 against a client who was credited 75. The COST half
 * was relieved correctly the whole time, because the supplier-credit document reads the LEDGER,
 * which is exactly why no supplier-side or AP-control check could see it.
 *
 * ── What it means when you see it ───────────────────────────────────────────────────────────────
 * NOT "the refund is malformed". It means the sale this credit note was raised against is no
 * longer carrying anything: it was reversed by a reissue, or a previous refund already credited
 * it. Refusing is the only honest answer — the alternative is a second credit note for money that
 * was already given back once.
 *
 * Partial refunds are NOT expressible today: the credit note is a FULL reversal of the live sale.
 * "What a second refund on an already-refunded task should mean" is therefore an owner ruling
 * (verify report §7 item 2), not a patch — until it is made, this refuses and names the position
 * it measured so the operator can see what is actually on the ledger.
 */
final class NothingOutstandingToCreditException extends PostingException
{
    /** Context-first, message-last — see UnbalancedDocumentException's docblock for why. */
    public function __construct(
        public readonly int $refundDetailId,
        public readonly int $invoiceDetailId,
        public readonly ?int $taskId = null,
        public readonly int $reversedSaleDocuments = 0,
        public readonly ?string $reason = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'refund_detail #%d (invoice_detail #%d, task #%s): there is nothing outstanding to credit — %s. '
            .'Refusing rather than returning an existing reversal as this refund\'s credit note: the sale '
            .'has already been taken off the ledger, and crediting it twice would give the same money back '
            .'twice. %d sale document(s) for this invoice detail are already reversed.',
            $this->refundDetailId,
            $this->invoiceDetailId,
            $this->taskId === null ? 'null' : (string) $this->taskId,
            $this->reason ?? 'no live sale document remains',
            $this->reversedSaleDocuments
        ));
    }
}
