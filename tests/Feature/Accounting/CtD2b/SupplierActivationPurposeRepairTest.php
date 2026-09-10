<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtD2b;

use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\Supplier;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\PurposeHealthService;
use App\Services\CompanyProvisioner;
use App\Services\SupplierActivationService;
use App\Support\CompanyRegistrationData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Support\AccountingTestCase;

/**
 * CT-D2b — the coordinator's resolution of the PR #14 / PR #15 conflict on
 * {@see SupplierActivationService}, proved.
 *
 * The conflict was not textual. `activate()` auto-merged with BOTH sides' call sites intact, and
 * the union that produced is self-contradicting: PR #14's `warnIfMintingUnderMappedLeaf()` emitted
 * an UNCONDITIONAL WARNING that a purpose mapping was about to start throwing
 * `NonLeafAccountException`, and PR #15's `remapPurposesAfterChartReshape()` (R3-7) repaired
 * exactly that mapping ten lines later in the same call. Every successful activation would have
 * warned about a breakage that no longer existed by the time it returned — and an operator reading
 * the log could not then tell a real, unrepaired non-leaf mapping from that noise.
 *
 * The resolution keeps both and orders them: reshape -> repair -> assert. #14's detection is kept
 * in full and becomes the POST-repair assertion, carrying its own operator guidance, at ERROR, as
 * exactly ONE signal, emitted only when the repair did not clear the mapping.
 *
 * Two cases below pin the two halves of that contract, and §"mutations" in the lane record names
 * the two mutation proofs run against them:
 *
 *   M-D2b-1  remove the `remapPurposesAfterChartReshape()` call from `activate()`
 *            -> {@see self::test_activation_under_a_purpose_bound_leaf_is_repaired_and_says_nothing()}
 *               fails on BOTH halves (the purpose no longer resolves to a leaf, and the ERROR fires).
 *   M-D2b-2  make the assertion unconditional (emit whenever this activation minted under a
 *            purpose-mapped leaf, regardless of the post-repair state)
 *            -> the same test fails on the "says nothing" half — which is precisely the noise the
 *               resolution exists to remove.
 */
class SupplierActivationPurposeRepairTest extends AccountingTestCase
{
    /** The one log event both PRs' signals share. */
    private const EVENT = 'accounting.supplier_activation.non_leaf_purpose_mapping';

    protected function setUp(): void
    {
        parent::setUp();

        config(['accounting.engine.enabled' => true]);
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);

