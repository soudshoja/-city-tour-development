<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\Accounting\ReconciliationPeriodLockedException;
use App\Models\AccountingPeriod;
use App\Models\BankStatementImportLine;
use App\Models\JournalEntry;
use App\Models\ReconciliationProposal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G): approve/reject a PROPOSALS-panel row, and manual match/unmatch
 * (drill-down panels 1 and 4). Every action here is a {@see ReconciliationCenterService}'s grid
 * consumer, not the grid itself.
 *
 * Permission gate is delegated to {@see ReconciliationService::assertCanReconcile()} — the SAME
 * `accounting.reconcile` dual-check (Spatie permission OR admin/company/accountant role tier)
 * W5.X already established for exactly this class of action, rather than a second copy of the
 * same rule.
 *
 * "Approving a proposal ... sets reconciled=1 + reconciled_ref_id and locks the line" (brief) —
 * `reconciled = 1` IS the lock: {@see PostingService::reverse()} already refuses a reconciled
 * original without `$force` (period-lock-design.md §1, Layer 3), so flipping this flag is the
 * real, pre-existing enforcement mechanism, not a separate lock column this class invents.
 */
final class ReconciliationProposalService
{
    public function __construct(
        private readonly ReconciliationService $reconciliation,
        private readonly ReconciliationCenterService $center,
    ) {}

