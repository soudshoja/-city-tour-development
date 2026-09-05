<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\GatewaySettlement;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Tests\Support\AccountingTestCase;

/**
 * Adversarial verification (T7 review): the packet's own 25 tests never exercise
 * `recordSettlement` / `settlements` / `bankAccounts` over HTTP at all — every claim about
 * authorization, validation, and tenant scoping on this new surface was unverified. Mirrors
 * {@see ReconciliationControllerTest}'s own fixture conventions.
 */
class GatewaySettlementHttpTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function makeCompanyAndAdmin(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);
        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch, $admin];
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

    private function bankAccount(Company $company): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();
    }

    // ── Authorization ────────────────────────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login_on_record_settlement(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $response = $this->postJson(route('accounting.reconciliation.settlements.record'), []);

        // JSON request from a guest -> Laravel's auth middleware responds 401, never a silent 200.
        $response->assertStatus(401);
    }

    public function test_unauthorized_agent_is_403d_on_record_settlement(): void
    {
        [$company] = $this->makeCompanyAndAdmin();
        $agent = $this->makeAgentInCompany($company);
        $bank = $this->bankAccount($company);

        $response = $this->actingAs($agent)->postJson(route('accounting.reconciliation.settlements.record'), [
            'gateway' => 'TAP', 'payout_reference' => 'HTTP-1', 'payout_date' => '2026-08-20',
            'gross' => 100, 'fee' => 5, 'net' => 95, 'bank_account_id' => $bank->id,
        ]);

        $response->assertStatus(403);
        $this->assertSame(0, GatewaySettlement::forCompany($company->id)->count(), 'a 403 must never leave a settlement row behind.');
    }

    public function test_unauthorized_agent_is_403d_on_settlements_list(): void
    {
        [$company] = $this->makeCompanyAndAdmin();
        $agent = $this->makeAgentInCompany($company);

        $response = $this->actingAs($agent)->getJson(route('accounting.reconciliation.settlements', ['company_id' => $company->id]));

        $response->assertStatus(403);
    }

    public function test_unauthorized_agent_is_403d_on_bank_accounts_list(): void
    {
        [$company] = $this->makeCompanyAndAdmin();
        $agent = $this->makeAgentInCompany($company);

        $response = $this->actingAs($agent)->getJson(route('accounting.reconciliation.bank-accounts', ['company_id' => $company->id]));

        $response->assertStatus(403);
    }

    // ── Happy path + validation ──────────────────────────────────────────────────────────────

    public function test_admin_can_record_a_settlement_via_http(): void
    {
        [$company] = $this->makeCompanyAndAdmin();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        $bank = $this->bankAccount($company);
        config(['accounting.engine.enabled' => true]);
        \Illuminate\Support\Facades\Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $response = $this->actingAs($admin)->postJson(route('accounting.reconciliation.settlements.record'), [
            'gateway' => 'TAP', 'payout_reference' => 'HTTP-OK-1', 'payout_date' => '2026-08-20',
            'gross' => 100, 'fee' => 5, 'net' => 95, 'bank_account_id' => $bank->id, 'company_id' => $company->id,
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $this->assertSame(1, GatewaySettlement::forCompany($company->id)->where('payout_reference', 'HTTP-OK-1')->count());
    }

    public function test_unknown_gateway_returns_422_not_500(): void
    {
        [$company] = $this->makeCompanyAndAdmin();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        $bank = $this->bankAccount($company);

        $response = $this->actingAs($admin)->postJson(route('accounting.reconciliation.settlements.record'), [
            'gateway' => 'NOTAGATEWAY', 'payout_reference' => 'BAD-GW-1', 'payout_date' => '2026-08-20',
            'gross' => 100, 'fee' => 5, 'net' => 95, 'bank_account_id' => $bank->id, 'company_id' => $company->id,
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertSame(0, GatewaySettlement::forCompany($company->id)->count(), 'an unknown gateway must never persist a row.');
    }

    public function test_mismatched_gross_net_fee_returns_422_not_500(): void
    {
        [$company] = $this->makeCompanyAndAdmin();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        $bank = $this->bankAccount($company);

        $response = $this->actingAs($admin)->postJson(route('accounting.reconciliation.settlements.record'), [
            'gateway' => 'TAP', 'payout_reference' => 'BAD-MATH-1', 'payout_date' => '2026-08-20',
            'gross' => 100, 'fee' => 5, 'net' => 80, 'bank_account_id' => $bank->id, 'company_id' => $company->id,
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_missing_required_fields_returns_422_via_form_request_validation(): void
    {
        [$company] = $this->makeCompanyAndAdmin();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        $response = $this->actingAs($admin)->postJson(route('accounting.reconciliation.settlements.record'), [
            'company_id' => $company->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['gateway', 'payout_reference', 'payout_date', 'gross', 'fee', 'net', 'bank_account_id']);
    }

    /**
     * Verifier fix (T7 adversarial review, defect #3): a bare `['required', 'numeric']` rule
     * accepted a negative, internally-consistent payout (-100 = -95 + -5) all the way through to
     * a posted GWS document. Pinned here with `gt:0`/`min:0` added to the controller's validation.
     */
    public function test_negative_amounts_are_rejected_before_reaching_the_service(): void
    {
        [$company] = $this->makeCompanyAndAdmin();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        $bank = $this->bankAccount($company);

        $response = $this->actingAs($admin)->postJson(route('accounting.reconciliation.settlements.record'), [
            'gateway' => 'TAP', 'payout_reference' => 'NEG-1', 'payout_date' => '2026-08-20',
            'gross' => -100, 'fee' => -5, 'net' => -95, 'bank_account_id' => $bank->id, 'company_id' => $company->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['gross', 'fee', 'net']);
        $this->assertSame(0, GatewaySettlement::forCompany($company->id)->count(), 'a negative-amount settlement must never persist.');
    }

    // ── Tenant isolation ─────────────────────────────────────────────────────────────────────

    public function test_settlements_list_is_scoped_to_the_requesting_company(): void
    {
        [$companyA] = $this->makeCompanyAndAdmin();
        [$companyB] = $this->makeCompanyAndAdmin();
        $adminA = User::factory()->create(['role_id' => Role::ADMIN]);
        $bankA = $this->bankAccount($companyA);
        $bankB = $this->bankAccount($companyB);

        app(\App\Services\Accounting\GatewaySettlementService::class)->record(
            companyId: $companyA->id, gateway: 'TAP', payoutReference: 'ISO-A',
            payoutDate: \Illuminate\Support\Carbon::parse('2026-08-20'), gross: 10, fee: 0, net: 10,
            bankAccountId: $bankA->id,
        );
        app(\App\Services\Accounting\GatewaySettlementService::class)->record(
            companyId: $companyB->id, gateway: 'TAP', payoutReference: 'ISO-B',
            payoutDate: \Illuminate\Support\Carbon::parse('2026-08-20'), gross: 10, fee: 0, net: 10,
            bankAccountId: $bankB->id,
        );

        $response = $this->actingAs($adminA)->getJson(route('accounting.reconciliation.settlements', ['company_id' => $companyA->id]));

        $response->assertStatus(200);
        $refs = collect($response->json('settlements'))->pluck('payout_reference');
        $this->assertTrue($refs->contains('ISO-A'));
        $this->assertFalse($refs->contains('ISO-B'), 'a settlements list scoped to company A must never leak company B rows.');
    }

    public function test_bank_accounts_list_only_returns_leaves_under_the_bank_group(): void
    {
        [$company] = $this->makeCompanyAndAdmin();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        $response = $this->actingAs($admin)->getJson(route('accounting.reconciliation.bank-accounts', ['company_id' => $company->id]));

        $response->assertStatus(200);
        $codes = collect($response->json('bank_accounts'))->pluck('code');
        $this->assertTrue($codes->contains('1201'), 'a real bank leaf (1201) must be offered.');
        $this->assertFalse($codes->contains('5203'), 'a non-bank leaf (Depreciation, 5203) must never be offered as a bank-account choice.');
        $this->assertFalse($codes->contains('1200'), 'the Bank Accounts GROUP node itself (non-leaf) must never be offered.');
    }
}
