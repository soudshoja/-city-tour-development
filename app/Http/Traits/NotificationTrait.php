<?php

namespace App\Http\Traits;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Agent;
use Illuminate\Support\Facades\Auth;

trait NotificationTrait
{
    public function storeNotification($data)
    {
        $notification = new Notification();
        $notification->user_id = $data['user_id'];
        $notification->title = $data['title'];
        $notification->message = $data['message'];
        $notification->type = $data['type'] ?? null;
        $notification->data = isset($data['data']) ? (is_string($data['data']) ? $data['data'] : json_encode($data['data'])) : null;
        $notification->save();
        return $notification;
    }

    public function getNotifications()
    {
        $user = Auth::user();

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
        $user = Auth::user();
        
        switch ($user->role_id) {
            case Role::ADMIN:
                return Notification::limit($limit)->latest()->get();
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
        $user = Auth::user();

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
        $user = Auth::user();

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
        if (!$user->company) {
            return [$user->id]; // Return only user's ID if no company
        }
        
        $branches = $user->company->branches ?? collect();
        $branchIds = $branches->pluck('id')->toArray();
        $branchesUserId = $branches->pluck('user_id')->toArray();
        $agentUserIds = Agent::whereIn('branch_id', $branchIds)->pluck('user_id')->toArray();
        $userIds = array_merge($branchesUserId, $agentUserIds, [$user->id]);

        return $userIds;
    }

    private function getBranchUserIds($user)
    {
        if (!$user->branch) {
            return [$user->id]; // Return only user's ID if no branch
        }
        
        $agentUserIds = $user->branch->agents()->pluck('user_id')->toArray();
        $userIds = array_merge($agentUserIds, [$user->id]);

        return $userIds;
    }

    public function storeNotificationWithSendingPdf(){

    }
}
