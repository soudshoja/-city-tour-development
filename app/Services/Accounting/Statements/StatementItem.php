<?php

declare(strict_types=1);

namespace App\Services\Accounting\Statements;

use Illuminate\Support\Carbon;

/**
 * P2.5.H (p2_5-brief.md §P2.5.H). One line on a client/supplier/agent statement — either a
 * "document" (an invoice, a supplier charge line, an agent settlement) that can be open or
 * settled, or an "unapplied" receipt/credit sitting against the party with nothing consumed yet.
 *
 * Deliberately a plain, immutable DTO with no model dependency — every {@see PartyStatementSource}
 * implementation maps its own underlying rows (Invoice+PaymentApplication, AgentSettlement,
 * journal_entries) onto this SAME shape, so {@see \App\Services\Accounting\StatementService} and
 * every view/PDF template built on it never need to know which source produced a given row.
 */
final class StatementItem
{
    public function __construct(
        public readonly string $kind,               // 'document' | 'unapplied'
        public readonly string $documentType,        // e.g. 'invoice', 'supplier_charge', 'agent_settlement', 'receipt', 'credit'
        public readonly ?int $documentId,
        public readonly string $documentNumber,
        public readonly Carbon $documentDate,
        public readonly ?Carbon $dueDate,
        public readonly float $amount,               // original document amount (always positive)
        public readonly float $settledAmount,        // amount already settled/applied (always >= 0, <= amount)
        public readonly string $description = '',
    ) {}

    public function outstanding(): float
    {
        return round($this->amount - $this->settledAmount, 3);
    }

    public function isSettled(float $tolerance): bool
    {
        return $this->outstanding() <= $tolerance;
    }

    /**
     * Age in whole days between the document date and the statement's "as of" date. Never
     * negative (a document dated after the statement date -- should not happen given callers
     * already filter to documentDate <= asOf, but this stays defensive rather than emitting a
     * negative bucket index).
     */
    public function ageInDays(Carbon $asOf): int
    {
        // Carbon::diffInDays() defaults to an absolute (unsigned) distance, so this is safe even
        // if a caller ever passes an asOf date before documentDate -- it never goes negative.
        return (int) $this->documentDate->copy()->startOfDay()->diffInDays($asOf->copy()->startOfDay());
    }

    /**
     * Which of the 4 configured buckets (30/60/90/120) this item's age falls into, 0-indexed;
     * returns the count of buckets (i.e. one past the last index) for the open-ended "120+" tier.
     *
     * @param  int[]  $buckets  Ageing bucket upper bounds in days, ascending.
     */
    public function ageingBucketIndex(Carbon $asOf, array $buckets): int
    {
        $age = $this->ageInDays($asOf);

        foreach ($buckets as $index => $upperBound) {
            if ($age <= $upperBound) {
                return $index;
            }
        }

        return count($buckets);
    }

    public function toArray(Carbon $asOf, array $buckets): array
    {
        return [
            'kind' => $this->kind,
            'document_type' => $this->documentType,
            'document_id' => $this->documentId,
            'document_number' => $this->documentNumber,
            'document_date' => $this->documentDate->toDateString(),
            'due_date' => $this->dueDate?->toDateString(),
            'amount' => $this->amount,
            'settled_amount' => $this->settledAmount,
            'outstanding' => $this->outstanding(),
            'description' => $this->description,
            'age_days' => $this->ageInDays($asOf),
            'ageing_bucket_index' => $this->ageingBucketIndex($asOf, $buckets),
        ];
    }
}
