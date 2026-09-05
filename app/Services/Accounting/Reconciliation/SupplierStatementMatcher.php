<?php

declare(strict_types=1);

namespace App\Services\Accounting\Reconciliation;

use App\Models\JournalEntry;
use App\Models\ReconciliationProposal;
use App\Models\SupplierStatementImport;
use App\Models\SupplierStatementImportLine;
use App\Modules\DotwAI\Models\DotwAIBooking;
use Illuminate\Support\Collection;

/**
 * accounting-builds T8 (Lane E). Matches a {@see SupplierStatementImport}'s lines against our
 * posted DOTW payable ledger. Read + state only (L13/spec): never posts, never flips
 * `journal_entries.reconciled` directly (that only happens once a created proposal is APPROVED
 * through the existing {@see \App\Services\Accounting\ReconciliationProposalService::approve()}
 * — see ArchitectureTest::test_no_post_hoc_reconciled_updates(), MP-8-3).
 *
 * PARTY DISCIPLINE / F3 (blueprint 08-akeed-as-built.md deviation 1; PLAN.md §5 Lane E note):
 * "any party position must be read via `partyAccountRef`, never via GL leaf balance." Every
 * ledger query below filters on `journal_entries.type_reference_id = $supplierId`
 * (partyAccountRef, {@see \App\Services\Accounting\LineDraft::$partyAccountRef}'s write target —
 * see LineDraft.php's own docblock) and deliberately NEVER on `account_id` at all. This is how
 * F3 is closed here rather than reproduced: `SupplierLedgerStatementSource` (the existing party
 * statement reader) resolves ONE leaf via `AccountResolver::resolve('PAYABLE_CONTROL', ...)` and
 * therefore misses a DOTW charge that landed on the SERVICE_PAYABLE/hotel pool leaf or one of its
 * per-currency children (mapSupplierPoolLeaf() steps 4-5, SystemAccountsSeeder.php) — this
 * matcher does not resolve or enumerate ANY leaf; `type_reference_id` alone scopes every posted
 * line back to its party regardless of which leaf it landed on, so PAYABLE_CONTROL and every
 * SERVICE_PAYABLE/{currency} child are read uniformly, with no leaf-walk to keep in sync with the
 * seeder's own resolution order. The one further filter added for precision (`invoice_id =
 * $booking->invoice_id`, matching a statement line to one specific booking's charge) narrows
 * WITHIN that already-party-safe set — it never substitutes for the party filter.
 *
 * BOOKING REFERENCE (task instructions: "find the actual column(s) in the codebase"): the real
 * `dotwai_bookings` schema (2026_03_24_100000_create_dotwai_bookings_table.php, verified against
 * this worktree — NOT the plan's inventory text, which named `confirmation_code`/
 * `confirmation_number`/`customer_reference` columns that do not exist on this table) carries
 * `prebook_key` (internal key), `booking_ref` (DOTW's returned reference, primary match target),
 * `booking_refs` (json array — a booking can carry more than one DOTW reference, e.g. a
 * multi-room booking), and `confirmation_no` (DOTW's confirmation number). This matcher resolves
 * a statement line's booking_ref/confirmation_code cell against, in order: `booking_ref` exact,
 * `booking_refs` JSON-contains, `confirmation_no` exact, `prebook_key` exact — trying the
 * statement's `booking_ref` cell first, then its `confirmation_code` cell through the same chain.
 */
final class SupplierStatementMatcher
{
    public function match(SupplierStatementImport $import): SupplierStatementMatchResult
    {
        $matched = 0;
        $disputed = 0;
        $unmatchedStatement = 0;

        // ADVERSARIAL-VERIFY FIX (T8 re-verify, AV-1/AV-2): tracks payable-line ids already
        // consumed by an EARLIER statement line within this same match() run, so that (a) two
        // legitimate ledger lines on one invoice (e.g. room + tax posted as separate JE credit
        // lines) each get matched to their OWN statement row instead of being summed and falsely
        // disputed against every row individually, and (b) two statement rows that duplicate one
        // booking reference never both resolve to — and each get a pending ReconciliationProposal
        // against — the SAME ledger line. See payableLinesFor()'s $excludeIds param.
        $consumedJournalEntryIds = [];

        foreach ($import->lines()->orderBy('row_no')->get() as $line) {
            $outcome = $this->matchLine($import, $line, $consumedJournalEntryIds);

            match ($outcome['state']) {
                SupplierStatementImportLine::STATE_MATCHED => $matched++,
                SupplierStatementImportLine::STATE_DISPUTED => $disputed++,
                default => $unmatchedStatement++,
            };
        }

        $result = new SupplierStatementMatchResult($matched, $disputed, $unmatchedStatement);

        $import->status = SupplierStatementImport::STATUS_MATCHED;
        $import->counts = array_merge($result->toArray(), [
            'unmatched_ledger' => $this->unmatchedLedgerLines($import)->count(),
        ]);
        $import->save();

        return $result;
    }

