<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by PostingService::reverse() (P1 fix round — HIGH finding: "reverse() cannot handle the
 * legacy corruption P3's repair plan points it at") when an original journal_entries row does not
 * have the canonical shape reverse()'s side-inference requires: exactly one of debit/credit strictly
 * positive, neither negative.
 *
 * Two real shapes in prod violate this and previously either threw a confusing, unrelated exception
 * or silently mis-reversed:
 *   - Sign-error rows (verification check 8.1, e.g. JE 15201/15202 both debit = -101.160): a negative
 *     debit fails `debit > 0`, so the old code treated the line as a credit and picked up the (also
 *     wrong) credit magnitude.
 *   - Rows with BOTH debit and credit populated: the old code only ever reversed the debit leg,
 *     silently dropping the credit leg, so the reversal never nets the original to zero.
 *
 * File 11 §P3 explicitly repairs the 8.1 pairs via "PostingService::reverse semantics", so reverse()
 * must fail loudly and name the exact row rather than mis-reverse it — P3's own dedicated repair path
 * (not plain reverse()) is the intended fix for a row this exception names.
 */
final class NonCanonicalJournalLineException extends PostingException
{
    /** Context-first, message-last — see UnbalancedDocumentException's docblock for why. */
    public function __construct(
        public readonly int $transactionId,
        public readonly int $journalEntryId,
        public readonly float $debit,
        public readonly float $credit,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Cannot reverse transaction #%d: journal_entries.id=%d has a non-canonical debit/credit '
            .'shape (debit=%.3f, credit=%.3f) — reverse() requires exactly one of debit/credit to be '
            .'strictly positive and neither negative. This is the legacy sign-error / both-legs-set '
            .'shape (verification check 8.1); route it through the dedicated P3 correction path '
            .'instead of reverse().',
            $this->transactionId,
            $this->journalEntryId,
            $this->debit,
            $this->credit
        ));
    }
}
