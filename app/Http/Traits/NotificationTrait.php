<?php

namespace App\Http\Traits;
use App\Models\Notification;
trait NotificationTrait
{
   public function storeNotification($data){
        $notification = new Notification();
        $notification->user_id = $data['user_id'];
        $notification->title = $data['title'];
        $notification->message = $data['message'];
        $notification->save();
        return $notification;
    }

    public function getNotifications(){
        $notifications = Notification::with('user.agent.branch.company')->get();
        if (request()->ajax()) {
            return response()->json($notifications);
        }
        return $notifications;
    }
}
