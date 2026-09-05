<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\GatewaySettlement;
use App\Models\Payment;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\GatewaySettlementService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T7 (Lane D — PLAN.md §5): {@see GatewaySettlementService}. Posting shapes
 * exact to the fils, fee true-up sign coverage, idempotency on (gateway, payout_reference),
 * bank-group refusal, engine-OFF no-op, settlement-channel stamping, and the daily-JV
 * non-double-move guard.
 */
class GatewaySettlementServiceTest extends AccountingTestCase
{
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

    // ── Posting shape, exact to the fils ────────────────────────────────────────────────────

    public function test_post_writes_the_exact_balanced_shape_for_each_gateway(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);
        $resolver = app(AccountResolver::class);

        foreach (['TAP', 'KNET', 'MYFATOORAH', 'HESABE', 'UPAYMENT'] as $gateway) {
            $settlement = $this->service()->record(
                companyId: $company->id,
                gateway: $gateway,
                payoutReference: "PAYOUT-{$gateway}-1",
                payoutDate: Carbon::parse('2026-08-20'),
                gross: 1000.500,
                fee: 25.250,
                net: 975.250,
                bankAccountId: $bank->id,
            );

            $this->assertSame(GatewaySettlement::STATUS_POSTED, $settlement->status);
            $this->assertNotNull($settlement->transaction_id);

            $lines = DB::table('journal_entries')->where('transaction_id', $settlement->transaction_id)->get();

            $this->assertCount(3, $lines, "{$gateway}: expected bank / clearing / fee-true-up (recognised_fee defaults to 0, so the full fee true-ups).");

            $bankLine = $lines->firstWhere('account_id', $bank->id);
            $this->assertNotNull($bankLine);
            $this->assertEqualsWithDelta(975.250, (float) $bankLine->debit, 0.0005);
            $this->assertEqualsWithDelta(0.0, (float) $bankLine->credit, 0.0005);

            $clearingAccount = $resolver->resolve("GATEWAY_CLEARING_{$gateway}", $company->id);
            $clearingLine = $lines->firstWhere('account_id', $clearingAccount->id);
            $this->assertNotNull($clearingLine, "{$gateway}: no GATEWAY_CLEARING line found.");
            $this->assertEqualsWithDelta(1000.500, (float) $clearingLine->credit, 0.0005);

            $feeAccount = $resolver->resolve("GATEWAY_FEE_EXPENSE_{$gateway}", $company->id);
            $feeLine = $lines->firstWhere('account_id', $feeAccount->id);
            $this->assertNotNull($feeLine, "{$gateway}: no GATEWAY_FEE_EXPENSE true-up line found.");
            $this->assertEqualsWithDelta(25.250, (float) $feeLine->debit, 0.0005);

            $totalDebit = (float) $lines->sum('debit');
            $totalCredit = (float) $lines->sum('credit');
            $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.0005, "{$gateway}: document does not balance.");
        }
    }

    // ── Fee true-up sign coverage ───────────────────────────────────────────────────────────

    public function test_fee_true_up_positive_when_actual_fee_exceeds_recognised(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);
        $resolver = app(AccountResolver::class);

        $settlement = $this->service()->record(
            companyId: $company->id,
            gateway: 'TAP',
            payoutReference: 'PO-POS-1',
            payoutDate: Carbon::parse('2026-08-20'),
            gross: 500.000,
            fee: 20.000,
            net: 480.000,
            bankAccountId: $bank->id,
            recognisedFee: 12.000,
        );

        $feeAccount = $resolver->resolve('GATEWAY_FEE_EXPENSE_TAP', $company->id);
        $line = DB::table('journal_entries')->where('transaction_id', $settlement->transaction_id)->where('account_id', $feeAccount->id)->first();

        $this->assertNotNull($line);
        $this->assertEqualsWithDelta(8.000, (float) $line->debit, 0.0005, 'true-up = fee(20) - recognised(12) = 8, on the DEBIT side (under-recognised).');
        $this->assertEqualsWithDelta(0.0, (float) $line->credit, 0.0005);
    }

    public function test_fee_true_up_negative_when_actual_fee_is_below_recognised(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);
        $resolver = app(AccountResolver::class);

        $settlement = $this->service()->record(
            companyId: $company->id,
            gateway: 'TAP',
            payoutReference: 'PO-NEG-1',
            payoutDate: Carbon::parse('2026-08-20'),
            gross: 500.000,
            fee: 10.000,
            net: 490.000,
            bankAccountId: $bank->id,
            recognisedFee: 18.000,
        );

        $feeAccount = $resolver->resolve('GATEWAY_FEE_EXPENSE_TAP', $company->id);
        $line = DB::table('journal_entries')->where('transaction_id', $settlement->transaction_id)->where('account_id', $feeAccount->id)->first();

        $this->assertNotNull($line);
        $this->assertEqualsWithDelta(8.000, (float) $line->credit, 0.0005, 'true-up = fee(10) - recognised(18) = -8, on the CREDIT side (over-recognised).');
        $this->assertEqualsWithDelta(0.0, (float) $line->debit, 0.0005);
    }

    public function test_fee_true_up_zero_omits_the_line_entirely(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);
        $resolver = app(AccountResolver::class);

        $settlement = $this->service()->record(
            companyId: $company->id,
            gateway: 'TAP',
            payoutReference: 'PO-ZERO-1',
            payoutDate: Carbon::parse('2026-08-20'),
            gross: 500.000,
            fee: 15.000,
            net: 485.000,
            bankAccountId: $bank->id,
            recognisedFee: 15.000,
        );

        $feeAccount = $resolver->resolve('GATEWAY_FEE_EXPENSE_TAP', $company->id);
        $line = DB::table('journal_entries')->where('transaction_id', $settlement->transaction_id)->where('account_id', $feeAccount->id)->first();

        $this->assertNull($line, 'true-up = 0 must omit the fee-expense line entirely, matching every other conditional-third-line feeder in this codebase.');

        $lines = DB::table('journal_entries')->where('transaction_id', $settlement->transaction_id)->get();
        $this->assertCount(2, $lines, 'zero true-up -> exactly 2 lines (bank / clearing).');
    }

    // ── Idempotency on (gateway, payout_reference) ──────────────────────────────────────────

    public function test_record_is_idempotent_on_gateway_and_payout_reference(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);

        $first = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'IDEMP-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 100.000, fee: 5.000, net: 95.000,
            bankAccountId: $bank->id,
        );

        $second = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'IDEMP-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 100.000, fee: 5.000, net: 95.000,
            bankAccountId: $bank->id,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->transaction_id, $second->transaction_id);
        $this->assertSame(1, GatewaySettlement::forCompany($company->id)->where('payout_reference', 'IDEMP-1')->count());
    }

    public function test_record_with_same_key_but_different_figures_throws_never_silently_overwrites(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);

        $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'CONFLICT-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 100.000, fee: 5.000, net: 95.000,
            bankAccountId: $bank->id,
        );

        $this->expectException(\RuntimeException::class);

        $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'CONFLICT-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 200.000, fee: 5.000, net: 195.000,
            bankAccountId: $bank->id,
        );
    }

    public function test_two_payouts_same_gateway_same_day_never_collapse(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);

        $a = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'SAMEDAY-A',
            payoutDate: Carbon::parse('2026-08-20'), gross: 100.000, fee: 5.000, net: 95.000,
            bankAccountId: $bank->id,
        );

        $b = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'SAMEDAY-B',
            payoutDate: Carbon::parse('2026-08-20'), gross: 300.000, fee: 5.000, net: 295.000,
            bankAccountId: $bank->id,
        );

        $this->assertNotSame($a->id, $b->id);
        $this->assertNotSame($a->transaction_id, $b->transaction_id);
    }

    // ── Bank-group refusal ───────────────────────────────────────────────────────────────────

    public function test_record_refuses_a_bank_account_not_under_the_bank_group(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $notABank = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5203')->firstOrFail();

        $this->expectException(\App\Exceptions\Accounting\AccountNotUnderGroupException::class);

        $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'BAD-BANK-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 100.000, fee: 5.000, net: 95.000,
            bankAccountId: $notABank->id,
        );
    }

    public function test_record_refuses_when_gross_does_not_equal_net_plus_fee(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'MISBALANCE-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 100.000, fee: 5.000, net: 90.000,
            bankAccountId: $bank->id,
        );
    }

    // ── Engine OFF ───────────────────────────────────────────────────────────────────────────

    public function test_engine_off_persists_the_record_but_posts_nothing(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $owner = User::factory()->create();
        Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        $this->trackCompanyForInvariants($company->id);

        config(['accounting.engine.enabled' => false]);

        $bank = $this->bankAccount($company);

        $before = DB::table('journal_entries')->count();

        $settlement = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'OFF-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 100.000, fee: 5.000, net: 95.000,
            bankAccountId: $bank->id,
        );

        $this->assertSame(GatewaySettlement::STATUS_RECORDED, $settlement->status);
        $this->assertNull($settlement->transaction_id);
        $this->assertSame($before, DB::table('journal_entries')->count(), 'engine OFF must post nothing.');
    }

    // ── Settlement channel stamped on every line ────────────────────────────────────────────

    public function test_every_line_of_the_gws_document_carries_the_settlement_channel(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);

        $settlement = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'CHANNEL-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 100.000, fee: 5.000, net: 95.000,
            bankAccountId: $bank->id, settlementChannel: 'tap:knet', recognisedFee: 2.000,
        );

        $lines = DB::table('journal_entries')->where('transaction_id', $settlement->transaction_id)->get();
        $this->assertGreaterThanOrEqual(2, $lines->count());

        foreach ($lines as $line) {
            $this->assertSame('tap:knet', $line->settlement_channel, "line on account #{$line->account_id} missing the settlement channel.");
        }
    }

    public function test_channel_defaults_to_bare_gateway_key_when_no_rail_supplied(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);

        $settlement = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'CHANNEL-2',
            payoutDate: Carbon::parse('2026-08-20'), gross: 100.000, fee: 5.000, net: 95.000,
            bankAccountId: $bank->id,
        );

        $this->assertSame('tap', $settlement->settlement_channel);
    }

    // ── Daily clearing->bank JV non-double-move ─────────────────────────────────────────────

    public function test_posted_settlement_stops_the_daily_release_job_from_resweeping_covered_payments(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);

        $agentType = \App\Models\AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
        $agentUser = User::factory()->create();
        $agent = \App\Models\Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);
        $client = \App\Models\Client::factory()->create(['agent_id' => $agent->id]);

        $payment1 = Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id,
            'payment_gateway' => 'tap', 'payment_date' => '2026-08-19', 'completed' => 0, 'amount' => 50,
            'invoice_id' => null, 'account_id' => null, 'created_by' => null,
        ]);
        $payment2 = Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id,
            'payment_gateway' => 'tap', 'payment_date' => '2026-08-20', 'completed' => 0, 'amount' => 50,
            'invoice_id' => null, 'account_id' => null, 'created_by' => null,
        ]);
        // A payment AFTER the payout date must NOT be swept by this settlement — still eligible
        // for the daily job's own future run.
        $paymentAfter = Payment::factory()->create([
            'company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id,
            'payment_gateway' => 'tap', 'payment_date' => '2026-08-21', 'completed' => 0, 'amount' => 50,
            'invoice_id' => null, 'account_id' => null, 'created_by' => null,
        ]);

        $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'SWEEP-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 100.000, fee: 5.000, net: 95.000,
            bankAccountId: $bank->id,
        );

        $this->assertSame(1, (int) Payment::withoutGlobalScopes()->where('id', $payment1->id)->value('completed'), 'payment dated before the payout must be marked completed (swept by the settlement, not the daily job).');
        $this->assertSame(1, (int) Payment::withoutGlobalScopes()->where('id', $payment2->id)->value('completed'), 'payment dated ON the payout date must be marked completed too.');
        $this->assertSame(0, (int) Payment::withoutGlobalScopes()->where('id', $paymentAfter->id)->value('completed'), 'payment dated AFTER the payout must be left alone — still eligible for the daily job.');

        $transactionCountBefore = DB::table('transactions')->where('company_id', $company->id)->count();

        Artisan::call('app:payment-release-to-company-bankacc-process');

        $transactionCountAfter = DB::table('transactions')->where('company_id', $company->id)->count();

        $this->assertSame(
            $transactionCountBefore,
            $transactionCountAfter,
            'the daily release job must post NOTHING for the (tap, 2026-08-19/20) group — every payment in it is already completed=1, covered by the payout-driven settlement instead.'
        );
    }
}
