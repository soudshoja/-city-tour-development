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
use App\Models\ClientGroup;

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
        $response = $this->actingAs($this->adminUser)->get(route('clients.index'));

        $response->assertStatus(200);
        $response->assertViewIs('clients.index');
        $response->assertViewHas(['agent', 'clients', 'clientsCount', 'fullClients']);
    }

    public function test_company_user_can_access_client_index()
    {
        $response = $this->actingAs($this->companyUser)->get(route('clients.index'));

        $response->assertStatus(200);
        $response->assertViewIs('clients.index');
        $response->assertViewHas(['agent', 'clients', 'clientsCount', 'fullClients']);
    }

    public function test_agent_can_access_client_index()
    {
        $response = $this->actingAs($this->agentUser)->get(route('clients.index'));

        $response->assertStatus(200);
        $response->assertViewIs('clients.index');
        $response->assertViewHas(['agent', 'clients', 'clientsCount']);
    }

    public function test_unauthenticated_user_redirected_to_login()
    {
        $response = $this->get(route('clients.index'));

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

        $response = $this->actingAs($this->adminUser)->get(route('clients.index'));

        $clients = $response->viewData('clients');
        // With pagination, need to check total() method
        $this->assertGreaterThanOrEqual(5, $clients->total()); // At least 5 clients total
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $clients);
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

        $response = $this->actingAs($this->companyUser)->get(route('clients.index'));

        $clients = $response->viewData('clients');
        // Should only see clients from their company's agents (with pagination)
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $clients);
        foreach ($clients->items() as $client) {
            $this->assertEquals($this->company->id, $client->agent->branch->company_id);
        }
    }

    public function test_agent_sees_only_their_clients()
    {
        $response = $this->actingAs($this->agentUser)->get(route('clients.index'));

        $clients = $response->viewData('clients');
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $clients);
        $this->assertEquals(3, $clients->total()); // Only their 3 clients

        foreach ($clients->items() as $client) {
            $this->assertEquals($this->agent->id, $client->agent_id);
        }
    }

    public function test_client_creation_with_valid_data()
    {
        $clientData = [
            'first_name' => 'Test Client',
            'middle_name' => 'Middle',
            'last_name' => 'Client',
            'email' => 'client@test.com',
            'dial_code' => '+965',
            'phone' => '12345678',
            'agent_id' => $this->agent->id,
            'civil_no' => '123456789',
            'address' => '123 Test Street'
        ];

        $response = $this->actingAs($this->adminUser)
                         ->post(route('clients.store'), $clientData);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('clients', [
            'first_name' => 'Test Client',
            'middle_name' => 'Middle',
            'last_name' => 'Client',
            'email' => 'client@test.com',
            'agent_id' => $this->agent->id
        ]);
    }

    public function test_client_creation_validation_fails_with_invalid_data()
    {
        $invalidData = [
            'first_name' => '', // Required field missing
            'email' => 'invalid-email', // Invalid email format
            'dial_code' => '', // Required field missing
            'phone' => '', // Required field missing
            'agent_id' => 999, // Non-existent agent
            'civil_no' => '', // Required field missing
        ];

        $response = $this->actingAs($this->adminUser)
                         ->post(route('clients.store'), $invalidData);

        $response->assertSessionHasErrors(['first_name', 'email', 'dial_code', 'phone', 'agent_id', 'civil_no']);
    }

    public function test_client_show_page_displays_correctly()
    {
        $client = Client::factory()->create([
            'agent_id' => $this->agent->id,
            'first_name' => 'Show Test Client'
        ]);

        $response = $this->actingAs($this->adminUser)
                         ->get(route('clients.show', $client->id));

        $response->assertStatus(200);
        $response->assertViewIs('clients.new-profile');
        $response->assertViewHas('client');
        $response->assertSee('Show Test Client');
    }

    // public function test_client_edit_page_displays_correctly()
    // {
    //     $client = Client::factory()->create([
    //         'agent_id' => $this->agent->id,
    //         'first_name' => 'Edit Test Client'
    //     ]);

    //     $response = $this->actingAs($this->adminUser)
    //                      ->get(route('clients.edit', $client->id));

    //     $response->assertStatus(200);
    //     $response->assertViewIs('clients.edit');
    //     $response->assertViewHas(['client', 'agents']);
    //     $response->assertSee('Edit Test Client');
    // }

    public function test_client_update_with_valid_data()
    {
        $client = Client::factory()->create([
            'agent_id' => $this->agent->id,
            'first_name' => 'Original Name'
        ]);

        $updateData = [
            'first_name' => 'Updated Name',
            'email' => 'updated@test.com',
            'phone' => '87654321',
            'country_code' => '+965',
            'address' => '456 Updated Street'
        ];

        $response = $this->actingAs($this->adminUser)
                         ->put(route('clients.update', $client->id), $updateData);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'first_name' => 'Updated Name',
            'email' => 'updated@test.com'
        ]);
    }

    // public function test_client_change_agent()
    // {
    //     $newAgentUser = User::factory()->create([
    //         'role_id' => Role::AGENT,
    //         'name' => 'New Agent User',
    //         'email' => 'new.agent@test.com'
    //     ]);
        
    //     $newAgent = Agent::factory()->create([
    //         'branch_id' => $this->branch->id,
    //         'name' => 'New Agent',
    //         'user_id' => $newAgentUser->id,
    //         'account_id' => 1,
    //         'type_id' => 1
    //     ]);

    //     $client = Client::factory()->create([
    //         'agent_id' => $this->agent->id
    //     ]);

    //     $response = $this->actingAs($this->adminUser)
    //                      ->put(route('clients.change-agent', $client->id), [
    //                          'agent_id' => $newAgent->id
    //                      ]);

    //     $response->assertRedirect();
    //     $response->assertSessionHas('success');

    //     $client->refresh();
    //     $this->assertEquals($newAgent->id, $client->agent_id);
    // }

    public function test_pagination_works_correctly()
    {
        // Create enough clients to test pagination (more than 20, which is the page size)
        Client::factory()->count(25)->create([
            'agent_id' => $this->agent->id
        ]);

        // Test first page
        $response = $this->actingAs($this->adminUser)->get(route('clients.index'));
        $clients = $response->viewData('clients');
        
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $clients);
        $this->assertEquals(1, $clients->currentPage());
        $this->assertEquals(20, $clients->perPage());
        $this->assertEquals(28, $clients->total()); // 25 + 3 from setUp
        $this->assertEquals(2, $clients->lastPage());
        $this->assertCount(20, $clients->items()); // Should show 20 items on first page

        // Test second page
        $response = $this->actingAs($this->adminUser)->get(route('clients.index', ['page' => 2]));
        $clients = $response->viewData('clients');
        
        $this->assertEquals(2, $clients->currentPage());
        $this->assertCount(8, $clients->items()); // Should show remaining 8 items on second page
    }

    public function test_search_with_pagination_preserves_search_terms()
    {
        // Create clients with specific names for searching
        Client::factory()->create([
            'agent_id' => $this->agent->id,
            'first_name' => 'John Doe',
            'phone' => '12345678'
        ]);
        
        Client::factory()->create([
            'agent_id' => $this->agent->id,
            'first_name' => 'Jane Smith',
            'phone' => '87654321'
        ]);

        // Test search functionality
        $response = $this->actingAs($this->adminUser)->get(route('clients.index', ['search' => 'John']));
        
        $response->assertStatus(200);
        $clients = $response->viewData('clients');
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $clients);
        
        // Check if search results contain the searched term
        $found = false;
        foreach ($clients->items() as $client) {
            if (str_contains($client->first_name, 'John')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Search results should contain clients matching the search term');
    }

    public function test_pagination_links_preserve_search_parameters()
    {
        // Create enough clients to trigger pagination
        Client::factory()->count(25)->create([
            'agent_id' => $this->agent->id,
            'first_name' => 'Test Client'
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('clients.index', ['search' => 'Test', 'page' => 1]));
        
        $response->assertStatus(200);
        $clients = $response->viewData('clients');
        
        // Check that pagination URLs preserve search parameters
        if ($clients->hasMorePages()) {
            $nextPageUrl = $clients->nextPageUrl();
            $this->assertStringContainsString('search=Test', $nextPageUrl);
        }
        
        if (!$clients->onFirstPage()) {
            $prevPageUrl = $clients->previousPageUrl();
            $this->assertStringContainsString('search=Test', $prevPageUrl);
        }
    }

    public function test_empty_search_returns_all_clients_with_pagination()
    {
        $response = $this->actingAs($this->adminUser)->get(route('clients.index', ['search' => '']));
        
        $response->assertStatus(200);
        $clients = $response->viewData('clients');
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $clients);
        
        // Should return all clients when search is empty
        $this->assertEquals(3, $clients->total()); // 3 clients from setUp
    }

    public function test_pagination_respects_user_role_restrictions()
    {
        // Create many clients for different agents to test pagination with role restrictions
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
        
        // Create 25 clients for the original agent and 25 for the new agent
        Client::factory()->count(25)->create(['agent_id' => $this->agent->id]);
        Client::factory()->count(25)->create(['agent_id' => $anotherAgent->id]);

        // Test that agent only sees their own clients in pagination
        $response = $this->actingAs($this->agentUser)->get(route('clients.index'));
        $clients = $response->viewData('clients');
        
        $this->assertEquals(28, $clients->total()); // 25 + 3 from setUp = 28 clients for this agent
        
        // Verify all clients on current page belong to the agent
        foreach ($clients->items() as $client) {
            $this->assertEquals($this->agent->id, $client->agent_id);
        }

        // Test admin sees all clients
        $response = $this->actingAs($this->adminUser)->get(route('clients.index'));
        $clients = $response->viewData('clients');
        
        $this->assertEquals(53, $clients->total()); // 28 + 25 = 53 total clients
    }

    public function test_full_clients_variable_contains_all_clients_regardless_of_search()
    {
        // Create clients with different names
        Client::factory()->create([
            'agent_id' => $this->agent->id,
            'first_name' => 'John Doe'
        ]);
        
        Client::factory()->create([
            'agent_id' => $this->agent->id,
            'first_name' => 'Jane Smith'
        ]);
        
        Client::factory()->create([
            'agent_id' => $this->agent->id,
            'first_name' => 'Bob Wilson'
        ]);

        // Search for specific client
        $response = $this->actingAs($this->adminUser)->get(route('clients.index', ['search' => 'John']));
        
        $response->assertStatus(200);
        $clients = $response->viewData('clients');
        $fullClients = $response->viewData('fullClients');
        
        // Verify that clients (paginated) is filtered by search
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $clients);
        
        // Verify that fullClients contains all clients regardless of search
        $this->assertIsIterable($fullClients);
        $this->assertGreaterThanOrEqual(6, $fullClients->count()); // 3 from setUp + 3 created = 6 clients
        
        // Check that fullClients contains all client names
        $fullClientNames = $fullClients->pluck('first_name')->toArray();
        $this->assertContains('John Doe', $fullClientNames);
        $this->assertContains('Jane Smith', $fullClientNames);
        $this->assertContains('Bob Wilson', $fullClientNames);
    }

    public function test_full_clients_respects_user_role_restrictions_but_not_search()
    {
        // Create another agent with clients
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
        
        Client::factory()->create([
            'agent_id' => $this->agent->id,
            'first_name' => 'Agent1 Client Search'
        ]);
        
        Client::factory()->create([
            'agent_id' => $anotherAgent->id,
            'first_name' => 'Agent2 Client Search'
        ]);

        // Test agent user with search
        $response = $this->actingAs($this->agentUser)->get(route('clients.index', ['search' => 'Search']));
        
        $response->assertStatus(200);
        $fullClients = $response->viewData('fullClients');
        
        // Agent should only see their own clients in fullClients, but all of them regardless of search
        $this->assertEquals(4, $fullClients->count()); // 3 from setUp + 1 created for this agent
        
        // All clients in fullClients should belong to the agent
        foreach ($fullClients as $client) {
            $this->assertEquals($this->agent->id, $client->agent_id);
        }
        
        // Verify fullClients contains both searched and non-searched clients for the agent
        $fullClientNames = $fullClients->pluck('first_name')->toArray();
        $this->assertContains('Agent1 Client Search', $fullClientNames);
    }

    public function test_full_clients_variable_for_admin_contains_all_clients_regardless_of_search()
    {
        // Create clients across different agents
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
        
        Client::factory()->create([
            'agent_id' => $this->agent->id,
            'first_name' => 'Agent1 Searchable Client'
        ]);
        
        Client::factory()->create([
            'agent_id' => $anotherAgent->id,
            'first_name' => 'Agent2 Different Client'
        ]);

        // Admin searches for specific term
        $response = $this->actingAs($this->adminUser)->get(route('clients.index', ['search' => 'Searchable']));
        
        $response->assertStatus(200);
        $clients = $response->viewData('clients');
        $fullClients = $response->viewData('fullClients');
        
        // Verify paginated clients are filtered
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $clients);
        
        // Verify fullClients contains all clients regardless of search
        $this->assertGreaterThanOrEqual(5, $fullClients->count()); // 3 from setUp + 2 created
        
        // Check that fullClients contains clients from both agents
        $agentIds = $fullClients->pluck('agent_id')->unique()->toArray();
        $this->assertContains($this->agent->id, $agentIds);
        $this->assertContains($anotherAgent->id, $agentIds);
        
        // Check that fullClients contains both searchable and non-searchable clients
        $fullClientNames = $fullClients->pluck('first_name')->toArray();
        $this->assertContains('Agent1 Searchable Client', $fullClientNames);
        $this->assertContains('Agent2 Different Client', $fullClientNames);
    }

    public function test_full_clients_variable_for_company_user_respects_company_scope()
    {
        // Create another company with clients
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
        
        Client::factory()->create([
            'agent_id' => $anotherAgent->id,
            'first_name' => 'Other Company Client'
        ]);

        // Company user searches for term
        $response = $this->actingAs($this->companyUser)->get(route('clients.index', ['search' => 'Company']));
        
        $response->assertStatus(200);
        $fullClients = $response->viewData('fullClients');
        
        // Company user should only see clients from their company in fullClients
        $this->assertEquals(3, $fullClients->count()); // Only the 3 clients from setUp (their company)
        
        // All clients in fullClients should belong to the company's agents
        foreach ($fullClients as $client) {
            $this->assertEquals($this->company->id, $client->agent->branch->company_id);
        }
        
        // Should not contain clients from other companies
        $fullClientNames = $fullClients->pluck('first_name')->toArray();
        $this->assertNotContains('Other Company Client', $fullClientNames);
    }

    public function test_full_clients_variable_is_collection_not_paginated()
    {
        // Create many clients to ensure pagination would normally occur
        Client::factory()->count(30)->create([
            'agent_id' => $this->agent->id
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('clients.index'));
        
        $response->assertStatus(200);
        $clients = $response->viewData('clients');
        $fullClients = $response->viewData('fullClients');
        
        // Verify clients is paginated
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $clients);
        $this->assertEquals(20, $clients->perPage());
        
        // Verify fullClients is a collection (not paginated)
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $fullClients);
        $this->assertGreaterThan(20, $fullClients->count()); // Should contain all clients, not just first page
        $this->assertEquals(33, $fullClients->count()); // 3 from setUp + 30 created
    }

    public function test_can_add_client_to_group()
    {
        // Create two clients for testing group functionality
        $parentClient = Client::factory()->create([
            'agent_id' => $this->agent->id,
            'first_name' => 'Parent Client'
        ]);
        
        $childClient = Client::factory()->create([
            'agent_id' => $this->agent->id,
            'first_name' => 'Child Client'
        ]);

        $groupData = [
            'parent_client_id' => $parentClient->id,
            'child_client_id' => $childClient->id
        ];

        $response = $this->actingAs($this->adminUser)
                         ->postJson(route('clients.group.add'), $groupData);

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Client added to the group successfully'
        ]);

        // Verify the relationship exists in the database
        $this->assertDatabaseHas('client_groups', [
            'parent_client_id' => $parentClient->id,
            'child_client_id' => $childClient->id
        ]);
    }

    public function test_cannot_add_client_to_group_with_same_parent_and_child()
    {
        $client = Client::factory()->create([
            'agent_id' => $this->agent->id
        ]);

        $groupData = [
            'parent_client_id' => $client->id,
            'child_client_id' => $client->id
        ];

        $response = $this->actingAs($this->adminUser)
                         ->postJson(route('clients.group.add'), $groupData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['child_client_id']);
    }

    public function test_cannot_add_client_to_group_with_nonexistent_client()
    {
        $client = Client::factory()->create([
            'agent_id' => $this->agent->id
        ]);

        $groupData = [
            'parent_client_id' => $client->id,
            'child_client_id' => 999999 // Non-existent client
        ];

        $response = $this->actingAs($this->adminUser)
                         ->postJson(route('clients.group.add'), $groupData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['child_client_id']);
    }

    public function test_cannot_add_duplicate_client_group_relationship()
    {
        $parentClient = Client::factory()->create([
            'agent_id' => $this->agent->id
        ]);
        
        $childClient = Client::factory()->create([
            'agent_id' => $this->agent->id
        ]);

        // Create the relationship first
        \App\Models\ClientGroup::create([
            'parent_client_id' => $parentClient->id,
            'child_client_id' => $childClient->id
        ]);

        // Try to create the same relationship again
        $groupData = [
            'parent_client_id' => $parentClient->id,
            'child_client_id' => $childClient->id
        ];

        $response = $this->actingAs($this->adminUser)
                         ->postJson(route('clients.group.add'), $groupData);

        $response->assertStatus(409);
        $response->assertJson([
            'message' => 'Client is already in this group'
        ]);
    }

    public function test_can_remove_client_from_group()
    {
        $parentClient = Client::factory()->create([
            'agent_id' => $this->agent->id
        ]);
        
        $childClient = Client::factory()->create([
            'agent_id' => $this->agent->id
        ]);

        // Create the relationship first
        \App\Models\ClientGroup::create([
            'parent_client_id' => $parentClient->id,
            'child_client_id' => $childClient->id
        ]);

        // Verify the relationship exists
        $this->assertDatabaseHas('client_groups', [
            'parent_client_id' => $parentClient->id,
            'child_client_id' => $childClient->id
        ]);

        $removeData = [
            'parent_client_id' => $parentClient->id,
            'child_client_id' => $childClient->id
        ];

        $response = $this->actingAs($this->adminUser)
                         ->postJson(route('clients.group.remove'), $removeData);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Client removed from the group'
        ]);

        // Verify the relationship is removed from the database
        $this->assertDatabaseMissing('client_groups', [
            'parent_client_id' => $parentClient->id,
            'child_client_id' => $childClient->id
        ]);
    }

    public function test_cannot_remove_nonexistent_client_group_relationship()
    {
        $parentClient = Client::factory()->create([
            'agent_id' => $this->agent->id
        ]);
        
        $childClient = Client::factory()->create([
            'agent_id' => $this->agent->id
        ]);

        // Don't create the relationship, just try to remove it
        $removeData = [
            'parent_client_id' => $parentClient->id,
            'child_client_id' => $childClient->id
        ];

        $response = $this->actingAs($this->adminUser)
                         ->postJson(route('clients.group.remove'), $removeData);

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Client not found in this group'
        ]);
    }

    public function test_get_sub_clients_returns_child_clients()
    {
        $parentClient = Client::factory()->create([
            'agent_id' => $this->agent->id,
            'first_name' => 'Parent Client'
        ]);
        
        $childClient1 = Client::factory()->create([
            'agent_id' => $this->agent->id,
            'first_name' => 'Child Client 1'
        ]);
        
        $childClient2 = Client::factory()->create([
            'agent_id' => $this->agent->id,
            'first_name' => 'Child Client 2'
        ]);

        // Create group relationships
        \App\Models\ClientGroup::create([
            'parent_client_id' => $parentClient->id,
            'child_client_id' => $childClient1->id,
            'relation' => 'Son'
        ]);
        
        \App\Models\ClientGroup::create([
            'parent_client_id' => $parentClient->id,
            'child_client_id' => $childClient2->id,
            'relation' => 'Daughter'
        ]);

        $response = $this->actingAs($this->adminUser)
                         ->getJson(route('clients.sub', $parentClient->id));

        $response->assertStatus(200);
        $responseData = $response->json();
        
        $this->assertCount(2, $responseData);
        $this->assertEquals('Child Client 1', $responseData[0]['client']['first_name']);
        $this->assertEquals('Son', $responseData[0]['relation']);
        $this->assertEquals('Child Client 2', $responseData[1]['client']['first_name']);
        $this->assertEquals('Daughter', $responseData[1]['relation']);
    }

    public function test_get_parent_clients_returns_parent_clients()
    {
        $parentClient = Client::factory()->create([
            'agent_id' => $this->agent->id,
            'first_name' => 'Parent Client'
        ]);
        
        $childClient = Client::factory()->create([
            'agent_id' => $this->agent->id,
            'first_name' => 'Child Client'
        ]);

        // Create group relationship
        \App\Models\ClientGroup::create([
            'parent_client_id' => $parentClient->id,
            'child_client_id' => $childClient->id,
            'relation' => 'Father'
        ]);

        $response = $this->actingAs($this->adminUser)
                         ->getJson(route('clients.parent', $childClient->id));

        $response->assertStatus(200);
        $responseData = $response->json();
        
        $this->assertCount(1, $responseData);
        $this->assertEquals('Parent Client', $responseData[0]['client']['first_name']);
        $this->assertEquals('Father', $responseData[0]['relation']);
    }

    public function test_update_group_relationship()
    {
        $parentClient = Client::factory()->create([
            'agent_id' => $this->agent->id
        ]);
        
        $childClient = Client::factory()->create([
            'agent_id' => $this->agent->id
        ]);

        // Create group relationship
        \App\Models\ClientGroup::create([
            'parent_client_id' => $parentClient->id,
            'child_client_id' => $childClient->id,
            'relation' => 'Son'
        ]);

        $updateData = [
            'relation' => 'Daughter',
            'selectedId' => $childClient->id
        ];

        $response = $this->actingAs($this->adminUser)
                         ->putJson(route('clients.group.update', $parentClient->id), $updateData);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Client relationship updated successfully!'
        ]);

        // Verify the relationship is updated in the database
        $this->assertDatabaseHas('client_groups', [
            'parent_client_id' => $parentClient->id,
            'child_client_id' => $childClient->id,
            'relation' => 'Daughter'
        ]);
    }

    public function test_update_group_relationship_with_nonexistent_relationship()
    {
        $parentClient = Client::factory()->create([
            'agent_id' => $this->agent->id
        ]);
        
        $childClient = Client::factory()->create([
            'agent_id' => $this->agent->id
        ]);

        // Don't create the relationship, just try to update it
        $updateData = [
            'relation' => 'Daughter',
            'selectedId' => $childClient->id
        ];

        $response = $this->actingAs($this->adminUser)
                         ->putJson(route('clients.group.update', $parentClient->id), $updateData);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Client relationship not found!'
        ]);
    }

    public function test_get_client_details()
    {
        $client = Client::factory()->create([
            'agent_id' => $this->agent->id,
            'first_name' => 'Test Client Details',
            'email' => 'details@test.com'
        ]);

        $response = $this->actingAs($this->adminUser)
                         ->getJson(route('clients.details', $client->id));

        $response->assertStatus(200);
        $responseData = $response->json();
        
        $this->assertEquals($client->id, $responseData['id']);
        $this->assertEquals('Test Client Details', $responseData['first_name']);
        $this->assertEquals('details@test.com', $responseData['email']);
    }

    public function test_get_nonexistent_client_details()
    {
        $response = $this->actingAs($this->adminUser)
                         ->getJson(route('clients.details', 999999));

        $response->assertStatus(404);
        $response->assertJson([
            'error' => 'Client not found'
        ]);
    }

    public function test_company_user_cannot_view_other_company_client()
    {
        $companyBUser = User::factory()->create(['role_id' => Role::COMPANY]);
        $companyB = Company::factory()->create(['user_id' => $companyBUser->id]);

        $branchBUser = User::factory()->create(['role_id' => Role::BRANCH]);
        $branchB = Branch::factory()->create([
            'company_id' => $companyB->id,
            'user_id'    => $branchBUser->id,
        ]);

        $agentBUser = User::factory()->create(['role_id' => Role::AGENT]);
        $agentB = Agent::factory()->create([
            'branch_id'  => $branchB->id,
            'user_id'    => $agentBUser->id,
            'account_id' => 1,
            'type_id'    => 1,
        ]);

        $clientB = Client::factory()->create([
            'agent_id'   => $agentB->id,
            'first_name' => 'Test Client B',
        ]);

        $response = $this->actingAs($this->companyUser)->get(route('clients.show', $clientB->id));
        $response->assertStatus(403);
    }
}
