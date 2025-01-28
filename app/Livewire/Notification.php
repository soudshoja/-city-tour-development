<?php

namespace App\Livewire;

use App\Http\Traits\NotificationTrait;
use App\Models\Agent;
use App\Models\Notification as ModelsNotification;
use App\Models\Role;
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
        $this->totalCount = ModelsNotification::count();
        $this->readCount = ModelsNotification::where('status', 'read')->count();
        $this->unreadCount = ModelsNotification::where('status', 'unread')->count();

        $this->getNotification();
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

        $this->getNotification();
        session()->flash('message', 'All notifications marked as read.');
    }


    public function render()
    {
        $this->getNotification();
        return view('livewire.notification');
    }
}
