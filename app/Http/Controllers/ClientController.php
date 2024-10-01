<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Exception;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index()
    {
        return view('clients.index');
    }

    // List all clients or clients by agent ID
    public function list($id = null)
    {
        // If agent ID is provided, filter clients by agent ID
        if ($id) {
            $clients = Client::where('agent_id', $id)->get();
        } else {
            $clients = Client::all();
        }

        return view('clients.list', compact('clients'));
    }

    // Show the form to create a new client
    public function create()
    {
        return view('clients.create');
    }

    // Store a new client
    public function store(Request $request)
    {   
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email',
            'status' => 'required',   // Optional status field
            'phone' => 'nullable|string|max:15',    // Optional phone field
        ]);
        
        // Create a new client record
        try {
            $statusId = intval($request->get('status'));
            $agentId = 1;  // Set the agent ID, modify this if you need it to be dynamic

            Client::create([
                'name' => $request->get('name'),
                'email' => $request->get('email'),
                'status_id' => $statusId,
                'phone' => $request->get('phone'),
                'agent_id' => $agentId,
            ]);

            // Redirect to the clients list with a success message
            return redirect()->route('clients.list')->with('success', 'Client added successfully!');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    // Show a specific client
    public function show($id)
    {
        $client = Client::findOrFail($id);
        return view('clients.profile', compact('client')); // Ensure the view exists
    }

    // Show the form for editing a client
    public function edit($id)
    {
        $client = Client::findOrFail($id);
        return view('clients.edit', compact('client')); // Ensure the view exists
    }

    // Update the client in the database
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email,' . $id,
            'status' => 'required',   // Optional status field
            'phone' => 'nullable|string|max:15',    // Optional phone field
        ]);

        // Find the client and update it
        try {
            $client = Client::findOrFail($id);
            $client->update([
                'name' => $request->get('name'),
                'email' => $request->get('email'),
                'status_id' => intval($request->get('status')),
                'phone' => $request->get('phone'),
            ]);

            // Redirect to the clients list with a success message
            return redirect()->route('clients.list')->with('success', 'Client updated successfully!');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}