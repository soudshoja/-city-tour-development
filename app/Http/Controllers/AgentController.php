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
use App\Models\BonusAgent;
use DateTimeImmutable;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AgentController extends Controller
{
    use NotificationTrait;

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Agent::class);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        $agentsQuery = Agent::with(['branch.company', 'agentType'])->orderBy('created_at', 'desc');

        if ($user->role_id == Role::ADMIN) {
            if ($companyId) {
                $agentsQuery->whereHas('branch', fn($q) => $q->where('company_id', $companyId));
            }
        } elseif ($user->role_id == Role::COMPANY) {
            $branchIds = Branch::where('company_id', $companyId)->pluck('id');
            $agentsQuery->whereIn('branch_id', $branchIds);
        } elseif ($user->role_id == Role::BRANCH) {
            $agentsQuery->where('branch_id', $user->branch->id);
        } elseif ($user->role_id == Role::AGENT) {
            $agentsQuery->where('id', $user->agent->id);
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $agentsQuery->where('branch_id', $user->accountant->branch_id);
        } else {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        if ($request->has('q')) {
            $search = $request->input('q');
            $agentsQuery->where(function ($query) use ($search) {
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

        $agents = $agentsQuery->paginate(20)->withQueryString();

        return view('agents.index', compact('agents'));
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
            ->paginate(25, ['*'], 'tasks');

        $taskInvoiced = Task::where('agent_id', $id)->whereHas('invoiceDetail')->count();
        $taskNotInvoiced = Task::where('agent_id', $id)->whereDoesntHave('invoiceDetail')->count();

        foreach ($tasks as $task) {
            $date = new DateTimeImmutable($task->created_at);
            $task->created_at = $date->format('d-M-Y');
        }

        $month = request('month') ? Carbon::parse(request('month'))->startOfMonth() : now()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();

        $stored = AgentMonthlyCommissions::where('agent_id', $agent->id)
            ->where('month', $month->month)
            ->where('year', $month->year)
            ->first();

        if ($stored) {
            $totalCommission = number_format($stored->total_commission, 3);
            $totalProfit = number_format($stored->total_profit, 3);
        } else {
            $monthlySummary = $this->calculateMonthlySummary($agent, $month);
            $totalCommission = number_format($monthlySummary['commission'], 3);
            $totalProfit = number_format($monthlySummary['profit'], 3);
        }
       
        $totalLoss = number_format(
            JournalEntry::where('account_id', $agent->loss_account_id)
                ->whereBetween('transaction_date', [$month, $endOfMonth])
                ->sum('debit'),
            3
        );

        $allInvoicesQuery = Invoice::with(['invoiceDetails.task'])
            ->where('agent_id', $id)
            ->whereBetween('invoice_date', [$month, $endOfMonth]);

        // Paid & outstanding
        $totalPaid = number_format(
            Invoice::where('agent_id', $id)
                ->whereBetween('invoice_date', [$month, $endOfMonth])
                ->where('status', 'paid')
                ->sum('amount'),
            2
        );

        $totalOutstanding = number_format(
            Invoice::where('agent_id', $id)
                ->whereBetween('invoice_date', [$month, $endOfMonth])
                ->where('status', '<>', 'paid')
                ->sum('amount'),
            2
        );

        // Now get paginated invoices for display
        $invoices = $allInvoicesQuery->orderBy('invoice_date', 'asc')->paginate(25, ['*'], 'invoices');

        foreach ($invoices as $invoice) {
            $invoice->total_profit = number_format($invoice->invoiceDetails->sum('profit'), 3);
            $invoice->total_commission = number_format(in_array($agent->type_id, [2, 3, 4]) ? $invoice->invoiceDetails->sum('commission') : 0, 3);
            $invoice->task_count = $invoice->invoiceDetails->count();

            $invoice->tasks = $invoice->invoiceDetails->map(function ($detail) {
                return [
                    'task_reference' => $detail->task->reference ?? 'N/A',
                    'passenger_name' => $detail->task->passenger_name ?? 'N/A',
                    'task_price' => $detail->task_price,
                    'markup_price' => $detail->markup_price,
                ];
            });
        }

        $clients = Client::with('invoices')->whereHas('tasks', function ($query) use ($agent) {
            $query->where('agent_id', $agent->id);
        })->paginate(25, ['*'], 'clients');

        foreach ($clients as $client) {
            $client->paid = number_format($client->invoices->where('status', 'paid')->sum('amount'), 3);
            $client->unpaid = number_format($client->invoices->where('status', '<>', 'paid')->sum('amount'), 3);
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

        $filterBonusMonth = (int) request('filter_month', now()->month);
        $filterBonusYear  = (int) request('filter_year', now()->year);
        $filterBonus = Carbon::createFromDate($filterBonusYear, $filterBonusMonth, 1)->startOfMonth();

        $bonuses = BonusAgent::where('agent_id', $agent->id)
            ->whereMonth('created_at', $filterBonus->month)
            ->whereYear('created_at', $filterBonus->year)
            ->with('transaction')
            ->orderByDesc('created_at')
            ->get();

        $clientCount = Client::where('agent_id', $agent->id)->count();

        return view('agents.agentsShow', compact(
            'agent',
            'agentType',
            'tasks',
            'invoices',
            'clients',
            'paid',
            'unpaid',
            'totalPaid',
            'totalOutstanding',
            'taskInvoiced',
            'taskNotInvoiced',
            'supplierCompany',
            'totalCommission',
            'totalProfit',
            'bonuses',
            'clientCount',
            'filterBonus',
            'totalLoss',
        ));
    }

    public function calculateMonthlySummary(Agent $agent, $month = null)
    {
        $from = Carbon::parse($month ?? now()->startOfMonth());
        $to = (clone $from)->endOfMonth();

        $invoices = Invoice::with('invoiceDetails')
            ->where('agent_id', $agent->id)
            ->whereBetween('invoice_date', [$from, $to])
            ->get();

        // Sum profit and commission directly from invoice_details
        $totalProfit = 0;
        $totalTaskCommission = 0;

        foreach ($invoices as $invoice) {
            $totalProfit += $invoice->invoiceDetails->sum('profit');
            $totalTaskCommission += $invoice->invoiceDetails->sum('commission');
        }

        // Monthly commission based on agent type
        switch ($agent->type_id) {
            case 1: // Salary only
                $monthlyCommission = 0;
                break;

            case 2: // Commission only
                $monthlyCommission = $totalTaskCommission;
                break;

            case 3: // Both-A: sum of per-task commission + salary
                $monthlyCommission = $totalTaskCommission + ($agent->salary ?? 0);
                break;

            case 4: // Both-B: (total_profit - salary) × rate + salary (only if profit > target)
                if ($totalProfit > ($agent->target ?? 0)) {
                    $base = $totalProfit - ($agent->salary ?? 0);
                    $monthlyCommission = ($base * ($agent->commission ?? 0.15)) + ($agent->salary ?? 0);
                } else {
                    $monthlyCommission = 0;
                }
                break;

            default:
                $monthlyCommission = 0;
        }

        return [
            'commission' => $monthlyCommission,
            'profit' => $totalProfit,
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
                        'transaction_date' => now(),
                    ]);

                    JournalEntry::create([
                        'transaction_id' => $transaction->id,
                        'branch_id' => $agent->branch_id,
                        'company_id' => $agent->branch->company_id,
                        'account_id' => $salaryExpenseAccount->id,
                        'transaction_date' => now(),
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

        $branch = Branch::with('company', 'account')->find($request->branch_id);

        if (!$branch) {
            logger('Failed to create agent: Branch not found');
            return redirect()->back()->with('error', 'Branch not found');
        }

        if (!$branch->account) {
            logger('Failed to create agent: Branch ' . $branch->name . ' does not have an account');
            return redirect()->back()->with('error', 'Something went wrong, please contact support');
        }

        $assetsAccount = Account::where('name', 'Assets')->first();

        if (!$assetsAccount) {
            logger('Failed to create agent: Assets account does not exist');
            return redirect()->back()->with('error', 'Something went wrong, please contact support');
        }

        try {

            $role = Role::where('name', 'agent')
                ->where('company_id', $branch->company_id)
                ->first();

            if (!$role) {
                $role = Role::create([
                    'name' => 'agent',
                    'description' => 'Agent role for company ' . $branch->company->name,
                    'company_id' => $branch->company_id,
                ]);
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => Role::AGENT,
                'remember_token' => Str::random(10),
                'first_login' => 1,
            ])->assignRole($role);
        } catch (Exception $e) {
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

        try {
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
        } catch (Exception $e) {
            $agent->delete();
            $user->delete();

            logger('Failed to create account for agent: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create account');
        }

        // Auto-create Agent Profit & Loss accounts
        try {
            $companyId = $branch->company_id;

            $accruedExpenses = DB::table('accounts')
                ->where('company_id', $companyId)
                ->where('name', 'Accrued Expenses')
                ->whereIn('parent_id', function ($q) use ($companyId) {
                    $q->select('id')->from('accounts')
                        ->where('company_id', $companyId)
                        ->whereNull('parent_id')
                        ->where('name', 'Liabilities');
                })
                ->first();

            if ($accruedExpenses) {
                $profitGroup = DB::table('accounts')
                    ->where('company_id', $companyId)
                    ->where('parent_id', $accruedExpenses->id)
                    ->where('name', 'Agent Profit Payable')
                    ->first();

                if (!$profitGroup) {
                    $profitGroupId = DB::table('accounts')->insertGetId([
                        'code' => '2230',
                        'name' => 'Agent Profit Payable',
                        'company_id' => $companyId,
                        'root_id' => $accruedExpenses->root_id ?? $accruedExpenses->id,
                        'parent_id' => $accruedExpenses->id,
                        'account_type' => $accruedExpenses->account_type,
                        'report_type' => $accruedExpenses->report_type ?? Account::REPORT_TYPES['BALANCE_SHEET'],
                        'level' => ($accruedExpenses->level ?? 0) + 1,
                        'is_group' => 1,
                        'disabled' => 0,
                        'actual_balance' => 0,
                        'budget_balance' => 0,
                        'variance' => 0,
                        'currency' => 'KWD',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $profitGroup = DB::table('accounts')->find($profitGroupId);
                }

                $existingProfit = DB::table('accounts')
                    ->where('company_id', $companyId)
                    ->where('parent_id', $profitGroup->id)
                    ->where('agent_id', $agent->id)
                    ->where('name', $agent->name)
                    ->first();

                if ($existingProfit) {
                    $profitAccountId = $existingProfit->id;
                } else {
                    $lastProfitCode = DB::table('accounts')->where('parent_id', $profitGroup->id)->max('code');
                    $profitCode = (string) (($lastProfitCode ? (int) $lastProfitCode : (int) $profitGroup->code) + 1);

                    $profitAccountId = DB::table('accounts')->insertGetId([
                        'code' => $profitCode,
                        'name' => $agent->name,
                        'company_id' => $companyId,
                        'root_id' => $profitGroup->root_id ?? $profitGroup->id,
                        'parent_id' => $profitGroup->id,
                        'branch_id' => $request->branch_id,
                        'agent_id' => $agent->id,
                        'account_type' => $profitGroup->account_type,
                        'report_type' => $profitGroup->report_type ?? Account::REPORT_TYPES['BALANCE_SHEET'],
                        'level' => ($profitGroup->level ?? 0) + 1,
                        'is_group' => 0,
                        'disabled' => 0,
                        'actual_balance' => 0,
                        'budget_balance' => 0,
                        'variance' => 0,
                        'currency' => 'KWD',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $agent->update(['profit_account_id' => $profitAccountId]);
            }

            $accountsReceivable = DB::table('accounts')
                ->where('company_id', $companyId)
                ->where('name', 'Accounts Receivable')
                ->whereIn('parent_id', function ($q) use ($companyId) {
                    $q->select('id')->from('accounts')
                        ->where('company_id', $companyId)
                        ->whereNull('parent_id')
                        ->where('name', 'Assets');
                })
                ->first();

            if ($accountsReceivable) {
                $company = $branch->company;

                $companyGroup = DB::table('accounts')
                    ->where('company_id', $companyId)
                    ->where('parent_id', $accountsReceivable->id)
                    ->where('name', $company->name)
                    ->first();

                if (!$companyGroup) {
                    $lastArCode = DB::table('accounts')->where('parent_id', $accountsReceivable->id)->max('code');
                    $companyCode = (string) (($lastArCode ? (int) $lastArCode : (int) $accountsReceivable->code) + 1);

                    $companyGroupId = DB::table('accounts')->insertGetId([
                        'code' => $companyCode,
                        'name' => $company->name,
                        'company_id' => $companyId,
                        'root_id' => $accountsReceivable->root_id ?? $accountsReceivable->id,
                        'parent_id' => $accountsReceivable->id,
                        'account_type' => $accountsReceivable->account_type,
                        'report_type' => $accountsReceivable->report_type ?? Account::REPORT_TYPES['BALANCE_SHEET'],
                        'level' => ($accountsReceivable->level ?? 0) + 1,
                        'is_group' => 1,
                        'disabled' => 0,
                        'actual_balance' => 0,
                        'budget_balance' => 0,
                        'variance' => 0,
                        'currency' => 'KWD',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $companyGroup = DB::table('accounts')->find($companyGroupId);
                }

                $agentGroup = DB::table('accounts')
                    ->where('company_id', $companyId)
                    ->where('parent_id', $companyGroup->id)
                    ->where('agent_id', $agent->id)
                    ->first();

                if (!$agentGroup) {
                    $lastCompanyCode = DB::table('accounts')->where('parent_id', $companyGroup->id)->max('code');
                    $agentGroupCode = (string) (($lastCompanyCode ? (int) $lastCompanyCode : (int) $companyGroup->code) + 1);

                    $agentGroupId = DB::table('accounts')->insertGetId([
                        'code' => $agentGroupCode,
                        'name' => $agent->name,
                        'company_id' => $companyId,
                        'root_id' => $companyGroup->root_id ?? $companyGroup->id,
                        'parent_id' => $companyGroup->id,
                        'branch_id' => $request->branch_id,
                        'agent_id' => $agent->id,
                        'account_type' => $companyGroup->account_type,
                        'report_type' => $companyGroup->report_type ?? Account::REPORT_TYPES['BALANCE_SHEET'],
                        'level' => ($companyGroup->level ?? 0) + 1,
                        'is_group' => 1,
                        'disabled' => 0,
                        'actual_balance' => 0,
                        'budget_balance' => 0,
                        'variance' => 0,
                        'currency' => 'KWD',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $agentGroup = DB::table('accounts')->find($agentGroupId);
                }

                $existingLoss = DB::table('accounts')
                    ->where('company_id', $companyId)
                    ->where('parent_id', $agentGroup->id)
                    ->where('agent_id', $agent->id)
                    ->where('name', 'Agent Loss Receivable')
                    ->first();

                if ($existingLoss) {
                    $lossAccountId = $existingLoss->id;
                } else {
                    $lastAgentCode = DB::table('accounts')->where('parent_id', $agentGroup->id)->max('code');
                    $lossCode = (string) (($lastAgentCode ? (int) $lastAgentCode : (int) $agentGroup->code) + 1);

                    $lossAccountId = DB::table('accounts')->insertGetId([
                        'code' => $lossCode,
                        'name' => 'Agent Loss Receivable',
                        'company_id' => $companyId,
                        'root_id' => $agentGroup->root_id ?? $agentGroup->id,
                        'parent_id' => $agentGroup->id,
                        'branch_id' => $request->branch_id,
                        'agent_id' => $agent->id,
                        'account_type' => $agentGroup->account_type,
                        'report_type' => $agentGroup->report_type ?? Account::REPORT_TYPES['BALANCE_SHEET'],
                        'level' => ($agentGroup->level ?? 0) + 1,
                        'is_group' => 0,
                        'disabled' => 0,
                        'actual_balance' => 0,
                        'budget_balance' => 0,
                        'variance' => 0,
                        'currency' => 'KWD',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $agent->update(['loss_account_id' => $lossAccountId]);
            }
        } catch (Exception $e) {
            $agent->delete();
            $user->delete();

            logger('Failed to create profit/loss accounts for agent: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create agent profit/loss accounts');
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
                $agent->branch->company->name
            ]);
        }

        fclose($handle);
        exit();
    }

    /**
     * Get commission account ID for the company
     */
    private function getCommissionAccountId($companyId)
    {
        $account = Account::where('name', 'Commissions (Agents)')
            ->where('company_id', $companyId)
            ->first();

        return $account ? $account->id : null;
    }
}
