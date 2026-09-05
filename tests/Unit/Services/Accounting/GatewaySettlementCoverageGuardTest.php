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
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\GatewaySettlementService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;
use Tests\Support\SeedsGatewayClearing;

/**
 * accounting-builds T7 — POST-FIX RE-VERIFICATION (fresh adversarial pass over commits
 * 66eeae34 + 7794cd07 + 61af47c7).
 *
 * Two money-safety holes the first adversarial pass's own fixes left open:
 *
 *  1. **Non-aligned payouts.** `Payment.completed` is a BOOLEAN — a Payment cannot be half
 *     released. The greedy oldest-first sweep introduced in 7794cd07 stops at the last payment
 *     that FITS inside `gross`, so a payout whose gross does not land exactly on a payment
 *     boundary (pending 100/200/300, payout 250) drains 250 from clearing while marking only
 *     100 as swept — the daily release job then moves the remaining 500 as well, taking 750 out
 *     of a clearing account that only ever received 600. Marking the overshooting payment
 *     instead simply inverts the error (money orphaned in clearing). Neither variant is
 *     recoverable, so a non-aligned payout is exactly the "unmatched case" the owner-approved
 *     spec says must "produce exceptions, never silent absorption".
 *
 *  2. **The pending-pool-empty escape.** 7794cd07's over-settlement guard compares `gross`
 *     against the pending `Payment` pool and SKIPS ENTIRELY when that pool is empty — so a
 *     payout recorded against a gateway with no unswept payments posts unconditionally and
 *     drives clearing negative, the precise outcome the guard exists to prevent. The pool is a
 *     proxy; the money is the CLEARING ACCOUNT BALANCE, derived from journal lines (never
 *     `accounts.actual_balance`). Guarding on the derived balance closes the escape and
 *     subsumes the pool check.
 */
