<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by PostingService::post() (pipeline step 3c — blueprint 02 §3 / MF-11) when a resolved
 * journal_entries line would carry both a debit and a credit amount, or neither. Exactly one side
 * must be non-zero.
 */
final class OneSidedLineException extends PostingException
{
    /** Context-first, message-last — see UnbalancedDocumentException's docblock for why. */
    public function __construct(
        public readonly ?float $debit = null,
        public readonly ?float $credit = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'A journal line must be debit XOR credit%s.',
            ($this->debit !== null || $this->credit !== null)
                ? sprintf('; got debit=%.3f credit=%.3f', $this->debit ?? 0.0, $this->credit ?? 0.0)
                : ''
        ));
    }
}
