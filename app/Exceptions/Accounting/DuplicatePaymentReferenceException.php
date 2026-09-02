<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * PROPOSED NAME (W2 build, D3 fix). Thrown by PostingService::post() when the header INSERT
 * collides on the payment/reference-type dedup unique index rather than on the idempotency-key
 * index isIdempotencyKeyRaceViolation()/findByIdempotencyKey() already recover from.
 *
 * UPDATED (transactions-cutover split): that dedup index used to be a single raw two-column
 * unique on `(payment_id, reference_type)` — `transactions_payment_id_reference_type_unique`,
 * from P0 hotfix migration 2026_08_24_000001_add_dedup_unique_indexes_for_payment_race_hotfixes.php.
 * Real CT-shaped data violated that raw pairing 2,007 times over its history (98% a legitimate
 * notification-row-vs-ledger-row design collision in the MyFatoorah top-up flow, not double
 * money — only 2 genuine double-posts, both already Suspense-balanced), so it could never be
 * applied as-is. The index is now `transactions_payment_ref_dedup_key_unique`, over a nullable
 * STORED generated column that is NULL for any row dated before 2026-09-01 00:00:00 (all
 * historic rows exempt) and `payment_id:reference_type` for any row on or after it (full
 * enforcement) — see migration 2026_08_24_000002_add_post_cutover_dedup_key_to_transactions_table.php
 * for the complete reasoning. PostingService::isPaymentReferenceTypeRaceViolation() recognises
 * EITHER index name, so this exception is still thrown correctly whether the colliding
 * environment carries the old raw index (a prior partial migration state) or the new
 * generated-column one.
 *
 * This is NOT the same failure as an idempotency-key retry, and there is no safe automatic
 * recovery for it the way there is for that one: it means some OTHER document — same
 * payment_id, same reference_type, a DIFFERENT idempotency_key — already occupies this exact
 * (payment_id, reference_type) slot. Silently returning that other document (the way a genuine
 * idempotency-key race does) would hand the caller the wrong document, so this must surface
 * loudly as a typed PostingException instead of the raw QueryException that used to escape here.
 *
 * The two known ways to reach this:
 *   - A feeder bug: two distinct DocumentDrafts built for the same payment under the same
 *     reference_type, each with its own (different) idempotency key.
 *   - PostingService::repost()'s $old still occupying the slot: reverse() intentionally never
 *     clears payment_id off the ORIGINAL document being reversed (it only stamps posting_status
 *     and reversal_of_transaction_id — see DocumentDraft::$paymentId's own docblock, "D3"). A
 *     caller that reverses $old some other way and then calls post() directly with the same
 *     payment_id (rather than going through repost(), which forces the replacement draft's
 *     payment_id to NULL specifically to avoid this) will still hit this exception.
 */
final class DuplicatePaymentReferenceException extends PostingException
{
    /** Context-first, message-last — see UnbalancedDocumentException's docblock for why. */
    public function __construct(
        public readonly ?int $paymentId,
        public readonly ?string $referenceType,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            "A transaction already exists for payment_id=%s / reference_type='%s' (payment/"
                .'reference-type dedup unique index — transactions_payment_ref_dedup_key_unique, '
                .'or the pre-cutover raw transactions_payment_id_reference_type_unique) — this is '
                .'a different document than the one being posted, not a retry of it.',
            $this->paymentId !== null ? (string) $this->paymentId : 'NULL',
            $this->referenceType ?? 'unknown'
        ));
    }
}
