<?php

namespace Tests\Feature\Invoice;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoiceSequence;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCreationTest extends TestCase
{
    use RefreshDatabase;

    protected User $companyUser;
    protected Company $company;
    protected Branch $branch;
    protected Agent $agent;
    protected Client $client;
    protected Supplier $supplier;
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

        // Create task
        $this->task = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $this->supplier->id,
            'total' => 100.00,
            'status' => 'issued',
            'type' => 'flight',
        ]);

        // Create invoice sequence
        InvoiceSequence::create([
            'company_id' => $this->company->id,
            'current_sequence' => 1,
        ]);
    }

    public function test_company_can_create_invoice_with_single_task(): void
    {
        $payload = [
            'invoiceNumber' => 'INV-2026-00001',
            'invdate' => '2026-03-04',
            'duedate' => '2026-03-09',
            'currency' => 'KWD',
            'subTotal' => 150.00,
            'clientId' => $this->client->id,
            'agentId' => $this->agent->id,
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 150.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $this->task->total,
                ],
            ],
        ];

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Invoice created successfully!',
            ]);

        // Verify invoice was created in DB
        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'INV-2026-00001',
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'currency' => 'KWD',
            'status' => 'unpaid',
        ]);

        // Verify invoice detail was created
        $this->assertDatabaseHas('invoice_details', [
            'invoice_number' => 'INV-2026-00001',
            'task_id' => $this->task->id,
            'task_price' => 150.00,
            'supplier_price' => $this->task->total,
        ]);
    }

    public function test_company_can_create_invoice_with_multiple_tasks(): void
    {
        $task2 = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $this->supplier->id,
            'total' => 200.00,
            'status' => 'issued',
            'type' => 'hotel',
        ]);

        $payload = [
            'invoiceNumber' => 'INV-2026-00002',
            'invdate' => '2026-03-04',
            'duedate' => '2026-03-09',
            'currency' => 'KWD',
            'subTotal' => 400.00,
            'clientId' => $this->client->id,
            'agentId' => $this->agent->id,
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 150.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $this->task->total,
                ],
                [
                    'id' => $task2->id,
                    'description' => $task2->reference,
                    'invprice' => 250.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $task2->total,
                ],
            ],
        ];

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload);

        $response->assertOk()
            ->assertJson(['success' => true]);

        // Both invoice details should exist
        $this->assertDatabaseCount('invoice_details', 2);

        $invoice = Invoice::where('invoice_number', 'INV-2026-00002')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(400.00, $invoice->amount);
        $this->assertEquals(2, $invoice->invoiceDetails()->count());
    }

    public function test_invoice_creation_calculates_markup_correctly(): void
    {
        $payload = [
            'invoiceNumber' => 'INV-2026-00003',
            'invdate' => '2026-03-04',
            'duedate' => '2026-03-09',
            'currency' => 'KWD',
            'subTotal' => 200.00,
            'clientId' => $this->client->id,
            'agentId' => $this->agent->id,
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 200.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $this->task->total,
                ],
            ],
        ];

        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload);

        $detail = InvoiceDetail::where('invoice_number', 'INV-2026-00003')->first();
        $expectedMarkup = 200.00 - $this->task->total;

        $this->assertEquals($expectedMarkup, $detail->markup_price);
        $this->assertEquals($expectedMarkup, $detail->profit);
        $this->assertFalse((bool) $detail->paid);
    }

    public function test_invoice_creation_increments_sequence(): void
    {
        $sequenceBefore = InvoiceSequence::where('company_id', $this->company->id)->first()->current_sequence;

        $payload = [
            'invoiceNumber' => 'INV-2026-00004',
            'invdate' => '2026-03-04',
            'duedate' => null,
            'currency' => 'KWD',
            'subTotal' => 150.00,
            'clientId' => $this->client->id,
            'agentId' => $this->agent->id,
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 150.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $this->task->total,
                ],
            ],
        ];

        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload);

        $sequenceAfter = InvoiceSequence::where('company_id', $this->company->id)->first()->current_sequence;
        $this->assertEquals($sequenceBefore + 1, $sequenceAfter);
    }

    public function test_invoice_creation_fails_without_tasks(): void
    {
        $payload = [
            'invoiceNumber' => 'INV-2026-00005',
            'invdate' => '2026-03-04',
            'duedate' => '2026-03-09',
            'currency' => 'KWD',
            'subTotal' => 0,
            'clientId' => $this->client->id,
            'agentId' => $this->agent->id,
            'tasks' => [],
        ];

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload);

        $response->assertStatus(422); // Validation error
    }

    public function test_invoice_creation_fails_without_invoice_number(): void
    {
        $payload = [
            'invdate' => '2026-03-04',
            'duedate' => '2026-03-09',
            'currency' => 'KWD',
            'subTotal' => 150.00,
            'clientId' => $this->client->id,
            'agentId' => $this->agent->id,
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 150.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $this->task->total,
                ],
            ],
        ];

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload);

        $response->assertStatus(422);
    }

    public function test_invoice_creation_fails_without_client(): void
    {
        $payload = [
            'invoiceNumber' => 'INV-2026-00006',
            'invdate' => '2026-03-04',
            'duedate' => '2026-03-09',
            'currency' => 'KWD',
            'subTotal' => 150.00,
            'agentId' => $this->agent->id,
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 150.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $this->task->total,
                ],
            ],
        ];

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload);

        $response->assertStatus(422);
    }

    public function test_invoice_creation_fails_without_agent(): void
    {
        $payload = [
            'invoiceNumber' => 'INV-2026-00007',
            'invdate' => '2026-03-04',
            'duedate' => '2026-03-09',
            'currency' => 'KWD',
            'subTotal' => 150.00,
            'clientId' => $this->client->id,
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 150.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $this->task->total,
                ],
            ],
        ];

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload);

        $response->assertStatus(422);
    }

    public function test_invoice_creation_fails_with_invalid_agent(): void
    {
        $payload = [
            'invoiceNumber' => 'INV-2026-00008',
            'invdate' => '2026-03-04',
            'duedate' => '2026-03-09',
            'currency' => 'KWD',
            'subTotal' => 150.00,
            'clientId' => $this->client->id,
            'agentId' => 99999, // Non-existent agent
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 150.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $this->task->total,
                ],
            ],
        ];

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload);

        $response->assertJson(['success' => false]);
    }

    public function test_invoice_creation_with_task_remark_and_notes(): void
    {
        $payload = [
            'invoiceNumber' => 'INV-2026-00009',
            'invdate' => '2026-03-04',
            'duedate' => '2026-03-09',
            'currency' => 'KWD',
            'subTotal' => 150.00,
            'clientId' => $this->client->id,
            'agentId' => $this->agent->id,
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 150.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $this->task->total,
                    'remark' => 'VIP client - priority handling',
                    'note' => 'Client requested window seat',
                ],
            ],
        ];

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('invoice_details', [
            'task_id' => $this->task->id,
            'task_remark' => 'VIP client - priority handling',
            'client_notes' => 'Client requested window seat',
        ]);
    }

    public function test_invoice_creation_with_different_currencies(): void
    {
        $currencies = ['KWD', 'USD', 'EUR'];

        foreach ($currencies as $index => $currency) {
            $task = Task::factory()->create([
                'company_id' => $this->company->id,
                'agent_id' => $this->agent->id,
                'client_id' => $this->client->id,
                'supplier_id' => $this->supplier->id,
                'total' => 100.00,
            ]);

            $payload = [
                'invoiceNumber' => "INV-2026-CUR-{$index}",
                'invdate' => '2026-03-04',
                'duedate' => '2026-03-09',
                'currency' => $currency,
                'subTotal' => 150.00,
                'clientId' => $this->client->id,
                'agentId' => $this->agent->id,
                'tasks' => [
                    [
                        'id' => $task->id,
                        'description' => $task->reference,
                        'invprice' => 150.00,
                        'supplier_id' => $this->supplier->id,
                        'client_id' => $this->client->id,
                        'agent_id' => $this->agent->id,
                        'total' => $task->total,
                    ],
                ],
            ];

            $response = $this->actingAs($this->companyUser)
                ->postJson(route('invoice.store'), $payload);

            $response->assertOk()->assertJson(['success' => true]);

            $this->assertDatabaseHas('invoices', [
                'invoice_number' => "INV-2026-CUR-{$index}",
                'currency' => $currency,
            ]);
        }
    }

    public function test_invoice_creation_without_due_date(): void
    {
        $payload = [
            'invoiceNumber' => 'INV-2026-NODUE',
            'invdate' => '2026-03-04',
            'duedate' => null,
            'currency' => 'KWD',
            'subTotal' => 150.00,
            'clientId' => $this->client->id,
            'agentId' => $this->agent->id,
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 150.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $this->task->total,
                ],
            ],
        ];

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'INV-2026-NODUE',
            'due_date' => null,
        ]);
    }

    public function test_invoice_status_defaults_to_unpaid(): void
    {
        $payload = [
            'invoiceNumber' => 'INV-2026-STATUS',
            'invdate' => '2026-03-04',
            'duedate' => '2026-03-09',
            'currency' => 'KWD',
            'subTotal' => 150.00,
            'clientId' => $this->client->id,
            'agentId' => $this->agent->id,
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 150.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $this->task->total,
                ],
            ],
        ];

        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload);

        $invoice = Invoice::where('invoice_number', 'INV-2026-STATUS')->first();
        $this->assertEquals('unpaid', $invoice->status);
    }

    public function test_create_invoice_page_loads_for_company(): void
    {
        $response = $this->actingAs($this->companyUser)
            ->get(route('invoices.create'));

        $response->assertStatus(200);
        $response->assertSee('Choose Agent');
        $response->assertSee('Choose Client');
    }

    public function test_unauthenticated_user_cannot_create_invoice(): void
    {
        $payload = [
            'invoiceNumber' => 'INV-2026-UNAUTH',
            'invdate' => '2026-03-04',
            'currency' => 'KWD',
            'subTotal' => 150.00,
            'clientId' => $this->client->id,
            'agentId' => $this->agent->id,
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 150.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $this->task->total,
                ],
            ],
        ];

        $response = $this->postJson(route('invoice.store'), $payload);

        $response->assertStatus(401);
    }

    // ─── ROLE-BASED ACCESS: AGENT USER ───────────────────────────────

    public function test_agent_user_can_create_invoice_for_own_client(): void
    {
        $agentUser = $this->agent->user;

        // Give agent user proper role and permissions
        $roleAgent = Role::create(['name' => 'agent_role', 'guard_name' => 'web', 'company_id' => $this->company->id]);
        $agentUser->assignRole($roleAgent);
        $roleAgent->givePermissionTo('view invoice');
        $roleAgent->givePermissionTo('create invoice');

        $payload = [
            'invoiceNumber' => 'INV-2026-AGENT-001',
            'invdate' => '2026-03-04',
            'duedate' => '2026-03-09',
            'currency' => 'KWD',
            'subTotal' => 150.00,
            'clientId' => $this->client->id,
            'agentId' => $this->agent->id,
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 150.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $this->task->total,
                ],
            ],
        ];

        $response = $this->actingAs($agentUser)
            ->postJson(route('invoice.store'), $payload);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'INV-2026-AGENT-001',
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
        ]);
    }

    public function test_api_returns_only_agent_own_clients(): void
    {
        // Create a second agent with different clients
        $agent2User = User::factory()->create(['role_id' => Role::AGENT]);
        $agent2 = Agent::factory()->create([
            'user_id' => $agent2User->id,
            'branch_id' => $this->branch->id,
            'type_id' => $this->agent->type_id,
        ]);

        $clientOfAgent2 = Client::factory()->create([
            'agent_id' => $agent2->id,
        ]);

        // API call for agent 1 should return only agent 1's clients
        $response = $this->getJson("/api/clients/{$this->agent->id}");
        $response->assertOk();

        $clientIds = collect($response->json())->pluck('id')->toArray();
        $this->assertContains($this->client->id, $clientIds);
        $this->assertNotContains($clientOfAgent2->id, $clientIds);

        // API call for agent 2 should return only agent 2's clients
        $response2 = $this->getJson("/api/clients/{$agent2->id}");
        $response2->assertOk();

        $client2Ids = collect($response2->json())->pluck('id')->toArray();
        $this->assertContains($clientOfAgent2->id, $client2Ids);
        $this->assertNotContains($this->client->id, $client2Ids);
    }

    public function test_switching_agent_returns_different_clients(): void
    {
        // Create second agent with own client
        $agent2User = User::factory()->create(['role_id' => Role::AGENT]);
        $agent2 = Agent::factory()->create([
            'user_id' => $agent2User->id,
            'branch_id' => $this->branch->id,
            'type_id' => $this->agent->type_id,
        ]);

        $clientA = Client::factory()->create(['agent_id' => $this->agent->id]);
        $clientB = Client::factory()->create(['agent_id' => $agent2->id]);

        // Get clients for agent 1
        $response1 = $this->getJson("/api/clients/{$this->agent->id}");
        $clients1 = collect($response1->json())->pluck('id')->toArray();

        // Get clients for agent 2
        $response2 = $this->getJson("/api/clients/{$agent2->id}");
        $clients2 = collect($response2->json())->pluck('id')->toArray();

        // Agent 1 should have clientA but not clientB
        $this->assertContains($clientA->id, $clients1);
        $this->assertNotContains($clientB->id, $clients1);

        // Agent 2 should have clientB but not clientA
        $this->assertContains($clientB->id, $clients2);
        $this->assertNotContains($clientA->id, $clients2);
    }

    public function test_agent_with_no_clients_returns_404(): void
    {
        $agent2User = User::factory()->create(['role_id' => Role::AGENT]);
        $agent2 = Agent::factory()->create([
            'user_id' => $agent2User->id,
            'branch_id' => $this->branch->id,
            'type_id' => $this->agent->type_id,
        ]);

        // Agent 2 has no clients
        $response = $this->getJson("/api/clients/{$agent2->id}");
        $response->assertStatus(404);
    }

    // ─── DUPLICATE INVOICE NUMBER ────────────────────────────────────

    public function test_cannot_create_invoice_with_duplicate_number(): void
    {
        $payload = [
            'invoiceNumber' => 'INV-2026-DUP',
            'invdate' => '2026-03-04',
            'duedate' => '2026-03-09',
            'currency' => 'KWD',
            'subTotal' => 150.00,
            'clientId' => $this->client->id,
            'agentId' => $this->agent->id,
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 150.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $this->task->total,
                ],
            ],
        ];

        // First creation succeeds
        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload)
            ->assertOk();

        // Create a second task for the duplicate attempt
        $task2 = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $this->supplier->id,
            'total' => 100.00,
            'status' => 'issued',
        ]);

        $payload['tasks'][0]['id'] = $task2->id;

        // Second creation with same invoice number should fail
        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload);

        // Should either fail validation or return error
        $this->assertTrue(
            $response->status() === 422 || $response->json('success') === false,
            'Duplicate invoice number should be rejected'
        );
    }

    // ─── INVOICE AMOUNT CALCULATIONS ─────────────────────────────────

    public function test_invoice_sub_amount_equals_sum_of_task_prices(): void
    {
        $task2 = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $this->supplier->id,
            'total' => 200.00,
            'status' => 'issued',
        ]);

        $payload = [
            'invoiceNumber' => 'INV-2026-SUM',
            'invdate' => '2026-03-04',
            'duedate' => '2026-03-09',
            'currency' => 'KWD',
            'subTotal' => 350.00,
            'clientId' => $this->client->id,
            'agentId' => $this->agent->id,
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 150.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $this->task->total,
                ],
                [
                    'id' => $task2->id,
                    'description' => $task2->reference,
                    'invprice' => 200.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $task2->total,
                ],
            ],
        ];

        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload);

        $invoice = Invoice::where('invoice_number', 'INV-2026-SUM')->first();

        $this->assertEquals(350.00, $invoice->sub_amount);
        $this->assertEquals(350.00, $invoice->amount);
        $this->assertEquals(0, (float) $invoice->invoice_charge);
    }

    public function test_each_task_markup_calculated_independently(): void
    {
        $task2 = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $this->supplier->id,
            'total' => 80.00,
            'status' => 'issued',
        ]);

        $payload = [
            'invoiceNumber' => 'INV-2026-MARKUP',
            'invdate' => '2026-03-04',
            'duedate' => '2026-03-09',
            'currency' => 'KWD',
            'subTotal' => 350.00,
            'clientId' => $this->client->id,
            'agentId' => $this->agent->id,
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 200.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => 100.00,
                ],
                [
                    'id' => $task2->id,
                    'description' => $task2->reference,
                    'invprice' => 150.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => 80.00,
                ],
            ],
        ];

        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload);

        $invoice = Invoice::where('invoice_number', 'INV-2026-MARKUP')->first();
        $details = $invoice->invoiceDetails;

        // Task 1: 200 - 100 = 100 markup
        $detail1 = $details->where('task_id', $this->task->id)->first();
        $this->assertEquals(100.00, $detail1->markup_price);

        // Task 2: 150 - 80 = 70 markup
        $detail2 = $details->where('task_id', $task2->id)->first();
        $this->assertEquals(70.00, $detail2->markup_price);
    }

    public function test_invoice_detail_paid_defaults_to_false(): void
    {
        $payload = [
            'invoiceNumber' => 'INV-2026-PAID-FLAG',
            'invdate' => '2026-03-04',
            'duedate' => '2026-03-09',
            'currency' => 'KWD',
            'subTotal' => 150.00,
            'clientId' => $this->client->id,
            'agentId' => $this->agent->id,
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 150.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $this->task->total,
                ],
            ],
        ];

        $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload);

        $detail = InvoiceDetail::where('invoice_number', 'INV-2026-PAID-FLAG')->first();
        $this->assertFalse((bool) $detail->paid);
    }

    // ─── INVOICE CREATION VALIDATION EDGE CASES ──────────────────────

    public function test_invoice_creation_fails_without_currency(): void
    {
        $payload = [
            'invoiceNumber' => 'INV-2026-NOCUR',
            'invdate' => '2026-03-04',
            'duedate' => '2026-03-09',
            'subTotal' => 150.00,
            'clientId' => $this->client->id,
            'agentId' => $this->agent->id,
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 150.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $this->task->total,
                ],
            ],
        ];

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload);

        $response->assertStatus(422);
    }

    public function test_invoice_creation_fails_without_invoice_date(): void
    {
        $payload = [
            'invoiceNumber' => 'INV-2026-NODATE',
            'duedate' => '2026-03-09',
            'currency' => 'KWD',
            'subTotal' => 150.00,
            'clientId' => $this->client->id,
            'agentId' => $this->agent->id,
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    'invprice' => 150.00,
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $this->task->total,
                ],
            ],
        ];

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload);

        $response->assertStatus(422);
    }

    public function test_invoice_creation_fails_with_task_missing_invprice(): void
    {
        $payload = [
            'invoiceNumber' => 'INV-2026-NOPRICE',
            'invdate' => '2026-03-04',
            'duedate' => '2026-03-09',
            'currency' => 'KWD',
            'subTotal' => 150.00,
            'clientId' => $this->client->id,
            'agentId' => $this->agent->id,
            'tasks' => [
                [
                    'id' => $this->task->id,
                    'description' => $this->task->reference,
                    // Missing invprice
                    'supplier_id' => $this->supplier->id,
                    'client_id' => $this->client->id,
                    'agent_id' => $this->agent->id,
                    'total' => $this->task->total,
                ],
            ],
        ];

        $response = $this->actingAs($this->companyUser)
            ->postJson(route('invoice.store'), $payload);

        $response->assertStatus(422);
    }
}
