<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by PostingService::post() (pipeline step 3d — R1 / BUG-C2) when a line targets an
 * account that is not a leaf: it has children and/or is_group === true.
 */
final class NonLeafAccountException extends PostingException
{
    /** Context-first, message-last — see UnbalancedDocumentException's docblock for why. */
    public function __construct(
        public readonly ?int $accountId = null,
        public readonly ?string $accountName = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Account%s is not a leaf (it has children and/or is_group=true) and cannot receive a posting line.',
            $this->accountId !== null
                ? " #{$this->accountId}".($this->accountName !== null ? " ({$this->accountName})" : '')
                : ''
        ));
    }
}
