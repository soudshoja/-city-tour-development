<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Company;
use App\Models\Transaction;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T0a (L7 guard, MP-0a-2): pins the L7 rule in both directions — a company
 * whose 1880 'Accumulated Depreciation' account already carries journal_entries lines must have
 * NONE of the seven per-class contra leaves (1881-1887) minted or mapped (refuse, report a gap);
 * a company with no such lines must have all seven minted/mapped normally. Covers BOTH guarded
 * call sites: SystemAccountsSeeder::resolveFixedAssetClasses() (the purpose-mapping guard) and
 * `accounting:ensure-system-leaves` (the leaf-creation guard, which also re-runs
 * SystemAccountsSeeder — see EnsureSystemLeaves::handle()'s own SystemAccountsSeeder call).
 *
 * "1880 has journal lines" is simulated by a direct, minimal `journal_entries` row referencing
 * that company's 1880 account_id — this test never goes through PostingSeam (it is proving a
 * READ-side guard against a pre-existing/legacy posting shape, not exercising the engine itself).
 */
class FixedAssetContraGuardTest extends AccountingTestCase
{
    private const CONTRA_CODES = ['1881', '1882', '1883', '1884', '1885', '1886', '1887'];

    private const CONTRA_PURPOSE_CODES = [
        'FA_ACCUM_DEP_CAPITAL_EQUIPMENT',
        'FA_ACCUM_DEP_ELECTRONIC_EQUIPMENT',
        'FA_ACCUM_DEP_FURNITURE_FIXTURES',
        'FA_ACCUM_DEP_OFFICE_EQUIPMENT',
        'FA_ACCUM_DEP_PLANT_MACHINERY',
        'FA_ACCUM_DEP_BUILDINGS',
        'FA_ACCUM_DEP_SOFTWARE',
    ];

