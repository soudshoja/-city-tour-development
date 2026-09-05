<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T6 (L10): HTTP feature tests for {@see
 * \App\Http\Controllers\Accounting\EquityStatementController} — route gate + tenant isolation
 * (MP-6-x companion), same fixture shapes {@see PeriodControllerTest} already established for an
 * accounting screen behind `module:accounting`.
 */
class EquityStatementControllerTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    /** @return array{0: Company, 1: Branch, 2: User} */
    private function makeCompanyAndAdmin(): array
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $admin->id]);
        config(['accounting.engine.enabled' => true]);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        session(['company_id' => $company->id]);
        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch, $admin];
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function makeAgentInCompany(Company $company): User
    {
        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);
        $agentType = AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
        Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        return $agentUser;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        $this->trackCompanyForInvariants($company->id);

        $response = $this->get(route('accounting.reports.equity-changes', ['company_id' => $company->id]));

        $response->assertRedirect(route('login'));
    }

    public function test_renders_for_an_authorized_admin(): void
    {
        [$company] = $this->makeCompanyAndAdmin();
        $admin = User::where('role_id', Role::ADMIN)->firstOrFail();

        $response = $this->actingAs($admin)->get(route('accounting.reports.equity-changes', ['year' => 2026]));

        $response->assertOk();
        $response->assertViewIs('accounting.reports.equity-changes');
        $response->assertViewHas('year', 2026);
        $response->assertViewHas('statement');
    }

    public function test_forbids_a_role_with_no_accounting_report_permission(): void
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $agent = $this->makeAgentInCompany($company);
        $this->trackCompanyForInvariants($company->id);

        $response = $this->actingAs($agent)->get(route('accounting.reports.equity-changes', ['year' => 2026]));

        $response->assertStatus(403);
    }

    /**
     * MP-6-x companion: two DIFFERENT companies, each with their own Capital Stock (3100)
     * balance. Company A's statement must show ONLY Company A's opening equity, never bleeding
     * in Company B's figures -- the tenant-isolation half of "never read accounts.actual_balance
     * / journal_entries.balance", proven at the HTTP layer.
     */
    public function test_statement_is_scoped_to_the_requested_company_only(): void
    {
        [$companyA, $branchA, $adminA] = $this->makeCompanyAndAdmin();

        $companyB = Company::factory()->create();
        $this->grantAccountingModule($companyB);
        CoaSeeder::run($companyB->id);
        (new SystemAccountsSeeder)->run();
        $ownerB = User::factory()->create();
        $branchB = Branch::factory()->create(['company_id' => $companyB->id, 'user_id' => $ownerB->id]);
        Artisan::call('accounting:engine', ['company' => $companyB->id, '--enable' => true]);
        $this->trackCompanyForInvariants($companyB->id);

        $resolver = app(AccountResolver::class);
        $bankA = Account::withoutGlobalScopes()->where('company_id', $companyA->id)->where('code', '1201')->firstOrFail();
        $capitalA = Account::withoutGlobalScopes()->where('company_id', $companyA->id)->where('code', '3100')->firstOrFail();
        $bankB = Account::withoutGlobalScopes()->where('company_id', $companyB->id)->where('code', '1201')->firstOrFail();
        $capitalB = Account::withoutGlobalScopes()->where('company_id', $companyB->id)->where('code', '3100')->firstOrFail();

        $this->postCapitalInjection($companyA, $branchA, $bankA, $capitalA, 1000);
        $this->postCapitalInjection($companyB, $branchB, $bankB, $capitalB, 9999);

        session(['company_id' => $companyA->id]);
        $response = $this->actingAs($adminA)->get(route('accounting.reports.equity-changes', ['year' => 2026, 'company_id' => $companyA->id]));

        $response->assertOk();
        $statement = $response->viewData('statement');
        $this->assertEqualsWithDelta(1000.0, $statement['components']['capital']['closing'], 0.001, "Company A's statement must show Company A's own capital movement, never Company B's.");
    }

    private function postCapitalInjection(Company $company, Branch $branch, Account $bank, Account $capital, float $amount): void
    {
        $date = \Illuminate\Support\Carbon::create(2026, 2, 1);
        $txn = \App\Models\Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => $amount, 'description' => 'Capital injection fixture', 'reference_type' => 'Payment',
            'reference_number' => 'CAP-'.uniqid(), 'name' => 'Capital injection fixture', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'JV', 'doc_year' => 2026, 'posting_status' => 'posted',
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => uniqid('cap:'),
        ]);
        \App\Models\JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id, 'account_id' => $bank->id,
            'transaction_date' => $date, 'posting_date' => $date, 'description' => 'cap', 'debit' => $amount, 'credit' => 0,
            'name' => $bank->name, 'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
            'voucher_number' => 'CAP', 'type_reference_id' => $company->id,
        ]);
        \App\Models\JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id, 'account_id' => $capital->id,
            'transaction_date' => $date, 'posting_date' => $date, 'description' => 'cap', 'debit' => 0, 'credit' => $amount,
            'name' => $capital->name, 'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
            'voucher_number' => 'CAP', 'type_reference_id' => $company->id,
        ]);
    }
}
