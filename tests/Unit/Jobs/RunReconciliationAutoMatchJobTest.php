<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\RunReconciliationAutoMatchJob;
use App\Models\Company;
use App\Models\ReconciliationRun;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G): "a Run-now button (permission accounting.reconcile, queued job,
 * withoutOverlapping)." Previously untested — {@see \App\Http\Controllers\Accounting\ReconciliationController::runNow()}'s
 * HTTP test only asserts the job is DISPATCHED (Queue::fake()); this covers what the job itself
 * does when it actually runs, and its overlap-guard middleware.
 */
class RunReconciliationAutoMatchJobTest extends AccountingTestCase
{
    public function test_handle_runs_the_auto_match_service_with_a_manual_trigger(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);
        $actor = User::factory()->create();

        $job = new RunReconciliationAutoMatchJob($company->id, $actor->id);
        $job->handle(app(\App\Services\Accounting\ReconciliationAutoMatchService::class));

        $run = ReconciliationRun::forCompany($company->id)->latest('id')->first();
        $this->assertNotNull($run);
        $this->assertSame(ReconciliationRun::TRIGGER_MANUAL, $run->trigger, 'Run-now must be distinguishable from the nightly cron in the run-status panel.');
        $this->assertSame($actor->id, $run->triggered_by);
    }

    public function test_middleware_is_a_per_company_without_overlapping_lock(): void
    {
        $job = new RunReconciliationAutoMatchJob(42, null);

        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }
}
