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
use Tests\Support\AccountingTestCase;

/**
 * CT-A7-5 — finding **CT-D2b-1** (CT-D2B-DEV-DEPLOY §1.5), verbatim:
 *
 * > "`SystemAccountsSeeder::resolveGatewayClearing()`'s bare-pool branch maps **all five** gateways
 * >  onto the `1300 Payment Gateway` pool while it is still a LEAF, and its own docblock says it
 * >  deliberately *preserves* that mapping later … `coa-linkage --apply` then mints `Knet` and
 * >  `uPayment` under that same pool, turning it into a GROUP — and `CoaLinkage::verifyPurposes()`
 * >  now calls exactly that 'a defect this run introduced' and exits 1 **without repairing it**.
 * >  … Measured on a bare seeded chart: `--apply` exits **0** at `c70ab5d0c` and **1** at
 * >  `fafaa14268`, with three blocking rows — `purpose GATEWAY_CLEARING_{MYFATOORAH,HESABE,TAP}
 * >  maps to a non-leaf account`. Both rules are defensible; they contradict each other."
 *
 * ── Why it matters here and not on the dev site ────────────────────────────────────────────────
 * It does not bite City Travelers: that company's pool already carries per-gateway named children,
 * so `purpose-health` reads `non-leaf = 0` on all three companies. It bites a **freshly seeded
 * chart** — which is exactly Akeed's situation, and exactly what every new tenant gets.
 *
 * ── The fix: correction **C1** from the COA design proposal, not a guard ────────────────────────
 * `1300 Payment Gateway` is now seeded WITH its five per-gateway clearing leaves — `1310 Tap`,
 * `1311 Knet`, `1312 uPayment`, `1313 MyFatoorah`, `1314 Hesabe` (COA-DESIGN-PROPOSAL §C1, whose
 * codes 1311/1312 are already the ones `EnsureSystemLeaves` mints, so the backfill now FINDS them
 * instead of creating them). The seeder's name-matching branch then maps each gateway onto its own
 * leaf on the first pass, the bare-pool branch never runs, and there is no pool-to-group transition
 * left for anything to break. That removes the failure mode rather than detecting it afterwards:
 * a guard in `verifyPurposes()` would have made `--apply` exit 0 while three gateways stayed
 * unpostable, which is the opposite of what that exit code is for.
 */
class BareSeededChartGatewayClearingCtD2b1Test extends AccountingTestCase
{
    /** Exactly `config('accounting.purpose_codes.gateways')`, named here so a config edit that
     * silently drops a gateway shows up as a failure rather than as a shorter loop. */
    private const GATEWAYS = ['TAP', 'KNET', 'UPAYMENT', 'MYFATOORAH', 'HESABE'];

    /** A BARE seeded chart: CoaSeeder + SystemAccountsSeeder, nothing else has ever run. */
    private function bareSeededCompany(): Company
    {
        $company = Company::factory()->create();
        (new AccountTypeSeeder)->run();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);

