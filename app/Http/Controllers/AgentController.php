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
use App\Models\Branch;
use App\Models\Role;
use App\Models\SupplierCompany;
use DateTimeImmutable;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
class AgentController extends Controller
{
    use NotificationTrait;

    public function index()
    {
        $user = Auth::user();
        $agents = collect();
        $agentCount = 0;

        if ($user->role_id == Role::COMPANY) {
            // Get agents belonging to the company
            $company_id = $user->company_id;
            $branchesId = Branch::where('company_id', $user->company->id)->pluck('id');
            $agents = Agent::whereIn('branch_id', $branchesId)->get();
            $agentCount = $agents->count();
        } elseif ($user->role_id == Role::BRANCH) {
            // Get agents belonging to the branch
            $branch_id = $user->branch_id;
            $agents = Agent::where('branch_id', $branch_id)->get();
            $agentCount = $agents->count();

        } elseif ($user->role_id == Role::ADMIN) {
            // Admin can see all agents
            $agents = Agent::all();
            $agentCount = $agents->count();
        }

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
        $tasks = Task::with('agent', 'invoiceDetail')->where('agent_id', $id)->paginate(6, ['*'], 'tasks');

        $taskInvoiced = Task::where('agent_id', $id)->whereHas('invoiceDetail')->count();
        $taskNotInvoiced = Task::where('agent_id', $id)->whereDoesntHave('invoiceDetail')->count();

        foreach ($tasks as $task) {
            $date = new DateTimeImmutable($task->created_at);
            $task->created_at = $date->format('d-M-Y');
        }

        $invoices = Invoice::with('invoiceDetails')->where('agent_id', $id)->paginate(4, ['*'], 'invoices');
        foreach ($invoices as $invoice) {
            $cost = 0;
            foreach ($invoice->invoiceDetails as $detail) {
                $cost += $detail->supplier_price;
                $cost = number_format($cost, 2);
            }
            $invoice->cost = (string)$cost;
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
        ));
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

        try {
            $agent->update($request->all());

            return redirect()->back()->with('success', 'Agent updated successfully');
        } catch (Exception $error) {
            logger('Failed to update agent: ' . $error->getMessage());

            return redirect()->back()->with('error', 'Failed to update agent');
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
                'reference_id' => $user->id,
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
