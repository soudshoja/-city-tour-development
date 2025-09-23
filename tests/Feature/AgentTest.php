<?php

namespace Tests\Feature;

use App\Http\Controllers\AgentController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Account;
use App\Models\Client;
use App\Models\Task;
use App\Models\Invoice;
use App\Models\AgentMonthlyCommissions;
use App\Models\InvoiceDetail;

class AgentTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $adminUser;
    protected $companyUser;
    protected $branchUser;
    protected $agentUser;
    protected $company;
    protected $branch;
    protected $agent;
    protected $agentType;

    protected function setUp(): void
    {
        parent::setUp();

        // Create agent types first
        $this->agentType = AgentType::create([
            'id' => 1,
            'name' => 'Commission',
        ]);

        AgentType::create(['id' => 2, 'name' => 'Salary']);
        AgentType::create(['id' => 3, 'name' => 'Both-A']);
        AgentType::create(['id' => 4, 'name' => 'Both-B']);



        // Create admin user
        $this->adminUser = User::factory()->create([
            'role_id' => Role::ADMIN,
            'name' => 'Admin User',
            'email' => 'admin@test.com'
        ]);

        // Create company user
        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@test.com'
        ]);

        // Create test company
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'status' => 1,
            'user_id' => $this->companyUser->id
        ]);

        // Update company user with company_id
        $this->companyUser->update(['company_id' => $this->company->id]);

        Role::create(['id' => Role::ADMIN, 'name' => 'Admin', 'guard_name' => 'web', 'company_id' => $this->company->id]);
        Role::create(['id' => Role::COMPANY, 'name' => 'Company', 'guard_name' => 'web', 'company_id' => $this->company->id]);
        Role::create(['id' => Role::BRANCH, 'name' => 'Branch', 'guard_name' => 'web', 'company_id' => $this->company->id]);
        Role::create(['id' => Role::AGENT, 'name' => 'Agent', 'guard_name' => 'web', 'company_id' => $this->company->id]);
        Role::create(['id' => Role::ACCOUNTANT, 'name' => 'Accountant', 'guard_name' => 'web', 'company_id' => $this->company->id]);
        Role::create(['id' => Role::CLIENT, 'name' => 'Client', 'guard_name' => 'web', 'company_id' => $this->company->id]);

        // Create branch user
        $this->branchUser = User::factory()->create([
            'role_id' => Role::BRANCH,
            'name' => 'Branch User',
            'email' => 'branch@test.com'
        ]);

        // Create test branch
        $this->branch = Branch::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Branch',
            'user_id' => $this->branchUser->id
        ]);

        // Update branch user with branch_id
        $this->branchUser->update(['branch_id' => $this->branch->id]);

        // Create root Assets account
        $rootAccount = Account::create([
            'id' => 1,
            'name' => 'Assets',
            'level' => 0,
            'actual_balance' => 0.00,
            'budget_balance' => 0.00,
            'variance' => 0.00,
            'parent_id' => null,
            'root_id' => null,
            'company_id' => $this->company->id,
            'code' => 'ASSETS',
        ]);

        // Create branch account (this creates the relationship with branch)
        $parentAccount = Account::create([
            'name' => 'Branch Account',
            'level' => 1,
            'actual_balance' => 0.00,
            'budget_balance' => 0.00,
            'variance' => 0.00,
            'parent_id' => $rootAccount->id,
            'root_id' => $rootAccount->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id, // This creates the branch->account relationship
            'code' => 'BRANCH-001',
        ]);

        // Create Agent Salaries account for salary tests
        Account::create([
            'name' => 'Agent Salaries',
            'company_id' => $this->company->id,
            'level' => 2,
            'actual_balance' => 0.00,
            'budget_balance' => 0.00,
            'variance' => 0.00,
            'parent_id' => $parentAccount->id,
            'root_id' => $rootAccount->id,
            'code' => 'SAL-001',
        ]);

        // Create agent user
        $this->agentUser = User::factory()->create([
            'role_id' => Role::AGENT,
            'name' => 'Agent User',
            'email' => 'agent@test.com'
        ]);

        // Create test agent
        $this->agent = Agent::factory()->create([
            'user_id' => $this->agentUser->id,
            'branch_id' => $this->branch->id,
            'name' => 'Test Agent',
            'email' => 'agent@test.com',
            'type_id' => $this->agentType->id,
            'commission' => 0.15,
            'salary' => 1000.00,
            'target' => 5000.00
        ]);

    }

    public function test_admin_can_view_all_agents()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('agents.index'));

        $response->assertStatus(200);
        $response->assertViewIs('agents.index');
        $response->assertViewHas('agents');
        $response->assertViewHas('agentCount');
        $response->assertSee('Test Agent');
    }

    public function test_company_user_can_view_their_company_agents()
    {
        $this->actingAs($this->companyUser);

        $response = $this->get(route('agents.index'));

        $response->assertStatus(200);
        $response->assertViewIs('agents.index');
        $response->assertViewHas('agents');
        $response->assertSee('Test Agent');
    }

    public function test_branch_user_can_view_their_branch_agents()
    {
        $this->actingAs($this->branchUser);

        $response = $this->get(route('agents.index'));

        $response->assertStatus(200);
        $response->assertViewIs('agents.index');
        $response->assertViewHas('agents');
        $response->assertSee('Test Agent');
    }

    public function test_agents_can_be_searched()
    {
        $this->actingAs($this->adminUser);

        // Create another agent
        $anotherAgent = Agent::factory()->create([
            'user_id' => User::factory()->create(['role_id' => Role::AGENT])->id,
            'branch_id' => $this->branch->id,
            'name' => 'Another Agent',
            'email' => 'another@test.com',
            'type_id' => $this->agentType->id,
        ]);

        $response = $this->get(route('agents.index', ['search' => 'Test Agent']));

        $response->assertStatus(200);
        $response->assertSee('Test Agent');
        $response->assertDontSee('Another Agent');
    }

    public function test_can_view_agent_details()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('agents.show', $this->agent->id));

        $response->assertStatus(200);
        $response->assertViewIs('agents.agentsShow');
        $response->assertViewHas('agent');
        $response->assertSee('Test Agent');
    }

    public function test_can_create_new_agent()
    {
        $this->actingAs($this->adminUser);

        $userData = [
            'name' => 'New Agent',
            'email' => 'newagent@test.com',
            'password' => 'password123',
            'dial_code' => '+1',
            'phone' => '1234567890',
            'branch_id' => $this->branch->id,
            'type_id' => $this->agentType->id,
            'amadeus_id' => 'AMD123',
            'serial_number' => 'SN001',
            'account_type' => 'agent'
        ];

        $response = $this->post(route('agents.store'), $userData);

        // Debug if creation failed
        if ($response->getSession()->has('error')) {
            $this->fail('Agent creation failed: ' . $response->getSession()->get('error'));
        }

        // Agent creation should succeed
        $response->assertRedirect(route('agents.index'));
        $response->assertSessionHas('success', 'Agent registered successfully');
        
        // Check if agent was actually created
        $this->assertDatabaseHas('agents', [
            'name' => 'New Agent',
            'email' => 'newagent@test.com',
            'branch_id' => $this->branch->id,
            'type_id' => $this->agentType->id,
            'amadeus_id' => 'AMD123'
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'New Agent',
            'email' => 'newagent@test.com',
            'role_id' => Role::AGENT
        ]);
    }

    public function test_agent_creation_requires_valid_data()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post(route('agents.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'phone', 'branch_id', 'type_id']);
    }

    public function test_can_update_agent_details()
    {
        $this->actingAs($this->adminUser);

        $updateData = [
            'name' => 'Updated Agent Name',
            'email' => 'updated@test.com',
            'password' => 'newpassword123',
            'salary' => 1500.00,
            'commission' => 0.20,
            'target' => 6000.00
        ];

        $response = $this->put(route('agents.update', $this->agent->id), $updateData);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Agent updated successfully');

        $this->assertDatabaseHas('agents', [
            'id' => $this->agent->id,
            'name' => 'Updated Agent Name',
            'salary' => 1500.00,
            'commission' => 0.20,
            'target' => 6000.00
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->agentUser->id,
            'name' => 'Updated Agent Name',
            'email' => 'updated@test.com'
        ]);
    }

    public function test_can_update_agent_commission()
    {
        $this->actingAs($this->adminUser);

        $response = $this->put(route('agents.update-commission', $this->agent->id), [
            'commission' => 25 // 25%
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Agent commission updated successfully');

        $this->assertDatabaseHas('agents', [
            'id' => $this->agent->id,
            'commission' => 0.25 // Should be stored as decimal
        ]);
    }

    public function test_commission_update_requires_valid_data()
    {
        $this->actingAs($this->adminUser);

        $response = $this->put(route('agents.update-commission', $this->agent->id), [
            'commission' => -5 // Invalid negative commission
        ]);

        $response->assertSessionHasErrors(['commission']);
    }

    // public function test_can_calculate_monthly_summary_for_commission_agent()
    // {
    //     // Use the Both-A agent type created in setUp
    //     $bothAAgentType = AgentType::where('name', 'Both-A')->first();
    //     $this->assertNotNull($bothAAgentType, 'Both-A agent type should exist');
        
    //     $agent = Agent::factory()->create([
    //         'user_id' => User::factory()->create(['role_id' => Role::AGENT])->id,
    //         'branch_id' => $this->branch->id,
    //         'type_id' => $bothAAgentType->id, // Both-A type
    //         'commission' => 0.15,
    //         'salary' => 1000.00
    //     ]);

    //     // Create test invoice with details
    //     $client = Client::factory()->create(['agent_id' => $agent->id]);

    //     //create task
    //     $task = Task::factory()->create([
    //         'agent_id' => $agent->id,
    //         'company_id' => $this->branch->company->id,
    //         'price' => 500.00
    //     ]);

    //     $invoice = Invoice::factory()->create([
    //         'agent_id' => $agent->id,
    //         'client_id' => $client->id,
    //         'amount' => 1000.00,
    //         'created_at' => now()
    //     ]);

    //     // Create invoice detail using factory
    //     InvoiceDetail::factory()->create([
    //         'task_id' => $task->id,
    //         'invoice_id' => $invoice->id,
    //         'markup_price' => 500.00,
    //     ]);

    //     $controller = new AgentController();
    //     $summary = $controller->calculateMonthlySummary($agent, now()->startOfMonth());

    //     $this->assertEquals(1075.00, $summary['commission']); // (500 * 0.15) + 1000 = 75 + 1000 = 1075
    //     $this->assertEquals(500.00, $summary['profit']);
    // }

    public function test_can_retrieve_agent_tasks()
    {
        $this->actingAs($this->adminUser);

        // Create test tasks
        Task::factory()->count(3)->create([
            'agent_id' => $this->agent->id,
            'company_id' => $this->agent->branch->company->id
        ]);

        $response = $this->get(route('agents.tasks', $this->agent->id));

        $response->assertStatus(200);
        $response->assertJsonStructure(['tasks']);
    }

    public function test_can_retrieve_agent_clients()
    {
        $this->actingAs($this->adminUser);

        // Create test clients
        Client::factory()->count(2)->create(['agent_id' => $this->agent->id]);

        $response = $this->get(route('agents.clients', $this->agent->id));

        $response->assertStatus(200);
        $response->assertJsonStructure(['clients']);
    }

    public function test_can_retrieve_agent_invoices()
    {
        $this->actingAs($this->adminUser);

        // Create test invoices
        $client = Client::factory()->create(['agent_id' => $this->agent->id]);
        Invoice::factory()->count(2)->create([
            'agent_id' => $this->agent->id,
            'client_id' => $client->id
        ]);

        $response = $this->get(route('agents.invoices', $this->agent->id));

        $response->assertStatus(200);
        $response->assertJsonStructure(['invoices']);
    }

    public function test_agent_show_page_displays_correct_statistics()
    {
        $this->actingAs($this->adminUser);

        // Create test data
        $client = Client::factory()->create(['agent_id' => $this->agent->id]);

        // Create tasks
        Task::factory()->count(3)->create([
            'agent_id' => $this->agent->id,
            'company_id' => $this->agent->branch->company->id
        ]);

        // Create invoices
        Invoice::factory()->create([
            'agent_id' => $this->agent->id,
            'client_id' => $client->id,
            'status' => 'paid',
            'amount' => 1000.00
        ]);

        Invoice::factory()->create([
            'agent_id' => $this->agent->id,
            'client_id' => $client->id,
            'status' => 'unpaid',
            'amount' => 500.00
        ]);

        $response = $this->get(route('agents.show', $this->agent->id));

        $response->assertStatus(200);
        $response->assertViewHas('paid', '1000.00');
        $response->assertViewHas('unpaid', '500.00');
        $response->assertViewHas('tasks');
        $response->assertViewHas('invoices');
        $response->assertViewHas('clients');
    }

    public function test_agent_type_relationships_work_correctly()
    {
        $this->assertInstanceOf(AgentType::class, $this->agent->agentType);
        $this->assertEquals('Commission', $this->agent->agentType->name);
    }

    public function test_agent_branch_and_company_relationships_work()
    {
        $this->assertInstanceOf(Branch::class, $this->agent->branch);
        $this->assertEquals('Test Branch', $this->agent->branch->name);

        $this->assertInstanceOf(Company::class, $this->agent->branch->company);
        $this->assertEquals('Test Company', $this->agent->branch->company->name);
    }

    public function test_agent_user_relationship_works()
    {
        $this->assertInstanceOf(User::class, $this->agent->user);
        $this->assertEquals('Agent User', $this->agent->user->name);
        $this->assertEquals(Role::AGENT, $this->agent->user->role_id);
    }

    public function test_monthly_commissions_can_be_stored_and_retrieved()
    {
        $monthlyCommission = AgentMonthlyCommissions::create([
            'agent_id' => $this->agent->id,
            'month' => now()->month,
            'year' => now()->year,
            'salary' => 1000.00,
            'target' => 5000.00,
            'commission_rate' => 0.15,
            'total_commission' => 750.00,
            'total_profit' => 5000.00
        ]);

        $this->assertInstanceOf(Agent::class, $monthlyCommission->agent);
        $this->assertEquals($this->agent->id, $monthlyCommission->agent->id);
    }

    // public function test_salary_update_creates_journal_entries()
    // {
    //     $this->actingAs($this->adminUser);

    //     $updateData = [
    //         'name' => $this->agent->name,
    //         'email' => $this->agent->email,
    //         'password' => 'password123',
    //         'salary' => 2000.00 // Updated from 1000.00
    //     ];

    //     $response = $this->put(route('agents.update', $this->agent->id), $updateData);

    //     $response->assertRedirect();
    //     $response->assertSessionHas('success', 'Agent updated successfully');

    //     // Check if agent salary was updated
    //     $this->assertDatabaseHas('agents', [
    //         'id' => $this->agent->id,
    //         'salary' => 2000.00
    //     ]);

    //     // Check if transaction was created
    //     $this->assertDatabaseHas('transactions', [
    //         'entity_id' => $this->agent->id,
    //         'entity_type' => 'agent',
    //         'amount' => 2000.00,
    //         'transaction_type' => 'debit',
    //         'description' => 'Monthly salary adjustment for agent: ' . $this->agent->name
    //     ]);
    // }

    public function test_agent_index_shows_correct_count()
    {
        // Create additional agents
        Agent::factory()->count(3)->create([
            'user_id' => User::factory()->create(['role_id' => Role::AGENT])->id,
            'branch_id' => $this->branch->id,
            'type_id' => $this->agentType->id,
        ]);

        $this->actingAs($this->adminUser);

        $response = $this->get(route('agents.index'));

        $response->assertStatus(200);
        $response->assertViewHas('agentCount', 4); // 1 original + 3 new
    }

    public function test_pagination_works_correctly()
    {
        // Create more than 20 agents to test pagination
        Agent::factory()->count(25)->create([
            'user_id' => function () {
                return User::factory()->create(['role_id' => Role::AGENT])->id;
            },
            'branch_id' => $this->branch->id,
            'type_id' => $this->agentType->id,
        ]);

        $this->actingAs($this->adminUser);

        $response = $this->get(route('agents.index'));

        $response->assertStatus(200);
        $response->assertViewHas('agents');

        $agents = $response->viewData('agents');
        $this->assertEquals(20, $agents->perPage());
        $this->assertTrue($agents->hasPages());
    }
}