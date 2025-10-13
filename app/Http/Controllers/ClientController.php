<?php

namespace App\Http\Controllers;

use App\Http\Traits\Converter;
use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\Invoice;
use App\Models\Agent;
use App\Models\Task;
use App\Models\ClientAssignmentRequest;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ClientsImport;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Charge;
use App\Models\Country;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\RefundClient;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\Credit;
use App\Models\InvoiceReceipt;
use App\Enums\ChargeType;
use App\Http\Traits\NotificationTrait;
use App\Models\Company;
use App\Models\InvoicePartial;
use App\Models\Notification;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use App\Services\ChargeService;

class ClientStoreResponse
{
    public string $status;
    public string $type;
    public string $message;
    public ?array $data;
    public ?int $task_id;

    public function __construct($status, $type, $message, $data = null, $task_id = null)
    {
        $this->status = $status;
        $this->type = $type;
        $this->message = $message;
        $this->data = $data;
        $this->task_id = $task_id;
    }
}

class ClientController extends Controller
{
    use Converter, NotificationTrait;

    public function index(Request $request)
    {
        $user = Auth::user();

        $clients = Client::with('agent.branch');
        $fullClients = clone $clients;
        $agentIds = [];

        if ($user->role_id == Role::ADMIN) {
            $agentIds = Agent::all()->pluck('id')->toArray();
            $branch = Branch::pluck('id')->toArray();
            $agent = Agent::whereIn('branch_id', $branch)->first();
        } elseif ($user->role_id == Role::COMPANY) {
            $branch = Branch::where('company_id', $user->company->id)->pluck('id')->toArray();
            $agent = Agent::whereIn('branch_id', $branch)->first();
            $agentIds = Agent::whereIn('branch_id', $branch)->pluck('id')->toArray();
        } elseif ($user->role_id == Role::AGENT) {
            $agent = Agent::where('user_id', $user->id)->first();
            $agentIds = [$agent->id];
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $branch = Branch::where('id', $user->accountant->branch_id)->pluck('id')->toArray();
            $agent = Agent::whereIn('branch_id', $branch)->first();
            $agentIds = Agent::whereIn('branch_id', $branch)->pluck('id')->toArray();
        }

        $clients = $clients->where(function ($query) use ($agentIds) {
            $query->whereIn('agent_id', $agentIds)
                ->orWhereHas('agents', function ($q) use ($agentIds) {
                    $q->whereIn('agent_id', $agentIds);
                });
        });

        $fullClients = (clone $clients);

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $clients = $clients->where(function ($query) use ($search) {
                $searchTerm = '%' . strtolower($search) . '%';
                $query->where('first_name', 'LIKE', $searchTerm)
                    ->orWhere('middle_name', 'LIKE', $searchTerm)
                    ->orWhere('last_name', 'LIKE', $searchTerm)
                    ->orWhere('email', 'LIKE', $searchTerm)
                    ->orWhere('phone', 'LIKE', $searchTerm)
                    ->orWhere('civil_no', 'LIKE', $searchTerm)
                    ->orWhereHas('agent', function ($q) use ($searchTerm) {
                        $q->where('name', 'LIKE', $searchTerm);
                    });

                // Handle multi-word search for name combinations
                if (str_word_count($search) > 1) {
                    $searchWords = explode(' ', trim($search));
                    $query->orWhere(function ($nameQuery) use ($searchWords) {
                        $firstWord = $searchWords[0];
                        $lastWord = end($searchWords);
                        $middleWords = array_slice($searchWords, 1, -1);

                        // For 2 words: first_name + last_name
                        if (count($searchWords) == 2) {
                            $nameQuery->where(function ($q) use ($firstWord, $lastWord) {
                                $q->where('first_name', 'LIKE', '%' . $firstWord . '%')
                                    ->where('last_name', 'LIKE', '%' . $lastWord . '%');
                            });
                        }
                        // For 3+ words: first_name + middle_name(s) + last_name
                        else if (count($searchWords) >= 3) {
                            $nameQuery->where(function ($q) use ($firstWord, $middleWords, $lastWord) {
                                $q->where('first_name', 'LIKE', '%' . $firstWord . '%')
                                    ->where('last_name', 'LIKE', '%' . $lastWord . '%');

                                // Add middle name conditions
                                foreach ($middleWords as $middleWord) {
                                    $q->where('middle_name', 'LIKE', '%' . $middleWord . '%');
                                }
                            });
                        }
                    });
                }
            });
        }

        $clientsCount = $clients->count();

        $clients = $clients->orderByDesc('created_at')->paginate(20);
        $fullClients = $fullClients->orderByDesc('created_at')->get();

        if ($user->role_id == Role::AGENT) {
            $clients->getCollection()->transform(function ($client) use ($user) {
                if ($client->agent_id != $user->id) {
                    $client->totalCredit = Credit::getTotalCreditsByClient($client->id);
                } else {
                    $client->totalCredit = Credit::whereHas('payment', function ($q) use ($user) {
                        $q->where('agent_id', $user->agent->id);
                    })
                        ->where('client_id', $client->id)
                        ->sum('amount');
                }
                return $client;
            });
        } else {
            $clients->getCollection()->transform(function ($client) {
                $client->totalCredit = Credit::getTotalCreditsByClient($client->id);
                return $client;
            });
        }


