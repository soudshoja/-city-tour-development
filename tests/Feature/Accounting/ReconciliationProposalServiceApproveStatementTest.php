<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\BankStatementImport;
use App\Models\BankStatementImportLine;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\ReconciliationProposal;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\Reconciliation\BankStatementMatcher;
use App\Services\Accounting\ReconciliationProposalService;
use Database\Seeders\CoaSeeder;
use Illuminate\Support\Carbon;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T9 (Wave 2): PLAN.md §5's own named test —
 * "ReconciliationProposalServiceApproveStatementTest" — proving `ReconciliationProposalService::
 * approve()`'s extension for `kind = bank_statement`: `journal_entries.reconciled_ref_id` is
 * stamped with the STATEMENT LINE's own id (not the proposal's id, the generic fallback every
 * other proposal kind still gets), and the statement line's own `state` is confirmed 'matched' at
 * the moment of approval.
 */
class ReconciliationProposalServiceApproveStatementTest extends AccountingTestCase
{
    private function service(): ReconciliationProposalService
    {
        return app(ReconciliationProposalService::class);
    }

    private function actor(): User
    {
        return User::factory()->create(['role_id' => Role::ADMIN]);
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

    private function offsetLeaf(int $companyId): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->orderBy('id')->skip(6)->take(1)->firstOrFail();
    }

    private function postBankLine(Company $company, Branch $branch, Account $bankLeaf, float $amount, Carbon $date, string $authNo): JournalEntry
    {
        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'RV', 'amount' => $amount, 'description' => 'Test bank receipt',
            'reference_type' => 'Receipt', 'reference_number' => 'RPS-'.substr(uniqid('', true), -8),
            'name' => 'Test', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'RV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => uniqid('key:', true),
        ]);
        $offset = $this->offsetLeaf($company->id);

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $offset->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'offset', 'debit' => 0, 'credit' => $amount, 'name' => $offset->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount, 'voucher_number' => 'RPS',
        ]);

        return JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $bankLeaf->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'bank receipt', 'debit' => $amount, 'credit' => 0, 'name' => $bankLeaf->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
            'voucher_number' => 'RPS', 'auth_no' => $authNo,
        ]);
    }

    public function test_approving_a_bank_statement_proposal_stamps_reconciled_ref_id_with_the_statement_line_id(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-18');

        $bookLine = $this->postBankLine($company, $branch, $leaf, 65.000, $date, 'AUTH-RPS-1');

        $import = BankStatementImport::create([
            'company_id' => $company->id, 'bank_account_id' => $leaf->id, 'file_name' => 't.csv',
            'statement_currency' => 'KWD', 'content_hash' => hash('sha256', uniqid('', true)),
            'column_map' => [], 'status' => BankStatementImport::STATUS_STAGED,
        ]);
        $statementLine = BankStatementImportLine::create([
            'import_id' => $import->id, 'row_no' => 1, 'value_date' => $date, 'debit' => 0,
            'credit' => 65.000, 'auth_no' => 'AUTH-RPS-1', 'state' => BankStatementImportLine::STATE_UNMATCHED,
        ]);

        app(BankStatementMatcher::class)->match($import->fresh(['lines']));

        $proposal = ReconciliationProposal::where('kind', ReconciliationProposal::KIND_BANK_STATEMENT)
            ->where('book_journal_entry_id', $bookLine->id)
            ->firstOrFail();

        $this->service()->approve($proposal, $this->actor());

        $bookLine->refresh();
        $this->assertSame(1, (int) $bookLine->reconciled);
        // The KEY assertion: reconciled_ref_id points at the STATEMENT LINE's own id, never the
        // proposal's id (the generic fallback every other proposal kind still gets).
        $this->assertSame($statementLine->id, (int) $bookLine->reconciled_ref_id);

        $statementLine->refresh();
        $this->assertSame(BankStatementImportLine::STATE_MATCHED, $statementLine->state);
    }

    public function test_approving_a_non_bank_statement_proposal_still_uses_the_generic_fallback(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-19');

        $bookLine = $this->postBankLine($company, $branch, $leaf, 33.000, $date, 'AUTH-RPS-2');

        // A plain manual match — kind = manual, not bank_statement — must be unaffected by the
        // T9 extension: reconciled_ref_id falls back to the proposal's own id, exactly as before.
        $proposal = $this->service()->manualMatch($company->id, $leaf->id, $bookLine->id, null, 'manual test match', $this->actor());

        $bookLine->refresh();
        $this->assertSame($proposal->id, (int) $bookLine->reconciled_ref_id);
    }
}
