<?php

namespace Tests\Feature;

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

class InvoicePaymentTest extends TestCase
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

        // Create task (type must match a Booking Revenue account in COA)
        $this->task = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $this->supplier->id,
            'total' => 100.00,
            'status' => 'issued',
            'type' => 'flight',
        ]);

        InvoiceSequence::create([
            'company_id' => $this->company->id,
            'current_sequence' => 1,
        ]);

        // Set up Chart of Accounts (required for journal entries during payment)
        $this->setupChartOfAccounts();

        // Create an invoice to test payments against
        $this->invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-PAY-001',
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'sub_amount' => 150.00,
            'amount' => 150.00,
            'currency' => 'KWD',
            'status' => 'unpaid',
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

    // ─── INVOICE CREATION MARKUP/PROFIT ───────────────────────────────

    public function test_markup_is_invoice_price_minus_supplier_price(): void
    {
        $detail = InvoiceDetail::where('invoice_id', $this->invoice->id)->first();

        $expectedMarkup = $detail->task_price - $detail->supplier_price;

        $this->assertEquals($expectedMarkup, $detail->markup_price);
        $this->assertEquals(50.00, $detail->markup_price);
    }

    public function test_profit_initially_equals_markup(): void
    {
        $detail = InvoiceDetail::where('invoice_id', $this->invoice->id)->first();

        // profit uses 3 decimal places, markup uses 2 — compare as floats
        $this->assertEquals((float) $detail->markup_price, (float) $detail->profit);
    }

    public function test_loss_when_invoice_price_below_supplier_cost(): void
    {
        // Invoice price = 80, supplier cost = 100 → loss of 20
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), [
                'invoiceNumber' => 'INV-LOSS-001',
                'invdate' => '2026-03-04',
                'duedate' => '2026-03-09',
                'currency' => 'KWD',
                'subTotal' => 80.00,
                'clientId' => $this->client->id,
                'agentId' => $this->agent->id,
                'tasks' => [[
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 80.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => 100.00,
                ]],
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $detail = InvoiceDetail::where('invoice_number', 'INV-LOSS-001')->first();

        // Markup and profit should be negative (loss)
        $this->assertEquals(-20.00, $detail->markup_price);
        $this->assertEquals(-20.00, $detail->profit);
    }

    // ─── INVOICE STATUS TRANSITIONS ───────────────────────────────────

    public function test_new_invoice_has_unpaid_status(): void
    {
        $this->assertEquals('unpaid', $this->invoice->status);
    }

    public function test_invoice_status_changes_to_paid_with_credit_payment(): void
    {
        // Give client credit balance
        Credit::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'type' => Credit::TOPUP,
            'amount' => 500.00,
            'gateway_fee' => 0,
        ]);

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'invoiceNumber' => $this->invoice->invoice_number,
                'gateway' => 'Credit',
                'credit' => true,
                'companyId' => $this->company->id,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->invoice->refresh();
        $this->assertEquals('paid', $this->invoice->status);
    }

    public function test_credit_payment_fails_when_insufficient_balance(): void
    {
        // Client has only 50 credit, but invoice is 150
        Credit::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'type' => Credit::TOPUP,
            'amount' => 50.00,
            'gateway_fee' => 0,
        ]);

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'invoiceNumber' => $this->invoice->invoice_number,
                'gateway' => 'Credit',
                'credit' => true,
                'companyId' => $this->company->id,
            ]);

        $response->assertJson([
            'success' => false,
            'message' => 'Client credit is not enough!',
        ]);

        // Invoice should remain unpaid
        $this->invoice->refresh();
        $this->assertEquals('unpaid', $this->invoice->status);
    }

    public function test_credit_payment_deducts_from_client_balance(): void
    {
        Credit::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'type' => Credit::TOPUP,
            'amount' => 500.00,
            'gateway_fee' => 0,
        ]);

        $balanceBefore = Credit::getTotalCreditsByClient($this->client->id);

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'invoiceNumber' => $this->invoice->invoice_number,
                'gateway' => 'Credit',
                'credit' => true,
                'companyId' => $this->company->id,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $balanceAfter = Credit::getTotalCreditsByClient($this->client->id);

        // 500 - 150 = 350
        $this->assertEquals($balanceBefore - 150.00, $balanceAfter);
    }

    public function test_credit_payment_has_zero_gateway_fees(): void
    {
        Credit::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'type' => Credit::TOPUP,
            'amount' => 500.00,
            'gateway_fee' => 0,
        ]);

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'invoiceNumber' => $this->invoice->invoice_number,
                'gateway' => 'Credit',
                'credit' => true,
                'companyId' => $this->company->id,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $partial = InvoicePartial::where('invoice_id', $this->invoice->id)->first();

        $this->assertEquals(0, $partial->service_charge);
        $this->assertEquals(0, $partial->gateway_fee);
        $this->assertEquals('paid', $partial->status);
    }

    // ─── PARTIAL PAYMENT STATUS ───────────────────────────────────────

    public function test_partial_payment_with_split_creates_multiple_partials(): void
    {
        // First partial: credit for 50
        Credit::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'type' => Credit::TOPUP,
            'amount' => 500.00,
            'gateway_fee' => 0,
        ]);

        $response1 = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'clientId' => $this->client->id,
                'amount' => 50.00,
                'type' => 'split',
                'invoiceNumber' => $this->invoice->invoice_number,
                'gateway' => 'Credit',
                'credit' => true,
                'companyId' => $this->company->id,
            ]);

        $response1->assertOk()->assertJson(['success' => true]);

        // After first partial (paid), there should be partial status
        // since 50 of 150 is paid
        $this->invoice->refresh();

        // Second partial: remaining 100 via gateway (unpaid until webhook)
        $response2 = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'clientId' => $this->client->id,
                'amount' => 100.00,
                'type' => 'split',
                'invoiceNumber' => $this->invoice->invoice_number,
                'gateway' => 'SomeGateway',
                'companyId' => $this->company->id,
            ]);

        $response2->assertOk()->assertJson(['success' => true]);

        $this->invoice->refresh();

        // Should have 2 partials
        $this->assertEquals(2, $this->invoice->invoicePartials()->count());

        // Has paid (credit) + unpaid (gateway) → status = partial
        $this->assertEquals('partial', $this->invoice->status);
    }

    // ─── INVOICE CHARGE ACCUMULATION ──────────────────────────────────

    public function test_invoice_charge_accumulates_from_partials(): void
    {
        Credit::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'type' => Credit::TOPUP,
            'amount' => 1000.00,
            'gateway_fee' => 0,
        ]);

        // Partial with invoice_charge = 5.00
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'clientId' => $this->client->id,
                'amount' => 75.00,
                'type' => 'partial',
                'invoiceNumber' => $this->invoice->invoice_number,
                'gateway' => 'Credit',
                'credit' => true,
                'partial_invoice_charge' => 5.00,
                'companyId' => $this->company->id,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->invoice->refresh();

        // invoice.amount = sub_amount + total invoice_charge from partials
        $this->assertEquals(5.00, $this->invoice->invoice_charge);
        $this->assertEquals(155.00, $this->invoice->amount); // 150 + 5
    }

    // ─── MULTI-TASK INVOICE CALCULATIONS ──────────────────────────────

    public function test_multi_task_invoice_sub_amount_equals_sum_of_invprices(): void
    {
        $task2 = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $this->supplier->id,
            'total' => 200.00,
        ]);

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), [
                'invoiceNumber' => 'INV-MULTI-001',
                'invdate' => '2026-03-04',
                'duedate' => '2026-03-09',
                'currency' => 'KWD',
                'subTotal' => 500.00, // 250 + 250
                'clientId' => $this->client->id,
                'agentId' => $this->agent->id,
                'tasks' => [
                    [
                        'id' => $this->task->id,
                        'description' => $this->task->reference,
                        'invprice' => 250.00,
                        'supplier_id' => $this->supplier->id,
                        'client_id' => $this->client->id,
                        'agent_id' => $this->agent->id,
                        'total' => 100.00,
                    ],
                    [
                        'id' => $task2->id,
                        'description' => $task2->reference,
                        'invprice' => 250.00,
                        'supplier_id' => $this->supplier->id,
                        'client_id' => $this->client->id,
                        'agent_id' => $this->agent->id,
                        'total' => 200.00,
                    ],
                ],
            ]);

        $response->assertOk();

        $invoice = Invoice::where('invoice_number', 'INV-MULTI-001')->first();
        $this->assertEquals(500.00, $invoice->sub_amount);
        $this->assertEquals(500.00, $invoice->amount);

        $details = $invoice->invoiceDetails;
        $this->assertEquals(2, $details->count());

        // Task 1: markup = 250 - 100 = 150
        $detail1 = $details->where('task_id', $this->task->id)->first();
        $this->assertEquals(150.00, $detail1->markup_price);

        // Task 2: markup = 250 - 200 = 50
        $detail2 = $details->where('task_id', $task2->id)->first();
        $this->assertEquals(50.00, $detail2->markup_price);
    }

    // ─── CREDIT BALANCE TRACKING ──────────────────────────────────────

    public function test_client_credit_balance_tracks_topup_and_usage(): void
    {
        // Topup +300
        Credit::create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'type' => Credit::TOPUP,
            'amount' => 300.00,
            'gateway_fee' => 0,
        ]);

        $this->assertEquals(300.00, Credit::getTotalCreditsByClient($this->client->id));

        // Usage -100
        Credit::create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'invoice_id' => $this->invoice->id,
            'type' => Credit::INVOICE,
            'amount' => -100.00,
            'gateway_fee' => 0,
        ]);

        $this->assertEquals(200.00, Credit::getTotalCreditsByClient($this->client->id));
    }

    public function test_refund_credit_adds_to_balance(): void
    {
        Credit::create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'type' => Credit::REFUND,
            'amount' => 75.00,
            'gateway_fee' => 0,
        ]);

        $this->assertEquals(75.00, Credit::getTotalCreditsByClient($this->client->id));
    }

    // ─── INVOICE PAYMENT VALIDATION ───────────────────────────────────

    public function test_partial_payment_requires_invoice_id(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'invoiceNumber' => $this->invoice->invoice_number,
                'gateway' => 'Credit',
                'companyId' => $this->company->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_partial_payment_requires_amount(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'clientId' => $this->client->id,
                'type' => 'full',
                'invoiceNumber' => $this->invoice->invoice_number,
                'gateway' => 'Credit',
                'companyId' => $this->company->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_partial_payment_requires_gateway(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
            ]);

        $response->assertStatus(422);
    }

    // ─── INVOICE STATUS ENUM ──────────────────────────────────────────

    public function test_invoice_status_enum_values(): void
    {
        $validStatuses = ['paid', 'unpaid', 'partial', 'paid by refund', 'refunded', 'partial refund'];

        foreach ($validStatuses as $status) {
            $invoice = Invoice::factory()->create([
                'agent_id' => $this->agent->id,
                'client_id' => $this->client->id,
                'status' => $status,
            ]);

            $this->assertEquals($status, $invoice->fresh()->status);
        }
    }

    // ─── HELPER: SETUP CHART OF ACCOUNTS ──────────────────────────────

    /**
     * Create the minimum Account records needed for journal entries.
     * The savePartial method creates journal entries which require
     * Accounts Receivable, Clients, Direct Income, etc.
     */
    protected function setupChartOfAccounts(): void
    {
        $defaults = [
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
        ];

        // Root accounts
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

        // Income accounts
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

        // Liability accounts (for supplier payable)
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
