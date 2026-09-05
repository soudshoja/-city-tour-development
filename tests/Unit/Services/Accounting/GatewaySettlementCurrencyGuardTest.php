<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Exceptions\Accounting\GatewaySettlementCurrencyMismatchException;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\GatewaySettlement;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\GatewaySettlementService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;
use Tests\Support\SeedsGatewayClearing;

/**
 * accounting-builds T7 (Lane D) — post-sign-off fix (review packet §12 finding 3, Fable
 * orchestrator sign-off 2026-09-02):
 *
 * "Multi-currency (NOTE, close before any non-KWD payout). `currency` is accepted by HTTP/CLI/CSV
 * (not the modal) and posted with `exchangeRate 1.0`, `originalAmount = amount`; PostingService
 * step 3f accepts that as a consistent FC line, so a USD payout books its USD figure as KWD.
 * Clearing and receipts are base-only. Recommend `record()` refuse `currency != base` until a
 * rate input exists (one guard + one test)."
 *
 * {@see GatewaySettlementService::record()} now refuses pre-flight — before the `gateway_settlements`
 * row exists and before any of the money guards run — when the requested currency is not the
 * company's base currency (`config('accounting.engine.base_currency')`, KWD).
 */
class GatewaySettlementCurrencyGuardTest extends AccountingTestCase
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

    public function test_a_non_base_currency_payout_is_refused_pre_flight_nothing_saved_or_posted(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);
        $this->seedGatewayClearing($company, 'TAP', 900.000);

        $txnsBefore = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count();
        $linesBefore = JournalEntry::withoutGlobalScopes()->where('company_id', $company->id)->count();

        try {
            $this->service()->record(
                companyId: $company->id, gateway: 'TAP', payoutReference: 'USD-1',
                payoutDate: Carbon::parse('2026-08-20'), gross: 900.000, fee: 0.000, net: 900.000,
                bankAccountId: $bank->id, currency: 'USD',
            );
            $this->fail('expected a GatewaySettlementCurrencyMismatchException');
        } catch (GatewaySettlementCurrencyMismatchException $e) {
            $this->assertSame('TAP', $e->gateway);
            $this->assertSame('USD-1', $e->payoutReference);
            $this->assertSame('USD', $e->requestedCurrency);
            $this->assertSame('KWD', $e->baseCurrency);
        }

        $this->assertSame(
            0,
            GatewaySettlement::withoutGlobalScopes()->where('company_id', $company->id)->where('payout_reference', 'USD-1')->count(),
            'a currency-refused payout must leave no settlement row — the key must stay free for a corrected re-record.'
        );
        $this->assertSame($txnsBefore, Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count(), 'a currency-refused payout must post no document.');
        $this->assertSame($linesBefore, JournalEntry::withoutGlobalScopes()->where('company_id', $company->id)->count(), 'a currency-refused payout must write no journal line.');
    }

    public function test_currency_check_is_case_insensitive_and_trims_whitespace(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);
        $this->seedGatewayClearing($company, 'TAP', 100.000);

        $this->expectException(GatewaySettlementCurrencyMismatchException::class);

        $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'LOWERCASE-USD-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 100.000, fee: 0.000, net: 100.000,
            bankAccountId: $bank->id, currency: ' usd ',
        );
    }

    public function test_a_kwd_payout_still_posts_as_before(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);
        $this->seedGatewayClearing($company, 'TAP', 100.000);

        $settlement = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'KWD-EXPLICIT-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 100.000, fee: 0.000, net: 100.000,
            bankAccountId: $bank->id, currency: 'KWD',
        );

        $this->assertSame(GatewaySettlement::STATUS_POSTED, $settlement->status);
        $this->assertSame('KWD', $settlement->currency);
    }

    public function test_an_unspecified_currency_still_defaults_to_base_and_posts(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $bank = $this->bankAccount($company);
        $this->seedGatewayClearing($company, 'TAP', 50.000);

        $settlement = $this->service()->record(
            companyId: $company->id, gateway: 'TAP', payoutReference: 'NULL-CURRENCY-1',
            payoutDate: Carbon::parse('2026-08-20'), gross: 50.000, fee: 0.000, net: 50.000,
            bankAccountId: $bank->id,
        );

        $this->assertSame(GatewaySettlement::STATUS_POSTED, $settlement->status);
        $this->assertSame('KWD', $settlement->currency);
    }
}
