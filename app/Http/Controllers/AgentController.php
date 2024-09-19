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
use Illuminate\Support\Facades\Hash;

class AgentController extends Controller
{
    public function index()
    {
        $agents = Agent::all();

        return view('agentsList', compact('agents'));
    }

    public function new()
    {
        $agents = Agent::all();

        return view('agentsNew', compact('agents'));
    }

    public function show($id)
    {
        $agent = Agent::find($id);
        // return view('agentsShow', compact('agent'));
        $pendingTasks = Task::where('agent_id', $agent->id)->where('status', 'pending')->get();
        return view('agentsShow', compact('agent', 'pendingTasks'));

    }

    public function edit($id)
{
    $agent = Agent::find($id);
    $companies = Company::all();
    
    return view('agentsEdit', compact('agent', 'companies'));
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
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email',
        'phone_number' => 'required|string',
        'company_id' => 'required',
        'type' => 'required'
    ]);

    // Create new agent
    $agent = Agent::create([
        'name' => $request->name,
        'email' => $request->email,
        'phone_number' => $request->phone_number,
        'company_id' => $request->company_id,
        'type' => $request->type,
    ]);

    return redirect()->route('agents.index')->with('success', 'Agent registered successfully');
}


    public function upload()
    {
        $agents = Agent::all();

        return view('agentsUpload', compact('agents'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|mimes:xlsx',
        ]);

        Excel::import(new AgentsImport, $request->file('excel_file'));

        return redirect()->back()->with('success', 'Agents imported successfully.');
    }

    // public function upload(Request $request)
    // {
    //     $request->validate([
    //         'file' => 'required|mimes:xlsx,xls'
    //     ]);

    //     $file = $request->file('file');
    //     Excel::import(new AgentsImport, $file);

    //     return redirect()->back()->with('success', 'Agents imported successfully!');
    // }

    // public function uploadExcel(Request $request)
    // {
    //     $request->validate([
    //         'file' => 'required|file|mimes:xlsx'
    //     ]);

    //     Excel::import(new AgentsImport, $request->file('file'));

    //     return redirect()->route('agents.upload')
    //          ->with('success', 'Agents imported successfully.');
    // }

}