    public function approve(ReconciliationProposal $proposal, ?User $actor): ReconciliationProposal
    {
        $this->reconciliation->assertCanReconcile($actor);

        if ($proposal->status !== ReconciliationProposal::STATUS_PENDING) {
            throw new \RuntimeException('Only a pending proposal can be approved.');
        }

        DB::transaction(function () use ($proposal, $actor) {
            $book = JournalEntry::withoutGlobalScopes()->findOrFail($proposal->book_journal_entry_id);

            // PHASE GATE (accounting-builds, cross-lane): this method is the ONE place every
            // proposal kind flips `journal_entries.reconciled`, and it is deliberately
            // kind-agnostic. That was safe while every detector was internal and each ran over a
            // disjoint slice of the ledger; it stopped being safe the moment this phase added two
            // independent, externally-fed kinds (`supplier_statement` from lane E,
            // `gateway_settlement` from lane D — and `bank_statement` from lane H next) whose
            // candidate sets are derived from files a human uploads, not from one shared query.
            //
            // Nothing upstream prevents two PENDING proposals of DIFFERENT kinds naming the same
            // `book_journal_entry_id`: there is no unique index on that column (only a plain index,
            // 2026_08_30_170001_p25g_create_reconciliation_proposals_table.php), lane E's own
            // claim check (SupplierStatementMatcher::liveClaimOn()) was scoped to its own kind
            // until this same fix widened it, and manualMatch() below creates-and-approves without
            // consulting pending proposals at all. Without this guard the SECOND approval simply
            // overwrote `reconciled_ref_id`/`reconciled_date`/`reconciled_amount` written by the
            // first — silently, with an audit row that recorded the overwrite as a normal
            // reconcile. One ledger line would then appear settled by two different external
            // documents, and only the later one would be traceable.
            //
            // A line is "claimed" at reconciled = 1 (an approved proposal, or a manual match) and
            // at reconciled = 2 (BankPaymentController's own fast path, which ReconciliationService
            // already refuses to touch — see its docblock). Refusing loudly is correct here rather
            // than silently skipping: the operator asked to approve a match the ledger cannot
            // honour, and the competing claim is named in the message so they can go look at it.
            if ((int) $book->reconciled !== 0) {
                throw new \RuntimeException(sprintf(
                    'Journal entry #%d is already reconciled (state %d, ref %s); proposal #%d (kind %s) cannot claim it. '
                    .'Unmatch the existing claim first if this proposal is the correct one.',
                    (int) $book->id,
                    (int) $book->reconciled,
                    $book->reconciled_ref_id === null ? 'none' : (string) $book->reconciled_ref_id,
                    (int) $proposal->id,
                    (string) $proposal->kind,
                ));
            }

            $before = [
                'reconciled' => $book->reconciled,
                'reconciled_ref_id' => $book->reconciled_ref_id,
                'type_reference_id' => $book->type_reference_id,
            ];

            // accounting-builds T9 (Wave 2): a bank_statement proposal's "other side" is not a
            // second journal_entries row (unlike KIND_SUB_LEDGER_VS_CONTROL/plain internal
            // matches) — it is a BankStatementImportLine, identified by
            // `matched_reference = 'bank_stmt_line:{id}'` (see BankStatementMatcher::
            // ensureProposal()). `reconciled_ref_id` is stamped with THAT statement line's id
            // (spec: "approval marks the ledger line reconciled" / "ReconciliationProposalService::
            // approve extended to stamp reconciled_ref_id = statement line id and set the line
            // state") — never with the proposal's own id, which is the generic fallback below for
            // every OTHER proposal kind.
            $bankStatementLineId = $this->bankStatementLineIdFor($proposal);

            $book->reconciled = 1;
            $book->reconciled_ref_id = $bankStatementLineId ?? ($proposal->matched_journal_entry_id ?? $proposal->id);
            $book->reconciled_date = now()->toDateString();
            $book->reconciled_amount = $proposal->amount;

            // Control-account attribution proposal (kind = sub_ledger_vs_control): the real fix
            // is writing the missing party attribution, not just flipping `reconciled` — this is
            // what actually clears PeriodCloseChecklistService's own `unattributed_net` gap (and
            // therefore THIS row's own GAP, computed the identical way — see
            // ReconciliationCenterService's class docblock). `reconciled` is flipped too, on every
            // kind uniformly, so "locks the line" holds regardless of which detector produced the
            // proposal.
            if ($proposal->kind === ReconciliationProposal::KIND_SUB_LEDGER_VS_CONTROL
                && is_string($proposal->matched_reference)
                && str_starts_with($proposal->matched_reference, 'party:')
                && $book->type_reference_id === null
            ) {
                $book->type_reference_id = (int) mb_substr($proposal->matched_reference, mb_strlen('party:'));
            }

            $book->save();

            // Confirm the statement line's own state at the moment of approval — idempotent (the
            // matcher already set state='matched' at match time, per T8's precedent); this is the
            // one write on approval that touches BankStatementImportLine.state, never
            // journal_entries.reconciled a second way.
            if ($bankStatementLineId !== null) {
                BankStatementImportLine::where('id', $bankStatementLineId)->update([
                    'state' => BankStatementImportLine::STATE_MATCHED,
                ]);
            }

            if ($proposal->matched_journal_entry_id !== null) {
                $matched = JournalEntry::withoutGlobalScopes()->find($proposal->matched_journal_entry_id);
                if ($matched !== null && (int) $matched->reconciled !== 2) {
                    $matched->reconciled = 1;
                    $matched->reconciled_ref_id = $book->id;
                    $matched->reconciled_date = now()->toDateString();
                    $matched->save();
                }
            }

            $proposal->status = ReconciliationProposal::STATUS_APPROVED;
            $proposal->decided_by = $actor?->id;
            $proposal->decided_at = now();
            $proposal->save();

            AccountingLog::write(
                action: 'reconcile',
                companyId: (int) $proposal->company_id,
                subjectType: 'reconciliation_proposal',
                subjectId: (int) $proposal->id,
                transactionId: $book->transaction_id !== null ? (int) $book->transaction_id : null,
                before: $before,
                after: [
                    'reconciled' => 1,
                    'reconciled_ref_id' => $book->reconciled_ref_id,
                    'type_reference_id' => $book->type_reference_id,
                    'proposal_status' => 'approved',
                ],
                actorId: $actor?->id,
            );
        });

        return $proposal->refresh();
    }

    /**
     * accounting-builds T9. Extracts the {@see BankStatementImportLine} id from a
     * `kind = bank_statement` proposal's `matched_reference` (format `bank_stmt_line:{id}`,
     * BankStatementMatcher::ensureProposal()'s own identity convention) — null for every other
     * proposal kind, or if the referenced line no longer exists.
     */
    private function bankStatementLineIdFor(ReconciliationProposal $proposal): ?int
    {
        if ($proposal->kind !== ReconciliationProposal::KIND_BANK_STATEMENT) {
            return null;
        }

        $reference = (string) $proposal->matched_reference;
        if (! str_starts_with($reference, 'bank_stmt_line:')) {
            return null;
        }

        $lineId = (int) mb_substr($reference, mb_strlen('bank_stmt_line:'));

        return BankStatementImportLine::where('id', $lineId)->exists() ? $lineId : null;
    }

