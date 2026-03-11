<?php

namespace Tests\Feature;

use App\Http\Traits\NotificationTrait;
use App\Livewire\Notification as LivewireNotification;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $companyUser;
    protected $branchUser;
    protected $agentUser;
    protected $company;
    protected $branch;
    protected $agent;
    protected $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('agent_type')->insert([
            'id' => 1,
            'name' => 'Test Agent Type',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'name' => 'Company User',
            'email' => 'company@test.com'
        ]);

        $this->company = Company::factory()->create([
            'id' => 1,
            'name' => 'Test Company',
            'status' => 1,
            'user_id' => $this->companyUser->id
        ]);
        session(['company_id' => $this->company->id]);

        DB::table('accounts')->insert([
            'id' => 1,
            'name' => 'Test Account',
            'code' => 'TEST001',
            'level' => 1,
            'actual_balance' => 0.00,
            'budget_balance' => 0.00,
            'variance' => 0.00,
            'company_id' => $this->company->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web', 'company_id' => $this->company->id]);
        Role::create(['name' => 'company', 'guard_name' => 'web', 'company_id' => $this->company->id]);
        Role::create(['name' => 'branch', 'guard_name' => 'web', 'company_id' => $this->company->id]);
        Role::create(['name' => 'agent', 'guard_name' => 'web', 'company_id' => $this->company->id]);

        $this->adminUser = User::factory()->create(['role_id' => Role::ADMIN]);

        $this->branchUser = User::factory()->create([
            'role_id' => Role::BRANCH,
            'name' => 'Branch User',
            'email' => 'branch@test.com'
        ]);

        $this->branch = Branch::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->branchUser->id
        ]);

        $this->agentUser = User::factory()->create([
            'role_id' => Role::AGENT,
            'name' => 'Agent User',
            'email' => 'agent@test.com'
        ]);

        $this->agent = Agent::factory()->create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->agentUser->id,
            'type_id' => 1,
            'account_id' => 1
        ]);
    }

    private function getTestController()
    {
        return new class {
            use NotificationTrait;
        };
    }

    public function test_display_notification_page()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('notifications.index'));
        $response->assertStatus(200);
        $response->assertSee('Notifications');
    }

    public function test_store_notification_creates_notification_correctly()
    {
        $controller = $this->getTestController();

        $data = [
            'user_id' => $this->adminUser->id,
            'title' => 'Test Notification',
            'message' => 'This is a test notification message'
        ];

        $notification = $controller->storeNotification($data);

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals($data['user_id'], $notification->user_id);
        $this->assertEquals($data['title'], $notification->title);
        $this->assertEquals($data['message'], $notification->message);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $data['user_id'],
            'title' => $data['title'],
            'message' => $data['message']
        ]);
    }

    public function test_admin_can_get_all_notifications()
    {
        Notification::factory()->count(5)->create(['user_id' => $this->adminUser->id]);

        $this->actingAs($this->adminUser);

        $controller = $this->getTestController();
        $notifications = $controller->getNotifications();

        $this->assertCount(5, $notifications);
    }

    public function test_agent_can_only_get_own_notifications()
    {
        Notification::factory()->count(3)->create(['user_id' => $this->agentUser->id]);
        Notification::factory()->count(2)->create(['user_id' => $this->adminUser->id]);

        $this->actingAs($this->agentUser);

        $controller = $this->getTestController();
        $notifications = $controller->getNotifications();

        $this->assertCount(3, $notifications);
        foreach ($notifications as $notification) {
            $this->assertEquals($this->agentUser->id, $notification->user_id);
        }
    }

    public function test_company_user_can_get_company_notifications()
    {
        Notification::factory()->create(['user_id' => $this->companyUser->id]);
        Notification::factory()->create(['user_id' => $this->branchUser->id]);
        Notification::factory()->create(['user_id' => $this->agentUser->id]);
        Notification::factory()->create(['user_id' => $this->adminUser->id]);

        $this->actingAs($this->companyUser);

        $controller = $this->getTestController();
        $notifications = $controller->getNotifications();

        $this->assertCount(3, $notifications);
    }

    public function test_get_limit_notifications_respects_limit()
    {
        Notification::factory()->count(10)->create(['user_id' => $this->adminUser->id]);

        $this->actingAs($this->adminUser);

        $controller = $this->getTestController();
        $notifications = $controller->getLimitNotifications(3);

        $this->assertCount(3, $notifications);
    }

    public function test_get_read_notifications_filters_correctly()
    {
        Notification::factory()->read()->count(3)->create(['user_id' => $this->adminUser->id]);
        Notification::factory()->unread()->count(2)->create(['user_id' => $this->adminUser->id]);

        $this->actingAs($this->adminUser);

        $controller = $this->getTestController();
        $readNotifications = $controller->getReadNotifications();

        $this->assertCount(3, $readNotifications);
        foreach ($readNotifications as $notification) {
            $this->assertEquals('read', $notification->status);
        }
    }

    public function test_get_unread_notifications_filters_correctly()
    {
        Notification::factory()->read()->count(2)->create(['user_id' => $this->adminUser->id]);
        Notification::factory()->unread()->count(4)->create(['user_id' => $this->adminUser->id]);

        $this->actingAs($this->adminUser);

        $controller = $this->getTestController();
        $unreadNotifications = $controller->getUnreadNotifications();

        $this->assertCount(4, $unreadNotifications);
        foreach ($unreadNotifications as $notification) {
            $this->assertEquals('unread', $notification->status);
        }
    }

    public function test_unauthenticated_user_gets_empty_notifications()
    {
        $controller = $this->getTestController();

        $notifications = $controller->getNotifications();
        $this->assertEmpty($notifications);
    }

    public function test_livewire_notification_component_mounts_correctly()
    {
        Notification::factory()->read()->count(5)->create(['user_id' => $this->adminUser->id]);
        Notification::factory()->unread()->count(3)->create(['user_id' => $this->adminUser->id]);

        $this->actingAs($this->adminUser);

        Livewire::test(LivewireNotification::class)
            ->assertSet('totalCount', 8)
            ->assertSet('readCount', 5)
            ->assertSet('unreadCount', 3)
            ->assertSet('filter', 'all');
    }

    public function test_livewire_notification_filter_works()
    {
        Notification::factory()->read()->count(3)->create(['user_id' => $this->adminUser->id]);
        Notification::factory()->unread()->count(2)->create(['user_id' => $this->adminUser->id]);

        $this->actingAs($this->adminUser);

        $component = Livewire::test(LivewireNotification::class);

        $component->call('updateFilter', 'read')
            ->assertSet('filter', 'read');

        $component->call('updateFilter', 'unread')
            ->assertSet('filter', 'unread');

        $component->call('updateFilter', 'all')
            ->assertSet('filter', 'all');
    }

    public function test_livewire_notification_close_functionality()
    {
        $notification = Notification::factory()->create(['close' => 0, 'user_id' => $this->adminUser->id]);

        $this->actingAs($this->adminUser);

        Livewire::test(LivewireNotification::class)
            ->call('close', $notification->id);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'close' => 1
        ]);
    }

    public function test_livewire_notification_mark_all_as_read()
    {
        Notification::factory()->unread()->count(5)->create(['user_id' => $this->adminUser->id]);

        $this->actingAs($this->adminUser);

        Livewire::test(LivewireNotification::class)
            ->call('markAllAsRead');

        $unreadCount = Notification::where('status', 'unread')->count();
        $this->assertEquals(0, $unreadCount);

        $readCount = Notification::where('status', 'read')->count();
        $this->assertEquals(5, $readCount);
    }

    public function test_livewire_notification_renders_correctly()
    {
        $this->actingAs($this->adminUser);

        Livewire::test(LivewireNotification::class)
            ->assertViewIs('livewire.notification');
    }

    public function test_livewire_notification_with_agent_role()
    {
        Notification::factory()->count(3)->create(['user_id' => $this->agentUser->id]);
        Notification::factory()->count(2)->create(['user_id' => $this->adminUser->id]);

        $this->actingAs($this->agentUser);

        $component = Livewire::test(LivewireNotification::class);

        $component->assertSet('totalCount', 3);
    }

    public function test_livewire_notification_with_company_role()
    {
        Notification::factory()->create(['user_id' => $this->companyUser->id]);
        Notification::factory()->create(['user_id' => $this->branchUser->id]);
        Notification::factory()->create(['user_id' => $this->agentUser->id]);

        $this->actingAs($this->companyUser);

        Livewire::test(LivewireNotification::class)
            ->assertViewIs('livewire.notification');
    }
}
