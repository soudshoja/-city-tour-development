<?php

namespace Tests\Feature\Invoice;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\InvoiceSequence;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected User $companyUser;
    protected Company $company;
    protected Branch $branch;
    protected Agent $agent;
    protected Client $client;
    protected Supplier $supplier;
    protected Invoice $invoice;
    protected Task $task;
    protected Task $task2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);

        // Create company user
        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
        ]);

        $this->company = Company::factory()->create([
            'user_id' => $this->companyUser->id,
        ]);

        $roleCompany = Role::create(['name' => 'company', 'guard_name' => 'web', 'company_id' => $this->company->id]);
        $this->companyUser->assignRole($roleCompany);
        $roleCompany->givePermissionTo('view invoice');
        $roleCompany->givePermissionTo('create invoice');

        // Create branch
        $this->branch = Branch::factory()->create([
            'user_id' => $this->companyUser->id,
            'company_id' => $this->company->id,
        ]);

        // Create agent
        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        $agentType = AgentType::create(['name' => 'Salary']);

        $this->agent = Agent::factory()->create([
            'user_id' => $agentUser->id,
            'branch_id' => $this->branch->id,
            'type_id' => $agentType->id,
        ]);

        // Create client
        $this->client = Client::factory()->create([
            'agent_id' => $this->agent->id,
        ]);

        // Create supplier
        $this->supplier = Supplier::factory()->create();

        // Create tasks
        $this->task = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $this->supplier->id,
            'total' => 100.00,
            'status' => 'issued',
            'type' => 'flight',
        ]);

        $this->task2 = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $this->supplier->id,
            'total' => 200.00,
            'status' => 'issued',
            'type' => 'hotel',
        ]);

        InvoiceSequence::create([
            'company_id' => $this->company->id,
            'current_sequence' => 1,
        ]);

        // Create an unpaid invoice with one task
        $this->invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-UPD-001',
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'sub_amount' => 150.00,
            'amount' => 150.00,
            'currency' => 'KWD',
            'status' => 'unpaid',
            'payment_type' => null,
            'invoice_date' => '2026-03-04',
            'due_date' => '2026-03-09',
        ]);

        InvoiceDetail::factory()->create([
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'task_id' => $this->task->id,
            'task_description' => $this->task->reference,
            'task_price' => 150.00,
            'supplier_price' => 100.00,
            'markup_price' => 50.00,
            'profit' => 50.00,
            'paid' => false,
        ]);
    }

    // ─── ADD TASK TO INVOICE ─────────────────────────────────────────

    public function test_can_add_task_to_unpaid_invoice(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.add-task'), [
                'invoice_id' => $this->invoice->id,
                'task_id' => $this->task2->id,
                'task_price' => 250.00,
            ]);

        $response->assertOk()
            ->assertJson(['message' => 'Task added successfully!']);

        // Should now have 2 invoice details
        $this->assertEquals(2, InvoiceDetail::where('invoice_id', $this->invoice->id)->count());

        // New detail should have correct markup
        $this->assertDatabaseHas('invoice_details', [
            'invoice_id' => $this->invoice->id,
            'task_id' => $this->task2->id,
            'task_price' => 250.00,
            'supplier_price' => 200.00,
            'markup_price' => 50.00,
        ]);

        // Invoice total should be recalculated: 150 + 250 = 400
        $this->invoice->refresh();
        $this->assertEquals(400.00, $this->invoice->amount);
        $this->assertEquals(400.00, $this->invoice->sub_amount);
    }

    public function test_cannot_add_task_to_paid_invoice(): void
    {
        $this->invoice->update(['status' => 'paid']);

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.add-task'), [
                'invoice_id' => $this->invoice->id,
                'task_id' => $this->task2->id,
                'task_price' => 250.00,
            ]);

        $response->assertStatus(403);
    }

    public function test_cannot_add_task_to_invoice_with_payment_type_set(): void
    {
        $this->invoice->update(['payment_type' => 'full']);

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.add-task'), [
                'invoice_id' => $this->invoice->id,
                'task_id' => $this->task2->id,
                'task_price' => 250.00,
            ]);

        $response->assertStatus(403);
    }

    public function test_add_task_validates_required_fields(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.add-task'), [
                'invoice_id' => $this->invoice->id,
                // Missing task_id and task_price
            ]);

        $response->assertStatus(422);
    }

    public function test_add_task_validates_invoice_exists(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.add-task'), [
                'invoice_id' => 99999,
                'task_id' => $this->task2->id,
                'task_price' => 250.00,
            ]);

        $response->assertStatus(422);
    }

    // ─── REMOVE TASK FROM INVOICE ────────────────────────────────────

    public function test_can_remove_task_from_unpaid_invoice(): void
    {
        // First add a second task so we can remove one
        InvoiceDetail::factory()->create([
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'task_id' => $this->task2->id,
            'task_description' => $this->task2->reference,
            'task_price' => 250.00,
            'supplier_price' => 200.00,
            'markup_price' => 50.00,
            'paid' => false,
        ]);

        $this->invoice->update(['sub_amount' => 400.00, 'amount' => 400.00]);

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.remove-task'), [
                'invoice_id' => $this->invoice->id,
                'task_id' => $this->task2->id,
            ]);

        $response->assertOk()
            ->assertJson(['message' => 'Task removed successfully!']);

        // Should now have only 1 detail
        $this->assertEquals(1, InvoiceDetail::where('invoice_id', $this->invoice->id)->count());

        // Invoice total should be recalculated to only task 1
        $this->invoice->refresh();
        $this->assertEquals(150.00, $this->invoice->amount);
    }

    public function test_cannot_remove_task_from_paid_invoice(): void
    {
        $this->invoice->update(['status' => 'paid']);

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.remove-task'), [
                'invoice_id' => $this->invoice->id,
                'task_id' => $this->task->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_cannot_remove_task_from_invoice_with_payment_type(): void
    {
        $this->invoice->update(['payment_type' => 'split']);

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.remove-task'), [
                'invoice_id' => $this->invoice->id,
                'task_id' => $this->task->id,
            ]);

        $response->assertStatus(403);
    }

    // ─── EDIT PAGE ACCESS ────────────────────────────────────────────

    public function test_edit_page_loads_for_unpaid_invoice(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->get(route('invoice.edit', [
                'companyId' => $this->company->id,
                'invoiceNumber' => $this->invoice->invoice_number,
            ]));

        $response->assertStatus(200);
    }

    public function test_edit_page_redirects_for_paid_invoice(): void
    {
        $this->invoice->update(['status' => 'paid']);

        $response = $this->actingAs($this->companyUser)
            ->get(route('invoice.edit', [
                'companyId' => $this->company->id,
                'invoiceNumber' => $this->invoice->invoice_number,
            ]));

        $response->assertRedirect();
    }

    // ─── REMOVE PARTIAL ──────────────────────────────────────────────

    public function test_can_remove_invoice_partial(): void
    {
        $partial = InvoicePartial::create([
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'client_id' => $this->client->id,
            'amount' => 150.00,
            'status' => 'unpaid',
            'type' => 'full',
            'payment_gateway' => 'SomeGateway',
            'service_charge' => 5.00,
            'gateway_fee' => 3.00,
        ]);

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.removepartial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
            ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'message' => 'Invoice partial removed successfully!',
        ]);

        $this->assertSoftDeleted('invoice_partials', ['id' => $partial->id]);
    }

    public function test_remove_partial_fails_when_no_partial_exists(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.removepartial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
            ]);

        $response->assertJson(['success' => false]);
    }

    public function test_remove_partial_validates_required_fields(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.removepartial'), []);

        $response->assertStatus(422);
    }

    // ─── CHANGE PAYMENT TYPE ─────────────────────────────────────────

    public function test_change_payment_type_deletes_existing_partials(): void
    {
        $this->setupChartOfAccounts();

        InvoicePartial::create([
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'client_id' => $this->client->id,
            'amount' => 150.00,
            'invoice_charge' => 5.00,
            'status' => 'unpaid',
            'type' => 'full',
            'payment_gateway' => 'SomeGateway',
            'service_charge' => 3.00,
            'gateway_fee' => 2.00,
        ]);

        $this->invoice->update([
            'payment_type' => 'full',
            'invoice_charge' => 5.00,
            'amount' => 155.00,
        ]);

        $response = $this->actingAs($this->companyUser)
            ->post(route('invoice.update-type'), [
                'invoice_id' => $this->invoice->id,
            ]);

        $response->assertRedirect();

        // Partials should be deleted
        $this->assertEquals(0, InvoicePartial::where('invoice_id', $this->invoice->id)->count());

        // Invoice should reset
        $this->invoice->refresh();
        $this->assertNull($this->invoice->payment_type);
        $this->assertEquals(0, $this->invoice->invoice_charge);
        $this->assertEquals($this->invoice->sub_amount, $this->invoice->amount);
    }

    public function test_change_payment_type_resets_invoice_amount_to_sub_amount(): void
    {
        $this->setupChartOfAccounts();

        InvoicePartial::create([
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'client_id' => $this->client->id,
            'amount' => 150.00,
            'invoice_charge' => 10.00,
            'status' => 'unpaid',
            'type' => 'full',
            'payment_gateway' => 'Tap',
            'service_charge' => 5.00,
            'gateway_fee' => 3.00,
        ]);

        $this->invoice->update([
            'payment_type' => 'full',
            'invoice_charge' => 10.00,
            'amount' => 160.00,
        ]);

        $this->actingAs($this->companyUser)
            ->post(route('invoice.update-type'), [
                'invoice_id' => $this->invoice->id,
            ]);

        $this->invoice->refresh();
        $this->assertEquals(150.00, $this->invoice->sub_amount);
        $this->assertEquals(150.00, $this->invoice->amount);
        $this->assertEquals(0, $this->invoice->invoice_charge);
    }

    public function test_change_payment_type_deletes_credit_records(): void
    {
        $this->setupChartOfAccounts();

        $partial = InvoicePartial::create([
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'client_id' => $this->client->id,
            'amount' => 150.00,
            'status' => 'paid',
            'type' => 'full',
            'payment_gateway' => 'Credit',
            'service_charge' => 0,
            'gateway_fee' => 0,
        ]);

        Credit::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'invoice_id' => $this->invoice->id,
            'invoice_partial_id' => $partial->id,
            'type' => Credit::INVOICE,
            'amount' => -150.00,
            'gateway_fee' => 0,
        ]);

        $this->invoice->update(['payment_type' => 'full', 'status' => 'paid']);

        // Reset status to unpaid so updatePaymentType can find it
        $this->invoice->update(['status' => 'unpaid']);

        $this->actingAs($this->companyUser)
            ->post(route('invoice.update-type'), [
                'invoice_id' => $this->invoice->id,
            ]);

        // Credit record for this partial should be soft-deleted
        $this->assertSoftDeleted('credits', [
            'invoice_partial_id' => $partial->id,
        ]);
    }

    protected function setupChartOfAccounts(): void
    {
        $defaults = [
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
        ];

        $assets = Account::create($defaults + [
            'code' => '1000',
            'name' => 'Assets',
            'company_id' => $this->company->id,
            'account_type' => 'asset',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'level' => 0,
            'is_group' => 1,
        ]);

        $accountReceivable = Account::create($defaults + [
            'code' => '1100',
            'name' => 'Accounts Receivable',
            'company_id' => $this->company->id,
            'parent_id' => $assets->id,
            'root_id' => $assets->id,
            'account_type' => 'asset',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'level' => 1,
            'is_group' => 1,
        ]);

        Account::create($defaults + [
            'code' => '1110',
            'name' => 'Clients',
            'company_id' => $this->company->id,
            'parent_id' => $accountReceivable->id,
            'root_id' => $assets->id,
            'account_type' => 'asset',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'level' => 2,
            'is_group' => 0,
        ]);

        $income = Account::create($defaults + [
            'code' => '4000',
            'name' => 'Income',
            'company_id' => $this->company->id,
            'account_type' => 'income',
            'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
            'level' => 0,
            'is_group' => 1,
        ]);

        $directIncome = Account::create($defaults + [
            'code' => '4100',
            'name' => 'Direct Income',
            'company_id' => $this->company->id,
            'parent_id' => $income->id,
            'root_id' => $income->id,
            'account_type' => 'income',
            'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
            'level' => 1,
            'is_group' => 1,
        ]);

        Account::create($defaults + [
            'code' => '4110',
            'name' => 'Flight Booking Revenue',
            'company_id' => $this->company->id,
            'parent_id' => $directIncome->id,
            'root_id' => $income->id,
            'branch_id' => $this->branch->id,
            'account_type' => 'income',
            'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
            'level' => 2,
            'is_group' => 0,
        ]);

        $liabilities = Account::create($defaults + [
            'code' => '2000',
            'name' => 'Liabilities',
            'company_id' => $this->company->id,
            'account_type' => 'liability',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'level' => 0,
            'is_group' => 1,
        ]);

        $accountsPayable = Account::create($defaults + [
            'code' => '2100',
            'name' => 'Accounts Payable',
            'company_id' => $this->company->id,
            'parent_id' => $liabilities->id,
            'root_id' => $liabilities->id,
            'account_type' => 'liability',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'level' => 1,
            'is_group' => 1,
        ]);

        Account::create($defaults + [
            'code' => '2110',
            'name' => 'Suppliers',
            'company_id' => $this->company->id,
            'parent_id' => $accountsPayable->id,
            'root_id' => $liabilities->id,
            'account_type' => 'liability',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'level' => 2,
            'is_group' => 0,
        ]);
    }
}
