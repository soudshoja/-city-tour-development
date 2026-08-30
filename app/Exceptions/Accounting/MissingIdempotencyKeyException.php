<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by {@see \App\Services\Accounting\PostingSeam::post()} when a feeder's `DocumentDraft`
 * is routed onto the LIVE engine path (both `config('accounting.engine.enabled')` and the
 * resolved company's `posting_engine_enabled` are true) but carries no idempotency key.
 *
 * `PostingService::post()` itself tolerates a null `$draft->idempotencyKey` (its own step 1 just
 * skips the idempotency lookup and always posts a fresh document) — that is deliberately
 * permissive at the engine layer, which has no way to know whether a given caller has some other
 * de-duplication mechanism of its own. The SEAM is a stricter gate one layer up: every feeder
 * that reaches the engine through it is, by construction, wrapping a real external event (a
 * gateway callback, a webhook, a queued job) that can retry or double-fire — R3's whole design
 * (route-to-legacy) exists to make that engine path safe, and an engine-path post with no
 * idempotency key is exactly the double-post bug the key exists to prevent. Refusing here, before
 * `PostingService::post()` is ever called, means nothing is written and no document number is
 * burned — see `PostingSeam::post()`'s own docblock for why this check runs strictly before the
 * `PostingService::post()` call.
 */
final class MissingIdempotencyKeyException extends PostingException
{
    /** Context-first, message-last — matches every sibling exception in this namespace. */
    public function __construct(
        public readonly ?string $feederKey = null,
        public readonly ?int $companyId = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'PostingSeam: feeder \'%s\' attempted to post on the LIVE engine path for company #%s '
            .'with no idempotency key. A feeder that reaches the posting engine through the seam '
            .'without one is a double-post bug, not a valid draft — set DocumentDraft::$idempotencyKey '
            .'to a stable identifier for the real-world event (gateway transaction id, payment id, '
            .'task id) before calling PostingSeam::post().',
            $this->feederKey ?? 'unknown',
            $this->companyId !== null ? (string) $this->companyId : 'unresolved'
        ));
    }
}