    /**
     * @param  array<int,bool>  $consumedJournalEntryIds  keyed by journal_entries.id already
     *                                                    claimed by an earlier statement line in this same match() run — mutated in place.
     * @return array{state:string}
     */
    private function matchLine(SupplierStatementImport $import, SupplierStatementImportLine $line, array &$consumedJournalEntryIds): array
    {
        $booking = $this->resolveBooking($import->company_id, $line);

        if ($booking === null) {
            return $this->settle($line, SupplierStatementImportLine::STATE_UNMATCHED, [
                'note' => 'No matching DOTW booking found for this statement line\'s reference.',
                'difference' => 0,
                'matched_journal_entry_id' => null,
                'matched_task_id' => null,
            ]);
        }

        if ($booking->invoice_id === null) {
            return $this->settle($line, SupplierStatementImportLine::STATE_UNMATCHED, [
                'note' => "Booking {$booking->prebook_key} matched but has no invoice yet — no payable line to compare.",
                'difference' => 0,
                'matched_journal_entry_id' => null,
                'matched_task_id' => $booking->task_id,
            ]);
        }

        $candidates = $this->payableLinesFor(
            $import->company_id,
            $import->supplier_id,
            (int) $booking->invoice_id,
            array_keys($consumedJournalEntryIds)
        );

        if ($candidates->isEmpty()) {
            return $this->settle($line, SupplierStatementImportLine::STATE_UNMATCHED, [
                'note' => "Booking {$booking->prebook_key} matched (invoice {$booking->invoice_id}) but no posted payable line was found for supplier id {$import->supplier_id}.",
                'difference' => 0,
                'matched_journal_entry_id' => null,
                'matched_task_id' => $booking->task_id,
            ]);
        }

        // NOTE: match_tolerance lives at config('accounting.supplier_statements.match_tolerance')
        // — a SIBLING of 'dotw', not nested inside it (see config/accounting.php's own layout).
        // Caught by the MP-8-2 mutation proof: widening this to 0.01 silently did nothing until
        // this path was corrected, because config()'s missing-path default (0.001) happened to
        // equal the real tolerance value and masked the wrong path.
        $tolerance = (float) config('accounting.supplier_statements.match_tolerance', 0.001);

        // AV-1/AV-2 fix: first try to match this statement row against ONE still-unconsumed
        // candidate individually (the common case — one statement row per ledger charge). Only
        // when no single candidate lines up do we fall back to the legacy aggregate-across-all
        // behavior (one statement row summarising several ledger lines for the same invoice,
        // e.g. multiple room-nights posted as separate credits).
        //
        // RV-4 (post-fix re-verify): when SEVERAL candidates sit inside tolerance, the CLOSEST one
        // wins, not simply the first by id. First-wins let a row consume another row's exact
        // counterpart (cross-pairing two rows that each had an exact match) and downgraded both
        // proposals from 'exact' to 'tolerance' confidence. Ties keep the lower id (`orderBy('id')`
        // on the candidate query + a strict `<` below), so selection stays deterministic.
        $best = null;

        foreach ($candidates as $candidate) {
            [$soloAmount] = $this->bookAmountFor(collect([$candidate]), $line->currency);
            $soloDiff = round($soloAmount - (float) $line->amount, 3);

            if (abs($soloDiff) <= $tolerance + 1e-9 && ($best === null || abs($soloDiff) < abs($best[1]) - 1e-9)) {
                $best = [$candidate, $soloDiff];
            }
        }

        if ($best !== null) {
            [$bestCandidate, $bestDiff] = $best;
            $consumedJournalEntryIds[$bestCandidate->id] = true;

            return $this->settle($line, SupplierStatementImportLine::STATE_MATCHED, [
                'note' => null,
                'difference' => $bestDiff,
                'matched_journal_entry_id' => $bestCandidate->id,
                'matched_task_id' => $booking->task_id,
            ], $this->proposalConfidenceFor($bestDiff), [$bestCandidate->id]);
        }

        [$bookAmount, $basis] = $this->bookAmountFor($candidates, $line->currency);
        $primary = $candidates->first();
        $diff = round($bookAmount - (float) $line->amount, 3);

        $aggregateNote = $candidates->count() > 1
            ? sprintf(' (aggregated across %d payable lines, primary=JE#%d: %s)', $candidates->count(), $primary->id, $candidates->pluck('id')->implode(','))
            : '';

        if (abs($diff) <= $tolerance + 1e-9) {
            foreach ($candidates as $candidate) {
                $consumedJournalEntryIds[$candidate->id] = true;
            }

            // RV-1 (post-fix re-verify): an aggregate match consumes EVERY candidate but only one
            // of them fits `matched_journal_entry_id` (the primary). Recording the full covered set
            // is what stops the non-primary lines from resurfacing as "unmatched-ledger"
            // exceptions — they are not open payables absent from the statement, they are inside
            // the row that just matched. See unmatchedLedgerLines().
            return $this->settle($line, SupplierStatementImportLine::STATE_MATCHED, [
                'note' => null,
                'difference' => $diff,
                'matched_journal_entry_id' => $primary->id,
                'matched_task_id' => $booking->task_id,
            ], $this->proposalConfidenceFor($diff), $candidates->pluck('id')->map(fn ($id) => (int) $id)->all());
        }

        return $this->settle($line, SupplierStatementImportLine::STATE_DISPUTED, [
            'note' => "Amount differs beyond tolerance ({$basis} comparison){$aggregateNote}.",
            'difference' => $diff,
            'matched_journal_entry_id' => $primary->id,
            'matched_task_id' => $booking->task_id,
        ]);
    }