        parent::tearDown();
    }

    /**
     * Provisioned with NO suppliers, through the real provisioner — which is what leaves
     * `Suppliers (Flights)` / `Flights Cost` as LEAVES at the moment `mapAccountingPurposes()`
     * points `SERVICE_PAYABLE/flight` and `SERVICE_COST/flight` at them. That is R3-7's setup
     * exactly, and `provision()` already refuses to hand over a company whose purposes do not all
     * resolve to a leaf, so a clean pre-condition is guaranteed rather than asserted by hand.
     */
    private function provisionCompany(array $supplierIds = []): Company
    {
        $unique = uniqid();

        $company = app(CompanyProvisioner::class)->provision(CompanyRegistrationData::fromArray([
            'company_name' => 'CtD2b Co '.$unique,
            'company_code' => 'CTD2B-'.$unique,
            'country_id' => Country::factory()->create()->id,
            'company_email' => "owner-{$unique}@example.test",
            'owner_name' => 'Test Owner',
            'owner_email' => "owner-{$unique}@example.test",
            'owner_password' => 'password12345',
            'currency' => 'KWD',
            'supplier_ids' => $supplierIds,
        ]));

        $company->forceFill(['posting_engine_enabled' => true])->save();

        return $company->fresh();
    }

    private function flightSupplier(string $name): Supplier
    {
        return Supplier::factory()->create([
            'name' => $name,
            'has_flight' => true,
            'has_hotel' => false,
            'has_visa' => false,
            'has_ferry' => false,
        ]);
    }

    private function flightsPool(Company $company): Account
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Suppliers (Flights)')
            ->firstOrFail();
    }

    /**
     * HALF ONE — the repair works, and NOTHING is said.
     *
     * This is the case PR #14's unconditional warning got wrong: the mapping really was about to
     * break, #15 really did repair it, and the only correct amount of operator noise for a
     * successful activation is none.
     */
    public function test_activation_under_a_purpose_bound_leaf_is_repaired_and_says_nothing(): void
    {
        $company = $this->provisionCompany([]);
        $this->trackCompanyForInvariants($company->id);

        $pool = $this->flightsPool($company);

        // Pre-condition, stated rather than assumed: SERVICE_PAYABLE/flight is bound directly to
        // the pool, and the pool is a LEAF. Minting a supplier child under it is what turns that
        // mapping into a non-leaf one.
        $this->assertSame(
            $pool->id,
            (int) DB::table('system_accounts')
                ->where('company_id', $company->id)
                ->where('purpose_code', 'SERVICE_PAYABLE')
                ->where('service_type', 'flight')
                ->value('account_id'),
            'Pre-condition: provisioning must bind SERVICE_PAYABLE/flight directly onto the (leaf) '.
            'Suppliers (Flights) pool. If this fails the fixture no longer reproduces R3-7.'
        );
        $this->assertSame(0, $pool->children()->count(), 'Pre-condition: the pool must still be a LEAF.');

        Log::spy();

        app(SupplierActivationService::class)->activate($this->flightSupplier('CtD2b Flight A'), $company->fresh());

        // 1. The chart really was reshaped — the pool is a GROUP now.
        $this->assertGreaterThan(
            0,
            Account::withoutGlobalScopes()->where('parent_id', $pool->id)->count(),
            'The activation did not mint a child, so nothing under test actually happened.'
        );

        // 2. The purpose resolves, and resolves to a LEAF.
        $resolvedId = app(AccountResolver::class)->resolve('SERVICE_PAYABLE', (int) $company->id, 'flight');
        $this->assertSame(
            0,
            Account::withoutGlobalScopes()->where('parent_id', $resolvedId)->count(),
            'SERVICE_PAYABLE/flight resolves to an account that HAS CHILDREN — the R3-7 defect, '.
            'un-repaired. The next flight sale for this company throws NonLeafAccountException.'
        );
        $this->assertNotSame(
            $pool->id,
            $resolvedId,
            'SERVICE_PAYABLE/flight is still bound to the pool this activation just turned into a group.'
        );

        // 3. Company-wide health is clean — not just the one purpose this fixture watches.
        $health = app(PurposeHealthService::class)->inspect((int) $company->id);
        $this->assertSame([], $health['non_leaf'], 'Non-leaf purposes after the repair: '.json_encode($health['non_leaf']));

        // 4. And the point of the whole resolution: NOTHING was said about it. Not #14's warning
        //    (removed as an unconditional pre-repair signal), not the post-repair ERROR (the repair
        //    succeeded, so there is nothing to report).
        Log::shouldNotHaveReceived('warning', [self::EVENT, \Mockery::any()]);
        Log::shouldNotHaveReceived('error', [self::EVENT, \Mockery::any()]);
    }

    /**
     * HALF TWO — the repair CANNOT run, and exactly one ERROR carries PR #14's guidance.
     *
     * The construction is the real refusal path R3-7's own docblock names as the reason the repair
     * is tolerant rather than fatal: `accounting:coa-linkage --apply` refuses, across EVERY
     * company, while any `system_accounts` row names an account id that does not exist (CT-A3 R4 /
     * CT-A3 R3 §3.4). So a dangling row planted on a DIFFERENT company is enough to make the repair
     * exit 1 having written nothing — leaving this company's mapping genuinely broken, which is the
     * one state in which an operator must be told.
     */
    public function test_when_the_repair_cannot_run_exactly_one_error_carries_the_pr14_guidance(): void
    {
        // Not tracked for the suite invariants: this fixture deliberately ends with a company whose
        // purpose mapping is BROKEN, which is the state under test.
        $company = $this->provisionCompany([]);
        $other = $this->provisionCompany([]);

        $pool = $this->flightsPool($company);

        // A dangling `system_accounts` row on the OTHER company. `guardDanglingSystemAccounts()`
        // scans every company before the first `processCompany()` call, precisely because the row
        // that gets adopted belongs to a different tenant than the one being repaired.
        //
        // `SET FOREIGN_KEY_CHECKS=0` is the fixture being faithful, not a convenience — the same
        // reasoning `CtA3\R41DanglingSweepTest::plantDangling()` records: the real dangling rows
        // predate the constraint (CT-A4 catalogued 33 of them on a chart whose highest real account
        // id was 1661), so the state the command must survive is one the constraint would refuse to
        // create today.
        $missingAccountId = ((int) DB::table('accounts')->max('id')) + 5_000;
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::table('system_accounts')->insert([
                'company_id' => $other->id,
                'purpose_code' => 'BANK_CLEARING',
                'service_type' => null,
                'account_id' => $missingAccountId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        Log::spy();

        app(SupplierActivationService::class)->activate($this->flightSupplier('CtD2b Flight B'), $company->fresh());

        // The activation still SUCCEEDED — R3-7's tolerance contract: never refuse to activate a
        // supplier because a different tenant's chart is dirty.
        $this->assertGreaterThan(
            0,
            Account::withoutGlobalScopes()->where('parent_id', $pool->id)->count(),
            'The activation was aborted. remapPurposesAfterChartReshape() must be tolerant of a '.
            'failed repair, not fatal — it runs inside the caller\'s own transaction.'
        );

        // The mapping really is still broken.
        $health = app(PurposeHealthService::class)->inspect((int) $company->id);
        $this->assertNotSame(
            [],
            $health['non_leaf'],
            'The fixture did not actually break anything: coa-linkage was expected to REFUSE while '.
            'another company carries a dangling system_accounts row, leaving SERVICE_PAYABLE/flight '.
            'bound to the now-non-leaf pool.'
        );

        // EXACTLY ONE signal, at ERROR, carrying BOTH sides' detail: #15's post-repair purpose list
        // and #14's "which group did this activation mint under, and what points at it".
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context) use ($company, $pool) {
                return $message === self::EVENT
                    && (int) $context['company_id'] === (int) $company->id
                    && $context['coa_linkage_exit'] !== 0
                    // #14's detail, verbatim keys
                    && in_array('SERVICE_PAYABLE', $context['purposes_now_pointing_at_a_non_leaf'], true)
                    && collect($context['groups_this_activation_minted_under'])
                        ->contains(fn (string $g) => str_contains($g, '#'.$pool->id))
                    // #14's operator guidance
                    && str_contains($context['message'], "accounting:coa-linkage --company={$company->id} --apply")
                    && str_contains($context['message'], 'NonLeafAccountException')
                    // #15's detail
                    && collect($context['purposes'])->contains(fn (string $p) => str_starts_with($p, 'SERVICE_PAYABLE/flight'));
            });

        // ... and NOT as a warning. PR #14's unconditional WARNING call sites are gone; if this
        // starts failing, the union has been re-introduced and every successful activation is
        // warning about a breakage that was repaired microseconds later.
        Log::shouldNotHaveReceived('warning', [self::EVENT, \Mockery::any()]);

        // The same state is reachable on demand, by the read-only operator view: it NAMES the
        // purpose, and exits non-zero because a non-leaf mapping is a BLOCKING gap.
        //
        // Asserted through `$this->artisan()` rather than `Artisan::call()` + `Artisan::output()`:
        // in this suite the facade's buffered output comes back EMPTY for every command (verified
        // against `accounting:coa-linkage` as well), so an assertion built on it would pass
        // vacuously — which is exactly the kind of oracle this lane exists to refuse.
        $this->artisan('accounting:purpose-health', ['--company' => 'all'])
            ->expectsOutputToContain('NON-LEAF  SERVICE_PAYABLE/flight')
            ->assertExitCode(1);
    }
}
