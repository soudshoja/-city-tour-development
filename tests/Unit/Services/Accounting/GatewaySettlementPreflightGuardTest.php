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
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\GatewaySettlementService;
use App\Services\Accounting\PostingService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;
use Tests\Support\SeedsGatewayClearing;

/**
 * accounting-builds T7 — FINAL re-verification pass (loop 3) over ce0b3612.
 *
 * ce0b3612 made three claims that its own tests did not pin:
 *
 *  (c) the money guards run PRE-FLIGHT in `record()`, so a refused payout never persists a
 *      `recorded` row occupying its (company, gateway, payout_reference) key and the operator's
 *      corrected re-record under the SAME reference goes through — while a same-key/different-
 *      figures re-record AFTER a successful post is still the genuine conflict it always was.
 *      Nothing in GatewaySettlementCoverageGuardTest / ...ServiceTest / ...AdversarialTest
 *      exercises the key-occupancy consequence at all, so a regression that moved the guards
 *      back inside `post()` would have stayed green.
 *
 *  (b) the over-settlement guard reads the DERIVED clearing balance. "Derived" carries scoping
 *      obligations the guard's own tests never state: another company's clearing money must not
 *      fund this company's payout, soft-deleted lines are not money, a REVERSED settlement puts
 *      the money back (the guard nets reversal lines rather than double-counting the original),
 *      and the invariant must be INDUCTIVE — a second payout against an already-drained clearing
 *      is refused even for a single fils.
 *
 *  (atomicity) a refused payout must post no document, write no journal line, persist no
 *      settlement row and mark no `Payment` completed — the whole point of refusing pre-flight.
 *
 * Engine-OFF recordings are guard-free by design; this file also pins that turning the engine ON
 * afterwards does NOT let such a row post unchecked — `post()` re-runs the identical guards.
 *
 * NOT pinned here, deliberately: whether the payment-boundary rule (greedy oldest-first) is the
 * right MATCHING rule for real payouts. It over-refuses legitimate payouts (see §11 of the T7
 * review packet) and the widening is an owner decision, so this file avoids freezing that
 * behaviour in either direction.
 */
