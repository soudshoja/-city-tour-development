<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\UnmappedPurposeException;
use App\Models\Company;
use App\Services\Accounting\AccountResolver;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Tests\Support\AccountingTestCase;

/**
 * P2-exit purpose-mapping gap fix (residual register §8 pre-flip checklist, 2026-09-01) — the
 * regression guard the gap analysis flagged as missing: this test enumerates the COMPLETE purpose
 * catalog `App\Services\Accounting\AccountResolver` can be asked to resolve
 * (config('accounting.purpose_codes')) and asserts EVERY one of them resolves for a freshly
 * migrated+seeded company (Company::factory()->create() -- on a fresh RefreshDatabase run this is
 * always company_id=1, the single-tenant lock's own id -- + CoaSeeder::run() +
 * SystemAccountsSeeder::run(), the exact "fresh company" setup shape this codebase's own test
 * suite already establishes as its seeding convention, e.g.
 * tests/Feature/Accounting/EnsureSystemLeavesTest.php, AccountServiceCreateSystemLeafTest.php).
 *
 * Two deliberate, documented exclusions from "every purpose" (both already carved out by
 * SystemAccountsSeeder's own class docblock / this file's own investigation, NOT gaps this test
 * pretends don't exist):
 *   - VAT_OUTPUT: SystemAccountsSeeder::resolveControls() unconditionally, permanently skips this
 *     one -- "Kuwait v1 has no VAT — GCC VAT is P9" (that method's own comment). No leaf exists or
 *     is meant to exist for it yet; asserting it resolves would be asserting a feature this build
 *     does not implement.
 *   - The two `purpose_codes.anchors` entries (AGENT_COMMISSION_PAYABLE_GROUP /
 *     AGENT_RECEIVABLE_GROUP): resolved via AccountResolver::resolveAnchor(), a DIFFERENT method
 *     from resolve() with its own contract (does not require a leaf) -- and config('accounting.
 *     purpose_codes')'s own docblock on the 'anchors' sub-key says these are "deliberately NOT
 *     seeded/mapped by this build ... an explicitly out-of-scope lane per this build's own brief".
 *     SystemAccountsSeeder's global-purpose loop never iterates this sub-key at all.
 *
 * Every OTHER purpose code -- all 25 remaining 'global' entries, all 5 gateways x 2
 * (GATEWAY_CLEARING_{key} and GATEWAY_FEE_EXPENSE_{key}), and all 3 per_service codes x 12
 * service_types (36) -- MUST resolve. 25 + 10 + 36 = 71 assertions.
 *
 * Before this build's SUSPENSE/SERVICE_PAYABLE/SERVICE_COST fixes (CoaSeeder's new 'Suspense'
 * leaf, SystemAccountsSeeder::mapSupplierPoolLeaf()), this exact test would have failed on
 * SUSPENSE alone on a bare fresh company (no 'Suspense' leaf ever existed in the seed COA) --
 * see SupplierPoolLeafR3Test for the companion regression guard covering the supplier-activated
 * shape mapSupplierPoolLeaf() actually exists to fix.
 */
class PurposeMappingCoverageTest extends AccountingTestCase
{
    private const EXCLUDED_GLOBAL_PURPOSE_CODES = [
        // Documented, permanent gap -- see class docblock above.
        'VAT_OUTPUT',
    ];

    public function test_every_catalogued_purpose_resolves_for_a_freshly_seeded_company(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        $resolver = app(AccountResolver::class);

        $purposeCodes = config('accounting.purpose_codes');

        $globalCodes = array_diff($purposeCodes['global'], self::EXCLUDED_GLOBAL_PURPOSE_CODES);

        $this->assertNotEmpty($globalCodes, 'Sanity: the global purpose-code catalog must not be empty.');

        foreach ($globalCodes as $purposeCode) {
            $this->assertResolvable($resolver, $purposeCode, $company->id, null);
        }

        $gateways = array_keys($purposeCodes['gateways']);
        $this->assertNotEmpty($gateways, 'Sanity: the gateway catalog must not be empty.');

        foreach ($gateways as $gatewayKey) {
            $this->assertResolvable($resolver, "GATEWAY_CLEARING_{$gatewayKey}", $company->id, null);
            $this->assertResolvable($resolver, "GATEWAY_FEE_EXPENSE_{$gatewayKey}", $company->id, null);
        }

        $perServiceCodes = $purposeCodes['per_service'];
        $serviceTypes = $purposeCodes['service_types'];
        $this->assertNotEmpty($perServiceCodes, 'Sanity: the per-service purpose-code catalog must not be empty.');
        $this->assertNotEmpty($serviceTypes, 'Sanity: the service-type catalog must not be empty.');

        foreach ($perServiceCodes as $purposeCode) {
            foreach ($serviceTypes as $serviceType) {
                $this->assertResolvable($resolver, $purposeCode, $company->id, $serviceType);
            }
        }

        // Explicitly confirms the two documented exclusions are STILL excluded by design -- if a
        // future change accidentally makes VAT_OUTPUT resolvable, that is worth knowing about (a
        // real leaf now exists), but this test's job is only to assert it is not SILENTLY broken
        // by being missing from the loop above; the anchors are asserted via resolveAnchor()'s own
        // distinct, expected-to-throw contract per config's own docblock.
        $this->expectExceptionCaughtFor($resolver, 'VAT_OUTPUT', $company->id);

        foreach ($purposeCodes['anchors'] as $anchorCode) {
            $this->expectAnchorUnmapped($resolver, $anchorCode, $company->id);
        }
    }

    private function assertResolvable(AccountResolver $resolver, string $purposeCode, int $companyId, ?string $serviceType): void
    {
        try {
            $account = $resolver->resolve($purposeCode, $companyId, $serviceType);
        } catch (UnmappedPurposeException $e) {
            $this->fail(sprintf(
                "Purpose code '%s'%s did not resolve for company_id=%d on a freshly seeded company: %s",
                $purposeCode,
                $serviceType ? "/{$serviceType}" : '',
                $companyId,
                $e->getMessage()
            ));
        }

        $this->assertNotNull($account, "resolve() returned no account for '{$purposeCode}'".($serviceType ? "/{$serviceType}" : ''));
        $this->assertSame($companyId, (int) $account->company_id, "'{$purposeCode}' resolved to an account belonging to a different company.");
    }

    private function expectExceptionCaughtFor(AccountResolver $resolver, string $purposeCode, int $companyId): void
    {
        try {
            $resolver->resolve($purposeCode, $companyId, null);
            $this->fail("Expected '{$purposeCode}' to remain unmapped (documented permanent gap — Kuwait v1 has no VAT) but it resolved.");
        } catch (UnmappedPurposeException) {
            $this->addToAssertionCount(1);
        }
    }

    private function expectAnchorUnmapped(AccountResolver $resolver, string $anchorCode, int $companyId): void
    {
        try {
            $resolver->resolveAnchor($anchorCode, $companyId);
            $this->fail("Expected anchor '{$anchorCode}' to remain unmapped (deliberately out-of-scope per config('accounting.purpose_codes.anchors')'s own docblock) but it resolved.");
        } catch (UnmappedPurposeException) {
            $this->addToAssertionCount(1);
        }
    }
}
