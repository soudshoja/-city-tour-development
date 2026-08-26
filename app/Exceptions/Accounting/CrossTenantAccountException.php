<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by PostingService::post() (pipeline step 2 — T7.1) when a line's explicit accountId
 * belongs to a different company than the document being posted. Not literally named in file 11's
 * pipeline text, but required by acceptance test `post_rejects_cross_tenant_account`; file 11's
 * own Open Questions section recommends exactly this class, following the naming pattern already
 * used for the other five per-rule exceptions.
 */
final class CrossTenantAccountException extends PostingException
{
    /** Context-first, message-last — see UnbalancedDocumentException's docblock for why. */
    public function __construct(
        public readonly ?int $accountId = null,
        public readonly ?int $accountCompanyId = null,
        public readonly ?int $expectedCompanyId = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Account%s belongs to a different company than the document being posted.',
            $this->accountId !== null ? " #{$this->accountId}" : ''
        ));
    }
}
