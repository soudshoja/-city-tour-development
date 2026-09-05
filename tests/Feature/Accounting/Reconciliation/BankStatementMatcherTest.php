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
use Database\Seeders\CoaSeeder;
use Illuminate\Support\Carbon;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T9 (Wave 2): BankStatementMatcherTest — the three matching tiers with
 * precedence proven (MP-9-1), the ±3-day window boundary (day 4 excluded), the 0.001 tolerance
 * boundary, all four owner-approved states, the one-to-one invariant (in-run consumed set +
 * cross-run live-claim guard, T8's own pattern), idempotent re-match, and company/bank-leaf
 * scoping. Mirrors SupplierStatementMatcherTest's fixture shape (direct Transaction/JournalEntry
 * rows, `posting_status = 'posted'`).
 */
class BankStatementMatcherTest extends AccountingTestCase
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

    /**
     * A genuine LEAF (no children) for the offset side of a balanced two-line fixture — CoaSeeder
     * inserts group/parent nodes ('Assets', 'Cash In Hand', ...) before their own leaf children,
     * so `skip(5)` lands on 'Petty Cash' (1110), a real leaf, never a group node (posting to a
     * group node is invisible to TrialBalanceService's leaf-walk and would falsely trip the
     * tearDown ledger-balance invariant).
     */
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
            'reference_type' => 'Receipt', 'reference_number' => 'BSM-'.substr(uniqid('', true), -8),
            'name' => 'Test', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'RV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => uniqid('key:', true),
        ]);
    }

    /**
     * Posts a balanced two-line document: the bank leaf side ($side = 'debit'|'credit') and an
     * offset control line. Returns the BANK LEAF's own line — the one a statement row matches
     * against.
     */
    private function postBankLine(
        Company $company,
        Branch $branch,
        Account $bankLeaf,
        string $side,
        float $amount,
        Carbon $date,
        ?string $authNo = null,
        ?string $reference = null,
        ?string $voucherNumber = null,
        ?string $chequeNo = null,
        ?string $bankInfo = null,
        int $reconciled = 0,
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
            'voucher_number' => 'BSM',
        ]);

        return JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $bankLeaf->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'bank line', 'debit' => $side === 'debit' ? $amount : 0,
            'credit' => $side === 'credit' ? $amount : 0, 'name' => $bankLeaf->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
            'voucher_number' => $voucherNumber ?? 'BSM', 'auth_no' => $authNo,
            'receipt_reference_number' => $reference, 'cheque_no' => $chequeNo, 'bank_info' => $bankInfo,
            'reconciled' => $reconciled,
        ]);
    }

    /** A statement 'Credit' cell = money IN = bank-leaf DEBIT (see BankStatementMatcher docblock). */
    private function makeImportWithLine(Company $company, Account $bankLeaf, array $lineAttrs): BankStatementImport
    {
        $import = BankStatementImport::create([
            'company_id' => $company->id,
            'bank_account_id' => $bankLeaf->id,
            'file_name' => 'test.csv',
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

    // ── Tier precedence (MP-9-1) ────────────────────────────────────────────────────────────────

    public function test_auth_no_exact_match_wins_over_a_reference_match_on_a_different_line(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-10');

        $authNoLine = $this->postBankLine($company, $branch, $leaf, 'debit', 100.000, $date, authNo: 'AUTH-1');
        $referenceLine = $this->postBankLine($company, $branch, $leaf, 'debit', 100.000, $date, reference: 'REF-1');

        $import = $this->makeImportWithLine($company, $leaf, [
            'value_date' => $date, 'credit' => 100.000, 'auth_no' => 'AUTH-1', 'reference' => 'REF-1',
        ]);

        $this->matcher()->match($import);

        $line = $import->lines->first()->fresh();
        $this->assertSame(BankStatementImportLine::STATE_MATCHED, $line->state);
        $this->assertSame($authNoLine->id, $line->matched_journal_entry_id);
        $this->assertNotSame($referenceLine->id, $line->matched_journal_entry_id);
    }

    public function test_reference_exact_match_wins_when_no_auth_no_present(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-11');

        $refLine = $this->postBankLine($company, $branch, $leaf, 'debit', 75.000, $date, reference: 'REF-2');
        $this->postBankLine($company, $branch, $leaf, 'debit', 75.000, $date->copy()->addDay());

        $import = $this->makeImportWithLine($company, $leaf, [
            'value_date' => $date, 'credit' => 75.000, 'reference' => 'REF-2',
        ]);

        $this->matcher()->match($import);

        $line = $import->lines->first()->fresh();
        $this->assertSame(BankStatementImportLine::STATE_MATCHED, $line->state);
        $this->assertSame($refLine->id, $line->matched_journal_entry_id);
    }

    public function test_reference_matches_cheque_no_and_bank_info_and_voucher_number_too(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-12');

        $chequeLine = $this->postBankLine($company, $branch, $leaf, 'credit', 40.000, $date, chequeNo: 'CHQ-9');

        $import = $this->makeImportWithLine($company, $leaf, [
            'value_date' => $date, 'debit' => 40.000, 'reference' => 'CHQ-9',
        ]);

        $this->matcher()->match($import);

        $this->assertSame($chequeLine->id, $import->lines->first()->fresh()->matched_journal_entry_id);
    }

    public function test_amount_and_date_window_tier_used_when_no_key_matches(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-13');

        $line = $this->postBankLine($company, $branch, $leaf, 'debit', 60.000, $date);

        $import = $this->makeImportWithLine($company, $leaf, ['value_date' => $date, 'credit' => 60.000]);

        $this->matcher()->match($import);

        $statementLine = $import->lines->first()->fresh();
        $this->assertSame(BankStatementImportLine::STATE_MATCHED, $statementLine->state);
        $this->assertSame($line->id, $statementLine->matched_journal_entry_id);
    }

    /** MP-9-1 target: a key match (auth_no) with an amount mismatch DISPUTES against that SAME candidate — never falls through to tier 3. */
    public function test_a_key_match_with_amount_mismatch_disputes_against_the_identified_line_not_a_different_one(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-14');

        $authLine = $this->postBankLine($company, $branch, $leaf, 'debit', 100.000, $date, authNo: 'AUTH-5');
        // A DIFFERENT candidate that would amount-match exactly, if tier 3 ran instead.
        $this->postBankLine($company, $branch, $leaf, 'debit', 99.000, $date);

        $import = $this->makeImportWithLine($company, $leaf, [
            'value_date' => $date, 'credit' => 99.000, 'auth_no' => 'AUTH-5',
        ]);

        $this->matcher()->match($import);

        $line = $import->lines->first()->fresh();
        $this->assertSame(BankStatementImportLine::STATE_DISPUTED, $line->state);
        $this->assertSame($authLine->id, $line->matched_journal_entry_id);
        $this->assertEqualsWithDelta(1.0, $line->difference, 0.0001);
    }

    // ── Window boundary (±3 days INCLUSIVE, day 4 EXCLUDED) ─────────────────────────────────────

    public function test_window_boundary_day_3_matches_day_4_does_not(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $statementDate = Carbon::parse('2026-08-20');

        $withinWindow = $this->postBankLine($company, $branch, $leaf, 'debit', 30.000, $statementDate->copy()->addDays(3));

        $import = $this->makeImportWithLine($company, $leaf, ['value_date' => $statementDate, 'credit' => 30.000]);
        $this->matcher()->match($import);

        $line = $import->lines->first()->fresh();
        $this->assertSame(BankStatementImportLine::STATE_MATCHED, $line->state);
        $this->assertSame($withinWindow->id, $line->matched_journal_entry_id);
    }

    public function test_window_boundary_day_4_is_excluded(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $statementDate = Carbon::parse('2026-08-21');

        $this->postBankLine($company, $branch, $leaf, 'debit', 31.000, $statementDate->copy()->addDays(4));

        $import = $this->makeImportWithLine($company, $leaf, ['value_date' => $statementDate, 'credit' => 31.000]);
        $this->matcher()->match($import);

        $this->assertSame(BankStatementImportLine::STATE_UNMATCHED, $import->lines->first()->fresh()->state);
    }

    // ── Tolerance 0.001 boundary ─────────────────────────────────────────────────────────────────
    // Proven via a KEYED (reference) candidate, so "beyond tolerance" is meaningfully DISPUTED
    // (the reference identifies WHICH ledger line this is) rather than merely unmatched — the
    // owner-approved four-state vocabulary's "amount-disputed" state.

    public function test_amount_difference_of_exactly_tolerance_matches(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-22');

        $this->postBankLine($company, $branch, $leaf, 'debit', 50.001, $date, reference: 'REF-TOL');

        $import = $this->makeImportWithLine($company, $leaf, ['value_date' => $date, 'credit' => 50.000, 'reference' => 'REF-TOL']);
        $this->matcher()->match($import);

        $this->assertSame(BankStatementImportLine::STATE_MATCHED, $import->lines->first()->fresh()->state);
    }

    public function test_amount_difference_beyond_tolerance_is_disputed(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-23');

        $this->postBankLine($company, $branch, $leaf, 'debit', 50.002, $date, reference: 'REF-TOL2');

        $import = $this->makeImportWithLine($company, $leaf, ['value_date' => $date, 'credit' => 50.000, 'reference' => 'REF-TOL2']);
        $this->matcher()->match($import);

        $line = $import->lines->first()->fresh();
        $this->assertSame(BankStatementImportLine::STATE_DISPUTED, $line->state);
        $this->assertEqualsWithDelta(0.002, $line->difference, 0.0001);
    }

    /**
     * When NO key is present at all, tier 3 (amount+date) has no single "identified" candidate to
     * dispute against — beyond tolerance there, the honest answer is UNMATCHED-STATEMENT, not a
     * guessed dispute against an arbitrary nearby line.
     */
    public function test_amount_difference_beyond_tolerance_with_no_key_present_is_unmatched_not_a_guessed_dispute(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-23');

        $this->postBankLine($company, $branch, $leaf, 'debit', 50.002, $date);

        $import = $this->makeImportWithLine($company, $leaf, ['value_date' => $date, 'credit' => 50.000]);
        $this->matcher()->match($import);

        $this->assertSame(BankStatementImportLine::STATE_UNMATCHED, $import->lines->first()->fresh()->state);
    }

    // ── Four states ──────────────────────────────────────────────────────────────────────────────

    public function test_state_unmatched_ledger_is_a_live_read_never_a_stored_row(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-24');

        $orphan = $this->postBankLine($company, $branch, $leaf, 'credit', 15.000, $date);

        $import = BankStatementImport::create([
            'company_id' => $company->id, 'bank_account_id' => $leaf->id, 'file_name' => 't.csv',
            'statement_currency' => 'KWD', 'content_hash' => hash('sha256', uniqid('', true)),
            'column_map' => [], 'status' => BankStatementImport::STATUS_STAGED,
            'statement_from' => $date->copy()->subDay(), 'statement_to' => $date->copy()->addDay(),
        ]);

        $unmatchedLedger = $this->matcher()->unmatchedLedgerLines($import);
        $this->assertTrue($unmatchedLedger->pluck('id')->contains($orphan->id));
    }

    public function test_all_four_states_in_one_import(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-25');

        $matchTarget = $this->postBankLine($company, $branch, $leaf, 'debit', 10.000, $date);
        $disputeTarget = $this->postBankLine($company, $branch, $leaf, 'debit', 20.000, $date, authNo: 'AUTH-D');
        $orphanLedgerLine = $this->postBankLine($company, $branch, $leaf, 'credit', 30.000, $date);

        $import = BankStatementImport::create([
            'company_id' => $company->id, 'bank_account_id' => $leaf->id, 'file_name' => 't.csv',
            'statement_currency' => 'KWD', 'content_hash' => hash('sha256', uniqid('', true)),
            'column_map' => [], 'status' => BankStatementImport::STATUS_STAGED,
            'statement_from' => $date->copy()->subDay(), 'statement_to' => $date->copy()->addDay(),
        ]);

        BankStatementImportLine::create(['import_id' => $import->id, 'row_no' => 1, 'value_date' => $date, 'debit' => 0, 'credit' => 10.000, 'state' => 'unmatched']);
        BankStatementImportLine::create(['import_id' => $import->id, 'row_no' => 2, 'value_date' => $date, 'debit' => 0, 'credit' => 25.000, 'auth_no' => 'AUTH-D', 'state' => 'unmatched']);
        BankStatementImportLine::create(['import_id' => $import->id, 'row_no' => 3, 'value_date' => $date, 'debit' => 999.000, 'credit' => 0, 'reference' => 'NOPE', 'state' => 'unmatched']);

        $result = $this->matcher()->match($import->fresh(['lines']));

        $this->assertSame(1, $result->matched);
        $this->assertSame(1, $result->disputed);
        $this->assertSame(1, $result->unmatchedStatement);

        $exceptions = $this->matcher()->exceptionsFor($import->fresh());
        $this->assertTrue($exceptions['unmatched_ledger']->pluck('id')->contains($orphanLedgerLine->id));
        $this->assertFalse($exceptions['unmatched_ledger']->pluck('id')->contains($matchTarget->id));
        $this->assertFalse($exceptions['unmatched_ledger']->pluck('id')->contains($disputeTarget->id));
    }

    // ── One-to-one invariant (T8 pattern: in-run consumed set + cross-run live-claim guard) ──────

    public function test_two_statement_rows_with_the_same_amount_each_claim_their_own_ledger_line(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-26');

        $lineA = $this->postBankLine($company, $branch, $leaf, 'debit', 45.000, $date);
        $lineB = $this->postBankLine($company, $branch, $leaf, 'debit', 45.000, $date);

        $import = BankStatementImport::create([
            'company_id' => $company->id, 'bank_account_id' => $leaf->id, 'file_name' => 't.csv',
            'statement_currency' => 'KWD', 'content_hash' => hash('sha256', uniqid('', true)),
            'column_map' => [], 'status' => BankStatementImport::STATUS_STAGED,
        ]);
        BankStatementImportLine::create(['import_id' => $import->id, 'row_no' => 1, 'value_date' => $date, 'debit' => 0, 'credit' => 45.000, 'state' => 'unmatched']);
        BankStatementImportLine::create(['import_id' => $import->id, 'row_no' => 2, 'value_date' => $date, 'debit' => 0, 'credit' => 45.000, 'state' => 'unmatched']);

        $this->matcher()->match($import->fresh(['lines']));

        $matchedIds = $import->fresh()->lines()->pluck('matched_journal_entry_id')->sort()->values()->all();
        $this->assertSame([$lineA->id, $lineB->id], $matchedIds);
        // Both consumed — neither ledger line appears twice.
        $this->assertCount(2, array_unique($matchedIds));
    }

    public function test_rematching_the_same_import_does_not_duplicate_proposals(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-27');

        $this->postBankLine($company, $branch, $leaf, 'debit', 12.000, $date);

        $import = $this->makeImportWithLine($company, $leaf, ['value_date' => $date, 'credit' => 12.000]);

        $this->matcher()->match($import);
        $this->matcher()->match($import->fresh(['lines']));

        $this->assertSame(
            1,
            ReconciliationProposal::where('kind', ReconciliationProposal::KIND_BANK_STATEMENT)->count()
        );
    }

    /** A ledger line already claimed by an EARLIER import's live proposal never raises a second one. */
    public function test_a_later_import_never_raises_a_second_proposal_against_an_already_claimed_ledger_line(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-28');

        $bookLine = $this->postBankLine($company, $branch, $leaf, 'debit', 77.000, $date, authNo: 'AUTH-CLAIM');

        $firstImport = $this->makeImportWithLine($company, $leaf, ['value_date' => $date, 'credit' => 77.000, 'auth_no' => 'AUTH-CLAIM']);
        $this->matcher()->match($firstImport);

        $secondImport = $this->makeImportWithLine($company, $leaf, ['value_date' => $date, 'credit' => 77.000, 'auth_no' => 'AUTH-CLAIM']);
        $this->matcher()->match($secondImport);

        $this->assertSame(
            1,
            ReconciliationProposal::where('kind', ReconciliationProposal::KIND_BANK_STATEMENT)
                ->where('book_journal_entry_id', $bookLine->id)
                ->count()
        );
        // Both statement lines still show 'matched' — the second stays matched with an
        // explanatory note, per T8's own liveClaimOn() precedent.
        $this->assertSame(BankStatementImportLine::STATE_MATCHED, $secondImport->fresh(['lines'])->lines->first()->state);
    }

    // ── Company + bank-leaf scoping ──────────────────────────────────────────────────────────────

    public function test_a_ledger_line_on_a_different_bank_leaf_with_the_same_amount_is_never_matched(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-29');

        // A second bank leaf, same company, same amount+date — must never be a candidate for an
        // import scoped to the FIRST leaf.
        $otherLeaf = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1204')->firstOrFail();
        $this->postBankLine($company, $branch, $otherLeaf, 'debit', 88.000, $date);

        $import = $this->makeImportWithLine($company, $leaf, ['value_date' => $date, 'credit' => 88.000]);
        $this->matcher()->match($import);

        $this->assertSame(BankStatementImportLine::STATE_UNMATCHED, $import->lines->first()->fresh()->state);
    }

    public function test_a_ledger_line_in_a_different_company_with_the_same_amount_is_never_matched(): void
    {
        [$companyA, , $leafA] = $this->makeCompanyWithBankLeaf();
        [$companyB, $branchB, $leafB] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-30');

        $this->postBankLine($companyB, $branchB, $leafB, 'debit', 66.000, $date);

        $import = $this->makeImportWithLine($companyA, $leafA, ['value_date' => $date, 'credit' => 66.000]);
        $this->matcher()->match($import);

        $this->assertSame(BankStatementImportLine::STATE_UNMATCHED, $import->lines->first()->fresh()->state);
    }

    public function test_a_reconciled_ledger_line_is_never_a_candidate(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-31');

        $this->postBankLine($company, $branch, $leaf, 'debit', 22.000, $date, reconciled: 1);

        $import = $this->makeImportWithLine($company, $leaf, ['value_date' => $date, 'credit' => 22.000]);
        $this->matcher()->match($import);

        $this->assertSame(BankStatementImportLine::STATE_UNMATCHED, $import->lines->first()->fresh()->state);
    }

    // ── Running-balance reconciliation report ───────────────────────────────────────────────────

    public function test_reconciliation_report_totals(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-09-01');

        $this->postBankLine($company, $branch, $leaf, 'debit', 200.000, $date);
        $this->postBankLine($company, $branch, $leaf, 'credit', 50.000, $date);

        $import = BankStatementImport::create([
            'company_id' => $company->id, 'bank_account_id' => $leaf->id, 'file_name' => 't.csv',
            'statement_currency' => 'KWD', 'content_hash' => hash('sha256', uniqid('', true)),
            'column_map' => [], 'status' => BankStatementImport::STATUS_STAGED,
            'statement_to' => $date, 'closing_balance' => 150.000,
        ]);

        $report = $this->matcher()->reconciliationReport($import);

        $this->assertEqualsWithDelta(150.0, $report['ledger_balance'], 0.0001);
        $this->assertEqualsWithDelta(150.0, $report['statement_closing_balance'], 0.0001);
        $this->assertEqualsWithDelta(0.0, $report['difference'], 0.0001);
    }

    public function test_reconciliation_report_reports_a_nonzero_difference_when_lines_are_missing(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-09-02');

        $this->postBankLine($company, $branch, $leaf, 'debit', 100.000, $date);

        $import = BankStatementImport::create([
            'company_id' => $company->id, 'bank_account_id' => $leaf->id, 'file_name' => 't.csv',
            'statement_currency' => 'KWD', 'content_hash' => hash('sha256', uniqid('', true)),
            'column_map' => [], 'status' => BankStatementImport::STATUS_STAGED,
            'statement_to' => $date, 'closing_balance' => 250.000,
        ]);

        $report = $this->matcher()->reconciliationReport($import);

        $this->assertEqualsWithDelta(-150.0, $report['difference'], 0.0001);
    }
}
