<?php
// app/Http/Controllers/AgentController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agent;
use App\Models\User;
use App\Models\Task;
use App\Models\Company;
use App\Models\Client;
use App\Models\Invoice;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\AgentsImport;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AgentController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role == 'admin') {
            // Admin can see all agents
            $agents = Agent::with('company')->get();
        } elseif ($user->role == 'company') {
            // Company can only see their agents
            $agents = Agent::with('company')
                            ->where('company_id', $user->company->id) // assuming user belongs to one company
                            ->get();
        }


        return view('agents.agentsList', compact('agents'));
    }

    public function new()
    {
        $agents = Agent::all();
        $companies = Company::all();

        return view('agents.agentsNew', compact('agents', 'companies'));
    }

    public function show($id)
    {
        $agent = Agent::with('company', 'tasks', 'invoices', 'clients')->findOrFail($id);

        // Paginate all sections when viewing the main page (agentsShow)
        $tasks = Task::where('agent_email', $agent->email)->paginate(6);
        $invoices = Invoice::where('agent_id', $id)->paginate(6);
        $clients = Client::whereHas('tasks', function($query) use ($agent) {
            $query->where('agent_email', $agent->email);
        })->paginate(6);
        
        // Return the main view with paginated data
        return view('agents.agentsShow', compact('agent', 'tasks', 'invoices', 'clients'));
    }
    
    
    

    public function edit($id)
    {
        $agent = Agent::find($id);
        $companies = Company::all();

        return view('agents.agentsEdit', compact('agent', 'companies'));
    }


    public function update(Request $request, $id)
    {   
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
        ]);

        // Create a new agent associated with the user
        $agent = new Agent([
            'user_id' => $user->id,
            'company_id' => $request->company_id,
            'type' => $request->type,
            'email' => $request->email,
            'name' => $request->name,
            'phone_number' => $request->phone_number,
        ]);
        $agent->save();

        return redirect()->route('agents.index')->with('success', 'Agent updated successfully');
    }


    public function store(Request $request)
    {
        $user = Auth::user();
        $role = $user->role;

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


        if($role == 'admin'){
        // Create new agent
        $agent = Agent::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'company_id' => $request->company_id,
            'type' => $request->type,
        ]);
        } else{
            $agent = Agent::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'company_id' => $user->company->id,
                'type' => $request->type,
            ]);
        }


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

            return redirect()->back()->with('success', 'Agent profile created successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to create agent profile: ' . $e->getMessage());
        }
    }

    public function exportCsv()
    {
        // Fetch all agents data
        $agents = Agent::with('company')->get();

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
