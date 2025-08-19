<?php
// app/Http/Controllers/AgentController.php

namespace App\Http\Controllers;

use App\Http\Traits\NotificationTrait;
use Illuminate\Http\Request;
use App\Models\Agent;
use App\Models\User;
use App\Models\Task;
use App\Models\Company;
use App\Models\Client;
use App\Models\Invoice;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\AgentsImport;
use App\Models\Account;
use App\Models\AgentType;
use App\Models\AgentMonthlyCommissions;
use App\Models\Branch;
use App\Models\Transaction;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\SupplierCompany;
use DateTimeImmutable;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
class AgentController extends Controller
{
    use NotificationTrait;

    public function index(Request $request)
    {
        $user = Auth::user();
        $agents = collect();
        $agentCount = 0;

        if ($user->role_id == Role::COMPANY) {
            // Get agents belonging to the company
            $company_id = $user->company_id;
            $branchesId = Branch::where('company_id', $user->company->id)->pluck('id');
            $agents = Agent::whereIn('branch_id', $branchesId);

        } elseif ($user->role_id == Role::BRANCH) {
            // Get agents belonging to the branch
            $branch_id = $user->branch_id;
            $agents = Agent::where('branch_id', $branch_id);

        } elseif ($user->role_id == Role::ADMIN) {
            // Admin can see all agents
            $agents = new Agent;
        }

        if($request->has('search')) {
            $search = $request->input('search');
            // Filter agents based on the search query
            $agents = $agents->where(function ($query) use ($search) {
                $searchTerm = '%' . strtolower($search) . '%';
                $query->where('name', 'like', $searchTerm)
                    ->orWhere('amadeus_id', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm)
                    ->orWhere('phone_number', 'like', $searchTerm)
                    ->orWhereHas('agentType', function ($q) use ($searchTerm) {
                        $q->where('name', 'like', $searchTerm);
                    });
            });
        }

        $agentCount = $agents->count();

        $agents = $agents->orderBy('created_at', 'desc')->paginate(20);

        // Pass both 'agents' and 'agentCount' to the view
        return view('agents.index', compact('agents', 'agentCount'));
    }


    public function new()
    {
        $agents = Agent::all();
        $companies = Company::all();
        $admin = Role::ADMIN;

        return view('agents.agentsNew', compact('agents', 'companies', 'admin'));
    }

    public function show($id)
    {
        $agent = Agent::with('agentType', 'branch.company', 'tasks', 'invoices', 'clients')->findOrFail($id);

        // Paginate all sections when viewing the main page (agentsShow)
        $tasks = Task::with('agent', 'invoiceDetail')
            ->leftJoin('invoice_details', 'tasks.id', '=', 'invoice_details.task_id')
            ->where('agent_id', $id)
            ->orderByRaw('invoice_details.id IS NULL, tasks.created_at DESC')
            ->select('tasks.*')
            ->paginate(6, ['*'], 'tasks');

        $taskInvoiced = Task::where('agent_id', $id)->whereHas('invoiceDetail')->count();
        $taskNotInvoiced = Task::where('agent_id', $id)->whereDoesntHave('invoiceDetail')->count();

        foreach ($tasks as $task) {
            $date = new DateTimeImmutable($task->created_at);
            $task->created_at = $date->format('d-M-Y');
        }

        $month = request('month') ? Carbon::parse(request('month'))->startOfMonth() : now()->startOfMonth();

        $stored = AgentMonthlyCommissions::where('agent_id', $agent->id)
            ->where('month', $month->month)
            ->where('year', $month->year)
            ->first();

        if ($stored) {
            $totalCommission = number_format($stored->total_commission, 2);
            $totalProfit = number_format($stored->total_profit, 2);
        } else {
            $monthlySummary = $this->calculateMonthlySummary($agent, $month);
            $totalCommission = number_format($monthlySummary['commission'], 2);
            $totalProfit = number_format($monthlySummary['profit'], 2);
        }

        $invoices = Invoice::with('invoiceDetails')->where('agent_id', $id)->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->paginate(4, ['*'], 'invoices');

        foreach ($invoices as $invoice) {
            $commission = 0;
            $profit = 0;

            foreach ($invoice->invoiceDetails as $detail) {
                $markup = $detail->markup_price ?? 0;
                $profit += $markup;
                if ($agent->type_id == 2) {
                    $detail->commission = JournalEntry::where('invoice_detail_id', $detail->id)
                        ->where('account_id', 43)
                        ->sum('credit');
                } elseif ($agent->type_id == 3) {
                    $detail->commission = $markup * ($agent->commission ?? 0.15);
                } else {
                    $detail->commission = 0;
                }
                $commission += $detail->commission;
            }
            $invoice->profit = number_format($profit, 2);
            $invoice->commission = number_format($commission, 2);
        }

        $clients = Client::with('invoices')->whereHas('tasks', function ($query) use ($agent) {
            $query->where('agent_id', $agent->id);
        })->paginate(3, ['*'], 'clients');

        foreach ($clients as $client) {
            $client->paid = number_format($client->invoices->where('status', 'paid')->sum('amount'), 2);
            $client->unpaid = number_format($client->invoices->where('status', '<>', 'paid')->sum('amount'), 2);
        }

        $paid = Invoice::where('status', 'paid')->where('agent_id', $id)->sum('amount');
        $unpaid = Invoice::where('status', '<>', 'paid')->where('agent_id', $id)->sum('amount');
        $agentType = AgentType::all();
        $company = Company::find($agent->branch->company_id);
        $supplierCompany = SupplierCompany::with('supplier')
            ->where('company_id', $company->id)
            ->get()
            ->pluck('supplier.name')
            ->toArray();
        
        // Return the main view with paginated data
        return view('agents.agentsShow', compact(
            'agent',
            'agentType',
            'tasks',
            'invoices',
            'clients',
            'paid',
            'unpaid',
            'taskInvoiced',
            'taskNotInvoiced',
            'supplierCompany',
            'totalCommission',
            'totalProfit',
        ));
    }

