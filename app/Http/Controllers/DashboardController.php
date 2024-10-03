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
        $itemCount = Item::count();
        $totalInvoiceAmount = Invoice::sum('amount');
        // You can also get detailed info if needed (e.g., for display in tables)
        $invoices = Invoice::all();
        $tasks = Task::all();

        return view('dashboard', compact('companyCount', 'agentCount', 'clientCount','totalInvoiceAmount',
         'invoiceCount', 'taskCount', 'itemCount', 'invoices', 'tasks'));
 
    }
    
}
