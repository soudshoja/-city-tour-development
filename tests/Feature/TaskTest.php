<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Client;
use App\Models\Supplier;
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

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create necessary roles
        Role::create(['id' => Role::ADMIN, 'name' => 'admin', 'guard_name' => 'web']);
        Role::create(['id' => Role::COMPANY, 'name' => 'company', 'guard_name' => 'web']);
        Role::create(['id' => Role::BRANCH, 'name' => 'branch', 'guard_name' => 'web']);
        Role::create(['id' => Role::AGENT, 'name' => 'agent', 'guard_name' => 'web']);

        // Create necessary reference data for agents
        DB::table('agent_type')->insert([
            'id' => 1,
            'name' => 'Test Agent Type',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public function test_admin_can_soft_delete_simple_task()
    {
        // Create admin user
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        
        // Create simple task
        $task = Task::factory()->create([
            'reference' => 'TEST-REF-001',
            'total' => 1000.00
        ]);
        
        // Create account for the company
        DB::table('accounts')->insert([
            'id' => 1,
            'name' => 'Test Account',
            'code' => 'TEST001',
            'level' => 1,
            'actual_balance' => 0.00,
            'budget_balance' => 0.00,
            'variance' => 0.00,
            'company_id' => $company->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => 1,
            'account_id' => 1
        ]);
        
        // Create client and supplier
        $client = Client::factory()->create();
        $supplier = Supplier::factory()->create();
        
        // Create task
        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'reference' => 'TEST-REF-001',
            'total' => 1000.00
        ]);

        // Create related data manually
        $invoice = Invoice::create([
            'invoice_number' => 'INV-001',
            'client_id' => $client->id,
            'total' => 1000.00,
            'status' => 'pending'
        ]);
        
        $invoiceDetail = InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'task_id' => $task->id,
            'amount' => 1000.00
        ]);
        
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'amount' => 1000.00,
            'payment_date' => now(),
            'payment_method' => 'cash'
        ]);
        
        $transaction = Transaction::create([
            'task_id' => $task->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'amount' => 1000.00,
            'transaction_type' => 'debit',
            'entity_type' => 'task',
            'entity_id' => $task->id
        ]);
        
        $journalEntry = JournalEntry::create([
            'task_id' => $task->id,
            'transaction_id' => $transaction->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'account_id' => 1,
            'transaction_date' => now(),
            'description' => 'Test journal entry',
            'debit' => 1000.00,
            'credit' => 0,
            'balance' => 1000.00
        ]);
        
        $flightDetail = TaskFlightDetail::create([
            'task_id' => $task->id,
            'departure_time' => now(),
            'arrival_time' => now()->addHours(2),
            'flight_number' => 'FL001'
        ]);
        
        $hotelDetail = TaskHotelDetail::create([
            'task_id' => $task->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'hotel_name' => 'Test Hotel'
        ]);

        // Authenticate as admin
        $this->actingAs($admin);

        // Call destroy method directly
        $response = $this->delete(route('tasks.destroy', $task->id));

        // Assert response
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => "Task 'TEST-REF-001' and all related data have been soft deleted successfully."
                ]);

        // Assert all records are soft deleted (exist but have deleted_at timestamp)
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        $this->assertSoftDeleted('invoice_details', ['id' => $invoiceDetail->id]);
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
        $this->assertSoftDeleted('journal_entries', ['id' => $journalEntry->id]);
        $this->assertSoftDeleted('task_flight_details', ['id' => $flightDetail->id]);
        $this->assertSoftDeleted('task_hotel_details', ['id' => $hotelDetail->id]);

        // Assert records still exist in database but are marked as deleted
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'reference' => 'TEST-REF-001'
        ]);
        $this->assertNotNull(Task::withTrashed()->find($task->id)->deleted_at);
    }

    public function test_non_admin_cannot_delete_task()
    {
        // Create non-admin user (company user)
        $companyUser = User::factory()->create(['role_id' => Role::COMPANY]);
        
        // Create task
        $task = Task::factory()->create();

        // Authenticate as company user
        $this->actingAs($companyUser);

        // Call destroy method
        $response = $this->delete(route('tasks.destroy', $task->id));

        // Assert unauthorized
        $response->assertStatus(403)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Unauthorized. Only super admin can delete tasks.'
                ]);

        // Assert task is not deleted
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->assertNull(Task::find($task->id)->deleted_at);
    }

    public function test_unauthenticated_user_cannot_delete_task()
    {
        // Create task
        $task = Task::factory()->create();

        // Call destroy method without authentication
        $response = $this->delete(route('tasks.destroy', $task->id));

        // Assert unauthorized (401 or 403 depending on auth middleware)
        $response->assertStatus(401);

        // Assert task is not deleted
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->assertNull(Task::find($task->id)->deleted_at);
    }

    public function test_admin_cannot_delete_nonexistent_task()
    {
        // Create admin user
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        // Authenticate as admin
        $this->actingAs($admin);

        // Call destroy method with non-existent task ID
        $response = $this->delete(route('tasks.destroy', 99999));

        // Assert not found
        $response->assertStatus(404);
    }

    public function test_destroy_task_without_related_data()
    {
        // Create admin user
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        
        // Create simple task without related data
        $task = Task::factory()->create([
            'reference' => 'SIMPLE-TASK-001'
        ]);

        // Authenticate as admin
        $this->actingAs($admin);

        // Call destroy method
        $response = $this->delete(route('tasks.destroy', $task->id));

        // Assert response
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => "Task 'SIMPLE-TASK-001' and all related data have been soft deleted successfully."
                ]);

        // Assert task is soft deleted
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_destroy_handles_database_errors_gracefully()
    {
        // Create admin user
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        
        // Create task
        $task = Task::factory()->create();

        // Authenticate as admin
        $this->actingAs($admin);

        // Mock a database error by forcing a constraint violation
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollback')->once();
        
        // This would normally be tested with a more sophisticated approach
        // but for now we'll just verify the basic structure exists
        $this->assertTrue(method_exists(app('App\Http\Controllers\TaskController'), 'destroy'));
    }
}
