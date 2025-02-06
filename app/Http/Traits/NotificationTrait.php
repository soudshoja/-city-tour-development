<?php

namespace App\Http\Traits;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Agent;

trait NotificationTrait
{
    public function storeNotification($data)
    {
        $notification = new Notification();
        $notification->user_id = $data['user_id'];
        $notification->title = $data['title'];
        $notification->message = $data['message'];
        $notification->save();
        return $notification;
    }

    public function getNotifications()
    {
        $user = auth()->user();

        switch ($user->role_id) {
            case Role::ADMIN:
                return Notification::all();
            case Role::COMPANY: 
                return Notification::whereIn('user_id', $this->getCompanyUserIds($user))->get();
            case Role::BRANCH:
                return Notification::whereIn('user_id', $this->getBranchUserIds($user))->get();
            case Role::AGENT:
                return Notification::where('user_id', $user->id)->get();
            default:
                return [];
        }
    }

    public function getLimitNotifications($limit)
    {
        $user = auth()->user();
        
        switch ($user->role_id) {
            case Role::ADMIN:
                return Notification::limit($limit)->get();
            case Role::COMPANY: 
                return Notification::whereIn('user_id', $this->getCompanyUserIds($user))->where('close', 0)->limit($limit)->latest()->get();
            case Role::BRANCH:
                return Notification::whereIn('user_id', $this->getBranchUserIds($user))->where('close', 0)->limit($limit)->latest()->get();
            case Role::AGENT:
                return Notification::where('user_id', $user->id)->limit($limit)->latest()->where('close', 0)->get();
            default:
                return [];
        }
    }

    public function getReadNotifications()
    {
        $user = auth()->user();

        switch ($user->role_id) {
            case Role::ADMIN:
                return Notification::where('status', 'read')->get();
            case Role::COMPANY:
                return Notification::whereIn('user_id', $this->getCompanyUserIds($user))->where('status', 'read')->where('close', 0)->latest()->limit(10)->get();
            case Role::BRANCH:
                return Notification::whereIn('user_id', $this->getBranchUserIds($user))->where('status', 'read')->where('close', 0)->latest()->limit(10)->get();
            case Role::AGENT:
                return Notification::where('user_id', $user->id)->where('status', 'read')->where('close', 0)->latest()->limit(10)->get();
            default:
                return [];
    }
    }

    public function getUnreadNotifications()
    {
        $user = auth()->user();

        switch ($user->role_id) {
            case Role::ADMIN:
                return Notification::where('status', 'unread')->get();
            case Role::COMPANY:
                return Notification::whereIn('user_id', $this->getCompanyUserIds($user))->where('status', 'unread')->where('close', 0)->latest()->limit(10)->get();
            case Role::BRANCH:
                return Notification::whereIn('user_id', $this->getBranchUserIds($user))->where('status', 'unread')->where('close', 0)->latest()->limit(10)->get();
            case Role::AGENT:
                return Notification::where('user_id', $user->id)->where('status', 'unread')->where('close', 0)->latest()->limit(10)->get();
            default:
                return [];
        }
    }

    private function getCompanyUserIds($user)
    {
        $branches = $user->company->branches;
        $branchIds = $branches->pluck('id')->toArray();
        $branchesUserId = $branches->pluck('user_id')->toArray();
        $agentUserIds = Agent::whereIn('branch_id', $branchIds)->pluck('user_id')->toArray();
        $userIds = array_merge($branchesUserId, $agentUserIds, [$user->id]);

        return $userIds;
    }

    private function getBranchUserIds($user)
    {
        // $agentIds = $user->branch->agents->pluck('id')->toArray();
        $agentIds = [10, 11, 12];
        $userIds = array_merge($agentIds, [$user->id]);

        return $userIds;
    }
}
