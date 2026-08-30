<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Company;
use App\Models\ReconciliationRun;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G; reconciliation-design.md §9): "Scheduled command
 * accounting:reconcile --auto ... running daily ... it never posts money." Previously untested —
 * this suite is this command's own coverage, exercising the CLI wiring itself
 * ({@see \App\Services\Accounting\ReconciliationAutoMatchServiceTest} already covers the detector
 * logic {@see \App\Services\Accounting\ReconciliationAutoMatchService} does the actual work).
 */
class AccountingReconcileAutoCommandTest extends AccountingTestCase
{
    private function makeCompany(): Company
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);

        return $company;
    }

    public function test_requires_the_auto_flag(): void
    {
        $exitCode = Artisan::call('accounting:reconcile');

        $this->assertSame(1, $exitCode, 'v0 is auto-proposal-only; omitting --auto must fail, not silently no-op.');
    }

    public function test_auto_with_a_company_option_creates_a_nightly_triggered_run(): void
    {
        $company = $this->makeCompany();

        $exitCode = Artisan::call('accounting:reconcile', ['--auto' => true, '--company' => $company->id]);

        $this->assertSame(0, $exitCode);
        $run = ReconciliationRun::forCompany($company->id)->where('trigger', ReconciliationRun::TRIGGER_NIGHTLY)->first();
        $this->assertNotNull($run, 'The command must create a ReconciliationRun row with trigger=nightly (distinct from a Run-now/manual trigger).');
        $this->assertSame(ReconciliationRun::STATUS_COMPLETED, $run->status);
    }

    public function test_auto_with_an_unknown_company_option_still_exits_successfully_with_zero_companies_run(): void
    {
        $exitCode = Artisan::call('accounting:reconcile', ['--auto' => true, '--company' => 999999999]);

        $this->assertSame(0, $exitCode, 'An unknown --company id must run zero companies, not fail the whole command.');
        $this->assertSame(0, ReconciliationRun::query()->count());
    }
}
