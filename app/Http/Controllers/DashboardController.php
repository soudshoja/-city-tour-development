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
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\Task;
use App\Services\IataEasyPayService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{

    public function index()
    {
        $serializedData = [];

        if (Auth::user()->role_id == Role::COMPANY) {
            $company = Company::where('user_id', Auth::id())->first();
        } elseif (Auth::user()->role_id == Role::ACCOUNTANT) {
            $company = Auth()->user()?->accountant?->branch?->company;
        } else {
            $company = null;
        }

        $walletData = $this->getCompanyWallets($company);
        extract($walletData);

        if (Auth::user()->role_id == Role::ADMIN) {
            $dashboardData =  $this->adminDashboard();

            $serializedData = [
                'companies' => $dashboardData['companies'],
                'branches' => $dashboardData['branches'],
                'agents' => $dashboardData['agents'],
                'clients' => $dashboardData['clients'],
                'pieChartTitle' => 'Companies Sales',
                'pieChartNumbers' => $dashboardData['companiesSales'],
                'pieChartLabels' => $dashboardData['companiesNames'],
                'pieChartColors' => $this->generateColors($dashboardData['companies']->count()),
            ];

        } elseif (Auth::user()->role_id == Role::COMPANY) {
            
            $dashboardData = $this->companyDashboard();

            $reportController = new ReportController();

            $childAccountsPayable = $reportController->getPayableSupplier();
            $payableSupplier = $childAccountsPayable;

            $childAccountReceivable = $reportController->getReceivable();

            $totalBank = $reportController->getTotalBank();

            $gatewayReceivable = $reportController->getGatewayReceivable();

            $profitAgentWise = $reportController->getProfitAgent();

            $serializedData = [
                'paidAmounts' => $dashboardData['paidAmounts'],
                'unpaidAmounts' => $dashboardData['unpaidAmounts'],
                'branches' => $dashboardData['branches'],
                'agents' => $dashboardData['branches']->flatMap->agents,
                'clients' => $dashboardData['branches']->flatMap->agents->flatMap->clients,
                'pieChartTitle' => 'Branch Sales',
                'pieChartNumbers' => $dashboardData['branchesSales'],
                'pieChartLabels' => $dashboardData['branches']->pluck('name'),
                'pieChartColors' => $this->generateColors($dashboardData['branches']->count()),
                'payableSupplier' => $payableSupplier,
                'profitAgentWise' => $profitAgentWise['sumProfitAgent'],
                'totalReceivable' => $childAccountReceivable['balance'],
                'totalBank' =>  $totalBank['balance'],
                'gatewayReceivable' =>  $gatewayReceivable['balance'],
                'wallets' => $wallets,
                'iataWalletName' => $iataWalletName,
                'iataBalance' => $iataBalance,
                'iataErrorMessage' => $iataErrorMessage,
            ];

        } elseif (Auth::user()->role_id == Role::AGENT) {
            return $this->agentDashboard();
        } elseif (Auth::user()->role_id == Role::BRANCH) {
            return $this->branchDashboard();
        } elseif (Auth::user()->role_id == Role::ACCOUNTANT) {
            
            $dashboardData = $this->accountantDasboard();

            $serializedData = [
                'payableSupplier'   => (object)['balance' => 0],
                'profitAgentWise'   => 0,
                'totalReceivable'   => 0,
                'totalBank'         => 0,
                'gatewayReceivable' => 0,
                'companies'         => $dashboardData['companies'],
                'branches'          => $dashboardData['branches'],
                'agents'            => $dashboardData['agents'],
                'clients'           => $dashboardData['clients'],
                'pieChartTitle'   => 'Companies Sales',
                'pieChartNumbers' => $dashboardData['companiesSales'],
                'pieChartLabels'  => $dashboardData['companiesNames'],
                'pieChartColors'  => $this->generateColors($dashboardData['companies']->count()),
                'wallets' => $wallets,
                'iataWalletName' => $iataWalletName,
                'iataBalance' => $iataBalance,
                'iataErrorMessage' => $iataErrorMessage,
            ];
        }


        return view('dashboard', $serializedData);
    }

    private function getCompanyWallets($company)
    {
        $wallets = collect();
        $iataBalance = 0;
        $walletName = 'N/A';
        $error = null;

        try {
            if (!$company || !$company->iata_code || !$company->iata_client_id || !$company->iata_client_secret) {
                throw new \Exception('Missing IATA credentials. Please update your company profile with the IATA Code, Client ID, and Client Secret.');
            }

            $service = new IataEasyPayService(
                $company->iata_client_id,
                $company->iata_client_secret
            );

            $data = $service->getWalletBalanceByCompany($company->iata_code, 'KWD');
            $wallets = collect($data['wallets'] ?? [])->where('status', 'OPEN')->values();
            $iataBalance = $wallets->sum('balance');
            $walletName = $wallets->pluck('name')->join(', ');

        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return [
            'wallets' => $wallets,
            'iataBalance' => $iataBalance,
            'iataWalletName' => $walletName,
            'iataErrorMessage' => $error,
        ];
    }

    public function adminDashboard()
    {
        $user = Auth::user();

        $companies = Company::with('branches.agents.invoices.invoicePartials')->whereHas('branches.agents.invoices')->get();
        $companiesSales = $companies->map(function ($company) {
            return $company->branches->flatMap(function ($branch) {
                return $branch->agents->flatMap(function ($agent) {
                    return $agent->invoices;
                });
            })->sum('amount');
        });
        


        $branches = Branch::all();
        $agents = Agent::all();
        $clients = Client::all();

       return [
            'companies' => $companies,
            'branches' => $branches,
            'agents' => $agents,
            'clients' => $clients,
            'companiesSales' => $companiesSales,
            'companiesNames' => $companies->pluck('name'),
            // 'notifications' => $notifications,
        ];
    }

    public function companyDashboard()
    {
        $company = Company::where('user_id', Auth::id())->with('branches.agents.clients.invoices.invoiceDetails.task')->first();

        if (!$company) {
            return redirect()->back()->with('error', 'No company found for the authenticated user.');
        }

        $paidAmounts = array_fill(0, 12, 0);
        $unpaidAmounts = array_fill(0, 12, 0);

        // Loop through branches and calculate paid/unpaid amounts
        $branches = $company->branches()
            ->with('agents.clients.invoices')
            ->whereHas('agents.clients')
            ->get();

        $branchesSales = $branches->map(function ($branch) {
            return $branch->agents->flatMap(function ($agent) {
                return $agent->invoices;
            })->sum('amount');
        });

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

        return [
            'branches' => $branches,
            'branchesSales' => $branchesSales,
            'paidAmounts' => $paidAmounts,
            'unpaidAmounts' => $unpaidAmounts,
        ];

    }

    public function agentDashboard()
    {
        $taskController = new TaskController();
        return $taskController->index(new Request());
    }

    public function accountantDasboard()
    {
        $user = Auth::user();

        $companies = Company::with('branches.agents.invoices.invoicePartials')->whereHas('branches.agents.invoices')->get();
        $companiesSales = $companies->map(function ($company) {
            return $company->branches->flatMap(function ($branch) {
                return $branch->agents->flatMap(function ($agent) {
                    return $agent->invoices;
                });
            })->sum('amount');
        });



        $branches = Branch::all();
        $agents = Agent::all();
        $clients = Client::all();

        return [
            'companies' => $companies,
            'branches' => $branches,
            'agents' => $agents,
            'clients' => $clients,
            'companiesSales' => $companiesSales,
            'companiesNames' => $companies->pluck('name'),
            // 'notifications' => $notifications,
        ];
    }

    // public function agentDashboard()
    // {
    //     $companyCount = Company::count();
    //     $agentCount = Agent::count();
    //     $clientCount = Client::count();
    //     $invoiceCount = Invoice::count();
    //     $taskCount = Task::count();
    //     $pendingTask = Task::where('status', 'pending')->count();
    //     $completedTask = Task::where('status', 'completed')->count();
    //     $totalInvoiceAmount = Invoice::sum('amount');
    //     $totalPaidAmount = Invoice::where('status', 'paid')->sum('amount');
    //     $totalUnpaidAmount = Invoice::where('status', 'unpaid')->sum('amount');
    //     $paidInvoices =  Invoice::where('status', 'paid')->count();
    //     $unpaidInvoices =  Invoice::where('status', 'unpaid')->count();
    //     $invoices = Invoice::all();
    //     $tasks = Task::all();
    //     $agents = Agent::with('branch.company')->get();
    //     $clients = Client::all();
    //     $companies = Company::all();
        
    //     $clientsWithDetails = $clients->map(function ($client) {
            
    //         $taskCount = Task::where('client_id', $client->id)->count();

    //         $totalInvoices = Invoice::where('client_id', $client->id)->count();

    //         $unpaidInvoices = Invoice::where('client_id', $client->id)
    //             ->where('status', 'unpaid')
    //             ->count();

    //         return [
    //             'name' => $client->first_name,
    //             'taskCount' => $taskCount,
    //             'totalInvoices' => $totalInvoices,
    //             'unpaidInvoices' => $unpaidInvoices,
    //         ];
    //     });

    //     $agents->map(function ($agent) {
    //         $taskCount = Task::where('agent_id', $agent->id)->count();
    //         $pendingTasks = Task::where('agent_id', $agent->id)
    //         ->where('status', 'pending')
    //         ->count();
    //         $totalInvoices = Invoice::where('agent_id', $agent->id)->count();

    //         // $data = [
    //         // 'taskCount' => $taskCount,
    //         // 'totalInvoices' => $totalInvoices,
    //         // 'pendingTasks' => $pendingTasks,
    //         // ];
    //         $agent->taskCount = $taskCount;
    //         $agent->totalInvoices = $totalInvoices;
    //         $agent->pendingTasks = $pendingTasks;

    //     });

       

    //     // $dashboardData = [
    //     //     'totalTasks' => $taskCount,
    //     //     'pendingTasks' => $pendingTask,
    //     //     'completedTasks' => $completedTask,
    //     //     'totalInvoices' => $invoiceCount,
    //     //     'totalInvoiceAmount' => $totalInvoiceAmount,
    //     //     'totalPaidAmount' => $totalPaidAmount,
    //     //     'totalUnpaidAmount' => $totalUnpaidAmount,
    //     //     'paidInvoices' => $paidInvoices,
    //     //     'unpaidInvoices' => $unpaidInvoices,
    //     //     'clientsCount' => $clientCount,
    //     //     'agentCount' => $agentCount,
    //     //     'companiesCount' => $companyCount,
    //     //     'agents' => $agentsWithDetails,
    //     //     'clients' => $clientsWithDetails,
    //     // ];
       
      
    //     $totalTasks = $taskCount;
    //     $pendingTasks = $pendingTask;
    //     $completedTasks = $completedTask;
    //     $totalInvoices = $invoiceCount;
    //     $clientsCount = $clientCount;
    //     $companiesCount = $companyCount;
    //     $clients = $clientsWithDetails;

    //     return view('tasks.index', compact(
    //         'totalTasks',
    //         'pendingTasks',
    //         'completedTasks',
    //         'totalInvoices',
    //         'totalInvoiceAmount',
    //         'totalPaidAmount',
    //         'totalUnpaidAmount',
    //         'paidInvoices',
    //         'unpaidInvoices',
    //         'clientsCount',
    //         'agentCount',
    //         'companiesCount',
    //         'agents',
    //         'clients',
    //     ));
    // }

    public function branchDashboard()
    {
        $branch = Branch::where('user_id', Auth::id())->first();
        return view('branches.index', compact('branch'));
    }

    public function generateColors($count)
    {
        $colors = [
            '#3672b1',
            '#48494d',
            '#064a9a',
            '#3c84c2',
            '#f4cc8c',
            '#f4a259',
            '#f4874b',
            '#f46d43',
            '#f44e3f',
            '#f42c2f',
            '#f42c2f',
            '#f42c2f',
        ];

        return array_slice($colors, 0, $count);

    }

    
};
