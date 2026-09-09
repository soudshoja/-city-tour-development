<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Exceptions\Accounting\NonLeafAccountException;
use App\Exceptions\Accounting\UnmappedPurposeException;
use App\Models\Account;
use App\Models\Company;
use App\Services\Accounting\AccountResolver;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 E5 — CT-F37: per-service SERVICE_PAYABLE/SERVICE_COST purposes are unmappable on this
 * chart for a service type with no dedicated leaf, because every candidate group account (the
 * per-service "Suppliers (X)"/"X Cost" family) HAS CHILDREN and so fails
 * {@see AccountResolver::isLeaf()}. CT-A2 had to hand-create 13 orphan control leaves plus a
 * Creditors Control just to make the replay run.
 *
 * OWNER-SPECIFIED FIX: per-service purposes fall back — in a documented, config-driven order
 * (`config('accounting.purpose_codes.purpose_fallbacks')`) — to the company's single global
 * control leaf (PAYABLE_CONTROL / COST_OF_SALES_CONTROL) when no per-service leaf is mapped,
 * instead of minting new leaves. A per-service mapping that DOES exist still wins outright.
 *
 * @see \App\Services\Accounting\AccountResolver::resolveViaFallback()
 */
class E5PurposeFallbackTest extends AccountingTestCase
{
    private function makeCompany(): Company
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);

        return $company;
    }

    private function mappedAccountId(int $companyId, string $purposeCode, ?string $serviceType): ?int
    {
        return DB::table('system_accounts')
            ->where('company_id', $companyId)
            ->where('purpose_code', $purposeCode)
            ->where(function ($query) use ($serviceType) {
                $serviceType === null
                    ? $query->whereNull('service_type')
                    : $query->where('service_type', $serviceType);
            })
            ->value('account_id');
    }

    private function deleteMapping(int $companyId, string $purposeCode, ?string $serviceType): void
    {
        DB::table('system_accounts')
            ->where('company_id', $companyId)
            ->where('purpose_code', $purposeCode)
            ->when(
                $serviceType === null,
                fn ($q) => $q->whereNull('service_type'),
                fn ($q) => $q->where('service_type', $serviceType)
            )
            ->delete();
    }

    /**
     * Case 1: a service type WITH a mapped SERVICE_PAYABLE row resolves to that per-service leaf
     * directly — no fallback consulted, no warning logged.
     */
    public function test_mapped_service_payable_resolves_directly_with_no_fallback_or_log(): void
    {
        $company = $this->makeCompany();

        $expectedAccountId = $this->mappedAccountId($company->id, 'SERVICE_PAYABLE', 'hotel');
        $this->assertNotNull($expectedAccountId, 'Precondition: SystemAccountsSeeder must map SERVICE_PAYABLE/hotel.');

        Log::spy();

        $account = app(AccountResolver::class)->resolve('SERVICE_PAYABLE', $company->id, 'hotel');

        $this->assertSame($expectedAccountId, $account->id);
        Log::shouldNotHaveReceived('warning');
    }

    /**
     * Case 2: a service type with NO mapped SERVICE_PAYABLE row resolves to the company's
     * PAYABLE_CONTROL leaf, and a warning names the service type.
     */
    public function test_unmapped_service_payable_falls_back_to_payable_control_and_logs(): void
    {
        $company = $this->makeCompany();
        $this->deleteMapping($company->id, 'SERVICE_PAYABLE', 'hotel');

        $payableControlId = $this->mappedAccountId($company->id, 'PAYABLE_CONTROL', null);
        $this->assertNotNull($payableControlId, 'Precondition: PAYABLE_CONTROL must be mapped for the fallback to land anywhere.');

        Log::spy();

        $account = app(AccountResolver::class)->resolve('SERVICE_PAYABLE', $company->id, 'hotel');

        $this->assertSame($payableControlId, $account->id);

        Log::shouldHaveReceived('warning')->once()->withArgs(
            function (string $message, array $context) use ($company, $payableControlId) {
                return $message === 'accounting.purpose_fallback'
                    && $context['company_id'] === $company->id
                    && $context['purpose_code'] === 'SERVICE_PAYABLE'
                    && $context['service_type'] === 'hotel'
                    && $context['fallback_purpose_code'] === 'PAYABLE_CONTROL'
                    && $context['resolved_account_id'] === $payableControlId;
            }
        );
    }

    /**
     * Case 3 (first half): a service type WITH a mapped SERVICE_COST row resolves to that
     * per-service leaf directly — no fallback, no warning.
     */
    public function test_mapped_service_cost_resolves_directly_with_no_fallback_or_log(): void
    {
        $company = $this->makeCompany();

        $expectedAccountId = $this->mappedAccountId($company->id, 'SERVICE_COST', 'hotel');
        $this->assertNotNull($expectedAccountId, 'Precondition: SystemAccountsSeeder must map SERVICE_COST/hotel.');

        Log::spy();

        $account = app(AccountResolver::class)->resolve('SERVICE_COST', $company->id, 'hotel');

        $this->assertSame($expectedAccountId, $account->id);
        Log::shouldNotHaveReceived('warning');
    }

    /**
     * Case 3 (second half): a service type with NO mapped SERVICE_COST row resolves to
     * COST_OF_SALES_CONTROL, and a warning names the service type.
     *
     * CT-A3 E5 follow-up (2026-09-09): COST_OF_SALES_CONTROL is now SEEDED — CoaSeeder mints the
     * single `5129 Cost of Sales Control` leaf and SystemAccountsSeeder maps it, so a freshly
     * seeded company has a working fallback out of the box. That is the whole point of the owner
     * ruling ("resolve to the company's single PAYABLE_CONTROL / COGS leaf"): ONE control account,
     * not the 13 per-service orphan leaves CT-A2 had to hand-create.
     */
    public function test_unmapped_service_cost_falls_back_to_cost_of_sales_control_and_logs(): void
    {
        $company = $this->makeCompany();
        $this->deleteMapping($company->id, 'SERVICE_COST', 'hotel');

        $costControlLeafId = $this->mappedAccountId($company->id, 'COST_OF_SALES_CONTROL', null);

        $this->assertNotNull(
            $costControlLeafId,
            'A freshly seeded company must already have COST_OF_SALES_CONTROL mapped — the '
            .'fallback is useless without a leaf to land on.'
        );
        $this->assertSame(
            '5129',
            (string) Account::query()->withoutGlobalScopes()->find($costControlLeafId)->code,
            'It maps to the single 5129 Cost of Sales Control leaf, not a per-service one.'
        );

        $costControlLeaf = Account::query()->withoutGlobalScopes()->find($costControlLeafId);

        Log::spy();

        $account = app(AccountResolver::class)->resolve('SERVICE_COST', $company->id, 'hotel');

        $this->assertSame($costControlLeaf->id, $account->id);

        Log::shouldHaveReceived('warning')->once()->withArgs(
            function (string $message, array $context) use ($company, $costControlLeaf) {
                return $message === 'accounting.purpose_fallback'
                    && $context['company_id'] === $company->id
                    && $context['purpose_code'] === 'SERVICE_COST'
                    && $context['service_type'] === 'hotel'
                    && $context['fallback_purpose_code'] === 'COST_OF_SALES_CONTROL'
                    && $context['resolved_account_id'] === $costControlLeaf->id;
            }
        );
    }

    /**
     * Case 4: when neither the per-service row nor its fallback is mapped, UnmappedPurposeException
     * is thrown and its message names BOTH purpose codes — never a silent skip, never a fabricated
     * account. Both mappings are deleted here to construct that state deliberately, since
     * COST_OF_SALES_CONTROL is now seeded (see the case above).
     */
    public function test_neither_service_cost_nor_its_fallback_mapped_throws_naming_both_codes(): void
    {
        $company = $this->makeCompany();
        $this->deleteMapping($company->id, 'SERVICE_COST', 'hotel');
        $this->deleteMapping($company->id, 'COST_OF_SALES_CONTROL', null);

        $this->assertNull(
            $this->mappedAccountId($company->id, 'COST_OF_SALES_CONTROL', null),
            'Precondition: the fallback must be unmapped for this case to mean anything.'
        );

        try {
            app(AccountResolver::class)->resolve('SERVICE_COST', $company->id, 'hotel');
            $this->fail('Expected UnmappedPurposeException.');
        } catch (UnmappedPurposeException $exception) {
            $this->assertStringContainsString('SERVICE_COST', $exception->getMessage());
            $this->assertStringContainsString('COST_OF_SALES_CONTROL', $exception->getMessage());
        }
    }

    /**
     * Case 5: the fallback account still gets the leaf/tenant/disabled checks a direct hit gets —
     * pointing PAYABLE_CONTROL at a GROUP account (the 'Accounts Payable' parent of 'Creditors',
     * which also parents the twelve 'Suppliers (X)' leaves, so it definitely has children) must
     * still throw NonLeafAccountException, exactly as a direct resolve('PAYABLE_CONTROL', ...)
     * would.
     */
    public function test_fallback_account_still_enforces_the_leaf_guard(): void
    {
        $company = $this->makeCompany();
        $this->deleteMapping($company->id, 'SERVICE_PAYABLE', 'hotel');

        $creditors = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Creditors')
            ->firstOrFail();

        $accountsPayableGroupId = $creditors->parent_id;
        $this->assertNotNull($accountsPayableGroupId, 'Precondition: Creditors must sit under a parent group.');

        DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'PAYABLE_CONTROL')
            ->update(['account_id' => $accountsPayableGroupId]);

        $this->expectException(NonLeafAccountException::class);

        app(AccountResolver::class)->resolve('SERVICE_PAYABLE', $company->id, 'hotel');
    }

    /**
     * Case 6: the whole point of this fix — a fallback resolution must never mint a new account.
     * The 14 orphan leaves CT-A2 had to hand-create are exactly what this assertion forbids from
     * happening automatically.
     */
    public function test_fallback_resolution_creates_no_new_accounts(): void
    {
        $company = $this->makeCompany();
        $this->deleteMapping($company->id, 'SERVICE_PAYABLE', 'hotel');

        $countBefore = Account::withoutGlobalScopes()->count();

        app(AccountResolver::class)->resolve('SERVICE_PAYABLE', $company->id, 'hotel');

        $countAfter = Account::withoutGlobalScopes()->count();

        $this->assertSame(
            $countBefore,
            $countAfter,
            'Resolving via fallback must never mint a new account row.'
        );
    }
}
