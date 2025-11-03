<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Client;
use App\Models\Country;
use App\Models\Supplier;
use App\Models\Hotel;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\TaskFlightDetail;
use App\Models\TaskHotelDetail;
use Database\Seeders\CoaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Spatie\Permission\Models\Role as SpatieRole;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    protected $companyUser;
    protected $company;
    protected $branchUser;
    protected $admin;
    protected $country;
    protected $supplier;
    protected $branch;
    protected $agentUser;
    protected $agent;
    protected $client;
    protected $hotel;
    protected $assetsAccount;
    protected $accountsReceivableAccount;
    protected $clientsAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $companyRole = Role::firstOrCreate(['name' => 'company', 'guard_name' => 'web']);
        $branchRole = Role::firstOrCreate(['name' => 'branch', 'guard_name' => 'web']);
        $agentRole = Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'web']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);

        // Admin permissions (IDs 7-70)
        $adminPermissions = [
            'create task', 'view task', 'update task', 'delete task',
            'create supplier', 'view supplier', 'update supplier', 'delete supplier',
            'create company', 'view company', 'update company', 'delete company',
            'create branch', 'view branch', 'update branch', 'delete branch',
            'create agent', 'view agent', 'update agent', 'delete agent',
            'create invoice', 'view invoice', 'update invoice', 'delete invoice',
            'create client', 'view client', 'update client', 'delete client',
            'create currency exchange', 'view currency exchange', 'update currency exchange', 'delete currency exchange',
            'view credit', 'view payment', 'view refund',
            'view reconcile report', 'view profit loss', 'view settlement', 'view creditors'
        ];
        $adminRole->givePermissionTo($adminPermissions);

        // Company permissions (IDs 1-12, 17-70, 71)
        $companyPermissions = [
            'create task', 'view task', 'update task', 'delete task',
            'create supplier', 'view supplier', 'update supplier', 'delete supplier',
            'create branch', 'view branch', 'update branch', 'delete branch',
            'create agent', 'view agent', 'update agent', 'delete agent',
            'create invoice', 'view invoice', 'update invoice', 'delete invoice',
            'create client', 'view client', 'update client', 'delete client',
            'create currency exchange', 'view currency exchange', 'update currency exchange', 'delete currency exchange',
            'view credit', 'view payment', 'view refund',
            'view reconcile report', 'view profit loss', 'view settlement', 'view creditors', 'view daily sales'
        ];
        $companyRole->givePermissionTo($companyPermissions);

        // Agent permissions (IDs 9-12, 21-24, 29-35, 39-42, 48, 50-51, 56-59, 61, 64-66)
        $agentPermissions = [
            'create task', 'view task', 'update task', 'delete task',
            'create invoice', 'view invoice', 'update invoice', 'delete invoice',
            'view agent', 'update agent',
            'create client', 'view client', 'update client', 'delete client',
            'view currency exchange', 'view credit', 'view payment', 'view refund'
        ];
        $agentRole->givePermissionTo($agentPermissions);

        // Accountant permissions (IDs 22-23, 29-38, 40, 48-51, 61-62, 67-71)
        $accountantPermissions = [
            'view invoice', 'update invoice',
            'view agent',
            'view currency exchange', 'update currency exchange',
            'view reconcile report', 'view profit loss', 'view settlement', 'view creditors', 'view daily sales'
        ];
        $accountantRole->givePermissionTo($accountantPermissions);

        $this->admin = User::factory()->create(['role_id' => Role::ADMIN]);
        $this->admin->assignRole('admin');
        
        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@test.com'
        ]);

        $this->country = Country::factory()->create();

        $this->company = Company::factory()->create([
            'name' => 'Test Company',
            'status' => 1,
            'user_id' => $this->companyUser->id,
            'country_id' => $this->country->id,
        ]);

        $this->companyUser->assignRole('company');

        CoaSeeder::run($this->company->id);

        $this->branchUser = User::create([
            'name' => 'Branch User',
            'email' => 'branch@yahoo.com',
            'password' => bcrypt('password123'),
            'role_id' => Role::BRANCH,
            'company_id' => $this->company->id,
            'country_id' => $this->country->id,
        ]);

        $this->branch = Branch::create([
            'name' => 'Main Branch',
            'address' => '123 Main St',
            'city' => 'Metropolis',
            'country_id' => $this->country->id,
            'phone' => '+1234567890',
            'email' => 'branch@yahoo.com',
            'user_id' => $this->branchUser->id,
            'company_id' => $this->company->id,
        ]);

        $this->branchUser->assignRole('branch');

        AgentType::factory()->create([
            'id' => 1,
            'name' => 'Commission',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        AgentType::factory()->create([
            'id' => 2,
            'name' => 'Salary',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        AgentType::factory()->create([
            'id' => 3,
            'name' => 'Both',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        $this->agent = Agent::factory()->create([
            'user_id' => $this->agentUser->id,
            'branch_id' => $this->branch->id,
            'type_id' => 1
        ]);

        $this->agentUser->assignRole('agent');

        $this->supplier = Supplier::factory()->create(['country_id' => $this->country->id]);

        $this->client = Client::factory()->create(['agent_id' => $this->agent->id]);

        $this->hotel = Hotel::create([
            'name' => 'Test Hotel',
            'address' => '123 Test Street',
            'city' => 'Test City',
            'country' => $this->country->name,
            'phone' => '+1234567890',
            'email' => 'test@hotel.com',
            'rating' => 4,
        ]);

        $this->clientsAccount = Account::where('company_id', $this->company->id)
            ->where('name', 'Clients')
            ->first();
    }

    public function test_admin_can_soft_delete_simple_task()
    {
        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $this->supplier->id,
            'reference' => 'TEST-REF-001',
            'total' => 1000.00
        ]);

        $this->actingAs($this->admin);

        $response = $this->delete(route('tasks.destroy', $task->id));

        // Expect redirect with success message
        $response->assertRedirect()
                ->assertSessionHas('success', "Task 'TEST-REF-001' and all related data have been soft deleted successfully.");

        // Verify task was soft deleted
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        
        $trashedTask = Task::withTrashed()->find($task->id);
        $this->assertNotNull($trashedTask);
        $this->assertNotNull($trashedTask->deleted_at);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'reference' => 'TEST-REF-001'
        ]);
        $this->assertNotNull(Task::withTrashed()->find($task->id)->deleted_at);
    }

    public function test_non_admin_cannot_delete_task()
    {
        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $this->supplier->id,
        ]);

        $this->actingAs($this->companyUser);

        $response = $this->delete(route('tasks.destroy', $task->id));

        // Gate authorization fails first, which returns 403 from Laravel
        $response->assertStatus(403);

        // Assert task is not deleted
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->assertNull(Task::find($task->id)->deleted_at);
    }

    public function test_admin_cannot_delete_nonexistent_task()
    {
        $this->actingAs($this->admin);

        // Call destroy method with non-existent task ID
        $response = $this->delete(route('tasks.destroy', 99999));

        $response->assertStatus(404);
    }

    public function test_destroy_task_without_related_data()
    {
        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'reference' => 'SIMPLE-TASK-001'
        ]);

        $this->actingAs($this->admin);

        $response = $this->delete(route('tasks.destroy', $task->id));

        $response->assertRedirect()
            ->assertSessionHas('success', "Task 'SIMPLE-TASK-001' and all related data have been soft deleted successfully.");

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_admin_can_soft_delete_task_with_invoices()
    {
        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'supplier_id' => $this->supplier->id,
            'reference' => 'TEST-REF-001',
            'total' => 1000.00,
            'type' => 'flight',
        ]);

        // Create related data
        TaskFlightDetail::factory()->create([
            'task_id' => $task->id,
            'country_id_to' => $this->country->id,
            'country_id_from' => $this->country->id
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $task->client_id,
            'agent_id' => $task->agent_id,
            'country_id' => $this->country->id,
            'invoice_number' => 'INV-001',
            'sub_amount' => 1000.00,
            'amount' => 1000.00
        ]);

        InvoiceDetail::factory()->create([
            'task_id' => $task->id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_id' => $invoice->id,
        ]);

        $this->actingAs($this->admin);

        $response = $this->delete(route('tasks.destroy', $task->id));

        $response->assertRedirect()
            ->assertSessionHas('success', "Task 'TEST-REF-001' and all related data have been soft deleted successfully.");

        // Assert task is soft deleted
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);

        // Assert related data is also soft deleted
        $this->assertSoftDeleted('task_flight_details', ['task_id' => $task->id]);
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertSoftDeleted('invoice_details', ['invoice_id' => $invoice->id]);

        // Assert task still exists in database but is marked as deleted
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'reference' => 'TEST-REF-001'
        ]);
        $this->assertNotNull(Task::withTrashed()->find($task->id)->deleted_at);

        // Assert invoice still exists in database but is marked as deleted
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'invoice_number' => 'INV-001'
        ]);
        $this->assertNotNull(Invoice::withTrashed()->find($invoice->id)->deleted_at);

    }

    public function test_admin_can_soft_delete_task_with_all_related_data()
    {
        // Create task
        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'supplier_id' => $this->supplier->id,
            'reference' => 'COMPLEX-TASK-001',
            'total' => 2500.00,
            'type' => 'flight',
        ]);

        // 1. Create TaskFlightDetail
        $flightDetail = TaskFlightDetail::factory()->create([
            'task_id' => $task->id,
            'country_id_to' => $this->country->id,
            'country_id_from' => $this->country->id
        ]);

        // 2. Create TaskHotelDetail
        $hotelDetail = TaskHotelDetail::factory()->create([
            'task_id' => $task->id,
            'hotel_id' => $this->hotel->id,
        ]);

        // 3. Create Invoice
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'agent_id' => $this->agent->id,
            'country_id' => $this->country->id,
            'invoice_number' => 'INV-COMPLEX-001',
            'sub_amount' => 2500.00,
            'amount' => 2500.00
        ]);

        // 4. Create InvoiceDetail linking invoice to task
        $invoiceDetail = InvoiceDetail::factory()->create([
            'task_id' => $task->id,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
        ]);

        // 5. Create Payment related to invoice
        $payment = Payment::factory()->create([
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'invoice_id' => $invoice->id,
            'created_by' => $this->admin->id,
            'account_id' => null,
            'amount' => 1500.00,
            'payment_date' => now(),
        ]);

        // 6. Create Transaction related to invoice
        $invoiceTransaction = Transaction::create([
            'invoice_id' => $invoice->id,
            'company_id' => $this->company->id,
            'entity_id' => $this->company->id,
            'entity_type' => 'company',
            'amount' => 2500.00,
            'transaction_type' => 'credit',
            'status' => 'completed',
            'user_id' => $this->admin->id,
            'description' => 'Invoice transaction',
            'currency' => 'USD',
        ]);

        // 7. Create JournalEntry related to task
        $journalEntry = JournalEntry::create([
            'task_id' => $task->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'account_id' => $this->clientsAccount->id,
            'transaction_date' => now(),
            'description' => 'Client receivable for task booking',
            'name' => 'Client Receivable Entry',
            'debit' => 2500.00,
            'credit' => 0,
            'balance' => 2500.00,
            'amount' => 2500.00,
            'type' => 'debit',
            'currency' => 'USD',
            'exchange_rate' => 1.0,
        ]);

        // 8. Create Transaction related to journal entry
        $journalTransaction = Transaction::create([
            'company_id' => $this->company->id,
            'entity_id' => $this->company->id,
            'entity_type' => 'company',
            'branch_id' => $this->branch->id,
            'amount' => 2500.00,
            'transaction_type' => 'debit',
            'description' => 'Transaction for client receivable',
            'name' => 'Journal Entry Transaction',
        ]);

        // Update journal entry with transaction ID
        $journalEntry->update(['transaction_id' => $journalTransaction->id]);

        // Authenticate as admin
        $this->actingAs($this->admin);

        // Call destroy method
        $response = $this->delete(route('tasks.destroy', $task->id));

        // Assert successful redirect with success message
        $response->assertRedirect()
                ->assertSessionHas('success', "Task 'COMPLEX-TASK-001' and all related data have been soft deleted successfully.");

        // Assert main task is soft deleted
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);

        // Assert task details are soft deleted
        $this->assertSoftDeleted('task_flight_details', ['id' => $flightDetail->id]);
        $this->assertSoftDeleted('task_hotel_details', ['id' => $hotelDetail->id]);

        // Assert invoice and related data are soft deleted
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertSoftDeleted('invoice_details', ['id' => $invoiceDetail->id]);
        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
        $this->assertSoftDeleted('transactions', ['id' => $invoiceTransaction->id]);

        // Assert journal entries and related transactions are soft deleted
        $this->assertSoftDeleted('journal_entries', ['id' => $journalEntry->id]);
        $this->assertSoftDeleted('transactions', ['id' => $journalTransaction->id]);

        // Assert all records still exist in database but are marked as deleted
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'reference' => 'COMPLEX-TASK-001'
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'invoice_number' => 'INV-COMPLEX-001'
        ]);

        // Verify we can retrieve soft deleted records with withTrashed()
        $trashedTask = Task::withTrashed()->find($task->id);
        $this->assertNotNull($trashedTask);
        $this->assertNotNull($trashedTask->deleted_at);

        $trashedInvoice = Invoice::withTrashed()->find($invoice->id);
        $this->assertNotNull($trashedInvoice);
        $this->assertNotNull($trashedInvoice->deleted_at);

        // Assert that non-task related data is preserved (client, agent, company, etc.)
        $this->assertDatabaseHas('clients', ['id' => $this->client->id]);
        $this->assertDatabaseHas('agents', ['id' => $this->agent->id]);
        $this->assertDatabaseHas('companies', ['id' => $this->company->id]);
        $this->assertDatabaseHas('countries', ['id' => $this->country->id]);
        $this->assertDatabaseHas('suppliers', ['id' => $this->supplier->id]);
    }

    public function test_company_tasks_index_is_isolated_per_company()
    {
        // Company A uses the setup entities
        Task::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'agent_id'   => $this->agent->id,
            'client_id'  => $this->client->id,
            'supplier_id' => $this->supplier->id,
        ]);

        // Company B - create separate entities for isolation test
        $companyBUser = User::factory()->create(['role_id' => Role::COMPANY]);
        $companyB = Company::factory()->create(['user_id' => $companyBUser->id]);
        $branchB = Branch::factory()->create([
            'user_id'    => $companyBUser->id,
            'company_id' => $companyB->id,
        ]);
        $agentBUser = User::factory()->create(['role_id' => Role::AGENT]);
        $agentB = Agent::factory()->create([
            'user_id'   => $agentBUser->id,
            'branch_id' => $branchB->id,
            'type_id'   => 1,
        ]);
        $clientB = Client::factory()->create(['agent_id' => $agentB->id]);
        $supplierB = Supplier::factory()->create(['country_id' => $this->country->id]);

        Task::factory()->count(2)->create([
            'company_id' => $companyB->id,
            'agent_id'   => $agentB->id,
            'client_id'  => $clientB->id,
            'supplier_id' => $supplierB->id,
        ]);

        
        $roleCompany = SpatieRole::firstOrCreate(['name' => 'company', 'guard_name' => 'web']);
        $roleCompany->givePermissionTo('view task');
        $this->companyUser->assignRole($roleCompany);
        $this->actingAs($this->companyUser);

        $response = $this->get(route('tasks.index', ['invoiced' => 0, 'view_type' => 'invoice']));
        $response->assertOk()->assertViewIs('tasks.index')->assertViewHas('tasks');
        $tasks = $response->viewData('tasks');

        foreach (($tasks instanceof \Illuminate\Pagination\LengthAwarePaginator ? $tasks->items() : $tasks) as $task) {
            $this->assertEquals($this->company->id, $task->company_id);
        }
    }

    public function test_agent_sees_only_own_and_unassigned_tasks_in_same_company()
    {
        // Create unassigned task in company A
        Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => null,
            'supplier_id' => $this->supplier->id
        ]);

        // Create company B with its own unassigned task
        $companyBUser = User::factory()->create(['role_id' => Role::COMPANY]);
        $companyB = Company::factory()->create(['user_id' => $companyBUser->id]);
        $supplierB = Supplier::factory()->create(['country_id' => $this->country->id]);
        Task::factory()->create([
            'company_id' => $companyB->id,
            'agent_id' => null,
            'supplier_id' => $supplierB->id
        ]);

        $this->actingAs($this->agentUser);

        $response = $this->followingRedirects()->get(route('tasks.index', ['invoiced' => 0, 'view_type' => 'invoice']));
        $response->assertOk()->assertViewIs('tasks.index')->assertViewHas('tasks');
        $tasks = $response->viewData('tasks');

        foreach (($tasks instanceof \Illuminate\Pagination\LengthAwarePaginator ? $tasks->items() : $tasks) as $task) {
            $this->assertEquals($this->company->id, $task->company_id);
            $this->assertTrue($task->agent_id === $this->agent->id || is_null($task->agent_id));
        }
    }

    public function test_task_search_is_company_scoped()
    {
        $this->actingAs($this->companyUser);
        Task::factory()->create([
            'company_id' => $this->company->id,
            'passenger_name' => 'Unique Zed',
            'supplier_id' => $this->supplier->id
        ]);

        // Create Company B with same passenger name for isolation test
        $companyBUser = User::factory()->create(['role_id' => Role::COMPANY]);
        $companyB = Company::factory()->create(['user_id' => $companyBUser->id]);
        $supplierB = Supplier::factory()->create(['country_id' => $this->country->id]);
        Task::factory()->create([
            'company_id' => $companyB->id,
            'passenger_name' => 'Unique Zed',
            'supplier_id' => $supplierB->id
        ]);

        $roleCompany = SpatieRole::firstOrCreate(['name' => 'company', 'guard_name' => 'web']);
        $roleCompany->givePermissionTo('view task');
        $this->companyUser->assignRole($roleCompany);
        $this->actingAs($this->companyUser);

        $response = $this->followingRedirects()->get(route('tasks.index', ['invoiced'=>0,'view_type'=>'invoice','q'=>'Unique Zed']));
        $response->assertOk()->assertViewIs('tasks.index')->assertViewHas('tasks');
        $tasks = $response->viewData('tasks');

        foreach (($tasks instanceof \Illuminate\Pagination\LengthAwarePaginator ? $tasks->items() : $tasks) as $task) {
            $this->assertEquals($this->company->id, $task->company_id);
        }
    }

    public function test_pagination_does_not_leak_cross_company_tasks()
    {
        $this->actingAs($this->companyUser);
        Task::factory()->count(35)->create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id
        ]);
        
        // Create Company B for isolation test
        $companyBUser = User::factory()->create(['role_id'=>Role::COMPANY]);
        $companyB = Company::factory()->create(['user_id' => $companyBUser->id]);
        $supplierB = Supplier::factory()->create(['country_id' => $this->country->id]);
        Task::factory()->count(5)->create([
            'company_id' => $companyB->id,
            'supplier_id' => $supplierB->id
        ]);

        $roleCompany = SpatieRole::firstOrCreate(['name' => 'company', 'guard_name' => 'web']);
        $roleCompany->givePermissionTo('view task');
        $this->companyUser->assignRole($roleCompany);
        $this->actingAs($this->companyUser);

        foreach ([1,2] as $page) {
            $response = $this->followingRedirects()->get(route('tasks.index', ['invoiced'=>0,'view_type'=>'invoice','page'=>$page]));
            $response->assertOk()->assertViewIs('tasks.index')->assertViewHas('tasks');
            $tasks = $response->viewData('tasks');
            foreach ($tasks as $task) {
                $this->assertEquals($this->company->id, $task->company_id);
            }
        }
    }
}
