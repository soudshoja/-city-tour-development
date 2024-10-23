<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agent;
use App\Models\Item;
use App\Models\Invoice;
use App\Models\Company;
use App\Models\Client;
use App\Models\Task;
class DashboardController extends Controller
{


    public function index()
    {   
        // Get count for companies, agents, clients, invoices, and tasks
        
        $companyCount = Company::count();
        $agentCount = Agent::count();
        $clientCount = Client::count();
        $invoiceCount = Invoice::count();
        $taskCount = Task::count();
        $pendingTask = Task::where('status', 'pending')->count();
        $completedTask = Task::where('status', 'completed')->count();
        $itemCount = Item::count();
        $totalInvoiceAmount = Invoice::sum('amount');
        $totalPaidAmount = Invoice::where('status', 'paid')->sum('amount');
        $totalUnpaidAmount = Invoice::where('status', 'unpaid')->sum('amount');
        $paidInvoices =  Invoice::where('status', 'paid')->count();
        $unpaidInvoices =  Invoice::where('status', 'unpaid')->count();
        $invoices = Invoice::all();
        $tasks = Task::all();
        $agents = Agent::all();
        $clients = Client::all();
        $companies = Company::all();
        // Prepare clients with task count and invoice count
        $clientsWithDetails = $clients->map(function ($client) {
        // Count the number of tasks related to this client
        $taskCount = Task::where('client_id', $client->id)->count();

        // Count the total number of invoices related to this client
        $totalInvoices = Invoice::where('client_id', $client->id)->count();
          // Count the unpaid invoices for this client
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

        // Prepare agents with task count and invoice count
        $agentsWithDetails = $agents->map(function ($agent) {
            // Count the number of tasks related to this client
            $taskCount = Task::where('agent_id', $agent->id)->count();
            $pendingTasks = Task::where('agent_id', $agent->id)
            ->where('status', 'pending')
            ->count();
            // Count the total number of invoices related to this client
            $totalInvoices = Invoice::where('agent_id', $agent->id)->count();
    
            return [
                'name' => $agent->name,
                // 'companyName' => $agent->company->name,
                'taskCount' => $taskCount,
                'totalInvoices' => $totalInvoices,
                'pendingTasks' => $pendingTasks,
            ];
        });


        $dashboardData = [
            'totalTasks' => $taskCount,
            'pendingTasks' => $pendingTask,
            'completedTasks' => $completedTask,
            'totalInvoices' => $invoiceCount,
            'totalInvoiceAmount' => $totalInvoiceAmount,
            'totalPaidAmount' => $totalPaidAmount,
            'totalUnpaidAmount' => $totalUnpaidAmount, 
            'paidInvoices' => $paidInvoices,
            'unpaidInvoices' => $unpaidInvoices,
            'clientsCount'=> $clientCount,
            'agentsCount' => $agentCount,
            'companiesCount' => $companyCount,
            'agents' => $agentsWithDetails,
            'clients' => $clientsWithDetails,
        ];
        
        return view('dashboard', compact('dashboardData'));
 
    }
    
}