<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\GatewaySettlement;
use App\Models\Payment;
use App\Models\User;
use App\Services\Accounting\GatewaySettlementService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;
use Tests\Support\SeedsGatewayClearing;

/**
 * Adversarial verification (T7 review): mutation proofs and boundary cases the builder's own
 * packet did not cover — the daily-JV non-double-move guard's payment-sweep is DATE-based only
 * (no amount reconciliation against the settlement's own gross), and the bank-leaf validation's
 * cross-company / non-leaf-group boundary.
 */
class GatewaySettlementServiceAdversarialTest extends AccountingTestCase
{
    use SeedsGatewayClearing;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Branch} */
    private function makeEngineOnCompany(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);

        config(['accounting.engine.enabled' => true]);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch];
    }

    private function bankAccount(Company $company): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();
    }

    private function service(): GatewaySettlementService
    {
        return app(GatewaySettlementService::class);
    }

    private function makeClient(Company $company, Branch $branch): Client
    {
        $agentType = AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
        $agentUser = User::factory()->create();
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        return Client::factory()->create(['agent_id' => $agent->id]);
    }

    // ── Partial settlement: residual must stay in clearing, not be silently swept ──────────────

    public function test_partial_settlement_leaves_the_uncovered_residual_payment_untouched(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);
        $client = $this->makeClient($company, $branch);

        // The three receipts behind those payments, already sitting in clearing.
        $this->seedGatewayClearing($company, 'TAP', 150.000);

        // Three payments, same gateway, same date, KWD 50 each = KWD 150 pending.
        $p1 = Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $client->agent_id, 'client_id' => $client->id,
            'payment_gateway' => 'tap', 'payment_date' => '2026-08-20', 'completed' => 0, 'amount' => 50,
            'invoice_id' => null, 'account_id' => null, 'created_by' => null,
        ]);
        $p2 = Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $client->agent_id, 'client_id' => $client->id,
            'payment_gateway' => 'tap', 'payment_date' => '2026-08-20', 'completed' => 0, 'amount' => 50,
            'invoice_id' => null, 'account_id' => null, 'created_by' => null,
        ]);
        $p3 = Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $client->agent_id, 'client_id' => $client->id,
            'payment_gateway' => 'tap', 'payment_date' => '2026-08-20', 'completed' => 0, 'amount' => 50,
            'invoice_id' => null, 'account_id' => null, 'created_by' => null,
        ]);

        // The payout only covers KWD 50 of the KWD 150 pending — a genuine partial settlement.
        $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'PARTIAL-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 50.000, fee: 0.000, net: 50.000,
            bankAccountId: $bank->id,
        );

        $completedCount = Payment::withoutGlobalScopes()
            ->whereIn('id', [$p1->id, $p2->id, $p3->id])
            ->where('completed', 1)
            ->count();

        $this->assertSame(
            1,
            $completedCount,
            'a KWD 50 payout must sweep exactly KWD 50 of pending payments (one of the three), '
            .'never all three (KWD 150) just because they share a date — the residual KWD 100 '
            .'must stay uncovered so it is either swept by a LATER settlement or by the daily job, '
            .'not silently orphaned (marked completed without its money ever having moved).'
        );
    }

    public function test_two_payouts_same_day_different_amounts_partition_the_pending_pool_without_overlap(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);
        $client = $this->makeClient($company, $branch);

        $this->seedGatewayClearing($company, 'TAP', 100.000);

        $p1 = Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $client->agent_id, 'client_id' => $client->id,
            'payment_gateway' => 'tap', 'payment_date' => '2026-08-20', 'completed' => 0, 'amount' => 40,
            'invoice_id' => null, 'account_id' => null, 'created_by' => null,
        ]);
        $p2 = Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $client->agent_id, 'client_id' => $client->id,
            'payment_gateway' => 'tap', 'payment_date' => '2026-08-20', 'completed' => 0, 'amount' => 60,
            'invoice_id' => null, 'account_id' => null, 'created_by' => null,
        ]);

        // Payout A covers exactly p1 (40); Payout B (recorded after) must then cover exactly p2
        // (60) from what remains — never re-touch p1, never leave p2 stranded.
        $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'SPLIT-A',
            payoutDate: Carbon::parse('2026-08-20'), gross: 40.000, fee: 0.000, net: 40.000,
            bankAccountId: $bank->id,
        );
        $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'SPLIT-B',
            payoutDate: Carbon::parse('2026-08-20'), gross: 60.000, fee: 0.000, net: 60.000,
            bankAccountId: $bank->id,
        );

        $this->assertSame(1, (int) Payment::withoutGlobalScopes()->where('id', $p1->id)->value('completed'));
        $this->assertSame(1, (int) Payment::withoutGlobalScopes()->where('id', $p2->id)->value('completed'));
    }

    // ── Over-settlement: gross larger than the pending pool must refuse, not go negative ───────

    public function test_over_settlement_larger_than_the_pending_pool_raises_an_exception(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);
        $client = $this->makeClient($company, $branch);

        Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $client->agent_id, 'client_id' => $client->id,
            'payment_gateway' => 'tap', 'payment_date' => '2026-08-20', 'completed' => 0, 'amount' => 50,
            'invoice_id' => null, 'account_id' => null, 'created_by' => null,
        ]);

        $this->expectException(\App\Exceptions\Accounting\GatewayOverSettledException::class);

        // Only KWD 50 is pending for this gateway — a KWD 1,000 payout is a genuine over-settlement.
        $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'OVER-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 1000.000, fee: 5.000, net: 995.000,
            bankAccountId: $bank->id,
        );
    }

    /**
     * Post-fix re-verification: this test previously asserted that a KWD 1,000 payout recorded
     * against a company with NO pending payments and NO money in clearing still posted — the
     * pending-pool guard's own "skip when the pool is empty" escape. That is not an invariant to
     * preserve; it is the hole (it drives GATEWAY_CLEARING_TAP to −1,000). What must actually
     * hold is the narrower claim underneath it: a payout with no local `Payment` LINKAGE still
     * settles, provided the money it releases is genuinely in clearing. Guarding on the derived
     * clearing balance keeps that true and closes the hole; the refusal half is pinned in
     * {@see GatewaySettlementCoverageGuardTest::test_empty_pending_pool_with_no_clearing_balance_is_refused()}.
     */
    public function test_settlement_with_no_payment_linkage_posts_when_clearing_actually_holds_the_money(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);

        // No local Payment rows at all for this gateway/company — a from-scratch manual entry —
        // but the money it is releasing really is in clearing.
        $this->seedGatewayClearing($company, 'TAP', 1000.000);

        $settlement = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'NOLINK-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 1000.000, fee: 5.000, net: 995.000,
            bankAccountId: $bank->id,
        );

        $this->assertSame(GatewaySettlement::STATUS_POSTED, $settlement->status);
    }

    // ── Bank-leaf validation boundary ───────────────────────────────────────────────────────────

    public function test_record_refuses_a_bank_account_belonging_to_a_different_company(): void
    {
        [$companyA] = $this->makeEngineOnCompany();
        [$companyB] = $this->makeEngineOnCompany();
        $bankB = $this->bankAccount($companyB);

        $this->expectException(\App\Exceptions\Accounting\CrossTenantAccountException::class);

        $this->service()->record(
            companyId: $companyA->id, gateway: 'TAP', payoutReference: 'CROSS-CO-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 100.000, fee: 5.000, net: 95.000,
            bankAccountId: $bankB->id,
        );
    }

    public function test_record_refuses_the_bank_accounts_group_node_itself_as_a_non_leaf(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $groupNode = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1200')->firstOrFail();

        $this->expectException(\App\Exceptions\Accounting\NonLeafAccountException::class);

        $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'NON-LEAF-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 100.000, fee: 5.000, net: 95.000,
            bankAccountId: $groupNode->id,
        );
    }

    // ── DB-level uniqueness (defense in depth beneath the app-level idempotency check) ─────────

    public function test_database_unique_constraint_rejects_a_duplicate_payout_reference_directly(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);

        DB::connection('mysql_testing')->table('gateway_settlements')->insert([
            'company_id' => $company->id, 'gateway' => 'TAP', 'settlement_channel' => 'tap',
            'payout_reference' => 'DUP-DB-1', 'payout_date' => '2026-08-20',
            'gross' => 10, 'fee' => 0, 'net' => 10, 'recognised_fee' => 0,
            'currency' => 'KWD', 'bank_account_id' => $bank->id, 'status' => 'recorded',
            'source' => 'manual', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::connection('mysql_testing')->table('gateway_settlements')->insert([
            'company_id' => $company->id, 'gateway' => 'TAP', 'settlement_channel' => 'tap',
            'payout_reference' => 'DUP-DB-1', 'payout_date' => '2026-08-21',
            'gross' => 20, 'fee' => 0, 'net' => 20, 'recognised_fee' => 0,
            'currency' => 'KWD', 'bank_account_id' => $bank->id, 'status' => 'recorded',
            'source' => 'manual', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
