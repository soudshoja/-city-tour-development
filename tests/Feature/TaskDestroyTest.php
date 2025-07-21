<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskDestroyTest extends TestCase
{
    use RefreshDatabase;

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
        
        // Create necessary roles
        Role::create(['id' => Role::ADMIN, 'name' => 'admin', 'guard_name' => 'web']);
        Role::create(['id' => Role::COMPANY, 'name' => 'company', 'guard_name' => 'web']);
    }

    public function test_admin_can_soft_delete_task()
    {
        // Create admin user
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        
        // Create task using factory which should handle all dependencies
        $task = Task::factory()->create([
            'reference' => 'TEST-REF-001',
            'total' => 1000.00,
            'type' => 'hotel',
            'status' => 'issued'
        ]);

        // Authenticate as admin
        $this->actingAs($admin);

        // Call destroy method
        $response = $this->delete(route('tasks.destroy', $task->id));

        // Assert response
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => "Task 'TEST-REF-001' and all related data have been soft deleted successfully."
                ]);

        // Assert task is soft deleted
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        
        // Assert record still exists in database but is marked as deleted
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
}
