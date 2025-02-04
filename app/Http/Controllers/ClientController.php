<?php

namespace App\Http\Controllers;

use App\Http\Traits\Converter;
use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\Invoice;
use App\Models\Agent;
use App\Models\Task;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ClientsImport;
use App\Models\Branch;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class ClientController extends Controller
{
    use Converter;

    public function index()
    {
        $user = Auth::user();
        if ($user->role_id == Role::COMPANY) {
            $branch = Branch::where('company_id', $user->company->id)->pluck('id')->toArray();
            $agentIds = Agent::whereIn('branch_id', $branch)->pluck('id')->toArray();
            $clientsCount = Client::whereIn('agent_id', $agentIds)->count();
        } elseif ($user->role_id == Role::AGENT) {
            $agent = Agent::where('user_id', $user->id)->first();
            $clientsCount = Client::where('agent_id', $agent->id)->count();
        } else {
            $clientsCount = Client::count();
        }

        if ($user->role_id == Role::ADMIN) {
            $agentIds = Agent::all()->pluck('id')->toArray();

            // retrieve client that has the latest task
            $clients = Client::with('agent.branch')->whereIn('agent_id', $agentIds)->orderByDesc(
                Task::select('client_id')->whereColumn('client_id', 'clients.id')->limit(1)
            )->get();
        } elseif ($user->role_id == Role::COMPANY) {
            $branch = Branch::where('company_id', $user->company->id)->pluck('id')->toArray();
            $agentIds = Agent::whereIn('branch_id', $branch)->pluck('id')->toArray();

            // retrieve client that has the latest task
            $clients = Client::with('agent.branch')->whereIn('agent_id', $agentIds)->orderByDesc(
                Task::select('client_id')->whereColumn('client_id', 'clients.id')->limit(1)
            )->get();
        } elseif ($user->role_id == Role::AGENT) {
            $agent = Agent::where('user_id', $user->id)->first();

            // retrieve client that has the latest task
            $clients = Client::with('agent.branch')->where('agent_id', $agent->id)->orderByDesc(
                Task::select('client_id')->whereColumn('client_id', 'clients.id')->limit(1)
            )->get();
        }

        return view('clients.index', compact('clients', 'clientsCount'));
    }

    public function list()
    {

    }

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
            return redirect()->route('clients.index')->with('success', 'Client added successfully!');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    // Show a specific client
    public function show($id)
    {
        $client = Client::findOrFail($id);
        $agents = Agent::with('branch')->get();
        $invoices = Invoice::with('invoiceDetails', 'agent')->where('client_id', $id)->get();
        $tasks = Task::where('client_id', $id)->get();
        $paid = $invoices->where('status', 'paid')->sum('amount');
        $unpaid = $invoices->where('status', '<>', 'paid')->sum('amount');
        $clients = Client::with('agent.branch')->get();
            // Fetch the client groups where this client is the parent (i.e., group of sub-clients)
            $childClients = ClientGroup::where('parent_client_id', $id)
            ->with('childClient') // Load related child clients
            ->get()
            ->pluck('childClient'); // Extract the child clients
        
        return view('clients.profile', compact('client', 'agents', 'invoices', 'tasks', 'paid', 'unpaid', 'childClients', 'clients')); // Ensure the view exists
    }

    // Show the form for editing a client
    public function edit($id)
    {
        Gate::authorize('edit', [Client::class, Client::findOrFail($id)]);

        $agents = [];
        if (Gate::allows('clientAgent', Client::class)) {
            $agents = Agent::with('branch')->get();
        }

        $client = Client::findOrFail($id);
        return view('clients.edit', compact('client', 'agents')); // Ensure the view exists
    }

    // Update the client in the database
    public function update(Request $request, $id)
    {
        Gate::authorize('update', [Client::class, $client = Client::findOrFail($id)]);
        
        // Validate the incoming request data
        $validated = $request->validate([
            'name' => 'string|max:255',
            'email' => 'email|unique:clients,email,' . $id,
            'status' => 'nullable',
            'phone' => 'string|max:15',
            'file' => 'nullable|mimes:jpeg,jpg,png', // Optional passport file field
        ]);

        try {
            // Update the client data
            $client->update($request->only(['name', 'email', 'status', 'phone', 'address']));

            // If a file (image) is uploaded, process it
            if ($request->hasFile('file')) {
                try {
                    $imagePath = $request->file('file')->getRealPath();
                    // Process the image using OCR
                    $ocrResponse = $this->processImage($imagePath);  // Get the response from processImage

                    // Now $ocrResponse is already an array, so no need to decode it
                    if (isset($ocrResponse['ParsedResults'][0]['ParsedText'])) {
                        $parsedText = $ocrResponse['ParsedResults'][0]['ParsedText'];

                        // You can now use the parsed text (e.g., for passport extraction)
                        $openai = new OpenAiController();
                        $response = $openai->extractPassport($parsedText); // Pass the parsed text to OpenAI

                        // Since extractPassport already returns the parsed data (not a JSON string), 
                        // we can use it directly as an array
                        if (isset($response['data'])) {
                            $this->updateClientPassport($client, $response['data']);
                        } else {
                            // Handle case where 'data' is not available
                            return redirect()->back()->withInput()->with('error', 'OCR processing failed or no data returned.');
                        }
                    } else {
                        return redirect()->back()->withInput()->with('error', 'No text found in OCR response.');
                    }
                } catch (Exception $e) {
                    return redirect()->back()->withInput()->with('error', $e->getMessage());
                }
            }

            // Redirect back with success message
            return Redirect::back()->with('success', 'Client updated successfully!');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }





    public function updateClientPassport($client, $data)
    {
        $client->passport_no = $data['passport_no'];
        $client->civil_no = $data['civil_no'];
        // $client->passport_expiry = $data['passport_expiry'];
        // $client->passport_country = $data['passport_country'];
        $client->save();
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


    public function addToGroup(Request $request)
    {
        $request->validate([
            'parent_client_id' => 'required|exists:clients,id',
            'child_client_id' => 'required|exists:clients,id|different:parent_client_id',
        ]);

        // Check if relationship already exists
        $exists = ClientGroup::where('parent_client_id', $request->parent_client_id)
                             ->where('child_client_id', $request->child_client_id)
                             ->exists();

        if ($exists) {
            return response()->json(['message' => 'Client is already in this group'], 409);
        }

        // Create the client group relationship
        ClientGroup::create([
            'parent_client_id' => $request->parent_client_id,
            'child_client_id' => $request->child_client_id,
        ]);

        return response()->json(['message' => 'Client added to the group successfully'], 201);
    }

    /**
     * Remove a client from a group.
     */
    public function removeFromGroup(Request $request)
    {
        $request->validate([
            'parent_client_id' => 'required|exists:clients,id',
            'child_client_id' => 'required|exists:clients,id',
        ]);

        $deleted = ClientGroup::where('parent_client_id', $request->parent_client_id)
                              ->where('child_client_id', $request->child_client_id)
                              ->delete();

        if ($deleted) {
            return response()->json(['message' => 'Client removed from the group'], 200);
        }

        return response()->json(['message' => 'Client not found in this group'], 404);
    }

    public function getSubClients(int $parentClientId)
    {
        Log::info('clientId:', ['clientId' => $parentClientId]);
        $subClients = ClientGroup::where('parent_client_id', $parentClientId)
            ->with('childClient') // Load the full client details
            ->get()
            ->pluck('childClient'); // Extract only child clients

            Log::info('subClients:', ['subClients' => $subClients]);
        return response()->json($subClients);
    }

}
