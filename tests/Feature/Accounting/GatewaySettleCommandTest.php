<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\GatewaySettlement;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T7 (Lane D): the `accounting:gateway-settle` CLI wrapper around
 * {@see \App\Services\Accounting\GatewaySettlementService}.
 */
class GatewaySettleCommandTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Account} */
    private function makeCompanyAndBank(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $owner = User::factory()->create();
        Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        config(['accounting.engine.enabled' => true]);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->trackCompanyForInvariants($company->id);

        $bank = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();

        return [$company, $bank];
    }

    public function test_single_payout_records_and_posts(): void
    {
        [$company, $bank] = $this->makeCompanyAndBank();

        Artisan::call('accounting:gateway-settle', [
            'company' => $company->id,
            '--gateway' => 'TAP',
            '--payout-ref' => 'CLI-PO-1',
            '--gross' => '100.000',
            '--fee' => '5.000',
            '--net' => '95.000',
            '--date' => '2026-08-20',
            '--bank' => (string) $bank->id,
        ]);

        $this->assertSame(0, Artisan::call('accounting:gateway-settle', [
            'company' => $company->id, '--gateway' => 'TAP', '--payout-ref' => 'CLI-PO-1',
            '--gross' => '100.000', '--fee' => '5.000', '--net' => '95.000',
            '--date' => '2026-08-20', '--bank' => (string) $bank->id,
        ]), 'idempotent replay must still exit 0.');

        $settlement = GatewaySettlement::forCompany($company->id)->where('payout_reference', 'CLI-PO-1')->firstOrFail();
        $this->assertSame(GatewaySettlement::STATUS_POSTED, $settlement->status);
    }

    public function test_missing_required_option_fails_without_recording(): void
    {
        [$company] = $this->makeCompanyAndBank();

        $exit = Artisan::call('accounting:gateway-settle', [
            'company' => $company->id, '--gateway' => 'TAP', '--payout-ref' => 'CLI-PO-2',
            // gross/fee/net/date/bank all missing
        ]);

        $this->assertSame(1, $exit);
        $this->assertSame(0, GatewaySettlement::forCompany($company->id)->count());
    }

    public function test_invalid_bank_account_fails_with_a_clear_message(): void
    {
        [$company] = $this->makeCompanyAndBank();

        $exit = Artisan::call('accounting:gateway-settle', [
            'company' => $company->id, '--gateway' => 'TAP', '--payout-ref' => 'CLI-PO-3',
            '--gross' => '100.000', '--fee' => '5.000', '--net' => '95.000',
            '--date' => '2026-08-20', '--bank' => '999999',
        ]);

        $this->assertSame(1, $exit, 'a nonexistent bank account must fail the command (exit 1), never silently succeed.');
        $this->assertSame(0, GatewaySettlement::forCompany($company->id)->where('payout_reference', 'CLI-PO-3')->count());
    }

    public function test_csv_batch_imports_multiple_payouts(): void
    {
        [$company, $bank] = $this->makeCompanyAndBank();

        $csv = sys_get_temp_dir().'/gateway-settle-test-'.uniqid().'.csv';
        file_put_contents($csv, implode("\n", [
            'gateway,payout_reference,payout_date,gross,fee,net,bank_account_id',
            "TAP,CSV-1,2026-08-20,50.000,2.500,47.500,{$bank->id}",
            "TAP,CSV-2,2026-08-21,60.000,3.000,57.000,{$bank->id}",
        ]));

        try {
            $exit = Artisan::call('accounting:gateway-settle', [
                'company' => $company->id, '--file' => $csv,
            ]);

            $this->assertSame(0, $exit);
            $this->assertSame(2, GatewaySettlement::forCompany($company->id)->where('source', GatewaySettlement::SOURCE_CSV)->count());
        } finally {
            @unlink($csv);
        }
    }
}
