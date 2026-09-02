<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\UnmappedPurposeException;
use App\Models\Account;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Services\Accounting\AccountResolver;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * R3 (P2-exit residual register) + purpose-mapping gap fix, `SystemAccountsSeeder::
 * mapSupplierPoolLeaf()` (2026-09-01 build).
 *
 * ROOT CAUSE this covers: {@see \App\Http\Controllers\SupplierCompanyController::
 * activateSupplierProcess()} mints a PER-SUPPLIER child account under the "Suppliers (X)"/"X Cost"
 * pool leaf the moment any supplier is activated for a service_type -- turning that pool from a
 * leaf into a GROUP. The OLD SystemAccountsSeeder::resolveServices() (a bare
 * mapByName($poolName)) could only ever map SERVICE_PAYABLE/SERVICE_COST up to the FIRST supplier
 * activation for that type; every company with at least one real supplier activated (company_id=1
 * in `akeed_verify_snapshot` included -- see mapSupplierPoolLeaf()'s own docblock for the exact
 * real-data numbers) then had SERVICE_PAYABLE/SERVICE_COST permanently unmapped, so the ENGINE'S
 * very first flight/hotel sale attempt threw UnmappedPurposeException.
 *
 * R3 SPECIFICALLY: legacy's own liability posting target for a hotel task
 * ({@see \App\Http\Controllers\TaskController::getOrCreateCurrencySpecificAccount()}) is not even
 * the per-supplier leaf itself but a PER-CURRENCY CHILD of it ("{$supplier->name} ({$currency})").
 * This suite proves the engine path now reaches the SAME shape of child account, not just the
 * bare pool.
 */
