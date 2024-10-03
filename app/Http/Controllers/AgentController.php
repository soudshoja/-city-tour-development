<?php
// app/Http/Controllers/AgentController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agent;
use App\Models\User;
use App\Models\Task;
use App\Models\Company;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\AgentsImport;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AgentController extends Controller
{
    public function index()
    {
        $agents = Agent::with('company')->get();

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
        $agent = Agent::with('company')->find($id);

        // return view('agentsShow', compact('agent'));
        $pendingTasks = Task::where('agent_email', $agent->email)->where('status', 'pending')->get();
        return view('agents.agentsShow', compact('agent', 'pendingTasks'));
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
                'company_id' => '',
                'type' => $request->type,
            ]);
        }


        return redirect()->route('agents.index')->with('success', 'Agent registered successfully');
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