    private function postMinimalLineOn1880(Company $company): Account
    {
        $accumulatedDepreciation = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('code', '1880')
            ->firstOrFail();

        // Minimal, direct insert — deliberately bypasses PostingSeam: this fixture is simulating
        // a PRE-EXISTING/legacy posting shape on 1880 (the guard's own trigger condition), not
        // exercising the engine's own write path. A real Transaction parent is created too
        // (never left NULL) so AccountingInvariants::assertNoOrphanLines() stays satisfied.
        $transaction = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => null,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'debit',
            'amount' => 100.000,
            'description' => 'Legacy depreciation transaction (guard fixture)',
            'reference_type' => 'Invoice',
            'reference_number' => 'JV-LEGACY-00001',
            'transaction_date' => now(),
        ]);

        $depreciationExpense = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('code', '5203')
            ->firstOrFail();

        // Balanced pair (AccountingInvariants::assertLedgerBalanced() requires debit == credit
        // per transaction) — Dr Depreciation Expense (5203) / Cr Accumulated Depreciation (1880),
        // the exact shape a legacy (pre-engine) depreciation posting would have used.
        DB::table('journal_entries')->insert([
            [
                'company_id' => $company->id,
                'account_id' => $depreciationExpense->id,
                'transaction_id' => $transaction->id,
                'name' => 'Legacy depreciation line (guard fixture) — expense',
                'description' => 'Legacy depreciation line (guard fixture) — expense',
                'transaction_date' => now(),
                'debit' => 100.000,
                'credit' => 0.000,
                'currency' => 'KWD',
                'exchange_rate' => 1.000000,
                'amount' => 100.000,
                'is_locked' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $company->id,
                'account_id' => $accumulatedDepreciation->id,
                'transaction_id' => $transaction->id,
                'name' => 'Legacy depreciation line (guard fixture) — contra',
                'description' => 'Legacy depreciation line (guard fixture) — contra',
                'transaction_date' => now(),
                'debit' => 0.000,
                'credit' => 100.000,
                'currency' => 'KWD',
                'exchange_rate' => 1.000000,
                'amount' => 100.000,
                'is_locked' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        return $accumulatedDepreciation;
    }

    /**
     * Deliberately keeps the 7 physical 1881-1887 leaves IN PLACE (CoaSeeder already seeded
     * them) — this test is proving the SEEDER's own guard business-rule (MP-0a-2: "remove the
     * guard -> this test fails"), not merely "the leaf is missing so mapByCode() reports a gap
     * for an unrelated reason". If the physical leaves were deleted first (as the sibling
     * leaf-creation test below does, correctly, for ITS OWN different purpose), disabling the
     * guard would NOT change this test's outcome — mapByCode() would still report a gap because
     * the leaf genuinely doesn't exist, and MP-0a-2 would silently pass even with the guard
     * removed. Keeping the leaves present makes "0 mapped" possible ONLY because the guard
     * itself intercepts — a real, guard-specific oracle.
     *
     * Deliberately does NOT call trackCompanyForInvariants(): with 1880's children present, a
     * direct posting to 1880 itself makes TrialBalanceService's leaf-only query silently drop
     * that side of the ledger (see TrialBalanceService::getAccountBalances()'s own "leaf accounts
     * only" filter) — the EXACT reporting hazard the L7 guard exists to prevent in the first
     * place, reproduced here deliberately as this fixture's own precondition, not a bug in the
     * engine's real write path (this test never goes through PostingSeam).
     */
    public function test_seeder_refuses_to_map_contra_purposes_when_1880_has_journal_lines(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $this->postMinimalLineOn1880($company);

        (new SystemAccountsSeeder())->run();

        foreach (self::CONTRA_PURPOSE_CODES as $purposeCode) {
            $this->assertSame(
                0,
                DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', $purposeCode)->count(),
                "{$purposeCode} must remain UNMAPPED — 1880 carries journal lines, the L7 guard must refuse."
            );
        }

        // FA_COST_{class} is unaffected — it targets the pre-existing 1810-1870 leaves, never
        // 1880 or its children.
        $this->assertSame(
            7,
            DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'like', 'FA_COST_%')->count(),
            'FA_COST_{class} purposes must still map normally — the guard is scoped to FA_ACCUM_DEP_{class} only.'
        );
    }

    public function test_seeder_maps_contra_purposes_normally_when_1880_has_no_journal_lines(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        $this->trackCompanyForInvariants($company->id);

        $accumulatedDepreciation = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1880')->firstOrFail();
        $this->assertSame(0, DB::table('journal_entries')->where('account_id', $accumulatedDepreciation->id)->count());

        (new SystemAccountsSeeder())->run();

        foreach (self::CONTRA_PURPOSE_CODES as $purposeCode) {
            $accountId = DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', $purposeCode)->value('account_id');

            $this->assertNotNull($accountId, "{$purposeCode} must resolve when 1880 has no journal lines.");
        }
    }

    public function test_ensure_system_leaves_command_refuses_to_mint_contra_leaves_when_1880_has_journal_lines(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();

        // Simulate an "old company" missing the 7 contra leaves (same shape as
        // EnsureSystemLeavesTest's makeOldCompany() helper), THEN give 1880 a journal line.
        $contraIds = Account::withoutGlobalScopes()->where('company_id', $company->id)->whereIn('code', self::CONTRA_CODES)->pluck('id');
        DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'like', 'FA_ACCUM_DEP_%')->delete();
        Account::withoutGlobalScopes()->whereIn('id', $contraIds)->delete();

        $this->postMinimalLineOn1880($company);
        $this->trackCompanyForInvariants($company->id);

        $exitCode = Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);

        $this->assertSame(0, $exitCode, 'A guarded (refused) optional leaf family must not fail the company — same convention as a missing optional gateway pool.');

        foreach (self::CONTRA_CODES as $code) {
            $this->assertSame(
                0,
                Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', $code)->count(),
                "Contra leaf code={$code} must NOT have been created — the L7 guard must intercept before createSystemLeaf() is even called."
            );
        }

        foreach (self::CONTRA_PURPOSE_CODES as $purposeCode) {
            $this->assertSame(
                0,
                DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', $purposeCode)->count(),
                "{$purposeCode} must remain unmapped after the command's own SystemAccountsSeeder re-run."
            );
        }

        // Every OTHER leaf/purpose in the same run is unaffected by the guard — proves the guard
        // is scoped to the 7 FA_ACCUM_DEP_* entries only, never rolling back sibling leaves in the
        // same per-company transaction.
        $this->assertNotNull(
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '4139')->first(),
            'Realised Exchange Gain (4139) must still be created — unrelated to the 1880 guard.'
        );
    }
}