class SupplierPoolLeafR3Test extends AccountingTestCase
{
    private function seededCompany(): Company
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        return $company;
    }

    private function activateSupplier(Company $company, Supplier $supplier, string $poolName, string $costPoolName): array
    {
        SupplierCompany::create([
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        // Mirrors SupplierCompanyController::activateSupplierProcess()'s own account-creation
        // shape (name = supplier name, direct child of the pool) but picks a genuinely free code
        // company-wide rather than that controller's own naive "pool code + 1" (which is exactly
        // why real production data has duplicate codes under these pools — out of this test's
        // scope to reproduce) — AccountingTestCase's own tearDown() invariant check
        // (tests/Support/AccountingInvariants.php) refuses ANY duplicate account code for a
        // company other than the one already-known, explicitly-deferred CoaSeeder pair (2130,
        // 'Suppliers (Hotels)'/'Suppliers (Ferry)'), so a fixture-only collision here would be a
        // false invariant failure, not a real defect.
        $payablePool = Account::where('company_id', $company->id)->where('name', $poolName)->firstOrFail();
        $costPool = Account::where('company_id', $company->id)->where('name', $costPoolName)->firstOrFail();

        $payableLeaf = Account::create([
            'name' => $supplier->name,
            'level' => 4,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
            'company_id' => $company->id,
            'parent_id' => $payablePool->id,
            'root_id' => $payablePool->root_id,
            'code' => (string) $this->nextFreeCode($company->id),
        ]);

        $costLeaf = Account::create([
            'name' => $supplier->name,
            'level' => 4,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
            'company_id' => $company->id,
            'parent_id' => $costPool->id,
            'root_id' => $costPool->root_id,
            'code' => (string) $this->nextFreeCode($company->id),
        ]);

        return [$payableLeaf, $costLeaf];
    }

    /**
     * A company-wide-unique numeric code no CoaSeeder/EnsureSystemLeaves/this test's own earlier
     * fixtures could plausibly already own — deliberately in a high, out-of-range band (90000+)
     * well above every real seeded code (four digits or less), memoized per test run via a
     * monotonically increasing counter so two calls in the SAME test never collide either.
     */
    private static int $nextFreeCodeCounter = 90000;

    private function nextFreeCode(int $companyId): int
    {
        do {
            $candidate = ++self::$nextFreeCodeCounter;
        } while (Account::where('company_id', $companyId)->where('code', (string) $candidate)->exists());

        return $candidate;
    }

    public function test_service_payable_and_cost_still_resolve_once_exactly_one_supplier_is_activated(): void
    {
        $company = $this->seededCompany();

        $supplier = Supplier::factory()->create(['name' => 'DOTW', 'has_hotel' => true, 'has_flight' => false]);
        [$payableLeaf, $costLeaf] = $this->activateSupplier($company, $supplier, 'Suppliers (Hotels)', 'Hotels Cost');

        (new SystemAccountsSeeder)->run();

        $resolver = app(AccountResolver::class);

        $resolvedPayable = $resolver->resolve('SERVICE_PAYABLE', $company->id, 'hotel');
        $this->assertSame($payableLeaf->id, $resolvedPayable->id, 'SERVICE_PAYABLE/hotel must resolve onto the sole active supplier\'s own leaf, not the (now-group) pool.');

        $resolvedCost = $resolver->resolve('SERVICE_COST', $company->id, 'hotel');
        $this->assertSame($costLeaf->id, $resolvedCost->id, 'SERVICE_COST/hotel must resolve onto the sole active supplier\'s own leaf, not the (now-group) pool.');
    }

    public function test_service_payable_descends_to_the_base_currency_child_once_one_exists_r3(): void
    {
        $company = $this->seededCompany();

        $supplier = Supplier::factory()->create(['name' => 'DOTW', 'has_hotel' => true, 'has_flight' => false]);
        [$payableLeaf] = $this->activateSupplier($company, $supplier, 'Suppliers (Hotels)', 'Hotels Cost');

        // Mirrors TaskController::getOrCreateCurrencySpecificAccount()'s own naming/shape exactly.
        $kwdChild = Account::create([
            'name' => $supplier->name.' (KWD)',
            'level' => 5,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
            'company_id' => $company->id,
            'parent_id' => $payableLeaf->id,
            'root_id' => $payableLeaf->root_id,
            'code' => (string) $this->nextFreeCode($company->id),
            'currency' => 'KWD',
            'account_type' => 'liability',
        ]);

        (new SystemAccountsSeeder)->run();

        $resolved = app(AccountResolver::class)->resolve('SERVICE_PAYABLE', $company->id, 'hotel');

        $this->assertSame(
            $kwdChild->id,
            $resolved->id,
            'R3: once a base-currency (KWD) child of the supplier leaf exists (legacy\'s own per-currency posting target), SERVICE_PAYABLE/hotel must resolve onto THAT leaf, not the parent supplier account or the pool.'
        );
    }

    public function test_service_payable_stays_unmapped_when_more_than_one_active_supplier_is_ambiguous(): void
    {
        // Deliberately does NOT go through seededCompany()'s pre-activation SystemAccountsSeeder
        // run -- this reproduces the REAL company_id=1 akeed_verify_snapshot shape (2026-09-01
        // read-only audit: 19 active 'hotel' suppliers, ZERO existing system_accounts row for
        // SERVICE_PAYABLE/hotel at all) where the purpose code was NEVER mapped in the first
        // place, not a stale mapping left over from an earlier, less-ambiguous state (that second,
        // genuinely different shape -- an existing mapping surviving a LATER ambiguity -- is
        // covered separately below, and correctly throws NonLeafAccountException/
        // CrossTenantAccountException instead, per AccountResolver's own belt-and-braces leaf
        // check; this test is specifically the "never mapped, immediately ambiguous" shape, which
        // is what the P2-exit report's pre-flip checklist actually found for company_id=1).
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        CoaSeeder::run($company->id);

        $dotw = Supplier::factory()->create(['name' => 'DOTW', 'has_hotel' => true, 'has_flight' => false]);
        $this->activateSupplier($company, $dotw, 'Suppliers (Hotels)', 'Hotels Cost');

        $rateHawk = Supplier::factory()->create(['name' => 'Rate Hawk', 'has_hotel' => true, 'has_flight' => false]);
        $this->activateSupplier($company, $rateHawk, 'Suppliers (Hotels)', 'Hotels Cost');

        (new SystemAccountsSeeder)->run();

        // Two active hotel suppliers for the same company is a genuine business ambiguity this
        // seeder must never silently resolve by picking a winner -- see mapSupplierPoolLeaf()'s
        // own docblock, and the REAL company_id=1 akeed_verify_snapshot shape (19 active hotel
        // suppliers) this scenario models. A never-before-mapped purpose code in this shape stays
        // unmapped and reports the gap; it must NOT throw anything other than
        // UnmappedPurposeException, and it must not resolve to either supplier's leaf.
        $this->expectException(UnmappedPurposeException::class);
        app(AccountResolver::class)->resolve('SERVICE_PAYABLE', $company->id, 'hotel');
    }

    public function test_a_stale_pre_ambiguity_mapping_fails_closed_as_non_leaf_not_silently(): void
    {
        // The OTHER real shape: a purpose code WAS already mapped (onto the bare pool, before any
        // supplier was ever activated), and only LATER does a second supplier activation make the
        // pool ambiguous. mapSupplierPoolLeaf() correctly refuses to WRITE a new mapping here (see
        // the "preserved not broken" test above), but the pre-existing row now points at an
        // account that has since grown children -- AccountResolver::resolve()'s own leaf
        // belt-and-braces check (NOT mapSupplierPoolLeaf()'s job) is what keeps this failing
        // CLOSED instead of silently posting to a group account: NonLeafAccountException, never a
        // silent success.
        $company = $this->seededCompany();

        $dotw = Supplier::factory()->create(['name' => 'DOTW', 'has_hotel' => true, 'has_flight' => false]);
        $this->activateSupplier($company, $dotw, 'Suppliers (Hotels)', 'Hotels Cost');
        $rateHawk = Supplier::factory()->create(['name' => 'Rate Hawk', 'has_hotel' => true, 'has_flight' => false]);
        $this->activateSupplier($company, $rateHawk, 'Suppliers (Hotels)', 'Hotels Cost');
        (new SystemAccountsSeeder)->run();

        $this->expectException(\App\Exceptions\Accounting\NonLeafAccountException::class);
        app(AccountResolver::class)->resolve('SERVICE_PAYABLE', $company->id, 'hotel');
    }

    public function test_an_existing_mapping_is_preserved_not_broken_when_a_second_supplier_later_becomes_ambiguous(): void
    {
        $company = $this->seededCompany();

        $dotw = Supplier::factory()->create(['name' => 'DOTW', 'has_hotel' => true, 'has_flight' => false]);
        [$payableLeaf] = $this->activateSupplier($company, $dotw, 'Suppliers (Hotels)', 'Hotels Cost');
        (new SystemAccountsSeeder)->run();

        $resolved = app(AccountResolver::class)->resolve('SERVICE_PAYABLE', $company->id, 'hotel');
        $this->assertSame($payableLeaf->id, $resolved->id);

        // A second supplier activation makes the pool ambiguous going forward -- matches this
        // seeder's existing, established convention everywhere else (mapByChain/mapByName/
        // resolveGatewayFeeExpense's own "stays on the pool" preserve rule): skip() never clears
        // an existing system_accounts row, it only refuses to WRITE a new one. The already-mapped
        // target from before the ambiguity must survive a re-run untouched.
        $rateHawk = Supplier::factory()->create(['name' => 'Rate Hawk', 'has_hotel' => true, 'has_flight' => false]);
        $this->activateSupplier($company, $rateHawk, 'Suppliers (Hotels)', 'Hotels Cost');
        (new SystemAccountsSeeder)->run();

        $stillResolved = app(AccountResolver::class)->resolve('SERVICE_PAYABLE', $company->id, 'hotel');
        $this->assertSame($payableLeaf->id, $stillResolved->id, 'A pre-existing mapping must not be silently wiped by a later ambiguity — this seeder only ever refuses NEW writes, never un-maps.');
    }

    public function test_service_payable_with_zero_active_suppliers_still_maps_the_bare_pool(): void
    {
        // Baseline / non-regression: a service_type with no supplier ever activated must keep
        // resolving onto the bare pool leaf exactly as before this fix.
        $company = $this->seededCompany();

        $rows = DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'SERVICE_PAYABLE')
            ->where('service_type', 'hotel')
            ->first();

        $pool = Account::where('company_id', $company->id)->where('name', 'Suppliers (Hotels)')->firstOrFail();

        $this->assertSame($pool->id, $rows->account_id, 'With no supplier activated, SERVICE_PAYABLE/hotel must still map onto the bare pool leaf (unchanged baseline behaviour).');
    }
}
