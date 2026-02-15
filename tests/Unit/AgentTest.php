<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Agent;
use App\Models\User;
use App\Models\Branch;
use App\Models\Company;
use App\Models\AgentType;
use App\Models\Task;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Account;
use App\Models\Role;

class AgentTest extends TestCase
{
    use RefreshDatabase;

    protected $agent;
    protected $user;
    protected $branch;
    protected $company;
    protected $agentType;

    protected function setUp(): void
    {
        parent::setUp();

        // Create agent type
        $this->agentType = AgentType::create([
            'id' => 1,
            'name' => 'Commission',
        ]);

        // Create user for company
        $companyUser = User::factory()->create([
            'role_id' => Role::COMPANY
        ]);

        // Create company
        $this->company = Company::factory()->create([
            'user_id' => $companyUser->id
        ]);
        session(['company_id' => $this->company->id]);

        // Create user for branch
        $branchUser = User::factory()->create([
            'role_id' => Role::BRANCH
        ]);

        // Create branch
        $this->branch = Branch::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $branchUser->id
        ]);

        // Create user for agent
        $this->user = User::factory()->create([
            'role_id' => Role::AGENT
        ]);

        // Create agent
        $this->agent = Agent::factory()->create([
            'user_id' => $this->user->id,
            'branch_id' => $this->branch->id,
            'type_id' => $this->agentType->id,
            'commission' => 0.15,
            'salary' => 1000.00,
            'target' => 5000.00
        ]);
    }

    public function test_agent_has_fillable_attributes()
    {
        $fillableAttributes = [
            'user_id',
            'name',
            'tbo_reference',
            'amadeus_id',
            'email',
            'type_id',
            'phone_number',
            'country_code',
            'branch_id',
            'commission',
            'salary',
            'target',
            'profit_account_id',
            'loss_account_id'
        ];

        $this->assertEquals($fillableAttributes, $this->agent->getFillable());
    }

    public function test_agent_belongs_to_user()
    {
        $this->assertInstanceOf(User::class, $this->agent->user);
        $this->assertEquals($this->user->id, $this->agent->user->id);
    }

    public function test_agent_belongs_to_branch()
    {
        $this->assertInstanceOf(Branch::class, $this->agent->branch);
        $this->assertEquals($this->branch->id, $this->agent->branch->id);
    }

    public function test_agent_belongs_to_company_through_branch()
    {
        $this->assertInstanceOf(Company::class, $this->agent->branch->company);
        $this->assertEquals($this->company->id, $this->agent->branch->company->id);
    }

    public function test_agent_belongs_to_agent_type()
    {
        $this->assertInstanceOf(AgentType::class, $this->agent->agentType);
        $this->assertEquals($this->agentType->id, $this->agent->agentType->id);
        $this->assertEquals('Commission', $this->agent->agentType->name);
    }

    public function test_agent_has_many_tasks()
    {
        // Create tasks for the agent
        $tasks = Task::factory()->count(3)->create([
            'agent_id' => $this->agent->id,
            'company_id' => $this->agent->branch->company->id
        ]);

        $this->assertCount(3, $this->agent->tasks);
        $this->assertInstanceOf(Task::class, $this->agent->tasks->first());
    }

    public function test_agent_has_many_invoices()
    {
        // Create client first
        $client = Client::factory()->create(['agent_id' => $this->agent->id]);
        
        // Create invoices for the agent
        $invoices = Invoice::factory()->count(2)->create([
            'agent_id' => $this->agent->id,
            'client_id' => $client->id
        ]);

        $this->assertCount(2, $this->agent->invoices);
        $this->assertInstanceOf(Invoice::class, $this->agent->invoices->first());
    }

    public function test_agent_has_many_clients()
    {
        // Create clients
        $clients = Client::factory()->count(3)->create();

        // Attach clients to the agent using the pivot table
        $this->agent->clients()->attach($clients->pluck('id'));

        // Refresh the agent to get the updated relationships
        $this->agent->refresh();

        $this->assertCount(3, $this->agent->clients);
        $this->assertInstanceOf(Client::class, $this->agent->clients->first());
        
        // Test that the relationship works both ways
        $firstClient = $clients->first();
        $firstClient->refresh();
        $this->assertTrue($firstClient->agents->contains($this->agent));
    }

    public function test_agent_clients_many_to_many_relationship()
    {
        // Create multiple clients
        $client1 = Client::factory()->create();
        $client2 = Client::factory()->create();
        $client3 = Client::factory()->create();

        // Create another agent
        $anotherAgent = Agent::factory()->create([
            'user_id' => User::factory()->create(['role_id' => Role::AGENT])->id,
            'branch_id' => $this->branch->id,
            'type_id' => $this->agentType->id,
        ]);

        // Attach clients to agents (many-to-many)
        $this->agent->clients()->attach([$client1->id, $client2->id]);
        $anotherAgent->clients()->attach([$client2->id, $client3->id]); // client2 belongs to both agents

        // Refresh models
        $this->agent->refresh();
        $anotherAgent->refresh();
        $client1->refresh();
        $client2->refresh();
        $client3->refresh();

        // Test agent relationships
        $this->assertCount(2, $this->agent->clients);
        $this->assertCount(2, $anotherAgent->clients);

        // Test client relationships
        $this->assertCount(1, $client1->agents); // belongs to 1 agent
        $this->assertCount(2, $client2->agents); // belongs to 2 agents
        $this->assertCount(1, $client3->agents); // belongs to 1 agent

        // Test specific relationships
        $this->assertTrue($this->agent->clients->contains($client1));
        $this->assertTrue($this->agent->clients->contains($client2));
        $this->assertFalse($this->agent->clients->contains($client3));

        $this->assertTrue($client2->agents->contains($this->agent));
        $this->assertTrue($client2->agents->contains($anotherAgent));
    }

    public function test_agent_can_detach_clients()
    {
        // Create and attach clients
        $clients = Client::factory()->count(3)->create();
        $this->agent->clients()->attach($clients->pluck('id'));

        $this->assertCount(3, $this->agent->clients);

        // Detach one client
        $this->agent->clients()->detach($clients->first()->id);
        $this->agent->refresh();

        $this->assertCount(2, $this->agent->clients);
        $this->assertFalse($this->agent->clients->contains($clients->first()));

        // Detach all clients
        $this->agent->clients()->detach();
        $this->agent->refresh();

        $this->assertCount(0, $this->agent->clients);
    }

    public function test_agent_has_one_account()
    {
        // Create account for the agent
        $account = Account::create([
            'name' => 'Agent Account',
            'level' => 2,
            'actual_balance' => 0.00,
            'budget_balance' => 0.00,
            'variance' => 0.00,
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'code' => 'AGT-' . $this->agent->id,
            'parent_id' => 1,
            'root_id' => 1
        ]);

        $this->assertInstanceOf(Account::class, $this->agent->account);
        $this->assertEquals($account->id, $this->agent->account->id);
    }

    public function test_agent_can_be_created_with_minimum_required_fields()
    {
        $agentData = [
            'user_id' => $this->user->id,
            'branch_id' => $this->branch->id,
            'name' => 'Test Agent',
            'email' => 'test@example.com',
            'phone_number' => '1234567890',
            'type_id' => $this->agentType->id
        ];

        $agent = Agent::create($agentData);

        $this->assertInstanceOf(Agent::class, $agent);
        $this->assertEquals('Test Agent', $agent->name);
        $this->assertEquals('test@example.com', $agent->email);
        $this->assertEquals('1234567890', $agent->phone_number);
        $this->assertEquals($this->user->id, $agent->user_id);
        $this->assertEquals($this->branch->id, $agent->branch_id);
        $this->assertEquals($this->agentType->id, $agent->type_id);
    }

    public function test_agent_can_have_commission_and_salary_attributes()
    {
        $this->assertEquals(0.15, $this->agent->commission);
        $this->assertEquals(1000.00, $this->agent->salary);
        $this->assertEquals(5000.00, $this->agent->target);
    }

    public function test_agent_attributes_can_be_updated()
    {
        $this->agent->update([
            'commission' => 0.20,
            'salary' => 1500.00,
            'target' => 6000.00,
            'amadeus_id' => 'AMD123',
            'phone_number' => '+1234567890'
        ]);

        $this->assertEquals(0.20, $this->agent->fresh()->commission);
        $this->assertEquals(1500.00, $this->agent->fresh()->salary);
        $this->assertEquals(6000.00, $this->agent->fresh()->target);
        $this->assertEquals('AMD123', $this->agent->fresh()->amadeus_id);
        $this->assertEquals('+1234567890', $this->agent->fresh()->phone_number);
    }

    public function test_agent_table_name_is_explicitly_set()
    {
        $this->assertEquals('agents', $this->agent->getTable());
    }

    public function test_agent_uses_has_factory_trait()
    {
        $this->assertTrue(in_array('Illuminate\Database\Eloquent\Factories\HasFactory', class_uses($this->agent)));
    }

    public function test_agent_commission_defaults_to_zero()
    {
        $agent = Agent::factory()->create([
            'user_id' => User::factory()->create(['role_id' => Role::AGENT])->id,
            'branch_id' => $this->branch->id,
            'type_id' => $this->agentType->id,
        ]);

        $this->assertEquals(0.00, $agent->commission);
    }

    public function test_agent_salary_defaults_to_zero()
    {
        $agent = Agent::factory()->create([
            'user_id' => User::factory()->create(['role_id' => Role::AGENT])->id,
            'branch_id' => $this->branch->id,
            'type_id' => $this->agentType->id,
        ]);

        $this->assertEquals(0.00, $agent->salary);
    }

    public function test_agent_target_defaults_to_zero()
    {
        $agent = Agent::factory()->create([
            'user_id' => User::factory()->create(['role_id' => Role::AGENT])->id,
            'branch_id' => $this->branch->id,
            'type_id' => $this->agentType->id,
        ]);

        $this->assertEquals(0.00, $agent->target);
    }

    public function test_agent_can_have_optional_fields()
    {
        $agent = Agent::factory()->create([
            'user_id' => User::factory()->create(['role_id' => Role::AGENT])->id,
            'branch_id' => $this->branch->id,
            'type_id' => $this->agentType->id,
            'tbo_reference' => 'TBO123',
            'amadeus_id' => 'AMD456',
            'country_code' => '+1',
            'phone_number' => '1234567890'
        ]);

        $this->assertEquals('TBO123', $agent->tbo_reference);
        $this->assertEquals('AMD456', $agent->amadeus_id);
        $this->assertEquals('+1', $agent->country_code);
        $this->assertEquals('1234567890', $agent->phone_number);
    }
}
