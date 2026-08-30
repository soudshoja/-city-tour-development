<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * P2.5.E (p2_5-brief.md §P2.5.E; period-lock-design.md §8.2 — dependency-aware unlock): thrown by
 * {@see \App\Http\Traits\Lockable::unlock()} when {@see \App\Http\Traits\Lockable::unlockBlockers()}
 * returns a non-empty list — i.e. at least one downstream node in the invoice -> applications/
 * allocations -> receipts -> reconciled bank lines -> period chain (or a reversal/repost document
 * pointing at the record) is itself locked, reconciled, or sits inside a closed accounting period.
 *
 * `$blockers` is the SAME structured array {@see \App\Services\Accounting\UnlockDependencyResolver}
 * builds — `[{type, id, number, status, url, hint, log_center_url}, ...]` — carried on the
 * exception so both the console/service caller (which gets this object) and the HTTP layer (which
 * catches it and re-serializes `$blockers` verbatim as the JSON response's `blockers[]`, per the
 * owner refinement: "the JSON refusal response carries the same blockers[] so the API/UI stay in
 * sync") render the identical list — never two separately-maintained copies.
 *
 * Deliberately NOT a {@see PostingException} subclass: like {@see PeriodDependencyBlockedException}
 * (the Layer-2 sibling of this Layer-1 check), this is a record-CONTROL violation raised by the
 * unlock action itself, never something the posting pipeline throws while writing a document.
 */
final class UnlockDependencyBlockedException extends \RuntimeException
{
    /**
     * @param  array<int, array{type: string, id: int, number: ?string, status: string, url: ?string, hint: string, log_center_url: ?string}>  $blockers
     */
    public function __construct(
        public readonly string $subjectType,
        public readonly int $subjectId,
        public readonly array $blockers,
    ) {
        parent::__construct(sprintf(
            'Cannot unlock %s #%d: %d dependency/dependencies must be resolved first (%s).',
            $subjectType,
            $subjectId,
            count($blockers),
            implode(', ', array_map(
                static fn (array $b): string => sprintf('%s#%s [%s]', $b['type'], (string) $b['id'], $b['status']),
                $blockers
            ))
        ));
    }
}
