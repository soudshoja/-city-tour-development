<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Client;
use App\Models\Item;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{


    public function index() {}

    public function show(TaskController $taskController, $id)
    {

        $item = Item::findOrFail($id);

        $tasks = $taskController->getTaskbyItemId($id);

        if ($tasks->status() === 200) {
            $tasks = $tasks->getData(true)['tasks'];
        } else {
            $tasks = [];
        }

        return view('items.show', compact('item', 'tasks'));
    }
}
