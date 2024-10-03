<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Item;
use App\Models\Invoice;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        $items = DB::table('task_item_agent_view')
            ->where('task_agent_id', $agentId)
            ->get();

        if ($items->isEmpty()) {
            return response()->json([
                'message' => 'You have no pending tasks'
            ], 200);
        }

        return response()->json([
            'items' => $items
        ]);
    }

    public function getTransactionByAgentId()
    {
        $agentId = Agent::where('user_id', Auth::id())->first() ? Agent::where('user_id', Auth::id())->first()->id : null;

        if (!$agentId) {
            return response()->json([
                'message' => 'Agent not found'
            ], 404);
        }

        $transactions = Invoice::where('agent_id', $agentId)->get();
        // $transactions = DB::table('invoice_transaction_view')
        // ->where('agent_id', operator: $agentId)
        // ->get();
    
        if ($transactions->isEmpty()) {
            return response()->json(['message' => 'No transactions found for this agent.'], 404);
        }


        return response()->json([
            'transactions' => $transactions
        ]);
    }

    public function index()
    {
        $response = $this->getItemsByAgentId();
    
        $data = $response->getData(true);
        $status = $response->status();

        $response2 = $this->getTransactionByAgentId();
    
        $data2 = $response2->getData(true);
        $status2 = $response2->status();

        // Check if items are available and ensure they are not empty
        if (isset($data['items']) && !empty($data['items'])) {
            // If items is an array, we can count its elements
            $items = $data['items']; // Assuming this is an array of items
            // You may want to define counts based on the structure of items
            $pendingTasksCount = count(array_filter($items, fn($item) => $item['task_status'] === 'pending'));
            $completedTasksCount = count(array_filter($items, fn($item) => $item['task_status'] === 'completed'));
            $totalTasksCount = count($items);
            $totalClientsCount = count(array_unique(array_column($items, 'task_client_id'))); // Assuming task_client_id exists

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
    
            // Initialize counts to zero if no items are found
            $pendingTasksCount = 0;
            $completedTasksCount = 0;
            $totalTasksCount = 0;
            $totalClientsCount = 0;
        }

        if (isset($data2['transactions']) && !empty($data2['transactions'])) {
            // If items is an array, we can count its elements
            $transactions = $data2['transactions']; // Assuming this is an array of items
            // You may want to define counts based on the structure of items
            $unpaidInvoiceCount = count(array_filter($transactions, fn($transaction) => $transaction['status'] === 'unpaid'));
            $paidInvoiceCount = count(array_filter($transactions, fn($transaction) => $transaction['status'] === 'paid'));
            $totalInvoiceCount = count($transactions);
          
            $message = null;
            $status = 'success';
        } else {
            $transactions = [];
            $message = $data2['message'] ?? 'An error occurred';
    
            if ($status === 200) {
                $status = 'info';
            } elseif ($status === 404) {
                $status = 'warning';
            } else {
                $status = 'error';
            }
    
            // Initialize counts to zero if no items are found
            $unpaidInvoiceCount = 0;
            $paidInvoiceCount = 0;
            $totalInvoiceCount = 0;

        }


        return view('items.index', compact(
            'items', 
            'transactions', 
            'message', 
            'status', 
            'pendingTasksCount', 
            'completedTasksCount', 
            'totalTasksCount', 
            'totalClientsCount',
            'unpaidInvoiceCount',
            'paidInvoiceCount',
            'totalInvoiceCount'
        ));
    }
    
    

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
