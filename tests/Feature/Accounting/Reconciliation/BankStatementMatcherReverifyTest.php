<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\Reconciliation;

use App\Models\Account;
use App\Models\BankStatementImport;
use App\Models\BankStatementImportLine;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\ReconciliationProposal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\Reconciliation\BankStatementMatcher;
use App\Services\Accounting\ReconciliationAutoMatchService;
use App\Services\Accounting\ReconciliationProposalService;
use Database\Seeders\CoaSeeder;
use Illuminate\Support\Carbon;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T9 — POST-FIX RE-VERIFY of commit 4e96c08d's idempotent short-circuit.
 *
 * The fix made {@see BankStatementMatcher::matchLine()} skip recomputation for a MATCHED line
 * with a still-LIVE proposal. This file attacks the four blind spots that short-circuit opens:
 *   1. the LEDGER side changing under a live claim (reversal/void/soft-delete) — a stale match
 *      must never stay silently 'matched';
 *   2. a corrected re-import (content-conflict path) inheriting the old import's match;
 *   3. rejected -> allowed, with at most ONE live proposal per statement line and the rejected
 *      one preserved as history;
 *   4. nightly-sweep idempotency across a mixed approved/pending/rejected/unmatched set;
 *   5. CROSS-KIND claims — a bank-leaf ledger line already claimed by a live proposal of a
 *      DIFFERENT kind must not be claimed a second time by the bank-statement matcher, and
 *      vice versa.
 */
class BankStatementMatcherReverifyTest extends AccountingTestCase
{
    private function matcher(): BankStatementMatcher
    {
        return app(BankStatementMatcher::class);
    }

    /** @return array{0: Company, 1: Branch, 2: Account} */
    private function makeCompanyWithBankLeaf(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        $this->trackCompanyForInvariants($company->id);

        $leaf = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();

        return [$company, $branch, $leaf];
    }

