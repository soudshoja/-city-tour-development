<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Models\Account;
use App\Models\BankStatementImport;
use App\Models\BankStatementImportLine;
use App\Models\Branch;
use App\Models\Company;
use App\Models\GatewaySettlement;
use App\Models\JournalEntry;
use App\Models\ReconciliationProposal;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\PeriodCloseChecklistService;
use App\Services\Accounting\ReconciliationAutoMatchService;
use App\Services\Accounting\ReconciliationProposalService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Tests\Support\AccountingTestCase;

/**
 * PHASE GATE DELTA (accounting-builds, lane H merge on tip 6cce9953).
 *
 * The full gate proved GATE-1's cross-kind claim guard against the two kinds that existed then
 * ({@see ReconciliationProposal::KIND_GATEWAY_SETTLEMENT} and
 * {@see ReconciliationProposal::KIND_SUPPLIER_STATEMENT}) — see
 * {@see ReconciliationCrossKindClaimGuardTest}. Lane H then landed
 * {@see ReconciliationProposal::KIND_BANK_STATEMENT}, the THIRD externally-fed kind on the same
 * one id space, one status machine and one approval path, and it is the only kind whose approval
 * ALSO writes a second table ({@see BankStatementImportLine::$state}) and stamps
 * `reconciled_ref_id` with something other than a proposal or journal-entry id (the statement
 * line's own id — see {@see ReconciliationProposalService::approve()}'s T9 branch). None of that
 * existed when the guard was written, so this file re-proves the guard with a bank_statement leg
 * on both sides, plus the two other composition surfaces lane H touches: the nightly sweep now
 * running FIVE detectors, and the period-close checklist's new statement-side WARN row.
 *
 * Oracles, in order: (1) the second approval is refused and the FIRST claim survives byte-for-byte
 * — including the bank_statement kind's statement-line-id reference, which a naive re-stamp would
 * silently replace; (2) a refused bank_statement approval must not flip the statement line's own
 * state, i.e. the guard's early return happens before T9's second write; (3) two consecutive
 * {@see ReconciliationAutoMatchService::run()} calls over a company carrying BOTH a bank statement
 * import and a posted gateway settlement produce an identical proposal set — no kind duplicates
 * another kind's line, and no kind duplicates itself across runs; (4) the new
 * `unmatched_bank_statement_lines` warning ADDS to the pre-existing
 * `unreconciled_bank_cash_lines` rows rather than replacing, duplicating or contradicting them,
 * and neither blocks the close.
 */
class ReconciliationBankStatementCrossLaneDeltaTest extends AccountingTestCase
{
    private function proposals(): ReconciliationProposalService
    {
        return app(ReconciliationProposalService::class);
    }

