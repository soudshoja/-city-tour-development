<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Agent;
use App\Models\Task;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ClientsImport;
use App\Models\Branch;
use App\Models\Role;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    public function index()
    {
        return view('clients.index');
    }

    // List all clients or clients by agent ID
    public function list()
    {
        $user = Auth::user();
        $clientsCount = Client::count();

        if ($user->role_id == Role::ADMIN) {
            $agentIds = Agent::all()->pluck('id')->toArray();
            $clients = Client::with('agent.branch')->whereIn('agent_id', $agentIds)->paginate(6);
        } elseif ($user->role_id == Role::COMPANY) {
            $branch = Branch::where('company_id', $user->company->id)->pluck('id')->toArray();
            $agentIds = Agent::whereIn('branch_id', $branch)->pluck('id')->toArray();
            $clients = Client::with('agent.branch')->whereIn('agent_id', $agentIds)->paginate(6);
        } elseif ($user->role_id == Role::AGENT) {
            $agent = Agent::where('user_id', $user->id)->first();
            $clients = Client::with('agent.branch')->where('agent_id', $agent->id)->paginate(6);
        }

        $clientsNo = $clientsCount;

        return view('clients.list', compact('clients', 'clientsNo'));
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
            'phone' => 'nullable|string|max:15',    // Optional phone field
        ]);

        // Create a new client record
        try {
            $agent = Agent::where('email', $request->get('agent_email'))->first();

            Client::create([
                'name' => $request->get('name'),
                'email' => $request->get('email'),
                'status' => $request->get('status'),
                'phone' => $request->get('phone'),
                'address' => $request->get('address'),
                'passport_no' => $request->get('passport_no'),
                'agent_id' => $agent->id,
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
        $agents = Agent::with('company')->get();
        $invoices = Invoice::where('client_id', $id)->get();
        $tasks = Task::where('client_id', $id)->get();

        return view('clients.profile', compact('client', 'agents', 'invoices', 'tasks')); // Ensure the view exists
    }

    // Show the form for editing a client
    public function edit($id)
    {
        Gate::authorize('edit', [Auth::user(), Client::with('agent.branch.company')->findOrFail($id)]);

        $client = Client::findOrFail($id);
        return view('clients.edit', compact('client')); // Ensure the view exists
    }

    // Update the client in the database
    public function update(Request $request, $id)
    {
        Gate::authorize('update', Client::class);

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

    public function upload()
    {
        $clients = Client::with('agent')->get();

        return view('clients.clientsUpload', compact('clients'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|mimes:xlsx',
        ]);

        Excel::import(new ClientsImport, $request->file('excel_file'));

        return redirect()->back()->with('success', 'Clients imported successfully.');
    }


    public function changeAgent(Request $request, $id)
    {
        // Validate the new agent ID
        $validatedData = $request->validate([
            'agent_id' => 'required|exists:agents,id',
        ]);

        // Update the client's agent
        $client = Client::findOrFail($id);
        $client->agent_id = $request->agent_id;
        $client->save();

        // Get the new agent details
        $newAgent = $client->agent;

        // Update only pending tasks related to this client, changing the agent's email and id
        Task::where('client_id', $client->id)
            ->where('status', 'pending')
            ->update([
                'agent_id' => $newAgent->id,
                'agent_email' => $newAgent->email,
            ]);

        // Redirect back with a success message
        return redirect()->back()->with('success', 'Agent updated successfully for pending tasks.');
    }

    public function exportCsv()
    {
        // Fetch all agents data
        $clients = Client::with('agent')->get();

        // Create a CSV file in memory
        $csvFileName = 'clients.csv';
        $handle = fopen('php://output', 'w');

        // Set headers for the response
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $csvFileName . '"');

        // Add CSV header
        fputcsv($handle, ['Client Name', 'Client Email', 'Phone', 'Agent']);

        // Add company data to CSV
        foreach ($clients as $client) {
            fputcsv($handle, [
                $client->name,
                $client->email,
                $client->phone,
                $client->agent->name,
            ]);
        }

        fclose($handle);
        exit();
    }
}
