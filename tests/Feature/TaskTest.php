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
use App\Models\TaskFlightDetail;
use App\Models\TaskHotelDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    protected $companyUser;
    protected $company;

    protected function setUp(): void
    {
        parent::setUp();
        
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

        // Create necessary roles
        Role::create(['name' => 'admin', 'guard_name' => 'web', 'company_id' => $this->company->id]);
        Role::create(['name' => 'company', 'guard_name' => 'web', 'company_id' => $this->company->id]);
        Role::create(['name' => 'branch', 'guard_name' => 'web', 'company_id' => $this->company->id]);
        Role::create(['name' => 'agent', 'guard_name' => 'web', 'company_id' => $this->company->id]);

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
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        $admin->assignRole('admin');
        return $admin;
    }

    private function createCompanyUser(): User
    {
        return User::factory()->create(['role_id' => Role::COMPANY]);
    }

    public function test_admin_can_soft_delete_simple_task()
    {
        $admin = $this->createAdminUser();

        $country = Country::factory()->create();
        
        $userCompany = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create([
            'user_id' => $userCompany->id,
            'country_id' => $country->id,
        ]);
        
        $branch = Branch::factory()->create([
            'user_id' => $userCompany->id,
            'company_id' => $company->id,
        ]);
        
        $userAgent = User::factory()->create(['role_id' => Role::AGENT]);
        $agent = Agent::factory()->create([
            'user_id' => $userAgent->id,
            'branch_id' => $branch->id,
            'type_id' => 1
        ]);
        
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create(['country_id' => $country->id]);
        
        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'reference' => 'TEST-REF-001',
            'total' => 1000.00
        ]);

        $this->actingAs($admin);

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
        $companyUser = $this->createCompanyUser();

        $company = Company::factory()->create([
            'user_id' => $companyUser->id,
        ]);
        
        $task = Task::factory()->create([
            'company_id' => $company->id
        ]);

        $this->actingAs($companyUser);

        $response = $this->delete(route('tasks.destroy', $task->id));

        // Gate authorization fails first, which returns 403 from Laravel
        $response->assertStatus(403);

        // Assert task is not deleted
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->assertNull(Task::find($task->id)->deleted_at);
    }

    public function test_admin_cannot_delete_nonexistent_task()
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin);

        // Call destroy method with non-existent task ID
        $response = $this->delete(route('tasks.destroy', 99999));

        $response->assertStatus(404);
    }

    public function test_destroy_task_without_related_data()
    {
        $admin = $this->createAdminUser();
        
        $country = Country::factory()->create();
        $userCompany = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create([
            'user_id' => $userCompany->id,
            'country_id' => $country->id,
        ]);
        $supplier = Supplier::factory()->create(['country_id' => $country->id]);
        
        $task = Task::factory()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'reference' => 'SIMPLE-TASK-001'
        ]);

        $this->actingAs($admin);

        $response = $this->delete(route('tasks.destroy', $task->id));

        $response->assertRedirect()
            ->assertSessionHas('success', "Task 'SIMPLE-TASK-001' and all related data have been soft deleted successfully.");

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_admin_can_soft_delete_task_with_invoices()
    {
        $admin = $this->createAdminUser();
        
        $country = Country::factory()->create();
        $userCompany = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create([
            'user_id' => $userCompany->id,
            'country_id' => $country->id,
        ]);
        
        $branch = Branch::factory()->create([
            'user_id' => $userCompany->id,
            'company_id' => $company->id,
        ]);

        $userAgent = User::factory()->create(['role_id' => Role::AGENT]);
        $agent = Agent::factory()->create([
            'user_id' => $userAgent->id,
            'branch_id' => $branch->id,
            'type_id' => 1
        ]);
        
        $supplier = Supplier::factory()->create(['country_id' => $country->id]);

        $client = Client::factory()->create([
            'agent_id' => $agent->id
        ]);
        
        $task = Task::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'supplier_id' => $supplier->id,
            'reference' => 'TEST-REF-001',
            'total' => 1000.00,
            'type' => 'flight',
        ]);

        $country = Country::factory()->create();

        // Create related data
        TaskFlightDetail::factory()->create([
            'task_id' => $task->id,
            'country_id_to' => $country->id,
            'country_id_from' => $country->id
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $task->client_id,
            'agent_id' => $task->agent_id,
            'country_id' => $country->id,
            'invoice_number' => 'INV-001',
            'sub_amount' => 1000.00,
            'amount' => 1000.00
        ]);

        InvoiceDetail::factory()->create([
            'task_id' => $task->id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_id' => $invoice->id,
        ]);


        $this->actingAs($admin);

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
        // Create admin role and user
        $admin = $this->createAdminUser();
        
        // Create basic dependencies
        $country = Country::factory()->create();
        $userCompany = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create([
            'user_id' => $userCompany->id,
            'country_id' => $country->id,
        ]);
        
        $branch = Branch::factory()->create([
            'user_id' => $userCompany->id,
            'company_id' => $company->id,
        ]);

        $userAgent = User::factory()->create(['role_id' => Role::AGENT]);
        $agent = Agent::factory()->create([
            'user_id' => $userAgent->id,
            'branch_id' => $branch->id,
            'type_id' => 1
        ]);
        
        $supplier = Supplier::factory()->create(['country_id' => $country->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        
        // Create task
        $task = Task::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'supplier_id' => $supplier->id,
            'reference' => 'COMPLEX-TASK-001',
            'total' => 2500.00,
            'type' => 'flight',
        ]);

        // 1. Create TaskFlightDetail
        $flightDetail = TaskFlightDetail::factory()->create([
            'task_id' => $task->id,
            'country_id_to' => $country->id,
            'country_id_from' => $country->id
        ]);

        // Create Hotel for TaskHotelDetail
        $hotel = Hotel::create([
            'name' => 'Test Hotel',
            'address' => '123 Test Street',
            'city' => 'Test City',
            'country' => $country->name,
            'phone' => '+1234567890',
            'email' => 'test@hotel.com',
            'rating' => 4,
        ]);

        // 2. Create TaskHotelDetail
        $hotelDetail = TaskHotelDetail::factory()->create([
            'task_id' => $task->id,
            'hotel_id' => $hotel->id,
        ]);

        // 3. Create Invoice
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'country_id' => $country->id,
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
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'created_by' => $admin->id,
            'account_id' => null, // Assuming no account is linked for simplicity
            'amount' => 1500.00,
            'payment_date' => now(),
        ]);

                // 6. Create Transaction related to invoice
        $invoiceTransaction = Transaction::create([
            'invoice_id' => $invoice->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'amount' => 2500.00,
            'transaction_type' => 'credit',
            'status' => 'completed',
            'user_id' => $admin->id,
            'description' => 'Invoice transaction',
            'currency' => 'USD',
        ]);

        // Create Account for JournalEntry (Accounts Receivable - Clients)
        // First create the parent "Assets" account
        $assetsAccount = Account::create([
            'code' => '1000',
            'name' => 'Assets',
            'level' => 1,
            'parent_id' => null,
            'root_id' => null,
            'company_id' => $company->id,
            'account_type' => null,
            'report_type' => 'balance sheet',
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
        ]);

        // Create "Accounts Receivable" account
        $accountsReceivableAccount = Account::create([
            'code' => '1350',
            'name' => 'Accounts Receivable',
            'level' => 2,
            'parent_id' => $assetsAccount->id,
            'root_id' => $assetsAccount->id,
            'company_id' => $company->id,
            'account_type' => null,
            'report_type' => 'balance sheet',
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
        ]);

        // Create "Clients" account under Accounts Receivable
        $clientsAccount = Account::create([
            'code' => '1351',
            'name' => 'Clients',
            'level' => 3,
            'parent_id' => $accountsReceivableAccount->id,
            'root_id' => $assetsAccount->id,
            'company_id' => $company->id,
            'client_id' => $client->id, // Link to specific client
            'account_type' => null,
            'report_type' => 'balance sheet',
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
        ]);

        // 7. Create JournalEntry related to task (using proper Accounts Receivable account)
        $journalEntry = JournalEntry::create([
            'task_id' => $task->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id, // Add branch_id
            'account_id' => $clientsAccount->id, // Use the Clients account
            'transaction_date' => now(),
            'description' => 'Client receivable for task booking',
            'name' => 'Client Receivable Entry', // Required field
            'debit' => 2500.00, // Debit to Accounts Receivable (asset increases)
            'credit' => 0,
            'balance' => 2500.00,
            'amount' => 2500.00, // Add amount field
            'type' => 'debit',
            'currency' => 'USD',
            'exchange_rate' => 1.0,
        ]);

        // 8. Create Transaction related to journal entry
        $journalTransaction = Transaction::create([
            'company_id' => $company->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'branch_id' => $branch->id,
            'amount' => 2500.00,
            'transaction_type' => 'debit',
            'description' => 'Transaction for client receivable',
            'name' => 'Journal Entry Transaction',
        ]);

        // Update journal entry with transaction ID
        $journalEntry->update(['transaction_id' => $journalTransaction->id]);

        // Authenticate as admin
        $this->actingAs($admin);

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
        $this->assertDatabaseHas('clients', ['id' => $client->id]);
        $this->assertDatabaseHas('agents', ['id' => $agent->id]);
        $this->assertDatabaseHas('companies', ['id' => $company->id]);
        $this->assertDatabaseHas('countries', ['id' => $country->id]);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }
}
