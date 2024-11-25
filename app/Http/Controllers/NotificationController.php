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
    public function create(){

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
