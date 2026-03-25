<?php

namespace Tests\Feature\Invoice;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\InvoiceReceipt;
use App\Models\InvoiceSequence;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\PaymentApplication;
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
    protected Charge $cashCharge;
    protected Charge $myfatoorahCharge;
    protected PaymentMethod $knetMethod;
    protected PaymentMethod $visaMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);

        // ─── Company user with permissions ────────────────────────────
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
        $roleCompany->givePermissionTo('update invoice');
        $roleCompany->givePermissionTo('update invoice payment method');

        // ─── Branch, agent, client, supplier ──────────────────────────
        $this->branch = Branch::factory()->create([
            'user_id' => $this->companyUser->id,
            'company_id' => $this->company->id,
        ]);

        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        $agentType = AgentType::create(['name' => 'Salary']);

        $this->agent = Agent::factory()->create([
            'user_id' => $agentUser->id,
            'branch_id' => $this->branch->id,
            'type_id' => $agentType->id,
        ]);

        $this->client = Client::factory()->create([
            'agent_id' => $this->agent->id,
        ]);

        $this->supplier = Supplier::factory()->create();

        // ─── Tasks ────────────────────────────────────────────────────
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

        // ─── Charges & payment methods ──────────────
        $this->cashCharge = Charge::factory()->create([
            'name' => 'Cash',
            'company_id' => $this->company->id,
            'type' => 'Payment Gateway',
            'description' => 'Payment Gateway Fee',
            'api_key' => null,
            'amount' => 0,
            'self_charge' => 0,
            'extra_charge' => 0,
            'charge_type' => 'Flat Rate',
            'paid_by' => 'Company',
            'is_active' => true,
            'can_generate_link' => false,
            'is_auto_paid' => false,
            'has_url' => false,
            'can_charge_invoice' => false,
            'is_system_default' => false,
            'can_be_deleted' => true,
            'enabled_by' => 'company',
        ]);

        $this->myfatoorahCharge = Charge::factory()->create([
            'name' => 'MyFatoorah',
            'company_id' => $this->company->id,
            'type' => 'Payment Gateway',
            'description' => 'Payment Gateway Fee',
            'api_key' => 'test-api-key-myfatoorah',
            'amount' => 0.25,
            'self_charge' => 0.25,
            'extra_charge' => 0,
            'charge_type' => 'Percent',
            'paid_by' => 'Client',
            'is_active' => true,
            'can_generate_link' => true,
            'is_auto_paid' => false,
            'has_url' => true,
            'can_charge_invoice' => true,
            'is_system_default' => true,
            'can_be_deleted' => false,
            'enabled_by' => 'admin',
        ]);

        // KNET method for MyFatoorah
        $this->knetMethod = PaymentMethod::factory()->create([
            'charge_id' => $this->myfatoorahCharge->id,
            'company_id' => $this->company->id,
            'english_name' => 'KNET',
            'code' => 'kn',
            'type' => 'myfatoorah',
            'is_active' => true,
            'currency' => 'KWD',
            'service_charge' => 0.15,
            'self_charge' => 0.15,
            'extra_charge' => 0,
            'charge_type' => 'Flat Rate',
            'paid_by' => 'Company',
        ]);

        // VISA/MASTER method for MyFatoorah
        $this->visaMethod = PaymentMethod::factory()->create([
            'charge_id' => $this->myfatoorahCharge->id,
            'company_id' => $this->company->id,
            'english_name' => 'VISA/MASTER',
            'code' => 'vm',
            'type' => 'myfatoorah',
            'is_active' => true,
            'currency' => 'KWD',
            'service_charge' => 2.30,
            'self_charge' => 2.50,
            'extra_charge' => 0,
            'charge_type' => 'Percent',
            'paid_by' => 'Client',
        ]);

        // ─── Invoice with one task ────────────────────────────────────
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
            'payment_gateway' => 'Cash',
            'charge_id' => $this->cashCharge->id,
            'service_charge' => 0,
            'gateway_fee' => 0,
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
            'payment_gateway' => 'Cash',
            'charge_id' => $this->cashCharge->id,
            'service_charge' => 0,
            'gateway_fee' => 0,
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
            'payment_gateway' => 'MyFatoorah',
            'payment_method' => $this->visaMethod->id,
            'charge_id' => $this->myfatoorahCharge->id,
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

        $this->invoice->update(['payment_type' => 'full', 'status' => 'unpaid']);

        $this->actingAs($this->companyUser)
            ->post(route('invoice.update-type'), [
                'invoice_id' => $this->invoice->id,
            ]);

        // Credit record for this partial should be soft-deleted
        $this->assertSoftDeleted('credits', [
            'invoice_partial_id' => $partial->id,
        ]);
    }

    // ─── SAVE PARTIAL — FULL PAYMENT WITH CASH ─────────────────────────────────────────
    // Cash is NOT system_default → creates receipt voucher

    public function test_full_payment_cash_creates_invoice_partial(): void
    {
        $this->setupChartOfAccounts();

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'gateway' => 'Cash',
                'method' => null,
                'date' => null,
                'partial_invoice_charge' => 0,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        // InvoicePartial created with Cash gateway
        $partial = InvoicePartial::where('invoice_id', $this->invoice->id)->first();
        $this->assertNotNull($partial);
        $this->assertEquals('Cash', $partial->payment_gateway);
        $this->assertEquals(150.00, (float) $partial->amount);
        $this->assertEquals(0, (float) $partial->service_charge);
        $this->assertEquals('unpaid', $partial->status);
        $this->assertNull($partial->payment_method, 'Cash should have no payment method');
        $this->assertNull($partial->receipt_voucher_id);

        // Invoice payment_type updated
        $this->invoice->refresh();
        $this->assertEquals('full', $this->invoice->payment_type);
    }

    public function test_full_payment_cash_creates_receipt_voucher(): void
    {
        $this->setupChartOfAccounts();

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'gateway' => 'Cash',
                'method' => null,
                'date' => null,
                'partial_invoice_charge' => 0,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $partial = InvoicePartial::where('invoice_id', $this->invoice->id)->first();

        // Cash is NOT system_default → receipt voucher should be created
        $this->assertDatabaseHas('invoice_receipts', [
            'invoice_id' => $this->invoice->id,
            'invoice_partial_id' => $partial->id,
            'type' => 'invoice',
            'status' => 'pending',
        ]);

        // Receipt voucher creates a Transaction
        $receipt = InvoiceReceipt::where('invoice_partial_id', $partial->id)->first();
        $this->assertNotNull($receipt->transaction_id);

        $this->assertDatabaseHas('transactions', [
            'id' => $receipt->transaction_id,
            'invoice_id' => $this->invoice->id,
            'transaction_type' => 'debit',
        ]);

        $partial->refresh();
        $this->assertNull($partial->receipt_voucher_id);
        $this->assertNull($partial->payment_method,
            'Cash partial should have no payment method');
    }

    public function test_full_payment_cash_creates_transaction_and_journal_entries(): void
    {
        $this->setupChartOfAccounts();

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'gateway' => 'Cash',
                'method' => null,
                'date' => null,
                'partial_invoice_charge' => 0,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        // Invoice generation transaction created (reference_type = Invoice)
        $transaction = Transaction::where('invoice_id', $this->invoice->id)
            ->where('reference_type', 'Invoice')
            ->where('transaction_type', 'credit')
            ->first();
        $this->assertNotNull($transaction, 'Invoice generation transaction should be created');

        // Journal entries: DEBIT Accounts Receivable > Clients (debit > 0)
        $debitEntry = JournalEntry::where('transaction_id', $transaction->id)
            ->where('debit', '>', 0)
            ->first();
        $this->assertNotNull($debitEntry, 'Debit journal entry should exist');
        $this->assertEquals(150.00, (float) $debitEntry->debit);

        // Journal entries: CREDIT Flight Booking Revenue (credit > 0)
        $creditEntry = JournalEntry::where('transaction_id', $transaction->id)
            ->where('credit', '>', 0)
            ->first();
        $this->assertNotNull($creditEntry, 'Credit journal entry should exist');
    }

    // ─── SAVE PARTIAL — FULL PAYMENT WITH MYFATOORAH ─────────────────────────────────────────
    // MyFatoorah IS system_default → NO receipt voucher

    public function test_full_payment_myfatoorah_creates_partial_with_gateway_fee(): void
    {
        $this->setupChartOfAccounts();

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'gateway' => 'MyFatoorah',
                'method' => (string) $this->visaMethod->id,
                'date' => null,
                'partial_invoice_charge' => 0,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        // InvoicePartial with gateway fee calculated
        $partial = InvoicePartial::where('invoice_id', $this->invoice->id)->first();
        $this->assertNotNull($partial);
        $this->assertEquals('MyFatoorah', $partial->payment_gateway);
        $this->assertEquals($this->visaMethod->id, $partial->payment_method,
            'VISA method ID should be stored on partial for charge calculation');
        $this->assertNull($partial->receipt_voucher_id,
            'MyFatoorah (system_default) should have no receipt_voucher_id');
        $this->assertGreaterThan(0, $partial->service_charge, 'VISA/MASTER should have a service charge');
    }

    public function test_full_payment_myfatoorah_no_receipt_voucher(): void
    {
        $this->setupChartOfAccounts();

        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'gateway' => 'MyFatoorah',
                'method' => (string) $this->knetMethod->id,
                'date' => null,
                'partial_invoice_charge' => 0,
            ]);

        $partial = InvoicePartial::where('invoice_id', $this->invoice->id)->first();

        // MyFatoorah IS system_default → no receipt voucher
        $this->assertEquals(0, InvoiceReceipt::where('invoice_partial_id', $partial->id)->count(),
            'System default gateway should NOT create receipt voucher');

        // KNET method ID should be stored on partial
        $this->assertEquals($this->knetMethod->id, $partial->payment_method,
            'KNET method ID should be stored on partial for charge calculation');
    }

    public function test_full_payment_myfatoorah_knet_company_pays_fee(): void
    {
        $this->setupChartOfAccounts();

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'gateway' => 'MyFatoorah',
                'method' => (string) $this->knetMethod->id,
                'date' => null,
                'partial_invoice_charge' => 0,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        // KNET paid_by = Company → service_charge should be 0 (company absorbs)
        $partial = InvoicePartial::where('invoice_id', $this->invoice->id)->first();
        $this->assertEquals($this->knetMethod->id, $partial->payment_method,
            'KNET method ID should be stored on partial for charge calculation');
        $this->assertEquals(0, (float) $partial->service_charge,
            'When company pays, client service_charge should be 0');
        // But accounting fee should still be tracked
        $this->assertGreaterThanOrEqual(0, (float) $partial->gateway_fee);
    }

    // ─── SAVE PARTIAL — PARTIAL PAYMENT (installments) ─────────────────────────────────────────

    public function test_partial_payment_creates_multiple_installments(): void
    {
        $this->setupChartOfAccounts();

        // First installment: 75 KWD via Cash
        $response1 = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 75.00,
                'type' => 'partial',
                'gateway' => 'Cash',
                'method' => null,
                'date' => '2026-03-10',
                'partial_invoice_charge' => 0,
            ]);
        $response1->assertOk()->assertJson(['success' => true]);

        // Second installment: 75 KWD via MyFatoorah VISA
        $response2 = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 75.00,
                'type' => 'partial',
                'gateway' => 'MyFatoorah',
                'method' => (string) $this->visaMethod->id,
                'date' => '2026-03-20',
                'partial_invoice_charge' => 0,
            ]);
        $response2->assertOk()->assertJson(['success' => true]);

        // Should have 2 partials
        $partials = InvoicePartial::where('invoice_id', $this->invoice->id)->get();
        $this->assertCount(2, $partials);

        // First partial: Cash (no payment method, has receipt voucher)
        $cashPartial = $partials->firstWhere('payment_gateway', 'Cash');
        $this->assertEquals(75.00, (float) $cashPartial->amount);
        $this->assertEquals('partial', $cashPartial->type);
        $this->assertNull($cashPartial->payment_method, 'Cash partial should have no payment method');
        $this->assertNull($cashPartial->receipt_voucher_id);

        // Second partial: MyFatoorah with fee and method (no receipt voucher)
        $mfPartial = $partials->firstWhere('payment_gateway', 'MyFatoorah');
        $this->assertEquals(75.00, (float) $mfPartial->amount);
        $this->assertEquals($this->visaMethod->id, $mfPartial->payment_method,
            'VISA method ID should be stored on MyFatoorah partial');
        $this->assertNull($mfPartial->receipt_voucher_id,
            'MyFatoorah partial should have no receipt_voucher_id');
        $this->assertGreaterThan(0, (float) $mfPartial->service_charge);

        // Invoice status should be unpaid (both partials unpaid)
        $this->invoice->refresh();
        $this->assertEquals('partial', $this->invoice->payment_type);
    }

    public function test_partial_payment_cash_installment_creates_receipt_voucher(): void
    {
        $this->setupChartOfAccounts();

        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 75.00,
                'type' => 'partial',
                'gateway' => 'Cash',
                'method' => null,
                'date' => '2026-03-10',
                'partial_invoice_charge' => 0,
            ]);

        $partial = InvoicePartial::where('invoice_id', $this->invoice->id)->first();

        // Cash partial creates receipt voucher
        $this->assertDatabaseHas('invoice_receipts', [
            'invoice_partial_id' => $partial->id,
            'type' => 'invoice',
            'status' => 'pending',
        ]);

        $partial->refresh();
        $this->assertNull($partial->receipt_voucher_id);
    }

    // ─── SAVE PARTIAL — SPLIT PAYMENT (different clients/gateways) ─────────────────────────────────────────

    public function test_split_payment_creates_partials_with_different_gateways(): void
    {
        $this->setupChartOfAccounts();

        // Split 1: Cash
        $r1 = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 100.00,
                'type' => 'split',
                'gateway' => 'Cash',
                'method' => null,
                'date' => '2026-03-10',
                'partial_invoice_charge' => 0,
            ]);
        $r1->assertOk()->assertJson(['success' => true]);

        // Create a second client for split
        $client2 = Client::factory()->create(['agent_id' => $this->agent->id]);

        // Split 2: MyFatoorah KNET with different client
        $r2 = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $client2->id,
                'amount' => 50.00,
                'type' => 'split',
                'gateway' => 'MyFatoorah',
                'method' => (string) $this->knetMethod->id,
                'date' => '2026-03-10',
                'partial_invoice_charge' => 0,
            ]);
        $r2->assertOk()->assertJson(['success' => true]);

        $partials = InvoicePartial::where('invoice_id', $this->invoice->id)->get();
        $this->assertCount(2, $partials);

        // Verify different clients
        $this->assertEquals($this->client->id, $partials[0]->client_id);
        $this->assertEquals($client2->id, $partials[1]->client_id);

        // Verify different gateways
        $this->assertEquals('Cash', $partials[0]->payment_gateway);
        $this->assertNull($partials[0]->payment_method, 'Cash split should have no payment method');
        $this->assertEquals('MyFatoorah', $partials[1]->payment_gateway);
        $this->assertEquals($this->knetMethod->id, $partials[1]->payment_method,
            'KNET method ID should be stored on MyFatoorah split partial');

        $this->invoice->refresh();
        $this->assertEquals('split', $this->invoice->payment_type);
    }

    // ─── SAVE PARTIAL — CREDIT PAYMENT ─────────────────────────────────────────
    // Gateway = 'Credit' → auto paid, deduct credit, create COA

    public function test_credit_payment_fails_when_insufficient_balance(): void
    {
        $this->setupChartOfAccounts();

        // Client has NO credit balance
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'gateway' => 'Credit',
                'method' => null,
                'date' => null,
                'credit' => true,
                'partial_invoice_charge' => 0,
            ]);

        $response->assertOk()->assertJson([
            'success' => false,
            'message' => 'Client credit is not enough!',
        ]);

        // No partial should be created
        $this->assertEquals(0, InvoicePartial::where('invoice_id', $this->invoice->id)->count());
    }

    public function test_credit_payment_deducts_client_credit(): void
    {
        $this->setupChartOfAccounts();
        $this->setupCreditAccounts();

        // Give client 200 KWD credit (TOPUP)
        Credit::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'type' => Credit::TOPUP,
            'description' => 'Test topup',
            'amount' => 200.00,
            'gateway_fee' => 0,
        ]);

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'gateway' => 'Credit',
                'method' => null,
                'date' => null,
                'credit' => true,
                'partial_invoice_charge' => 0,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        // InvoicePartial created with status=paid (credit is auto-paid)
        $this->assertDatabaseHas('invoice_partials', [
            'invoice_id' => $this->invoice->id,
            'payment_gateway' => 'Credit',
            'status' => 'paid',
            'service_charge' => 0,
        ]);

        // Negative credit record created (deduction from client balance)
        $this->assertDatabaseHas('credits', [
            'client_id' => $this->client->id,
            'invoice_id' => $this->invoice->id,
            'type' => Credit::INVOICE,
            'amount' => -150.00,
        ]);

        // Invoice should be marked as paid
        $this->invoice->refresh();
        $this->assertEquals('paid', $this->invoice->status);
        $this->assertNotNull($this->invoice->paid_date);
    }

    public function test_credit_payment_creates_credit_coa_entries(): void
    {
        $this->setupChartOfAccounts();
        $this->setupCreditAccounts();

        // Give client credit
        Credit::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'type' => Credit::TOPUP,
            'description' => 'Test topup',
            'amount' => 200.00,
            'gateway_fee' => 0,
        ]);

        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'gateway' => 'Credit',
                'method' => null,
                'date' => null,
                'credit' => true,
                'partial_invoice_charge' => 0,
            ]);

        // Credit payment COA: Transaction with reference_type = 'Payment'
        $creditTransaction = Transaction::where('invoice_id', $this->invoice->id)
            ->where('reference_type', 'Payment')
            ->first();
        $this->assertNotNull($creditTransaction, 'Credit payment transaction should be created');

        // Should have journal entries for credit payment
        $journalEntries = JournalEntry::where('transaction_id', $creditTransaction->id)->get();
        $this->assertGreaterThanOrEqual(2, $journalEntries->count(),
            'Credit payment should have at least 2 journal entries (debit liability, credit receivable)');
    }

    // ─── SAVE PARTIAL — PROFIT CALCULATION ─────────────────────────────────────────

    public function test_save_partial_calculates_profit_on_invoice_detail(): void
    {
        $this->setupChartOfAccounts();
        $this->setupProfitAccounts();

        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'gateway' => 'Cash',
                'method' => null,
                'date' => null,
                'partial_invoice_charge' => 0,
            ]);

        // Invoice detail profit should be calculated
        // selling=150, supplier=100, margin=50, Cash fee=0
        $detail = InvoiceDetail::where('invoice_id', $this->invoice->id)->first();
        $detail->refresh();
        $this->assertGreaterThanOrEqual(0, (float) $detail->profit,
            'Profit should be calculated after savePartial');
    }

    // ─── UPDATE PAYMENT GATEWAY (change gateway on existing full payment) ─────────────────────────────────────────

    public function test_update_payment_gateway_from_cash_to_myfatoorah(): void
    {
        $this->setupChartOfAccounts();

        // First save as Cash full payment
        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'gateway' => 'Cash',
                'method' => null,
                'date' => null,
                'partial_invoice_charge' => 0,
            ]);

        // Now change gateway to MyFatoorah
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.update-gateway'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'gateway' => 'MyFatoorah',
                'method' => (string) $this->visaMethod->id,
                'amount' => 150.00,
            ]);

        $response->assertOk()->assertJson(['message' => 'Payment method updated successfully!']);

        // Partial should now be MyFatoorah with VISA method
        $partial = InvoicePartial::where('invoice_id', $this->invoice->id)->first();
        $partial->refresh();
        $this->assertEquals('MyFatoorah', $partial->payment_gateway);
        $this->assertEquals($this->visaMethod->id, $partial->payment_method,
            'VISA method ID should be stored after gateway update');
    }

    // ─── UPDATE TASK PRICE ─────────────────────────────────────────

    public function test_can_update_task_price(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->postJson('/invoice/update-task-price', [
                'task_id' => $this->task->id,
                'new_price' => 200.00,
            ]);

        $response->assertOk();

        // Invoice detail price should be updated
        $detail = InvoiceDetail::where('invoice_id', $this->invoice->id)
            ->where('task_id', $this->task->id)
            ->first();
        $detail->refresh();
        $this->assertEquals(200.00, (float) $detail->task_price);
    }

    // ─── UPDATE INVOICE DATE ─────────────────────────────────────────

    public function test_can_update_invoice_date(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->put(route('invoice.updateDate', [
                'companyId' => $this->company->id,
                'invoiceNumber' => $this->invoice->invoice_number,
            ]), [
                'invdate' => '2026-03-20',
            ]);

        $response->assertRedirect();

        $this->invoice->refresh();
        $invoiceDate = $this->invoice->invoice_date instanceof \Carbon\Carbon
            ? $this->invoice->invoice_date->format('Y-m-d')
            : $this->invoice->invoice_date;
        $this->assertEquals('2026-03-20', $invoiceDate);
    }

    // ─── VALIDATION TESTS ─────────────────────────────────────────

    public function test_save_partial_validates_required_fields(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                // Missing required fields
            ]);

        $response->assertStatus(422);
    }

    public function test_save_partial_validates_gateway_required(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                // Missing gateway
            ]);

        $response->assertStatus(422);
    }

    // ─── SEND INVOICE EMAIL ─────────────────────────────────────────

    public function test_send_invoice_email(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.send-email', [
                'companyId' => $this->company->id,
                'invoiceNumber' => $this->invoice->invoice_number,
            ]), [
                'recipients' => ['test@example.com'],
                'send_to_client' => true,
                'custom_emails' => 'test@example.com',
            ]);

        // Should return success (email queued) or redirect
        $response->assertStatus(200);
    }

    // ─── CHANGE PAYMENT TYPE — Receipt voucher cleanup ─────────────────────────────────────────
    // updatePaymentType deletes InvoiceReceipts + their Transactions

    public function test_change_payment_type_deletes_receipt_vouchers(): void
    {
        $this->setupChartOfAccounts();

        // Save full payment with Cash (creates receipt voucher since Cash is NOT system_default)
        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'gateway' => 'Cash',
                'method' => null,
                'date' => null,
                'partial_invoice_charge' => 0,
            ]);

        $partial = InvoicePartial::where('invoice_id', $this->invoice->id)->first();
        $receipt = InvoiceReceipt::where('invoice_partial_id', $partial->id)->first();
        $this->assertNotNull($receipt, 'Receipt voucher should exist before type change');
        $rvTransactionId = $receipt->transaction_id;

        // Now change payment type (should delete receipt voucher + its transaction)
        $this->actingAs($this->companyUser)
            ->post(route('invoice.update-type'), [
                'invoice_id' => $this->invoice->id,
            ]);

        // Receipt voucher should be deleted
        $this->assertNull(InvoiceReceipt::find($receipt->id),
            'Receipt voucher must be deleted when payment type changes');

        // Receipt voucher transaction should be deleted
        $this->assertNull(Transaction::find($rvTransactionId),
            'Receipt voucher transaction must be deleted when payment type changes');

        // Journal entries for receipt voucher transaction should be deleted
        $this->assertEquals(0, JournalEntry::where('transaction_id', $rvTransactionId)->count(),
            'Journal entries for receipt voucher must be deleted');
    }

    // ─── UPDATE GATEWAY — Receipt voucher cleanup ─────────────────────────────────────────
    // When changing gateway from non-system-default, old receipt must go

    public function test_update_gateway_deletes_old_receipt_voucher(): void
    {
        $this->setupChartOfAccounts();

        // Save full payment with Cash (creates receipt voucher)
        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'gateway' => 'Cash',
                'method' => null,
                'date' => null,
                'partial_invoice_charge' => 0,
            ]);

        $partial = InvoicePartial::where('invoice_id', $this->invoice->id)->first();
        $oldReceipt = InvoiceReceipt::where('invoice_partial_id', $partial->id)->first();
        $this->assertNotNull($oldReceipt, 'Cash receipt voucher should exist before gateway change');
        $oldRvTransactionId = $oldReceipt->transaction_id;

        // Change gateway to MyFatoorah (system_default → no new receipt voucher needed)
        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.update-gateway'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'gateway' => 'MyFatoorah',
                'method' => (string) $this->visaMethod->id,
                'amount' => 150.00,
            ]);

        // Old Cash receipt voucher should be deleted
        $this->assertNull(InvoiceReceipt::find($oldReceipt->id),
            'Old receipt voucher must be deleted when gateway changes');

        // Old receipt voucher transaction should be deleted
        $this->assertNull(Transaction::find($oldRvTransactionId),
            'Old receipt voucher transaction must be deleted when gateway changes');
    }

    // ─── DELETE INVOICE — Credit reversal + receipt voucher cleanup ─────────────────────────────────────────

    public function test_delete_invoice_with_credit_returns_credit_to_client(): void
    {
        $this->setupChartOfAccounts();
        $this->setupCreditAccounts();

        // Give client 200 KWD credit
        Credit::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'type' => Credit::TOPUP,
            'description' => 'Test topup',
            'amount' => 200.00,
            'gateway_fee' => 0,
        ]);

        // Pay invoice with credit
        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'gateway' => 'Credit',
                'method' => null,
                'date' => null,
                'credit' => true,
                'partial_invoice_charge' => 0,
            ]);

        // Verify credit was deducted (200 topup - 150 invoice = 50 remaining)
        $balanceBefore = Credit::where('client_id', $this->client->id)->sum('amount');
        $this->assertEquals(50.00, (float) $balanceBefore);

        // Delete the invoice
        $this->actingAs($this->companyUser)
            ->delete(route('invoice.delete', $this->invoice->id));

        // Credit deduction records should be removed, restoring client balance
        $balanceAfter = Credit::where('client_id', $this->client->id)
            ->whereNull('deleted_at')
            ->sum('amount');
        $this->assertEquals(200.00, (float) $balanceAfter,
            'Client credit must be restored when invoice with credit payment is deleted');
    }

    public function test_delete_invoice_deletes_receipt_vouchers(): void
    {
        $this->setupChartOfAccounts();

        // Save full payment with Cash (creates receipt voucher)
        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'gateway' => 'Cash',
                'method' => null,
                'date' => null,
                'partial_invoice_charge' => 0,
            ]);

        $partial = InvoicePartial::where('invoice_id', $this->invoice->id)->first();
        $receipt = InvoiceReceipt::where('invoice_partial_id', $partial->id)->first();
        $this->assertNotNull($receipt, 'Receipt voucher should exist before delete');
        $rvTransactionId = $receipt->transaction_id;

        // Delete the invoice
        $this->actingAs($this->companyUser)
            ->delete(route('invoice.delete', $this->invoice->id));

        // Receipt voucher should be deleted
        $this->assertNull(InvoiceReceipt::find($receipt->id),
            'Receipt voucher must be deleted when invoice is deleted');

        // Receipt voucher transaction should be deleted
        $this->assertNull(Transaction::find($rvTransactionId),
            'Receipt voucher transaction must be deleted when invoice is deleted');
    }

    public function test_delete_invoice_clears_payment_link(): void
    {
        $this->setupChartOfAccounts();

        // Save full payment with MyFatoorah + external URL
        $this->invoice->update(['external_url' => 'https://payment.example.com/pay/123']);

        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'gateway' => 'MyFatoorah',
                'method' => (string) $this->visaMethod->id,
                'date' => null,
                'partial_invoice_charge' => 0,
            ]);

        // Delete the invoice
        $this->actingAs($this->companyUser)
            ->delete(route('invoice.delete', $this->invoice->id));

        // Invoice should be soft-deleted, payment link gone with it
        $this->assertSoftDeleted('invoices', ['id' => $this->invoice->id]);
    }

    // ─── CREDIT SPLIT PAYMENT SCENARIOS ─────────────────────────────

    public function test_credit_split_creates_multiple_partials(): void
    {
        $this->setupChartOfAccounts();
        $this->setupCreditAccounts();

        Credit::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'type' => Credit::TOPUP,
            'amount' => 500.00,
            'gateway_fee' => 0,
        ]);

        // Split 1: 50 via Credit (auto-paid)
        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 50.00,
                'type' => 'split',
                'gateway' => 'Credit',
                'credit' => true,
                'partial_invoice_charge' => 0,
            ])
            ->assertOk()->assertJson(['success' => true]);

        // Split 2: 100 via MyFatoorah (unpaid until webhook)
        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 100.00,
                'type' => 'split',
                'gateway' => 'MyFatoorah',
                'method' => (string) $this->visaMethod->id,
                'partial_invoice_charge' => 0,
            ])
            ->assertOk()->assertJson(['success' => true]);

        $this->invoice->refresh();
        $this->assertEquals(2, $this->invoice->invoicePartials()->count());
        // Has paid (credit) + unpaid (MyFatoorah) → status = partial
        $this->assertEquals('partial', $this->invoice->status);
    }

    public function test_split_payment_credit_plus_cash(): void
    {
        $this->setupChartOfAccounts();
        $this->setupCreditAccounts();

        Credit::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'type' => Credit::TOPUP,
            'amount' => 100.00,
            'gateway_fee' => 0,
        ]);

        // Split 1: 80 via Credit
        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 80.00,
                'type' => 'split',
                'gateway' => 'Credit',
                'credit' => true,
                'partial_invoice_charge' => 0,
            ])
            ->assertOk()->assertJson(['success' => true]);

        // Split 2: 70 via Cash
        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 70.00,
                'type' => 'split',
                'gateway' => 'Cash',
                'method' => null,
                'partial_invoice_charge' => 0,
            ])
            ->assertOk()->assertJson(['success' => true]);

        $this->invoice->refresh();
        $this->assertEquals(2, $this->invoice->invoicePartials()->count());

        $creditPartial = $this->invoice->invoicePartials()->where('payment_gateway', 'Credit')->first();
        $cashPartial = $this->invoice->invoicePartials()->where('payment_gateway', 'Cash')->first();

        $this->assertEquals('paid', $creditPartial->status);
        $this->assertEquals('unpaid', $cashPartial->status);
        // Invoice status should be partial (mix of paid + unpaid)
        $this->assertEquals('partial', $this->invoice->status);
    }

    public function test_split_payment_all_credit_becomes_paid(): void
    {
        $this->setupChartOfAccounts();
        $this->setupCreditAccounts();

        Credit::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'type' => Credit::TOPUP,
            'amount' => 500.00,
            'gateway_fee' => 0,
        ]);

        // Split 1: 80 credit
        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 80.00,
                'type' => 'split',
                'gateway' => 'Credit',
                'credit' => true,
                'partial_invoice_charge' => 0,
            ]);

        // Split 2: 70 credit
        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 70.00,
                'type' => 'split',
                'gateway' => 'Credit',
                'credit' => true,
                'partial_invoice_charge' => 0,
            ]);

        $this->invoice->refresh();

        // Both paid → invoice should be paid
        $this->assertEquals('paid', $this->invoice->status);
        $this->assertEquals(2, $this->invoice->invoicePartials()->where('status', 'paid')->count());
    }

    public function test_split_payment_shows_correct_client_credit_deduction(): void
    {
        $this->setupChartOfAccounts();
        $this->setupCreditAccounts();

        Credit::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'type' => Credit::TOPUP,
            'amount' => 200.00,
            'gateway_fee' => 0,
        ]);

        $balanceBefore = Credit::getTotalCreditsByClient($this->client->id);
        $this->assertEquals(200.00, $balanceBefore);

        // Use 80 credit for split
        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 80.00,
                'type' => 'split',
                'gateway' => 'Credit',
                'credit' => true,
                'partial_invoice_charge' => 0,
            ]);

        $balanceAfter = Credit::getTotalCreditsByClient($this->client->id);
        $this->assertEquals(120.00, $balanceAfter); // 200 - 80 = 120
    }

    public function test_client_credit_exact_amount_succeeds(): void
    {
        $this->setupChartOfAccounts();
        $this->setupCreditAccounts();

        Credit::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'type' => Credit::TOPUP,
            'amount' => 150.00,
            'gateway_fee' => 0,
        ]);

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'gateway' => 'Credit',
                'credit' => true,
                'partial_invoice_charge' => 0,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        // Balance should be exactly 0
        $this->assertEquals(0.00, Credit::getTotalCreditsByClient($this->client->id));
    }

    // ─── INVOICE CHARGE ACCUMULATION ──────────────────────────────

    public function test_invoice_charge_accumulates_from_partials(): void
    {
        $this->setupChartOfAccounts();
        $this->setupCreditAccounts();

        Credit::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'type' => Credit::TOPUP,
            'amount' => 1000.00,
            'gateway_fee' => 0,
        ]);

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 75.00,
                'type' => 'partial',
                'gateway' => 'Credit',
                'credit' => true,
                'partial_invoice_charge' => 5.00,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->invoice->refresh();
        $this->assertEquals(5.00, $this->invoice->invoice_charge);
        $this->assertEquals(155.00, $this->invoice->amount); // 150 + 5
    }

    public function test_multiple_partials_accumulate_invoice_charges(): void
    {
        $this->setupChartOfAccounts();
        $this->setupCreditAccounts();

        Credit::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'type' => Credit::TOPUP,
            'amount' => 1000.00,
            'gateway_fee' => 0,
        ]);

        // Partial 1 with invoice_charge = 3.00
        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 50.00,
                'type' => 'split',
                'gateway' => 'Credit',
                'credit' => true,
                'partial_invoice_charge' => 3.00,
            ]);

        // Partial 2 with invoice_charge = 7.00
        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 100.00,
                'type' => 'split',
                'gateway' => 'Credit',
                'credit' => true,
                'partial_invoice_charge' => 7.00,
            ]);

        $this->invoice->refresh();

        // Total invoice_charge = 3 + 7 = 10
        $this->assertEquals(10.00, $this->invoice->invoice_charge);
        // Amount = sub_amount + total charges = 150 + 10 = 160
        $this->assertEquals(160.00, $this->invoice->amount);
    }

    // ─── GATEWAY STATUS ───────────────────────────────────────────

    public function test_partial_payment_keeps_invoice_unpaid_when_only_gateway(): void
    {
        $this->setupChartOfAccounts();

        // Only gateway partial (no credit) → unpaid → invoice stays unpaid
        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'invoiceNumber' => $this->invoice->invoice_number,
                'companyId' => $this->company->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'partial',
                'gateway' => 'Cash',
                'method' => null,
                'partial_invoice_charge' => 0,
            ]);

        $this->invoice->refresh();
        $this->assertEquals('unpaid', $this->invoice->status);
    }

    // ─── CLIENT CREDIT BALANCE ────────────────────────────────────

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

    public function test_multiple_topups_accumulate_in_credit_balance(): void
    {
        Credit::create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'type' => Credit::TOPUP,
            'amount' => 100.00,
            'gateway_fee' => 0,
        ]);

        Credit::create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'type' => Credit::TOPUP,
            'amount' => 200.00,
            'gateway_fee' => 0,
        ]);

        $this->assertEquals(300.00, Credit::getTotalCreditsByClient($this->client->id));
    }

    public function test_mixed_credit_types_calculate_balance_correctly(): void
    {
        // Topup +500
        Credit::create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'type' => Credit::TOPUP,
            'amount' => 500.00,
            'gateway_fee' => 0,
        ]);

        // Invoice usage -150
        Credit::create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'invoice_id' => $this->invoice->id,
            'type' => Credit::INVOICE,
            'amount' => -150.00,
            'gateway_fee' => 0,
        ]);

        // Refund +50
        Credit::create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'type' => Credit::REFUND,
            'amount' => 50.00,
            'gateway_fee' => 0,
        ]);

        // 500 - 150 + 50 = 400
        $this->assertEquals(400.00, Credit::getTotalCreditsByClient($this->client->id));
    }

    // ─── ADDITIONAL PAYMENT VALIDATION ────────────────────────────

    public function test_partial_payment_requires_type(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'invoiceNumber' => $this->invoice->invoice_number,
                'gateway' => 'Credit',
                'companyId' => $this->company->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_partial_payment_requires_client_id(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'amount' => 150.00,
                'type' => 'full',
                'invoiceNumber' => $this->invoice->invoice_number,
                'gateway' => 'Credit',
                'companyId' => $this->company->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_partial_payment_requires_company_id(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'invoiceNumber' => $this->invoice->invoice_number,
                'gateway' => 'Credit',
            ]);

        $response->assertStatus(422);
    }

    public function test_partial_payment_requires_invoice_number(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.partial'), [
                'invoiceId' => $this->invoice->id,
                'clientId' => $this->client->id,
                'amount' => 150.00,
                'type' => 'full',
                'gateway' => 'Credit',
                'companyId' => $this->company->id,
            ]);

        $response->assertStatus(422);
    }

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

    // ─── HELPERS — Chart of Accounts Setup ─────────────────────────────────────────

    protected function setupChartOfAccounts(): void
    {
        $d = [
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
        ];

        // ── Assets ────────────────────────────────────────────────────
        $assets = Account::create($d + [
            'code' => '1000', 'name' => 'Assets',
            'company_id' => $this->company->id,
            'account_type' => 'asset',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'level' => 0, 'is_group' => 1,
        ]);

        $accountReceivable = Account::create($d + [
            'code' => '1100', 'name' => 'Accounts Receivable',
            'company_id' => $this->company->id,
            'parent_id' => $assets->id, 'root_id' => $assets->id,
            'account_type' => 'asset',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'level' => 1, 'is_group' => 1,
        ]);

        Account::create($d + [
            'code' => '1110', 'name' => 'Clients',
            'company_id' => $this->company->id,
            'parent_id' => $accountReceivable->id, 'root_id' => $assets->id,
            'account_type' => 'asset',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'level' => 2, 'is_group' => 0,
        ]);

        // ── Income ────────────────────────────────────────────────────
        $income = Account::create($d + [
            'code' => '4000', 'name' => 'Income',
            'company_id' => $this->company->id,
            'account_type' => 'income',
            'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
            'level' => 0, 'is_group' => 1,
        ]);

        $directIncome = Account::create($d + [
            'code' => '4100', 'name' => 'Direct Income',
            'company_id' => $this->company->id,
            'parent_id' => $income->id, 'root_id' => $income->id,
            'account_type' => 'income',
            'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
            'level' => 1, 'is_group' => 1,
        ]);

        Account::create($d + [
            'code' => '4110', 'name' => 'Flight Booking Revenue',
            'company_id' => $this->company->id,
            'parent_id' => $directIncome->id, 'root_id' => $income->id,
            'branch_id' => $this->branch->id,
            'account_type' => 'income',
            'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
            'level' => 2, 'is_group' => 0,
        ]);

        // ── Liabilities ───────────────────────────────────────────────
        $liabilities = Account::create($d + [
            'code' => '2000', 'name' => 'Liabilities',
            'company_id' => $this->company->id,
            'account_type' => 'liability',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'level' => 0, 'is_group' => 1,
        ]);

        $accountsPayable = Account::create($d + [
            'code' => '2100', 'name' => 'Accounts Payable',
            'company_id' => $this->company->id,
            'parent_id' => $liabilities->id, 'root_id' => $liabilities->id,
            'account_type' => 'liability',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'level' => 1, 'is_group' => 1,
        ]);

        Account::create($d + [
            'code' => '2110', 'name' => 'Suppliers',
            'company_id' => $this->company->id,
            'parent_id' => $accountsPayable->id, 'root_id' => $liabilities->id,
            'account_type' => 'liability',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'level' => 2, 'is_group' => 0,
        ]);

        // ── Expenses ──────────────────────────────────────────────────
        $expenses = Account::create($d + [
            'code' => '5000', 'name' => 'Expenses',
            'company_id' => $this->company->id,
            'account_type' => 'expense',
            'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
            'level' => 0, 'is_group' => 1,
        ]);

        // Supplier cost account (named after supplier)
        Account::create($d + [
            'code' => '5100', 'name' => $this->supplier->name,
            'company_id' => $this->company->id,
            'parent_id' => $expenses->id, 'root_id' => $expenses->id,
            'account_type' => 'expense',
            'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
            'level' => 1, 'is_group' => 0,
        ]);

        // Gateway Fee Recovery (income from gateway markup)
        Account::create($d + [
            'code' => '4200', 'name' => 'Gateway Fee Recovery',
            'company_id' => $this->company->id,
            'parent_id' => $income->id, 'root_id' => $income->id,
            'account_type' => 'income',
            'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
            'level' => 1, 'is_group' => 0,
        ]);

        // Loss accounts
        Account::create($d + [
            'code' => '4300', 'name' => 'Loss Recovery Income',
            'company_id' => $this->company->id,
            'parent_id' => $income->id, 'root_id' => $income->id,
            'account_type' => 'income',
            'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
            'level' => 1, 'is_group' => 0,
        ]);

        Account::create($d + [
            'code' => '5200', 'name' => 'Company Loss on Sales',
            'company_id' => $this->company->id,
            'parent_id' => $expenses->id, 'root_id' => $expenses->id,
            'account_type' => 'expense',
            'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
            'level' => 1, 'is_group' => 0,
        ]);

        Account::create($d + [
            'code' => '5300', 'name' => 'Fee Loss Provision',
            'company_id' => $this->company->id,
            'parent_id' => $expenses->id, 'root_id' => $expenses->id,
            'account_type' => 'expense',
            'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
            'level' => 1, 'is_group' => 0,
        ]);
    }

    protected function setupCreditAccounts(): void
    {
        $d = [
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
        ];

        // Liabilities > Advances > Client > Payment Gateway
        $liabilities = Account::where('name', 'Liabilities')
            ->where('company_id', $this->company->id)->first();

        $advances = Account::create($d + [
            'code' => '2200', 'name' => 'Advances',
            'company_id' => $this->company->id,
            'parent_id' => $liabilities->id, 'root_id' => $liabilities->id,
            'account_type' => 'liability',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'level' => 1, 'is_group' => 1,
        ]);

        $clientAdvance = Account::create($d + [
            'code' => '2210', 'name' => 'Client',
            'company_id' => $this->company->id,
            'parent_id' => $advances->id, 'root_id' => $liabilities->id,
            'account_type' => 'liability',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'level' => 2, 'is_group' => 1,
        ]);

        Account::create($d + [
            'code' => '2211', 'name' => 'Payment Gateway',
            'company_id' => $this->company->id,
            'parent_id' => $clientAdvance->id, 'root_id' => $liabilities->id,
            'account_type' => 'liability',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'level' => 3, 'is_group' => 0,
        ]);
    }

    protected function setupProfitAccounts(): void
    {
        $d = [
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
        ];

        $expenses = Account::where('name', 'Expenses')
            ->where('company_id', $this->company->id)->first();

        $liabilities = Account::where('name', 'Liabilities')
            ->where('company_id', $this->company->id)->first();

        // Agent Salaries (expense)
        Account::create($d + [
            'code' => '5400', 'name' => 'Agent Salaries',
            'company_id' => $this->company->id,
            'parent_id' => $expenses->id, 'root_id' => $expenses->id,
            'account_type' => 'expense',
            'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
            'level' => 1, 'is_group' => 0,
        ]);

        // Agent Profit Payable (liability)
        $profitAccount = Account::create($d + [
            'code' => '2300', 'name' => 'Agent Profit Payable',
            'company_id' => $this->company->id,
            'parent_id' => $liabilities->id, 'root_id' => $liabilities->id,
            'account_type' => 'liability',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'level' => 1, 'is_group' => 0,
        ]);

        // Set agent profit account
        $this->agent->update(['profit_account_id' => $profitAccount->id]);

        // Commissions accounts
        Account::create($d + [
            'code' => '5500', 'name' => 'Commissions Expense (Agents)',
            'company_id' => $this->company->id,
            'parent_id' => $expenses->id, 'root_id' => $expenses->id,
            'account_type' => 'expense',
            'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
            'level' => 1, 'is_group' => 0,
        ]);

        Account::create($d + [
            'code' => '2400', 'name' => 'Commissions (Agents)',
            'company_id' => $this->company->id,
            'parent_id' => $liabilities->id, 'root_id' => $liabilities->id,
            'account_type' => 'liability',
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'level' => 1, 'is_group' => 0,
        ]);
    }
}
