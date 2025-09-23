<?php

namespace Tests\Feature;

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
        $user = User::factory()->create([
            'role_id' => Role::ADMIN
        ]);

        $userCompany = User::factory()->create([
            'role_id' => Role::COMPANY
        ]);

        $company = Company::factory()->create([
            'user_id' => $userCompany->id
        ]);

        $roleAdmin = Role::create(['name' => 'admin', 'guard_name' => 'web', 'company_id' => $company->id]);

        $user->assignRole($roleAdmin);

        $roleAdmin->givePermissionTo('view invoice');

        $response = $this->actingAs($user)->get(route('invoices.index'));

        $response->assertStatus(403);
    }
    
    public function test_invoice_page_displayed_for_company(): void
    {

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
        $userCompany = User::factory()->create([
            'role_id' => Role::COMPANY
        ]);

        $company = Company::factory()->create([
            'user_id' => $userCompany->id
        ]);

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
        $userCompany = User::factory()->create([
            'role_id' => Role::COMPANY
        ]);

        $company = Company::factory()->create([
            'user_id' => $userCompany->id
        ]);

        $userBranch = User::factory()->create([
            'role_id' => Role::BRANCH
        ]);

        $branch = Branch::factory()->create([
            'user_id' => $userBranch->id,
            'company_id' => $company->id
        ]);

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
