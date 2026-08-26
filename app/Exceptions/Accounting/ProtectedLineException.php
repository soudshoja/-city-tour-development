<?php

declare(strict_types=1);

namespace App\Exceptions\Accounting;

/**
 * Thrown by PostingService::reverse() (blueprint 02 §5 — "Protect applied/reconciled lines")
 * when the posted document being reversed has any line that is reconciled or settled, and the
 * caller did not pass force=true.
 *
 * Not literally named in file 11's pipeline text (only described in prose), but required to
 * implement the documented "refuses (throws) when any line is reconciled != 0 or settled unless
 * force=true" behavior and the `reverse_refuses_reconciled_lines_without_force` acceptance test —
 * added here so the PostingService builder has it ready, following the same additive pattern file
 * 11's Open Questions section recommends for CrossTenantAccountException.
 */
final class ProtectedLineException extends PostingException
{
    public function __construct(
        public readonly int $transactionId,
        public readonly ?int $journalEntryId = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'Transaction #%d has reconciled and/or settled lines and cannot be reversed without force=true.%s',
            $this->transactionId,
            $this->journalEntryId !== null ? " (journal_entries.id={$this->journalEntryId})" : ''
        ));
    }
}
