<?php

declare(strict_types=1);

namespace App\Services\Accounting\Reconciliation;

use App\Models\BankStatementImport;
use App\Models\BankStatementImportLine;
use App\Models\JournalEntry;
use App\Models\ReconciliationProposal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * accounting-builds T9 (Wave 2). Matches a {@see BankStatementImport}'s lines against our posted
 * bank-leaf ledger lines (receipts and payments on that ONE bank leaf — see the importer's own
 * "bank-leaf scoping at the import boundary" note; a candidate query below is ALWAYS
 * `account_id = $import->bank_account_id`, so a KWD statement can structurally never match a USD
 * leaf's lines, and vice versa). Read + state only (L13/spec): never posts, never flips
 * `journal_entries.reconciled` directly — that only happens once a created proposal is APPROVED
 * through the existing {@see \App\Services\Accounting\ReconciliationProposalService::approve()}
 * (ArchitectureTest::test_no_post_hoc_reconciled_updates(), MP-9-3).
 *
 * STATEMENT POLARITY (see BankStatementImporter's own docblock, point 3): a bank statement's
 * "Credit" column is money paid INTO the account (our books' DEBIT on this asset leaf); its
 * "Debit" column is money paid OUT (our books' CREDIT). Every comparison below translates a
 * statement row to its ledger-equivalent column FIRST ({@see self::ledgerColumnFor()}) — a
 * statement credit is only ever compared against ledger DEBIT lines, a statement debit only
 * against ledger CREDIT lines. This is also a correctness guard: it stops a receipt from ever
 * being proposed as a match for a payment merely because their unsigned amounts coincide.
 *
 * MATCHING PRECEDENCE (spec, exact — proven by BankStatementMatcherTest's per-tier tests):
 *   1. `auth_no` EXACT match against `journal_entries.auth_no` (captured on receipts, previously
 *      unused as a match key — see the creating migration's own docblock).
 *   2. `reference` EXACT match against ANY of `journal_entries.receipt_reference_number` /
 *      `.voucher_number` / `.cheque_no` / `.bank_info` — the four free-text fields a bank's own
 *      "Reference" cell could plausibly reproduce.
 *   3. amount + date window (`config('accounting.bank_statements.date_window_days')`, default ±3
 *      days INCLUSIVE — day 4 is excluded, see the boundary test) — the CLOSEST candidate strictly
 *      within `config('accounting.bank_statements.match_tolerance')` wins (T8's RV-4 pattern:
 *      first-inside-tolerance is NOT good enough — ties keep the lower id, deterministic).
 * A key match (tier 1/2) whose amount is beyond tolerance is NOT demoted to a tier-3 probe — it
 * settles DISPUTED against that same identified candidate (the key told us WHICH ledger line this
 * statement row is; an amount mismatch on a positively-identified pair is a dispute, not a miss).
 * Only when NO key matches at all does tier 3 run.
 *
 * ONE-TO-ONE INVARIANT (T8's own invariant, "a ledger line is settled by at most one live
 * statement row", and the reverse): {@see self::$consumedJournalEntryIds} excludes any ledger line
 * an EARLIER row in this SAME match() run already matched (in-run guard); {@see self::liveClaimOn()}
 * refuses to raise a second proposal against a ledger line any still-pending-or-approved proposal
 * already claims — a PRIOR import's, or one of the internal detectors' that sweep the same
 * bank/cash leaves (cross-run guard, kind-agnostic: see that method's own note) — together the
 * covered-ids/live-claim guard pattern T8's review packet documents as one invariant enforced at
 * two horizons. A line settled by such a FOREIGN claim carries no proposal of its own, so
 * {@see self::matchLine()} treats that claim as its short-circuit oracle too.
 */
final class BankStatementMatcher
{
    public function match(BankStatementImport $import): BankStatementMatchResult
    {
        $matched = 0;
        $disputed = 0;
        $unmatchedStatement = 0;
        $byAuthNo = 0;
        $byReference = 0;
        $byAmountDate = 0;

        $consumedJournalEntryIds = [];

        foreach ($import->lines()->orderBy('row_no')->get() as $line) {
            $outcome = $this->matchLine($import, $line, $consumedJournalEntryIds);

            match ($outcome['state']) {
                BankStatementImportLine::STATE_MATCHED => $matched++,
                BankStatementImportLine::STATE_DISPUTED => $disputed++,
                default => $unmatchedStatement++,
            };

            match ($outcome['tier']) {
                'auth_no' => $byAuthNo++,
                'reference' => $byReference++,
                'amount_date' => $byAmountDate++,
                default => null,
            };
        }

        $result = new BankStatementMatchResult($matched, $disputed, $unmatchedStatement, $byAuthNo, $byReference, $byAmountDate);

        $import->status = BankStatementImport::STATUS_MATCHED;
        $import->counts = array_merge($result->toArray(), [
            'unmatched_ledger' => $this->unmatchedLedgerLines($import)->count(),
        ]);
        $import->save();

        return $result;
    }

    /**
     * @param  array<int,bool>  $consumedJournalEntryIds  mutated in place — see class docblock.
     * @return array{state:string, tier:?string}
     */
    private function matchLine(BankStatementImport $import, BankStatementImportLine $line, array &$consumedJournalEntryIds): array
    {
        // Idempotent short-circuit: a MATCHED line with a still-LIVE (pending or approved)
        // proposal is already settled — skip recomputation entirely. Without this, re-running
        // match() (the nightly detector sweep, or an operator re-clicking "match" on an
        // already-matched import — both call this unconditionally over every line) would
        // re-derive the candidate from scratch; once the matched proposal is APPROVED, approval
        // sets the candidate's `journal_entries.reconciled = 1`, which every tier's baseQuery
        // excludes (`->where('reconciled', 0)`) — so the SAME candidate that was correctly
        // matched moments ago would no longer be found, and this line would be wrongly reverted
        // to 'unmatched' (matched_journal_entry_id nulled, note overwritten), even though the
        // underlying ledger line remains correctly reconciled and the proposal remains approved.
        // A REJECTED proposal is deliberately NOT live here (see ensureProposal()'s matching
        // status filter) — rejecting a match must allow a corrected re-match to reconsider it,
        // per spec ("rejected -> allowed").
        //
        // POST-FIX RE-VERIFY hardening: the short-circuit's premise is that the candidate vanished
        // ONLY because approval reconciled it — a self-inflicted exclusion. That premise has to be
        // CHECKED, not assumed. If the claimed ledger line stopped being a valid candidate for any
        // OTHER reason — its document was reversed (PostingService::reverse() stamps
        // `posting_status = 'reversed'` on the original and leaves the line itself untouched and
        // still `reconciled = 0`, so nothing else catches this) or the line was soft-deleted — then
        // the claim is STALE: the statement row's evidence has been backed out of the ledger.
        // Leaving it silently 'matched' would tell the reconciliation report and
        // PeriodCloseChecklistService that a movement is settled against a line that no longer
        // exists. It is settled as DISPUTED instead (an owner-approved state, so it surfaces in
        // exceptionsFor() and re-trips the close WARN — correct: it genuinely IS an open item
        // again), keeping `matched_journal_entry_id` so an operator can trace WHICH line went away.
        // It deliberately does NOT fall through to the tiers: re-deriving would claim a SECOND
        // ledger line while the stale proposal is still live, breaking the one-to-one invariant.
        // The matcher never decides proposals — the live one stays for a human to reject in the
        // reconciliation center, and the note names it.
        //
        // FINAL RE-VERIFY (loop 3) — the FOREIGN-claim route into that same hazard. A statement
        // line can be settled by a live claim that is not its OWN proposal: when
        // {@see self::ensureProposal()} correctly declines to raise a second claim on a ledger
        // line another detector already holds (the kind-agnostic guard R-2 introduced — see
        // {@see self::liveClaimOn()}), the line ends up MATCHED with NO bank_statement proposal of
        // its own, so `liveReferenceFor()` is null and the short-circuit above cannot fire.
        // Approving that OTHER proposal then sets `journal_entries.reconciled = 1`, which every
        // tier's baseQuery excludes — and the line was reverted to 'unmatched' on the next sweep.
        // That is the very defect `4e96c08d` fixed, reached through the one route its own oracle
        // does not cover, and made reachable precisely BY R-2's (correct) decision to let a
        // foreign claim block ours. The settlement is real — the ledger line IS reconciled, just
        // by someone else's claim — so the foreign claim is the oracle here, and the same
        // staleness rule applies to it unchanged.
        $claim = $this->liveReferenceFor($line);
        $claimedId = $claim?->book_journal_entry_id !== null ? (int) $claim->book_journal_entry_id : null;

        if ($claim === null
            && $line->state === BankStatementImportLine::STATE_MATCHED
            && $line->matched_journal_entry_id !== null
        ) {
            $foreignId = (int) $line->matched_journal_entry_id;
            $foreign = $this->liveClaimOn($foreignId);

            if ($foreign !== null) {
                $claim = $foreign;
                $claimedId = $foreignId;
            }
        }

        if ($claim !== null) {
            if ($claimedId !== null && ! $this->claimedLineIsStillValid($import, $claimedId)) {
                return $this->settle($line, BankStatementImportLine::STATE_DISPUTED, [
                    'note' => sprintf(
                        'Matched ledger line JE#%d is no longer a live posted line on this bank leaf (reversed, voided or deleted) — the match is stale. Proposal #%d (kind %s, %s) still stands; reject it in the reconciliation center to allow a fresh match.',
                        $claimedId,
                        $claim->id,
                        $claim->kind,
                        $claim->status
                    ),
                    'difference' => (float) $line->difference,
                    'matched_journal_entry_id' => $claimedId,
                ], null, null);
            }

            if ($line->state === BankStatementImportLine::STATE_MATCHED) {
                if ($claimedId !== null) {
                    $consumedJournalEntryIds[$claimedId] = true;
                }

                return ['state' => $line->state, 'tier' => null];
            }
        }

        $tolerance = (float) config('accounting.bank_statements.match_tolerance', 0.001);
        $windowDays = (int) config('accounting.bank_statements.date_window_days', 3);

        [$statementAmount, $ledgerColumn] = $this->ledgerColumnFor($line);

        $baseQuery = fn () => JournalEntry::withoutGlobalScopes()
            ->where('company_id', $import->company_id)
            ->where('account_id', $import->bank_account_id)
            ->whereNull('deleted_at')
            ->where('reconciled', 0)
            ->where($ledgerColumn, '>', 0)
            ->when($consumedJournalEntryIds !== [], fn ($q) => $q->whereNotIn('id', array_keys($consumedJournalEntryIds)))
            ->whereHas('transaction', fn ($q) => $q->where('posting_status', 'posted'));

        // Tier 1: auth_no exact.
        if ($line->auth_no !== null && trim((string) $line->auth_no) !== '') {
            $candidate = $baseQuery()->where('auth_no', $line->auth_no)->orderBy('id')->first();
            if ($candidate !== null) {
                return $this->settleAgainst($line, $candidate, $statementAmount, $ledgerColumn, $tolerance, 'auth_no', $consumedJournalEntryIds);
            }
        }

        // Tier 2: reference exact, against any of the four free-text reference-shaped columns.
        if ($line->reference !== null && trim((string) $line->reference) !== '') {
            $ref = $line->reference;
            $candidate = $baseQuery()
                ->where(function ($q) use ($ref) {
                    $q->where('receipt_reference_number', $ref)
                        ->orWhere('voucher_number', $ref)
                        ->orWhere('cheque_no', $ref)
                        ->orWhere('bank_info', $ref);
                })
                ->orderBy('id')
                ->first();
            if ($candidate !== null) {
                return $this->settleAgainst($line, $candidate, $statementAmount, $ledgerColumn, $tolerance, 'reference', $consumedJournalEntryIds);
            }
        }

        // Tier 3: amount + date window. ±$windowDays INCLUSIVE both ends (boundary test: day 4 is
        // excluded). Closest-within-tolerance wins (T8 RV-4 pattern), ties keep the lower id.
        $valueDate = Carbon::parse($line->value_date);
        $windowStart = $valueDate->copy()->subDays($windowDays)->toDateString();
        $windowEnd = $valueDate->copy()->addDays($windowDays)->toDateString();

        $candidates = $baseQuery()
            ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '>=', $windowStart)
            ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', $windowEnd)
            ->orderBy('id')
            ->get();

        $best = null;
        foreach ($candidates as $candidate) {
            $diff = round((float) $candidate->{$ledgerColumn} - $statementAmount, 3);
            if (abs($diff) <= $tolerance + 1e-9 && ($best === null || abs($diff) < abs($best[1]) - 1e-9)) {
                $best = [$candidate, $diff];
            }
        }

        if ($best !== null) {
            [$bestCandidate, $bestDiff] = $best;
            $consumedJournalEntryIds[$bestCandidate->id] = true;

            return $this->settle($line, BankStatementImportLine::STATE_MATCHED, [
                'note' => null,
                'difference' => $bestDiff,
                'matched_journal_entry_id' => $bestCandidate->id,
            ], $this->proposalConfidenceFor($bestDiff), 'amount_date');
        }

        return $this->settle($line, BankStatementImportLine::STATE_UNMATCHED, [
            'note' => 'No ledger line found by auth_no, reference, or amount+date window on this bank leaf.',
            'difference' => 0,
            'matched_journal_entry_id' => null,
        ], null, null);
    }

    /**
     * A key match (auth_no or reference) settles against that ONE identified candidate — MATCHED
     * if the amount is within tolerance, else DISPUTED against the same candidate (never falls
     * through to a different tier's candidate — the key already told us which ledger line this is).
     *
     * @return array{state:string, tier:?string}
     */
    private function settleAgainst(
        BankStatementImportLine $line,
        JournalEntry $candidate,
        float $statementAmount,
        string $ledgerColumn,
        float $tolerance,
        string $tier,
        array &$consumedJournalEntryIds,
    ): array {
        $diff = round((float) $candidate->{$ledgerColumn} - $statementAmount, 3);

        if (abs($diff) <= $tolerance + 1e-9) {
            $consumedJournalEntryIds[$candidate->id] = true;

            return $this->settle($line, BankStatementImportLine::STATE_MATCHED, [
                'note' => null,
                'difference' => $diff,
                'matched_journal_entry_id' => $candidate->id,
            ], $this->proposalConfidenceFor($diff), $tier);
        }

        return $this->settle($line, BankStatementImportLine::STATE_DISPUTED, [
            'note' => sprintf('Ledger line identified by %s (JE#%d) but amount differs beyond tolerance.', $tier, $candidate->id),
            'difference' => $diff,
            'matched_journal_entry_id' => $candidate->id,
        ], null, $tier);
    }

    /**
     * @param  array{note:?string,difference:float,matched_journal_entry_id:?int}  $fields
     * @return array{state:string, tier:?string}
     */
    private function settle(BankStatementImportLine $line, string $state, array $fields, ?string $proposalConfidence, ?string $tier): array
    {
        $line->state = $state;
        $line->note = $fields['note'];
        $line->difference = $fields['difference'];
        $line->matched_journal_entry_id = $fields['matched_journal_entry_id'];
        $line->save();

        if ($state === BankStatementImportLine::STATE_MATCHED && $fields['matched_journal_entry_id'] !== null) {
            $this->ensureProposal($line, (int) $fields['matched_journal_entry_id'], $proposalConfidence ?? ReconciliationProposal::CONFIDENCE_EXACT);
        }

        return ['state' => $state, 'tier' => $tier];
    }

    /**
     * Creates the external ReconciliationProposal for a clean match (L13) — idempotent per line;
     * refuses (cross-run guard) when the ledger line already carries a live (pending-or-approved)
     * proposal of ANY kind — an earlier bank statement, or one of the internal detectors that
     * sweep the same bank/cash leaves (see {@see self::liveClaimOn()}'s own note). No
     * aggregate/covered-ids case exists for bank statements — every match here is 1:1.
     */
    private function ensureProposal(BankStatementImportLine $line, int $bookJournalEntryId, string $confidence): void
    {
        // Only a LIVE (pending or approved) proposal blocks re-proposing — a REJECTED one must
        // not, so a corrected re-match can raise a fresh proposal (spec: "rejected -> allowed").
        // The matchLine() short-circuit above already prevents a LIVE match from ever reaching
        // this far a second time, so this check only ever bites the "rejected, now re-derived"
        // path or a genuinely-first call.
        if ($this->liveReferenceFor($line) !== null) {
            return;
        }

        $claimed = $this->liveClaimOn($bookJournalEntryId);
        if ($claimed !== null) {
            $line->note = sprintf(
                'Ledger line already claimed by a live reconciliation proposal (#%d, kind %s, %s) — matched, no new proposal raised.',
                $claimed->id,
                $claimed->kind,
                $claimed->status
            );
            $line->save();

            return;
        }

        $book = JournalEntry::withoutGlobalScopes()->find($bookJournalEntryId);
        if ($book === null) {
            return;
        }

        ReconciliationProposal::create([
            'company_id' => $book->company_id,
            'account_id' => $book->account_id,
            'source' => 'external',
            'kind' => ReconciliationProposal::KIND_BANK_STATEMENT,
            'confidence' => $confidence,
            'book_journal_entry_id' => $bookJournalEntryId,
            'matched_journal_entry_id' => null,
            'matched_reference' => 'bank_stmt_line:'.$line->id,
            'amount' => $line->amount(),
            'difference_amount' => $line->difference,
            'status' => ReconciliationProposal::STATUS_PENDING,
        ]);
    }

    /**
     * Any LIVE (pending or approved) proposal already claiming this ledger line — of ANY kind.
     *
     * POST-FIX RE-VERIFY (cross-kind): this deliberately does NOT filter on
     * `kind = bank_statement`. A bank leaf is not this matcher's private territory —
     * {@see \App\Services\Accounting\ReconciliationAutoMatchService::detectClearingRollforward()}
     * sweeps the SAME bank/cash leaves for cleared cheques and runs FIRST inside the same nightly
     * `run()`, and detectReceiptInvoiceConsistency() flags lines on those leaves too. A cleared
     * cheque that also appears on the bank statement — the most ordinary bank-reconciliation event
     * there is — reaches both. With a kind filter here, the clearing proposal was invisible and a
     * SECOND live proposal was raised against the same ledger line, which could then be reconciled
     * twice against two different counterparts (`reconciled_ref_id` ending up as whichever was
     * approved last). This is now the exact mirror of that service's own kind-agnostic
     * `alreadyPending()` guard, which already declines in the reverse direction for the same
     * reason — one invariant, symmetrical in both directions: a ledger line carries at most one
     * live claim.
     */
    private function liveClaimOn(int $bookJournalEntryId): ?ReconciliationProposal
    {
        return ReconciliationProposal::where('book_journal_entry_id', $bookJournalEntryId)
            ->whereIn('status', [ReconciliationProposal::STATUS_PENDING, ReconciliationProposal::STATUS_APPROVED])
            ->first();
    }

    /**
     * Is the ledger line a live proposal claims still a valid candidate for THIS import? Mirrors
     * {@see self::matchLine()}'s own `baseQuery` eligibility predicate, minus `reconciled = 0`
     * (approval legitimately sets that — it is the whole reason the short-circuit exists) and minus
     * the in-run consumed set. Anything else that makes it ineligible — soft-deleted, or its
     * document no longer `posting_status = 'posted'` (reversed/void/draft), or it moved off this
     * company's bank leaf — means the claim is stale.
     */
    private function claimedLineIsStillValid(BankStatementImport $import, int $bookJournalEntryId): bool
    {
        return JournalEntry::withoutGlobalScopes()
            ->where('id', $bookJournalEntryId)
            ->where('company_id', $import->company_id)
            ->where('account_id', $import->bank_account_id)
            ->whereNull('deleted_at')
            ->whereHas('transaction', fn ($q) => $q->where('posting_status', 'posted'))
            ->exists();
    }

    /**
     * This ONE statement line's own live (pending or approved) proposal, if any — the
     * re-match short-circuit's and ensureProposal()'s shared oracle for "is this line already
     * settled by a claim still in force". A rejected proposal for the same line is deliberately
     * excluded (not live), so a corrected re-match can reconsider it.
     */
    private function liveReferenceFor(BankStatementImportLine $line): ?ReconciliationProposal
    {
        return ReconciliationProposal::where('matched_reference', 'bank_stmt_line:'.$line->id)
            ->whereIn('status', [ReconciliationProposal::STATUS_PENDING, ReconciliationProposal::STATUS_APPROVED])
            ->first();
    }

    private function proposalConfidenceFor(float $diff): string
    {
        return abs($diff) < 1e-9 ? ReconciliationProposal::CONFIDENCE_EXACT : ReconciliationProposal::CONFIDENCE_TOLERANCE;
    }

    /**
     * Translates a statement row's own debit/credit columns into (unsigned amount, ledger column
     * to compare against) per the class docblock's polarity note. A statement 'Credit' (money in)
     * is compared against ledger `debit`; a statement 'Debit' (money out) against ledger `credit`.
     *
     * @return array{0:float,1:string}
     */
    private function ledgerColumnFor(BankStatementImportLine $line): array
    {
        $isInflow = (float) $line->credit > 0;

        return $isInflow ? [(float) $line->credit, 'debit'] : [(float) $line->debit, 'credit'];
    }

    /**
     * Exceptions report component: our open, posted bank-leaf lines (this leaf only — never a
     * different account) within the import's statement period, that never matched ANY line in this
     * import and are not already reconciled from an earlier cycle. This IS "unmatched-ledger" (the
     * owner-approved spec's fourth state) — never a stored row, always a live read, exactly like
     * T8's own {@see \App\Services\Accounting\Reconciliation\SupplierStatementMatcher::unmatchedLedgerLines()}.
     *
     * @return Collection<int, JournalEntry>
     */
    public function unmatchedLedgerLines(BankStatementImport $import): Collection
    {
        $matchedIds = $import->lines()
            ->whereNotNull('matched_journal_entry_id')
            ->pluck('matched_journal_entry_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $from = $import->statement_from?->toDateString() ?? $import->lines()->min('value_date');
        $to = $import->statement_to?->toDateString() ?? $import->lines()->max('value_date');

        return JournalEntry::withoutGlobalScopes()
            ->where('company_id', $import->company_id)
            ->where('account_id', $import->bank_account_id)
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->where('debit', '!=', 0)->orWhere('credit', '!=', 0))
            ->where(function ($q) {
                $q->whereNull('reconciled')->orWhere('reconciled', '!=', 1);
            })
            ->whereHas('transaction', fn ($q) => $q->where('posting_status', 'posted'))
            ->when($from !== null, fn ($q) => $q->whereDate('posting_date', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('posting_date', '<=', $to))
            ->when($matchedIds !== [], fn ($q) => $q->whereNotIn('id', $matchedIds))
            ->orderBy('posting_date')
            ->get();
    }

    /**
     * @return array{unmatched_statement: Collection, disputed: Collection, unmatched_ledger: Collection}
     */
    public function exceptionsFor(BankStatementImport $import): array
    {
        return [
            'unmatched_statement' => $import->lines()->where('state', BankStatementImportLine::STATE_UNMATCHED)->orderBy('row_no')->get(),
            'disputed' => $import->lines()->where('state', BankStatementImportLine::STATE_DISPUTED)->orderBy('row_no')->get(),
            'unmatched_ledger' => $this->unmatchedLedgerLines($import),
        ];
    }

    /**
     * Unreconciled report (spec): running statement balance vs the bank leaf's ledger-DERIVED
     * balance at the statement's end date — `journal_entries` only, NEVER `accounts.
     * actual_balance`/`journal_entries.balance`. Both directions: statement lines never matched to
     * a ledger line, and posted ledger lines never matched to a statement line (unmatched_ledger).
     *
     * @return array{
     *     ledger_balance: float, statement_closing_balance: ?float, difference: ?float,
     *     unmatched_statement_count: int, unmatched_statement_net: float,
     *     disputed_count: int, disputed_net: float,
     *     unmatched_ledger_count: int, unmatched_ledger_net: float,
     * }
     */
    public function reconciliationReport(BankStatementImport $import): array
    {
        $asOf = $import->statement_to?->toDateString() ?? now()->toDateString();

        $totals = JournalEntry::withoutGlobalScopes()
            ->where('company_id', $import->company_id)
            ->where('account_id', $import->bank_account_id)
            ->whereNull('deleted_at')
            ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', $asOf)
            ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
            ->first();

        $ledgerBalance = round((float) $totals->d - (float) $totals->c, 3);

        $statementClosing = $import->closing_balance !== null ? round((float) $import->closing_balance, 3) : null;

        $exceptions = $this->exceptionsFor($import);

        $unmatchedStatementNet = round((float) $exceptions['unmatched_statement']->sum(fn (BankStatementImportLine $l) => $l->amount()), 3);
        $disputedNet = round((float) $exceptions['disputed']->sum(fn (BankStatementImportLine $l) => $l->amount()), 3);
        $unmatchedLedgerNet = round((float) $exceptions['unmatched_ledger']->sum(fn (JournalEntry $l) => (float) $l->debit - (float) $l->credit), 3);

        return [
            'ledger_balance' => $ledgerBalance,
            'statement_closing_balance' => $statementClosing,
            'difference' => $statementClosing !== null ? round($ledgerBalance - $statementClosing, 3) : null,
            'unmatched_statement_count' => $exceptions['unmatched_statement']->count(),
            'unmatched_statement_net' => $unmatchedStatementNet,
            'disputed_count' => $exceptions['disputed']->count(),
            'disputed_net' => $disputedNet,
            'unmatched_ledger_count' => $exceptions['unmatched_ledger']->count(),
            'unmatched_ledger_net' => $unmatchedLedgerNet,
        ];
    }
}