        return view('clients.index', compact(
            'agent',
            'fullClients',
            'clients',
            'clientsCount'
        ));
    }

    public function storeProcess(Request $request) : ClientStoreResponse
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'dial_code' => 'required|string|max:30',
            'email' => 'nullable|email',
            'civil_no' => 'nullable|string|max:100',
            'phone' => 'required|string|max:15',
            'agent_id' => 'required|exists:agents,id',
            'company_id' => 'nullable|exists:companies,id',
            'passport_no' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
        ]);


        if (!$request->company_id) { //this fallback is temporary until company_id is added in the form
            $companyId = Agent::find($request->agent_id)->branch->company_id;

            $request->merge(['company_id' => $companyId]);
        }

        $existingClient = null;
        $duplicateType = null;

        if ($request->civil_no) {

            $existingClient = Client::where('company_id', $request->company_id)
                ->where('civil_no', $request->civil_no)
                ->with('agent') // Load the owner agent
                ->first();

            $duplicateType = 'civil_no';

        } else {
            $existingClient = Client::where('company_id', $request->company_id)
                ->where('first_name', $request->first_name)
                ->where('phone', preg_replace('/\s+/', '', $request->phone))
                ->with('agent') // Load the owner agent
                ->first();

            $duplicateType = 'name_phone';
        }

        $message  = '';

        if ($existingClient) {
            $duplicateResponse = $this->handleDuplicateClient($existingClient, $request->agent_id, $duplicateType);

            Log::info('Duplicate client detected: ', $duplicateResponse);            

            if ($duplicateResponse['status'] == 'success') { // means we succeed in handling duplicate client by showing assignment request form

                return new ClientStoreResponse(
                    'error',
                    'duplicate',
                    $duplicateResponse['message'],
                    $duplicateResponse['data']
                );
            }

            return new ClientStoreResponse(
                'error',
                'general',
                $duplicateResponse['message']
            );
        }


        try {
            DB::beginTransaction();
            $message = 'Client created successfully.';

            $client = Client::create([
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'status' => 'active',
                'phone' => preg_replace('/\s+/', '', $request->phone),
                'country_code' => $request->dial_code,
                'date_of_birth' => $request->date_of_birth,
                'address' => $request->address,
                'civil_no' => $request->civil_no,
                'passport_no' => $request->passport_no,
                'old_passport_no' => $request->passport_no,
                'agent_id' => $request->agent_id,
                'company_id' => $request->company_id,
            ]);

            if ($request->filled('task_id')) {
                $task = Task::findOrFail($request->task_id);

                // Link client
                $task->client_id = $client->id;
                $task->client_name = $client->full_name;

                // Attempt to auto-enable
                if (!$task->enabled && $task->is_complete) {
                    $task->enabled = true;
                }

                $task->save();
                $message = 'Client created and task updated successfully.';
            }

            DB::commit();

            return new ClientStoreResponse(
                'success',
                'general',
                $message,
                $client->toArray(),
                $request->task_id
            );

        } catch (\Exception $e) {
            DB::rollBack();
            logger('Error in storeProcess(): ' . $e->getMessage());

            return new ClientStoreResponse(
                'error',
                'general',
                'An error occurred while creating the client. Please try again.'
            );
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'dial_code' => 'required|string|max:30',
            'phone' => 'required|string|max:15',
            'agent_id' => 'required|exists:agents,id',
            'company_id' => 'nullable|exists:companies,id', //this will be compulsory later
            'civil_no' => 'nullable|string|max:100',
            'passport_no' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
        ]);

        if (!$request->company_id) { //this fallback is temporary until company_id is added in the form
            $companyId = Agent::find($request->agent_id)->branch->company_id;

            $request->merge(['company_id' => $companyId]);
        }

        $response = $this->storeProcess($request);

        Log::info('Store process response: ', (array)$response);

        $status = $response->status;
        $type = $response->type;
        $message = $response->message;

        if ($status == 'error') {
            if ($type == 'duplicate' && auth()->user()->role_id == Role::AGENT) {
                $data = $response->data;
                return $this->showAssignmentRequestForm(
                    $data['existing_client'],
                    $data['current_agent'],
                    $data['owner_agent'],
                    $data['duplicate_type'],
                    $request
                );
            }
        }

        return redirect()->back()->with($status, $message);
    }

    public function storeApi(Request $request) : JsonResponse
    {
        Log::info('API Client store request: ', $request->all());

        $response = $this->storeProcess($request);

        $status = $response->status;
        $type = $response->type;
        $message = $response->message;
        $data = $response->data;
        $task_id = $response->task_id;

        if($status == 'error') {
            if($type == 'duplicate') {
                $data = $response->data;

                $requestAgent = $data['current_agent'];
                $client = $data['existing_client'];
                $ownerAgent = $data['owner_agent'];

                if($requestAgent->name == Agent::AI_AGENT){
                   $client->agents()->attach($requestAgent->id);

                   Log::info("AI Agent assigned to client ID {$client->id}");

                    $this->sendAssignmentRequest(
                        $ownerAgent,
                        $requestAgent,
                        $client,
                        'Client automatically assigned to AI Agent by system.'
                    );

                   return response()->json([
                        'status' => 'success',
                        'type' => 'general',
                        'message' => 'Client assigned to AI Agent successfully.',
                        'data' => $client
                    ]);

                } else {

                    $this->sendAssignmentRequest(
                        $ownerAgent,
                        $requestAgent,
                        $client,
                        'Requesting assignment to existing client by ' . $requestAgent->name
                    );
                }

                return response()->json([
                    'status' => 'error',
                    'type' => 'duplicate',
                    'message' => $response->message,
                    'data' => $data
                ]);
            }
        }

        return response()->json([
            'status' => $status,
            'type' => $type,
            'message' => $message,
            'data' => $data,
            'task_id' => $task_id
        ]);
    }

    /**
     * Check if a string is valid JSON.
     *
     * @param string $string
     * @return bool
     */
    private function isValidJson($string)
    {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    /**
     * Handle duplicate client detection and assignment request workflow
     */
    private function handleDuplicateClient($existingClient, $requestAgentId, $duplicateType) : array
    {
        $currentAgent = Agent::find($requestAgentId);
        $ownerAgent = $existingClient->agent;
        $assignedAgents = $existingClient->agents;

        // If the same agent is trying to create the client, or the client is already assigned to them, show error/info
        if ($currentAgent->id === $ownerAgent->id || $assignedAgents->contains($currentAgent->id)) {
            $message = $duplicateType === 'civil_no'
                ? 'You already have a client with this Civil No.'
                : 'You already have a client with this name and phone number.';

            // return redirect()->back()->withInput()->with('error', $message);
            return [
                'status' => 'error',
                'message' => $message
            ];

        }

        // Check if the current agent is already assigned to this client
        if ($existingClient->agents()->where('agent_id', $currentAgent->id)->exists()) {
            // return redirect()->back()->withInput()->with(
            //     'info',
            //     "You are already assigned to this client. You can find them in your client list under the name: {$existingClient->first_name} {$existingClient->last_name}"
            // );

            return [
                'status' => 'error',
                'message' => "You are already assigned to this client. You can find them in your client list under the name: {$existingClient->first_name} {$existingClient->last_name}"
            ];
        }

        // Show assignment request form instead of allowing duplicate creation
        // return $this->showAssignmentRequestForm($existingClient, $currentAgent, $ownerAgent, $duplicateType, $request);

        return [
            'status' => 'success',
            'message' => 'Duplicate client detected',
            'data' => [
                'existing_client' => $existingClient,
                'current_agent' => $currentAgent,
                'owner_agent' => $ownerAgent,
                'duplicate_type' => $duplicateType
            ],
        ];
    }

    /**
     * Show form to request assignment to existing client
     */
    private function showAssignmentRequestForm($existingClient, $currentAgent, $ownerAgent, $duplicateType, $request)
    {
        $duplicateMessage = $duplicateType === 'civil_no'
            ? "A client with Civil No '{$existingClient->civil_no}' already exists"
            : "A client with name '{$existingClient->first_name}' and phone '{$existingClient->phone}' already exists";

        return redirect()->back()->withInput()->with([
            'duplicate_warning' => true,
            'duplicate_data' => [
                'existing_client' => $existingClient,
                'owner_agent' => $ownerAgent,
                'duplicate_message' => $duplicateMessage,
                'duplicate_type' => $duplicateType
            ]
        ]);
    }

    public function show($id)
    {
        $client = Client::with(['agent.branch.company'])->findOrFail($id);
        Gate::authorize('view', $client);
        $user = Auth::user();
        $agentsQuery = Agent::query()->with('branch');
        $payment = Payment::where('client_id', $id)->first();

        if ($user->role_id == Role::ADMIN) {
            $payments = Payment::where('client_id', $id)
                ->orderBy('created_at', 'desc')
                ->get();

            $balanceCredit = Credit::getTotalCreditsByClient($id);
            $clients = Client::all();
        } elseif ($user->role_id == Role::COMPANY) {
            $companyId = $user->company?->id;
            $branchIds = Branch::where('company_id', $companyId)->pluck('id');
            $agentIds = $agentsQuery->whereIn('branch_id', $branchIds)->pluck('id');

            $payments = Payment::where('client_id', $id)
                ->orderBy('created_at', 'desc')
                ->get();
            $balanceCredit = Credit::getTotalCreditsByClient($id);

            $clients = Client::where(function ($query) use ($agentIds) {
                $query->whereIn('agent_id', $agentIds)
                    ->orWhereHas('agents', function ($q) use ($agentIds) {
                        $q->whereIn('agent_id', $agentIds);
                    });
            })->get();

        } elseif ($user->role_id == Role::BRANCH) {
            $companyId = $user->branch->company->id;
            $agentsQuery->where('branch_id', function ($query) use ($companyId) {
                $branchIds = Branch::where('company_id', $companyId)->pluck('id');
                $query->whereIn('id', $branchIds);
            });

            $payments = Payment::where('client_id', $id)
                ->orderBy('created_at', 'desc')
                ->get();
            $balanceCredit = Credit::getTotalCreditsByClient($id);

            $clients = Client::where(function ($query) use ($companyId) {
                $branchIds = Branch::where('company_id', $companyId)->pluck('id');
                $agentIds = Agent::whereIn('branch_id', $branchIds)->pluck('id');

                $query->whereIn('agent_id', $agentIds)
                    ->orWhereHas('agents', function ($q) use ($agentIds) {
                        $q->whereIn('agent_id', $agentIds);
                    });
            })->get();

        } elseif ($user->role_id == Role::AGENT) {
            $companyId = Agent::where('user_id', $user->id)->first()->branch->company_id;
            $agentsQuery->where('branch_id', $user->agent->branch_id)
                ->orWhere(function ($query) use ($companyId) {
                    $branchIds = Branch::where('company_id', $companyId)->pluck('id');
                    $query->whereIn('branch_id', $branchIds);
                });

            $clients = Client::where(function ($query) use ($user) {
                $query->where('agent_id', $user->agent->id)
                    ->orWhereHas('agents', function ($q) use ($user) {
                        $q->where('agent_id', $user->agent->id);
                    });
            })->get();
            
            if ($payment) {
                if ($payment->agent_id === $user->id) { //Assigned agent to the client
                  $payments = Payment::where('client_id', $id)
                        ->where('agent_id', $user->agent->id)
                        ->orderBy('created_at', 'desc')
                        ->get();
                    
                    $balanceCredit = Credit::whereHas('payment', function ($q) use ($user) {
                        $q->where('agent_id', $user->agent->id ?? 0);
                    })
                        ->where('client_id', $id)
                        ->sum('amount') ?? 0;
                } else { //Owner agent of the client
                    $payments = Payment::where('client_id', $id)
                        ->orderBy('created_at', 'desc')
                        ->get();

                    $balanceCredit = Credit::getTotalCreditsByClient($client->id) ?? 0;
                }
            } else {
                $payments = collect();
                $balanceCredit = 0;
            }
        }
        $agents = $agentsQuery->get();
        $agentsId = $agents->pluck('id')->toArray();
        $invoices = Invoice::with('invoiceDetails', 'agent')->where('client_id', $id)->get();

        $tasks = Task::where('client_id', $id)->get();
        foreach ($tasks as $task) {
            if (is_string($task->cancellation_policy) && $this->isValidJson($task->cancellation_policy)) {
                $stringCancelPolicy = json_decode($task->cancellation_policy, true);
                $arrayCancelPolicy = json_decode($stringCancelPolicy, true);
                $task->cancellation_policy = is_array($arrayCancelPolicy) ? $arrayCancelPolicy : [];
            }
        }

        $paid   = 0.0;
        $unpaid = 0.0;
        $invoicesPart = Invoice::where('client_id', $id)->get(['id', 'amount', 'status']);

        foreach ($invoicesPart as $invoice) {
            $total = $invoice->amount;
            $paidPartials = $invoice->invoicePartials->where('status', 'paid')->sum('amount');

            if ($invoice->status === 'paid') {
                $paid   += $total;
                continue;
            }

            $paidOnInvoice   = min($paidPartials, $total);
            $outstanding     = max(0.0, $total - $paidOnInvoice);

            $paid   += $paidOnInvoice;
            $unpaid += $outstanding;
        }


        $countries = Country::all(); // Fetch all countries for the view
        $selectedDialingCode = $countries->where('dialing_code', $client->country_code)->pluck('id')->first();

        $paymentGateways = Charge::where('type', ChargeType::PAYMENT_GATEWAY)
            ->where('is_active', true)
            ->get();
        $paymentMethods  = PaymentMethod::where('is_active', true)->get();

        return view('clients.new-profile', compact(
            'client',
            'agents',
            'invoices',
            'payments',
            'paymentGateways',
            'paymentMethods',
            'tasks',
            'paid',
            'unpaid',
            'clients',
            'balanceCredit',
            'countries',
            'selectedDialingCode',
        ));
    }

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

    public function update(Request $request, $id)
    {
        Gate::authorize('update', [Client::class, $client = Client::findOrFail($id)]);

        // Validate the incoming request data
        $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'civil_no' => 'nullable|string|max:100|unique:clients,civil_no,' . $id,
            'email' => 'email',
            'status' => 'nullable',
            'phone' => 'string|max:15',
            'country_code' => 'string|max:30',
            'file' => 'nullable|mimes:jpeg,jpg,png', // Optional passport file field
            'agent_id' => 'nullable|exists:agents,id', // this is the agent that create the client (owner)
            'agent_ids' => 'nullable|array|exists:agents,id',
        ]);

        try {
            // Update the client data
            $client->update($request->only([
                'first_name',
                'middle_name',
                'last_name',
                'civil_no',
                'email',
                'status',
                'country_code',
                'phone',
                'address',
            ]));

            if ($request->filled('agent_id')) {
                $response = Gate::inspect('assignOwnerAgent', Client::class);

                if ($response->denied()) {
                    return redirect()->back()->withInput()->with('error', $response->message() ?: 'You do not have permission to change the ownership');
                }

                $client->agent_id = $request->agent_id;
                $client->save();
            }

            if ($request->has('agent_ids')) {
                $response = Gate::inspect('assignAgents', $client);

                if ($response->denied()) {
                    return redirect()->back()->withInput()->with('error', $response->message() ?: 'You do not have permission to assign agents.');
                }

                $client->agents()->sync($request->agent_ids);
            }

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
                $client->full_name,
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

        $childClients = ClientGroup::where('parent_client_id', $parentClientId)
            ->with('childClient') // Load related child clients
            ->get()
            ->map(function ($group) {
                return [
                    'client' => $group->childClient, // Extract child client details
                    'relation' => $group->relation, // Include relation column
                ];
            });
        return response()->json($childClients);
    }


    public function getParClients(int $childClientId)
    {
        $parentClients = ClientGroup::where('child_client_id', $childClientId) // Fetch parents using child ID
            ->with('parentClient') // Load related parent clients
            ->get()
            ->map(function ($group) {
                return [
                    'client' => $group->parentClient, // Extract parent client details
                    'relation' => $group->relation, // Include relation column
                ];
            });

        return response()->json($parentClients);
    }

    public function getDetails($id)
    {
        // Retrieve client details along with related client groups
        $client = Client::find($id);

        // Check if client exists
        if (!$client) {
            return response()->json(['error' => 'Client not found'], 404);
        }

        return response()->json($client);
    }


    public function updateGroup(Request $request, int $id)
    {
        // Validate request
        $request->validate([
            'relation' => 'required|string|max:255',
            'selectedId' => 'required|exists:clients,id',
        ]);

        // Ensure that relation is a valid string
        $relation = (string)$request->relation;

        // Log query parameters for debugging
        Log::info('Query parameters:', [
            'parent_client_id' => $id,
            'child_client_id' => $request->selectedId,
        ]);

        // Find the client group based on parent_client_id and child_client_id
        $clientGroup = ClientGroup::where('parent_client_id', $id)
            ->where('child_client_id', $request->selectedId)
            ->first();

        // Log if the client group is found or not
        Log::info('clientGroup found:', ['clientGroup' => $clientGroup]);

        // If no client group is found, return an error response
        if (!$clientGroup) {
            return response()->json([
                'success' => false,
                'message' => 'Client relationship not found!',
            ], 404);
        }

        // Check current value of relation before updating
        Log::info('Current relation value:', ['relation' => $clientGroup->relation]);

        // If relation is null, set a default value
        if ($clientGroup->relation === null) {
            Log::warning('Relation is null, setting a default value.');
            $clientGroup->relation = 'parents'; // Or whatever default you prefer
        }

        // Update the specific client group's relation field
        $clientGroup->relation = $relation;

        // Log the updated relation value
        Log::info('Updated relation value:', ['relation' => $clientGroup->relation]);

        // Save the updated record explicitly
        try {
            $clientGroup->save();
        } catch (\Exception $e) {
            Log::error('Error saving client group:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update client relationship!',
            ], 500);
        }

        // Return response with updated client group data
        return response()->json([
            'success' => true,
            'message' => 'Client relationship updated successfully!',
            'clientGroup' => $clientGroup,
        ]);
    }

    public function addCredit(Payment $payment)
    {
        $client = Client::findOrFail($payment->client_id);
        $agent = Agent::find($payment->agent_id);

        if (!$client) {
            return [
                'status' => 'error',
                'message' => 'Client not found',
            ];
        }


        DB::beginTransaction();
        try {
            // Insert credit table
            $topupCreditClientData = [
                'company_id'  => $agent->branch->company->id,
                'client_id'   => $client->id,
                'type'        => 'Topup',
                'payment_id'  => $payment->id,
                'description' => 'Topup Credit via ' . $payment->voucher_number,
                'amount'      => $payment->amount,
            ];

            Log::info('Creating Credit record:', $topupCreditClientData);

            Credit::create($topupCreditClientData);

            Log::info('Credit record created successfully for client ID: ' . $client->id);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create Credit record', [
                'data'  => $topupCreditClientData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        DB::commit();

        DB::beginTransaction();
        try {

            $chargeRecord = Charge::where('name', 'LIKE', '%' . $payment->payment_gateway . '%')
                ->where('company_id', $agent->branch->company->id)
                ->select('amount', 'acc_bank_id', 'acc_fee_bank_id', 'acc_fee_id', 'paid_by')
                ->first();
            $paymentMethod = $payment->paymentMethod;
            $paidBy = $paymentMethod?->paid_by ?? $chargeRecord?->paid_by ?? 'Company';

            if ($chargeRecord) {
                $coaBankIdRec = $chargeRecord->acc_bank_id; //COA (Assets) for Debited Bank Account
                $coaFeeIdRec = $chargeRecord->acc_fee_id; //COA (Expenses) for Payment Gateway Fee
                $coaBankFeeIdRec = $chargeRecord->acc_fee_bank_id; //COA (Assets) for Bank Account for the selected Payment Gateway

                $bankCOAFee = Account::where('id', $coaFeeIdRec)
                    ->where('company_id', $agent->branch->company->id)
                    ->first();

                $bankPaymentFee = Account::where('id', $coaBankFeeIdRec)
                    ->where('company_id', $agent->branch->company->id)
                    ->first();
            }

            if (strtolower($payment->payment_gateway) === 'myfatoorah') {
                try {
                    $gatewayFee = ChargeService::FatoorahCharge($payment->amount, $paymentMethod->id, $payment->agent->branch->company_id)['gatewayFee'] ?? 0;
                } catch (Exception $e) {
                    Log::error('FatoorahCharge exception', [
                        'message' => $e->getMessage(),
                        'paymentMethod' => $paymentMethod->id,
                        'company_id' => $payment->agent->branch->company_id,
                    ]);
                    $gatewayFee = 0;
                }
            } elseif (strtolower($payment->payment_gateway) === 'tap') {
                try {
                    $gatewayFee = ChargeService::TapCharge([
                        'amount' => $payment->amount,
                        'client_id' => $payment->client_id,
                        'agent_id' => $payment->agent_id,
                        'currency' => $payment->currency
                    ], $payment->payment_gateway)['gatewayFee'] ?? 0;
                } catch (Exception $e) {
                    Log::error('TapCharge exception', [
                        'message' => $e->getMessage(),
                        'amount' => $payment->amount,
                        'client_id' => $payment->client_id,
                        'agent_id' => $payment->agent_id,
                    ]);
                    $gatewayFee = 0;
                }
            } elseif (strtolower($payment->payment_gateway) === 'hesabe') {
                try {
                    $gatewayFee = ChargeService::HesabeCharge($payment->amount, $paymentMethod->id, $payment->agent->branch->company_id)['gatewayFee'] ?? 0;
                } catch (Exception $e) {
                    Log::error('HesabeCharge exception', [
                        'message' => $e->getMessage(),
                        'amount' => $payment->amount,
                        'payment_method' => $paymentMethod->id,
                        'company_id' => $payment->agent->branch->company_id,
                    ]);
                    $gatewayFee = 0;
                }
            } else if (strtolower($payment->payment_gateway) === 'upayment') {
                try {
                    $gatewayFee = ChargeService::UPaymentCharge($payment->amount, $paymentMethod->id, $payment->agent->branch->company_id)['fee'] ?? 0;
                } catch (Exception $e) {
                    Log::error('PaypalCharge exception', [
                        'message' => $e->getMessage(),
                        'amount' => $payment->amount,
                        'payment_method' => $paymentMethod->id,
                        'company_id' => $payment->agent->branch->company_id,
                    ]);
                    $gatewayFee = 0;
                }
            } else {
                $gatewayFee = $chargeRecord?->amount ?? 0;
            }

            $transaction = Transaction::create([
                'branch_id' =>  $agent->branch->id,
                'company_id' =>  $agent->branch->company->id,
                'entity_id' =>  $agent->branch->company->id,
                'entity_type' => 'client',
                'transaction_type' => 'debit',
                'amount' => $payment->amount,
                'description' => 'Client Credit of ' . $client->full_name,
                'invoice_id' => null,
                'reference_type' => 'Payment',
                'reference_number' => $payment->voucher_number,
                'transaction_date' => now(),
            ]);

            $receivableAccount = Account::where('name', 'Clients')->first();
            $receivableAccountId = $receivableAccount->id;

            if ($bankPaymentFee) {
                // Create record to payment_gateway assets coa account (OK)
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'company_id' => $agent->branch->company->id,
                    'branch_id' => $agent->branch->id,
                    'account_id' =>  $bankPaymentFee->id,
                    'transaction_date' => Carbon::now(),
                    'description' => 'Client Pays by ' . $client->full_name . ' via (Assets): ' . $bankPaymentFee->name,
                    'debit' => $payment->amount,
                    'credit' => 0,
                    'name' =>  $bankPaymentFee->name,
                    'type' => 'bank',
                    'voucher_number' => $payment->voucher_number,
                    'type_reference_id' => $bankPaymentFee->id
                ]);

                $bankPaymentFee->actual_balance += ($payment->amount - $gatewayFee);
                $bankPaymentFee->save();
            }

            $bankCOAFee->actual_balance += $gatewayFee;

            if ($bankCOAFee) {
                JournalEntry::create([
                    'transaction_id'    => $transaction->id,
                    'company_id'        => $agent->branch->company->id,
                    'branch_id'         => $agent->branch->id,
                    'account_id'        => $bankCOAFee->id,
                    'voucher_number'    => $payment->voucher_number,
                    'transaction_date'  => Carbon::now(),
                    'description'       => ($paidBy === 'Company' ? 'Company Pays Gateway Fee: ' : 'Client Pays Gateway Fee: ') . $bankCOAFee->name,
                    'debit'             => $gatewayFee,
                    'credit'            => 0,
                    'balance'           => $bankCOAFee->actual_balance + $gatewayFee,
                    'name'              => $bankCOAFee->name,
                    'type'              => 'charges',
                    'type_reference_id' => $bankCOAFee->id
                ]);

                $bankCOAFee->actual_balance += $gatewayFee;
                $bankCOAFee->save();
            }
        } catch (Exception $e) {
            DB::rollBack();
            logger('Error adding JournalEntry: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to add JournalEntry',
            ];
        }

        DB::commit();
        return [
            'status' => 'success',
            'message' => 'Credit added successfully',
            'data' => [
                'client_id' => $client->id,
                'credit' => $payment->amount,
            ],
        ];
    }

    public function updateCredit($id, $amount)
    {
        try {
            $client = Client::findOrFail($id);
            // $client->credit = $amount;
            // $client->save();

            $balanceCredit = Credit::getTotalCreditsByClient($client->id);
            $difference = $amount - $balanceCredit;

            Log::info('Credit Update', [
                'client_id'       => $client->id,
                'client_name'     => $client->full_name,
                'current_balance' => $balanceCredit,
                'new_amount'      => $amount,
                'difference'      => $difference,
                'change_type'     => $difference > 0 ? 'Increase' : ($difference < 0 ? 'Decrease' : 'No change'),
            ]);

            if ($difference != 0) {
                Credit::create([
                    'company_id'  => $client->agent->branch->company->id,
                    'client_id'   => $client->id,
                    'type'        => 'Update Credit',
                    'description' => 'Update Credit for ' . $client->full_name,
                    'amount'      => $difference,
                ]);
            }
        } catch (Exception $e) {
            logger('Error updating credit: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to update credit',
            ];
        }

        logger('Credit updated successfully for client ID: ' . $id);
        return [
            'status' => 'success',
            'message' => 'Credit updated successfully',
            'data' => [
                'client_id' => $client->id,
                'credit' => $difference,
            ],
        ];
    }

    public function refund($id, Request $request)
    {
        $response = $this->refundProcess($id, $request);

        return redirect()->back()->with($response['status'], $response['message']);
    }

    public function refundProcess($id, Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'agent_id' => 'required|exists:agents,id',
        ]);

        if ($request->amount <= 0) {
            return [
                'status' => 'error',
                'message' => 'Refund amount must be greater than zero',
            ];
        }

        $client = Client::findOrFail($id);
        $balanceCredit = Credit::getTotalCreditsByClient($client->id);
        if ($balanceCredit < $request->amount) {
            return [
                'status' => 'error',
                'message' => 'Insufficient credit',
            ];
        }

        $agent = Agent::find($request->agent_id);

        DB::beginTransaction();

        try {

            $liabilities = Account::where('name', 'Liabilities')
                ->where('company_id', $agent->branch->company->id)
                ->first();

            if (!$liabilities) {
                throw new Exception('Liabilities account not found');
            }

            $advances = Account::where('name', 'Advances')
                ->where('company_id', $agent->branch->company->id)
                ->where('parent_id', $liabilities->id)
                ->first();

            if (!$advances) {
                throw new Exception('Advances account not found');
            }

            $clientAdvance = Account::where('name', 'Client')
                ->where('company_id', $agent->branch->company->id)
                ->where('parent_id', $advances->id)
                ->where('root_id', $liabilities->id)
                ->first();

            if (!$clientAdvance) {
                throw new Exception('Client Advance account not found');
            }

            $refundPayable = Account::where('name', 'Refund Payable')
                ->where('company_id', $agent->branch->company->id)
                ->where('root_id', $liabilities->id)
                ->first();

            if (!$refundPayable) {
                throw new Exception('Refund Payable account not found');
            }

            $clientRefund = Account::where('name', 'Clients')
                ->where('company_id', $agent->branch->company->id)
                ->where('parent_id', $refundPayable->id)
                ->where('root_id', $liabilities->id)
                ->first();

            if (!$clientRefund) {
                throw new Exception('Client Refund account not found');
            }

            $transaction = Transaction::create([
                'branch_id' =>  $agent->branch->id,
                'company_id' =>  $agent->branch->company->id,
                'entity_id' =>  $agent->branch->company->id,
                'entity_type' => 'client',
                'transaction_type' => 'credit',
                'amount' => $request->amount,
                'description' => 'Client Refund of ' . $client->full_name . ' of ' . $request->amount,
                'invoice_id' => null,
                'reference_type' => 'Refund',
                'reference_number' => null,
                'transaction_date' => now(),
            ]);

            JournalEntry::create([
                'transaction_id' => $transaction->id,
                'branch_id' => $agent->branch->id,
                'company_id' => $agent->branch->company->id,
                'account_id' =>  $clientAdvance->id,
                'transaction_date' => Carbon::now(),
                'description' => 'Deduct Client Advance: ' . $client->full_name . ' of ' . $request->amount,
                'debit' => $request->amount,
                'credit' => 0,
                'balance' => null,
                'name' =>  $client->full_name,
                'type' => 'receivable',
                'voucher_number' => null,
                'type_reference_id' => $advances->id
            ]);

            JournalEntry::create([
                'transaction_id' => $transaction->id,
                'branch_id' => $agent->branch->id,
                'company_id' => $agent->branch->company->id,
                'account_id' =>  $clientRefund->id,
                'transaction_date' => Carbon::now(),
                'description' => 'Debit Client Refund Payable: ' . $client->full_name . ' of ' . $request->amount,
                'debit' => 0,
                'credit' => $request->amount,
                'balance' => null,
                'name' =>  $client->full_name,
                'type' => 'payable',
                'voucher_number' => null,
                'type_reference_id' => $refundPayable->id
            ]);

            // $client->credit -= $request->amount;
            // $client->save();



            RefundClient::create([
                'client_id' => $client->id,
                'agent_id' => $request->agent_id,
                'status' => 'pending',
                'amount' => $request->amount,
                'currency' => 'KWD',
                'remark' => $request->remark,
            ]);

            try {
                Credit::create([
                    'company_id'  => $agent->branch->company->id,
                    'client_id'   => $client->id,
                    'type'        => 'Refund Credit',
                    'description' => 'Refund Credit for ' . $client->full_name,
                    'amount'      => - ($request->amount),
                ]);
            } catch (Exception $e) {
                Log::error('Failed to create Credit: ' . $e->getMessage());
                return response()->json('Something Went Wrong', 500);
            }
        } catch (Exception $e) {
            DB::rollBack();
            logger('Error processing refund: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to process refund',
            ];
        }

        DB::commit();

        return [
            'status' => 'success',
            'message' => 'Credit refunded successfully',
            'data' => [
                'client_id' => $client->id,
                'credit' => $request->amount,
            ],
        ];
    }

    public function getAgent($id)
    {
        $client = Client::with('agent')->find($id);

        if (!$client) {
            return response()->json(['error' => 'Client not found'], 404);
        }

        if (!$client->agent) {
            return response()->json(['error' => 'No agent assigned to this client'], 404);
        }

        return response()->json([
            'agent' => [
                'id' => $client->agent->id,
                'name' => $client->agent->name,
            ],
        ]);
    }

    // get Credit balance of a client
    public function getCreditBalance($id)
    {
        $credit = Credit::getTotalCreditsByClient($id);
        return response()->json(['credit' => $credit]);
    }

    public function showCredit($id)
    {
        $client = Client::with('agent')->findOrFail($id);
        $base = Credit::with(['client', 'invoice'])->where('client_id', $client->id);

        $totals = (clone $base)
            ->selectRaw('SUM(GREATEST(amount,0)) total_in, SUM(LEAST(amount,0)) total_out')
            ->first();

        $totalIn  = $totals->total_in  ?? 0;
        $totalOut = $totals->total_out ?? 0;
        $netBalance = $totalIn + $totalOut;

        $credits = (clone $base)
            ->orderBy('id', 'asc')
            ->paginate(25)
            ->withQueryString();

        return view('clients.credit', compact('client', 'credits', 'totalIn', 'totalOut', 'netBalance'));
    }

    public function assignAgents(Request $request, $id): JsonResponse
    {
        $client = Client::findOrFail($id);

        $response = Gate::inspect('assignAgents', $client);

        if ($response->denied()) {
            return response()->json([
                'status' => 'error',
                'message' => $response->message() ?: 'You do not have permission to assign agents.'
            ], 403);
        }

        $request->validate([
            'agent_ids' => 'required|array|exists:agents,id',
        ]);

        $client->agents()->sync($request->agent_ids);

        return response()->json([
            'status' => 'success',
            'message' => 'Agents assigned successfully.'
        ]);
    }

    /**
     * Handle assignment request to existing client
     */
    public function requestAssignment(Request $request)
    {
        $request->validate([
            'existing_client_id' => 'required|exists:clients,id',
            'owner_agent_id' => 'required|exists:agents,id',
            'request_reason' => 'required|string|min:5|max:500'
        ]);

        $existingClient = Client::with('agent')->findOrFail($request->existing_client_id);
        $ownerAgent = Agent::findOrFail($request->owner_agent_id);
        $requestingAgent = Agent::where('user_id', Auth::id())->first();

        if (!$requestingAgent) {
            return redirect()->back()->with('error', 'Agent profile not found.');
        }

        // Check if already assigned
        if ($existingClient->agents()->where('agent_id', $requestingAgent->id)->exists()) {
            return redirect()->back()->with(
                'info',
                "You are already assigned to this client: {$existingClient->first_name} {$existingClient->last_name}"
            );
        }

        // Log the assignment request
        Log::info('Client assignment request submitted', [
            'existing_client_id' => $existingClient->id,
            'existing_client_name' => $existingClient->full_name,
            'owner_agent_id' => $ownerAgent->id,
            'owner_agent_name' => $ownerAgent->name,
            'requesting_agent_id' => $requestingAgent->id,
            'requesting_agent_name' => $requestingAgent->name,
            'request_reason' => $request->request_reason,
            'timestamp' => now()
        ]);

        // Send notification to owner agent
        $this->sendAssignmentRequest($ownerAgent, $requestingAgent, $existingClient, $request->request_reason);

        return redirect()->back()->with(
            'success',
            "Assignment request sent to {$ownerAgent->name}. You will be notified once they review your request."
        );
    }

    /**
     * Send assignment request notification to owner agent
     */
    private function sendAssignmentRequest($ownerAgent, $requestingAgent, $existingClient, $reason)
    {
        // Generate unique request token for secure actions
        $requestToken = ClientAssignmentRequest::generateToken();

        // Log the notification
        Log::info('Assignment request notification sent', [
            'owner_agent_id' => $ownerAgent->id,
            'owner_agent_name' => $ownerAgent->name,
            'requesting_agent_id' => $requestingAgent->id,
            'requesting_agent_name' => $requestingAgent->name,
            'client_id' => $existingClient->id,
            'client_name' => $existingClient->first_name . ' ' . $existingClient->last_name,
            'reason' => $reason,
            'request_token' => $requestToken,
            'timestamp' => now()
        ]);

        // Create actionable notification data
        $data = [
            'user_id' => $ownerAgent->user_id,
            'title' => "Client Assignment Request",
            'message' => "Agent {$requestingAgent->name} requests assignment to your client \"{$existingClient->first_name} {$existingClient->last_name}\". Reason: {$reason}",
            'type' => 'client_assignment_request',
            'data' => json_encode([
                'request_token' => $requestToken,
                'requesting_agent_id' => $requestingAgent->id,
                'requesting_agent_name' => $requestingAgent->name,
                'client_id' => $existingClient->id,
                'client_name' => $existingClient->full_name,
                'client_phone' => $existingClient->phone,
                'reason' => $reason,
                'status' => 'pending',
                'expires_at' => now()->addDays(7)->toISOString(),
                'actions' => [
                    'approve_url' => route('clients.assignment.approve', ['token' => $requestToken]),
                    'deny_url' => route('clients.assignment.deny', ['token' => $requestToken]),
                    'view_client_url' => route('clients.show', $existingClient->id)
                ]
            ])
        ];

        $this->storeNotification($data);

        // Store in client_assignment_requests table using Eloquent model
        ClientAssignmentRequest::create([
            'request_token' => $requestToken,
            'owner_agent_id' => $ownerAgent->id,
            'requesting_agent_id' => $requestingAgent->id,
            'client_id' => $existingClient->id,
            'reason' => $reason,
            'status' => ClientAssignmentRequest::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Approve assignment request
     */
    public function approveAssignment($token)
    {
        $request = ClientAssignmentRequest::byToken($token)->active()->first();

        if (!$request) {
            return redirect()->route('dashboard')->with('error', 'Assignment request not found or has expired.');
        }

        // Check if the current user is authorized to approve
        $ownerAgent = $request->ownerAgent;
        if (Auth::id() !== $ownerAgent->user_id) {
            return redirect()->route('dashboard')->with('error', 'You are not authorized to approve this request.');
        }

        try {
            DB::beginTransaction();

            // Add the requesting agent to the client
            $client = $request->client;
            $client->agents()->attach($request->requesting_agent_id);

            // Update request status using model method
            $request->approve(Auth::id());

            // Mark the original notification as read - Using Eloquent model method
            $notification = Notification::findByUserAndToken(Auth::id(), 'client_assignment_request', $token);

            if ($notification) {
                $notification->update(['status' => 'read']);
            }

            // Send notification to requesting agent
            $requestingAgent = $request->requestingAgent;
            $notificationData = [
                'user_id' => $requestingAgent->user_id,
                'title' => "Assignment Request Approved",
                'message' => "Your request to be assigned to client \"{$client->first_name} {$client->last_name}\" has been approved by {$ownerAgent->name}.",
                'type' => 'assignment_approved',
                'data' => json_encode([
                    'client_id' => $client->id,
                    'client_name' => $client->full_name,
                    'approved_by' => $ownerAgent->name,
                    'approved_at' => now()->toISOString(),
                    'view_client_url' => route('clients.show', $client->id)
                ])
            ];
            $this->storeNotification($notificationData);

            DB::commit();

            Log::info('Assignment request approved', [
                'token' => $token,
                'client_id' => $client->id,
                'owner_agent_id' => $ownerAgent->id,
                'requesting_agent_id' => $requestingAgent->id,
                'approved_by' => Auth::id()
            ]);

            return redirect()->route('clients.show', $client->id)
                ->with('success', "Successfully assigned {$requestingAgent->name} to client {$client->first_name} {$client->last_name}.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve assignment request', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);
            return redirect()->route('dashboard')->with('error', 'Failed to approve assignment request.');
        }
    }

    /**
     * Deny assignment request
     */
    public function denyAssignment($token)
    {
        $request = ClientAssignmentRequest::byToken($token)->active()->first();

        if (!$request) {
            return redirect()->route('dashboard')->with('error', 'Assignment request not found or has expired.');
        }

        // Check if the current user is authorized to deny
        $ownerAgent = $request->ownerAgent;
        if (Auth::id() !== $ownerAgent->user_id) {
            return redirect()->route('dashboard')->with('error', 'You are not authorized to deny this request.');
        }

        try {
            DB::beginTransaction();

            // Update request status using model method
            $request->deny(Auth::id());

            // Mark the original notification as read - Using Eloquent model method
            $notification = Notification::findByUserAndToken(Auth::id(), 'client_assignment_request', $token);
            if ($notification) {
                $notification->update(['status' => 'read']);
            }

            // Send notification to requesting agent
            $requestingAgent = $request->requestingAgent;
            $client = $request->client;
            $notificationData = [
                'user_id' => $requestingAgent->user_id,
                'title' => "Assignment Request Denied",
                'message' => "Your request to be assigned to client \"{$client->first_name} {$client->last_name}\" has been denied by {$ownerAgent->name}.",
                'type' => 'assignment_denied',
                'data' => json_encode([
                    'client_id' => $client->id,
                    'client_name' => $client->full_name,
                    'denied_by' => $ownerAgent->name,
                    'denied_at' => now()->toISOString(),
                    'reason' => $request->reason
                ])
            ];
            $this->storeNotification($notificationData);

            DB::commit();

            Log::info('Assignment request denied', [
                'token' => $token,
                'client_id' => $client->id,
                'owner_agent_id' => $ownerAgent->id,
                'requesting_agent_id' => $requestingAgent->id,
                'denied_by' => Auth::id()
            ]);

            return redirect()->route('dashboard')
                ->with('success', "Assignment request denied for {$requestingAgent->name}.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to deny assignment request', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);
            return redirect()->route('dashboard')->with('error', 'Failed to deny assignment request.');
        }
    }

    public function receiptVoucherCredit($data)
    {
        //dd($data);
        $user = Auth::user();

        $client = Client::findOrFail($data['items'][0]['client_id']);
        if (!$client) {
            return [
                'status' => 'error',
                'message' => 'Client not found',
            ];
        }

        $companyId = $data['company_id'];
        $branchId = $data['branch_id'];
        $amount = $data['items'][0]['amount'];

        try {
            DB::beginTransaction();

            $topupCreditClientData = Credit::create ([
                'company_id'  => $companyId,
                'branch_id'   => $branchId,
                'client_id'   => $client->id,
                'type'        => 'Topup',
                'description' => 'Topup Credit via ' . $data['receiptvoucherref'] . '. Additional Remarks of ' . $data['remarks_create'],
                'amount'      => $amount,
                'topup_by'    => $user->id,
            ]);
            
            Log::info('Credit record created successfully for client ID: ' . $client->id);
       
            $transaction = Transaction::create([
                'branch_id'        => $branchId,
                'company_id'       => $companyId,
                'name'             => $client->full_name,
                'entity_id'        => $companyId,
                'entity_type'      => 'client',
                'transaction_type' => 'debit',
                'amount'           => $amount,
                'description'      => 'Credit for Client ' . $client->full_name . '. Additional Remarks of ' . $data['remarks_create'],
                'reference_type'   => 'Receipt',
                'reference_number' => $data['receiptvoucherref'],
                'transaction_date' => now(),
            ]);

            if (!$transaction) {
                Log::error('Transaction failed to create');
                return [
                    'status' => 'error',
                    'message' => 'Failed to create transaction',
                ];
            }

            $assets = Account::where('name', 'like', '%Assets%')
                ->where('company_id', $companyId)
                ->value('id');

            if (!$assets) {
                Log::error('Assets root account not found');
                return [
                    'status' => 'error',
                    'message' => 'Assets root account not found',
                ];
            }

            $liabilities = Account::where('name', 'like', '%Liabilities%')
                ->where('company_id', $companyId)
                ->value('id');

            if (!$liabilities) {
                Log::error('Liabilities root account not found');
                return [
                    'status' => 'error',
                    'message' => 'Liabilities root account not found',
                ];
            }

            $receiptVoucherCash = Account::where('name', 'Receipt Voucher Cash')
                ->where('company_id', $companyId)
                ->where('root_id', $assets)
                ->first();

            if (!$receiptVoucherCash) {
                Log::error('Cash in Hand (Receipt Voucher Cash) account not found');
                return [
                    'status' => 'error',
                    'message' => 'Failed to add journal entry to Cash in Hand (Receipt Voucher Cash) account',
                ];

            }

            JournalEntry::create([
                'transaction_id'   => $transaction->id,
                'company_id'       => $companyId,
                'branch_id'        => $branchId,
                'account_id'       => $receiptVoucherCash->id,
                'transaction_date' => Carbon::now(),
                'description'      => 'Client ' . $client->full_name . ' Pays Cash via (Assets): ' . $receiptVoucherCash->name,
                'debit'            => $amount,
                'credit'           => 0,
                'name'             => $receiptVoucherCash->name,
                'type'             => 'receivable',
                'voucher_number'   => $data['receiptvoucherref'],
                'type_reference_id'=> $receiptVoucherCash->id,
            ]);

            $receiptVoucherCash->actual_balance = ($receiptVoucherCash->actual_balance ?? 0) + $amount;
            $receiptVoucherCash->save();

            $advancesParent = Account::where('name', 'Advances')
                ->where('company_id', $companyId)
                ->where('root_id', $liabilities)
                ->first();

            $clientAdvance = Account::where('name', 'Client')
                ->where('company_id', $companyId)
                ->where('parent_id', $advancesParent->id)
                ->first();

            $cash= Account::where('name', 'Cash')
                ->where('company_id', $companyId)
                ->where('parent_id', $clientAdvance->id)
                ->first();
                
            if (!$cash) {
                Log::error('Advances (Client -> Cash) account not found');
                return [
                    'status' => 'error',
                    'message' => 'Failed to add journal entry to Advances (Client -> Cash) account',
                ];
            }

            JournalEntry::create([
                'transaction_id'   => $transaction->id,
                'company_id'       => $companyId,
                'branch_id'        => $branchId,
                'account_id'       => $cash->id,
                'voucher_number'   => $data['receiptvoucherref'],
                'transaction_date' => Carbon::now(),
                'description'      => 'Client Pays Credit via (Advances): ' . $cash->name,
                'debit'            => 0, // liability increase → credit
                'credit'           => $amount,
                'balance'          => ($cash->actual_balance ?? 0) + $amount,
                'name'             => $cash->name,
                'type'             => 'credit',
                'type_reference_id'=> $cash->id,
            ]);

            $cash->actual_balance = ($cash->actual_balance ?? 0) + $amount;
            $cash->save();
            
            $invoiceReceipt = InvoiceReceipt::create([
                'type' => 'credit',
                'credit_id' => $topupCreditClientData->id,
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'status' => 'approved',
            ]);

            if (!$invoiceReceipt) {
                    Log::error('Failed to create Invoice Receipt record', [
                    'transaction_id' => $transaction->id
                ]);
            }
            
            DB::commit();

        } catch (Exception $e) {
            DB::rollBack();
            logger('Error adding JournalEntry: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to add JournalEntry',
            ];
        }

        
        return [
            'status' => 'success',
            'message' => 'Credit added successfully',
            'data' => [
                'client_id' => $client->id,
                'credit' => $amount,
            ],
        ];
    }
}
