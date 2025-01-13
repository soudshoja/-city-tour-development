<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Invoice;
use App\Models\Company;
use App\Models\Client;
use App\Models\Notification;
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

        $agents = Agent::with('branch.company')->first();

        $company = $agents->company;

        // Count total tasks, pending tasks, and completed tasks for all agents
        $totalTaskCount = $agents->tasks()->count();

        $pendingTaskCount = $agents->tasks()->where('status', 'pending')->count();

        $completedTaskCount = $agents->tasks()->where('status', 'completed')->count();

        $totalInvoices = $agents->invoices()->count();

        $totalInvoiceAmount = $agents->invoices()->sum('amount');

        $paidInvoices = $agents->invoices()->where('status', 'paid')->count();

        $unpaidInvoices = $agents->invoices()->where('status', 'unpaid')->count();

        $invoices = $agents->invoices()->get();

        $tripsCount = Item::whereIn('agent_id', $agents->pluck('id'))->count();
        // Get clients under those agents
        $clients = Client::whereIn('agent_id', $agents->pluck('id'))->get();

        $clientsCount = Client::whereIn('agent_id', $agents->pluck('id'))->count();

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

        $notifications = Notification::with('user.agent.branch.company')->get();
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
            'notifications' => $notifications,
        ];
        return view('admin.index', compact('company', 'dashboardData'));
    }

    public function companyDashboard()
    {
        // Retrieve the company for the authenticated user with agents
        $company = Company::where('user_id', Auth::id())->with('branches.agents.clients.invoices.invoiceDetails.task')->first();
        // dd($company);
        // Handle the case where no company is found
        if (!$company) {
            return redirect()->back()->with('error', 'No company found for the authenticated user.');
        }

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

        $notifications = Notification::with('user.agent.branch.company')->orderBy('created_at', 'desc')->get();

        $branchesWithInvoiceSums = $company->branches()
            ->with(['agents.clients.invoices.invoiceDetails'])
            ->get()
            ->map(function ($branch) {
                // Calculate the total invoice amount for the branch
                $totalInvoiceSum = $branch->agents->flatMap(function ($agent) {
                    return $agent->clients->flatMap(function ($client) {
                        return $client->invoices->flatMap(function ($invoice) {
                            return $invoice->invoiceDetails;
                        });
                    });
                })->sum('task_price'); // Assuming 'task_price' exists in `invoiceDetails`

                return [
                    'name' => $branch->name,
                    'totalInvoiceSum' => $totalInvoiceSum,
                ];
            });

        $totalInvoiceSumForCompany = $branchesWithInvoiceSums->sum('totalInvoiceSum');

        // Calculate percentages
        $chartBranchData = $branchesWithInvoiceSums->map(function ($branch) use ($totalInvoiceSumForCompany) {
            return [
                'name' => $branch['name'],
                'percentage' => $totalInvoiceSumForCompany > 0
                    ? round(($branch['totalInvoiceSum'] / $totalInvoiceSumForCompany) * 100, 2)
                    : 0,
            ];
        });


        // Initialize arrays for months
        $paidAmounts = array_fill(0, 12, 0);
        $unpaidAmounts = array_fill(0, 12, 0);

        // Loop through branches and calculate paid/unpaid amounts
        $branches = $company->branches()
            ->with('agents.clients.invoices')
            ->get();

        foreach ($branches as $branch) {
            foreach ($branch->agents as $agent) {
                foreach ($agent->clients as $client) {
                    foreach ($client->invoices as $invoice) {
                        $monthIndex = (int) date('n', strtotime($invoice->invoice_date)) - 1; // Get month index (0 = January, 11 = December)

                        if ($invoice->status === 'paid') {
                            $paidAmounts[$monthIndex] += $invoice->amount; // Add to paid amounts
                        } elseif ($invoice->status === 'unpaid') {
                            $unpaidAmounts[$monthIndex] += $invoice->amount; // Add to unpaid amounts
                        }
                    }
                }
            }
        }


        $agentsData = [];

        foreach ($company->branches as $branch) {
            foreach ($branch->agents as $agent) {
                // Count tasks associated with each agent
                $taskCount = 0;

                foreach ($agent->clients as $client) {
                    foreach ($client->invoices as $invoice) {
                        foreach ($invoice->invoiceDetails as $invoiceDetail) {
                            if ($invoiceDetail->task) {
                                $taskCount++;
                            }
                        }
                    }
                }

                // Calculate the percentage of tasks per agent
                $totalTasks = Task::count(); // Assuming this fetches all tasks across the company
                $percentage = $totalTasks > 0 ? ($taskCount / $totalTasks) * 100 : 0;

                $agentsData[] = [
                    'name' => $agent->name,
                    'percentage' => $percentage,
                ];
            }
        }


        usort($agentsData, function ($a, $b) {
            return $b['percentage'] <=> $a['percentage'];
        });


        // Prepare the data array
        $dashboardData = [
            'totalTasks' => $totalTaskCount,
            'chartBranchData' => $chartBranchData,
            'totalBranches' => $company->branches->count(),
            'paidAmounts' => $paidAmounts,
            'unpaidAmounts' => $unpaidAmounts,
            'agentsData' => $agentsData,
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
            'notifications' => $notifications,
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
        $agents = Agent::with('branch.company')->get();
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
