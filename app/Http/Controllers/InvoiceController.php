<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Task;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $invoices = Invoice::where('agent_id', auth()->user()->agent->id)
            ->with(['client', 'agent'])
            ->get()
            ->groupBy('status');

        return view('invoice.index', compact('invoices'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        // Retrieve and decode the task IDs from the query parameters
        $taskIds = json_decode($request->query('task_ids'), true);

        // Fetch the tasks based on the task IDs
        $tasks = Task::whereIn('id', $taskIds)->get();

        //Fetch list of client
        $clients = Client::all();

        // Pass the client to the view
        return view('invoice.create', compact('tasks', 'clients'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $taskIds = json_decode($request->input('task_ids'), true);
        $amount = $request->input('amount');
        $clientId = $request->input('client_id');
        $agentId = auth()->user()->agent->id;

        // Check if a new client is being created
        if ($clientId === 'new') {
            $client = Client::create([
                'name' => $request->input('new_client_name'),
                'email' => $request->input('new_client_email'),
                'phone' => $request->input('new_client_phone'),
                'agent_id' => $agentId,
                'status_id' => 1, // Set default status_id
            ]);
            $clientId = $client->id;
        }

        // Create a new invoice

        $invoice = Invoice::create([
            'client_id' => $clientId,
            'agent_id' => $agentId,
            'amount' => $amount,
        ]);
        
        return redirect()->route('invoice.index')->with('status', 'Invoice created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function updateStatus(Request $request, Invoice $invoice)
    {
        $request->validate([
            'status' => 'required|string',
        ]);

        $invoice->status = $request->input('status');
        $invoice->save();

        return redirect()->route('invoice.index')->with('status', 'Invoice status updated successfully!');
    }
}
