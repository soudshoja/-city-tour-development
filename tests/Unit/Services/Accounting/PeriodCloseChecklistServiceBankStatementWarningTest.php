<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Models\Account;
use App\Models\BankStatementImport;
use App\Models\BankStatementImportLine;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\PeriodCloseChecklistService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T9 (Wave 2): PLAN.md §5's own line — "PeriodCloseChecklistService::
 * checkBankCashReconciliation gains a WARN row 'N statement lines unmatched'." A dedicated file
 * (rather than editing the large pre-existing PeriodCloseChecklistServiceTest) so a regression in
 * this new check never risks a merge conflict with that file's own suite.
 */
class PeriodCloseChecklistServiceBankStatementWarningTest extends AccountingTestCase
{
    private function service(): PeriodCloseChecklistService
    {
        return app(PeriodCloseChecklistService::class);
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

    public function test_unmatched_statement_lines_in_period_produce_a_warning(): void
    {
        [$company, , $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::create(2026, 3, 12);

        $import = BankStatementImport::create([
            'company_id' => $company->id, 'bank_account_id' => $leaf->id, 'file_name' => 't.csv',
            'statement_currency' => 'KWD', 'content_hash' => hash('sha256', uniqid('', true)),
            'column_map' => [], 'status' => BankStatementImport::STATUS_STAGED,
        ]);
        BankStatementImportLine::create([
            'import_id' => $import->id, 'row_no' => 1, 'value_date' => $date, 'debit' => 0,
            'credit' => 10.000, 'state' => BankStatementImportLine::STATE_UNMATCHED,
        ]);

        $result = $this->service()->run($company->id, 2026, 3);

        $this->assertTrue($result['can_close']);
        $this->assertContains('unmatched_bank_statement_lines', array_column($result['warnings'], 'code'));
    }

    public function test_disputed_statement_lines_also_warn(): void
    {
        [$company, , $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::create(2026, 4, 5);

        $import = BankStatementImport::create([
            'company_id' => $company->id, 'bank_account_id' => $leaf->id, 'file_name' => 't.csv',
            'statement_currency' => 'KWD', 'content_hash' => hash('sha256', uniqid('', true)),
            'column_map' => [], 'status' => BankStatementImport::STATUS_MATCHED,
        ]);
        BankStatementImportLine::create([
            'import_id' => $import->id, 'row_no' => 1, 'value_date' => $date, 'debit' => 0,
            'credit' => 25.000, 'state' => BankStatementImportLine::STATE_DISPUTED,
        ]);

        $result = $this->service()->run($company->id, 2026, 4);

        $this->assertContains('unmatched_bank_statement_lines', array_column($result['warnings'], 'code'));
    }

    public function test_matched_statement_lines_produce_no_warning(): void
    {
        [$company, , $leaf] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::create(2026, 5, 6);

        $import = BankStatementImport::create([
            'company_id' => $company->id, 'bank_account_id' => $leaf->id, 'file_name' => 't.csv',
            'statement_currency' => 'KWD', 'content_hash' => hash('sha256', uniqid('', true)),
            'column_map' => [], 'status' => BankStatementImport::STATUS_MATCHED,
        ]);
        BankStatementImportLine::create([
            'import_id' => $import->id, 'row_no' => 1, 'value_date' => $date, 'debit' => 0,
            'credit' => 5.000, 'state' => BankStatementImportLine::STATE_MATCHED,
        ]);

        $result = $this->service()->run($company->id, 2026, 5);

        $this->assertNotContains('unmatched_bank_statement_lines', array_column($result['warnings'], 'code'));
    }

    public function test_unmatched_lines_outside_the_period_do_not_warn(): void
    {
        [$company, , $leaf] = $this->makeCompanyWithBankLeaf();

        $import = BankStatementImport::create([
            'company_id' => $company->id, 'bank_account_id' => $leaf->id, 'file_name' => 't.csv',
            'statement_currency' => 'KWD', 'content_hash' => hash('sha256', uniqid('', true)),
            'column_map' => [], 'status' => BankStatementImport::STATUS_STAGED,
        ]);
        BankStatementImportLine::create([
            'import_id' => $import->id, 'row_no' => 1, 'value_date' => Carbon::create(2026, 1, 1), 'debit' => 0,
            'credit' => 10.000, 'state' => BankStatementImportLine::STATE_UNMATCHED,
        ]);

        $result = $this->service()->run($company->id, 2026, 6);

        $this->assertNotContains('unmatched_bank_statement_lines', array_column($result['warnings'], 'code'));
    }

    /** A different company's unmatched statement lines never leak into this company's checklist. */
    public function test_unmatched_lines_on_another_companys_bank_leaf_do_not_warn(): void
    {
        [$companyA] = $this->makeCompanyWithBankLeaf();
        [$companyB, , $leafB] = $this->makeCompanyWithBankLeaf();
        $date = Carbon::create(2026, 7, 1);

        $import = BankStatementImport::create([
            'company_id' => $companyB->id, 'bank_account_id' => $leafB->id, 'file_name' => 't.csv',
            'statement_currency' => 'KWD', 'content_hash' => hash('sha256', uniqid('', true)),
            'column_map' => [], 'status' => BankStatementImport::STATUS_STAGED,
        ]);
        BankStatementImportLine::create([
            'import_id' => $import->id, 'row_no' => 1, 'value_date' => $date, 'debit' => 0,
            'credit' => 10.000, 'state' => BankStatementImportLine::STATE_UNMATCHED,
        ]);

        $result = $this->service()->run($companyA->id, 2026, 7);

        $this->assertNotContains('unmatched_bank_statement_lines', array_column($result['warnings'], 'code'));
    }
}
