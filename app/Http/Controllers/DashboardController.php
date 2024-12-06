<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Invoice;
use App\Models\Company;
use App\Models\Client;
use App\Models\Role;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{


    public function index()
    {

        if (Auth::user()->role_id == Role::ADMIN) {
            return $this->adminDashboard();
        } elseif (Auth::user()->role_id == Role::COMPANY) {
            return $this->companyDashboard();
        } elseif (Auth::user()->role_id == Role::AGENT) {
            return $this->agentDashboard();
        } elseif (Auth::user()->role_id == Role::BRANCH) {
            return $this->branchDashboard();
        }
    }

    public function adminDashboard()
    {

        $user = Auth::user();

        if ($user->role_id == Role::ADMIN) {
            // Admin can see all agents
            $agent = Agent::with('company')->first();
        } elseif ($user->role_id == Role::COMPANY) {
            // Company can only see their agents
            $agent = Agent::with('company')
                ->where('company_id', $user->company->id) // assuming user belongs to one company
                ->first();
        } else {
            $agent = Agent::where('user_id', Auth::id())->with('company')->first();
        }


        $company = $agent->company;

        $agents = Agent::where('company_id', $company->id)->get();
        $agentsCount = $agents->count();
        // Count total tasks, pending tasks, and completed tasks for all agents
        $totalTaskCount = $agent->tasks()->count();

        $pendingTaskCount = $agent->tasks()->where('status', 'pending')->count();

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
            'clientsCount' => $clientsCount,
            'clients' => $clientsWithDetails,
            'totalTrips' => $tripsCount,
            'invoices' => $invoices,
        ];
        return view('admin.index', compact('company', 'agent', 'dashboardData'));
    }

    public function companyDashboard()
    {
        // Retrieve the company for the authenticated user with agents
        $company = Company::where('user_id', Auth::id())->with('branches.agents')->first();
        // dd($company);
        // Get all agents under the company
        $agents = $company->branches->flatMap(function ($branch) {
            return $branch->agents;
        });
        // dd($agents);
        $agentsCount = $agents->count();
        // Count total tasks, pending tasks, and completed tasks for all agents
        $totalTaskCount = $agents->sum(function ($agent) {
            return $agent->tasks()->count(); // Count all tasks for each agent
        });

        $pendingTaskCount = $agents->sum(function ($agent) {
            return $agent->tasks()->where('status', 'pending')->count(); // Count pending tasks for each agent
        });

        $completedTaskCount = $agents->sum(function ($agent) {
            return $agent->tasks()->where('status', 'completed')->count(); // Count completed tasks for each agent
        });

        // Count total invoices, paid invoices, and unpaid invoices for all agents
        $totalInvoices = $agents->sum(function ($agent) {
            return $agent->invoices()->count(); // Count all invoices for each agent
        });

        $totalInvoiceAmount = $agents->sum(function ($agent) {
            return $agent->invoices()->sum('amount'); // Sum the 'amount' field for all invoices of each agent
        });

        $totalPaidAmount = $agents->sum(function ($agent) {
            return $agent->invoices()->where('status', 'paid')->sum('amount'); // Sum the 'amount' field for all invoices of each agent
        });
        $totalPaidAmountChart = Invoice::where('status', 'paid')
            ->selectRaw('SUM(amount) as total, DATE_FORMAT(created_at, "%Y-%m") as month')
            ->groupBy('month')
            ->get();
        $totalUnpaidAmountChart = Invoice::where('status', 'unpaid')
            ->selectRaw('SUM(amount) as total, DATE_FORMAT(created_at, "%Y-%m") as month')
            ->groupBy('month')
            ->get();

        $totalUnpaidAmount = $agents->sum(function ($agent) {
            return $agent->invoices()->where('status', 'unpaid')->sum('amount'); // Sum the 'amount' field for all invoices of each agent
        });

        $paidInvoices = $agents->sum(function ($agent) {
            return $agent->invoices()->where('status', 'paid')->count(); // Count paid invoices for each agent
        });

        $unpaidInvoices = $agents->sum(function ($agent) {
            return $agent->invoices()->where('status', 'unpaid')->count(); // Count unpaid invoices for each agent
        });

        // Get clients under those agents
        $clients = Client::whereIn('agent_id', $agents->pluck('id'))->get();
        // Count clients associated with all agents
        $clientsCount = Client::whereIn('agent_id', $agents->pluck('id'))->count();

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
                'taskCount' => $taskCount,
                'totalInvoices' => $totalInvoices,
                'pendingTasks' => $pendingTasks,
            ];
        });


        // Prepare the data array
        $dashboardData = [
            'totalTasks' => $totalTaskCount,
            'pendingTasks' => $pendingTaskCount,
            'completedTasks' => $completedTaskCount,
            'totalInvoices' => $totalInvoices,
            'totalInvoiceAmount' => $totalInvoiceAmount,
            'totalPaidAmount' => $totalPaidAmount,
            'totalUnpaidAmount' => $totalUnpaidAmount,
            'totalPaidAmountChart' => $totalPaidAmountChart,
            'totalUnpaidAmountChart' => $totalUnpaidAmountChart,
            'paidInvoices' => $paidInvoices,
            'unpaidInvoices' => $unpaidInvoices,
            'clientsCount' => $clientsCount,
            'agentsCount' => $agentsCount,
            'agents' => $agentsWithDetails,
            'clients' => $clientsWithDetails,
        ];

        return view('companies.index', compact('company', 'dashboardData'));
    }

    public function agentDashboard()
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
        $agents = Agent::with('company')->get();
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

        $agentsWithDetails = $agents->map(function ($agent) {
            $taskCount = Task::where('agent_id', $agent->id)->count();
            $pendingTasks = Task::where('agent_id', $agent->id)
                ->where('status', 'pending')
                ->count();
            $totalInvoices = Invoice::where('agent_id', $agent->id)->count();

            return [
                'name' => $agent->name,
                'companyName' => $agent->company ? $agent->company->name : 'N/A', // Safely access company name
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
            'clientsCount' => $clientCount,
            'agentsCount' => $agentCount,
            'companiesCount' => $companyCount,
            'agents' => $agentsWithDetails,
            'clients' => $clientsWithDetails,
        ];

        return view('dashboard', compact('dashboardData'));
    }

    public function branchDashboard()
    {
        $branch = Branch::where('user_id', Auth::id())->first();
        return view('branches.index', compact('branch'));
    }
};
