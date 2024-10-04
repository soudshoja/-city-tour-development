<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Client;
use App\Models\Item;
use App\Models\Invoice;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{


    public function index()
    {
        $agent = Agent::where('user_id', Auth::id())->first();

        $company = $agent->company;

        $agentsCount = $company->agents->count();
        // Count total tasks, pending tasks, and completed tasks for all agents
        $totalTaskCount = $agent->tasks()->count();

        $pendingTaskCount =$agent->tasks()->where('status', 'pending')->count();

        $completedTaskCount = $agent->tasks()->where('status', 'completed')->count();

        $totalInvoices = $agent->invoices()->count();

        $totalInvoiceAmount = $agent->invoices()->sum('amount');
    
        $paidInvoices = $agent->invoices()->where('status', 'paid')->count(); 

        $unpaidInvoices = $agent->invoices()->where('status', 'unpaid')->count();

        $invoices = $agent->invoices()->get();

        $tripsCount = Item::whereIn('agent_id', $agent->pluck('id'))->count();
        // Get clients under those agents
        $clients = Client::whereIn('agent_id', $agent->pluck('id'))->get();

        $clientsCount = Client::whereIn('agent_id', $agent->pluck('id'))->count();

        $clientsWithDetails = $clients->map(function ($client) {

        $taskCount = Task::where('client_id', $client->id)->count();

        $totalInvoices = Invoice::where('client_id', $client->id)->count();
        $unpaidInvoices = Invoice::where('client_id', $client->id)
        ->where('status', 'unpaid')
        ->count();

        return [
            'name' => $client->name,
            'taskCount' => $taskCount,
            'totalInvoices' => $totalInvoices,
            'unpaidInvoices' => $unpaidInvoices,
        ];
    });

        // Prepare the data array
        $dashboardData = [
            'totalTasks' => $totalTaskCount,
            'pendingTasks' => $pendingTaskCount,
            'completedTasks' => $completedTaskCount,
            'totalInvoices' => $totalInvoices,
            'totalInvoiceAmount' => $totalInvoiceAmount,
            'paidInvoices' => $paidInvoices,
            'unpaidInvoices' => $unpaidInvoices,
            'clientsCount'=> $clientsCount,
            'clients' => $clientsWithDetails,
            'totalTrips' => $tripsCount,
            'invoices' => $invoices,
        ];
        return view('items.index', compact( 'company', 'agent','dashboardData'));

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
