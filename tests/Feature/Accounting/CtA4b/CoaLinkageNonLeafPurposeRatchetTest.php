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
 *
 * STALE-RATCHET RE-DERIVATION (CT-A8b, 2026-09-16). Both cases here were failing on
 * `feat/accounting-dev-line` @ `9c783fab5` itself (`Failed asserting that 18 is identical to 13.`
 * / `Failed asserting that null is not null.`), for TWO compounding reasons neither of which was
 * this fix's own doing:
 *
 * 1. COA correction C1 (CT-A7-5) made `CoaSeeder` seed FIVE per-gateway clearing leaves (1310
 *    Tap, 1311 Knet, 1312 uPayment, 1313 MyFatoorah, 1314 Hesabe) under `1300 Payment Gateway`
 *    at PROVISIONING time — before `SystemAccountsSeeder` ever runs. The original fixture's
 *    premise (a bare pool that a LATER step turns into a group by minting one child under it) can
 *    no longer occur for a freshly seeded company: the pool already has all five gateway-named
 *    children the moment `SystemAccountsSeeder` first looks at it, so every `GATEWAY_CLEARING_*`
 *    purpose resolves straight to its own dedicated leaf and the pool is never a leaf to begin
 *    with (`$pool->id` and the resolved account's id genuinely differ — that is the "18 vs 13").
 * 2. Reconstructing a pre-C1 chart (deleting 1310-1314 before `SystemAccountsSeeder` runs, the
 *    same technique `EnsureSystemLeavesTest::makeOldCompany()` uses one pool family over) is NOT
 *    enough on its own: CT-A7 ROUND 2 (finding F3, see `EnsureSystemLeaves::LEAVES`) also taught
 *    `accounting:coa-linkage --apply`'s own internal `runEnsureSystemLeaves()` step to backfill
 *    ALL FIVE gateway leaves (not just Knet/uPayment) and then re-run `SystemAccountsSeeder` for
 *    the whole company, UNCONDITIONALLY, before `verifyPurposes()` ever runs. Verified with a
 *    debug dump of the live run: on the reconstructed pre-C1 fixture, `--apply` itself re-minted
 *    the missing Tap/uPayment/MyFatoorah/Hesabe leaves and cleanly RE-MAPPED
 *    `GATEWAY_CLEARING_HESABE` onto the freshly minted leaf (a brand-new `system_accounts` row,
 *    `account_id` pointing at the new leaf) — `--apply` exited 0 and the pool-based reproduction
 *    never reaches `verifyPurposes()` in a broken state at all, on THIS codebase. Config
 *    (`accounting.purpose_codes.gateways`) also only ever names these same five gateways, so
 *    there is no sixth, permanently-unbackfilled gateway to fall back on either. This is the
 *    "something else changed" case, reported rather than papered over: the pool-based
 *    reproduction this test originally used is now structurally unreachable through
 *    `accounting:coa-linkage --apply`'s pipeline, independently of C1.
 *
 * Re-derivation: both tests below reconstruct the SAME defect shape ("a purpose validly mapped to
 * an account that later, independently of this run's own repair steps, gains a child and becomes
 * a non-leaf") one level down — on the DEDICATED gateway leaf itself (`SystemAccountsSeeder::
 * resolveGatewayClearing()`'s own per-child loop explicitly `skip()`s re-mapping a matched child
 * that `$child->children()->exists()`, WITHOUT touching whatever `system_accounts` row already
 * exists for it — verified from that method's source, ~line 620 of
 * `database/seeders/SystemAccountsSeeder.php`, and from `skip()`'s own body, which only appends a
 * report entry and logs a warning, never writes `system_accounts`). This exercises the exact same
 * production code path (`AccountResolver::resolve()` throwing `NonLeafAccountException` on a
 * mapped-but-now-non-leaf account, caught by `CoaLinkage::verifyPurposes()`) without depending on
 * a bare-pool state that no longer survives this codebase's own auto-repair.
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
     * The same shape the original bug report described, reconstructed on a post-C1 chart: a
     * `GATEWAY_CLEARING_HESABE` mapping that is valid at the moment it is written — pointing at
     * the DEDICATED 'Hesabe' leaf `CoaSeeder`/`SystemAccountsSeeder` give it — then, independently
     * of anything `accounting:coa-linkage --apply` itself does, that leaf gains a child of its
     * own and becomes a non-leaf. The mapping is now silently pointing at a group.
     */
    public function test_a_purpose_mapped_to_a_pool_that_gains_a_child_is_a_blocking_finding(): void
    {
        $company = $this->freshCompany();
        $hesabe = $this->account($company->id, 'Hesabe');

        $this->assertSame(
            $hesabe->id,
            app(AccountResolver::class)->resolve('GATEWAY_CLEARING_HESABE', $company->id)->id,
            'sanity: the mapping resolves to the dedicated leaf while it is still a leaf'
        );

        // The dedicated leaf gains a child — the exact shape a partial/legacy backfill or a
        // manual chart edit could produce. GATEWAY_CLEARING_HESABE's mapping is untouched; it
        // just silently stopped being valid.
        Account::factory()->create([
            'company_id' => $company->id,
            'parent_id' => $hesabe->id,
            'root_id' => $hesabe->root_id,
            'code' => '1314-1',
            'name' => 'Hesabe Sub-Leaf (synthetic, test-only)',
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
            $hesabe->id,
            (int) DB::table('system_accounts')
                ->where('company_id', $company->id)
                ->where('purpose_code', 'GATEWAY_CLEARING_HESABE')
                ->value('account_id')
        );
    }

    /**
     * The negative control every ratchet needs: a purpose that FAILS TO RESOLVE for a reason
     * OTHER than "its mapped account is a non-leaf" is still the pre-existing, non-blocking,
     * owner-ruled gap — NOT reclassified as blocking by this fix. Only an actual
     * `NonLeafAccountException` is unconditionally blocking.
     *
     * On a post-C1 chart every one of the five configured gateways
     * (`config('accounting.purpose_codes.gateways')`) always gets its own dedicated leaf — seeded
     * by `CoaSeeder` and, failing that, backfilled by `EnsureSystemLeaves::LEAVES` — and
     * `accounting:coa-linkage --apply`'s own repair re-maps every one of them unconditionally on
     * every run. There is no longer a company shape where a `GATEWAY_CLEARING_*` purpose is
     * simply "never configured" and stays that way after `--apply`. The reachable equivalent of
     * that gap today is: the dedicated leaf's own mapping is missing AND that leaf is, at the
     * moment `--apply`'s internal `SystemAccountsSeeder` re-run inspects it, a non-leaf — so
     * `resolveGatewayClearing()`'s per-child loop `skip()`s writing any mapping for it (see this
     * class's own docblock) and `AccountResolver::resolve()` falls through to
     * `resolveViaFallback()`, which throws `UnmappedPurposeException` — a plain `Throwable`, never
     * `NonLeafAccountException` — for `deliberateGapFor()` to classify as `ruling`.
     */
    public function test_a_genuinely_unconfigured_gateway_purpose_stays_non_blocking(): void
    {
        $company = $this->freshCompany();
        $tap = $this->account($company->id, 'Tap');

        // No GATEWAY_CLEARING_TAP mapping was ever written — this is the deliberate, ruling-only
        // gap this fix must leave alone.
        DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'GATEWAY_CLEARING_TAP')->delete();

        // The dedicated 'Tap' leaf itself becomes a non-leaf, which is what stops
        // resolveGatewayClearing()'s re-run from silently re-mapping GATEWAY_CLEARING_TAP straight
        // back onto it (without this, --apply's own repair would just re-create the mapping this
        // test just deleted, and the purpose would never reach "genuinely unresolved").
        Account::factory()->create([
            'company_id' => $company->id,
            'parent_id' => $tap->id,
            'root_id' => $tap->root_id,
            'code' => '1310-1',
            'name' => 'Tap Sub-Leaf (synthetic, test-only)',
        ]);

        Artisan::call('accounting:coa-linkage', ['--company' => (string) $company->id, '--apply' => true]);

        $tapFinding = DB::table('coa_linkage_findings')
            ->where('company_id', $company->id)
            ->where('code', 'UNRESOLVED_PURPOSE')
            ->get()
            ->first(fn ($row) => str_contains((string) $row->summary, 'GATEWAY_CLEARING_TAP'));

        $this->assertNotNull($tapFinding, 'GATEWAY_CLEARING_TAP should still be reported as unresolved');
        $this->assertSame('ruling', $tapFinding->severity, 'a genuinely unconfigured gateway stays non-blocking');

        // Negative control on the negative control: no purpose is mapped directly to the
        // synthetic sub-leaf, so it must never itself surface as a NON_LEAF_PURPOSE_MAPPING
        // finding — this test is only about TAP's own UNRESOLVED_PURPOSE/ruling classification.
        $this->assertSame(
            0,
            DB::table('coa_linkage_findings')
                ->where('company_id', $company->id)
                ->where('code', 'NON_LEAF_PURPOSE_MAPPING')
                ->count(),
            'no purpose is mapped to the synthetic sub-leaf, so no NON_LEAF_PURPOSE_MAPPING finding should exist in this test'
        );
    }
}
