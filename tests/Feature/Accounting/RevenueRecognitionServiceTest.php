<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\DeferredRevenueScheduleReport;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\RevenueRecognitionService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.D (p2_5-brief.md §P2.5.D; doc 22 §15.6, IFRS 15). Exercises the full engine path — real
 * seeders, real PostingService — proving:
 *   (1) an `at_travel` sale defers revenue/cost instead of posting them at issue;
 *   (2) `accounting:recognize-revenue` releases them on the travel date;
 *   (3) the job is idempotent (`recognize:{task_id}`);
 *   (4) refunding/voiding an unrecognised sale via the existing PostingService::reverse()
 *       excludes it from recognition, with no special-case code needed;
 *   (5) an `at_issue` service type is completely unaffected.
 */
class RevenueRecognitionServiceTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);

        parent::tearDown();
    }

    /**
     * @return array{0: Company, 1: Branch, 2: Agent, 3: Client, 4: Supplier}
     */
    private function makeFixtures(): array
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $agentType = AgentType::firstOrCreate(['name' => 'Sales']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => User::factory()->create()->id,
            'type_id' => $agentType->id,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create();

        return [$company, $branch, $agent, $client, $supplier];
    }

    private function makeTask(Company $company, Agent $agent, Client $client, Supplier $supplier, string $type, float $total, ?Carbon $travelDate): Task
    {
        return Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => $type,
            'total' => $total,
            'travel_date' => $travelDate,
        ]);
    }

    /**
     * Posts a sale for $task via SaleDraftBuilder + PostingService directly (no controller
     * involved — see this sub-wave's own report for why the 6 existing SaleDraftInput call sites
     * were deliberately left unwired).
     */
    private function postSale(Company $company, Branch $branch, Client $client, Supplier $supplier, Agent $agent, Task $task, float $sell, float $cost, string $postingBasis): \App\Services\Accounting\PostedDocument
    {
        $lines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: (string) $task->type,
            sellAmount: $sell,
            costAmount: $cost,
            postingBasis: $postingBasis,
            clientId: $client->id,
            clientName: $client->full_name ?? 'Test Client',
            supplierId: $supplier->id,
            supplierName: $supplier->name,
            agentId: $agent->id,
            agentName: $agent->name,
            taskId: $task->id,
        ));

        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'INV',
            subType: null,
            docDate: Carbon::now(),
            narration: 'Test sale for task '.$task->id,
            lines: $lines,
            idempotencyKey: 'test:sale:'.$task->id,
            sourceId: $task->id,
        );

        return app(PostingService::class)->post($draft);
    }

    private function accountIdFor(int $companyId, string $purposeCode): int
    {
        return app(AccountResolver::class)->resolve($purposeCode, $companyId)->id;
    }

    public function test_at_travel_principal_sale_defers_revenue_and_cost(): void
    {
        [$company, $branch, $agent, $client, $supplier] = $this->makeFixtures();
        $task = $this->makeTask($company, $agent, $client, $supplier, 'tour', 150.0, Carbon::today()->addDays(10));

        // 'tour' defaults to principal + at_travel (doc 22 §15.1/§15.6) — no explicit
        // recognitionTiming needed; the config default already applies (see SaleDraftBuilder's
        // own "P2.5.D addition" docblock note).
        $this->postSale($company, $branch, $client, $supplier, $agent, $task, sell: 250.0, cost: 150.0, postingBasis: SaleDraftInput::BASIS_PRINCIPAL);

        $deferredRevenueId = $this->accountIdFor($company->id, 'DEFERRED_REVENUE');
        $prepaidCostId = $this->accountIdFor($company->id, 'PREPAID_SUPPLIER_COST');
        $serviceRevenueTourId = app(AccountResolver::class)->resolve('SERVICE_REVENUE', $company->id, 'tour')->id;
        $serviceCostTourId = app(AccountResolver::class)->resolve('SERVICE_COST', $company->id, 'tour')->id;

        $lines = DB::table('journal_entries')->where('company_id', $company->id)->where('task_id', $task->id)->get();
        $this->assertCount(4, $lines);

        $deferredLine = $lines->firstWhere('account_id', $deferredRevenueId);
        $this->assertNotNull($deferredLine, 'DEFERRED_REVENUE leaf must be credited at sale time.');
        $this->assertEqualsWithDelta(250.0, (float) $deferredLine->credit, 0.0005);

        $prepaidLine = $lines->firstWhere('account_id', $prepaidCostId);
        $this->assertNotNull($prepaidLine, 'PREPAID_SUPPLIER_COST leaf must be debited at sale time.');
        $this->assertEqualsWithDelta(150.0, (float) $prepaidLine->debit, 0.0005);

        $this->assertNull($lines->firstWhere('account_id', $serviceRevenueTourId), 'SERVICE_REVENUE/tour must NOT be posted at sale time for an at_travel service.');
        $this->assertNull($lines->firstWhere('account_id', $serviceCostTourId), 'SERVICE_COST/tour must NOT be posted at sale time for an at_travel service.');

        // Still outstanding (not yet due — travel_date is 10 days out).
        $due = app(RevenueRecognitionService::class)->findDue($company->id, Carbon::today());
        $this->assertArrayNotHasKey($task->id, $due);

        $outstanding = app(RevenueRecognitionService::class)->outstandingByTask($company->id);
        $this->assertArrayHasKey($task->id, $outstanding);
        $this->assertEqualsWithDelta(250.0, $outstanding[$task->id]['revenue_amount'], 0.0005);
        $this->assertEqualsWithDelta(150.0, $outstanding[$task->id]['cost_amount'], 0.0005);
    }

    public function test_release_job_posts_recognition_entries_on_travel_date(): void
    {
        [$company, $branch, $agent, $client, $supplier] = $this->makeFixtures();
        $task = $this->makeTask($company, $agent, $client, $supplier, 'tour', 150.0, Carbon::yesterday());
        $this->postSale($company, $branch, $client, $supplier, $agent, $task, 250.0, 150.0, SaleDraftInput::BASIS_PRINCIPAL);

        $summary = app(RevenueRecognitionService::class)->run($company->id, Carbon::today());

        $this->assertSame(1, $summary['processed']);
        $this->assertSame([$task->id], $summary['released']);
        $this->assertSame([], $summary['errors']);

        $releaseTransaction = DB::table('transactions')
            ->where('company_id', $company->id)
            ->where('idempotency_key', 'recognize:'.$task->id)
            ->first();
        $this->assertNotNull($releaseTransaction);
        $this->assertSame('posted', $releaseTransaction->posting_status);
        $this->assertSame('JV', $releaseTransaction->doc_type);

        $releaseLines = DB::table('journal_entries')->where('transaction_id', $releaseTransaction->id)->get();
        $this->assertCount(4, $releaseLines);

        $deferredRevenueId = $this->accountIdFor($company->id, 'DEFERRED_REVENUE');
        $prepaidCostId = $this->accountIdFor($company->id, 'PREPAID_SUPPLIER_COST');
        $serviceRevenueTourId = app(AccountResolver::class)->resolve('SERVICE_REVENUE', $company->id, 'tour')->id;
        $serviceCostTourId = app(AccountResolver::class)->resolve('SERVICE_COST', $company->id, 'tour')->id;

        $this->assertEqualsWithDelta(250.0, (float) $releaseLines->firstWhere('account_id', $deferredRevenueId)->debit, 0.0005, 'Release debits DEFERRED_REVENUE.');
        $this->assertEqualsWithDelta(250.0, (float) $releaseLines->firstWhere('account_id', $serviceRevenueTourId)->credit, 0.0005, 'Release credits SERVICE_REVENUE/tour.');
        $this->assertEqualsWithDelta(150.0, (float) $releaseLines->firstWhere('account_id', $serviceCostTourId)->debit, 0.0005, 'Release debits SERVICE_COST/tour.');
        $this->assertEqualsWithDelta(150.0, (float) $releaseLines->firstWhere('account_id', $prepaidCostId)->credit, 0.0005, 'Release credits PREPAID_SUPPLIER_COST.');

        $outstanding = app(RevenueRecognitionService::class)->outstandingByTask($company->id);
        $this->assertArrayNotHasKey($task->id, $outstanding, 'Nothing left outstanding once released.');
    }

    public function test_recognize_revenue_command_is_idempotent(): void
    {
        [$company, $branch, $agent, $client, $supplier] = $this->makeFixtures();
        $task = $this->makeTask($company, $agent, $client, $supplier, 'tour', 150.0, Carbon::yesterday());
        $this->postSale($company, $branch, $client, $supplier, $agent, $task, 250.0, 150.0, SaleDraftInput::BASIS_PRINCIPAL);

        $exitCode1 = Artisan::call('accounting:recognize-revenue', ['--company' => $company->id]);
        $exitCode2 = Artisan::call('accounting:recognize-revenue', ['--company' => $company->id]);

        $this->assertSame(0, $exitCode1);
        $this->assertSame(0, $exitCode2);

        $count = DB::table('transactions')
            ->where('company_id', $company->id)
            ->where('idempotency_key', 'recognize:'.$task->id)
            ->count();
        $this->assertSame(1, $count, 'Running the command twice must never double-post the release.');
    }

    public function test_refund_before_release_excludes_the_task_from_recognition(): void
    {
        [$company, $branch, $agent, $client, $supplier] = $this->makeFixtures();
        $travelDate = Carbon::today()->addDays(5);
        $task = $this->makeTask($company, $agent, $client, $supplier, 'tour', 150.0, $travelDate);
        $posted = $this->postSale($company, $branch, $client, $supplier, $agent, $task, 250.0, 150.0, SaleDraftInput::BASIS_PRINCIPAL);

        // Confirmed outstanding before the refund.
        $this->assertArrayHasKey($task->id, app(RevenueRecognitionService::class)->outstandingByTask($company->id));

        // Simulate a refund/void of the unrecognised sale — the existing reverse() call every
        // W4/W6 refund/void path already uses; no P2.5.D-specific code is involved.
        app(PostingService::class)->reverse($posted->transaction, Carbon::now(), null);

        $this->assertArrayNotHasKey($task->id, app(RevenueRecognitionService::class)->outstandingByTask($company->id), 'A reversed sale must disappear from outstanding — its deferred lines are reversed through the existing reverse().');

        // Even once the travel date arrives, recognize-revenue must find nothing to release.
        $summary = app(RevenueRecognitionService::class)->run($company->id, $travelDate);
        $this->assertSame(0, $summary['processed']);
        $this->assertSame([], $summary['released']);

        $releaseTransaction = DB::table('transactions')
            ->where('company_id', $company->id)
            ->where('idempotency_key', 'recognize:'.$task->id)
            ->first();
        $this->assertNull($releaseTransaction, 'A refunded-before-release sale must never be recognized.');
    }

    public function test_at_issue_service_type_is_unaffected_and_never_appears_in_recognition(): void
    {
        [$company, $branch, $agent, $client, $supplier] = $this->makeFixtures();
        $task = $this->makeTask($company, $agent, $client, $supplier, 'flight', 100.0, Carbon::yesterday());
        $this->postSale($company, $branch, $client, $supplier, $agent, $task, 130.0, 100.0, SaleDraftInput::BASIS_AGENT);

        $serviceRevenueFlightId = app(AccountResolver::class)->resolve('SERVICE_REVENUE', $company->id, 'flight')->id;
        $line = DB::table('journal_entries')->where('company_id', $company->id)->where('task_id', $task->id)->where('account_id', $serviceRevenueFlightId)->first();
        $this->assertNotNull($line, 'flight (at_issue default) posts SERVICE_REVENUE at sale time, unchanged by P2.5.D.');
        $this->assertEqualsWithDelta(30.0, (float) $line->credit, 0.0005);

        $this->assertArrayNotHasKey($task->id, app(RevenueRecognitionService::class)->outstandingByTask($company->id));

        $summary = app(RevenueRecognitionService::class)->run($company->id, Carbon::today());
        $this->assertSame(0, $summary['processed'], 'An at_issue sale must never surface as something recognize-revenue could release.');
    }

    public function test_deferred_revenue_schedule_report_groups_by_release_month(): void
    {
        [$company, $branch, $agent, $client, $supplier] = $this->makeFixtures();
        $travelDate = Carbon::create(2027, 6, 15);
        $task = $this->makeTask($company, $agent, $client, $supplier, 'tour', 150.0, $travelDate);
        $this->postSale($company, $branch, $client, $supplier, $agent, $task, 250.0, 150.0, SaleDraftInput::BASIS_PRINCIPAL);

        $schedule = app(DeferredRevenueScheduleReport::class)->byReleaseMonth($company->id);

        $this->assertArrayHasKey('2027-06', $schedule);
        $this->assertEqualsWithDelta(250.0, $schedule['2027-06']['revenue_total'], 0.0005);
        $this->assertEqualsWithDelta(150.0, $schedule['2027-06']['cost_total'], 0.0005);
        $this->assertSame($task->id, $schedule['2027-06']['rows'][0]['task_id']);

        // Release it — the bucket must disappear from the schedule afterward.
        app(RevenueRecognitionService::class)->run($company->id, $travelDate);
        $scheduleAfter = app(DeferredRevenueScheduleReport::class)->byReleaseMonth($company->id);
        $this->assertArrayNotHasKey('2027-06', $scheduleAfter);
    }

    public function test_deferred_revenue_schedule_endpoint_is_gated_by_role(): void
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        $this->trackCompanyForInvariants($company->id);
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        $response = $this->actingAs($admin)->getJson(route('reports.deferred-revenue-schedule'));

        $response->assertOk();
        $response->assertJsonStructure(['schedule']);
    }

    /**
     * P2.5.D fix (verify finding — minor/non-blocking): the previous submission only exercised
     * the ADMIN 200-OK path, despite describing the endpoint as "tested for role-gating". This
     * proves the OTHER half of {@see \App\Http\Controllers\ReportController::deferredRevenueSchedule()}'s
     * own `in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT])` gate: a role
     * outside that allow-list gets a real 403, not the schedule.
     */
    public function test_deferred_revenue_schedule_endpoint_rejects_a_non_permitted_role(): void
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        $this->trackCompanyForInvariants($company->id);

        // Role::BRANCH resolves its company via $user->branch->company (see getCompanyId()) --
        // NOT session('company_id') like ADMIN -- so this user clears the `module:accounting`
        // route middleware (EnsureModuleEnabled -- which fails closed with 404, never 403, for a
        // user who cannot resolve a company at all) on the SAME company the admin test used, and
        // the request actually reaches ReportController::deferredRevenueSchedule()'s own
        // `in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT])` gate, which
        // Role::BRANCH is not in.
        $branchUser = User::factory()->create(['role_id' => Role::BRANCH]);
        Branch::factory()->create(['user_id' => $branchUser->id, 'company_id' => $company->id]);

        $response = $this->actingAs($branchUser)->getJson(route('reports.deferred-revenue-schedule'));

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Unauthorized']);
    }
}