        return $company;
    }

    private function gatewayPool(int $companyId): Account
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('code', '1300')
            ->firstOrFail();
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * The premise of the whole finding, asserted rather than assumed: the seeded pool must NOT be
     * the account the five clearing purposes point at. Before C1 all five pointed at `1300` itself
     * — one group account standing in for five gateways — which is the state the backfill then
     * broke.
     */
    public function test_a_bare_seeded_chart_gives_every_gateway_its_own_clearing_leaf(): void
    {
        $company = $this->bareSeededCompany();
        $pool = $this->gatewayPool($company->id);

        $mapped = [];

        foreach (self::GATEWAYS as $gateway) {
            $row = SystemAccount::query()
                ->where('company_id', $company->id)
                ->where('purpose_code', "GATEWAY_CLEARING_{$gateway}")
                ->whereNull('service_type')
                ->first();

            $this->assertNotNull($row, "GATEWAY_CLEARING_{$gateway} must be mapped on a bare seeded chart");
            $this->assertNotSame(
                (int) $pool->id,
                (int) $row->account_id,
                "GATEWAY_CLEARING_{$gateway} must map to its OWN leaf, not to the 1300 pool — mapping the "
                .'pool is what the backfill later turns into a non-leaf mapping (CT-D2b-1)'
            );

            $account = Account::withoutGlobalScopes()->findOrFail($row->account_id);

            $this->assertSame((int) $pool->id, (int) $account->parent_id, 'the leaf must be a child of the 1300 pool');
            $this->assertFalse($account->children()->exists(), 'the mapped clearing account must be a LEAF');

            $mapped[$gateway] = (int) $row->account_id;
        }

        $this->assertSame(
            count($mapped),
            count(array_unique($mapped)),
            'no two gateways may share a clearing leaf — sharing one is the pooled state C1 removes'
        );
    }

    /**
     * The measured symptom, closed: `accounting:coa-linkage --apply` on a bare seeded chart exits
     * **0**. CT-D2b-1 measured it exiting **1** with three blocking
     * `NON_LEAF_PURPOSE_MAPPING` rows for MYFATOORAH / HESABE / TAP, having repaired nothing.
     */
    public function test_coa_linkage_apply_exits_zero_on_a_bare_seeded_chart(): void
    {
        $company = $this->bareSeededCompany();

        $exitCode = Artisan::call('accounting:coa-linkage', ['--company' => $company->id, '--apply' => true]);

        $this->assertSame(
            0,
            $exitCode,
            "accounting:coa-linkage --apply must exit 0 on a freshly seeded chart. Output:\n".Artisan::output()
        );
        $this->assertStringNotContainsString(
            'maps to a non-leaf account',
            Artisan::output(),
            'no purpose may map to a non-leaf after --apply on a bare seeded chart (CT-D2b-1)'
        );
    }

    /**
     * And the consequence that actually matters: after `--apply`, every gateway is POSTABLE — the
     * real `AccountResolver` the engine uses resolves all five without throwing. This is the
     * assertion a `verifyPurposes()` guard would have left false while the exit code went green.
     */
    public function test_every_gateway_clearing_purpose_resolves_after_apply(): void
    {
        $company = $this->bareSeededCompany();

        Artisan::call('accounting:coa-linkage', ['--company' => $company->id, '--apply' => true]);

        $resolver = app(AccountResolver::class);
        $resolved = [];

        foreach (self::GATEWAYS as $gateway) {
            $account = $resolver->resolve("GATEWAY_CLEARING_{$gateway}", $company->id);

            $this->assertFalse($account->children()->exists(), "GATEWAY_CLEARING_{$gateway} resolved to a group account");

            $resolved[$gateway] = (int) $account->id;
        }

        $this->assertSame(
            count($resolved),
            count(array_unique($resolved)),
            'every gateway must still resolve to a leaf of its own after the backfill has run'
        );
    }

    /**
     * The backfill is now a no-op on this pool: `EnsureSystemLeaves`' `1311 Knet` / `1312 uPayment`
     * entries FIND the seeded leaves instead of minting new ones, so the pool's child count does
     * not change and no pool-to-group transition happens at all. Asserted because "the seeder and
     * the backfill agree on the codes" is the property C1 actually relies on — two different code
     * families here would re-create CT-D2b-1 with extra accounts.
     */
    public function test_the_leaf_backfill_mints_nothing_new_under_the_gateway_pool(): void
    {
        $company = $this->bareSeededCompany();
        $pool = $this->gatewayPool($company->id);

        $before = Account::withoutGlobalScopes()->where('parent_id', $pool->id)->pluck('code')->sort()->values()->all();

        Artisan::call('accounting:coa-linkage', ['--company' => $company->id, '--apply' => true]);

        $after = Account::withoutGlobalScopes()->where('parent_id', $pool->id)->pluck('code')->sort()->values()->all();

        $this->assertSame($before, $after, 'the backfill must find the seeded gateway leaves, not mint duplicates');
        $this->assertSame(['1310', '1311', '1312', '1313', '1314'], $after, 'COA-DESIGN-PROPOSAL C1 codes');
    }
}
