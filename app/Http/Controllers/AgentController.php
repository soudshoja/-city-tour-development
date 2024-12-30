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
use App\Models\Branch;
use App\Models\Role;
use DateTimeImmutable;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class AgentController extends Controller
{
    use NotificationTrait;

    public function index()
    {
        $agentCount = Agent::count();
        $user = Auth::user();

        if ($user->role_id == Role::ADMIN) {
            // Admin can see all agents
            $agents = Agent::with('company')->get();
        } elseif ($user->role_id == Role::COMPANY) {
            // Company can only see their agents
            $agents = Agent::with(['branch' => function ($query) use ($user) {
                $query->where('id', $user->company_id);
            }])->with('agentType')->get();
        
        }
        $AgentsData = [
            'agentsCount' => $agentCount,
        ];

        // Pass both 'agents' and 'AgentsData' to the view
        return view('agents.agentsList', compact('agents', 'AgentsData'));
    }


    public function new()
    {
        $agents = Agent::all();
        $companies = Company::all();
        $admin = Role::ADMIN;

        return view('agents.agentsNew', compact('agents', 'companies', 'admin'));
        return view('agents.agentsNew', compact('agents', 'companies', 'admin'));
    }

    public function show($id)
    {
        $agent = Agent::with('branch.company', 'tasks', 'invoices', 'clients')->findOrFail($id);

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
        // dd(Task::with('invoiceDetail', 'client')->where('agent_id', $id)->get());
        // Return the main view with paginated data
        return view('agents.agentsShow', compact(
            'agent',
            'tasks',
            'invoices',
            'clients',
            'paid',
            'unpaid',
            'taskInvoiced',
            'taskNotInvoiced'
        ));
    }

    public function edit($id)
    {
        $agent = Agent::find($id);
        $branches = collect();

        $user = auth()->user();
        if ($user->role_id == Role::COMPANY) {
            $branches = Branch::where('company_id', $user->company->id)->get();
        }

        return view('agents.agentsEdit', compact('agent', 'branches'));
    }


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
        $user = Auth::user();
        $role = $user->role_id;

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone_number' => 'required|string',
            'company_id' => 'required',
            'type' => 'required'
        ]);

        // Create a new user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make('citytour123'),
            'role' => 'agent'
        ]);


        if ($role == Role::ADMIN) {
            // Create new agent
            $agent = Agent::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'company_id' => $request->company_id,
                'type' => $request->type,
            ]);
        } else {
            $agent = Agent::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'company_id' => $user->company->id,
                'type' => $request->type,
            ]);
        }

        $this->storeNotification([
            'user_id' => $user->id,
            'title' => 'Agent Registration',
            'message' => 'You have been registered as an agent.'
        ]);

        return redirect()->route('companiesshow.show', ['id' => $request->company_id])
            ->with('success', 'Agent registered successfully');
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
