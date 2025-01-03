<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Auth;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\Role;
use App\Models\User;

class ChatController extends Controller
{
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
                    'content' => "You are a chatbot for a travel agency. Please use the following data based to answer any questions.",
                ],
                [
                    'role' => 'user',
                    'content' => $userMessage,
                ],
            ];
    
            // Add user data context dynamically
            $messages[] = [
                'role' => 'system',
                'content' => json_encode($userData)  // Send user data as JSON to OpenAI
            ];
    
            // First, detect the type of message (Data Query or Action Request)
            if ($this->isDataQuery($userMessage)) {
                // Handle data query
                return $this->handleDataQuery($userMessage, $userData);
            } elseif ($this->isActionRequest($userMessage)) {
                // Handle action request (like create invoice, send message)
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
    

    private function handleDataQuery($userMessage, $userData)
    {
        // Check for specific data request in the message and return corresponding data
        if (stripos($userMessage, 'clients') !== false) {
            $clients = $userData['clients']->pluck('name'); // Assuming name is the relevant column
            return response()->json(['clients' => $clients], 200);
        } elseif (stripos($userMessage, 'invoices') !== false) {
            $invoices = $userData['invoices']->pluck('invoice_number'); // Assuming invoice_number is the relevant column
            return response()->json(['invoices' => $invoices], 200);
        } elseif (stripos($userMessage, 'tasks') !== false) {
            $tasks = $userData['tasks']->pluck('task_name'); // Assuming task_name is the relevant column
            return response()->json(['tasks' => $tasks], 200);
        }
        // Add more conditions based on other data
    }

    private function handleActionRequest($userMessage, $userData)
    {
        // Check if the action is 'create invoice'
        if (stripos($userMessage, 'create invoice') !== false) {
            $invoiceDetails = $this->extractInvoiceDetailsFromMessage($userMessage);
    
            // Validate and create the invoice
            if (empty($invoiceDetails['client_id']) || empty($invoiceDetails['amount'])) {
                return response()->json(['error' => 'Please provide complete invoice details.'], 400);
            }
    
            $invoice = $this->createInvoice($invoiceDetails);
            return response()->json(['invoice' => $invoice], 200);
        }
    
        // Handle other actions like sending a message
        if (stripos($userMessage, 'send message') !== false) {
            $messageDetails = $this->extractMessageDetailsFromMessage($userMessage);
    
            if (empty($messageDetails['recipient_id']) || empty($messageDetails['content'])) {
                return response()->json(['error' => 'Please provide recipient and message content.'], 400);
            }
    
            $this->sendMessage($messageDetails);
            return response()->json(['message' => 'Message sent successfully.'], 200);
        }
    }

    
    
    private function isDataQuery($message)
    {
        // Basic keyword matching for data-related queries
        $dataKeywords = ['clients', 'invoices', 'tasks', 'agents', 'branches'];
    
        foreach ($dataKeywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return true;
            }
        }
    
        return false;
    }
    

    private function isActionRequest($message)
    {
        // Basic keyword matching for action requests
        $actionKeywords = ['create invoice', 'send message', 'update task'];

        foreach ($actionKeywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return true;
            }
        }

        return false;
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
                        'description' => $task->additional_info,
                        'status' => $task->status,
                        'agentId' => $task->agent_id,
                        'clientId' => $task->client_id,
                        'supplierId' => $task->supplier_id,
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
                        'description' => $task->additional_info,
                        'status' => $task->status,
                        'agentId' => $task->agent_id,
                        'clientId' => $task->client_id,
                        'supplierId' => $task->supplier_id,
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
    

    private function extractInvoiceDetailsFromMessage($message)
{
    preg_match('/client (\d+)/', $message, $clientMatches);
    preg_match('/amount (\d+(\.\d{1,2})?)/', $message, $amountMatches);
    preg_match('/due date (\d{4}-\d{2}-\d{2})/', $message, $dueDateMatches);

    return [
        'client_id' => $clientMatches[1] ?? null,
        'amount' => $amountMatches[1] ?? null,
        'due_date' => $dueDateMatches[1] ?? null,
    ];
}

private function extractMessageDetailsFromMessage($message)
{
    preg_match('/to (\d+)/', $message, $recipientMatches);
    preg_match('/message (.+)/', $message, $contentMatches);

    return [
        'recipient_id' => $recipientMatches[1] ?? null,
        'content' => $contentMatches[1] ?? null,
    ];
}

private function sendMessage($messageDetails)
{
    // Send the message to the recipient (You need to implement this method based on your system)
    // Example: Message::create($messageDetails);
}

   
}
