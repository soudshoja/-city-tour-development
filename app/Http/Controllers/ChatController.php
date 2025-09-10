<?php

namespace App\Http\Controllers;

use App\AI\AIManager;
use App\Http\Traits\NotificationTrait;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Illuminate\Http\Request;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Smalot\PdfParser\Parser;
use App\Http\Traits\Converter;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\Role;
use App\Models\User;
use App\Models\Account;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\Client;
use App\Models\JournalEntry;
use App\Models\Task;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\Transaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use LDAP\Result;

class ChatController extends Controller
{
    use NotificationTrait;
    use Converter;

    protected $aiManager;

    public function __construct(AIManager $aiManager)
    {
        $this->aiManager = $aiManager;
    }


    public function chat(Request $request)
    {

        $validated = $request->validate([
            'messages' => 'required|array',
            'messages.*.role' => 'required|string',
            'messages.*.content' => 'required|string',
        ]);

        try {
            $userMessage = collect($validated['messages'])->last()['content'];
            $userData = $this->fetchUserBasedData();

            // Check if there was an error in fetching user data
            if (isset($userData['error'])) {
                return response()->json(['error' => $userData['error']], 403);
            }

            // Prepare messages for OpenAI with the user's role-based data
            $messages = [
                [
                    'role' => 'system',
                    'content' => "You are a chatbot for a travel agency that will interact with the travel agencies or the agents. Please use the following data to answer any questions.",
                ],
                [
                    'role' => 'user',
                    'content' => $userMessage,
                ]
            ];


            // Classification Step
            $classification = $this->classifyMessage($userMessage) ?? 'GeneralMessage';

            // Process based on classification
            if ($classification === 'DataQuery') {
                return $this->handleDataRequest($userMessage, $userData);
            } elseif ($classification === 'ActionRequest') {

                return $this->handleActionRequest($userMessage, $userData);
            } else {
                // Pass the message to OpenAI if it's not recognized as data or action
                $response = $this->aiManager->chat($messages);
                Log::info('Chat response:', ['response' => $response]); 
                // Return standardized response format
                if ($response['success']) {
                    return response()->json([
                        'success' => true,
                        'data' => $response['data'],
                        'metadata' => $response['metadata'] ?? []
                    ], 200);
                } else {
                    return response()->json(['error' => $response['message']], 500);
                }
            }
        } catch (\Exception $e) {
            Log::error('Chatbot error: ' . $e->getMessage());
            return response()->json(['error' => 'Something went wrong. Please try again later.'], 500);
        }
    }


    private function classifyMessage($message)
    {
        // Example: Using OpenAI or another NLP service to classify the message
        $classificationMessages = [
            [
                'role' => 'system',
                'content' => "Classify the following user message into one of the following categories: DataQuery (question or statement that need to query for data) , ActionRequest (statement that need to create or use automation), or GeneralMessage.",
            ],
            [
                'role' => 'user',
                'content' => $message,
            ],
        ];

        $response = $this->aiManager->chat($classificationMessages);

        // Extract the classification result from standardized response
        if ($response['success']) {
            return trim($response['data']);
        }

        return 'GeneralMessage'; // Default to GeneralMessage if no classification is returned
    }

