<?php

declare(strict_types=1);

namespace App\Services\Accounting\Replay;

/**
 * CT-A3 wave 2 (W2-1). What {@see ReplaySource::replay()} decided about ONE source row, in the
 * one shape `accounting:replay` tallies and prints.
 *
 * Four statuses, deliberately distinct — CT-A2's ad-hoc script conflated the last two and its
 * report had to reconstruct the difference by hand afterwards:
 *
 *   - `would_post` — dry-run only: the draft built cleanly and a real run would post it.
 *   - `posted`     — a real run posted it (or PostingService's idempotency short-circuit returned
 *                    the document that was already there; {@see self::$alreadyPosted} says which).
 *   - `skipped`    — nothing to post, and that is CORRECT: the row carries no money, the feeder's
 *                    own rule says it is not due, or its document already exists. Never an error.
 *   - `refused`    — the engine (or the feeder) rejected it. Always carries a reason and the
 *                    exception class, so the command can group the refusals by cause with ids
 *                    rather than printing 3,000 lines.
 */
final class ReplayOutcome
{
    public const WOULD_POST = 'would_post';

    public const POSTED = 'posted';

    public const SKIPPED = 'skipped';

    public const REFUSED = 'refused';

    private function __construct(
        public readonly string $status,
        public readonly string $reason,
        public readonly int|string|null $sourceId = null,
        public readonly ?float $amount = null,
        public readonly ?int $transactionId = null,
        public readonly ?string $exceptionClass = null,
        public readonly bool $alreadyPosted = false,
    ) {}

    public static function wouldPost(int|string|null $sourceId, ?float $amount = null): self
    {
        return new self(self::WOULD_POST, 'would_post', $sourceId, $amount);
    }

    public static function posted(int|string|null $sourceId, ?int $transactionId, ?float $amount = null, bool $alreadyPosted = false): self
    {
        return new self(self::POSTED, $alreadyPosted ? 'already_posted' : 'posted', $sourceId, $amount, $transactionId, null, $alreadyPosted);
    }

    public static function skipped(int|string|null $sourceId, string $reason, ?float $amount = null): self
    {
        return new self(self::SKIPPED, $reason, $sourceId, $amount);
    }

    public static function refused(int|string|null $sourceId, string $reason, ?\Throwable $e = null, ?float $amount = null): self
    {
        return new self(self::REFUSED, $reason, $sourceId, $amount, null, $e !== null ? get_class($e) : null);
    }

    public function isRefused(): bool
    {
        return $this->status === self::REFUSED;
    }

    /** The bucket label refusals and skips are grouped under in the command's summary. */
    public function bucket(): string
    {
        return $this->exceptionClass !== null
            ? class_basename($this->exceptionClass)
            : $this->reason;
    }
}
