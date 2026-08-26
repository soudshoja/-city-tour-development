<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by PostingService::post() (pipeline step 3b — blueprint 02 §3 / MF-11) when a line's
 * amount is negative. Every line amount must be >= 0; direction is carried by `side`, not sign.
 */
final class NonNegativeAmountException extends PostingException
{
    /** Context-first, message-last — see UnbalancedDocumentException's docblock for why. */
    public function __construct(
        public readonly ?float $amount = null,
        public readonly ?string $context = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Line amount must be >= 0%s%s.',
            $this->amount !== null ? sprintf(', got %.6f', $this->amount) : '',
            $this->context !== null ? " ({$this->context})" : ''
        ));
    }
}
