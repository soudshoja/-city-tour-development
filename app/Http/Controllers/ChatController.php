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
            // Extract the user message from the validated input
            $userMessage = collect($validated['messages'])->last()['content'];
            
            // Fetch user-specific data based on their role
            $userData = $this->fetchUserBasedData();
    
            // Check if there was an error in fetching user data
            if (isset($userData['error'])) {
                return response()->json(['error' => $userData['error']], 403);
            }
    
            // Prepare the messages for OpenAI
            $messages = [
                [
                    'role' => 'system',
                    'content' => "You are a chatbot for a travel agency. Please use the following data based on the user's role to answer any questions about companies, branches, agents, and clients.",
                ],
                [
                    'role' => 'user',
                    'content' => $userMessage,
                ],
            ];
    
            // Dynamically add user data context to the system message (e.g., clients, agents, etc.)
            if ($userData) {
                $messages[] = [
                    'role' => 'system',
                    'content' => json_encode($userData)  // Send user data as JSON to OpenAI
                ];
            }
    
            // Get the response from OpenAI using the OpenAIService
            $response = $this->openAIService->getChatResponse($messages);
    
            // Return the response from OpenAI
            return response()->json($response, 200);
        } catch (\Exception $e) {
            \Log::error('Chatbot error: ' . $e->getMessage());
            return response()->json(['error' => 'Something went wrong. Please try again later.'], 500);
        }
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
    

   
}
