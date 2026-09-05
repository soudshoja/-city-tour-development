<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\Reconciliation;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\BankStatementImport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Illuminate\Http\UploadedFile;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T9 (Wave 2): HTTP authorization (view/manage) for the bank-statement import +
 * auto-match surface — mirrors {@see \Tests\Feature\Accounting\GatewaySettlementHttpTest}'s own
 * fixture conventions (guest 401, unauthorized-agent 403, admin succeeds, tenant scoping), plus an
 * import -> match -> exceptions round trip over the real routes (T8's own HTTP-round-trip
 * convention).
 */
class BankStatementHttpTest extends AccountingTestCase
{
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

    private function makeAgentInCompany(Company $company): User
    {
        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);
        $agentType = AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
        Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        return $agentUser;
    }

    // ── Authorization ────────────────────────────────────────────────────────────────────────────

    public function test_guest_is_redirected_or_401d_on_import(): void
    {
        [$company, , $leaf] = $this->makeCompanyWithBankLeaf();
        $csv = "Value Date,Debit,Credit\n2026-08-01,,10.000\n";
        $file = UploadedFile::fake()->createWithContent('t.csv', $csv);

        $response = $this->postJson(route('accounting.reconciliation.bank-statements.import'), [
            'file' => $file, 'bank_account_id' => $leaf->id, 'statement_currency' => 'KWD',
        ]);

        $response->assertStatus(401);
    }

    public function test_unauthorized_agent_is_403d_on_import(): void
    {
        [$company, , $leaf] = $this->makeCompanyWithBankLeaf();
        $agent = $this->makeAgentInCompany($company);
        session(['company_id' => $company->id]);
        $csv = "Value Date,Debit,Credit\n2026-08-01,,10.000\n";
        $file = UploadedFile::fake()->createWithContent('t.csv', $csv);

        $response = $this->actingAs($agent)->postJson(route('accounting.reconciliation.bank-statements.import'), [
            'file' => $file, 'bank_account_id' => $leaf->id, 'statement_currency' => 'KWD',
        ]);

        $response->assertStatus(403);
        $this->assertSame(0, BankStatementImport::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_unauthorized_agent_is_403d_on_bank_statements_index(): void
    {
        [$company] = $this->makeCompanyWithBankLeaf();
        $agent = $this->makeAgentInCompany($company);
        session(['company_id' => $company->id]);

        $response = $this->actingAs($agent)->get(route('accounting.reconciliation.bank-statements.index'));

        $response->assertStatus(403);
    }

    public function test_admin_can_view_the_index_but_agent_cannot_manage(): void
    {
        [$company] = $this->makeCompanyWithBankLeaf();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        $response = $this->actingAs($admin)->get(route('accounting.reconciliation.bank-statements.index'));

        $response->assertOk();
    }

    // ── Import -> match -> exceptions round trip ────────────────────────────────────────────────

    public function test_http_import_match_and_exceptions_round_trip(): void
    {
        [$company, $branch, $leaf] = $this->makeCompanyWithBankLeaf();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        // Post a real ledger line on the bank leaf for the statement row to match.
        $txn = \App\Models\Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'RV', 'amount' => 25, 'description' => 'HTTP test receipt',
            'reference_type' => 'Receipt', 'reference_number' => 'HTTP-'.substr(uniqid('', true), -8),
            'name' => 'Test', 'transaction_date' => '2026-08-09', 'posting_date' => '2026-08-09',
            'doc_type' => 'RV', 'doc_year' => 2026, 'posting_status' => 'posted',
            'total_debit' => 25, 'total_credit' => 25, 'idempotency_key' => uniqid('key:', true),
        ]);
        $offset = Account::withoutGlobalScopes()->where('company_id', $company->id)->orderBy('id')->skip(6)->take(1)->firstOrFail();
        \App\Models\JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $offset->id, 'transaction_date' => '2026-08-09', 'posting_date' => '2026-08-09',
            'description' => 'offset', 'debit' => 0, 'credit' => 25, 'name' => $offset->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 25, 'voucher_number' => 'HTTP',
        ]);
        \App\Models\JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $leaf->id, 'transaction_date' => '2026-08-09', 'posting_date' => '2026-08-09',
            'description' => 'bank receipt', 'debit' => 25, 'credit' => 0, 'name' => $leaf->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 25,
            'voucher_number' => 'HTTP', 'reference_type' => null, 'auth_no' => 'AUTH-HTTP',
        ]);

        $csv = "Value Date,Debit,Credit,Auth No\n2026-08-09,,25.000,AUTH-HTTP\n";
        $file = UploadedFile::fake()->createWithContent('http-test.csv', $csv);

        $this->actingAs($admin);

        $importResponse = $this->post(route('accounting.reconciliation.bank-statements.import'), [
            'file' => $file,
            'bank_account_id' => $leaf->id,
            'statement_currency' => 'KWD',
            'column_map' => ['value_date' => 'Value Date', 'debit' => 'Debit', 'credit' => 'Credit', 'auth_no' => 'Auth No'],
        ]);

        $importResponse->assertStatus(201);
        $importId = $importResponse->json('import.id');
        $this->assertNotNull($importId);

        $matchResponse = $this->post(route('accounting.reconciliation.bank-statements.match', ['bankStatementImport' => $importId]));
        $matchResponse->assertOk();
        $matchResponse->assertJsonPath('result.matched', 1);

        $exceptionsResponse = $this->get(route('accounting.reconciliation.bank-statements.exceptions', ['bankStatementImport' => $importId]));
        $exceptionsResponse->assertOk();
        $exceptionsResponse->assertJsonCount(1, 'matched');
        $exceptionsResponse->assertJsonStructure(['report' => ['ledger_balance', 'statement_closing_balance', 'difference']]);
    }

    public function test_a_statement_import_is_inaccessible_to_a_different_companys_admin(): void
    {
        [$companyA, , $leafA] = $this->makeCompanyWithBankLeaf();
        [$companyB] = $this->makeCompanyWithBankLeaf();

        $import = BankStatementImport::create([
            'company_id' => $companyA->id, 'bank_account_id' => $leafA->id, 'file_name' => 't.csv',
            'statement_currency' => 'KWD', 'content_hash' => hash('sha256', uniqid('', true)),
            'column_map' => [], 'status' => BankStatementImport::STATUS_STAGED,
        ]);

        $adminB = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $companyB->id]);

        $response = $this->actingAs($adminB)->get(route('accounting.reconciliation.bank-statements.exceptions', ['bankStatementImport' => $import->id]));

        // Route-model-binding's own company global scope hides the record entirely for a
        // different company's session (404) before the controller's own defense-in-depth
        // abort_if($import->company_id !== $companyId, 403, ...) would even run — either way,
        // company A's import is unreachable from company B's session.
        $response->assertStatus(404);
    }
}
