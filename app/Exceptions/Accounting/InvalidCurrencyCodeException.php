<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by PostingService::post() (P1 fix round 4, MEDIUM finding) when a line's `currency` is
 * longer than the tightest of the two legacy columns it gets written into verbatim at step 8:
 * `journal_entries.currency` (varchar(10)) AND `journal_entries.original_currency` (varchar(3)) —
 * so the real limit is 3, the smaller of the two. LineDraft (a different fixer's file, out of this
 * round's scope) applies no length validation of its own, so without this guard an over-long
 * currency string reached the INSERT unchecked and MySQL, running in strict mode, rejected the
 * whole statement with a raw "Data too long for column 'original_currency'" driver error instead
 * of a typed, catchable PostingException — after steps 1-7 had already run, including the
 * document-number reservation (step 6).
 */
final class InvalidCurrencyCodeException extends PostingException
{
    /** Context-first, message-last — see UnbalancedDocumentException's docblock for why. */
    public function __construct(
        public readonly ?string $currency = null,
        public readonly ?int $maxLength = null,
        public readonly ?string $context = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            "Line currency '%s' is %d characters, exceeding the %d-character limit shared by "
            .'journal_entries.currency and .original_currency%s.',
            $this->currency ?? '',
            $this->currency !== null ? mb_strlen($this->currency) : 0,
            $this->maxLength ?? 3,
            $this->context !== null ? " ({$this->context})" : ''
        ));
    }
}
