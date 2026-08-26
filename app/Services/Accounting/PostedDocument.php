<?php

namespace App\Services\Accounting;

use App\Models\JournalEntry;
use App\Models\Transaction;

/**
 * Result of a successful {@see PostingService::post()} / reverse() / repost() call.
 *
 * Contract: 11-technical-implementation-plan.md ("file 11") §P1.1, L242-246:
 *
 *   `public readonly Transaction $transaction; /** @var JournalEntry[] *\/ public readonly array $lines;`
 * — a property-only sketch, no constructor shown.
 *
 * DEVIATIONS (both additive, documented):
 *   1. A constructor is added — readonly properties must be initialized somewhere, and file 11's own
 *      convention elsewhere in this contract (DocumentDraft, LineDraft) is constructor-promoted
 *      readonly properties, so this class follows the same house pattern.
 *   2. A third readonly property, `$documentNumber`, is added. The mission task description for this
 *      file (not file 11) calls it "the return value (transaction + lines + formatted number)" —
 *      i.e. the caller-facing result is expected to surface SequenceService::next()'s formatted
 *      document number directly. Rather than making every caller reach into
 *      `$posted->transaction->reference_number` and rely on knowing that storage decision (an Open
 *      Question this build resolves — see PostingService), this convenience field mirrors that same
 *      string at construction time. It changes nothing about what is persisted.
 */
final class PostedDocument
{
    /** @param JournalEntry[] $lines */
    public function __construct(
        public readonly Transaction $transaction,
        public readonly array $lines,
        public readonly ?string $documentNumber = null,
    ) {}
}
