<?php

namespace Tests\Feature\Invoice;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    // public function test_invoice_page_displayed_for_admin()
    // {
    //     $user = User::factory()->create([
    //         'role_id' => Role::ADMIN
    //     ]);

    //     $response = $this->actingAs($user)->get(route('invoices.index'));

    //     $response->assertStatus(200);
    // }

    protected function setUp() : void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
    }

    public function test_admin_cannot_view_invoice_list()
    {
        /** @var User $user */
        $user = User::factory()->create([
            'role_id' => Role::ADMIN
        ]);

        /** @var User $userCompany */
        $userCompany = User::factory()->create([
            'role_id' => Role::COMPANY
        ]);

        $company = Company::factory()->create([
            'user_id' => $userCompany->id
        ]);

        $roleAdmin = Role::create(['name' => 'admin', 'guard_name' => 'web', 'company_id' => $company->id]);

        $user->assignRole($roleAdmin);

        $response = $this->actingAs($user)->get(route('invoices.index'));

        $response->assertStatus(403);
    }
    
    public function test_invoice_page_displayed_for_company(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'role_id' => Role::COMPANY
        ]);

        $company = Company::factory()->create([
            'user_id' => $user->id
        ]);

        $roleCompany = Role::create(['name' => 'company', 'guard_name' => 'web', 'company_id' => $company->id]);
        $user->assignRole($roleCompany);

        $roleCompany->givePermissionTo('view invoice');

        Branch::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('invoices.index'));

        $response->assertStatus(200);
    }

    public function test_invoice_page_displayed_for_branch()
    {
        /** @var User $userCompany */
        $userCompany = User::factory()->create([
            'role_id' => Role::COMPANY
        ]);

        $company = Company::factory()->create([
            'user_id' => $userCompany->id
        ]);

        /** @var User $userBranch */
        $userBranch = User::factory()->create([
            'role_id' => Role::BRANCH
        ]);

        Branch::factory()->create([
            'user_id' => $userBranch->id,
            'company_id' => $company->id
        ]);

        $roleBranch = Role::create(['name' => 'branch', 'guard_name' => 'web', 'company_id' => $company->id]);
        $userBranch->assignRole($roleBranch);

        $roleBranch->givePermissionTo('view invoice');

        $response = $this
            ->actingAs($userBranch)
            ->get(route('invoices.index'));
        
        $response->assertStatus(200);
    }
    
    public function test_invoice_page_displayed_for_agent()
    {
        /** @var User $userCompany */
        $userCompany = User::factory()->create([
            'role_id' => Role::COMPANY
        ]);

        $company = Company::factory()->create([
            'user_id' => $userCompany->id
        ]);

        /** @var User $userBranch */
        $userBranch = User::factory()->create([
            'role_id' => Role::BRANCH
        ]);

        $branch = Branch::factory()->create([
            'user_id' => $userBranch->id,
            'company_id' => $company->id
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'role_id' => Role::AGENT
        ]);

        $agentType = AgentType::create(['name' => 'Salary']);

        $roleAgent = Role::create(['name' => 'agent', 'guard_name' => 'web', 'company_id' => $company->id]);
        $user->assignRole($roleAgent);
        $roleAgent->givePermissionTo('view invoice');

        Agent::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
        ]);

        $response = $this->actingAs($user)->get(route('invoices.index'));

        $response->assertStatus(200);
    }

    public function test_company_invoice_list_is_isolated()
    {
        /** @var User $userA */
        $userA    = User::factory()->create(['role_id' => Role::COMPANY]);
        $companyA = Company::factory()->create(['user_id' => $userA->id]);
        $branchA  = Branch::factory()->create(['user_id' => $userA->id, 'company_id' => $companyA->id]);
        $agentA   = Agent::factory()->create([
            'user_id'   => User::factory()->create(['role_id' => Role::AGENT])->id,
            'branch_id' => $branchA->id,
            'type_id'   => AgentType::create(['name' => 'Salary'])->id,
        ]);
        $clientA  = Client::factory()->create(['agent_id' => $agentA->id]);

        $invoiceA = Invoice::factory()->count(2)->create([
            'agent_id'  => $agentA->id,
            'client_id' => $clientA->id,
        ]);

        $taskA = Task::factory()->create([
            'company_id' => $companyA->id,
            'agent_id'   => $agentA->id,
            'client_id'  => $clientA->id,
        ]);
        
        foreach ($invoiceA as $invoice) {
            InvoiceDetail::factory()->create([
                'invoice_id' => $invoice->id,
                'task_id' => $taskA->id,
                'invoice_number'  => $invoice->invoice_number,
            ]);
        }        

        /** @var User $userB */
        $userB    = User::factory()->create(['role_id' => Role::COMPANY]);
        $companyB = Company::factory()->create(['user_id' => $userB->id]);
        $branchB  = Branch::factory()->create(['user_id' => $userB->id, 'company_id' => $companyB->id]);
        $agentB   = Agent::factory()->create([
            'user_id'   => User::factory()->create(['role_id' => Role::AGENT])->id,
            'branch_id' => $branchB->id,
            'type_id'   => AgentType::first()->id,
        ]);
        $clientB  = Client::factory()->create(['agent_id' => $agentB->id]);

        $invoiceB = Invoice::factory()->count(2)->create([
            'agent_id'  => $agentB->id,
            'client_id' => $clientB->id,
        ]);

        $roleCompanyA = Role::create(['name' => 'company', 'guard_name' => 'web', 'company_id' => $companyA->id]);
        $userA->assignRole($roleCompanyA);
        $roleCompanyA->givePermissionTo('view invoice');

        $response = $this->actingAs($userA)->get(route('invoices.index'));
        $response->assertOk();

        $invoices = $response->viewData('invoices') ?? collect();
        foreach (($invoices instanceof \Illuminate\Pagination\LengthAwarePaginator ? $invoices->items() : $invoices) as $invoice) {
            $this->assertEquals($companyA->id, $invoice->agent->branch->company_id);
        }
    }

    // public function test_admin_view_on_list_of_invoice()
    // {
    //     $user = User::factory()->create([
    //         'role_id' => Role::ADMIN
    //     ]);


    //     $userAgent = User::factory()->create([
    //         'role_id' => Role::AGENT
    //     ]);

    //     $agentType = AgentType::create(['name' => 'Commission']);

    //     $agent = Agent::factory()->create([
    //         'user_id' => $userAgent->id,
    //         'type_id' => $agentType->id
    //     ]);

    //     $client  = Client::factory()->create([
    //         'agent_id' => $agent->id
    //     ]);

    //     $userCompany = User::factory()->create([
    //         'role_id' => Role::COMPANY
    //     ]);

    //     $company = Company::factory()->create([
    //         'user_id' => $userCompany->id
    //     ]);

    //     $userBranch = User::factory()->create([
    //         'role_id' => Role::BRANCH
    //     ]);

    //     $branch = Branch::factory()->create([
    //         'user_id' => $userBranch->id,
    //         'company_id' => $company->id
    //     ]);

    //     Agent::factory()->create([
    //         'user_id' => $userAgent->id,
    //         'branch_id' => $branch->id,
    //         'type_id' => $agentType->id,
    //     ]);

    //     $task = Task::factory()->create([
    //         'client_id' => $client->id,
    //         'agent_id' => $agent->id,
    //         'company_id' => $company->id,
    //     ]);

    //     $invoices = Invoice::factory()->count(5)->create([
    //         'client_id' => $client->id,
    //         'agent_id' => $agent->id,
    //     ]);

    //     $invoiceDetails = $invoices->map(function ($invoice) use ($task) {
    //         return InvoiceDetail::factory()->create([
    //             'invoice_id' => $invoice->id,
    //             'task_id' => $task->id,
    //         ]);
    //     });

    //     $response = $this->actingAs($user)->get(route('invoices.index'));
    //     $response->assertStatus(200);

    //     foreach ($invoices as $invoice) {
    //         $response->assertSee($invoice->number);
    //     }

    //     foreach ($invoiceDetails as $detail) {
    //         $response->assertSee($detail->task_description);
    //     }

    //     $response->assertSee('Total Invoices: ' . $invoices->count());
    //     $response->assertSee('Total Amount: ' . $invoices->sum('total amount'));
    // }

}
