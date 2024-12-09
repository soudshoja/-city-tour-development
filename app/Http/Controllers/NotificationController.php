<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(){

        return view('notifications.index', ['notifications' => $this->getNotifications()]);
    }

    public function getNotifications(){
            // $notifications = auth()->user()->notification->toArray();
        $notifications = Notification::with('user.agent.branch.company')->get();
        if (request()->ajax()) {
            return response()->json($notifications);
        }
        return $notifications;
    }
    public function store($data){

        $notification = new Notification();
        $notification->user_id = $data['user_id'];
        $notification->title = $data['title'];
        $notification->message = $data['message'];
        $notification->save();
        return $notification;

    }
    
    public function edit(){

    }

    public function update(){

    }

    public function delete(){

    }
    public function destroy(){

    }
}