    private function offsetLeaf(int $companyId, int $skip = 6): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->orderBy('id')->skip($skip)->take(1)->firstOrFail();
    }

    private function makeTransaction(Company $company, Branch $branch, Carbon $date, float $amount): Transaction
    {
        return Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'RV', 'amount' => $amount, 'description' => 'Test bank line',
            'reference_type' => 'Receipt', 'reference_number' => 'RVF-'.substr(uniqid('', true), -8),
            'name' => 'Test', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'RV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => uniqid('key:', true),
        ]);
    }

    private function postBankLine(
        Company $company,
        Branch $branch,
        Account $bankLeaf,
        string $side,
        float $amount,
        Carbon $date,
        ?string $authNo = null,
        ?string $chequeNo = null,
        ?Carbon $chequeClearanceDate = null,
    ): JournalEntry {
        $txn = $this->makeTransaction($company, $branch, $date, $amount);
        $offset = $this->offsetLeaf($company->id);
        $offsetSide = $side === 'debit' ? 'credit' : 'debit';

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $offset->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'offset', 'debit' => $offsetSide === 'debit' ? $amount : 0,
            'credit' => $offsetSide === 'credit' ? $amount : 0, 'name' => $offset->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
            'voucher_number' => 'RVF',
        ]);

        return JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $bankLeaf->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'bank line', 'debit' => $side === 'debit' ? $amount : 0,
            'credit' => $side === 'credit' ? $amount : 0, 'name' => $bankLeaf->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
            'voucher_number' => 'RVF', 'auth_no' => $authNo, 'cheque_no' => $chequeNo,
            'cheque_clearance_date' => $chequeClearanceDate,
            'reconciled' => 0,
        ]);
    }

    /** @param array<string,mixed> $lineAttrs */
    private function makeImportWithLine(Company $company, Account $bankLeaf, array $lineAttrs): BankStatementImport
    {
        $import = BankStatementImport::create([
            'company_id' => $company->id,
            'bank_account_id' => $bankLeaf->id,
            'file_name' => 'reverify.csv',
            'statement_currency' => 'KWD',
            'content_hash' => hash('sha256', uniqid('', true)),
            'column_map' => ['value_date' => 'Value Date'],
            'status' => BankStatementImport::STATUS_STAGED,
        ]);

        BankStatementImportLine::create(array_merge([
            'import_id' => $import->id,
            'row_no' => 1,
            'debit' => 0,
            'credit' => 0,
            'state' => BankStatementImportLine::STATE_UNMATCHED,
        ], $lineAttrs));

        return $import->fresh(['lines']);
    }

    // ── 1. The ledger side changed under a live claim ───────────────────────────────────────────

    /**
     * RV-1. A statement line matched with a still-PENDING proposal, whose matched ledger line is
     * then REVERSED (PostingService::reverse() stamps `posting_status = 'reversed'` on the
     * original transaction — line 1741 — and posts a contra document; the original line itself is
     * NOT soft-deleted and keeps `reconciled = 0`, so nothing else stops this).
     *
     * The 4e96c08d short-circuit fires on `state === matched && a live proposal exists` alone. It
     * asks nothing about whether the CLAIMED LEDGER LINE is still a valid candidate. A stale match
     * pointing at reversed (i.e. no-longer-posted) evidence must NOT survive a re-match as a
     * silent, unqualified 'matched' — the reconciliation report and the period-close checklist
     * would both read it as settled when the money movement it evidences has been backed out.
     */
    public function test_a_rematch_does_not_leave_a_line_matched_against_a_reversed_ledger_line(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-19');

        $bookLine = $this->postBankLine($company, $branch, $leaf, 'debit', 120.000, $date, authNo: 'AUTH-REV-1');
        $import = $this->makeImportWithLine($company, $leaf, [
            'value_date' => $date, 'credit' => 120.000, 'auth_no' => 'AUTH-REV-1',
        ]);

        $this->matcher()->match($import);
        $line = $import->fresh(['lines'])->lines->first();
        $this->assertSame(BankStatementImportLine::STATE_MATCHED, $line->state, 'precondition');
        $this->assertSame(1, ReconciliationProposal::where('book_journal_entry_id', $bookLine->id)->count(), 'precondition');

        // The ledger side changes: the document is reversed. This is exactly the post-condition
        // PostingService::reverse() leaves on the ORIGINAL transaction.
        Transaction::withoutGlobalScopes()->where('id', $bookLine->transaction_id)
            ->update(['posting_status' => 'reversed']);

        $this->matcher()->match($import->fresh(['lines']));

        $line = $import->fresh(['lines'])->lines->first();
        $this->assertNotSame(
            BankStatementImportLine::STATE_MATCHED,
            $line->state,
            'a statement line whose matched ledger line has been reversed must not stay silently matched'
        );

        // The ruling: DISPUTED (an owner-approved state, so it surfaces in exceptionsFor() and
        // re-trips the close WARN), the vanished JE retained for traceability, the live proposal
        // untouched — the matcher never decides proposals.
        $this->assertSame(BankStatementImportLine::STATE_DISPUTED, $line->state);
        $this->assertSame($bookLine->id, $line->matched_journal_entry_id);
        $this->assertStringContainsString('no longer a live posted line', (string) $line->note);
        $this->assertSame(
            ReconciliationProposal::STATUS_PENDING,
            (string) ReconciliationProposal::where('book_journal_entry_id', $bookLine->id)->firstOrFail()->status
        );

        // And it must NOT claim a second ledger line, nor churn, on every subsequent sweep.
        $before = [$line->state, $line->matched_journal_entry_id, $line->note];
        $this->matcher()->match($import->fresh(['lines']));
        $again = $import->fresh(['lines'])->lines->first();
        $this->assertSame($before, [$again->state, $again->matched_journal_entry_id, $again->note]);
        $this->assertSame(1, ReconciliationProposal::where('company_id', $company->id)->count());
    }

    /**
     * RV-1b, the same hazard reached through the other realistic route: the matched ledger line is
     * SOFT-DELETED (the delete-and-recreate edit path P3 repairs). Every tier query excludes
     * `deleted_at`, so the claim is pointing at a row the matcher itself would refuse to consider.
     */
    public function test_a_rematch_does_not_leave_a_line_matched_against_a_soft_deleted_ledger_line(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-20');

        $bookLine = $this->postBankLine($company, $branch, $leaf, 'debit', 130.000, $date, authNo: 'AUTH-REV-2');
        $import = $this->makeImportWithLine($company, $leaf, [
            'value_date' => $date, 'credit' => 130.000, 'auth_no' => 'AUTH-REV-2',
        ]);

        $this->matcher()->match($import);
        $this->assertSame(BankStatementImportLine::STATE_MATCHED, $import->fresh(['lines'])->lines->first()->state);

        // Soft-delete the WHOLE document (both legs) — a half-deleted document would break the
        // suite's own trial-balance invariant and is not what the edit path produces.
        JournalEntry::withoutGlobalScopes()->where('transaction_id', $bookLine->transaction_id)
            ->update(['deleted_at' => now()]);

        $this->matcher()->match($import->fresh(['lines']));

        $line = $import->fresh(['lines'])->lines->first();
        $this->assertSame(
            BankStatementImportLine::STATE_DISPUTED,
            $line->state,
            'a statement line whose matched ledger line has been soft-deleted must not stay silently matched'
        );
        $this->assertStringContainsString('no longer a live posted line', (string) $line->note);
    }

    /**
     * RV-1c, the control case that must NOT regress: the ledger line is perfectly healthy and the
     * proposal is APPROVED (so `reconciled = 1` hides it from every tier query). This is the exact
     * scenario 4e96c08d fixed; the RV-1 hardening must not reintroduce the flip.
     */
    public function test_a_rematch_after_approval_still_keeps_a_healthy_line_matched(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-21');
        $actor = User::factory()->create();

        $bookLine = $this->postBankLine($company, $branch, $leaf, 'debit', 140.000, $date, authNo: 'AUTH-OK-1');
        $import = $this->makeImportWithLine($company, $leaf, [
            'value_date' => $date, 'credit' => 140.000, 'auth_no' => 'AUTH-OK-1',
        ]);

        $this->matcher()->match($import);
        $proposal = ReconciliationProposal::where('book_journal_entry_id', $bookLine->id)->firstOrFail();
        app(ReconciliationProposalService::class)->approve($proposal, $actor);

        $this->matcher()->match($import->fresh(['lines']));

        $line = $import->fresh(['lines'])->lines->first();
        $this->assertSame(BankStatementImportLine::STATE_MATCHED, $line->state);
        $this->assertSame($bookLine->id, $line->matched_journal_entry_id);
        $this->assertSame(1, ReconciliationProposal::where('book_journal_entry_id', $bookLine->id)->count());
    }

    // ── 2. Corrected re-import (content-conflict path) ──────────────────────────────────────────

    /**
     * RV-2. The operator re-uploads the statement with a CORRECTED amount for the same movement.
     * A corrected file has a different content hash, so it lands as a NEW import with NEW line ids
     * — the short-circuit is keyed on the line's own id, so no state can leak. What must also hold
     * is that the new import's line is judged on its OWN evidence and never silently inherits the
     * old import's proposal: the old live claim on that ledger line must stop a SECOND live
     * proposal being raised (the cross-run one-to-one guard).
     */
    public function test_a_corrected_re_import_never_inherits_the_old_imports_proposal(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-22');

        $bookLine = $this->postBankLine($company, $branch, $leaf, 'debit', 200.000, $date, authNo: 'AUTH-CONF-1');

        $first = $this->makeImportWithLine($company, $leaf, [
            'value_date' => $date, 'credit' => 200.000, 'auth_no' => 'AUTH-CONF-1',
        ]);
        $this->matcher()->match($first);
        $firstLine = $first->fresh(['lines'])->lines->first();
        $this->assertSame(BankStatementImportLine::STATE_MATCHED, $firstLine->state);

        // Corrected file: same movement, amount corrected beyond tolerance -> a NEW import.
        $second = $this->makeImportWithLine($company, $leaf, [
            'value_date' => $date, 'credit' => 205.000, 'auth_no' => 'AUTH-CONF-1',
        ]);
        $this->matcher()->match($second);
        $secondLine = $second->fresh(['lines'])->lines->first();

        // The corrected row disagrees with the ledger by 5.000 — it must be judged on its own
        // evidence (disputed), never inherit the first import's clean match.
        $this->assertSame(BankStatementImportLine::STATE_DISPUTED, $secondLine->state);
        $this->assertEqualsWithDelta(-5.0, (float) $secondLine->difference, 0.0001);

        // And exactly ONE live proposal exists against that ledger line across both imports.
        $live = ReconciliationProposal::where('book_journal_entry_id', $bookLine->id)
            ->whereIn('status', [ReconciliationProposal::STATUS_PENDING, ReconciliationProposal::STATUS_APPROVED])
            ->get();
        $this->assertCount(1, $live);
        $this->assertSame('bank_stmt_line:'.$firstLine->id, $live->first()->matched_reference);
    }

    // ── 3. Rejected -> allowed, with at most one LIVE proposal per line ─────────────────────────

    /**
     * RV-3. After rejection the same line re-matches and raises a NEW proposal; the rejected one
     * survives as history; the count of LIVE proposals for that line never exceeds 1, and a third
     * re-match adds nothing further.
     */
    public function test_rejection_history_is_kept_and_at_most_one_live_proposal_exists_per_line(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-23');
        $actor = User::factory()->create();

        $bookLine = $this->postBankLine($company, $branch, $leaf, 'debit', 55.000, $date, authNo: 'AUTH-REJ-1');
        $import = $this->makeImportWithLine($company, $leaf, [
            'value_date' => $date, 'credit' => 55.000, 'auth_no' => 'AUTH-REJ-1',
        ]);

        $this->matcher()->match($import);
        $lineId = (int) $import->fresh(['lines'])->lines->first()->id;
        $reference = 'bank_stmt_line:'.$lineId;

        $first = ReconciliationProposal::where('matched_reference', $reference)->firstOrFail();
        app(ReconciliationProposalService::class)->reject($first, 'not this evidence', $actor);

        $this->matcher()->match($import->fresh(['lines']));
        $this->matcher()->match($import->fresh(['lines']));

        $all = ReconciliationProposal::where('matched_reference', $reference)->orderBy('id')->get();
        $this->assertCount(2, $all, 'the rejected proposal is kept as history, plus exactly one fresh one');
        $this->assertSame(ReconciliationProposal::STATUS_REJECTED, $all->first()->status);
        $this->assertSame('not this evidence', $all->first()->reason);

        $live = $all->filter(fn (ReconciliationProposal $p) => in_array(
            $p->status,
            [ReconciliationProposal::STATUS_PENDING, ReconciliationProposal::STATUS_APPROVED],
            true
        ));
        $this->assertCount(1, $live, 'at most ONE live proposal per statement line, however many re-matches run');
        $this->assertSame($bookLine->id, (int) $live->first()->book_journal_entry_id);
    }

    // ── 4. Nightly sweep idempotency over a mixed set ───────────────────────────────────────────

    /**
     * RV-4. Two consecutive {@see ReconciliationAutoMatchService::run()} sweeps over an import
     * carrying an APPROVED, a PENDING, a REJECTED-then-rematched and a genuinely UNMATCHED line
     * must leave byte-identical state: no line flips, no duplicate proposals, no change in the
     * checklist's unmatched count.
     */
    public function test_the_nightly_sweep_is_idempotent_over_approved_pending_rejected_and_unmatched_lines(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-24');
        $actor = User::factory()->create();

        $approvedLine = $this->postBankLine($company, $branch, $leaf, 'debit', 11.000, $date, authNo: 'SW-APP');
        $pendingLine = $this->postBankLine($company, $branch, $leaf, 'debit', 12.000, $date, authNo: 'SW-PEN');
        $rejectedLine = $this->postBankLine($company, $branch, $leaf, 'debit', 13.000, $date, authNo: 'SW-REJ');

        $import = BankStatementImport::create([
            'company_id' => $company->id, 'bank_account_id' => $leaf->id, 'file_name' => 'sweep.csv',
            'statement_currency' => 'KWD', 'content_hash' => hash('sha256', uniqid('', true)),
            'column_map' => [], 'status' => BankStatementImport::STATUS_STAGED,
        ]);
        foreach ([
            [1, 11.000, 'SW-APP'],
            [2, 12.000, 'SW-PEN'],
            [3, 13.000, 'SW-REJ'],
            [4, 99.000, 'SW-NONE'], // no ledger counterpart at all -> stays unmatched forever
        ] as [$rowNo, $amount, $auth]) {
            BankStatementImportLine::create([
                'import_id' => $import->id, 'row_no' => $rowNo, 'value_date' => $date,
                'debit' => 0, 'credit' => $amount, 'auth_no' => $auth,
                'state' => BankStatementImportLine::STATE_UNMATCHED,
            ]);
        }

        $service = app(ReconciliationProposalService::class);

        $this->matcher()->match($import->fresh(['lines']));
        $service->approve(
            ReconciliationProposal::where('book_journal_entry_id', $approvedLine->id)->firstOrFail(),
            $actor
        );
        $service->reject(
            ReconciliationProposal::where('book_journal_entry_id', $rejectedLine->id)->firstOrFail(),
            'operator disagreed',
            $actor
        );

        $auto = app(ReconciliationAutoMatchService::class);
        $auto->run($company->id);
        $afterFirst = $this->sweepSnapshot($company->id, (int) $import->id);

        $auto->run($company->id);
        $afterSecond = $this->sweepSnapshot($company->id, (int) $import->id);

        $this->assertSame($afterFirst, $afterSecond, 'a second nightly sweep must be a pure no-op');
        $this->assertSame(
            BankStatementImportLine::STATE_MATCHED,
            (string) $import->fresh(['lines'])->lines->firstWhere('row_no', 1)->state,
            'the approved line stays matched'
        );
        $this->assertSame(
            BankStatementImportLine::STATE_UNMATCHED,
            (string) $import->fresh(['lines'])->lines->firstWhere('row_no', 4)->state,
            'the genuinely unmatched line stays unmatched'
        );
        $this->assertSame(0, (int) $pendingLine->fresh()->reconciled, 'a pending proposal never reconciles its line');
    }

    /** @return array<string,mixed> a full comparable snapshot of everything a sweep could change. */
    private function sweepSnapshot(int $companyId, int $importId): array
    {
        $lines = BankStatementImportLine::where('import_id', $importId)->orderBy('row_no')->get()
            ->map(fn (BankStatementImportLine $l) => [
                'row' => (int) $l->row_no,
                'state' => (string) $l->state,
                'je' => $l->matched_journal_entry_id === null ? null : (int) $l->matched_journal_entry_id,
                'difference' => (string) $l->difference,
                'note' => (string) $l->note,
            ])->all();

        $proposals = ReconciliationProposal::where('company_id', $companyId)->orderBy('id')->get()
            ->map(fn (ReconciliationProposal $p) => [
                'id' => (int) $p->id,
                'kind' => (string) $p->kind,
                'status' => (string) $p->status,
                'book' => (int) $p->book_journal_entry_id,
                'reference' => (string) $p->matched_reference,
            ])->all();

        $reconciled = JournalEntry::withoutGlobalScopes()->where('company_id', $companyId)
            ->orderBy('id')->pluck('reconciled', 'id')->map(fn ($v) => (int) $v)->all();

        return ['lines' => $lines, 'proposals' => $proposals, 'reconciled' => $reconciled];
    }

    // ── 5. Cross-kind claim safety ──────────────────────────────────────────────────────────────

    /**
     * RV-5a. A bank-leaf ledger line already claimed by a LIVE proposal of a DIFFERENT kind must
     * not be claimed a second time by the bank-statement matcher.
     *
     * This is not hypothetical: {@see \App\Services\Accounting\ReconciliationAutoMatchService::
     * detectClearingRollforward()} sweeps exactly the same bank/cash leaves for lines whose
     * `cheque_clearance_date` has passed, and runs FIRST inside the same `run()`. A cleared cheque
     * that also appears on the bank statement — the single most ordinary bank-reconciliation event
     * there is — hits both detectors. `BankStatementMatcher::liveClaimOn()` filters on
     * `kind = bank_statement`, so it cannot see the clearing proposal; two live proposals then
     * claim the same ledger line and it can be reconciled twice, against two different
     * counterparts, with `reconciled_ref_id` ending up as whichever was approved last.
     */
    public function test_a_ledger_line_already_claimed_by_another_kinds_live_proposal_is_not_claimed_again(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-25');

        $bookLine = $this->postBankLine(
            $company, $branch, $leaf, 'debit', 77.000, $date,
            authNo: 'AUTH-XK-1', chequeNo: 'CHQ-XK-1', chequeClearanceDate: $date
        );

        // What detectClearingRollforward() writes for a cleared cheque, verbatim in shape.
        ReconciliationProposal::create([
            'company_id' => $company->id,
            'account_id' => $leaf->id,
            'source' => 'internal',
            'kind' => ReconciliationProposal::KIND_CLEARING_ROLLFORWARD,
            'confidence' => ReconciliationProposal::CONFIDENCE_EXACT,
            'book_journal_entry_id' => $bookLine->id,
            'matched_reference' => 'cheque_cleared:CHQ-XK-1',
            'amount' => 77.000,
            'difference_amount' => 0,
            'status' => ReconciliationProposal::STATUS_PENDING,
        ]);

        $import = $this->makeImportWithLine($company, $leaf, [
            'value_date' => $date, 'credit' => 77.000, 'auth_no' => 'AUTH-XK-1',
        ]);

        $this->matcher()->match($import);

        $live = ReconciliationProposal::where('book_journal_entry_id', $bookLine->id)
            ->whereIn('status', [ReconciliationProposal::STATUS_PENDING, ReconciliationProposal::STATUS_APPROVED])
            ->get();

        $this->assertCount(
            1,
            $live,
            'a ledger line must carry at most ONE live claim regardless of which detector raised it'
        );
        $this->assertSame(ReconciliationProposal::KIND_CLEARING_ROLLFORWARD, (string) $live->first()->kind);
    }

    /**
     * RV-5b, the reverse direction — already safe, pinned so it stays safe: the internal detectors
     * skip a line that already carries any PENDING proposal ({@see
     * \App\Services\Accounting\ReconciliationAutoMatchService::alreadyPending()} is deliberately
     * NOT kind-scoped), so a line claimed by a live bank_statement proposal is not re-claimed by
     * the clearing-rollforward detector.
     */
    public function test_an_internal_detector_does_not_reclaim_a_line_already_claimed_by_a_bank_statement_proposal(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-26');

        $bookLine = $this->postBankLine(
            $company, $branch, $leaf, 'debit', 88.000, $date,
            authNo: 'AUTH-XK-2', chequeNo: 'CHQ-XK-2', chequeClearanceDate: $date
        );

        $import = $this->makeImportWithLine($company, $leaf, [
            'value_date' => $date, 'credit' => 88.000, 'auth_no' => 'AUTH-XK-2',
        ]);
        $this->matcher()->match($import);

        $this->assertSame(1, ReconciliationProposal::where('book_journal_entry_id', $bookLine->id)->count(), 'precondition');

        app(ReconciliationAutoMatchService::class)->run($company->id);

        $live = ReconciliationProposal::where('book_journal_entry_id', $bookLine->id)
            ->whereIn('status', [ReconciliationProposal::STATUS_PENDING, ReconciliationProposal::STATUS_APPROVED])
            ->get();

        $this->assertCount(1, $live);
        $this->assertSame(ReconciliationProposal::KIND_BANK_STATEMENT, (string) $live->first()->kind);
    }

    // ── 6. FINAL RE-VERIFY (loop 3) — recovery, predicate coverage, cross-kind approval ─────────

    /**
     * FV-1 (recovery path). The RV-1 ruling settles a stale claim DISPUTED and deliberately does
     * NOT fall through to the tiers. The question that leaves open is whether an operator can ever
     * get OUT of that state when the ledger side comes back — the document was reposted
     * ({@see \App\Services\Accounting\PostingService::repost()} = reverse the original + post a
     * replacement), which is the ordinary way an edited receipt looks afterwards.
     *
     * RULING (derived, and pinned here): the matcher must NOT auto-follow the `:repost:`
     * replacement the way {@see \App\Services\Accounting\RealisedFxService::resolveLiveSourceTransaction()}
     * (T1) does. T1 is a headless posting feeder with no human in the loop — a skip there means the
     * books silently miss a real gain/loss, so it must resolve the chain itself. Here the opposite
     * holds: the stale proposal is a LIVE claim on a ledger line and the matcher never decides
     * proposals (L13 — approve/reject is the reconciliation centre's job). Auto-re-matching would
     * have to either supersede that live claim (deciding it) or raise a SECOND live claim (breaking
     * the one-to-one invariant). A repost can also change the amount, which is exactly the case a
     * human must see. So: DISPUTED + a note that names the proposal to reject, and the recovery is
     * the operator's REJECT — after which the very next sweep matches the replacement with no
     * further intervention. That recovery is what this test proves actually works end to end;
     * without it the disputed state would be a dead end.
     */
    public function test_a_stale_disputed_line_recovers_via_reject_then_rematch_against_the_replacement(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-27');
        $actor = User::factory()->create();

        $original = $this->postBankLine($company, $branch, $leaf, 'debit', 310.000, $date, authNo: 'AUTH-FV-1');
        $import = $this->makeImportWithLine($company, $leaf, [
            'value_date' => $date, 'credit' => 310.000, 'auth_no' => 'AUTH-FV-1',
        ]);

        $this->matcher()->match($import);
        $this->assertSame(BankStatementImportLine::STATE_MATCHED, $import->fresh(['lines'])->lines->first()->state, 'precondition');

        // The document is reposted: the ORIGINAL is stamped 'reversed', a REPLACEMENT is posted
        // carrying the same evidence (same auth_no, same amount, same date).
        Transaction::withoutGlobalScopes()->where('id', $original->transaction_id)->update(['posting_status' => 'reversed']);
        $replacement = $this->postBankLine($company, $branch, $leaf, 'debit', 310.000, $date, authNo: 'AUTH-FV-1');

        // Sweep 1: DISPUTED, and deliberately NOT re-derived against the replacement even though a
        // perfect candidate now exists — the stale proposal is still live.
        $this->matcher()->match($import->fresh(['lines']));
        $line = $import->fresh(['lines'])->lines->first();
        $this->assertSame(BankStatementImportLine::STATE_DISPUTED, $line->state);
        $this->assertSame($original->id, $line->matched_journal_entry_id);
        $this->assertSame(1, ReconciliationProposal::where('company_id', $company->id)->count(), 'no second claim while the stale one is live');

        // The operator does what the note tells them to: reject the stale proposal.
        $stale = ReconciliationProposal::where('matched_reference', 'bank_stmt_line:'.$line->id)
            ->where('status', ReconciliationProposal::STATUS_PENDING)->firstOrFail();
        $this->assertStringContainsString('#'.$stale->id, (string) $line->note, 'the note names the proposal to reject');
        app(ReconciliationProposalService::class)->reject($stale, 'ledger line was reversed and reposted', $actor);

        // Sweep 2: recovery — the line matches the REPLACEMENT, with a fresh proposal.
        $this->matcher()->match($import->fresh(['lines']));
        $line = $import->fresh(['lines'])->lines->first();

        $this->assertSame(BankStatementImportLine::STATE_MATCHED, $line->state, 'the disputed state must not be a dead end');
        $this->assertSame($replacement->id, $line->matched_journal_entry_id, 'it re-matches the reposted replacement');
        $this->assertNull($line->note);

        $live = ReconciliationProposal::where('matched_reference', 'bank_stmt_line:'.$line->id)
            ->whereIn('status', [ReconciliationProposal::STATUS_PENDING, ReconciliationProposal::STATUS_APPROVED])->get();
        $this->assertCount(1, $live);
        $this->assertSame($replacement->id, (int) $live->first()->book_journal_entry_id);
        $this->assertSame(
            2,
            ReconciliationProposal::where('matched_reference', 'bank_stmt_line:'.$line->id)->count(),
            'the rejected one is kept as history'
        );
    }

    /**
     * FV-1b. The same recovery when there is NOTHING to re-match against (the document was
     * reversed outright, not reposted): after rejection the line settles UNMATCHED — an honest
     * open item that the exceptions report and the close WARN both surface — rather than being
     * stuck DISPUTED against a JE that no longer exists.
     */
    public function test_a_stale_disputed_line_with_no_replacement_settles_unmatched_after_rejection(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-28');
        $actor = User::factory()->create();

        $original = $this->postBankLine($company, $branch, $leaf, 'debit', 320.000, $date, authNo: 'AUTH-FV-2');
        $import = $this->makeImportWithLine($company, $leaf, [
            'value_date' => $date, 'credit' => 320.000, 'auth_no' => 'AUTH-FV-2',
        ]);
        $this->matcher()->match($import);

        Transaction::withoutGlobalScopes()->where('id', $original->transaction_id)->update(['posting_status' => 'reversed']);
        $this->matcher()->match($import->fresh(['lines']));

        $line = $import->fresh(['lines'])->lines->first();
        $this->assertSame(BankStatementImportLine::STATE_DISPUTED, $line->state, 'precondition');

        $stale = ReconciliationProposal::where('matched_reference', 'bank_stmt_line:'.$line->id)
            ->where('status', ReconciliationProposal::STATUS_PENDING)->firstOrFail();
        app(ReconciliationProposalService::class)->reject($stale, 'reversed, not reposted', $actor);

        $this->matcher()->match($import->fresh(['lines']));
        $line = $import->fresh(['lines'])->lines->first();

        $this->assertSame(BankStatementImportLine::STATE_UNMATCHED, $line->state);
        $this->assertNull($line->matched_journal_entry_id);
        $this->assertSame(
            1,
            ReconciliationProposal::where('matched_reference', 'bank_stmt_line:'.$line->id)->count(),
            'no new claim is invented for a movement with no ledger evidence'
        );
    }

    /**
     * FV-2. The stale-claim predicate's remaining routes. RV-1/RV-1b already pin `reversed` and a
     * soft-deleted JOURNAL LINE; these are the other three shapes the same predicate must trip on:
     * a soft-deleted TRANSACTION (the line rows survive — `whereHas('transaction')` carries the
     * related model's own SoftDeletingScope, which is what catches it), a transaction rolled back
     * to `draft`, and a line MOVED to another bank leaf.
     *
     * The moved-leaf case is the one worth a ruling: it must trip. This import is scoped to ONE
     * bank leaf (`account_id = $import->bank_account_id` in every tier) — a line that now sits on a
     * different leaf is not evidence for THIS statement, whichever leaf it moved to.
     *
     * @dataProvider staleLedgerRoutes
     */
    public function test_every_route_that_makes_a_claimed_line_ineligible_trips_the_stale_check(string $route): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-29');
        $auth = 'AUTH-FV-3-'.substr(md5($route), 0, 6);

        $bookLine = $this->postBankLine($company, $branch, $leaf, 'debit', 410.000, $date, authNo: $auth);
        $import = $this->makeImportWithLine($company, $leaf, [
            'value_date' => $date, 'credit' => 410.000, 'auth_no' => $auth,
        ]);
        $this->matcher()->match($import);
        $this->assertSame(BankStatementImportLine::STATE_MATCHED, $import->fresh(['lines'])->lines->first()->state, 'precondition');

        match ($route) {
            'soft_deleted_transaction' => Transaction::withoutGlobalScopes()
                ->where('id', $bookLine->transaction_id)->update(['deleted_at' => now()]),
            'draft' => Transaction::withoutGlobalScopes()
                ->where('id', $bookLine->transaction_id)->update(['posting_status' => 'draft']),
            'moved_to_another_bank_leaf' => JournalEntry::withoutGlobalScopes()
                ->where('id', $bookLine->id)
                ->update(['account_id' => Account::withoutGlobalScopes()
                    ->where('company_id', $company->id)->where('code', '1204')->firstOrFail()->id]),
        };

        $this->matcher()->match($import->fresh(['lines']));
        $line = $import->fresh(['lines'])->lines->first();

        $this->assertSame(
            BankStatementImportLine::STATE_DISPUTED,
            $line->state,
            "route '{$route}' must make the claim stale, not leave the line silently matched"
        );
        $this->assertStringContainsString('no longer a live posted line', (string) $line->note);
        $this->assertSame($bookLine->id, $line->matched_journal_entry_id, 'the vanished JE is kept for traceability');
    }

    /** @return array<string, array{0:string}> */
    public static function staleLedgerRoutes(): array
    {
        return [
            'soft-deleted transaction' => ['soft_deleted_transaction'],
            'transaction rolled back to draft' => ['draft'],
            'line moved to another bank leaf' => ['moved_to_another_bank_leaf'],
        ];
    }

    /**
     * FV-3. R-2 dropped the `kind` filter from `liveClaimOn()`. The obvious worry is a FALSE
     * positive: a live proposal of some unrelated kind blocking a legitimate bank-statement match.
     * It structurally cannot — `liveClaimOn()` is keyed on `book_journal_entry_id`, and a
     * `supplier_statement` proposal claims a PARTY-scoped payable line, never a bank leaf. Pinned
     * so a future kind that DOES touch bank leaves is a deliberate decision, not an accident.
     */
    public function test_a_supplier_statement_claim_on_a_payable_line_never_blocks_a_bank_leaf_match(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-30');

        $payableLeaf = $this->offsetLeaf($company->id);
        $payableLine = $this->postBankLine($company, $branch, $payableLeaf, 'credit', 500.000, $date);

        ReconciliationProposal::create([
            'company_id' => $company->id,
            'account_id' => $payableLeaf->id,
            'source' => 'external',
            'kind' => ReconciliationProposal::KIND_SUPPLIER_STATEMENT,
            'confidence' => ReconciliationProposal::CONFIDENCE_EXACT,
            'book_journal_entry_id' => $payableLine->id,
            'matched_reference' => 'supplier_stmt_line:999',
            'amount' => 500.000,
            'difference_amount' => 0,
            'status' => ReconciliationProposal::STATUS_PENDING,
        ]);

        $bookLine = $this->postBankLine($company, $branch, $leaf, 'debit', 500.000, $date, authNo: 'AUTH-FV-4');
        $import = $this->makeImportWithLine($company, $leaf, [
            'value_date' => $date, 'credit' => 500.000, 'auth_no' => 'AUTH-FV-4',
        ]);
        $this->matcher()->match($import);

        $line = $import->fresh(['lines'])->lines->first();
        $this->assertSame(BankStatementImportLine::STATE_MATCHED, $line->state);
        $this->assertSame($bookLine->id, $line->matched_journal_entry_id);
        $this->assertNull($line->note, 'a same-amount payable-side claim is not a claim on this bank line');
        $this->assertSame(
            1,
            ReconciliationProposal::where('book_journal_entry_id', $bookLine->id)
                ->where('kind', ReconciliationProposal::KIND_BANK_STATEMENT)->count()
        );
    }

    /**
     * FV-4. The other half of the R-2 block: when the bank-statement matcher correctly DECLINES to
     * raise a second claim on a bank line another kind already claims (here a gateway-payout
     * proposal — a `GWS` payout hitting the same bank leaf), it still records the line as MATCHED
     * against that JE. Approving the OTHER kind's proposal then sets `reconciled = 1`, which hides
     * the candidate from every tier. The statement line must NOT then be reverted to 'unmatched'
     * by the next sweep — that is the very defect 4e96c08d fixed, reached by a route its
     * short-circuit does not cover (there is no bank_statement proposal on this line to be its
     * oracle). Left unfixed, the close checklist re-raises "N statement lines unmatched" every
     * night for a movement that is fully settled.
     */
    public function test_approving_another_kinds_claim_does_not_revert_the_statement_line(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-31');
        $actor = User::factory()->create();

        $bookLine = $this->postBankLine($company, $branch, $leaf, 'debit', 610.000, $date, authNo: 'AUTH-FV-5');

        $foreign = ReconciliationProposal::create([
            'company_id' => $company->id,
            'account_id' => $leaf->id,
            'source' => 'external',
            'kind' => ReconciliationProposal::KIND_GATEWAY_SETTLEMENT,
            'confidence' => ReconciliationProposal::CONFIDENCE_EXACT,
            'book_journal_entry_id' => $bookLine->id,
            'matched_reference' => 'gw_payout:PO-FV-5',
            'amount' => 610.000,
            'difference_amount' => 0,
            'status' => ReconciliationProposal::STATUS_PENDING,
        ]);

        $import = $this->makeImportWithLine($company, $leaf, [
            'value_date' => $date, 'credit' => 610.000, 'auth_no' => 'AUTH-FV-5',
        ]);
        $this->matcher()->match($import);

        $line = $import->fresh(['lines'])->lines->first();
        $this->assertSame(BankStatementImportLine::STATE_MATCHED, $line->state, 'precondition: matched, no second claim raised');
        $this->assertSame($bookLine->id, $line->matched_journal_entry_id);
        $this->assertSame(1, ReconciliationProposal::where('book_journal_entry_id', $bookLine->id)->count(), 'precondition');

        // The operator approves the gateway-settlement proposal — the one live claim on the line.
        app(ReconciliationProposalService::class)->approve($foreign->fresh(), $actor);
        $this->assertSame(1, (int) $bookLine->fresh()->reconciled, 'precondition');

        $this->matcher()->match($import->fresh(['lines']));

        $line = $import->fresh(['lines'])->lines->first();
        $this->assertSame(
            BankStatementImportLine::STATE_MATCHED,
            $line->state,
            'the statement line is settled by the other kind\'s approved claim — it must not flip to unmatched'
        );
        $this->assertSame($bookLine->id, $line->matched_journal_entry_id);
        $this->assertSame(1, ReconciliationProposal::where('book_journal_entry_id', $bookLine->id)->count(), 'still exactly one claim');

        // Stable across further sweeps.
        $before = [$line->state, $line->matched_journal_entry_id, $line->note];
        $this->matcher()->match($import->fresh(['lines']));
        $again = $import->fresh(['lines'])->lines->first();
        $this->assertSame($before, [$again->state, $again->matched_journal_entry_id, $again->note]);
    }
}
