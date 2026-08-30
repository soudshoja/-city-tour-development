<?php

namespace Tests\Unit\Services\Accounting;

use App\Models\Company;
use App\Models\Setting;
use App\Services\Accounting\VoucherOptions;
use Tests\Support\AccountingTestCase;

/**
 * W5.L item 5 (w5-brief.md §W5.L): the two per-company voucher options —
 * `voucher_approval_threshold` (nullable amount) and `pv_allow_overdraft` (bool, default false) —
 * resolved via the SAME `settings` table / Setting::getByKey() convention
 * SaleDraftBuilder::resolvePostingBasis() already established.
 */
class VoucherOptionsTest extends AccountingTestCase
{
    public function test_approval_threshold_defaults_to_null_with_no_setting_row(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $this->assertNull(VoucherOptions::approvalThreshold($company->id));
    }

    public function test_approval_threshold_honours_the_config_default_when_set(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        config(['accounting.vouchers.voucher_approval_threshold_default' => 25.5]);

        $this->assertSame(25.5, VoucherOptions::approvalThreshold($company->id));
    }

    public function test_approval_threshold_reads_a_per_company_setting_override(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        Setting::create([
            'company_id' => $company->id,
            'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'type' => 'string',
            'value' => '150.750',
        ]);

        $this->assertSame(150.75, VoucherOptions::approvalThreshold($company->id));
    }

    /**
     * A company-scoped Setting must never leak to a DIFFERENT company — same tenant isolation
     * every other engine-layer resolver in this codebase enforces.
     */
    public function test_approval_threshold_is_scoped_per_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $this->trackCompanyForInvariants($companyA->id);
        $this->trackCompanyForInvariants($companyB->id);

        Setting::create([
            'company_id' => $companyA->id,
            'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'type' => 'string',
            'value' => '999.000',
        ]);

        $this->assertNull(VoucherOptions::approvalThreshold($companyB->id));
    }

    public function test_pv_allow_overdraft_defaults_to_false_with_no_setting_row(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $this->assertFalse(VoucherOptions::pvAllowOverdraft($company->id));
    }

    public function test_pv_allow_overdraft_reads_a_per_company_setting_override(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        Setting::create([
            'company_id' => $company->id,
            'key' => VoucherOptions::PV_ALLOW_OVERDRAFT_KEY,
            'type' => 'boolean',
            'value' => true,
        ]);

        $this->assertTrue(VoucherOptions::pvAllowOverdraft($company->id));
    }

    public function test_pv_allow_overdraft_explicit_false_setting_stays_false(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        Setting::create([
            'company_id' => $company->id,
            'key' => VoucherOptions::PV_ALLOW_OVERDRAFT_KEY,
            'type' => 'boolean',
            'value' => false,
        ]);

        $this->assertFalse(VoucherOptions::pvAllowOverdraft($company->id));
    }
}
