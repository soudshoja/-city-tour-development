<?php

namespace App\Livewire;

use App\Http\Traits\NotificationTrait;
use App\Models\Agent;
use App\Models\Notification as ModelsNotification;
use App\Models\Role;
use App\Models\ClientAssignmentRequest;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

class Notification extends Component
{
    use NotificationTrait;

    public $notifications;
    public $totalCount;
    public $readCount;
    public $unreadCount;
    public $filter = 'all';

    public function mount()
    {
        $this->updateCounts();
        $this->getNotification();
    }

    private function baseScope(): Builder
    {
        return ModelsNotification::query()->where('user_id', Auth::id());
    }

    /**
     * Update notification counts (scoped to current user)
     */
    public function updateCounts()
    {
        $base = $this->baseScope();
        $this->totalCount = (clone $base)->count();
        $this->readCount = (clone $base)->where('status','read')->count();
        $this->unreadCount = (clone $base)->where('status','unread')->count();
    }

    /**
     * Get the notification for the user
     *
     * @return void
     */
    public function getNotification()
    {
        $q = $this->baseScope()->latest();
        if ($this->filter === 'read') {
            $q->where('status','read');
        } elseif ($this->filter === 'unread') {
            $q->where('status','unread');
        }
        $this->notifications = $q->limit(10)->get();
    }

    public function close($id)
    {
        $notification = ModelsNotification::find($id);
        $notification->close = 1;
        $notification->save();
        $this->getNotification();
    }

    public function updateFilter($filter)
    {
        $this->filter = $filter;
        $this->getNotification();
    }

    public function markAllAsRead()
    {
        $this->baseScope()->where('status','unread')->update(['status' => 'read']);
        $this->updateCounts();
        $this->getNotification();
        session()->flash('message', 'All notifications marked as read.');
    }

    public function markAsRead($id)
    {
        $this->baseScope()->whereKey($id)->update(['status' => 'read']);
        $this->updateCounts();
        $this->getNotification();
    }

    /**
     * Check if assignment request is still pending
     */
    public function isAssignmentRequestPending($token)
    {
        if (!$token) return false;
        
        return ClientAssignmentRequest::byToken($token)->active()->exists();
    }

    /**
     * Get assignment request status
     */
    public function getAssignmentRequestStatus($token)
    {
        if (!$token) return null;
        
        return ClientAssignmentRequest::byToken($token)->first();
    }


    public function render()
    {
        // Update expired requests before rendering
        $this->markExpiredRequests();
        $this->getNotification();
        return view('livewire.notification');
    }

    /**
     * Mark expired assignment requests
     */
    private function markExpiredRequests()
    {
        ClientAssignmentRequest::markAllExpired();
    }
}