    /**
     * @param  array{note:?string,difference:float,matched_journal_entry_id:?int,matched_task_id:?int}  $fields
     * @param  array<int,int>  $coveredJournalEntryIds  every payable line this statement row
     *                                                  actually consumed — one id for a 1:1 match, all of them for an aggregate match (RV-1).
     * @return array{state:string}
     */
    private function settle(SupplierStatementImportLine $line, string $state, array $fields, ?string $proposalConfidence = null, array $coveredJournalEntryIds = []): array
    {
        $line->state = $state;
        $line->note = $fields['note'];
        $line->difference = $fields['difference'];
        $line->matched_journal_entry_id = $fields['matched_journal_entry_id'];
        $line->matched_journal_entry_ids = $coveredJournalEntryIds !== [] ? array_values($coveredJournalEntryIds) : null;
        $line->matched_task_id = $fields['matched_task_id'];
        $line->save();

        if ($state === SupplierStatementImportLine::STATE_MATCHED && $fields['matched_journal_entry_id'] !== null) {
            $this->ensureProposal($line, (int) $fields['matched_journal_entry_id'], $proposalConfidence ?? ReconciliationProposal::CONFIDENCE_EXACT);
        }

        return ['state' => $state];
    }

    /**
     * Creates the external ReconciliationProposal for a clean match (L13) — idempotent: a second
     * match() run for the same line reuses the existing pending proposal rather than creating a
     * duplicate. Never creates one for 'disputed'/'unmatched' lines — there is nothing clean to
     * approve there; those stay visible only in the exceptions report.
     *
     * RV-6 (post-fix re-verify): the AV-2 consumed-id set lives in memory for ONE match() run, so
     * it cannot stop a LATER import (a corrected or extended statement — a different content_hash,
     * so the importer's own idempotency does not apply either) from raising a SECOND approvable
     * proposal against a ledger line an earlier statement already claimed. That is the same
     * double-count risk AV-2 named, one cycle later, so the guard is completed here at the point
     * of truth: a payable line already carrying a pending-or-approved supplier-statement proposal
     * is spoken for. A REJECTED one is not a claim — a re-import may legitimately propose again.
     */
    private function ensureProposal(SupplierStatementImportLine $line, int $bookJournalEntryId, string $confidence): void
    {
        $reference = 'supplier_stmt_line:'.$line->id;

        $existing = ReconciliationProposal::where('matched_reference', $reference)->first();
        if ($existing !== null) {
            return;
        }

        $claimed = ReconciliationProposal::where('book_journal_entry_id', $bookJournalEntryId)
            ->where('kind', ReconciliationProposal::KIND_SUPPLIER_STATEMENT)
            ->whereIn('status', [ReconciliationProposal::STATUS_PENDING, ReconciliationProposal::STATUS_APPROVED])
            ->first();

        if ($claimed !== null) {
            // The line stays 'matched' (it genuinely corresponds to that ledger line — demoting it
            // to an exception would send the operator chasing an already-reconciled charge), but
            // says so instead of silently producing no proposal.
            $line->note = sprintf(
                'Ledger line already reconciled against an earlier statement (proposal #%d, %s) — matched, no new proposal raised.',
                $claimed->id,
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
            'kind' => ReconciliationProposal::KIND_SUPPLIER_STATEMENT,
            'confidence' => $confidence,
            'book_journal_entry_id' => $bookJournalEntryId,
            'matched_journal_entry_id' => null,
            'matched_reference' => $reference,
            'amount' => $line->amount,
            'difference_amount' => $line->difference,
            'status' => ReconciliationProposal::STATUS_PENDING,
        ]);
    }

