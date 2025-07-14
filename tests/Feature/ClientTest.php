<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Agent;
use App\Models\Client;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $companyUser;
    protected $agentUser;
    protected $company;
    protected $branch;
    protected $agent;

    protected function setUp(): void
    {
        parent::setUp();

        // Create necessary reference data first
        DB::table('agent_type')->insert([
            'id' => 1,
            'name' => 'Commission',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create admin user first
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

        // Create test company with company user as owner
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'status' => 1,
            'user_id' => $this->companyUser->id  // Company owned by company user
        ]);
        
        // Create account after company exists
        DB::table('accounts')->insert([
            'id' => 1,
            'name' => 'Test Account',
            'level' => 1,
            'actual_balance' => 0.00,
            'budget_balance' => 0.00,
            'variance' => 0.00,
            'parent_id' => null,
            'company_id' => $this->company->id,
            'reference_id' => null,
            'code' => 'TEST001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create branch user
        $branchUser = User::factory()->create([
            'role_id' => Role::BRANCH,
            'name' => 'Branch User',
            'email' => 'branch@test.com'
        ]);

        // Create test branch with valid user_id
        $this->branch = Branch::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Branch',
            'user_id' => $branchUser->id
        ]);

        // Create agent user
        $this->agentUser = User::factory()->create([
            'role_id' => Role::AGENT,
            'name' => 'Agent User',
            'email' => 'agent@test.com'
        ]);

        // Create test agent
        $this->agent = Agent::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Test Agent',
            'email' => 'agent@test.com',
            'user_id' => $this->agentUser->id,
            'account_id' => 1,
            'type_id' => 1
        ]);

        // Create some test clients
        Client::factory()->count(3)->create([
            'agent_id' => $this->agent->id
        ]);
    }

    public function test_admin_can_access_client_index()
    {
        $response = $this->actingAs($this->adminUser)->get('/clients');

        $response->assertStatus(200);
        $response->assertViewIs('clients.index');
        $response->assertViewHas(['agent', 'clients', 'clientsCount']);
    }

    public function test_company_user_can_access_client_index()
    {
        $response = $this->actingAs($this->companyUser)->get('/clients');

        $response->assertStatus(200);
        $response->assertViewIs('clients.index');
        $response->assertViewHas(['agent', 'clients', 'clientsCount']);
    }

    public function test_agent_can_access_client_index()
    {
        $response = $this->actingAs($this->agentUser)->get('/clients');

        $response->assertStatus(200);
        $response->assertViewIs('clients.index');
        $response->assertViewHas(['agent', 'clients', 'clientsCount']);
    }

    public function test_unauthenticated_user_redirected_to_login()
    {
        $response = $this->get('/clients');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_admin_sees_all_clients()
    {
        // Create clients for different agents
        $anotherAgentUser = User::factory()->create([
            'role_id' => Role::AGENT,
            'name' => 'Another Agent User',
            'email' => 'another.agent@test.com'
        ]);
        
        $anotherAgent = Agent::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Another Agent',
            'user_id' => $anotherAgentUser->id,
            'account_id' => 1,
            'type_id' => 1
        ]);
        
        Client::factory()->count(2)->create([
            'agent_id' => $anotherAgent->id
        ]);

        $response = $this->actingAs($this->adminUser)->get('/clients');

        $clients = $response->viewData('clients');
        $this->assertGreaterThanOrEqual(5, $clients->count()); // At least 5 clients total
    }

    public function test_company_user_sees_only_company_clients()
    {
        // Create another company with its own clients
        $anotherCompanyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Another Company User',
            'email' => 'another.company@test.com'
        ]);
        
        $anotherCompany = Company::factory()->create([
            'user_id' => $anotherCompanyUser->id
        ]);
        
        $anotherBranchUser = User::factory()->create([
            'role_id' => Role::BRANCH,
            'name' => 'Another Branch User',
            'email' => 'another.branch@test.com'
        ]);
        
        $anotherBranch = Branch::factory()->create([
            'company_id' => $anotherCompany->id,
            'user_id' => $anotherBranchUser->id
        ]);
        
        $anotherAgentUser = User::factory()->create([
            'role_id' => Role::AGENT,
            'name' => 'Another Agent User',
            'email' => 'another.agent2@test.com'
        ]);
        
        $anotherAgent = Agent::factory()->create([
            'branch_id' => $anotherBranch->id,
            'user_id' => $anotherAgentUser->id,
            'account_id' => 1,
            'type_id' => 1
        ]);
        
        Client::factory()->count(2)->create([
            'agent_id' => $anotherAgent->id
        ]);

        $response = $this->actingAs($this->companyUser)->get('/clients');

        $clients = $response->viewData('clients');
        // Should only see clients from their company's agents
        foreach ($clients as $client) {
            $this->assertEquals($this->company->id, $client->agent->branch->company_id);
        }
    }

    public function test_agent_sees_only_their_clients()
    {
        $response = $this->actingAs($this->agentUser)->get('/clients');

        $clients = $response->viewData('clients');
        $this->assertEquals(3, $clients->count()); // Only their 3 clients

        foreach ($clients as $client) {
            $this->assertEquals($this->agent->id, $client->agent_id);
        }
    }

    public function test_client_creation_with_valid_data()
    {
        $clientData = [
            'name' => 'Test Client',
            'email' => 'client@test.com',
            'dial_code' => '+965',
            'phone' => '12345678',
            'agent_id' => $this->agent->id,
            'civil_no' => '123456789',
            'address' => '123 Test Street'
        ];

        $response = $this->actingAs($this->adminUser)
                         ->post('/clients', $clientData);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('clients', [
            'name' => 'Test Client',
            'email' => 'client@test.com',
            'agent_id' => $this->agent->id
        ]);
    }

    public function test_client_creation_validation_fails_with_invalid_data()
    {
        $invalidData = [
            'name' => '', // Required field missing
            'email' => 'invalid-email', // Invalid email format
            'dial_code' => '', // Required field missing
            'phone' => '', // Required field missing
            'agent_id' => 999, // Non-existent agent
            'civil_no' => '', // Required field missing
        ];

        $response = $this->actingAs($this->adminUser)
                         ->post('/clients', $invalidData);

        $response->assertSessionHasErrors(['name', 'email', 'dial_code', 'phone', 'agent_id', 'civil_no']);
    }

    public function test_client_show_page_displays_correctly()
    {
        $client = Client::factory()->create([
            'agent_id' => $this->agent->id,
            'name' => 'Show Test Client'
        ]);

        $response = $this->actingAs($this->adminUser)
                         ->get("/clients/{$client->id}");

        $response->assertStatus(200);
        $response->assertViewIs('clients.new-profile');
        $response->assertViewHas('client');
        $response->assertSee('Show Test Client');
    }

    public function test_client_edit_page_displays_correctly()
    {
        $client = Client::factory()->create([
            'agent_id' => $this->agent->id,
            'name' => 'Edit Test Client'
        ]);

        $response = $this->actingAs($this->adminUser)
                         ->get("/clients/{$client->id}/edit");

        $response->assertStatus(200);
        $response->assertViewIs('clients.edit');
        $response->assertViewHas(['client', 'agents']);
        $response->assertSee('Edit Test Client');
    }

    public function test_client_update_with_valid_data()
    {
        $client = Client::factory()->create([
            'agent_id' => $this->agent->id,
            'name' => 'Original Name'
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@test.com',
            'phone' => '87654321',
            'country_code' => '+965',
            'address' => '456 Updated Street'
        ];

        $response = $this->actingAs($this->adminUser)
                         ->put("/clients/{$client->id}", $updateData);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Updated Name',
            'email' => 'updated@test.com'
        ]);
    }

    public function test_client_change_agent()
    {
        $newAgentUser = User::factory()->create([
            'role_id' => Role::AGENT,
            'name' => 'New Agent User',
            'email' => 'new.agent@test.com'
        ]);
        
        $newAgent = Agent::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'New Agent',
            'user_id' => $newAgentUser->id,
            'account_id' => 1,
            'type_id' => 1
        ]);

        $client = Client::factory()->create([
            'agent_id' => $this->agent->id
        ]);

        $response = $this->actingAs($this->adminUser)
                         ->put("/clients/{$client->id}/change-agent", [
                             'agent_id' => $newAgent->id
                         ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $client->refresh();
        $this->assertEquals($newAgent->id, $client->agent_id);
    }
}
