<?php

namespace Tests\Unit\Services\Accounting;

use App\Exceptions\Accounting\AccountNotUnderGroupException;
use App\Exceptions\Accounting\CrossTenantAccountException;
use App\Exceptions\Accounting\FrozenAccountException;
use App\Exceptions\Accounting\NonLeafAccountException;
use App\Models\Account;
use App\Models\Company;
use App\Services\Accounting\AccountResolver;
use Tests\Support\AccountingTestCase;

/**
 * W5.L item 4 (w5-brief.md §W5.L): "bank leaf on a voucher is passed by account id and validated
 * to sit under the BANK group" — AccountResolver::assertUnderBankGroup().
 */
class AccountResolverBankGroupTest extends AccountingTestCase
{
    private function makeBankGroup(Company $company): Account
    {
        return Account::factory()->create([
            'company_id' => $company->id,
            'name' => 'Bank Accounts',
        ]);
    }

    public function test_accepts_a_leaf_under_the_bank_group(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $bankGroup = $this->makeBankGroup($company);
        $bankLeaf = Account::factory()->create([
            'company_id' => $company->id,
            'parent_id' => $bankGroup->id,
            'name' => 'Kuwait International Bank',
        ]);

        $resolved = app(AccountResolver::class)->assertUnderBankGroup($bankLeaf->id, $company->id);

        $this->assertSame($bankLeaf->id, $resolved->id);
    }

    public function test_accepts_a_leaf_nested_two_levels_under_the_bank_group(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $bankGroup = $this->makeBankGroup($company);
        $subGroup = Account::factory()->create([
            'company_id' => $company->id,
            'parent_id' => $bankGroup->id,
            'name' => 'KWD Accounts',
        ]);
        $bankLeaf = Account::factory()->create([
            'company_id' => $company->id,
            'parent_id' => $subGroup->id,
            'name' => 'NBK Current Account',
        ]);

        $resolved = app(AccountResolver::class)->assertUnderBankGroup($bankLeaf->id, $company->id);

        $this->assertSame($bankLeaf->id, $resolved->id);
    }

    public function test_rejects_a_leaf_not_under_the_bank_group(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $cashGroup = Account::factory()->create([
            'company_id' => $company->id,
            'name' => 'Cash In Hand',
        ]);
        $cashLeaf = Account::factory()->create([
            'company_id' => $company->id,
            'parent_id' => $cashGroup->id,
            'name' => 'Receipt Voucher Cash',
        ]);

        try {
            app(AccountResolver::class)->assertUnderBankGroup($cashLeaf->id, $company->id);
            $this->fail('Expected AccountNotUnderGroupException to be thrown.');
        } catch (AccountNotUnderGroupException $e) {
            $this->assertSame($cashLeaf->id, $e->accountId);
            $this->assertSame('Bank Accounts', $e->expectedGroupName);
        }
    }

    public function test_rejects_a_root_account_with_no_ancestors_at_all(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $rootLeaf = Account::factory()->create([
            'company_id' => $company->id,
            'parent_id' => null,
            'name' => 'Some Root Leaf',
        ]);

        $this->expectException(AccountNotUnderGroupException::class);
        app(AccountResolver::class)->assertUnderBankGroup($rootLeaf->id, $company->id);
    }

    public function test_rejects_a_nonexistent_account_id(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $this->expectException(CrossTenantAccountException::class);
        app(AccountResolver::class)->assertUnderBankGroup(999999999, $company->id);
    }

    public function test_rejects_a_cross_tenant_account(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $this->trackCompanyForInvariants($companyA->id);
        $this->trackCompanyForInvariants($companyB->id);

        $bankGroupB = $this->makeBankGroup($companyB);
        $bankLeafB = Account::factory()->create([
            'company_id' => $companyB->id,
            'parent_id' => $bankGroupB->id,
            'name' => 'Bank of B',
        ]);

        $this->expectException(CrossTenantAccountException::class);
        app(AccountResolver::class)->assertUnderBankGroup($bankLeafB->id, $companyA->id);
    }

    public function test_rejects_a_disabled_account(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $bankGroup = $this->makeBankGroup($company);
        $bankLeaf = Account::factory()->create([
            'company_id' => $company->id,
            'parent_id' => $bankGroup->id,
            'name' => 'Disabled Bank',
            'disabled' => true,
        ]);

        $this->expectException(FrozenAccountException::class);
        app(AccountResolver::class)->assertUnderBankGroup($bankLeaf->id, $company->id);
    }

    public function test_rejects_a_non_leaf_group_account_even_if_under_the_bank_group(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $bankGroup = $this->makeBankGroup($company);
        // bankGroup itself has a child (the group created above already implicitly does once we
        // add one here), so passing bankGroup's own id must be rejected as non-leaf.
        Account::factory()->create([
            'company_id' => $company->id,
            'parent_id' => $bankGroup->id,
        ]);

        $this->expectException(NonLeafAccountException::class);
        app(AccountResolver::class)->assertUnderBankGroup($bankGroup->id, $company->id);
    }
}