    private function proposalConfidenceFor(float $diff): string
    {
        return abs($diff) < 1e-9 ? ReconciliationProposal::CONFIDENCE_EXACT : ReconciliationProposal::CONFIDENCE_TOLERANCE;
    }

    private function resolveBooking(int $companyId, SupplierStatementImportLine $line): ?DotwAIBooking
    {
        foreach ([$line->booking_ref, $line->confirmation_code] as $reference) {
            if ($reference === null || trim($reference) === '') {
                continue;
            }

            $booking = DotwAIBooking::query()
                ->where('company_id', $companyId)
                ->where(function ($q) use ($reference) {
                    $q->where('booking_ref', $reference)
                        ->orWhere('confirmation_no', $reference)
                        ->orWhere('prebook_key', $reference)
                        ->orWhereJsonContains('booking_refs', $reference);
                })
                ->first();

            if ($booking !== null) {
                return $booking;
            }
        }

        return null;
    }

    /**
     * @param  array<int,int>  $excludeIds  journal_entries.id values already claimed by an
     *                                      earlier statement line in the current match() run (AV-1/AV-2 fix) — excluded so a
     *                                      second statement row never re-consumes a payable line another row already matched.
     * @return Collection<int, JournalEntry> posted payable lines for one supplier on one invoice
     *                                       — see this class's own docblock for why this reads by `type_reference_id` only, never
     *                                       by GL leaf.
     */
    private function payableLinesFor(int $companyId, int $supplierId, int $invoiceId, array $excludeIds = []): Collection
    {
        return JournalEntry::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('type_reference_id', $supplierId)
            ->where('invoice_id', $invoiceId)
            ->where('credit', '>', 0)
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->whereHas('transaction', fn ($q) => $q->where('posting_status', 'posted'))
            ->orderBy('id')
            ->get();
    }

    /**
     * L16: "when the statement currency equals the line's FC amount when FC, else base." Sums
     * `original_amount` across candidate lines whose `original_currency` equals the statement's
     * currency when any exist; otherwise sums `credit` (base currency).
     *
     * @param  Collection<int, JournalEntry>  $candidates
     * @return array{0:float,1:string}
     */
    private function bookAmountFor(Collection $candidates, string $statementCurrency): array
    {
        $fxLines = $candidates->filter(
            fn (JournalEntry $l) => $l->original_currency !== null
                && strtoupper((string) $l->original_currency) === strtoupper($statementCurrency)
        );

        if ($fxLines->isNotEmpty()) {
            return [round((float) $fxLines->sum('original_amount'), 3), 'original_amount ('.strtoupper($statementCurrency).')'];
        }

        return [round((float) $candidates->sum('credit'), 3), 'base currency credit'];
    }

    /**
     * Exceptions report component: our open DOTW payable lines for this supplier, within the
     * import's period (falling back to the statement's own date span), that never matched ANY
     * line in this import and are not already reconciled from an earlier cycle. This IS
     * "unmatched-ledger" (spec's fourth state) — never a stored row, always a live read.
     *
     * @return Collection<int, JournalEntry>
     */
    public function unmatchedLedgerLines(SupplierStatementImport $import): Collection
    {
        // RV-1: a line's own `matched_journal_entry_id` is only the PRIMARY of its match; an
        // aggregate match covers several payable lines (`matched_journal_entry_ids`). Both sets
        // are "present on the statement" and neither is an open ledger gap.
        $matchedLines = $import->lines()->whereNotNull('matched_journal_entry_id')->get(['matched_journal_entry_id', 'matched_journal_entry_ids']);

        $matchedIds = $matchedLines->pluck('matched_journal_entry_id')->all();

        foreach ($matchedLines as $matchedLine) {
            foreach ((array) $matchedLine->matched_journal_entry_ids as $coveredId) {
                $matchedIds[] = $coveredId;
            }
        }

        $matchedIds = array_values(array_unique(array_map('intval', $matchedIds)));

        $from = $import->period_from?->toDateString() ?? $import->lines()->min('statement_date');
        $to = $import->period_to?->toDateString() ?? $import->lines()->max('statement_date');

        return JournalEntry::withoutGlobalScopes()
            ->where('company_id', $import->company_id)
            ->where('type_reference_id', $import->supplier_id)
            ->where('credit', '>', 0)
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
    public function exceptionsFor(SupplierStatementImport $import): array
    {
        return [
            'unmatched_statement' => $import->lines()->where('state', SupplierStatementImportLine::STATE_UNMATCHED)->orderBy('row_no')->get(),
            'disputed' => $import->lines()->where('state', SupplierStatementImportLine::STATE_DISPUTED)->orderBy('row_no')->get(),
            'unmatched_ledger' => $this->unmatchedLedgerLines($import),
        ];
    }
}
