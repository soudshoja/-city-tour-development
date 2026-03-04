<?php

namespace Tests\Browser;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\InvoiceSequence;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class InvoiceCreationTest extends DuskTestCase
{
    use DatabaseMigrations;

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
            'password' => bcrypt('password'),
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

        // Create task with known values
        $this->task = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $this->supplier->id,
            'total' => 100.00,
            'invoice_price' => 150.00,
            'status' => 'issued',
            'type' => 'flight',
            'reference' => 'TST-DUSK-001',
        ]);

        // Create invoice sequence
        InvoiceSequence::create([
            'company_id' => $this->company->id,
            'current_sequence' => 1,
        ]);
    }

    /**
     * Test the full invoice creation flow through the browser:
     * 1. Login
     * 2. Navigate to create invoice page
     * 3. Select branch
     * 4. Click "Choose Agent" → pick agent from modal
     * 5. Click "Choose Client" → pick client from modal
     * 6. Click "Add Task" → pick task from modal
     * 7. Fill invoice price
     * 8. Click "Generate Invoice"
     * 9. Assert success
     */
    public function test_create_invoice_full_flow(): void
    {
        $this->browse(function (Browser $browser) {
            // Step 1: Login
            $browser->loginAs($this->companyUser)
                ->visit(route('invoices.create'))
                ->assertSee('Choose Agent')
                ->assertSee('Choose Client')
                ->pause(2000); // Wait for page JS to load

            // Step 2: Select Branch (if branch dropdown exists)
            $branchSelect = '#selectedBranch';
            $browser->whenAvailable($branchSelect, function (Browser $browser) {
                $browser->select('selectedBranch', $this->branch->id);
            }, 3);

            // Step 3: Choose Agent from modal
            $browser->click('#select-agent')
                ->pause(500)
                ->waitFor('#agentModal', 5)
                ->assertVisible('#agentModal');

            // Click on the first agent in the list
            $browser->with('#agentList', function (Browser $modal) {
                $modal->click('li:first-child');
            })
            ->pause(500);

            // Verify agent was selected
            $browser->assertInputValue('#agentId', $this->agent->id);

            // Step 4: Choose Client from modal
            $browser->click('#openClientModalButton')
                ->pause(500)
                ->waitFor('#clientModal', 5)
                ->assertVisible('#clientModal');

            // Click on the first client in the list
            $browser->with('#clientList', function (Browser $modal) {
                $modal->click('li:first-child');
            })
            ->pause(500);

            // Verify client was selected
            $browser->assertInputValue('#receiverId', $this->client->id);

            // Step 5: Add Task from modal
            $browser->click('#openTaskModalButton')
                ->pause(500)
                ->waitFor('#taskModal', 5)
                ->assertVisible('#taskModal');

            // Click on the first task row
            $browser->with('#taskListBody', function (Browser $modal) {
                $modal->click('tr:first-child');
            })
            ->pause(500);

            // Step 6: Set invoice price for the task
            $browser->waitFor('[id^="invprice-table-"]', 5)
                ->type('[id^="invprice-table-"]', '150');

            $browser->pause(500);

            // Step 7: Click Generate Invoice
            $browser->click('#generate-invoice-btn')
                ->pause(3000); // Wait for AJAX request and redirect

            // Step 8: Assert success - should redirect to edit page
            $browser->assertPathIsNot('/invoices/create');

            // Take a screenshot for verification
            $browser->screenshot('invoice-created-successfully');
        });
    }

    /**
     * Test that the create invoice page loads correctly with all elements.
     */
    public function test_create_invoice_page_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoices.create'))
                ->assertSee('Choose Agent')
                ->assertSee('Choose Client')
                ->assertSee('Add Task')
                ->assertSee('Invoice Number')
                ->assertSee('Invoice Date')
                ->assertSee('Due Date')
                ->assertSee('Total Net')
                ->assertSee('Invoice Total')
                ->screenshot('invoice-create-page');
        });
    }

    /**
     * Test agent modal opens and shows agents.
     */
    public function test_agent_modal_opens_and_shows_agents(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoices.create'))
                ->pause(2000)
                ->click('#select-agent')
                ->pause(500)
                ->waitFor('#agentModal', 5)
                ->assertVisible('#agentModal')
                ->assertSee('Agent Management')
                ->assertSee($this->agent->name)
                ->screenshot('agent-modal-open');
        });
    }

    /**
     * Test client modal opens after selecting agent.
     */
    public function test_client_modal_shows_agent_clients(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoices.create'))
                ->pause(2000);

            // First select an agent
            $browser->click('#select-agent')
                ->pause(500)
                ->waitFor('#agentModal', 5)
                ->with('#agentList', function (Browser $modal) {
                    $modal->click('li:first-child');
                })
                ->pause(500);

            // Then open client modal
            $browser->click('#openClientModalButton')
                ->pause(500)
                ->waitFor('#clientModal', 5)
                ->assertVisible('#clientModal')
                ->assertSee('Client Management')
                ->screenshot('client-modal-open');
        });
    }

    /**
     * Test task modal opens and shows available tasks.
     */
    public function test_task_modal_shows_available_tasks(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoices.create'))
                ->pause(2000);

            // First select an agent
            $browser->click('#select-agent')
                ->pause(500)
                ->waitFor('#agentModal', 5)
                ->with('#agentList', function (Browser $modal) {
                    $modal->click('li:first-child');
                })
                ->pause(500);

            // Open task modal
            $browser->click('#openTaskModalButton')
                ->pause(500)
                ->waitFor('#taskModal', 5)
                ->assertVisible('#taskModal')
                ->assertSee('TST-DUSK-001')
                ->screenshot('task-modal-open');
        });
    }

    /**
     * Test validation: cannot generate invoice without selecting agent.
     */
    public function test_cannot_generate_without_agent(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoices.create'))
                ->pause(2000)
                ->click('#generate-invoice-btn')
                ->pause(1000)
                ->assertSee('Agent ID is missing')
                ->screenshot('validation-no-agent');
        });
    }
}
