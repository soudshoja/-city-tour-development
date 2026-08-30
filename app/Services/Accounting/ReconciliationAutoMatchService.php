<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\JournalEntry;
use App\Models\ReconciliationProposal;
use App\Models\ReconciliationRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G; reconciliation-design.md §9): the internal half of
 * `accounting:reconcile --auto` — "v0 scope: grid + internal proposals only (receipt<->invoice
 * consistency, sub-ledger vs control, clearing roll-forward) ... no statement import." This class
 * is the "nightly job [that] only PROPOSES" (brief's own words, restated at §9(d): "it never posts
 * money"); it writes {@see ReconciliationProposal} rows for a human to approve/reject via
 * {@see ReconciliationProposalService} and NOTHING else — no journal_entries write happens here.
 *
 * Three detectors, one per brief-named internal kind:
 *
 *   1. {@see self::detectClearingRollforward()} — a bank/cash/clearing line whose
 *      `cheque_clearance_date` has passed is self-evidently clearable (the bank has, by
 *      definition, actually processed a cheque once its clearance date arrives) — proposed at
 *      `confidence = exact`.
 *   2. {@see self::detectSubLedgerVsControl()} — an unattributed control-leaf line
 *      (`type_reference_id IS NULL`, the same "untraceable to a party" definition
 *      {@see PeriodCloseChecklistService} already blocks a close on) sitting in the SAME balanced
 *      transaction as another line that DOES carry a party attribution almost certainly belongs to
 *      that same party (a balanced document's legs are never posted for two different customers) —
 *      proposed at `confidence = exact` when exactly one other attributed leg exists in the same
 *      transaction, `suggested` when more than one (ambiguous — a human picks).
 *   3. {@see self::detectReceiptInvoiceConsistency()} — two still-unreconciled bank/cash lines on
 *      the same account, for the same party, for the identical amount, within a short window, is
 *      exactly the shape of a duplicate/un-applied receipt — flagged (never auto-resolved) at
 *      `confidence = suggested` so a human reviews the un-apply advice
 *      {@see ReconciliationCenterService::adviseFor()} already surfaces for this pattern.
 *
 * Idempotent per line: every detector skips a `book_journal_entry_id` that already has a
 * `pending` proposal, so re-running the same day (nightly or Run-now) never duplicates a queue
 * entry — matches every other engine-adjacent idempotency convention in this codebase.
 */
final class ReconciliationAutoMatchService
{
    public function __construct(private readonly ReconciliationCenterService $center) {}

    public function run(int $companyId, string $trigger = ReconciliationRun::TRIGGER_MANUAL, ?int $triggeredBy = null): ReconciliationRun
    {
        $startedAt = now();
        $run = ReconciliationRun::create([
            'company_id' => $companyId,
            'status' => ReconciliationRun::STATUS_RUNNING,
            'trigger' => $trigger,
            'triggered_by' => $triggeredBy,
            'started_at' => $startedAt,
        ]);

        try {
            $created = 0;
            $created += $this->detectClearingRollforward($companyId, $run->id);
            $created += $this->detectSubLedgerVsControl($companyId, $run->id);
            $created += $this->detectReceiptInvoiceConsistency($companyId, $run->id);

            $exactCount = ReconciliationProposal::forCompany($companyId)
                ->where('run_id', $run->id)
                ->where('confidence', ReconciliationProposal::CONFIDENCE_EXACT)
                ->count();

            $exceptionsCount = ReconciliationProposal::forCompany($companyId)
                ->where('run_id', $run->id)
                ->where('confidence', '!=', ReconciliationProposal::CONFIDENCE_EXACT)
                ->count();

            $run->update([
                'status' => ReconciliationRun::STATUS_COMPLETED,
                'finished_at' => now(),
                'proposals_created' => $created,
                'auto_matched_pending' => $exactCount,
                'exceptions_count' => $exceptionsCount,
                'duration_ms' => (int) $startedAt->diffInMilliseconds(now()),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => ReconciliationRun::STATUS_FAILED,
                'finished_at' => now(),
                'duration_ms' => (int) $startedAt->diffInMilliseconds(now()),
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            throw $e;
        }

        return $run->fresh();
    }

    private function alreadyPending(int $bookJournalEntryId): bool
    {
        return ReconciliationProposal::where('book_journal_entry_id', $bookJournalEntryId)
            ->where('status', ReconciliationProposal::STATUS_PENDING)
            ->exists();
    }

    private function detectClearingRollforward(int $companyId, int $runId): int
    {
        $grid = $this->center->grid($companyId, now(), 'day');
        $leafIds = [];
        foreach ($grid['rows'] as $row) {
            if (in_array($row['group'], [ReconciliationCenterService::GROUP_BANK_CASH, ReconciliationCenterService::GROUP_CLEARING], true)) {
                $leafIds = array_merge($leafIds, $row['account_ids']);
            }
        }
        $leafIds = array_values(array_unique($leafIds));

        if ($leafIds === []) {
            return 0;
        }

        $lines = JournalEntry::withoutGlobalScopes()
            ->whereIn('account_id', $leafIds)
            ->whereNull('deleted_at')
            ->where('reconciled', 0)
            ->whereNotNull('cheque_clearance_date')
            ->where('cheque_clearance_date', '<=', now()->toDateString())
            ->get();

        $created = 0;
        foreach ($lines as $line) {
            if ($this->alreadyPending($line->id)) {
                continue;
            }

            ReconciliationProposal::create([
                'company_id' => $companyId,
                'run_id' => $runId,
                'account_id' => $line->account_id,
                'source' => 'internal',
                'kind' => ReconciliationProposal::KIND_CLEARING_ROLLFORWARD,
                'confidence' => ReconciliationProposal::CONFIDENCE_EXACT,
                'book_journal_entry_id' => $line->id,
                'matched_reference' => 'cheque_cleared:'.($line->cheque_no ?? $line->id),
                'amount' => abs((float) $line->debit - (float) $line->credit),
                'difference_amount' => 0,
                'status' => ReconciliationProposal::STATUS_PENDING,
                'period_year' => (int) Carbon::parse($line->posting_date ?? $line->transaction_date)->year,
                'period_month' => (int) Carbon::parse($line->posting_date ?? $line->transaction_date)->month,
            ]);
            $created++;
        }

        return $created;
    }

    private function detectSubLedgerVsControl(int $companyId, int $runId): int
    {
        $grid = $this->center->grid($companyId, now(), 'day');
        $leafIds = [];
        foreach ($grid['rows'] as $row) {
            if ($row['group'] === ReconciliationCenterService::GROUP_CONTROL) {
                $leafIds = array_merge($leafIds, $row['account_ids']);
            }
        }
        $leafIds = array_values(array_unique($leafIds));

        if ($leafIds === []) {
            return 0;
        }

        $unattributed = JournalEntry::withoutGlobalScopes()
            ->whereIn('account_id', $leafIds)
            ->whereNull('deleted_at')
            ->whereNull('type_reference_id')
            ->where(fn ($q) => $q->where('debit', '!=', 0)->orWhere('credit', '!=', 0))
            ->get();

        $created = 0;
        foreach ($unattributed as $line) {
            if ($this->alreadyPending($line->id)) {
                continue;
            }

            $siblingParties = JournalEntry::withoutGlobalScopes()
                ->where('transaction_id', $line->transaction_id)
                ->where('id', '!=', $line->id)
                ->whereNotNull('type_reference_id')
                ->pluck('type_reference_id')
                ->unique()
                ->values();

            if ($siblingParties->isEmpty()) {
                continue; // nothing to propose an attribution against — leave as a plain unmatched item
            }

            ReconciliationProposal::create([
                'company_id' => $companyId,
                'run_id' => $runId,
                'account_id' => $line->account_id,
                'source' => 'internal',
                'kind' => ReconciliationProposal::KIND_SUB_LEDGER_VS_CONTROL,
                'confidence' => $siblingParties->count() === 1 ? ReconciliationProposal::CONFIDENCE_EXACT : ReconciliationProposal::CONFIDENCE_SUGGESTED,
                'book_journal_entry_id' => $line->id,
                'matched_reference' => 'party:'.$siblingParties->first(),
                'amount' => abs((float) $line->debit - (float) $line->credit),
                'difference_amount' => 0,
                'status' => ReconciliationProposal::STATUS_PENDING,
                'period_year' => (int) Carbon::parse($line->posting_date ?? $line->transaction_date)->year,
                'period_month' => (int) Carbon::parse($line->posting_date ?? $line->transaction_date)->month,
            ]);
            $created++;
        }

        return $created;
    }

    private function detectReceiptInvoiceConsistency(int $companyId, int $runId): int
    {
        $grid = $this->center->grid($companyId, now(), 'day');
        $leafIds = [];
        foreach ($grid['rows'] as $row) {
            if ($row['group'] === ReconciliationCenterService::GROUP_BANK_CASH) {
                $leafIds = array_merge($leafIds, $row['account_ids']);
            }
        }
        $leafIds = array_values(array_unique($leafIds));

        if ($leafIds === []) {
            return 0;
        }

        $candidates = JournalEntry::withoutGlobalScopes()
            ->whereIn('account_id', $leafIds)
            ->whereNull('deleted_at')
            ->where('reconciled', 0)
            ->whereNotNull('type_reference_id')
            ->where('debit', '!=', 0)
            ->orderBy('account_id')
            ->orderBy('type_reference_id')
            ->orderBy('debit')
            ->get(['id', 'account_id', 'type_reference_id', 'debit', 'transaction_date', 'posting_date']);

        $created = 0;
        $seen = [];
        foreach ($candidates as $line) {
            $groupKey = $line->account_id.':'.$line->type_reference_id.':'.number_format((float) $line->debit, 3, '.', '');
            if (! isset($seen[$groupKey])) {
                $seen[$groupKey] = $line;
                continue;
            }

            $earlier = $seen[$groupKey];
            $daysApart = abs(Carbon::parse($earlier->posting_date ?? $earlier->transaction_date)
                ->diffInDays(Carbon::parse($line->posting_date ?? $line->transaction_date)));

            if ($daysApart > 14) {
                continue; // too far apart to be a plausible duplicate
            }

            if ($this->alreadyPending($line->id)) {
                continue;
            }

            ReconciliationProposal::create([
                'company_id' => $companyId,
                'run_id' => $runId,
                'account_id' => $line->account_id,
                'source' => 'internal',
                'kind' => ReconciliationProposal::KIND_RECEIPT_INVOICE_CONSISTENCY,
                'confidence' => ReconciliationProposal::CONFIDENCE_SUGGESTED,
                'book_journal_entry_id' => $line->id,
                'matched_journal_entry_id' => $earlier->id,
                'matched_reference' => 'possible_duplicate_receipt',
                'amount' => (float) $line->debit,
                'difference_amount' => 0,
                'status' => ReconciliationProposal::STATUS_PENDING,
                'period_year' => (int) Carbon::parse($line->posting_date ?? $line->transaction_date)->year,
                'period_month' => (int) Carbon::parse($line->posting_date ?? $line->transaction_date)->month,
            ]);
            $created++;
        }

        return $created;
    }
}
