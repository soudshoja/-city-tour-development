<?php

namespace App\Livewire;

use App\Http\Traits\NotificationTrait;
use App\Models\Notification;
use App\View\Components\AppLayout;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout(AppLayout::class)]
class NotificationIndex extends Component
{
    use NotificationTrait;

    public $notifications;

    /**
     * Mark the notification as read
     *
     * @param [type] $notificationId
     * @return void
     */
    public function markAsRead($notificationId)
    {
        $notification = Notification::find($notificationId);
        $notification->update(['status' => 'read']);

        $this->notifications = $this->getNotifications();
    }


    public function render()
    {
        $this->notifications = $this->getNotifications();
        return view('livewire.notification-index');
    }
}
