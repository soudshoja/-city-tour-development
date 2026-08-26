<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by PostingService::post() (pipeline step 3e — blueprint 02 §3 / MF-11) when a line
 * targets an account flagged accounts.disabled.
 */
final class FrozenAccountException extends PostingException
{
    /** Context-first, message-last — see UnbalancedDocumentException's docblock for why. */
    public function __construct(
        public readonly ?int $accountId = null,
        public readonly ?string $accountName = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Account%s is disabled and cannot receive a posting line.',
            $this->accountId !== null
                ? " #{$this->accountId}".($this->accountName !== null ? " ({$this->accountName})" : '')
                : ''
        ));
    }
}
