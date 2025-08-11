<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\Company;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ExpiredConfirmedTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_confirmed_task_becomes_void()
    {
        // Create test data
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();
        
        $task = Task::factory()->create([
            'status' => 'confirmed',
            'reference' => 'TEST001',
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'expiry_date' => Carbon::now()->subHour(), // Already expired
        ]);

        // Run the command
        Artisan::call('tasks:process-expired-confirmed');

        // Assert the task status changed to void
        $task->refresh();
        $this->assertEquals('void', $task->status);
    }

    public function test_multiple_expired_confirmed_tasks_become_void()
    {
        // Create test data
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();
        
        $task1 = Task::factory()->create([
            'status' => 'confirmed',
            'reference' => 'TEST002',
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'expiry_date' => Carbon::now()->subHour(), // Already expired
        ]);

        $task2 = Task::factory()->create([
            'status' => 'confirmed',
            'reference' => 'TEST003',
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'expiry_date' => Carbon::now()->subMinutes(30), // Already expired
        ]);

        // Run the command
        Artisan::call('tasks:process-expired-confirmed');

        // Assert both tasks status changed to void
        $task1->refresh();
        $task2->refresh();
        $this->assertEquals('void', $task1->status);
        $this->assertEquals('void', $task2->status);
    }

    public function test_non_expired_confirmed_tasks_are_not_processed()
    {
        // Create test data
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();
        
        $task = Task::factory()->create([
            'status' => 'confirmed',
            'reference' => 'TEST004',
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'expiry_date' => Carbon::now()->addHour(), // Not expired yet
        ]);

        // Run the command
        Artisan::call('tasks:process-expired-confirmed');

        // Assert the task status remains unchanged
        $task->refresh();
        $this->assertEquals('confirmed', $task->status);
    }

    public function test_issued_tasks_are_ignored()
    {
        // Create test data
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();
        
        $task = Task::factory()->create([
            'status' => 'issued',
            'reference' => 'TEST005',
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'expiry_date' => Carbon::now()->subHour(), // Already expired but issued
        ]);

        // Run the command
        Artisan::call('tasks:process-expired-confirmed');

        // Assert the task status remains unchanged
        $task->refresh();
        $this->assertEquals('issued', $task->status);
    }

    public function test_dry_run_mode_does_not_change_task_status()
    {
        // Create test data
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();
        
        $task = Task::factory()->create([
            'status' => 'confirmed',
            'reference' => 'TEST006',
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'expiry_date' => Carbon::now()->subHour(), // Already expired
        ]);

        // Run the command in dry-run mode
        Artisan::call('tasks:process-expired-confirmed', ['--dry-run' => true]);

        // Assert the task status remains unchanged
        $task->refresh();
        $this->assertEquals('confirmed', $task->status);
    }
}