class GatewaySettlementCoverageGuardTest extends AccountingTestCase
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

    private function makePayment(Company $company, Client $client, float $amount, string $date): Payment
    {
        return Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $client->agent_id, 'client_id' => $client->id,
            'payment_gateway' => 'tap', 'payment_date' => $date, 'completed' => 0, 'amount' => $amount,
            'invoice_id' => null, 'account_id' => null, 'created_by' => null,
        ]);
    }

    // ── 1. Payment-boundary alignment ──────────────────────────────────────────────────────────

    public function test_payout_that_does_not_land_on_a_payment_boundary_is_refused(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);
        $client = $this->makeClient($company, $branch);

        $this->seedGatewayClearing($company, 'TAP', 600.000);
        $this->makePayment($company, $client, 100.000, '2026-08-18');
        $this->makePayment($company, $client, 200.000, '2026-08-19');
        $this->makePayment($company, $client, 300.000, '2026-08-20');

        // 250 lands between payment boundaries (100 | 100+200=300). Sweeping only the 100 leaves
        // 500 still completed=0 for the daily job, which would then move 500 more out of a
        // clearing account that only holds 600 — 750 released against 600 received.
        $this->expectException(\App\Exceptions\Accounting\GatewaySettlementUnmatchedException::class);

        $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'MISALIGN-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 250.000, fee: 0.000, net: 250.000,
            bankAccountId: $bank->id,
        );
    }

    public function test_payout_smaller_than_the_oldest_pending_payment_is_refused(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);
        $client = $this->makeClient($company, $branch);

        $this->seedGatewayClearing($company, 'TAP', 100.000);
        $this->makePayment($company, $client, 100.000, '2026-08-18');

        // Covers zero payments — the greedy sweep marks nothing, so the daily job still releases
        // the full 100 on top of this payout's own 50.
        $this->expectException(\App\Exceptions\Accounting\GatewaySettlementUnmatchedException::class);

        $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'MISALIGN-2',
            payoutDate: Carbon::parse('2026-08-20'), gross: 50.000, fee: 0.000, net: 50.000,
            bankAccountId: $bank->id,
        );
    }

    public function test_exactly_aligned_payout_sweeps_every_covered_payment_and_posts(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);
        $client = $this->makeClient($company, $branch);

        $this->seedGatewayClearing($company, 'TAP', 600.000);
        $p1 = $this->makePayment($company, $client, 100.000, '2026-08-18');
        $p2 = $this->makePayment($company, $client, 200.000, '2026-08-19');
        $p3 = $this->makePayment($company, $client, 300.000, '2026-08-20');

        $settlement = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'ALIGNED-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 300.000, fee: 0.000, net: 300.000,
            bankAccountId: $bank->id,
        );

        $this->assertSame(GatewaySettlement::STATUS_POSTED, $settlement->status);
        $this->assertSame(1, (int) Payment::withoutGlobalScopes()->where('id', $p1->id)->value('completed'));
        $this->assertSame(1, (int) Payment::withoutGlobalScopes()->where('id', $p2->id)->value('completed'));
        $this->assertSame(0, (int) Payment::withoutGlobalScopes()->where('id', $p3->id)->value('completed'), 'the uncovered 300 stays pending — a later payout or the daily job releases it.');
    }

    // ── 2. Derived clearing-balance guard (no pending-pool escape) ─────────────────────────────

    public function test_empty_pending_pool_with_no_clearing_balance_is_refused(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);

        // No Payment rows AND nothing in clearing: this payout would move money that is not
        // there, driving GATEWAY_CLEARING_TAP negative.
        $this->expectException(\App\Exceptions\Accounting\GatewayOverSettledException::class);

        $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'NOMONEY-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 1000.000, fee: 5.000, net: 995.000,
            bankAccountId: $bank->id,
        );
    }

    public function test_empty_pending_pool_with_a_sufficient_clearing_balance_posts(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);

        // Money genuinely sitting in clearing but with no local Payment linkage (a from-scratch
        // manual/CSV payout) must still settle — the guard is about the money, not the linkage.
        $this->seedGatewayClearing($company, 'TAP', 1000.000);

        $settlement = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'NOLINK-OK-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 1000.000, fee: 5.000, net: 995.000,
            bankAccountId: $bank->id,
        );

        $this->assertSame(GatewaySettlement::STATUS_POSTED, $settlement->status);
    }

    public function test_clearing_drain_uses_gross_minus_recognised_fee_not_raw_gross(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);

        // Receipts already booked fee at receipt, so clearing only ever held gross - recognised.
        $this->seedGatewayClearing($company, 'TAP', 488.000);

        $settlement = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'DRAIN-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 500.000, fee: 20.000, net: 480.000,
            bankAccountId: $bank->id, recognisedFee: 12.000,
        );

        $this->assertSame(GatewaySettlement::STATUS_POSTED, $settlement->status, 'drain = 500 - 12 = 488 exactly equals the clearing balance — must post, not be mistaken for an over-settlement on raw gross.');

        $clearing = app(AccountResolver::class)->resolve('GATEWAY_CLEARING_TAP', $company->id);
        $balance = (float) JournalEntry::withoutGlobalScopes()->where('account_id', $clearing->id)->sum('debit')
            - (float) JournalEntry::withoutGlobalScopes()->where('account_id', $clearing->id)->sum('credit');

        $this->assertEqualsWithDelta(0.0, $balance, 0.0005, 'a fully-settled clearing account must land exactly on zero, to the fils.');
    }

    public function test_negative_fee_true_up_credits_fee_expense_and_still_zeroes_clearing_to_the_fils(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);

        // Over-recognised at receipt: actual fee 10.125 vs recognised 18.750, so the true-up is
        // -8.625 and belongs on the CREDIT side of fee expense (an expense REDUCTION), while
        // clearing only ever held 500.250 - 18.750 = 481.500.
        $this->seedGatewayClearing($company, 'TAP', 481.500);

        $settlement = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'NEGTRUEUP-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 500.250, fee: 10.125, net: 490.125,
            bankAccountId: $bank->id, recognisedFee: 18.750,
        );

        $resolver = app(AccountResolver::class);
        $feeAccount = $resolver->resolve('GATEWAY_FEE_EXPENSE_TAP', $company->id);
        $clearing = $resolver->resolve('GATEWAY_CLEARING_TAP', $company->id);

        $lines = JournalEntry::withoutGlobalScopes()->where('transaction_id', $settlement->transaction_id)->get();

        $feeLine = $lines->firstWhere('account_id', $feeAccount->id);
        $this->assertNotNull($feeLine);
        $this->assertEqualsWithDelta(8.625, (float) $feeLine->credit, 0.0005, 'actual fee BELOW recognised must CREDIT fee expense — a reduction of an already-booked expense, never a second debit.');
        $this->assertEqualsWithDelta(0.0, (float) $feeLine->debit, 0.0005);

        $bankLine = $lines->firstWhere('account_id', $bank->id);
        $this->assertEqualsWithDelta(490.125, (float) $bankLine->debit, 0.0005);

        $clearingLine = $lines->firstWhere('account_id', $clearing->id);
        $this->assertEqualsWithDelta(481.500, (float) $clearingLine->credit, 0.0005);

        // Dr 490.125 = Cr 481.500 + Cr 8.625 — balanced to the fils.
        $this->assertEqualsWithDelta((float) $lines->sum('debit'), (float) $lines->sum('credit'), 0.0005);

        $balance = (float) JournalEntry::withoutGlobalScopes()->where('account_id', $clearing->id)->sum('debit')
            - (float) JournalEntry::withoutGlobalScopes()->where('account_id', $clearing->id)->sum('credit');
        $this->assertEqualsWithDelta(0.0, $balance, 0.0005, 'clearing must land exactly on zero in the negative-true-up direction too.');
    }

    public function test_a_payout_one_fils_over_the_clearing_balance_is_refused(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);

        $this->seedGatewayClearing($company, 'TAP', 100.000);

        $this->expectException(\App\Exceptions\Accounting\GatewayOverSettledException::class);

        $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'FILS-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 100.001, fee: 0.000, net: 100.001,
            bankAccountId: $bank->id,
        );
    }

    // ── 3. Engine OFF must still only record, never guard-throw ────────────────────────────────

    public function test_engine_off_still_records_without_running_the_money_guards(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);

        config(['accounting.engine.enabled' => false]);

        $bank = $this->bankAccount($company);

        $settlement = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'OFF-GUARD-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 9999.000, fee: 0.000, net: 9999.000,
            bankAccountId: $bank->id,
        );

        $this->assertSame(GatewaySettlement::STATUS_RECORDED, $settlement->status);
        $this->assertNull($settlement->transaction_id);
    }
}
