<?php

namespace Tests\Feature\Accounting\Concerns;

use App\Models\Company;
use App\Models\Setting;
use App\Support\Modules;

/**
 * B7a (Wave-A fixture gap): `config/modules.php` fails accounting CLOSED for any
 * company with no `module.accounting` Setting row (see Company::hasModule()), so
 * every fixture that hits a `module:accounting`-gated route must grant it explicitly
 * or the request 404s at the middleware before the test's own assertions ever run.
 * Copied verbatim (mechanism only) from
 * tests/Feature/Security/AccountingAjaxTenantIsolationTest.php's own
 * grantAccountingModule().
 */
trait GrantsAccountingModule
{
    private function grantAccountingModule(Company $company): void
    {
        Setting::updateOrCreate(
            ['company_id' => $company->id, 'key' => Modules::settingKey(Modules::ACCOUNTING)],
            ['type' => 'boolean', 'value' => true]
        );

        Company::forgetModuleCache();
    }
}
