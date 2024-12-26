<?php
// app/Http/Controllers/AgentController.php

namespace App\Http\Controllers;

use App\AIService;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Traits\HttpRequestTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;
use App\Models\Agent;
use App\Models\Client;
use App\Models\User;
use App\Models\Task;
use App\Models\Company;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\AgentsImport;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\DB;
use App\Models\InvoiceSequence;
use Illuminate\Support\Facades\Log;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\InvoiceDetail;
use Exception;

class MobileController extends Controller
{
    use HttpRequestTrait;

    private $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;        
    }

    public function login2(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        // If login is successful, return a JSON response
        return response()->json([
            'message' => 'Login successful. Proceed to 2FA.',
            'status' => 'success',
            'user' => Auth::user()
        ]);
    }

    public function verifytwofa(Request $request)
    {
        $google2fa = new Google2FA();

        // Validate the OTP input
        $validator = Validator::make($request->all(), [
            'secret' => 'required|string',
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'OTP is required'], 400);
        }


        $isValid = $google2fa->verifyKey($request->secret, $request->otp);

        if ($isValid) {
            // Mark 2FA as checked in the session or DB
            session(['2fa_checked' => true]);

            return response()->json([
                'message' => '2FA verification successful.',
                'status' => 'success',
            ], 200);
        }

        // If the OTP is incorrect
        return response()->json([
            'message' => 'Invalid 2FA code. Please try again.'
        ], 422);
    }



    public function agent()
    {
        return response()->json(Agent::all(), 200);
    }

    public function getAgentByUserId($userId)
    {
        // Fetch agent by user ID
        $agent = Agent::where('user_id', $userId)->first();

        if ($agent) {
            return response()->json($agent, 200);  // Return agent data if found
        } else {
            return response()->json(['message' => 'Agent not found'], 404);  // Return 404 if not found
        }
    }

    public function company()
    {
        return response()->json(Company::all(), 200);
    }

    public function task()
    {
        return response()->json(Task::all(), 200);
    }

    public function getTasksByAgentId($agentId)
    {
        $tasks = Task::where('agent_id', $agentId)
            ->get();

        return response()->json($tasks, 200);
    }

    public function getClientByAgentId($agentId)
    {
        // Retrieve agents where the 'user_id' column matches the provided userId
        $clients = Client::where('agent_id', $agentId)->get();

        // Check if any agents were found
        if ($clients->isEmpty()) {
            return response()->json(['message' => 'No clients found for this agent.'], 404);
        }

        // Return the agents as a JSON response with a 200 HTTP status
        return response()->json($clients, 200);
    }

    public function getTransactionByAgentId($agentId)
    {
        $transactions = DB::table('invoice_transaction_view')
            ->where('agent_id', $agentId)
            ->whereNotNull('transaction_amount')
            ->get();

        if ($transactions->isEmpty()) {
            return response()->json(['message' => 'No transactions found for this agent.'], 404);
        }

        return response()->json($transactions, 200);
    }


    public function getInvoiceByAgentId($agentId)
    {
        $invoices = Invoice::where('agent_id', $agentId)->get();
        if ($invoices->isEmpty()) {
            return response()->json(['message' => 'No invoices found for this agent.'], 404);
        }
        return response()->json($invoices, 200);
    }


    public function client()
    {
        return response()->json(Client::all(), 200);
    }


    public function store(Request $request)
    {
        $tasks = $request->input('tasks');
        $amount = $request->input('total');
        $clientId = $request->input('clientId');
        $currency = $request->input('currency');
        $agentId = Agent::where('user_id', Auth::id())->first() ? Agent::where('user_id', Auth::id())->first()->id : null;

        // Create a new invoice
        try {
            $invoiceSequence = InvoiceSequence::lockForUpdate()->first();

            if (!$invoiceSequence) {
                // If no sequence exists yet, create one
                $invoiceSequence = InvoiceSequence::create(['current_sequence' => 1]);
            }

            // Generate the new invoice number
            $currentSequence = $invoiceSequence->current_sequence;
            $invoiceNumber = $this->generateInvoiceNumber($currentSequence);

            // Increment the sequence number
            $invoiceSequence->current_sequence++;
            $invoiceSequence->save();

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'client_id' => $clientId,
                'agent_id' => $agentId,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'unpaid',
            ]);

            if (is_array($tasks) && !empty($tasks)) {
                foreach ($tasks as $task) {
                    try {
                        // Try to create each invoice detail
                        InvoiceDetail::create([
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoiceNumber,
                            'task_id' => $task['taskId'],
                            'task_description' => $task['taskName'],
                            'task_remark' => $task['remark'],
                            'task_price' => $task['price'],
                        ]);
                    } catch (Exception $e) {
                        // Log the error if something goes wrong with a specific task
                        Log::error('Failed to create InvoiceDetails: ' . $e->getMessage());
                        return response()->json(['error' => 'Failed to create InvoiceDetails for task: ' . $task['taskName']], 500);
                    }
                }
            }

            // Return the invoice number in the response
            return response()->json([
                'status' => 'success',
                'invoice_number' => $invoiceNumber // Return the generated invoice number
            ]);
        } catch (Exception $e) {
            // Handle exceptions
            return response()->json(['error' => 'Invoice creation failed!'], 500);
        }
    }

    private function generateInvoiceNumber($sequence)
    {
        $year = now()->year;
        return sprintf('INV-%s-%05d', $year, $sequence);
    }

    public function new()
    {
        $agents = Agent::all();

        return view('agentsNew', compact('agents'));
    }

    public function show($id)
    {
        $agent = Agent::find($id);
        // return view('agentsShow', compact('agent'));
        $pendingTasks = Task::where('agent_id', $agent->id)->where('status', 'pending')->get();
        return view('agentsShow', compact('agent', 'pendingTasks'));
    }

    public function edit($id)
    {
        $agent = Agent::find($id);
        $companies = Company::all();

        return view('agentsEdit', compact('agent', 'companies'));
    }


    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone_number' => 'required|string',
            'company_id' => 'required',
            'type' => 'required'
        ]);

        // Create a new user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make('citytour123'),
        ]);

        // Create a new agent associated with the user
        $agent = new Agent([
            'user_id' => $user->id,
            'company_id' => $request->company_id,
            'type' => $request->type,
            'email' => $request->email,
            'name' => $request->name,
            'phone_number' => $request->phone_number,
        ]);
        $agent->save();

        return redirect()->route('agents.index')->with('success', 'Agent updated successfully');
    }


    public function upload()
    {
        $agents = Agent::all();

        return view('agentsUpload', compact('agents'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|mimes:xlsx',
        ]);

        Excel::import(new AgentsImport, $request->file('excel_file'));

        return redirect()->back()->with('success', 'Agents imported successfully.');
    }

    // THREAD
    public function createThreadRun(string $assistantId, User $user)
    {
        if ($assistantId == null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Assistant ID is required',
            ]);
        }

        $url = config('services.open-ai.url') . '/threads/runs';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];
        $data = [
            'assistant_id' => $assistantId,
            'additional_instructions' => 'Address the user as' . $user->name . ', but you dont need to call his name every time you respond.',
            'metadata' => [
                'user_id' => (string) $user->id,
            ],
        ];

        $response = $this->postRequest($url, $header, json_encode($data));

        logger('create thread run response: ', $response);

        if (isset($response['id'])) {
            return [
                'status' => 'success',
                'message' => 'Thread run created successfully',
                'data' => $response,
            ];
        } else {

            return [
                'status' => 'error',
                'message' => 'Failed to create thread run',
                'data' => $response,
            ];
        }
    }

    public function createThread(User $user)
    {
        return $this->aiService->createThread($user);
    }

    public function retrieveThread($threadId)
    {
        return $this->aiService->retrieveThread($threadId);
    }

    public function deleteThread(string $threadId)
    {
        return $this->aiService->deleteThread($threadId);
    }

    // MESSAGE
    public function createMessage(string $threadId, string $message, array $functions = [], bool $isFunctionResponse = false)
    {
        return $this->aiService->createMessage($threadId, $message, $functions, $isFunctionResponse);
    }

    public function getMessages(string $threadId, string $assistantId, User $user)
    {
        return $this->aiService->getMessages($threadId, $assistantId, $user);
    }

    // RUN
    public function createRun(string $assistantId, string $threadId, User $user)
    {
        return $this->aiService->createRun($assistantId, $threadId, $user);
    }

    public function checkRun(string $threadId, string $runId)
    {
        return $this->aiService->checkRun($threadId, $runId);
    }

    public function listRun($threadId)
    {
        return $this->aiService->listRun($threadId);
    }

    public function cancelRun($threadId, $runId)
    {
        return $this->aiService->cancelRun($threadId, $runId);
    }

    // RUN STEP
    public function listStep(string $threadId, string $runId)
    {
        return $this->aiService->listStep($threadId, $runId);
    }

    public function retrieveStep(string $threadId, string $runId, string $stepId)
    {
        return $this->aiService->retrieveStep($threadId, $runId, $stepId);
    }

    public function sendMessage(Request $request)
    {
        $openAiController = new OpenAiController($this->aiService);

        $response = $openAiController->askOpenAi($request->input('prompt'), $request->input('user_id'));

        return response()->json($response);
    }
}