class GatewaySettlementPreflightGuardTest extends AccountingTestCase
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

    // ── Pre-flight ordering: refuse → key free → correct → post → conflict ────────────────────

    public function test_refused_payout_leaves_the_key_free_then_corrected_figures_post_then_conflict(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);

        $this->seedGatewayClearing($company, 'TAP', 500.000);

        // 1. refused pre-flight — nothing persists, so the reference stays re-usable.
        try {
            $this->service()->record(
                companyId: $company->id, gateway: 'TAP', payoutReference: 'PREFLIGHT-1',
                payoutDate: Carbon::parse('2026-08-20'), gross: 900.000, fee: 0.000, net: 900.000,
                bankAccountId: $bank->id,
            );
            $this->fail('expected a GatewayOverSettledException');
        } catch (\App\Exceptions\Accounting\GatewayOverSettledException) {
            // expected
        }

        $this->assertSame(
            0,
            GatewaySettlement::withoutGlobalScopes()->where('company_id', $company->id)->where('payout_reference', 'PREFLIGHT-1')->count(),
            'a refused payout must leave no row occupying its (company, gateway, payout_reference) key — otherwise the correction the exception asks for collides with the same-key conflict check.'
        );

        // 2. the operator corrects the figures under the SAME reference — must go through.
        $ok = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'PREFLIGHT-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 500.000, fee: 10.000, net: 490.000,
            bankAccountId: $bank->id,
        );
        $this->assertSame(GatewaySettlement::STATUS_POSTED, $ok->status);

        // 3. same reference, DIFFERENT figures, after a successful post — still a real conflict.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/genuine conflict/');

        $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'PREFLIGHT-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 400.000, fee: 10.000, net: 390.000,
            bankAccountId: $bank->id,
        );
    }

    // ── Refusal atomicity ─────────────────────────────────────────────────────────────────────

    public function test_a_refused_payout_posts_nothing_and_completes_no_payment(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);
        $client = $this->makeClient($company, $branch);

        $this->seedGatewayClearing($company, 'TAP', 600.000);
        $p1 = $this->makePayment($company, $client, 100.000, '2026-08-18');
        $p2 = $this->makePayment($company, $client, 200.000, '2026-08-19');
        $p3 = $this->makePayment($company, $client, 300.000, '2026-08-20');

        $txnsBefore = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count();
        $linesBefore = JournalEntry::withoutGlobalScopes()->where('company_id', $company->id)->count();

        // Unmatched gross AND a non-zero fee true-up: neither may leave a trace.
        try {
            $this->service()->record(
                companyId: $company->id, gateway: 'TAP', payoutReference: 'ATOMIC-1',
                payoutDate: Carbon::parse('2026-08-20'), gross: 250.000, fee: 7.500, net: 242.500,
                bankAccountId: $bank->id, recognisedFee: 1.000,
            );
            $this->fail('expected a GatewaySettlementUnmatchedException');
        } catch (\App\Exceptions\Accounting\GatewaySettlementUnmatchedException) {
            // expected
        }

        $this->assertSame($txnsBefore, Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count(), 'a refused payout must post no document.');
        $this->assertSame($linesBefore, JournalEntry::withoutGlobalScopes()->where('company_id', $company->id)->count(), 'a refused payout must write no journal line.');
        $this->assertSame(0, GatewaySettlement::withoutGlobalScopes()->where('company_id', $company->id)->count(), 'a refused payout must persist no settlement row.');

        foreach ([$p1, $p2, $p3] as $payment) {
            $this->assertSame(0, (int) Payment::withoutGlobalScopes()->where('id', $payment->id)->value('completed'), 'a refused payout must not mark any Payment as released.');
        }
    }

    // ── Derived-balance scoping ───────────────────────────────────────────────────────────────

    public function test_derived_clearing_balance_does_not_leak_across_companies(): void
    {
        [$other] = $this->makeEngineOnCompany();
        $this->seedGatewayClearing($other, 'TAP', 5000.000);

        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);

        $this->expectException(\App\Exceptions\Accounting\GatewayOverSettledException::class);

        $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'XCOMPANY-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 100.000, fee: 0.000, net: 100.000,
            bankAccountId: $bank->id,
        );
    }

    public function test_soft_deleted_clearing_lines_are_not_money(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);

        $this->seedGatewayClearing($company, 'TAP', 100.000);

        // Soft-delete BOTH legs of the seeding receipt (keeps Σdebit = Σcredit per transaction).
        $seedTxn = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('doc_type', 'RV')->latest('id')->firstOrFail();
        JournalEntry::withoutGlobalScopes()->where('transaction_id', $seedTxn->id)->delete();

        $this->expectException(\App\Exceptions\Accounting\GatewayOverSettledException::class);

        $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'SOFTDEL-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 100.000, fee: 0.000, net: 100.000,
            bankAccountId: $bank->id,
        );
    }

    public function test_the_guard_is_inductive_a_second_payout_against_drained_clearing_is_refused(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);

        $this->seedGatewayClearing($company, 'TAP', 300.000);

        $first = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'DRAINED-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 300.000, fee: 0.000, net: 300.000,
            bankAccountId: $bank->id,
        );
        $this->assertSame(GatewaySettlement::STATUS_POSTED, $first->status);

        // One fils more than clearing now holds.
        $this->expectException(\App\Exceptions\Accounting\GatewayOverSettledException::class);

        $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'DRAINED-2',
            payoutDate: Carbon::parse('2026-08-21'), gross: 0.001, fee: 0.000, net: 0.001,
            bankAccountId: $bank->id,
        );
    }

    public function test_reversing_a_settlement_returns_the_money_to_clearing_for_the_guard(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);

        $this->seedGatewayClearing($company, 'TAP', 300.000);

        $settlement = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'REV-SRC-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 300.000, fee: 0.000, net: 300.000,
            bankAccountId: $bank->id,
        );

        $posted = Transaction::withoutGlobalScopes()->findOrFail($settlement->transaction_id);
        app(PostingService::class)->reverse($posted, Carbon::parse('2026-08-21'), null);

        // The reversal put the 300 back, so a corrected payout for the same money must be
        // allowed again — the guard nets reversal lines instead of remembering the original.
        $again = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'REV-REPLACEMENT-1',
            payoutDate: Carbon::parse('2026-08-21'), gross: 300.000, fee: 0.000, net: 300.000,
            bankAccountId: $bank->id,
        );

        $this->assertSame(GatewaySettlement::STATUS_POSTED, $again->status);
    }

    /**
     * TOCTOU between the guard and the sweep: the guard validated one payment set, and the sweep
     * used to RE-QUERY the pool after the document had posted. A receipt for the same gateway
     * landing in between (a gateway webhook capturing a payment while an operator records a
     * payout) moves the greedy walk onto a different subset — so the document drains `gross`
     * while marking a set that no longer sums to it, which is exactly the strand/double-move
     * corruption Guard B refuses, reintroduced through a race instead of a bad payout.
     */
    public function test_the_sweep_marks_the_set_the_guard_validated_not_a_later_re_read(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);
        $client = $this->makeClient($company, $branch);

        $this->seedGatewayClearing($company, 'TAP', 350.000);
        $validated = $this->makePayment($company, $client, 300.000, '2026-08-20');

        // A receipt lands DURING the posting transaction, older than the one the guard validated.
        $latecomer = null;
        JournalEntry::created(function () use (&$latecomer, $company, $client) {
            if ($latecomer !== null) {
                return;
            }
            $latecomer = $this->makePayment($company, $client, 50.000, '2026-08-18');
        });

        $settlement = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'TOCTOU-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 300.000, fee: 0.000, net: 300.000,
            bankAccountId: $bank->id,
        );

        $this->assertSame(GatewaySettlement::STATUS_POSTED, $settlement->status);
        $this->assertNotNull($latecomer, 'the race precondition never happened — the test proves nothing as written.');

        $this->assertSame(1, (int) Payment::withoutGlobalScopes()->where('id', $validated->id)->value('completed'), 'the 300 the guard validated is what this payout released.');
        $this->assertSame(0, (int) Payment::withoutGlobalScopes()->where('id', $latecomer->id)->value('completed'), 'a receipt that arrived after the guard ran is not part of this payout — its own money is still in clearing for the daily job.');
    }

    // ── Engine OFF now, ON later ──────────────────────────────────────────────────────────────

    public function test_an_engine_off_recording_is_still_guarded_when_it_is_posted_later(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);

        config(['accounting.engine.enabled' => false]);
        $bank = $this->bankAccount($company);

        $settlement = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'OFFTHENON-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 9999.000, fee: 0.000, net: 9999.000,
            bankAccountId: $bank->id,
        );
        $this->assertSame(GatewaySettlement::STATUS_RECORDED, $settlement->status);

        config(['accounting.engine.enabled' => true]);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $this->expectException(\App\Exceptions\Accounting\GatewayOverSettledException::class);

        $this->service()->post($settlement->refresh());
    }

    // ── Ordinary multi-batch operation still works ────────────────────────────────────────────

    public function test_batches_settled_in_arrival_order_both_post_and_sweep_their_own_payments(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);
        $client = $this->makeClient($company, $branch);

        $this->seedGatewayClearing($company, 'TAP', 300.000);
        $p1 = $this->makePayment($company, $client, 100.000, '2026-08-18');
        $p2 = $this->makePayment($company, $client, 200.000, '2026-08-19');

        $first = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'INORDER-1',
            payoutDate: Carbon::parse('2026-08-18'), gross: 100.000, fee: 0.000, net: 100.000,
            bankAccountId: $bank->id,
        );
        $second = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'INORDER-2',
            payoutDate: Carbon::parse('2026-08-19'), gross: 200.000, fee: 0.000, net: 200.000,
            bankAccountId: $bank->id,
        );

        $this->assertSame(GatewaySettlement::STATUS_POSTED, $first->status);
        $this->assertSame(GatewaySettlement::STATUS_POSTED, $second->status);
        $this->assertSame(1, (int) Payment::withoutGlobalScopes()->where('id', $p1->id)->value('completed'));
        $this->assertSame(1, (int) Payment::withoutGlobalScopes()->where('id', $p2->id)->value('completed'));
    }
}
