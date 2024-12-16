<?php

namespace Tests\Feature;

use App\Http\Controllers\NotificationController;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Notification;
use App\Models\User;
use Database\Factories\NotificationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_see_notifications()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->get('/notifications');

        $response->assertStatus(200);
    }

    /**
     * Get NotifcationList
     * 
     * @return array
     */
    public function test_get_notifications()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
         
        $controller = new NotificationController();
        Notification::factory()->count(5)->create();
        $notifications = $controller->getNotifications();

        $this->assertIsArray($notifications);
    }

    /**
     * Get NotifcationList
     * 
     * @return Response
     */
    public function test_company_notifications(){
        $user = User::factory()->create([
            'role_id' => 2
        ]);

        $this->actingAs($user);

        $companyId = Company::factory()->create([
            'id' => $user->company_id
        ])->id;

        $users = User::factory(10)->create([
            'role_id' => 2,
        ]);
        $companyUsersId = $users->pluck('id')->toArray();
        Company::factory()->count(10)->create([
            'user_id' => $companyUsersId[array_rand($companyUsersId)]
        ]);

        $companiesId = Company::pluck('id')->toArray();
        
        array_push($companiesId, $companyId);


        $users = User::factory(10)->create([
            'role_id' => 6,
        ]);

        $usersId = $users->pluck('id');

        $branches = Branch::factory()->count(10)->create([
            'company_id' => $companiesId[array_rand($companiesId)],
            'user_id' => $usersId->random(),
        ]);

        $branchesId = $branches->pluck('id')->toArray();
        $userAgents = User::factory()->count(10)->create([
            'role_id' => 3
        ]);

        $userAgentsId = $userAgents->pluck('id')->toArray();

        Agent::factory()->count(10)->create([
            'branch_id' => $branchesId[array_rand($branchesId)],
            'user_id' => $userAgentsId[array_rand($userAgentsId)]
        ]);

        $notificationsFactory = Notification::factory()->count(50)->create(
            [
                'user_id' => $userAgentsId[array_rand($userAgentsId)]
            ]
        );

        //filter notifications by company where the user_id == 5
        $result = array_filter($notificationsFactory->toArray(), function($notification){
            dump($notification['user_id']);
            return $notification['user_id'] == 9;
        });

        dd($result);

        $controller = new NotificationController();
        $notifications = $controller->getNotifications();

        $this->assertIsArray($notifications);
        $this->assertEquals($notificationsFactory->count(), count($notifications));
    }

}