    private function handleDataRequest($userMessage, $userData)
    {
        $messagesData = [
            [
                'role' => 'system',
                'content' => "You are a chatbot for a travel agency. Classify the user's query into the following categories:
                - 'general': The user is asking a question or seeking information.
                - 'listing': The user is requesting a list of items or data.
        
                If the query is classified as 'listing', further identify the type of list being requested as one of the following:
                - 'task list': If the user is requesting tasks or assignments.
                - 'invoice list': If the user is requesting a list of invoices.
                - 'client list': If the user is requesting a list of clients.
                - 'agent list': If the user is requesting a list of agents.
                - 'branch list': If the user is requesting a list of branches.
        
                **Always respond in HTML format** for listing queries:
                - For a general question: Just return a simple text response.
                - For a listing, return an HTML table with a grey background color for both headers and content.
        
                **Example Outputs:**
                - User query: 'Show me all tasks'
                - Response:
                  `<table border='1' style='border-collapse: collapse; width: 100%;'>
                    <tr><th style='background-color: #f2f2f2; padding: 8px;'>ID</th><th style='background-color: #f2f2f2; padding: 8px;'>Task</th></tr>
                    <tr><td style='background-color: #f2f2f2; padding: 8px;'>1</td><td style='background-color: #f2f2f2; padding: 8px;'>Task 1</td></tr>
                    <tr><td style='background-color: #f2f2f2; padding: 8px;'>2</td><td style='background-color: #f2f2f2; padding: 8px;'>Task 2</td></tr>
                  </table>`
        
                - User query: 'I need the invoice list'
                - Response:
                  `<table border='1' style='border-collapse: collapse; width: 100%;'>
                    <tr><th style='background-color: #f2f2f2; padding: 8px;'>Invoice Number</th><th style='background-color: #f2f2f2; padding: 8px;'>Amount</th></tr>
                    <tr><td style='background-color: #f2f2f2; padding: 8px;'>101</td><td style='background-color: #f2f2f2; padding: 8px;'>$500.00</td></tr>
                  </table>`

                - User query: 'Profit for agent'
                - Response:
                  `<table border='1' style='border-collapse: collapse; width: 100%;'>
                    <tr><th style='background-color: #f2f2f2; padding: 8px;'>Agent</th><th style='background-color: #f2f2f2; padding: 8px;'>Profit</th></tr>
                    <tr><td style='background-color: #f2f2f2; padding: 8px;'>101</td><td style='background-color: #f2f2f2; padding: 8px;'>$500.00</td></tr>
                  </table>`

                - User query: 'Payment to supplier'
                - Response:
                  `<table border='1' style='border-collapse: collapse; width: 100%;'>
                    <tr><th style='background-color: #f2f2f2; padding: 8px;'>Supplier</th><th style='background-color: #f2f2f2; padding: 8px;'>Payment</th></tr>
                    <tr><td style='background-color: #f2f2f2; padding: 8px;'>101</td><td style='background-color: #f2f2f2; padding: 8px;'>$500.00</td></tr>
                  </table>`

                - User query: 'Pending or Unpaid invoice link'
                - Response:
                  `<table border='1' style='border-collapse: collapse; width: 100%;'>
                    <tr><th style='background-color: #f2f2f2; padding: 8px;'>Invoice Number</th><th style='background-color: #f2f2f2; padding: 8px;'>Total Amount</th><th style='background-color: #f2f2f2; padding: 8px;'>Link</th></tr>
                    <tr><td style='background-color: #f2f2f2; padding: 8px;'>101</td><td style='background-color: #f2f2f2; padding: 8px;'>$500.00</td><td style='background-color: #f2f2f2; padding: 8px;'>http://127.0.0.1:8000/invoice/INV-2025-01301</td></tr>
                  </table>`

                Format all listings as an HTML table with relevant headers and a grey background color for both headers and content.",
            ],
            [
                'role' => 'user',
                'content' => $userMessage,
            ],
            [
                'role' => 'system',
                'content' => json_encode($userData), // Providing user data for context
            ],
        ];

        $response = $this->aiManager->chat($messagesData);

        // Return standardized response format
        if ($response['success']) {
            return response()->json([
                'success' => true,
                'data' => $response['data'],
                'metadata' => $response['metadata'] ?? []
            ], 200);
        } else {
            return response()->json(['error' => $response['message']], 500);
        }
    }


