<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by PostingService::post() (pipeline step 3f — R3) when a line's currency equals the
 * company's base currency but originalAmount !== amount and/or exchangeRate !== 1.0.
 */
final class FcConsistencyException extends PostingException
{
    /** Context-first, message-last — see UnbalancedDocumentException's docblock for why. */
    public function __construct(
        public readonly ?string $currency = null,
        public readonly ?string $baseCurrency = null,
        public readonly ?float $amount = null,
        public readonly ?float $originalAmount = null,
        public readonly ?float $exchangeRate = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Line currency%s fails FC consistency: originalAmount/exchangeRate do not match the base-currency rule.',
            $this->currency !== null ? " ({$this->currency})" : ''
        ));
    }
}
