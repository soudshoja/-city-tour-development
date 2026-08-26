<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by PostingService::post() (P1 fix round 4, BLOCKING #2 —
 * .planning/P1-VERIFICATION-FINDINGS.json) when a caller's idempotency key collides with a
 * transaction that turns out to be SOFT-DELETED.
 *
 * Before this fix: post() silently returned the dead transaction's own (stale) PostedDocument as
 * if it were a fresh, successful post of the NEW attempt — writing nothing to journal_entries,
 * burning a real document number, and giving the caller no way to tell the two apart. Proven by
 * execution: post key K for 20.000 -> soft-delete that transaction -> post key K again for
 * 999.000 -> returns the dead 20.000 document as "success".
 *
 * A collision with a soft-deleted transaction is NOT the same situation as BLOCKER 5's genuine
 * concurrent-race idempotency (two callers racing to post the SAME real document with the SAME
 * key at the SAME moment — that path correctly returns the winner's LIVE document, unchanged by
 * this fix). This is a caller reusing a key whose original document was deliberately removed from
 * the ledger — reusing it can only mean either a real bug upstream (a payment/task id being
 * recycled) or a genuine "this is now a different real-world event, but happens to share the
 * dead one's key" case that a human, not silent code, must resolve. Either way: THROW, don't
 * guess. A row is also recorded in `idempotency_key_rejections` (see
 * App\Models\IdempotencyKeyRejection) in the SAME call that throws this, on a connection that
 * survives the transaction rollback this exception triggers — see that model's docblock for why.
 */
final class SupersededIdempotencyKeyException extends PostingException
{
    /** Context-first, message-last — see UnbalancedDocumentException's docblock for why. */
    public function __construct(
        public readonly ?int $companyId = null,
        public readonly ?string $idempotencyKey = null,
        public readonly ?int $deadTransactionId = null,
        public readonly ?\DateTimeInterface $deadTransactionDeletedAt = null,
        public readonly ?float $attemptedAmount = null,
        public readonly ?int $adminRecordId = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Idempotency key%s is superseded: it belongs to transaction #%s, which was soft-deleted%s.'
            .' This attempt (amount %s) was rejected, not silently accepted, to avoid resurrecting'
            .' a dead document under a new amount.%s',
            $this->idempotencyKey !== null ? " '{$this->idempotencyKey}'" : '',
            $this->deadTransactionId !== null ? (string) $this->deadTransactionId : 'unknown',
            $this->deadTransactionDeletedAt !== null
                ? ' at '.$this->deadTransactionDeletedAt->format('Y-m-d H:i:s')
                : '',
            $this->attemptedAmount !== null ? number_format($this->attemptedAmount, 3) : 'unknown',
            $this->adminRecordId !== null
                ? " Recorded for admin review as idempotency_key_rejections #{$this->adminRecordId}."
                : ''
        ));
    }
}
