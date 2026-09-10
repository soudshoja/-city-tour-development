<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA4b;

use App\Models\Account;
use App\Models\Company;
use App\Services\Accounting\AccountResolver;
use Database\Seeders\AccountTypeSeeder;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * CT-A4b — same defect family as the duplicate-code minting this lane exists for, one purpose-
 * mapping step over. Reported live on travelerp (which now carries the ported coa-linkage):
 *
 * *"`accounting:coa-linkage --apply` mints child leaves UNDER an account that other purposes
 * already map to, turning that account into a group and leaving those purposes pointing at a
 * non-leaf. Concretely: it minted 'Knet' and 'uPayment' under account #200 'Payment Gateway' for
 * GATEWAY_CLEARING_KNET / GATEWAY_CLEARING_UPAYMENT, while GATEWAY_CLEARING_HESABE / _MYFATOORAH /
 * _TAP still map to #200 itself. Post-apply state: 98 system_accounts rows, 0 dangling, but
 * NON_LEAF=3."*
 *
 * `AccountResolver::resolve()` already throws `NonLeafAccountException` for exactly this shape —
 * it always has. The defect was in `CoaLinkage::verifyPurposes()`: `GATEWAY_CLEARING_` is one of
 * `NON_BLOCKING_PURPOSE_PREFIXES` (severity RULING), a classification meant for "no leaf was ever
 * configured for a gateway this company doesn't use" — a legitimate owner ruling. It was being
 * applied indiscriminately to a DIFFERENT failure: a purpose that WAS validly mapped, whose target
 * this exact run turned into a non-leaf. `--apply` exited 0 while three gateway purposes were left
 * unpostable.
 *
 * Fix: `verifyPurposes()` now catches `NonLeafAccountException` before the generic
 * `deliberateGapFor()` branch and treats it as unconditionally BLOCKING, regardless of purpose
 * prefix — a non-leaf mapping is never a deliberate gap, it is always a defect this run either
 * introduced or inherited.
 */
class CoaLinkageNonLeafPurposeRatchetTest extends AccountingTestCase
{
    private function freshCompany(): Company
    {
        $company = Company::factory()->create();
        (new AccountTypeSeeder)->run();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        return $company;
    }

    private function account(int $companyId, string $name): Account
    {
        $account = Account::query()->withoutGlobalScopes()->where('company_id', $companyId)->where('name', $name)->first();
        $this->assertNotNull($account, "Fixture expects an account named '{$name}' on company {$companyId}.");

        return $account;
    }

    /**
     * The exact shape reported: 'Payment Gateway' already carries a GATEWAY_CLEARING_HESABE
     * mapping pointing at ITSELF (legitimate, from before it had any children), then gains a
     * child (the same effect `EnsureSystemLeaves` minting Knet/uPayment under it has). The
     * mapping is now silently pointing at a non-leaf.
     */
    public function test_a_purpose_mapped_to_a_pool_that_gains_a_child_is_a_blocking_finding(): void
    {
        $company = $this->freshCompany();
        $pool = $this->account($company->id, 'Payment Gateway');

        // freshCompany()'s SystemAccountsSeeder run already mapped ALL FIVE
        // GATEWAY_CLEARING_{gateway} purposes onto this bare pool (resolveGatewayClearing()'s
        // "the pool itself is the leaf" branch, since it has no children yet). Isolate this test
        // to exactly the one purpose under test — MYFATOORAH/UPAYMENT/TAP are the negative
        // control's job (they become genuinely-unmapped once Knet is the pool's only child, which
        // is the deliberate, non-blocking gap the second test proves this fix does NOT touch).
        DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->whereIn('purpose_code', ['GATEWAY_CLEARING_MYFATOORAH', 'GATEWAY_CLEARING_UPAYMENT', 'GATEWAY_CLEARING_TAP'])
            ->delete();

        $this->assertSame(
            $pool->id,
            app(AccountResolver::class)->resolve('GATEWAY_CLEARING_HESABE', $company->id)->id,
            'sanity: the mapping resolves fine while the pool is still a leaf'
        );

        // The pool gains a child — the exact effect ensure-system-leaves minting Knet/uPayment
        // under it has. GATEWAY_CLEARING_HESABE's mapping is untouched; it just silently stopped
        // being valid.
        Account::factory()->create([
            'company_id' => $company->id,
            'parent_id' => $pool->id,
            'root_id' => $pool->root_id,
            'code' => '1311',
            'name' => 'Knet',
        ]);

        $exit = Artisan::call('accounting:coa-linkage', ['--company' => (string) $company->id, '--apply' => true]);

        $this->assertNotSame(0, $exit, '--apply must exit non-zero when a purpose maps to a non-leaf.');

        $finding = DB::table('coa_linkage_findings')
            ->where('company_id', $company->id)
            ->where('code', 'NON_LEAF_PURPOSE_MAPPING')
            ->get();

        $this->assertCount(1, $finding, 'exactly one NON_LEAF_PURPOSE_MAPPING finding expected (GATEWAY_CLEARING_HESABE only)');
        $this->assertSame('blocking', $finding->first()->severity);
        $this->assertStringContainsString('GATEWAY_CLEARING_HESABE', $finding->first()->summary);

        // The mapping was left exactly as it was (refuse, never silently re-point onto an
        // unrelated sibling — SystemAccountsSeeder's own documented reasoning for this pool).
        $this->assertSame(
            $pool->id,
            (int) DB::table('system_accounts')
                ->where('company_id', $company->id)
                ->where('purpose_code', 'GATEWAY_CLEARING_HESABE')
                ->value('account_id')
        );
    }

    /**
     * The negative control every ratchet needs: a purpose that is GENUINELY unmapped (the pool
     * has children, none named for this gateway, and nothing was ever mapped to the pool itself)
     * is still the pre-existing, non-blocking, owner-ruled gap — NOT reclassified as blocking by
     * this fix. Only an actual NonLeafAccountException is unconditionally blocking.
     */
    public function test_a_genuinely_unconfigured_gateway_purpose_stays_non_blocking(): void
    {
        $company = $this->freshCompany();
        $pool = $this->account($company->id, 'Payment Gateway');

        Account::factory()->create([
            'company_id' => $company->id,
            'parent_id' => $pool->id,
            'root_id' => $pool->root_id,
            'code' => '1311',
            'name' => 'Knet',
        ]);

        // No GATEWAY_CLEARING_TAP mapping was ever written — this is the deliberate, ruling-only
        // gap this fix must leave alone.
        DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'GATEWAY_CLEARING_TAP')->delete();

        Artisan::call('accounting:coa-linkage', ['--company' => (string) $company->id, '--apply' => true]);

        $tapFinding = DB::table('coa_linkage_findings')
            ->where('company_id', $company->id)
            ->where('code', 'UNRESOLVED_PURPOSE')
            ->get()
            ->first(fn ($row) => str_contains((string) $row->summary, 'GATEWAY_CLEARING_TAP'));

        $this->assertNotNull($tapFinding, 'GATEWAY_CLEARING_TAP should still be reported as unresolved');
        $this->assertSame('ruling', $tapFinding->severity, 'a genuinely unconfigured gateway stays non-blocking');
    }
}