    private function handleActionRequest($message, $userData)
    {
        // Define the messages to classify the action and extract task information
        $actionMessages = [
            [
                'role' => 'system',
                'content' => "Analyze the following user message and return a JSON object with two keys: 'action' and 'task_ids'. 
                The 'action' should be one of the following: 'create invoice', 'create client', 'create agent', or 'create branch'. 
                If the action is 'create invoice', include the 'task_ids' as an array of integers. 
                Example JSON: {\"action\": \"create invoice\", \"task_ids\": [123, 456]}",
            ],
            [
                'role' => 'user',
                'content' => $message,
            ],
        ];

        $response = $this->aiManager->chat($actionMessages);

        // Handle standardized response format
        if ($response['success']) {
            $responseContent = $response['data'];

            // Attempt to decode the JSON response
            $parsedResponse = json_decode($responseContent, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($parsedResponse['action'])) {
                $action = strtolower(trim($parsedResponse['action']));
                $taskIds = $parsedResponse['task_ids'] ?? [];

                if (!is_array($taskIds)) {
                    $taskIds = [];
                }

                Log::info('action:', ['action' => $action]);
                // Handle actions based on parsed response
                switch ($action) {
                    case 'create invoice':
                        // if (!empty($taskIds)) {
                        return $this->initiateInvoiceCreationWithTasks($taskIds, $userData);
                        // }
                        // return response()->json(['error' => 'At least one Task ID is required to create an invoice.'], 400);

                    case 'create client':
                        return $this->initiatecreateClient($userData);

                    case 'create agent':
                        return $this->initiatecreateAgent($userData);

                    case 'create branch':
                        return $this->initiatecreateBranch($userData);

                    default:
                        return response()->json(['error' => 'Action not recognized: ' . $action], 400);
                }
            } else {
                // Log and return error if JSON structure is invalid or no action found
                Log::error('Invalid JSON structure or missing action in response:', ['response' => $responseContent]);
                return response()->json(['error' => 'Unable to parse action from response.'], 400);
            }
        } else {
            // Handle error from AI service
            Log::error('AI action classification failed:', ['error' => $response['message']]);
            return response()->json(['error' => 'Unable to classify action: ' . $response['message']], 400);
        }
    }


    private function initiateInvoiceCreationWithTasks(array $taskIds, $userData)
    {
        // Logic to create an invoice using the provided task IDs
        Log::info('initiateInvoiceCreationWithTasks:', ['taskIds' => $taskIds]);

        if (!empty($taskIds)) {
            // If tasks are directly mentioned, validate and proceed
            return $this->processTaskSelection($taskIds);
        }

        return response()->json([
            'message' => 'Please choose the tasks to include in the invoice:',
            'tasks' => collect($userData['selectedtasks'])->map(function ($task) {
                return [
                    'id' => $task['id'],
                    'description' => $task['description'],
                    'client' => $task['clientName'],
                ];
            }),
        ], 200);
    }



    public function processTaskSelection($taskIds)
    {

        // Logic to create an invoice using the provided task IDs
        Log::info('processTaskSelection:', ['taskIds' => $taskIds]);

        $userData = $this->fetchUserBasedData();

        // Cast task IDs to integers
        $taskIds = array_map('intval', $taskIds);
        Log::info('processTaskSelectiontaskIds:', ['taskIds' => $taskIds]);
        // Filter available tasks based on provided IDs
        $availableTasks = collect($userData['tasks'])->whereIn('id', $taskIds);
        Log::info('available Tasks:', ['availableTasks' => $availableTasks]);


        if ($availableTasks->isEmpty()) {
            return response()->json(['message' => 'No valid tasks found for the provided IDs.'], 400);
        }

        return response()->json([
            'message' => 'Please insert invoice price for each selected task:',
            'taskPricing' => $availableTasks->map(function ($task) {
                return [
                    'id' => $task['id'],
                    'description' => $task['description'],
                    'client' => $task['clientName'],
                    'taskprice' => $task['price'],
                ];
            })->values(),
        ], 200);
    }


    public function sendProcessTaskSelection(Request $request)
    {
        // Get the task IDs from the request
        $taskIds = $request->input('tasks');

        // Fetch user data, including tasks
        $userData = $this->fetchUserBasedData();

        // Ensure task IDs are integers
        $taskIds = array_map('intval', $taskIds);
        // Fetch available tasks based on the provided task IDs (with eager loading of client)
        $availableTasks = Task::with('client')  // Eager load the 'client' relationship
            ->whereIn('id', $taskIds)
            ->get();  // Execute the query to retrieve the tasks

        // If no tasks were found, return an error response
        if ($availableTasks->isEmpty()) {
            return response()->json(['message' => 'No valid tasks found for the provided IDs.'], 400);
        }

        // Map the available tasks to include necessary data for invoice pricing
        return response()->json([
            'message' => 'Please insert invoice price for each selected task:',
            'taskPricing' => $availableTasks->map(function ($task) {
                return [
                    'id' => $task->id, // Access Eloquent model attribute using ->id
                    'description' => $task->reference . ' ' . $task->additional_info, // Concatenate description fields
                    'client' => $task->client ? $task->client->first_name : 'N/A', // Safely access client name
                    'taskprice' => $task->price, // The task price
                    'invprice' => $task->invprice, // The task price
                ];
            })->values(),
        ], 200);
    }



    public function handleTaskPricing(Request $request)
    {
        $validated = $request->validate([
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|integer',
            'tasks.*.invprice' => 'required|numeric|min:0',
        ], [
            'tasks.required' => 'You must select at least one task.',
            'tasks.*.invprice.numeric' => 'All prices must be numeric values.',
        ]);

        $selectedTasks = collect($validated['tasks']);
        // Generate invoice
        return $this->createInvoice($selectedTasks);
    }



    private function fetchUserBasedData()
    {
        $user = Auth::user();
        $suppliers = Supplier::all();

        if ($user->role_id == Role::COMPANY) {
            $company = Company::with('branches.agents.clients')->find($user->company->id);

            return [
                'supplier' => $suppliers,
                'company' => [
                    'name' => $company->name,  // Essential company data
                    'contactPerson' => $company->contact_person,
                    'id' => $company->id,
                ],
                'branches' => $company->branches->map(function ($branch) {
                    return [
                        'name' => $branch->name,
                        'id' => $branch->id,
                    ];
                }),
                'agents' => $company->branches->flatMap->agents->map(function ($agent) {
                    return [
                        'name' => $agent->name,
                        'id' => $agent->id,
                        'email' => $agent->email,
                        'contact' => $agent->phone_number,
                        'branchId' => $agent->branch_id,
                        'branchName' => $agent->branch->name,
                        'type' => $agent->type,
                    ];
                }),
                'clients' => $company->branches->flatMap->agents->flatMap->clients->map(function ($client) {
                    return [
                        'name' => $client->first_name,
                        'id' => $client->id,
                        'agentId' => $client->agent_id,
                        'agentName' => $client->agent->name,
                        'contact' => $client->phone,
                        'email' => $client->email,
                        'address' => $client->address,
                        'passportNo' => $client->passport_no,
                    ];
                }),
                'tasks' => $company->branches
                    ->flatMap->agents->flatMap->clients->flatMap->tasks
                    ->map(function ($task) {
                        return [
                            'id' => $task->id,
                            'description' => $task->reference . ' - ' . $task->additional_info,
                            'status' => $task->status,
                            'agentId' => $task->agent_id,
                            'agentName' => $task->agent ? $task->agent->name : null,
                            'clientId' => $task->client_id,
                            'clientName' => $task->client->first_name,
                            'supplierName' => $task->supplier->name,
                            'supplierId' => $task->supplier_id,
                            'invprice' =>  $task->invoice_price,
                            'price' => $task->total,
                        ];
                    }),
                'selectedtasks' => $company->branches()
                    ->with(['agents.clients.tasks.invoiceDetail']) // Eager load all nested relationships
                    ->get()
                    ->flatMap->agents->flatMap->clients->flatMap->tasks
                    ->filter(function ($task) {
                        return !$task->invoiceDetail;
                    })
                    ->map(function ($task) {
                        return [
                            'id' => $task->id,
                            'description' => $task->reference . ' - ' . $task->additional_info,
                            'status' => $task->status,
                            'agentId' => $task->agent_id,
                            'agentName' => $task->agent ? $task->agent->name : null,
                            'clientId' => $task->client_id,
                            'clientName' => $task->client->first_name,
                            'supplierName' => $task->supplier->name,
                            'supplierId' => $task->supplier_id,
                            'invprice' =>  $task->invoice_price,
                            'price' => $task->total,
                        ];
                    }),
                'invoices' => $company->branches->flatMap->agents->flatMap->invoices->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'date' => $invoice->invoice_date,
                        'invoice_number' => $invoice->invoice_number,
                        'total_amount' => $invoice->amount,
                        'invoice_date' => $invoice->invoice_date,
                        'due_date' => $invoice->due_date,
                        'status' => $invoice->status,  // Only essential invoice details
                        'agentId' => $invoice->agent_id,
                        'agentName' => $invoice->agent->name,
                        'clientId' => $invoice->client_id,
                        'clientName' => $invoice->client->first_name,
                        'payment_type' => $invoice->payment_type,
                        'paid_date' => $invoice->paid_date,
                    ];
                }),
                'invoiceDetails' => $company->branches->flatMap->agents->flatMap->invoices->flatMap->invoiceDetails->map(function ($detail) {
                    return [
                        'id' => $detail->id,
                        'task_id' => $detail->task_id,
                        'invoice_id' => $detail->invoice_id,
                        'task_description' => $detail->task_description,
                        'supplier_price' => $detail->supplier_price,
                        'markup_price' => $detail->markup_price,
                        'invoice_price' => $detail->task_price,
                    ];
                }),
                'invoicePartials' => $company->branches->flatMap->agents->flatMap->invoices->flatMap->invoicePartials->map(function ($detail) {
                    return [
                        'id' => $detail->id,
                        'client_id' => $detail->client_id,
                        'invoice_id' => $detail->invoice_id,
                        'amount' => $detail->amount,
                        'status' => $detail->status,
                        'type' => $detail->type,
                        'payment_gateway' => $detail->payment_gateway,
                    ];
                }),
            ];
        } elseif ($user->role_id == Role::AGENT) {
            $agent = $user->agent;
            $company = $agent->branch->company;

            return [
                'supplier' => $suppliers,
                'company' => [
                    'name' => $company->name,  // Essential company data
                    'contactPerson' => $company->contact_person,
                    'id' => $company->id,
                ],
                'branches' => $company->branches->map(function ($branch) {
                    return [
                        'name' => $branch->name,
                        'id' => $branch->id,
                    ];
                }),
                'agents' => $company->branches->flatMap->agents->map(function ($agent) {
                    return [
                        'name' => $agent->name,
                        'id' => $agent->id,
                        'branchId' => $agent->branch_id,
                        'role' => $agent->role,
                    ];
                }),
                'clients' => $company->branches->flatMap->agents->flatMap->clients->map(function ($client) {
                    return [
                        'name' => $client->first_name,
                        'id' => $client->id,
                        'agentId' => $client->agent_id,
                        'contact' => $client->contact_details,  // Only essential client details
                    ];
                }),
                'tasks' => $company->branches->flatMap->agents->flatMap->clients->flatMap->tasks->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'description' => $task->reference . ' - ' . $task->additional_info,
                        'status' => $task->status,
                        'agentId' => $task->agent_id,
                        'agentName' => $task->agent ? $task->agent->name : null,
                        'clientId' => $task->client_id,
                        'clientName' => $task->client->first_name,
                        'supplierId' => $task->supplier_id,
                        'invprice' =>  $task->invoice_price,
                        'price' => $task->total,
                    ];
                }),
                'invoices' => $company->branches->flatMap->agents->flatMap->invoices->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'total_amount' => $invoice->amount,
                        'status' => $invoice->status,  // Only essential invoice details
                        'agentId' => $invoice->agent_id,
                        'clientId' => $invoice->client_id,
                    ];
                }),
                'invoiceDetails' => $company->branches->flatMap->agents->flatMap->invoices->flatMap->invoiceDetails->map(function ($detail) {
                    return [
                        'id' => $detail->id,
                        'task_id' => $detail->task_id,
                        'invoice_id' => $detail->invoice_id,
                        'task_description' => $detail->task_description,
                        'supplier_price' => $detail->supplier_price,
                        'markup_price' => $detail->markup_price,
                    ];
                }),
            ];
        } else {
            return [
                'error' => 'Unauthorized role for chatbot context.',
            ];
        }
    }


    private function createInvoice($tasks)
    {
        $duedate = now()->addDays(30);
        $invdate = now();
        $currency =  'KWD';
        $user = Auth::user();
        $taskIds = collect($tasks)->pluck('id')->toArray();
        // Retrieve the tasks from the database and include 'invprice' directly
        $selectedTasks = Task::whereIn('id', $taskIds)
            ->get()
            ->each(function ($task) use ($tasks) {
                // Find the matching task and assign invprice to the task object
                $taskData = collect($tasks)->firstWhere('id', $task->id);
                if ($taskData) {
                    $task->invprice = $taskData['invprice'];
                }
            });


        $invoiceSequence = InvoiceSequence::lockForUpdate()->first();

        if (!$invoiceSequence) {
            $invoiceSequence = InvoiceSequence::create(['current_sequence' => 1]);
        }

        $currentSequence = $invoiceSequence->current_sequence;
        $invoiceNumber = $this->generateInvoiceNumber($currentSequence);

        $invoiceSequence->current_sequence++;
        $invoiceSequence->save();

        $this->storeNotification([
            'user_id' => $user->id,
            'title' => 'Invoice' . $invoiceNumber . ' Created By ' . $user->name,
            'message' => 'Invoice ' . $invoiceNumber . ' has been created.'
        ]);

        $subTotal = $selectedTasks->sum('invprice');
        if ($selectedTasks->count() > 0) {
            $clientIds = $selectedTasks->pluck('client_id')->unique();
            $agentIds =  $selectedTasks->pluck('agent_id')->unique();
            $selectedAgent = Agent::find($agentIds->first());

            if ($clientIds->count() >= 1) {
                $selectedClient = Client::find($clientIds->first());
            } else {
                $selectedClient = null; // Handle multi-client case
            }
        } else {
            $selectedClient = null; // No tasks selected
            $selectedAgent = null;
        }


        $appUrl = config('app.url');
        $agent = $selectedAgent;
        $companyId = $agent && $agent->branch && $agent->branch->company ? $agent->branch->company->id : null;
        $branchId = $agent ? $agent->branch_id : null;

        $receivableAccount = Account::where('name', 'like', '%Receivable%')
            ->where('company_id', $companyId)
            ->first();


        $payableAccount =  Account::where('name', 'like', '%Payable%')
            ->where('company_id', $companyId)
            ->first();

        $incomeAccount =  Account::where('name', 'like', '%Income On Sales%')
            ->where('company_id', $companyId)
            ->first();

        try {


            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'agent_id' => $selectedAgent->id,
                'client_id' => $selectedClient->id,
                'sub_amount' => $subTotal,
                'amount' => $subTotal,
                'currency' => $currency,
                'status' => 'unpaid',
                'invoice_date' => $invdate,
                'due_date' => $duedate,
                'payment_type' => 'full',
            ]);

            if (!empty($selectedTasks)) {
                foreach ($selectedTasks as $task) {
                    try {

                        $selectedtask = Task::where('id', operator: $task['id'])->first();
                        $supplier = Supplier::where('id', operator: $task['supplier_id'])->first();
                        $client = Client::where('id', operator: $task['client_id'])->first();
                        $agent = Agent::where('id', operator: $task['agent_id'])->first();
                        // Create a transaction record first

                        $invoiceDetail =  InvoiceDetail::create([
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoiceNumber,
                            'task_id' => $task['id'],
                            'task_description' => $task['reference'] . ' ' . $task['additional_info'],
                            'task_remark' => $task['remark'],
                            'client_notes' => $task['note'],
                            'task_price' =>  $task['invprice'],
                            'supplier_price' => $selectedtask->total,
                            'markup_price' => $task['invprice'] - $selectedtask->total,
                            'paid' => false,
                        ]);

                        $transaction = Transaction::create([
                            'branch_id' => $branchId,
                            'entity_id' => $companyId,
                            'entity_type' => 'company',
                            'transaction_type' => 'credit',
                            'amount' =>  $task['invprice'],
                            'description' => 'Invoice:' . $invoiceNumber . ' Generated',
                            'invoice_id' => $invoice->id,
                            'reference_type' => 'Invoice',
                        ]);


                        if ($payableAccount) {
                            $filteredPayableChildAccount = $payableAccount->children()
                                ->where('reference_id', $task['supplier_id']) // Filter by child reference_id
                                ->first(); // Get the first matching child account
                            Log::info('filteredPayableChildAccount', ['filteredPayableChildAccount' => $filteredPayableChildAccount]);
                            $PayablechildAccountId = $filteredPayableChildAccount ? $filteredPayableChildAccount->id : null;
                        } else {
                            $PayablechildAccountId = null; // Handle case when no parent account is found
                        }


                        // Try to create payable account
                        JournalEntry::create([
                            'transaction_id' => $transaction->id,
                            'company_id' => $companyId,
                            'branch_id' => $branchId,
                            'account_id' =>  $payableAccount->id,
                            'branch_id' => $branchId,
                            'account_id' =>  $payableAccount->id,
                            'invoice_id' =>  $invoice->id,
                            'invoiceDetail_id' =>  $invoiceDetail->id,
                            'invoiceDetail_id' =>  $invoiceDetail->id,
                            'transaction_date' => Carbon::now(),
                            'description' => 'Payment : ' . $supplier->name,
                            'debit' => $selectedtask->total,
                            'credit' => 0,
                            'balance' => $selectedtask->total,
                            'name' => $supplier->name,
                            'type' => 'payable',
                            'type_reference_id' => $supplier->id
                        ]);


                        // Try to create receivable account
                        JournalEntry::create([
                            'transaction_id' => $transaction->id,
                            'company_id' => $companyId,
                            'branch_id' => $branchId,
                            'branch_id' => $branchId,
                            'invoice_id' =>  $invoice->id,
                            'invoiceDetail_id' =>  $invoiceDetail->id,
                            'account_id' =>  $receivableAccount->id,
                            'invoiceDetail_id' =>  $invoiceDetail->id,
                            'account_id' =>  $receivableAccount->id,
                            'transaction_date' => Carbon::now(),
                            'description' => 'Payment received from: ' . $client->first_name,
                            'debit' => 0,
                            'credit' => $task['invprice'],
                            'balance' => $task['invprice'],
                            'name' =>  $client->first_name,
                            'type' => 'receivable',
                            'type_reference_id' => $client->id
                        ]);



                        $markup = $task['invprice'] - $selectedtask->total;
                        // Try to create income
                        JournalEntry::create([
                            'transaction_id' => $transaction->id,
                            'company_id' => $companyId,
                            'branch_id' => $branchId,
                            'account_id' => $incomeAccount->id,
                            'branch_id' => $branchId,
                            'account_id' => $incomeAccount->id,
                            'invoice_id' =>  $invoice->id,
                            'invoiceDetail_id' =>  $invoiceDetail->id,
                            'invoiceDetail_id' =>  $invoiceDetail->id,
                            'transaction_date' => Carbon::now(),
                            'description' => 'Price markup by Agent: ' . $agent->name,
                            'debit' => 0,
                            'credit' => $markup,
                            'balance' => $markup,
                            'name' =>   $agent->name,
                            'type' => 'income',
                            'type_reference_id' => $agent->id
                        ]);


                        $selectedtask->status = 'Assigned';
                        $selectedtask->save();
                    } catch (Exception $e) {
                        Log::error('Failed to create InvoiceDetails: ' . $e->getMessage());
                        return response()->json('Failed to create InvoiceDetails for task: ' . $task['description']);
                    }
                }
            }

            $generatedLink = $appUrl . '/invoice/' . $invoiceNumber;
            $clients = Client::select('id', 'name', 'email')->get();

            // Return response
            return response()->json([
                'success' => true,
                'invoiceLink' => $generatedLink,
                'invoiceNumber' => $invoiceNumber,
                'invoiceId' => $invoice->id,
                'clientId' => $invoice->client_id,
                'invoiceAmount' => $invoice->amount,
                'due_date' => $invoice->due_date,
                'clients' => $clients
            ]);
        } catch (Exception $e) {
            Log::error('Failed to create InvoiceDetails: ' . $e->getMessage());
            return response()->json('Invoice creation failed!');
        }
    }


    public function generateInvoiceNumber($sequence)
    {
        $year = now()->year;
        return sprintf('INV-%s-%05d', $year, $sequence);
    }


    private function sendWhatsApp($userData)
    {
        if (!isset($userData['contact_number'])) {
            return response()->json(['error' => 'No contact number provided for WhatsApp.'], 400);
        }

        // Logic to send WhatsApp message
        $message = "Hello! This is a reminder from your travel agency.";
        // Example: WhatsAppService::send($userData['contact_number'], $message);

        return response()->json(['success' => true, 'message' => 'WhatsApp message sent.'], 200);
    }

    private function sendEmail($userData)
    {
        if (!isset($userData['email'])) {
            return response()->json(['error' => 'No email address provided.'], 400);
        }

        // Logic to send email
        $emailContent = "Hello! This is a reminder from your travel agency.";
        // Example: Mail::to($userData['email'])->send(new ReminderEmail($emailContent));

        return response()->json(['success' => true, 'message' => 'Email sent successfully.'], 200);
    }

    private function initiatecreateClient($userData)
    {

        $user = Auth::user();
        $company = collect();

        if($user->role_id == Role::COMPANY){
            $company = Company::with('branches.agents.clients')->find($user->company->id);
        } else if($user->role_id == Role::BRANCH){
            $company = Company::with('branches.agents.clients')->find($user->branch->company->id);
        } else if($user->role_id == Role::AGENT){
            $company = Company::with('branches.agents.clients')->find($user->agent->branch->company->id);
        } else {
            return response()->json(['error' => 'Unauthorized role for creating a client.'], 403);
        }

        return response()->json([
            'message' => 'Create New Client:',
            'client' => $company,
        ], 200);
    }

    private function initiatecreateAgent($userData)
    {

        $user = Auth::user();

        $company = Company::with('branches.agents.clients')->find($user->company->id);
        $branches = $company->branches;

        return response()->json([
            'message' => 'Create New Agent:',
            'agent' => $branches,
        ], 200);
    }


    private function initiatecreateBranch($userData)
    {

        $user = Auth::user();

        $company = Company::with('branches.agents.clients')->find($user->company->id);

        return response()->json([
            'message' => 'Create New Branch:',
            'branch' => $company,
        ], 200);
    }

    public function handleFileUpload(Request $request)
    {
        if ($request->hasFile('file')) {
            try {

                $file = $request->file('file');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('uploads', $fileName, 'public');

                $fullFilePath = storage_path('app/public/' . $filePath);
                
                Log::info('Processing passport file with AI:', [
                    'fileName' => $fileName,
                    'filePath' => $fullFilePath
                ]);

                $aiManager = new AIManager();
                $response = $aiManager->extractPassportData($fullFilePath, $fileName);
                
                Log::info('AI passport extraction response:', ['response' => $response]);

                if ($response['success']) {
                    $passportData = $response['data'];

                    return response()->json([
                        'success' => true,
                        'message' => 'Passport data extracted successfully using AI!',
                        'data' => $passportData,
                    ], 200);

                } else {
                    Log::error('AI passport extraction failed: ' . $response['message']);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to extract passport data using AI: ' . $response['message'],
                        'errors' => $response['message'],
                    ], 400);
                }

            } catch (Exception $e) {
                Log::error('Failed to process passport with AI: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Error processing passport with AI',
                    'errors' => $e->getMessage(),
                ], 400);
            }
        } else {
            Log::error('No file uploaded for passport processing');
            return response()->json([
                'success' => false,
                'message' => 'Error processing passport',
                'errors' => 'No file uploaded.',
            ], 400);
        }
    }

    // Function to extract text from PDF using Smalot PdfParser
    private function extractTextFromPdf($filePath)
    {

        Log::info('extractTextFromPdf:', ['filePath' => $filePath]);
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile(storage_path('app/public/' . $filePath));
            Log::info('pdf:', ['pdf' => $pdf]);
            $text = $pdf->getText(); // Extract text content from PDF

            if (empty($text)) {
                return null; // No text extracted
            }

            Log::info('Extracted Text from PDF:', ['text' => $text]);
            return $text; // Return the extracted text
        } catch (\Exception $e) {
            Log::error('Error extracting text from PDF', ['error' => $e->getMessage()]);
            return null; // Return null in case of error
        }
    }

    private function extractImagesFromPdf($pdfFilePath)
    {
        try {
            // Command to extract images from the PDF using poppler-utils (pdftohtml or pdfimages)
            $outputDir = storage_path('app/public/outputs/');
            $command = "pdftohtml -c -hidden -images '$pdfFilePath' '$outputDir/output.html'";

            // Run the command to extract images
            exec($command);

            // Get all image paths from the output directory
            $imagePaths = glob($outputDir . '*.jpg'); // Adjust the file extension if necessary

            return $imagePaths;
        } catch (\Exception $e) {
            Log::error('Error extracting images from PDF', ['error' => $e->getMessage()]);
            return [];
        }
    }


    public function createClientPassport($data)
    {
        // Ensure that $data is an array and has the required fields
        if (is_array($data) && isset($data['name'], $data['passport_no'], $data['civil_no'], $data['date_of_birth'])) {
            // Create client from parsed passport data
            $dateOfBirth = $this->convertToDate($data['date_of_birth'] ?? null);

            $clientController = new ClientController();

            $request = new Request();
            $request->merge([
                'name' => $data['name'],
                'passport_no' => $data['passport_no'],
                'civil_no' => $data['civil_no'],
                'date_of_birth' => $dateOfBirth,
                'address' => $data['place_of_issue'] ?? null, // Ensure this field exists
            ]);

            $response = $clientController->storeProcess($request);

            if($response['status'] == 'error') {
                return response()->json([
                    'success' => false,
                    'message' => $response['message'],
                ], 400);
            }

            $client = $response['data'];
            // $client = Client::create([
            //     'name' => $data['name'],
            //     'status' => 'active',
            //     'address' => $data['place_of_issue'], // Make sure place_of_issue exists, otherwise handle accordingly
            //     'passport_no' => $data['passport_no'],
            //     'civil_no' => $data['civil_no'],
            //     'date_of_birth' => $dateOfBirth,
            //     'account_id' => $account->id,
            // ]);

            Log::info('Client created successfully', ['client' => $client]);

            return $client;
        } else {
            Log::error('Invalid passport data received', ['data' => $data]);

            throw new \Exception('Invalid passport data');
        }
    }

    private function convertToDate(?string $date): ?string
    {
        if (!$date) {
            return null; // Return null if date is empty or not set
        }

        // Check if the date is already in YYYY-MM-DD format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        // Convert DD/MM/YYYY to YYYY-MM-DD
        $dateParts = explode('/', $date);
        if (count($dateParts) === 3) {
            return sprintf('%04d-%02d-%02d', $dateParts[2], $dateParts[1], $dateParts[0]);
        }

        Log::error('Invalid date format', ['date' => $date]);
        return null; // Return null if the date format is invalid
    }

    public function createClient(Request $request)
    {
        $clientController = new ClientController();
        $response = $clientController->storeProcess($request);

        if ($response['status'] == 'error') {
            return response()->json([
                'success' => false,
                'message' => $response['message'],
            ], 400);
        }


        return response()->json([
            'success' => true,
            'message' => 'Client updated successfully!',
            'client' => $response['data'],
            'action' => 'update',
        ], 200);
    }

    public function createAgent(Request $request)
    {
        $userAuth = Auth::user();
        $role = $userAuth->role_id;

        if ($userAuth->role_id == Role::COMPANY) {
            $company = $userAuth->company;
            $company = Company::with('branches.agents')->find($company->id);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone_number' => 'required|string',
            'branch_id' => 'required',
            'type' => 'required'
        ]);

        try {

            // Create a new user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make('citytour123'),
                'role_id' => Role::AGENT
            ]);

            $receivableAccount = Account::where('name', 'like', '%Receivable%')
                ->where('company_id', $company->id)
                ->first();

            $account = Account::create([
                'name' => $request->name,
                'level' => 4,
                'actual_balance' => 0,
                'budget_balance' => 0,
                'variance' => 0,
                'company_id' => $company->id,
                'parent_id' => $receivableAccount->id,
                'reference_id' => $user->id,
                'code' => 'AGT-' . rand(1000000, 9999999),
            ]);

            $agent = Agent::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'branch_id' => $request->branch_id,
                'type' => $request->type,
                // 'account_id' => $account->id
            ]);


            return response()->json([
                'success' => true,
                'message' => 'Agent registered successfully!',
                'agent' => $agent,
            ], 201);
        } catch (Exception $e) {
            Log::error('Failed to create agent: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error registering agent: ' . $e->getMessage(),
                'errors' => $e->getMessage(),
            ], 400);
        }
    }

    public function createBranch(Request $request)
    {
        $userAuth = Auth::user();
        $role = $userAuth->role_id;

        if ($userAuth->role_id == Role::COMPANY) {
            $company = $userAuth->company;
            $company = Company::with('branches.agents')->find($company->id);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string'
        ]);

        // Create a new user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make('citytour123'),
            'role_id' => Role::BRANCH
        ]);

        $branch = Branch::create([
            'user_id' => $user->id,
            'name' => $request->branch_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'company_id' => $company->id
        ]);


        return response()->json([
            'success' => true,
            'message' => 'Branch registered successfully!',
            'branch' => $branch, // Optionally return client details
        ], 201);
    }


    public function processPayment(Request $request)
    {
        $request->validate([
            'invoiceId' => 'required',
            'date' => 'required',
            'clientId' => 'required',
            'amount' => 'required',
            'type' => 'required|string',
            'invoiceNumber' => 'required|string',
            'gateway' => 'required|string',
        ]);

        $invoiceId = $request->input('invoiceId');
        $invoiceNumber = $request->input('invoiceNumber');
        $clientId = $request->input('clientId');
        $type = $request->input('type');
        $date = $request->input('date');
        $amount = $request->input('amount');
        $gateway = $request->input('gateway');

        $invoice = Invoice::where('invoice_number', $invoiceNumber)->with('agent.branch.company', 'client', 'invoiceDetails')->first();


        try {

            $invoicepartial = InvoicePartial::create([
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'client_id' => $clientId,
                'amount' => $amount,
                'status' => 'unpaid',
                'expiry_date' => $date,
                'type' => $type,
                'payment_gateway' => $gateway,
            ]);

            $invoice->payment_type = $type;
            $invoice->save();

            return response()->json([
                'success' => true,
                'message' => 'Invoice Partial created successfully!',
                'invoiceId' => $invoiceId,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to create InvoiceDetails: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create invoice!',
            ]);
        }
    }
}