    /** @return array{0: Company, 1: Branch, 2: Account} */
    private function makeCompanyWithBankLeaf(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        $this->trackCompanyForInvariants($company->id);

        $leaf = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();

        return [$company, $branch, $leaf];
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::ADMIN]);
    }

    /**
     * A balanced two-line JV whose debit leg sits on $target. The debit leg is what proposals
     * compete over; the credit leg only keeps AccountingTestCase's tearDown invariant sweep
     * meaningful.
     */
    private function postClaimableBankLine(
        Company $company,
        Branch $branch,
        Account $target,
        Account $counter,
        float $amount,
        Carbon $date,
        ?string $authNo = null,
    ): JournalEntry {
        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'RV', 'amount' => $amount, 'description' => 'Delta bank receipt',
            'reference_type' => 'Receipt', 'reference_number' => 'DLT-'.substr(uniqid('', true), -8),
            'name' => 'Delta', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'RV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => uniqid('delta:', true),
        ]);

        $line = JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $target->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Delta bank line', 'debit' => $amount, 'credit' => 0, 'name' => $target->name,
            'type' => 'bank', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
            'voucher_number' => 'DLT', 'auth_no' => $authNo, 'reconciled' => 0,
        ]);

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $counter->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Delta counter', 'debit' => 0, 'credit' => $amount, 'name' => $counter->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
            'voucher_number' => 'DLT',
        ]);

        return $line;
    }

    private function stageStatementLine(Company $company, Account $leaf, Carbon $date, float $credit, ?string $authNo = null): BankStatementImportLine
    {
        $import = BankStatementImport::create([
            'company_id' => $company->id, 'bank_account_id' => $leaf->id, 'file_name' => 'delta.csv',
            'statement_currency' => 'KWD', 'content_hash' => hash('sha256', uniqid('', true)),
            'column_map' => [], 'status' => BankStatementImport::STATUS_STAGED,
            'statement_from' => $date->copy()->startOfMonth(), 'statement_to' => $date->copy()->endOfMonth(),
        ]);

        return BankStatementImportLine::create([
            'import_id' => $import->id, 'row_no' => 1, 'value_date' => $date, 'debit' => 0,
            'credit' => $credit, 'auth_no' => $authNo, 'state' => BankStatementImportLine::STATE_UNMATCHED,
        ]);
    }

    private function proposalOn(
        Company $company,
        Account $account,
        JournalEntry $book,
        string $kind,
        float $amount,
        ?string $matchedReference = null,
    ): ReconciliationProposal {
        return ReconciliationProposal::create([
            'company_id' => $company->id,
            'account_id' => $account->id,
            'source' => 'external',
            'kind' => $kind,
            'confidence' => ReconciliationProposal::CONFIDENCE_EXACT,
            'book_journal_entry_id' => $book->id,
            'matched_journal_entry_id' => null,
            'matched_reference' => $matchedReference,
            'amount' => $amount,
            'difference_amount' => 0,
            'status' => ReconciliationProposal::STATUS_PENDING,
        ]);
    }

    /**
     * Direction 1: bank_statement wins the line, clearing_rollforward (an INTERNAL detector's
     * kind — deliberately chosen over another external kind, because the internal detectors sweep
     * exactly the bank/cash leaves lane H's statements land on and are therefore the likeliest
     * real-world collision) is refused. The bank_statement kind's own `reconciled_ref_id` — the
     * STATEMENT LINE id, not the proposal id — must survive the refusal unchanged.
     */
    public function test_a_clearing_rollforward_cannot_claim_a_line_a_bank_statement_already_claimed(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $actor = $this->admin();
        $date = Carbon::create(2026, 6, 20);

        $counter = $this->accountByCode($company->id, '4110');
        $book = $this->postClaimableBankLine($company, $branch, $leaf, $counter, 410.750, $date, 'AUTH-DLT-1');

        // A throwaway statement row staged FIRST, purely so the real one's id cannot coincide with
        // the proposal's id — without it both are 1 on a fresh schema and the reference assertion
        // below could not tell the two stamping rules apart.
        $this->stageStatementLine($company, $leaf, $date->copy()->addDay(), 1.000, 'AUTH-DLT-DECOY');
        $statementLine = $this->stageStatementLine($company, $leaf, $date, 410.750, 'AUTH-DLT-1');

        $bankStatement = $this->proposalOn(
            $company, $leaf, $book, ReconciliationProposal::KIND_BANK_STATEMENT, 410.750,
            'bank_stmt_line:'.$statementLine->id
        );
        $this->proposals()->approve($bankStatement, $actor);

        $book->refresh();
        $this->assertSame(1, (int) $book->reconciled);
        $this->assertSame(
            $statementLine->id,
            (int) $book->reconciled_ref_id,
            'T9 stamps reconciled_ref_id with the statement line id — not the proposal id.'
        );
        $this->assertNotSame(
            $bankStatement->id,
            (int) $book->reconciled_ref_id,
            'Guard oracle would be vacuous if the statement-line id happened to equal the proposal id.'
        );
        $statementLine->refresh();
        $this->assertSame(BankStatementImportLine::STATE_MATCHED, $statementLine->state);

        $firstRef = (int) $book->reconciled_ref_id;
        $firstDate = (string) $book->reconciled_date;
        $firstAmount = (float) $book->reconciled_amount;

        $rollforward = $this->proposalOn($company, $leaf, $book, ReconciliationProposal::KIND_CLEARING_ROLLFORWARD, 410.750);

        try {
            $this->proposals()->approve($rollforward, $actor);
            $this->fail('A second-kind proposal on a line a bank statement already claimed must be refused.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already reconciled', $e->getMessage());
            $this->assertStringContainsString((string) $book->id, $e->getMessage());
        }

        $book->refresh();
        $this->assertSame(1, (int) $book->reconciled);
        $this->assertSame($firstRef, (int) $book->reconciled_ref_id, 'The losing approval overwrote the bank statement claim.');
        $this->assertSame($firstDate, (string) $book->reconciled_date);
        $this->assertEqualsWithDelta($firstAmount, (float) $book->reconciled_amount, 0.0005);

        $rollforward->refresh();
        $this->assertSame(ReconciliationProposal::STATUS_PENDING, $rollforward->status);

        $statementLine->refresh();
        $this->assertSame(BankStatementImportLine::STATE_MATCHED, $statementLine->state);
    }

    /**
     * Direction 2: the foreign kind wins, and the refused bank_statement approval must not reach
     * T9's SECOND write either — the statement line stays unmatched, so the exceptions report
     * still shows the operator an unsettled statement row rather than a phantom match.
     */
    public function test_a_bank_statement_cannot_claim_a_line_a_clearing_rollforward_already_claimed(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $actor = $this->admin();
        $date = Carbon::create(2026, 6, 21);

        $counter = $this->accountByCode($company->id, '4110');
        $book = $this->postClaimableBankLine($company, $branch, $leaf, $counter, 88.125, $date, 'AUTH-DLT-2');
        $statementLine = $this->stageStatementLine($company, $leaf, $date, 88.125, 'AUTH-DLT-2');

        $rollforward = $this->proposalOn($company, $leaf, $book, ReconciliationProposal::KIND_CLEARING_ROLLFORWARD, 88.125);
        $this->proposals()->approve($rollforward, $actor);

        $book->refresh();
        $this->assertSame($rollforward->id, (int) $book->reconciled_ref_id);

        $bankStatement = $this->proposalOn(
            $company, $leaf, $book, ReconciliationProposal::KIND_BANK_STATEMENT, 88.125,
            'bank_stmt_line:'.$statementLine->id
        );

        try {
            $this->proposals()->approve($bankStatement, $actor);
            $this->fail('A bank_statement proposal must not claim a line another kind already settled.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already reconciled', $e->getMessage());
            $this->assertStringContainsString(ReconciliationProposal::KIND_BANK_STATEMENT, $e->getMessage());
        }

        $book->refresh();
        $this->assertSame($rollforward->id, (int) $book->reconciled_ref_id, 'The refused bank_statement approval re-stamped the winning claim.');

        $statementLine->refresh();
        $this->assertSame(
            BankStatementImportLine::STATE_UNMATCHED,
            $statementLine->state,
            'A refused approval must not reach T9s BankStatementImportLine::state write.'
        );
    }

    /**
     * The nightly sweep now runs FIVE detectors. Two consecutive runs over a company carrying both
     * a bank statement import AND a posted gateway settlement must produce exactly the same
     * proposal set: no kind may re-propose its own match, and no kind may raise a second proposal
     * against a line another kind already claims.
     */
    public function test_auto_match_run_twice_across_all_five_detectors_is_idempotent(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $counter = $this->accountByCode($company->id, '4110');
        $receivable = $this->accountByCode($company->id, '1351');
        $date = Carbon::parse('2026-08-18');

        // Lane H's surface: a bank leaf line plus a statement row that matches it on auth_no.
        $this->postClaimableBankLine($company, $branch, $leaf, $counter, 90.000, $date, 'AUTH-FIVE-1');
        $this->stageStatementLine($company, $leaf, $date, 90.000, 'AUTH-FIVE-1');

        // Lane D's surface: a posted settlement whose payout item matches a clearing-leaf line.
        $clearing = app(AccountResolver::class)->resolve('GATEWAY_CLEARING_TAP', $company->id);
        $this->postClaimableBankLine($company, $branch, $clearing, $receivable, 50.000, $date, 'AUTH-FIVE-2');
        GatewaySettlement::create([
            'company_id' => $company->id, 'gateway' => 'TAP', 'settlement_channel' => 'tap',
            'payout_reference' => 'PO-'.uniqid(), 'payout_date' => '2026-08-20',
            'gross' => 50, 'fee' => 0, 'net' => 50, 'recognised_fee' => 0, 'currency' => 'KWD',
            'bank_account_id' => $leaf->id,
            'status' => GatewaySettlement::STATUS_POSTED, 'source' => GatewaySettlement::SOURCE_CSV,
            'raw' => ['payout_items' => [['reference' => 'AUTH-FIVE-2', 'amount' => 50.000, 'date' => '2026-08-19']]],
        ]);

        $service = app(ReconciliationAutoMatchService::class);

        $service->run($company->id);
        $afterFirst = $this->proposalFingerprints($company->id);

        $secondRun = $service->run($company->id);
        $afterSecond = $this->proposalFingerprints($company->id);

        // Both externally-fed kinds must actually have fired, or "idempotent" would be trivially
        // true over an empty set.
        $kinds = array_unique(array_map(fn (array $p) => $p['kind'], $afterFirst));
        $this->assertContains(ReconciliationProposal::KIND_BANK_STATEMENT, $kinds);
        $this->assertContains(ReconciliationProposal::KIND_GATEWAY_SETTLEMENT, $kinds);

        $this->assertSame($afterFirst, $afterSecond, 'The second nightly run changed the proposal set.');
        $this->assertSame(0, (int) $secondRun->proposals_created, 'The second run reported newly created proposals.');

        // And no two proposals of ANY kinds may stand against the same ledger line.
        $lines = array_map(fn (array $p) => $p['book_journal_entry_id'], $afterSecond);
        $this->assertSame(
            count($lines),
            count(array_unique($lines)),
            'Two proposals claim the same ledger line — the cross-kind collision GATE-1 exists to prevent.'
        );
    }

    /** @return list<array{kind:string, book_journal_entry_id:int, matched_reference:?string, status:string}> */
    private function proposalFingerprints(int $companyId): array
    {
        return ReconciliationProposal::forCompany($companyId)
            ->orderBy('id')
            ->get()
            ->map(fn (ReconciliationProposal $p) => [
                'kind' => (string) $p->kind,
                'book_journal_entry_id' => (int) $p->book_journal_entry_id,
                'matched_reference' => $p->matched_reference === null ? null : (string) $p->matched_reference,
                'status' => (string) $p->status,
            ])
            ->all();
    }

    /**
     * The statement-side WARN row must COMPOSE with the book-side rows the check already emitted:
     * both codes present, each exactly once, close still permitted.
     */
    public function test_the_bank_statement_warning_composes_with_the_existing_bank_cash_rows(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $counter = $this->accountByCode($company->id, '4110');
        $date = Carbon::create(2026, 5, 14);

        // Book side: an unreconciled bank-leaf line in the period -> unreconciled_bank_cash_lines.
        $this->postClaimableBankLine($company, $branch, $leaf, $counter, 30.000, $date);
        // Statement side: an unmatched statement row in the same period -> the new warning.
        $this->stageStatementLine($company, $leaf, $date, 30.000);

        $result = app(PeriodCloseChecklistService::class)->run($company->id, 2026, 5);

        $codes = array_column($result['warnings'], 'code');

        $this->assertContains('unreconciled_bank_cash_lines', $codes, 'The pre-existing book-side warning disappeared.');
        $this->assertContains('unmatched_bank_statement_lines', $codes, 'The new statement-side warning did not fire.');
        $this->assertSame(1, count(array_keys($codes, 'unmatched_bank_statement_lines', true)), 'The statement warning was emitted more than once.');
        $this->assertSame(1, count(array_keys($codes, 'unreconciled_bank_cash_lines', true)), 'The book-side warning was emitted more than once.');

        // Both are class (a) WARN rows: neither may block, and neither may be promoted into the
        // blocking list by the other's presence.
        $this->assertTrue($result['can_close'], 'A statement-side gap must never block the close.');
        $blockingCodes = array_column($result['blocking'] ?? [], 'code');
        $this->assertNotContains('unmatched_bank_statement_lines', $blockingCodes);
        $this->assertNotContains('unreconciled_bank_cash_lines', $blockingCodes);
    }
}
