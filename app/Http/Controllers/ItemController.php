<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Item;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ItemController extends Controller
{
    public function getItemsByAgentId()
    {
        $agentId = Agent::where('user_id', Auth::id())->first() ? Agent::where('user_id', Auth::id())->first()->id : null;

        if (!$agentId) {
            return response()->json([
                'message' => 'Agent not found'
            ], 404);
        }


        $items = Item::where('agent_id', $agentId)->get();

        if ($items->isEmpty()) {
            return response()->json([
                'message' => 'You have no pending tasks'
            ], 200);
        }

        return response()->json([
            'items' => $items
        ]);
    }

    public function index()
    {
        $response = $this->getItemsByAgentId();

        $data = $response->getData(true);
        $status = $response->status();

        if (isset($data['items'])) {
            $items = $data['items'];
            $message = null;
            $status = 'success';
        } else {
            $items = [];
            $message = $data['message'] ?? 'An error occurred';

            if ($status === 200) {
                $status = 'info';
            } elseif ($status === 404) {
                $status = 'warning';
            } else {
                $status = 'error';
            }
        }

        return compact('items', 'message', 'status');
    }

    public function show(TaskController $taskController, $id)
    {

        $item = Item::findOrFail($id);

        $tasks = $taskController->getTaskbyItemId($item->first()->id);

        if ($tasks->status() === 200) {
            $tasks = $tasks->getData(true)['tasks'];
        } else {
            $tasks = [];
        }

        return view('items.show', compact('item', 'tasks'));
    }
}
