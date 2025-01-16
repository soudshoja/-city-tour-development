<?php

namespace App\Http\Controllers;

use App\Http\Traits\NotificationTrait;
use Illuminate\Http\Request;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\Role;
use App\Models\User;
use App\Models\Account;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\Client;
use App\Models\GeneralLedger;
use App\Models\Task;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\InvoiceDetail;
use App\Models\Transaction;
use Carbon\Carbon;

class ChatController extends Controller
{
    use NotificationTrait;

    protected $openAIService;

    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
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


            $messagesData = [
                [
                    'role' => 'system',
                    'content' => "You are a chatbot for a travel agency that interacts with travel agencies or agents. 
                              If the user's query involves a list, ensure the response is formatted as a proper list 
                              using bullet points (-) or numbering (1., 2., 3.) for clarity. Use the following data:",
                ],
                [
                    'role' => 'user',
                    'content' => $userMessage,
                ],
                [
                    'role' => 'system',
                    'content' => json_encode($userData), // Send user data as JSON to OpenAI
                ],
            ];

            // Classification Step
            $classification = $this->classifyMessage($userMessage) ?? 'GeneralMessage';
            \Log::info('Message classification: ' . $classification);

            // Process based on classification
            if ($classification === 'DataQuery') {
                $response = $this->openAIService->getChatResponse($messagesData);

                $content = $response['choices'][0]['message']['content'] ?? '';
                $formattedContent = $this->formatAsList($content);

                $response['choices'][0]['message']['content'] = $formattedContent;

                return response()->json($response, 200);
            } elseif ($classification === 'ActionRequest') {
                \Log::info('action:', ['class' => $classification]);
                return $this->handleActionRequest($userMessage, $userData);
            } else {
                // Pass the message to OpenAI if it's not recognized as data or action
                $response = $this->openAIService->getChatResponse($messages);
                return response()->json($response, 200);
            }
        } catch (\Exception $e) {
            \Log::error('Chatbot error: ' . $e->getMessage());
            return response()->json(['error' => 'Something went wrong. Please try again later.'], 500);
        }
    }


    private function formatAsList($content)
    {
        // Check if the content is a single line (for cases like your example)
        if (strpos($content, '-') === false && strpos($content, '1.') === false) {
            // If it's a single line, split it by commas or other delimiters
            $clients = preg_split('/\s*[-,;]\s*/', $content);

            $formattedLines = [];
            foreach ($clients as $client) {
                $trimmedClient = trim($client);
                if (!empty($trimmedClient)) {
                    $formattedLines[] = '- ' . $trimmedClient; // Add bullet points
                }
            }

            return implode("\n", $formattedLines); // Return the formatted list
        }

        // If it's already formatted with bullet points or numbering, return as is
        $lines = explode("\n", $content);
        $formattedLines = [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Add bullet points or numbering if not already present
            if (!preg_match('/^\d+\./', $trimmedLine) && !str_starts_with($trimmedLine, '-')) {
                $formattedLines[] = '- ' . $trimmedLine; // Add bullet points for plain lines
            } else {
                $formattedLines[] = $trimmedLine; // Keep already formatted lines
            }
        }

        return implode("\n", $formattedLines);
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

        $response = $this->openAIService->getChatResponse($classificationMessages);

        // Extract the classification result
        if (isset($response['choices'][0]['message']['content'])) {
            return trim($response['choices'][0]['message']['content']);
        }

        return 'GeneralMessage'; // Default to GeneralMessage if no classification is returned
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

        \Log::info('handleActionRequest:', ['message' => $message]);
        $response = $this->openAIService->getChatResponse($actionMessages);

        if (isset($response['choices'][0]['message']['content'])) {
            $responseContent = $response['choices'][0]['message']['content'];

            // Attempt to decode the JSON response
            $parsedResponse = json_decode($responseContent, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($parsedResponse['action'])) {
                $action = strtolower(trim($parsedResponse['action']));
                $taskIds = $parsedResponse['task_ids'] ?? [];

                if (!is_array($taskIds)) {
                    $taskIds = [];
                }

                \Log::info('action:', ['action' => $action]);
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
            }
        }

        // Log and return error if JSON structure is invalid
        \Log::error('Invalid response from OpenAI:', ['response' => $response]);
        return response()->json(['error' => 'Unable to classify action.'], 400);
    }


    private function initiateInvoiceCreationWithTasks(array $taskIds, $userData)
    {
        // Logic to create an invoice using the provided task IDs
        \Log::info('initiateInvoiceCreationWithTasks:', ['taskIds' => $taskIds]);

        if (!empty($taskIds)) {
            // If tasks are directly mentioned, validate and proceed
            return $this->processTaskSelection($taskIds);
        }

        return response()->json([
            'message' => 'Please choose the tasks to include in the invoice:',
            'tasks' => collect($userData['tasks'])->map(function ($task) {
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
        \Log::info('processTaskSelection:', ['taskIds' => $taskIds]);

        $userData = $this->fetchUserBasedData();

        // Cast task IDs to integers
        $taskIds = array_map('intval', $taskIds);
        \Log::info('processTaskSelectiontaskIds:', ['taskIds' => $taskIds]);
        // Filter available tasks based on provided IDs
        $availableTasks = collect($userData['tasks'])->whereIn('id', $taskIds);
        \Log::info('available Tasks:', ['availableTasks' => $availableTasks]);


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

        // Log task IDs received
        \Log::info('processTaskSelection:', ['taskIds' => $taskIds]);

        // Fetch user data, including tasks
        $userData = $this->fetchUserBasedData();

        // Ensure task IDs are integers
        $taskIds = array_map('intval', $taskIds);
        \Log::info('processTaskSelection taskIds:', ['taskIds' => $taskIds]);

        // Fetch available tasks based on the provided task IDs (with eager loading of client)
        $availableTasks = Task::with('client')  // Eager load the 'client' relationship
            ->whereIn('id', $taskIds)
            ->get();  // Execute the query to retrieve the tasks

        \Log::info('availableTasks:', ['availableTasks' => $availableTasks]);

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
                    'client' => $task->client ? $task->client->name : 'N/A', // Safely access client name
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
        \Log::info('handleTaskPricing:', ['selectedTask' => $selectedTasks]);
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
                        'name' => $client->name,
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
                        'agentName' => $task->agent->name,
                        'clientId' => $task->client_id,
                        'clientName' => $task->client->name,
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
        } elseif ($user->role_id == Role::AGENT) {
            $agent = $user->agent;
            $company = $agent->branch->company;

            return [
                'supplier' => $suppliers,
                'company' => [
                    'name' => $company->name,  // Essential company data
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
                        'name' => $client->name,
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
                        'agentName' => $task->agent->name,
                        'clientId' => $task->client_id,
                        'clientName' => $task->client->name,
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
        \Log::info('createInvoice:', ['selectedTask' => $tasks]);
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

        Log::info('Company ID:', ['companyId' => $companyId]);

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
                            'entity_id' => $companyId,
                            'entity_type' => 'company',
                            'transaction_type' => 'credit',
                            'amount' =>  $task['invprice'],
                            'date' => Carbon::now(),
                            'description' => 'Invoice:' . $invoiceNumber . ' Generated',
                            'invoice_id' => $invoice->id,
                            'reference_type' => 'Invoice',
                        ]);


                        Log::info('filteredPayableChild', ['filteredPayableChild' => $payableAccount->children()]);
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
                        GeneralLedger::create([
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
                        GeneralLedger::create([
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
                            'description' => 'Payment received from: ' . $client->name,
                            'debit' => 0,
                            'credit' => $task['invprice'],
                            'balance' => $task['invprice'],
                            'name' =>  $client->name,
                            'type' => 'receivable',
                            'type_reference_id' => $client->id
                        ]);



                        $markup = $task['invprice'] - $selectedtask->total;
                        // Try to create income
                        GeneralLedger::create([
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

        $company = Company::with('branches.agents.clients')->find($user->company->id);

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

    public function createClient(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email',
            'phone' => 'nullable|string|max:15',    // Optional phone field
        ]);

        // Create a new client record
        try {
            $agent = Agent::where('email', $request->get('agent_email'))->first();

            $client = Client::create([
                'name' => $request->get('name'),
                'email' => $request->get('email'),
                'status' => $request->get('status'),
                'phone' => $request->get('phone'),
                'address' => $request->get('address'),
                'passport_no' => $request->get('passport_no'),
                'agent_id' => $agent->id,
            ]);

            // Redirect to the clients list with a success message
            return response()->json([
                'success' => true,
                'message' => 'Client registered successfully!',
                'client' => $client, // Optionally return client details
            ], 201);
        } catch (Exception $e) {
            Log::error('Failed to create client: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error registering client: ' . $e->getMessage(),
                'errors' => $e->errors() ?? [],
            ], 400);
        }
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
                'role_id' => 3
            ]);

            $agent = Agent::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'company_id' => $company->id,
                'branch_id' => $request->branch_id,
                'type' => $request->type,
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
                'errors' => $e->errors() ?? [],
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
            'role_id' => 6
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
