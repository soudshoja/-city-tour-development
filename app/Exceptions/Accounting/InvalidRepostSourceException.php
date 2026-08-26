<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by PostingService::repost() (P1 fix round — MEDIUM finding: "repost() does not assert the
 * replacement draft belongs to the same company as the document being reversed") in two situations,
 * both checked before either half of the reverse-then-post pair runs:
 *
 *   - `$old->company_id` does not match `$new->companyId` — without this check, a caller that
 *     resolves the wrong `$old` (trivially possible during P2 when a controller looks a transaction
 *     up by a non-tenant-scoped key) reverses one company's document and posts a different company's
 *     replacement in one atomic unit; both halves individually pass their own tenant checks, so
 *     nothing else catches it.
 *   - `$old->posting_status` is not 'posted' — repost()/reverse() semantics assume the document being
 *     replaced is a live, posted document; reposting over an already-reversed, void, or draft row has
 *     no defined meaning.
 */
final class InvalidRepostSourceException extends PostingException
{
    /** Context-first, message-last — see UnbalancedDocumentException's docblock for why. */
    public function __construct(
        public readonly int $transactionId,
        public readonly ?int $oldCompanyId = null,
        public readonly ?int $newCompanyId = null,
        public readonly ?string $postingStatus = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Transaction #%d cannot be reposted%s%s.',
            $this->transactionId,
            ($this->oldCompanyId !== null && $this->newCompanyId !== null && $this->oldCompanyId !== $this->newCompanyId)
                ? sprintf(': it belongs to company_id=%d but the replacement draft is for company_id=%d', $this->oldCompanyId, $this->newCompanyId)
                : '',
            $this->postingStatus !== null
                ? sprintf(": posting_status is '%s', expected 'posted'", $this->postingStatus)
                : ''
        ));
    }
}
