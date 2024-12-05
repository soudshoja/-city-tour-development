<?php

namespace App\Livewire;

use App\Models\Agent;
use App\Models\Notification as ModelsNotification;
use App\Models\Role;
use Livewire\Component;

class Notification extends Component
{
    public $notifications;
    public $filter = '';

    /**
     * Get the notification for the user
     *
     * @return void
     */
    public function getNotification()
    {

        $user = auth()->user();
        $userRole = $user->role_id;

        if ($userRole == Role::ADMIN) {
            $notifications = ModelsNotification::all();
        } elseif ($userRole == Role::COMPANY) {
            $usersId = array();

            $branches = $user->company->branches;
            
            $branchId = $branches->pluck('id')->toArray();
            $branchUserid = $branches->pluck('user_id')->toArray();
            $agents = Agent::whereIn('branch_id', $branchId)->get(); 
            $agentsId = $agents->pluck('id')->toArray();
            $agentsUserId = $agents->pluck('user_id')->toArray();
            $usersId = array_merge($branchUserid, $agentsUserId);
            $usersId[] = $user->id;
            
            $notifications = ModelsNotification::whereIn('user_id', $usersId);
            if ($this->filter) {
                $notifications = $notifications->where('status', $this->filter);
            } 
            $notifications =  $notifications->latest()->limit(10)->get();
        } elseif ($userRole == Role::BRANCH) {

            $usersId = array();

            $agentsId = $user->agents->pluck('id')->toArray();

            $usersId = array_merge($agentsId, $userRole->id);

            $notifications= ModelsNotification::whereIn('user_id', $usersId)->get()->toArray();

        } elseif($userRole == Role::AGENT) {
            $notifications = ModelsNotification::where('user_id', $user->id)->get()->toArray();
        }

        $this->notifications = $notifications;

    }

    public function render()
    {
        $this->getNotification();
        return view('livewire.notification');
    }
}
