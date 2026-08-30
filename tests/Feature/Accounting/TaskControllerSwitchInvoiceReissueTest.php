<?php

namespace Tests\Feature\Accounting;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TaskStatusService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * W6.R (w6-brief.md Kind 4): "switchInvoiceTask() becomes a thin wrapper over this flow ... its
 * previously logged-only profit delta now posts." Covers the HTTP route end-to-end on the
 * engine-ON path -- the raw invoiceDetail/JournalEntry re-pointing this route used to do directly
 * (ct-void-map.md §4) must now go entirely through TaskStatusService::reissue(): a real reversal
 * of the original's sale, a real new sale posted on the SAME invoice, and the OFF path left
 * byte-for-byte unchanged.
 */
class TaskControllerSwitchInvoiceReissueTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Company::forgetModuleCache();
        config(['accounting.engine.enabled' => false]);

        parent::tearDown();
    }

    /** @return array{0: Company, 1: Agent, 2: Client, 3: Supplier, 4: User} */
    private function makeFixtures(): array
    {
        Company::forgetModuleCache();

        $country = Country::factory()->create();
        $companyOwner = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create([
            'user_id' => $companyOwner->id,
            'country_id' => $country->id,
        ]);
        CoaSeeder::run($company->id);

        session(['company_id' => $company->id]);

        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => $companyOwner->id,
        ]);
        $agentType = AgentType::firstOrCreate(['name' => 'switch-invoice-test-type']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => User::factory()->create()->id,
            'commission' => 0.15,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create();

        SpatieRole::firstOrCreate(['name' => 'switch-invoice-test-role', 'guard_name' => 'web']);
        $role = SpatieRole::where('name', 'switch-invoice-test-role')->first();
        $role->givePermissionTo('switch invoice task');
        $user = User::factory()->create(['role_id' => Role::ADMIN]);
        $user->assignRole('switch-invoice-test-role');

        return [$company, $agent, $client, $supplier, $user];
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    public function test_switch_invoice_route_reissues_through_the_engine_when_on(): void
    {
        [$company, $agent, $client, $supplier, $user] = $this->makeFixtures();
        $this->enableEngine($company);

        // originalTask: billed as a placeholder while still 'issued', then left stale at
        // 'confirmed' -- exactly the shape switchInvoiceTask()'s own precondition requires
        // (ct-void-map.md §4: "confirmed original ... already has an invoice").
        $originalTask = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'reference' => 'PNR-' . uniqid(),
            'price' => 500.0,
            'total' => 350.0,
        ]);
        $issueResult = app(TaskStatusService::class)->issue($originalTask);
        $this->assertTrue($issueResult['success'] ?? false, json_encode($issueResult));
        $originalTask->update(['status' => 'confirmed']);

        $oldInvoiceDetail = InvoiceDetail::where('task_id', $originalTask->id)->first();
        $invoice = Invoice::find($oldInvoiceDetail->invoice_id);

        $newTask = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'reference' => $originalTask->reference,
            'original_task_id' => $originalTask->id,
            'price' => 600.0,
            'total' => 400.0,
        ]);

        $oldSaleTransaction = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-detail:' . $oldInvoiceDetail->id . ':sale')
            ->first();

        $response = $this->actingAs($user)->post(route('tasks.switchInvoice', $newTask->id), []);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // The OLD sale must have been reversed (never just silently re-pointed in place).
        $reversal = Transaction::withoutGlobalScopes()
            ->where('reversal_of_transaction_id', $oldSaleTransaction->id)
            ->first();
        $this->assertNotNull($reversal, 'switchInvoiceTask() must reverse the original sale through the engine.');
        $this->assertSame('REISSUE_REVERSAL', $reversal->sub_type);

        // A brand-new invoice_detail for the NEW task, on the SAME invoice -- never a raw
        // task_id re-point of the OLD invoice_detail row.
        $newInvoiceDetail = InvoiceDetail::where('task_id', $newTask->id)->first();
        $this->assertNotNull($newInvoiceDetail);
        $this->assertSame($invoice->id, $newInvoiceDetail->invoice_id);
        $this->assertEqualsWithDelta(600.0, (float) $newInvoiceDetail->task_price, 0.001);

        // The OLD invoice_detail row itself must still point at the ORIGINAL task -- the legacy
        // in-place re-point ("previously logged-only") is what this sub-wave replaces.
        $oldInvoiceDetail->refresh();
        $this->assertSame($originalTask->id, $oldInvoiceDetail->task_id);

        $originalTask->refresh();
        $newTask->refresh();
        $this->assertSame('reissued', $originalTask->ticket_status);
        $this->assertSame('issued', $newTask->ticket_status);
    }

    public function test_switch_invoice_route_off_path_still_repoints_in_place(): void
    {
        [$company, $agent, $client, $supplier, $user] = $this->makeFixtures();
        // Engine deliberately left OFF.

        $originalTask = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'confirmed',
            'reference' => 'PNR-' . uniqid(),
            'price' => 500.0,
            'total' => 350.0,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-SWITCH-' . uniqid(),
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'sub_amount' => 500.0,
            'amount' => 500.0,
            'currency' => 'KWD',
            'status' => 'unpaid',
            'payment_type' => 'full',
            'is_client_credit' => false,
            'invoice_date' => now(),
            'due_date' => now(),
        ]);

        $oldInvoiceDetail = InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'task_id' => $originalTask->id,
            'task_price' => 500.0,
            'supplier_price' => 350.0,
            'markup_price' => 150.0,
            'paid' => false,
        ]);

        $newTask = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'reference' => $originalTask->reference,
            'original_task_id' => $originalTask->id,
            'price' => 600.0,
            'total' => 400.0,
        ]);

        $response = $this->actingAs($user)->post(route('tasks.switchInvoice', $newTask->id), []);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // OFF path: the SAME invoice_detail row is re-pointed in place -- no new row, no reversal.
        $this->assertSame(1, InvoiceDetail::where('invoice_id', $invoice->id)->count());
        $oldInvoiceDetail->refresh();
        $this->assertSame($newTask->id, $oldInvoiceDetail->task_id);
        $this->assertEqualsWithDelta(400.0, (float) $oldInvoiceDetail->supplier_price, 0.001);
    }
}
