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

    /**
     * Update notification counts
     */
    public function updateCounts()
    {
        $this->totalCount = ModelsNotification::count();
        $this->readCount = ModelsNotification::where('status', 'read')->count();
        $this->unreadCount = ModelsNotification::where('status', 'unread')->count();
    }

    /**
     * Get the notification for the user
     *
     * @return void
     */
    public function getNotification()
    {
        if ($this->filter == 'read') {
            $this->notifications = $this->getReadNotifications();
        } elseif ($this->filter == 'unread') {
            $this->notifications = $this->getUnreadNotifications();
        } else {
            $this->notifications = $this->getLimitNotifications(10);
        }
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
        ModelsNotification::where('status', 'unread')->update(['status' => 'read']);
        $this->updateCounts();
        $this->getNotification();
        session()->flash('message', 'All notifications marked as read.');
    }

    public function markAsRead($id)
    {
        $notification = ModelsNotification::find($id);
        if ($notification) {
            $notification->status = 'read';
            $notification->save();
            $this->updateCounts();
            $this->getNotification();
        }
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
