<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Models\Account;
use App\Models\BankStatementImport;
use App\Models\BankStatementImportLine;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\ReconciliationProposal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\ReconciliationAutoMatchService;
use Database\Seeders\CoaSeeder;
use Illuminate\Support\Carbon;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T9 (Wave 2): {@see ReconciliationAutoMatchService::detectBankStatementMatches()}
 * — the nightly-sweep detector PLAN.md §5 names for T9, wired on top of {@see \App\Services\
 * Accounting\Reconciliation\BankStatementMatcher} rather than re-implementing tiered matching a
 * second time. Same oracle every other detector in this class already proves: never posts money,
 * idempotent per line/re-run.
 */
class ReconciliationAutoMatchServiceBankStatementDetectorTest extends AccountingTestCase
{
    private function service(): ReconciliationAutoMatchService
    {
        return app(ReconciliationAutoMatchService::class);
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

    private function postBankLine(Company $company, Branch $branch, Account $bankLeaf, float $amount, Carbon $date, ?string $authNo = null): JournalEntry
    {
        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'RV', 'amount' => $amount, 'description' => 'Test bank receipt',
            'reference_type' => 'Receipt', 'reference_number' => 'RAM-'.substr(uniqid('', true), -8),
            'name' => 'Test', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'RV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => uniqid('key:', true),
        ]);
        $offset = $this->offsetLeaf($company->id);

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $offset->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'offset', 'debit' => 0, 'credit' => $amount, 'name' => $offset->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount, 'voucher_number' => 'RAM',
        ]);

        return JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $bankLeaf->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'bank receipt', 'debit' => $amount, 'credit' => 0, 'name' => $bankLeaf->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
            'voucher_number' => 'RAM', 'auth_no' => $authNo,
        ]);
    }

    private function makeStagedImport(Company $company, Account $leaf, Carbon $date, float $amount, ?string $authNo = null): BankStatementImport
    {
        $import = BankStatementImport::create([
            'company_id' => $company->id, 'bank_account_id' => $leaf->id, 'file_name' => 't.csv',
            'statement_currency' => 'KWD', 'content_hash' => hash('sha256', uniqid('', true)),
            'column_map' => [], 'status' => BankStatementImport::STATUS_STAGED,
        ]);
        BankStatementImportLine::create([
            'import_id' => $import->id, 'row_no' => 1, 'value_date' => $date, 'debit' => 0,
            'credit' => $amount, 'auth_no' => $authNo, 'state' => BankStatementImportLine::STATE_UNMATCHED,
        ]);

        return $import;
    }

    public function test_run_matches_a_staged_bank_statement_import_and_creates_a_proposal(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-15');

        $this->postBankLine($company, $branch, $leaf, 90.000, $date, 'AUTH-RAM-1');
        $this->makeStagedImport($company, $leaf, $date, 90.000, 'AUTH-RAM-1');

        $run = $this->service()->run($company->id);

        $this->assertSame(1, ReconciliationProposal::where('kind', ReconciliationProposal::KIND_BANK_STATEMENT)->count());
        $this->assertGreaterThanOrEqual(1, $run->proposals_created);
    }

    public function test_run_never_writes_journal_entries_only_proposes(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-16');

        $this->postBankLine($company, $branch, $leaf, 40.000, $date, 'AUTH-RAM-2');
        $this->makeStagedImport($company, $leaf, $date, 40.000, 'AUTH-RAM-2');

        $before = JournalEntry::withoutGlobalScopes()->count();
        $this->service()->run($company->id);
        $after = JournalEntry::withoutGlobalScopes()->count();

        $this->assertSame($before, $after);
    }

    public function test_idempotent_re_run_never_duplicates_a_pending_proposal(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::parse('2026-08-17');

        $this->postBankLine($company, $branch, $leaf, 15.000, $date, 'AUTH-RAM-3');
        $this->makeStagedImport($company, $leaf, $date, 15.000, 'AUTH-RAM-3');

        $this->service()->run($company->id);
        $this->service()->run($company->id);

        $this->assertSame(1, ReconciliationProposal::where('kind', ReconciliationProposal::KIND_BANK_STATEMENT)->count());
    }

    public function test_a_company_with_no_bank_statement_imports_is_a_no_op(): void
    {
        [$company] = $this->makeCompanyWithBankLeaf();

        $run = $this->service()->run($company->id);

        $this->assertSame(0, ReconciliationProposal::forCompany($company->id)->where('kind', ReconciliationProposal::KIND_BANK_STATEMENT)->count());
        $this->assertSame('completed', $run->status);
    }
}
