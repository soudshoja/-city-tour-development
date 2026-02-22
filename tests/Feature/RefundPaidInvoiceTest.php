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
use App\Models\Refund;
use App\Models\RefundDetail;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundPaidInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $companyUser;
    protected Company $company;
    protected Branch $branch;
    protected Agent $agent;
    protected Client $client;
    protected Supplier $supplier;
    protected Task $originalTask;
    protected Task $refundTask;
    protected Invoice $invoice;
    protected InvoiceDetail $invoiceDetail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->seedTestData();
    }

    /**
     * Create the full chain of test data matching the user's real scenario:
     *
     * Original Task (13644): total=335.950, status=issued
     * Refund Task (13830):   total=289.600, status=refund, original_task_id=13644
     * Invoice:               status=paid, task_price=340.000, markup_price=4.050
     *
     * Expected results:
     *   Original Task Cost       = 335.950
     *   Original Task Profit     = 4.050
     *   Original Selling Price   = 340.000
     *   Supplier Charges         = 335.950 - 289.600 = 46.350
     *   Refund Task (Cost Price) = 289.600
     */
    private function seedTestData(): void
    {
        $this->companyUser = User::factory()->create(['role_id' => Role::COMPANY]);
        $this->company = Company::factory()->create(['user_id' => $this->companyUser->id]);

        $roleCompany = Role::create(['name' => 'company', 'guard_name' => 'web', 'company_id' => $this->company->id]);
        $this->companyUser->assignRole($roleCompany);
        $roleCompany->givePermissionTo('view invoice');

        $this->branch = Branch::factory()->create([
            'user_id' => $this->companyUser->id,
            'company_id' => $this->company->id,
        ]);

        $agentType = AgentType::create(['name' => 'Salary']);

        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        $this->agent = Agent::factory()->create([
            'user_id' => $agentUser->id,
            'branch_id' => $this->branch->id,
            'type_id' => $agentType->id,
            'commission' => 0,
        ]);

        $this->client = Client::factory()->create(['agent_id' => $this->agent->id]);
        $this->supplier = Supplier::factory()->create();

        // ── Original task (the issued ticket) ──
        $this->originalTask = Task::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'supplier_status' => 'issued',
            'reference' => '6504367454',
            'original_reference' => '6504367454',
            'passenger_name' => 'ALAWADHY/HUSEEN MR',
            'price' => 175.000,
            'tax' => 160.600,
            'surcharge' => 0.000,
            'penalty_fee' => 0.000,
            'total' => 335.950,
            'ticket_number' => 'T-K157-6504367454',
            'refund_charge' => 0.000,
        ]);

        // ── Invoice for original task (Paid) ──
        $this->invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-2026-01298',
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'status' => 'paid',
            'amount' => 340.000,
            'sub_amount' => 340.000,
            'currency' => 'KWD',
            'payment_type' => 'Credit',
        ]);

        $this->invoiceDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'task_id' => $this->originalTask->id,
            'task_description' => 'Flight KWI to DOH',
            'task_price' => 340.000,
            'supplier_price' => 335.950,
            'markup_price' => 4.050,
        ]);

        // ── Refund task (what airline refunded) ──
        $this->refundTask = Task::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'type' => 'flight',
            'status' => 'refund',
            'supplier_status' => 'refund',
            'original_task_id' => $this->originalTask->id,
            'reference' => '6504367454',
            'original_reference' => '6504367454',
            'passenger_name' => 'ALAWADHY/HUSEEN MR',
            'price' => 175.000,
            'tax' => 160.600,
            'surcharge' => 0.000,
            'penalty_fee' => 46.000,
            'total' => 289.600,
            'ticket_number' => 'R-157-6504367454',
            'refund_charge' => 0.000,
        ]);

        // ── Accounts needed for handlePaidRefund ──
        $liabilities = Account::create([
            'name' => 'Liabilities',
            'company_id' => $this->company->id,
            'root_id' => 2,
            'level' => 0,
            'is_group' => 1,
            'disabled' => 0,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
            'currency' => 'KWD',
        ]);

        $refundPayable = Account::create([
            'name' => 'Refund Payable',
            'company_id' => $this->company->id,
            'parent_id' => $liabilities->id,
            'root_id' => 2,
            'level' => 1,
            'is_group' => 1,
            'disabled' => 0,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
            'currency' => 'KWD',
        ]);
    }

    /**
     * Test that the create page renders correct field values for a paid invoice refund.
     *
     * With your data:
     *   Original Task Cost = 335.950 (sourceTask->total)
     *   Refund Task Cost   = 289.600 (real task->total before controller overwrite)
     *   Supplier Charges   = 335.950 - 289.600 = 46.350
     *   Refund Task (Cost) = 289.600
     */
    public function test_create_page_shows_correct_supplier_charges_and_refund_cost(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->get(route('refunds.create', ['task_ids' => $this->refundTask->id]));

        $response->assertStatus(200);

        // Supplier Charges should be 335.950 - 289.600 = 46.350
        $response->assertSee('46.350');

        // Refund Task (Cost Price) should be 289.600 (the actual refund task cost)
        $response->assertSee('289.600');

        // Original Task (Cost Price) = task_price - markup = 340.000 - 4.050 = 335.950
        $response->assertSee('335.950');

        // Original Task Profit = markup_price = 4.050
        $response->assertSee('4.050');

        // Original Selling Price = task_price = 340.000
        $response->assertSee('340.000');
    }

    /**
     * Test that the store endpoint processes a paid-invoice refund correctly.
     *
     * Expected with supplier_charge=46.350, new_task_profit=9.600:
     *   refund_fee_to_client   = 46.350 + 9.600 = 55.950
     *   total_refund_to_client = 289.600 - 9.600 = 280.000
     */
    public function test_store_paid_refund_creates_correct_records(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->post(route('refunds.store'), [
                'date' => now()->toDateString(),
                'method' => 'Credit',
                'client_id' => $this->client->id,
                'remarks' => 'Test refund',
                'remarks_internal' => null,
                'reason' => 'Customer requested',
                'tasks' => [
                    [
                        'task_id' => $this->refundTask->id,
                        'original_invoice_price' => 340.000,
                        'original_task_cost' => 335.950,
                        'original_task_profit' => 4.050,
                        'refund_fee_to_client' => 55.950,
                        'supplier_charge' => 46.350,
                        'new_task_profit' => 9.600,
                        'total_refund_to_client' => 280.000,
                        'remarks' => null,
                    ],
                ],
            ]);

        // Should redirect or return success
        $response->assertRedirect(route('refunds.index'));

        // Verify Refund record was created
        $refund = Refund::latest()->first();
        $this->assertNotNull($refund);
        $this->assertEquals('completed', $refund->status);
        $this->assertEqualsWithDelta(55.950, $refund->total_refund_amount, 0.001);
        $this->assertEqualsWithDelta(46.350, $refund->total_refund_charge, 0.001);
        $this->assertEqualsWithDelta(280.000, $refund->total_nett_refund, 0.001);

        // Verify RefundDetail was created with correct amounts
        $detail = RefundDetail::where('refund_id', $refund->id)->first();
        $this->assertNotNull($detail);
        $this->assertEquals($this->refundTask->id, $detail->task_id);
        $this->assertEqualsWithDelta(340.000, $detail->original_invoice_price, 0.001);
        $this->assertEqualsWithDelta(335.950, $detail->original_task_cost, 0.001);
        $this->assertEqualsWithDelta(4.050, $detail->original_task_profit, 0.001);
        $this->assertEqualsWithDelta(55.950, $detail->refund_fee_to_client, 0.001);
        $this->assertEqualsWithDelta(46.350, $detail->supplier_charge, 0.001);
        $this->assertEqualsWithDelta(9.600, $detail->new_task_profit, 0.001);
        $this->assertEqualsWithDelta(280.000, $detail->total_refund_to_client, 0.001);

        // Verify Credit was issued to client for the total_nett_refund amount
        $credit = Credit::where('refund_id', $refund->id)->first();
        $this->assertNotNull($credit);
        $this->assertEquals($this->client->id, $credit->client_id);
        $this->assertEqualsWithDelta(280.000, $credit->amount, 0.001);
        $this->assertEquals('Refund', $credit->type);
    }

    /**
     * Test that supplier charges = original cost - refund cost (not using overwritten total).
     *
     * This is the specific bug: the old blade used (task_price - markup - task.total)
     * which with raw data gives 335.950 - 289.600 = 46.350.
     * The new controller overwrites task.total to 50.400, so without the fix
     * supplier charges would wrongly become 335.950 - 50.400 = 285.550.
     */
    public function test_supplier_charge_uses_real_refund_task_cost_not_overwritten(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->get(route('refunds.create', ['task_ids' => $this->refundTask->id]));

        $response->assertStatus(200);

        // The WRONG value (285.550) should NOT appear — it comes from the overwritten total
        $response->assertDontSee('285.550');

        // The CORRECT supplier charge (46.350) should appear
        $response->assertSee('46.350');

        // The WRONG refund task cost (50.400) should NOT appear
        $response->assertDontSee('50.400');

        // The CORRECT refund task cost (289.600) should appear
        $response->assertSee('289.600');
    }

    /**
     * Test that a refund with zero new profit auto-fills correctly.
     *
     * When new_profit = 0:
     *   refund_fee = supplier_charge = 46.350
     *   total_refund_to_client = refund_task_cost - 0 = 289.600
     */
    public function test_store_zero_profit_refund(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->post(route('refunds.store'), [
                'date' => now()->toDateString(),
                'method' => 'Credit',
                'client_id' => $this->client->id,
                'remarks' => null,
                'remarks_internal' => null,
                'reason' => null,
                'tasks' => [
                    [
                        'task_id' => $this->refundTask->id,
                        'original_invoice_price' => 340.000,
                        'original_task_cost' => 335.950,
                        'original_task_profit' => 4.050,
                        'refund_fee_to_client' => 46.350,
                        'supplier_charge' => 46.350,
                        'new_task_profit' => 0.000,
                        'total_refund_to_client' => 289.600,
                        'remarks' => null,
                    ],
                ],
            ]);

        $response->assertRedirect(route('refunds.index'));

        $refund = Refund::latest()->first();
        $this->assertNotNull($refund);
        $this->assertEqualsWithDelta(46.350, $refund->total_refund_amount, 0.001);
        $this->assertEqualsWithDelta(289.600, $refund->total_nett_refund, 0.001);

        $credit = Credit::where('refund_id', $refund->id)->first();
        $this->assertNotNull($credit);
        $this->assertEqualsWithDelta(289.600, $credit->amount, 0.001);
    }
}
