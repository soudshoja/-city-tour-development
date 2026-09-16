<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA7;

use App\Models\Account;
use App\Models\Company;
use App\Models\SystemAccount;
use App\Services\Accounting\AccountResolver;
use Database\Seeders\AccountTypeSeeder;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * CT-A7 ROUND 2, finding **F3** — CT-D2b-1 is only fixed for charts that do not exist yet.
 *
 * CT-A7-5 changed `CoaSeeder`, which runs at PROVISIONING ONLY. `EnsureSystemLeaves` — the command
 * `accounting:coa-linkage --apply` runs to backfill missing leaves on a chart that already exists —
 * was not changed, and its leaf list holds only `Knet` (1311) and `uPayment` (1312). So on every
 * EXISTING company whose `1300 Payment Gateway` pool is bare, the original CT-D2b-1 sequence still
 * plays out unchanged: the seeder parks all five `GATEWAY_CLEARING_*` purposes on the still-leaf
 * pool, the backfill mints exactly two children under it, the pool becomes a GROUP, and
 * `verifyPurposes()` exits 1 over MyFatoorah / Hesabe / Tap without repairing anything.
 *
 * City Travelers itself is not affected (its pool already carries per-gateway named children), but
 * no other tenant is covered, and travelerp and Akeed are both next in line for this engine.
 *
 * `EnsureSystemLeaves` now carries all five leaves at the COA-DESIGN-PROPOSAL C1 codes. The two
 * halves agree by construction: the codes and the leaf names are the same in both places, so the
 * backfill FINDS what the seeder created and CREATES what an older seeder did not.
 *
 * ── The fixture is an EXISTING chart, not a fresh one ──────────────────────────────────────────
 * A chart is aged into the pre-C1 shape deliberately rather than mocked: seed it, delete the five
 * gateway leaves, then re-run `SystemAccountsSeeder` so the purposes land back on the bare pool
 * exactly the way a company seeded before C1 carries them today. That is the state
 * {@see BareSeededChartGatewayClearingCtD2b1Test} cannot reach, because C1 means a freshly seeded
 * chart never has it.
 */
class ExistingBarePoolChartF3Test extends AccountingTestCase
{
    private const GATEWAYS = ['TAP', 'KNET', 'UPAYMENT', 'MYFATOORAH', 'HESABE'];

    private const C1_CODES = ['1310', '1311', '1312', '1313', '1314'];

    /**
     * A company whose chart predates COA-DESIGN-PROPOSAL C1: `1300 Payment Gateway` is a bare leaf
     * and all five clearing purposes are parked on it.
     */
    private function companyWithABarePool(): Company
    {
        $company = Company::factory()->create();
        (new AccountTypeSeeder)->run();
        CoaSeeder::run($company->id);
        $this->trackCompanyForInvariants((int) $company->id);

        $pool = $this->gatewayPool((int) $company->id);

        // Age the chart back to the pre-C1 shape. Hard-deleted, not soft-deleted: a company seeded
        // before C1 never had these rows at all.
        DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'like', 'GATEWAY_CLEARING_%')
            ->delete();
        DB::table('accounts')->where('parent_id', $pool->id)->delete();

        $this->assertSame(
            0,
            Account::withoutGlobalScopes()->where('parent_id', $pool->id)->count(),
            'the fixture must start from a BARE pool'
        );

        // The seeder's bare-pool branch now parks all five purposes on the pool itself — the exact
        // state CT-D2b-1 describes, and the state every pre-C1 company is in today.
        (new SystemAccountsSeeder)->run();

        $parked = SystemAccount::query()
            ->where('company_id', $company->id)
            ->where('purpose_code', 'like', 'GATEWAY_CLEARING_%')
            ->where('account_id', $pool->id)
            ->count();

        $this->assertSame(
            5,
            $parked,
            'the fixture must reproduce CT-D2b-1: all five clearing purposes parked on the bare pool'
        );

        return $company;
    }

    private function gatewayPool(int $companyId): Account
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $companyId)->where('code', '1300')->firstOrFail();
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * The repair, on a chart that already exists: `--apply` exits 0 and leaves every gateway
     * postable.
     */
    public function test_coa_linkage_apply_repairs_an_existing_bare_pool_chart(): void
    {
        $company = $this->companyWithABarePool();

        $exitCode = Artisan::call('accounting:coa-linkage', ['--company' => $company->id, '--apply' => true]);

        $this->assertSame(
            0,
            $exitCode,
            'F3: --apply must REPAIR an existing bare-pool chart, not exit 1 over the non-leaf mapping it '
            ."just created. Output:\n".Artisan::output()
        );
        $this->assertStringNotContainsString('maps to a non-leaf account', Artisan::output());
    }

    /**
     * And the consequence that matters: all five gateways resolve, each to a leaf of its own.
     */
    public function test_every_gateway_resolves_to_its_own_leaf_after_the_repair(): void
    {
        $company = $this->companyWithABarePool();

        Artisan::call('accounting:coa-linkage', ['--company' => $company->id, '--apply' => true]);

        $resolver = app(AccountResolver::class);
        $resolved = [];

        foreach (self::GATEWAYS as $gateway) {
            $account = $resolver->resolve("GATEWAY_CLEARING_{$gateway}", (int) $company->id);

            $this->assertFalse(
                $account->children()->exists(),
                "GATEWAY_CLEARING_{$gateway} resolved to a GROUP account — CT-D2b-1, unrepaired"
            );

            $resolved[$gateway] = (int) $account->id;
        }

        $this->assertSame(
            count($resolved),
            count(array_unique($resolved)),
            'every gateway must resolve to a leaf of its OWN — five purposes sharing one account is the '
            .'pooled state this finding exists to remove'
        );
    }

    /**
     * The backfill mints the five leaves at C1's own codes, so a repaired existing chart and a
     * freshly seeded one are the same chart. Two code families here would re-create CT-D2b-1 with
     * extra accounts.
     */
    public function test_the_repair_mints_the_five_c1_leaves(): void
    {
        $company = $this->companyWithABarePool();

        Artisan::call('accounting:coa-linkage', ['--company' => $company->id, '--apply' => true]);

        $pool = $this->gatewayPool((int) $company->id);

        $codes = Account::withoutGlobalScopes()
            ->where('parent_id', $pool->id)
            ->whereNull('deleted_at')
            ->pluck('code')->sort()->values()->all();

        $this->assertSame(self::C1_CODES, $codes, 'COA-DESIGN-PROPOSAL C1 codes, same as CoaSeeder now seeds');
    }

    /**
     * Idempotent: a second `--apply` mints nothing further and still exits 0. A backfill that mints
     * on every run would grow a duplicate leaf per gateway per run.
     */
    public function test_a_second_apply_mints_nothing_further(): void
    {
        $company = $this->companyWithABarePool();

        Artisan::call('accounting:coa-linkage', ['--company' => $company->id, '--apply' => true]);

        $pool = $this->gatewayPool((int) $company->id);
        $after = Account::withoutGlobalScopes()->where('parent_id', $pool->id)->whereNull('deleted_at')->count();

        $exitCode = Artisan::call('accounting:coa-linkage', ['--company' => $company->id, '--apply' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(
            $after,
            Account::withoutGlobalScopes()->where('parent_id', $pool->id)->whereNull('deleted_at')->count(),
            'the second run must mint nothing'
        );
    }
}