    public function reject(ReconciliationProposal $proposal, string $reason, ?User $actor): ReconciliationProposal
    {
        $this->reconciliation->assertCanReconcile($actor);

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A reason is required to reject a reconciliation proposal.');
        }

        if ($proposal->status !== ReconciliationProposal::STATUS_PENDING) {
            throw new \RuntimeException('Only a pending proposal can be rejected.');
        }

        $proposal->status = ReconciliationProposal::STATUS_REJECTED;
        $proposal->reason = $reason;
        $proposal->decided_by = $actor?->id;
        $proposal->decided_at = now();
        $proposal->save();

        // Rejecting leaves the underlying journal_entries line exactly as it was (still
        // reconciled = 0) — "rejecting keeps it unmatched with reason" (brief's own words).
        AccountingLog::write(
            action: 'reject',
            companyId: (int) $proposal->company_id,
            subjectType: 'reconciliation_proposal',
            subjectId: (int) $proposal->id,
            reason: $reason,
            after: ['proposal_status' => 'rejected'],
            actorId: $actor?->id,
        );

        return $proposal->refresh();
    }

    /**
     * Manual match — drill-down panel 4. Recorded through the SAME `reconciliation_proposals`
     * table (kind = manual, confidence = manual, already-decided/approved) so the row's own
     * HISTORY drawer has one source of truth for every match regardless of how it happened.
     */
    public function manualMatch(
        int $companyId,
        int $accountId,
        int $journalEntryId,
        ?int $matchedJournalEntryId,
        string $reason,
        ?User $actor,
    ): ReconciliationProposal {
        $this->reconciliation->assertCanReconcile($actor);

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A reason is required for a manual match.');
        }

        $book = JournalEntry::withoutGlobalScopes()->findOrFail($journalEntryId);

        $proposal = ReconciliationProposal::create([
            'company_id' => $companyId,
            'account_id' => $accountId,
            'source' => 'internal',
            'kind' => ReconciliationProposal::KIND_MANUAL,
            'confidence' => ReconciliationProposal::CONFIDENCE_MANUAL,
            'book_journal_entry_id' => $journalEntryId,
            'matched_journal_entry_id' => $matchedJournalEntryId,
            'amount' => abs((float) $book->debit - (float) $book->credit),
            'difference_amount' => 0,
            'status' => ReconciliationProposal::STATUS_PENDING,
        ]);

        $approved = $this->approve($proposal->fresh(), $actor);
        $approved->reason = $reason;
        $approved->save();

        return $approved;
    }

    /**
     * Manual unmatch — "unmatch refused when the period is locked" (brief's own words). Locked is
     * resolved off the line's `posting_date` (falling back to `transaction_date`), the SAME column
     * PeriodGuard/PostingService key every other period decision off — never `transaction_date`
     * alone once `posting_date` exists.
     */
    public function manualUnmatch(int $journalEntryId, string $reason, ?User $actor): JournalEntry
    {
        $this->reconciliation->assertCanReconcile($actor);

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A reason is required to unmatch a reconciled line.');
        }

        $line = JournalEntry::withoutGlobalScopes()->findOrFail($journalEntryId);

        $postingDate = Carbon::parse($line->posting_date ?? $line->transaction_date);
        $status = $this->center->periodStatusFor((int) $line->company_id, $postingDate);

        if ($status === AccountingPeriod::STATUS_LOCKED) {
            throw new ReconciliationPeriodLockedException((int) $line->company_id, $postingDate->year, $postingDate->month);
        }

        $before = ['reconciled' => $line->reconciled, 'reconciled_ref_id' => $line->reconciled_ref_id];

        $line->reconciled = 0;
        $line->reconciled_ref_id = null;
        $line->save();

        ReconciliationProposal::where('book_journal_entry_id', $journalEntryId)
            ->where('status', ReconciliationProposal::STATUS_APPROVED)
            ->latest('decided_at')
            ->first()
            ?->forceFill(['status' => ReconciliationProposal::STATUS_PENDING, 'reason' => $reason])
            ->save();

        AccountingLog::write(
            action: 'unreconcile',
            companyId: (int) $line->company_id,
            subjectType: 'journal_entry',
            subjectId: (int) $line->id,
            reason: $reason,
            before: $before,
            after: ['reconciled' => 0, 'reconciled_ref_id' => null],
            actorId: $actor?->id,
        );

        return $line->refresh();
    }
}
