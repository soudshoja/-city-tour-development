<?php
// app/Http/Controllers/AgentController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agent;
use App\Models\Client;
use App\Models\User;
use App\Models\Task;
use App\Models\Company;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\AgentsImport;
use Illuminate\Support\Facades\Hash;

class MobileController extends Controller
{

    public function agent()
    {
        return response()->json(Agent::all(), 200);
    }

    public function company()
    {
        return response()->json(Company::all(), 200);
    }

    public function task()
    {
        return response()->json(Task::all(), 200);
    }

    public function client()
    {
        return response()->json(Client::all(), 200);
    }


    public function store(Request $request)
    {
        $user = User::create($request->all());
        return response()->json($user, 201);
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

}

