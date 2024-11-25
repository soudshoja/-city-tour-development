<?php

namespace App\Http\Controllers;

use Alimranahmed\LaraOCR\Facades\OCR;
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
use ConvertApi\ConvertApi;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use PhpParser\Node\Expr\Throw_;
use thiagoalessio\TesseractOCR\TesseractOCR;
use GuzzleHttp\Client as GuzzleClient;

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
        Gate::authorize('edit', [Client::class, Client::findOrFail($id)]);

        $agents = [];
        if(Gate::allows('clientAgent', Client::class)) {
            $agents = Agent::with('company')->get();
        }

        $client = Client::findOrFail($id);
        return view('clients.edit', compact('client','agents')); // Ensure the view exists
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
                    // Process the image using OCR
                    $ocrResponse = $this->processImage($request);  // Get the response from processImage
                    
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




      public function processImage(Request $request)
        {
            // Get the API key from the .env file
            $apiKey = env('OCR_SPACE_API_KEY');
            
            // Make sure the API key exists
            if (!$apiKey) {
                return response()->json(['error' => 'API key is missing.'], 400);
            }
        
            // Check if the request has a file
            if ($request->hasFile('file')) {
                $imagePath = $request->file('file')->getRealPath();
            
                // Use GuzzleClient instead of Client to avoid conflict
                $client = new GuzzleClient();
                $url = 'https://api.ocr.space/parse/image';
            
                try {
                    // Send the POST request to OCR.space
                    $response = $client->post($url, [
                        'headers' => [
                            'apikey' => $apiKey,  // Use the API key from .env
                        ],
                        'multipart' => [
                            [
                                'name' => 'file',
                                'contents' => fopen($imagePath, 'r'),
                                'filename' => 'image.jpg',
                            ],
                        ]
                    ]);
            
                    // Get the response body as an array
                    $result = json_decode($response->getBody()->getContents(), true);
                    
                    // Check if OCR was successful and parsed text is available
                    if (isset($result['ParsedResults'][0]['ParsedText'])) {
                        return $result; // return the entire response array to be used later
                    }
            
                    return response()->json(['error' => 'OCR processing failed.'], 500);
            
                } catch (\Exception $e) {
                    return response()->json(['error' => 'OCR processing failed: ' . $e->getMessage()], 500);
                }
            } else {
                return response()->json(['error' => 'No file provided.'], 400);
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
}
