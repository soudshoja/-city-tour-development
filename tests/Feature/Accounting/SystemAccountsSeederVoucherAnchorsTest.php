<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Company;
use App\Services\Accounting\AccountResolver;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * W5.L item 4 (w5-brief.md §W5.L): the five voucher/instrument anchor purpose codes —
 * CASH_IN_HAND, CHEQUES_IN_HAND (new leaf 1215), CHEQUES_ISSUED_NOT_CLEARED (new leaf 2215),
 * BANK_CHARGES_EXPENSE (new leaf 5222 — see this file's own note on why "existing" did not hold),
 * CASH_OVER_SHORT (new leaf 5127) — must all be mapped, to a real leaf, on a fresh CoaSeeder chart.
 */
class SystemAccountsSeederVoucherAnchorsTest extends AccountingTestCase
{
    private function assertMappedToLeaf(Company $company, string $purposeCode, string $expectedCode, string $expectedName): void
    {
        $mapping = DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', $purposeCode)
            ->whereNull('service_type')
            ->first();

        $this->assertNotNull($mapping, "{$purposeCode} must be mapped on a fresh CoaSeeder chart, never reported as a gap.");

        $account = Account::withoutGlobalScopes()->find($mapping->account_id);

        $this->assertNotNull($account, "The mapped account for {$purposeCode} must still exist.");
        $this->assertSame($expectedCode, $account->code, "{$purposeCode} must resolve to code {$expectedCode}.");
        $this->assertSame($expectedName, $account->name);
        $this->assertTrue(AccountResolver::isLeaf($account), "{$expectedName} ({$expectedCode}) must resolve to a true leaf.");
        $this->assertSame($company->id, $account->company_id);
    }

    public function test_all_five_voucher_anchor_purpose_codes_map_on_a_fresh_chart(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();

        $this->assertMappedToLeaf($company, 'CASH_IN_HAND', '1120', 'Receipt Voucher Cash');
        $this->assertMappedToLeaf($company, 'CHEQUES_IN_HAND', '1215', 'Cheques In Hand');
        $this->assertMappedToLeaf($company, 'CHEQUES_ISSUED_NOT_CLEARED', '2215', 'Cheques Issued Not Cleared');
        $this->assertMappedToLeaf($company, 'BANK_CHARGES_EXPENSE', '5222', 'Bank Charges');
        $this->assertMappedToLeaf($company, 'CASH_OVER_SHORT', '5127', 'Cash Over/Short');
    }

    /**
     * CHEQUES_IN_HAND (1215) must be a peer of 'Cash In Hand' (1100) directly under 'Assets', not
     * nested inside the 'Cash In Hand' group — pins the exact tree shape CoaSeeder's own comment
     * documents.
     */
    public function test_cheques_in_hand_sits_directly_under_assets(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        CoaSeeder::run($company->id);

        $account = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('code', '1215')
            ->first();

        $this->assertNotNull($account);
        $this->assertSame('Assets', $account->parent->name);
    }

    /**
     * CHEQUES_ISSUED_NOT_CLEARED (2215) must be a direct child of 'Liabilities', not nested inside
     * 'Accrued Expenses' (which auto-numbers agent-profit leaves upward from 2230).
     */
    public function test_cheques_issued_not_cleared_sits_directly_under_liabilities(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        CoaSeeder::run($company->id);

        $account = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('code', '2215')
            ->first();

        $this->assertNotNull($account);
        $this->assertSame('Liabilities', $account->parent->name);
    }

    /**
     * BANK_CHARGES_EXPENSE (5222) must live under 'Indirect Expenses (Operating Expenses)' (5200)
     * — deliberately NOT under 'Payment Gateway Charges' (5140), which would make it a candidate
     * for SystemAccountsSeeder::resolveGatewayFeeExpense()'s "neutral leaf" fallback and get it
     * silently adopted as a gateway's fee-expense leaf (see CoaSeeder's own comment on this code
     * for the full proof-by-execution).
     */
    public function test_bank_charges_sits_under_indirect_expenses_not_the_gateway_charges_family(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        CoaSeeder::run($company->id);

        $account = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('code', '5222')
            ->first();

        $this->assertNotNull($account);
        $this->assertSame('Indirect Expenses (Operating Expenses)', $account->parent->name);

        $gatewayChargeSiblingCodes = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Payment Gateway Charges')
            ->first()
            ->children()
            ->pluck('code')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['5141', '5142', '5143', '5144', '5145'],
            $gatewayChargeSiblingCodes,
            "Bank Charges (5222) must NOT be a child of 'Payment Gateway Charges' — that family "
                .'must stay exactly the five per-gateway charge leaves.'
        );
    }

    /**
     * CASH_OVER_SHORT (5127) must never collide with 5126, the code PostingService's own
     * resolved-gap #9 note and InvoiceController::postAgentLossRecoveryHook()'s docblock both
     * already reserve for P5.13's not-yet-built agent-loss-recovery leaf.
     */
    public function test_cash_over_short_does_not_collide_with_the_reserved_5126_code(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        CoaSeeder::run($company->id);

        $this->assertFalse(
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5126')->exists(),
            '5126 is reserved for P5.13 and must remain unused by this wave.'
        );
        $this->assertTrue(
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5127')->exists()
        );
    }
}
