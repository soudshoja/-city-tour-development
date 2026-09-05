<?php

namespace Tests\Feature\Accounting;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Models\Refund;
use App\Models\RefundDetail;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * INDEPENDENT re-verification (round 3) of the W4.U fix wave, written by the adversarial
 * verifier, not the builder. Targets exactly the fixes claimed for the two blocking findings
 * from w4-u-verify (HIGH: disposition='apply' dead end; MEDIUM: dead settings), plus a
 * regression check on the reject()/completeProcess() 403 gates this round's diff touched
 * heavily (store()/update() grew a new validateAppliedInvoiceId() guard and a new
 * applyRefundFeeSchedule() call on every creation path).
 */
class W4UReverifyRound3Test extends AccountingTestCase
{
    use GrantsAccountingModule;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function makeCompanyWithAdmin(): array
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
        AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);

        return [$company, $branch, $admin];
    }

    private function makeAgent(Branch $branch): Agent
    {
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);

        return Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => User::factory()->create()->id,
            'type_id' => $agentType->id,
            'commission' => 0.10,
        ]);
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->trackCompanyForInvariants($company->id);
    }

    private function grantCreateRefundPermission(Company $company, User $admin): void
    {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'create refund', 'guard_name' => 'web']);
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'refund-creator-r3', 'company_id' => $company->id],
            ['guard_name' => 'web']
        );
        $admin->assignRole($role);
        $role->givePermissionTo(['create refund']);
    }

    private function taskPayload(Task $task, float $sell = 100, float $cost = 60, float $fee = 0, float $charge = 0): array
    {
        return [
            'task_id' => $task->id,
            'original_invoice_price' => $sell,
            'original_task_cost' => $cost,
            'original_task_profit' => $sell - $cost,
            'refund_fee_to_client' => $fee,
            'supplier_charge' => $charge,
            'new_task_profit' => $fee - $charge,
            'total_refund_to_client' => $sell - $charge - $fee,
        ];
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // HIGH fix re-verify: disposition='apply' in the MULTI-INVOICE BATCH path (the builder's own
    // apply-disposition test only covers the single-invoice store() path -- untested combination).
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_apply_disposition_works_through_the_multi_invoice_batch_path_too(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $this->trackCompanyForInvariants($company->id);
        $agent = $this->makeAgent($branch);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $task1 = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $task2 = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'hotel']);

        $invoice1 = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice1->id, 'task_id' => $task1->id]);
        $invoice2 = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice2->id, 'task_id' => $task2->id]);

        $targetInvoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'unpaid']);

        $this->enableEngine($company);
        $this->grantCreateRefundPermission($company, $admin);

        $this->actingAs($admin)->post(route('refunds.store'), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'disposition' => 'apply',
            'applied_invoice_id' => $targetInvoice->id,
            'tasks' => [
                $this->taskPayload($task1),
                $this->taskPayload($task2),
            ],
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $refunds = Refund::where('invoice_id', $invoice1->id)->orWhere('invoice_id', $invoice2->id)->get();
        $this->assertCount(2, $refunds, 'Batch across two carrying invoices must create exactly one refund per invoice.');
        $this->assertNotNull($refunds->first()->refund_batch_id);
        $this->assertSame(1, $refunds->pluck('refund_batch_id')->unique()->count(), 'Both refunds must share one refund_batch_id.');

        foreach ($refunds as $refund) {
            $this->assertSame('apply', $refund->disposition);
            $this->assertSame($targetInvoice->id, $refund->applied_invoice_id);

            $this->actingAs($admin)->post(route('refunds.approve', $refund->id))->assertRedirect();
            $this->actingAs($admin)->post(route('refunds.complete_process', $refund->id))->assertRedirect();

            $refund->refresh();
            $this->assertContains($refund->status, [Refund::STATUS_COMPLETED, Refund::STATUS_POSTED]);

            $dispositionDoc = Transaction::withoutGlobalScopes()
                ->where('idempotency_key', 'refund:'.$refund->id.':disposition')
                ->first();
            $this->assertNotNull($dispositionDoc, "Refund #{$refund->id} disposition=apply must post, not throw, inside a batch too.");

            $appliedOnLine = JournalEntry::withoutGlobalScopes()
                ->where('transaction_id', $dispositionDoc->id)
                ->whereNotNull('invoice_id')
                ->value('invoice_id');
            $this->assertSame($targetInvoice->id, (int) $appliedOnLine);
        }
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // HIGH fix re-verify: update() path switching an existing draft refund's disposition to
    // 'apply' after creation with a different disposition -- the guard must fire on update() too,
    // not just store().
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_update_rejects_switching_to_apply_disposition_without_a_target_invoice(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $this->trackCompanyForInvariants($company->id);
        $agent = $this->makeAgent($branch);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        $this->enableEngine($company);
        $this->grantCreateRefundPermission($company, $admin);

        $this->actingAs($admin)->post(route('refunds.store'), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'disposition' => 'credit',
            'tasks' => [$this->taskPayload($task)],
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $refund = Refund::latest()->first();
        $this->assertSame('credit', $refund->disposition);

        // Flip to 'apply' via update() with no applied_invoice_id -- must be refused, not silently
        // accepted (which would only surface the failure later at complete_process() time).
        $this->actingAs($admin)->put(route('refunds.update', $refund->id), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'disposition' => 'apply',
            'tasks' => [$this->taskPayload($task)],
        ])->assertRedirect()->assertSessionHasErrors('error');

        $refund->refresh();
        $this->assertSame('credit', $refund->disposition, 'A rejected update() must not have mutated the disposition.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Regression check on RefundPolicy gates for reject()/completeProcess() -- this fix round's
    // diff added a new validation branch to both store() and update() but must not have loosened
    // authorization on the two actions the builder's own suite still does not directly probe.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_reject_and_complete_process_are_403_for_an_unauthorized_role(): void
    {
        [$company, $branch, ] = $this->makeCompanyWithAdmin();
        $agent = $this->makeAgent($branch);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now()]);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        $refund = Refund::create([
            'refund_number' => 'REF-R3-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Credit',
            'status' => Refund::STATUS_APPROVED,
            'refund_date' => now(),
            'total_refund_amount' => 100,
            'total_refund_charge' => 0,
            'total_nett_refund' => 100,
        ]);
        RefundDetail::create([
            'refund_id' => $refund->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'original_invoice_price' => 100.000,
            'original_task_cost' => 60.000,
            'refund_fee_to_client' => 0,
            'supplier_charge' => 0,
            'total_refund_to_client' => 100.000,
        ]);

        $unauthorized = User::factory()->create(['role_id' => Role::AGENT]);

        $this->actingAs($unauthorized)
            ->post(route('refunds.reject', $refund->id))
            ->assertForbidden();

        $this->actingAs($unauthorized)
            ->post(route('refunds.complete_process', $refund->id))
            ->assertForbidden();

        $refund->refresh();
        $this->assertSame(Refund::STATUS_APPROVED, $refund->status, 'An unauthorized 403 must not have moved the refund forward.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // MEDIUM fix re-verify: fee-schedule PERCENT takes priority over a flat amount, and the
    // resulting client-net figure is what actually lands on the posted disposition -- a different
    // slice of applyRefundFeeSchedule() than the builder's own "free override" / "flat amount"
    // tests (percent-priority branch was untested by name in the builder's suite).
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_fee_schedule_percent_overrides_flat_amount_and_the_next_refund_honours_it(): void
    {
        [$company, $branch, $admin] = $this->makeCompanyWithAdmin();
        $this->trackCompanyForInvariants($company->id);
        $agent = $this->makeAgent($branch);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $task = Task::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id, 'type' => 'flight']);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now(), 'status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        $this->enableEngine($company);
        $this->grantCreateRefundPermission($company, $admin);

        // Configure BOTH a flat amount and a percent for 'flight' -- percent must win per
        // applyRefundFeeSchedule()'s own documented priority order.
        Setting::updateOrCreate(['key' => 'accounting.refund.fee_schedule.flight.amount', 'company_id' => $company->id], ['value' => 999, 'type' => 'string']);
        Setting::updateOrCreate(['key' => 'accounting.refund.fee_schedule.flight.percent', 'company_id' => $company->id], ['value' => 10, 'type' => 'string']);
        Setting::updateOrCreate(['key' => 'accounting.refund.fee_schedule.flight.override', 'company_id' => $company->id], ['value' => 'needs_approval', 'type' => 'string']);

        // Screen submits a fee of 0 -- the server-side schedule must override it to 10% of the
        // 200 sell price = 20, and adjust total_refund_to_client by the same delta.
        $this->actingAs($admin)->post(route('refunds.store'), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'disposition' => 'credit',
            'tasks' => [$this->taskPayload($task, sell: 200, cost: 120, fee: 0, charge: 0)],
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $refund = Refund::latest()->first();
        $detail = RefundDetail::where('refund_id', $refund->id)->first();

        $this->assertSame(20.0, round((float) $detail->refund_fee_to_client, 3), 'Percent (10% of 200) must win over the configured flat amount (999).');
        $this->assertSame(180.0, round((float) $detail->total_refund_to_client, 3), 'total_refund_to_client must be reduced by the resolved fee delta (200 - 20).');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // MEDIUM fix re-verify: confirm 'adm' and 'gateway_fee' genuinely cannot be smuggled into
    // persisted Setting rows even via a raw request bypassing the Blade form (an adversarial
    // client, not just "the UI doesn't offer the field").
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_adm_and_gateway_fee_bearer_kinds_can_never_be_persisted_via_direct_post(): void
    {
        [$company, , $admin] = $this->makeCompanyWithAdmin();

        $response = $this->actingAs($admin)->postJson(route('settings.accounting-settings.store'), [
            'invoice_overpay_cancel_policy' => 'credit',
            'unclaimed_writeback_months' => 12,
            'commissionable_fee_types' => [],
            'refund_send_on_post' => true,
            'agent_unearn_notice' => true,
            'bearer' => [
                'commission_clawback' => ['value' => 'company', 'split_percent' => 50],
                'adm' => ['value' => 'agent', 'split_percent' => 50],
                'gateway_fee' => ['value' => 'agent', 'split_percent' => 50],
            ],
        ]);

        $response->assertOk();

        $this->assertNull(Setting::where('company_id', $company->id)->where('key', 'bearer.adm')->first(), 'bearer.adm must never be persisted -- no ADM document type consumes it.');
        $this->assertNull(Setting::where('company_id', $company->id)->where('key', 'bearer.gateway_fee')->first(), 'bearer.gateway_fee must never be persisted -- Charge::paid_by already owns this decision.');
        $this->assertNotNull(Setting::where('company_id', $company->id)->where('key', 'bearer.commission_clawback')->first(), 'commission_clawback is the one bearer kind with a real (gated) consumer and must still persist.');
    }
}