    public function calculateMonthlySummary(Agent $agent, $month = null)
    {
        $commission = 0;
        $profit = 0;

        $from = Carbon::parse($month ?? now()->startOfMonth());
        $to = (clone $from)->endOfMonth();

        $invoices = Invoice::with('invoiceDetails')
            ->where('agent_id', $agent->id)
            ->whereBetween('created_at', [$from, $to])
            ->get();

        foreach ($invoices as $invoice) {
            foreach ($invoice->invoiceDetails as $detail) {
                $markup = $detail->markup_price ?? 0;
                $profit += $markup;

                if ($agent->type_id == 2) {
                    $detail->commission += JournalEntry::where('invoice_detail_id', $detail->id)
                        ->where('account_id', 43)
                        ->sum('credit');
                    $commission += $detail->commission;
                } elseif ($agent->type_id == 3) {
                    // Type 3 ((Commission = total profit * %) + salary)
                    $commission += ($markup * ($agent->commission ?? 0.15));
                }
            }
        }

        if ($agent->type_id == 4 && $profit > $agent->target) {
            // Type 4 (only if profit > target, then (profit - salary) * % + salary)
            $commission = (($profit - $agent->salary) * ($agent->commission ?? 0.15)) + $agent->salary;
        } elseif ($agent->type_id == 4) {
            $commission = 0.00;
        } elseif ($agent->type_id == 3 && $profit != 0) {
            $commission += $agent->salary;
        }

        return [
            'commission' => round($commission, 2),
            'profit' => round($profit, 2),
        ];
    }

    // public function edit($id)
    // {
    //     $agent = Agent::find($id);
    //     $branches = collect();

    //     $user = auth()->user();
    //     if ($user->role_id == Role::COMPANY) {
    //         $branches = Branch::where('company_id', $user->company->id)->get();
    //     }

    //     return view('agents.agentsEdit', compact('agent', 'branches'));
    // }


    public function update(Request $request, $id)
    {
        $agent = Agent::find($id);
        $user = User::find($agent->user_id);
        try {
            $oldSalary = $agent->salary;
            $agent->update($request->all());
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            if ($request->salary != $oldSalary && $request->salary > 0) {
                $companyId = $agent->branch->company_id;
                $salaryExpenseAccount = Account::where('name', 'Agent Salaries')
                ->where('company_id', $agent->branch->company_id)
                ->first();

                if ($salaryExpenseAccount) {
                    $transaction = Transaction::create([
                        'company_id' => $companyId,
                        'branch_id' => $agent->branch_id,
                        'entity_id' => $agent->id,
                        'entity_type' => 'agent',
                        'transaction_type' => 'debit',
                        'amount' => $request->salary,
                        'description' => 'Monthly salary adjustment for agent: ' . $agent->name,
                        'reference_type' => 'Payment',
                    ]);

                    JournalEntry::create([
                        'transaction_id' => $transaction->id,
                        'branch_id' => $agent->branch_id,
                        'company_id' => $agent->branch->company_id,
                        'account_id' => $salaryExpenseAccount->id,
                        'transaction_date' => $transaction->created_at,
                        'description' => 'Recorded updated salary expense for agent: ' . $agent->name,
                        'debit' => $request->salary,
                        'credit' => 0,
                        'balance' => $salaryExpenseAccount->balance ?? 0,
                        'name' => $salaryExpenseAccount->name,
                        'type' => 'expense',
                    ]);
                }
            }
            return redirect()->back()->with('success', 'Agent updated successfully');
        } catch (Exception $error) {
            logger('Failed to update agent: ' . $error->getMessage());

            return redirect()->back()->with('error', 'Failed to update agent');
        }
    }

