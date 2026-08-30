<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Company;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * W5.L item 4 (w5-brief.md §W5.L): `accounting:ensure-system-leaves` must backfill the four new
 * voucher/instrument anchor leaves — 1215 "Cheques In Hand", 2215 "Cheques Issued Not Cleared",
 * 5127 "Cash Over/Short", and 5222 "Bank Charges" (all four CORE — see CoaSeeder's own comment on
 * code 5222 for why Bank Charges is core here, unlike KNET/uPayment Charges) — for a company whose
 * chart predates this wave, then re-map the purpose codes they back. Follows EnsureSystemLeavesTest's own
 * "old company" simulation convention (run the REAL current seeders, then surgically delete only
 * what this test is about) and its "DB state, never console text" proof convention (see that
 * file's own docblock for why Artisan::output() is unusable in this suite).
 */
class EnsureSystemLeavesVoucherAnchorsTest extends AccountingTestCase
{
    private function makeOldCompany(): Company
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();

        $chequesInHand = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1215')->firstOrFail();
        $chequesIssued = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '2215')->firstOrFail();
        $cashOverShort = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5127')->firstOrFail();
        $bankCharges = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5222')->firstOrFail();

        DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->whereIn('purpose_code', ['CHEQUES_IN_HAND', 'CHEQUES_ISSUED_NOT_CLEARED', 'CASH_OVER_SHORT', 'BANK_CHARGES_EXPENSE'])
            ->delete();

        Account::withoutGlobalScopes()
            ->whereIn('id', [$chequesInHand->id, $chequesIssued->id, $cashOverShort->id, $bankCharges->id])
            ->delete();

        return $company;
    }

    public function test_backfills_all_four_new_leaves_and_remaps_their_purpose_codes(): void
    {
        $company = $this->makeOldCompany();

        $exitCode = Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);
        $this->assertSame(0, $exitCode);

        $expectations = [
            '1215' => ['name' => 'Cheques In Hand', 'purposeCode' => 'CHEQUES_IN_HAND'],
            '2215' => ['name' => 'Cheques Issued Not Cleared', 'purposeCode' => 'CHEQUES_ISSUED_NOT_CLEARED'],
            '5127' => ['name' => 'Cash Over/Short', 'purposeCode' => 'CASH_OVER_SHORT'],
            '5222' => ['name' => 'Bank Charges', 'purposeCode' => 'BANK_CHARGES_EXPENSE'],
        ];

        foreach ($expectations as $code => $expect) {
            $account = Account::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('code', $code)
                ->first();

            $this->assertNotNull($account, "Leaf code {$code} must be recreated by the backfill.");
            $this->assertSame($expect['name'], $account->name);

            $mapping = DB::table('system_accounts')
                ->where('company_id', $company->id)
                ->where('purpose_code', $expect['purposeCode'])
                ->whereNull('service_type')
                ->first();

            $this->assertNotNull($mapping, "{$expect['purposeCode']} must be re-mapped after the backfill.");
            $this->assertSame((int) $account->id, (int) $mapping->account_id);
        }
    }

    public function test_backfill_is_idempotent(): void
    {
        $company = $this->makeOldCompany();

        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);

        $countAfterFirstRun = Account::withoutGlobalScopes()->where('company_id', $company->id)->count();

        $exitCode = Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);
        $this->assertSame(0, $exitCode);

        $this->assertSame(
            $countAfterFirstRun,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->count(),
            'A second run must create nothing new — every leaf already exists at its decided code.'
        );
    }

    public function test_dry_run_creates_nothing(): void
    {
        $company = $this->makeOldCompany();

        $countBefore = Account::withoutGlobalScopes()->where('company_id', $company->id)->count();

        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id, '--dry-run' => true]);

        $this->assertSame(
            $countBefore,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->count(),
            '--dry-run must never write any account row.'
        );
        $this->assertFalse(
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1215')->exists()
        );
    }

    /**
     * All four W5.L leaves are CORE (their parent groups — 'Assets', 'Liabilities', 'Direct
     * Expenses (Cost of Sales)', 'Indirect Expenses (Operating Expenses)' — are all level-1/2
     * roots every CoaSeeder chart, old or new, is expected to already have). Proving 'Bank
     * Charges' specifically is CORE (not optional, unlike KNET/uPayment Charges): when its parent
     * group is entirely absent (a maximally-broken legacy chart), the WHOLE company's backfill
     * must roll back and fail — including the three OTHER leaves already created earlier in the
     * same per-company transaction — never a partial, silently-incomplete backfill.
     */
    public function test_bank_charges_failure_rolls_back_the_whole_company_when_its_parent_group_is_absent(): void
    {
        $company = $this->makeOldCompany();

        $indirectExpenses = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Indirect Expenses (Operating Expenses)')
            ->firstOrFail();

        $subtreeIds = Account::withoutGlobalScopes()->where('parent_id', $indirectExpenses->id)->pluck('id')->push($indirectExpenses->id);

        // system_accounts.account_id has a real FK to accounts — clear any mapping into this
        // subtree first (SALARY_EXPENSE/AIRLINE_CLAWBACK_EXPENSE etc. live elsewhere; this group
        // itself has no purpose-code mapping of its own, but its descendants might on some chart
        // shape) before the accounts themselves can be deleted.
        DB::table('system_accounts')->whereIn('account_id', $subtreeIds)->delete();

        Account::withoutGlobalScopes()->where('parent_id', $indirectExpenses->id)->delete();
        Account::withoutGlobalScopes()->where('id', $indirectExpenses->id)->delete();

        $countBefore = Account::withoutGlobalScopes()->where('company_id', $company->id)->count();

        $exitCode = Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);

        $this->assertSame(
            1,
            $exitCode,
            'A CORE leaf whose parent group is entirely absent must fail the whole company (exit FAILURE).'
        );

        foreach (['1215', '2215', '5127'] as $coreCode) {
            $this->assertFalse(
                Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', $coreCode)->exists(),
                "CORE leaf {$coreCode}, created earlier in the SAME per-company transaction, must be "
                    .'rolled back too when a LATER core leaf in that same loop fails — never a partial backfill.'
            );
        }
        $this->assertSame(
            $countBefore,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->count(),
            'Nothing net-new may survive a failed company.'
        );
    }
}