    public function updateCommission(Request $request, $id)
    {
        $request->validate([
            'commission' => 'required|numeric|min:0',
        ]);

        try {
            $agent = Agent::findOrFail($id);
            $agent->commission = $request->commission / 100; 
            $agent->save();

            return redirect()->back()->with('success', 'Agent commission updated successfully');
        } catch (Exception $e) {
            logger('Failed to update agent commission: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update agent commission');
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'dial_code' => 'nullable|string|max:30',
            'phone' => 'required|string',
            'branch_id' => 'required',
            'amadeus_id' => 'nullable|string|max:255',
            // 'company_id' => 'required',
            'type_id' => 'required',
        ]);

        $branch = Branch::with('company','account')->find($request->branch_id);

        if(!$branch) {
            logger('Failed to create agent: Branch not found');
            return redirect()->back()->with('error', 'Branch not found');
        }

        if (!$branch->account) {
            logger('Failed to create agent: Branch ' . $branch->name . ' does not have an account');
            return redirect()->back()->with('error', 'Something went wrong, please contact support');
        }

        $assetsAccount = Account::where('name' , 'Assets')->first();

        if (!$assetsAccount) {
            logger('Failed to create agent: Assets account does not exist');
            return redirect()->back()->with('error', 'Something went wrong, please contact support');
        }

        try{

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => Role::AGENT,
                'remember_token' => Str::random(10),
                'first_login' => 1,
            ])->assignRole('agent');

        } catch(Exception $e){
            logger('Failed to create user for agent: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create user');
        }

        try {
            $agent = Agent::create([
                'user_id' => $user->id,
                'branch_id' => $request->branch_id,
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->dial_code . $request->phone,
                'type_id' => $request->type_id,
                'amadeus_id' => $request->amadeus_id,
            ]);
        } catch (Exception $e) {
            $user->delete();
            logger('Failed to create agent: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create agent');
        }

        try{
            Account::create([
                'serial_number' => $request->serial_number,
                'account_type' => $request->account_type,
                'name' => $request->name,
                'level' => $branch->account->level + 1,
                'actual_balance' => 0,
                'budget_balance' => 0,
                'variance' => 0,
                'parent_id' =>  $branch->account->id,
                'root_id' => $assetsAccount->id,
                'code' => 'AGT-' . rand(1000000, 9999999),
                'company_id' => $branch->company_id,
                'agent_id' => $agent->id,
            ]);
        } catch(Exception $e){
            $agent->delete();
            $user->delete();

            logger('Failed to create account for agent: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create account');
        }
    
        $this->storeNotification([
            'user_id' => $user->id,
            'title' => 'Agent Registration',
            'message' => 'Agent ' . $request->name . ' has been registered successfully.'
        ]);

        return redirect()->route('agents.index')->with('success', 'Agent registered successfully');
    }

    public function getTasks($id)
    {
        $tasks = Task::where('agent_id', $id)->get();
        return response()->json(['tasks' => $tasks]);
    }

    public function getClients($id)
    {
        $clients = Client::where('agent_id', $id)->get();
        return response()->json(['clients' => $clients]);
    }

    public function getInvoices($id)
    {
        $invoices = Invoice::where('agent_id', $id)->get();
        return response()->json(['invoices' => $invoices]);
    }

    public function upload()
    {
        $agents = Agent::all();

        return view('agents.agentsUpload', compact('agents'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|mimes:xlsx',
        ]);

        Excel::import(new AgentsImport, $request->file('excel_file'));

        return redirect()->back()->with('success', 'Agents imported successfully.');
    }

    public function createAgentProfile(Request $request)
    {
        $user = Auth::user();

        // Check if the user already has an agent profile
        if (Agent::where('user_id', $user->id)->exists()) {
            return redirect()->back()->with('error', 'You already have an agent profile.');
        }

        try {
            // Create new agent profile
            Agent::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $request->phone_number,
                'company_id' => $request->company_id, // You might need to handle this differently
                'type' => $request->type, // You might need to handle this differently
            ]);

            $this->storeNotification([
                'user_id' => $user->id,
                'title' => 'Agent Profile Created',
                'message' => $user->name . ' agent profile has been created successfully.'
            ]);

            return redirect()->back()->with('success', 'Agent profile created successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to create agent profile: ' . $e->getMessage());
        }
    }

    public function exportCsv()
    {
        // Fetch all agents data
        $agents = Agent::with('branch')->get();

        // Create a CSV file in memory
        $csvFileName = 'agents.csv';
        $handle = fopen('php://output', 'w');

        // Set headers for the response
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $csvFileName . '"');

        // Add CSV header
        fputcsv($handle, ['Agent Name', 'Agent Type', 'Email', 'Phone Number', 'Company']);

        // Add company data to CSV
        foreach ($agents as $agent) {
            fputcsv($handle, [
                $agent->name,
                $agent->type,
                $agent->email,
                $agent->phone_number,
                $agent->company->name
            ]);
        }

        fclose($handle);
        exit();
    }
}
