<?php
// app/Http/Controllers/AgentController.php

namespace App\Http\Controllers;

use App\AIService;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Traits\HttpRequestTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Http\Traits\NotificationTrait;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\Account;
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
use App\Models\JournalEntry;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\KnowledgeBase;
use App\Models\Role;
use DateTime;
use Exception;
use PDO;

class MobileController extends Controller
{
    use NotificationTrait;
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
        $tasks = Task::with('invoiceDetail')->get()->map(function ($task) {
            $task->is_invoiced = $task->invoiceDetail()->exists();
            return $task;
        });
    
        return response()->json($tasks, 200);
    }

    public function getTasksByAgentId($agentId)
    {
        $tasks = Task::where('agent_id', $agentId)
            ->with('invoiceDetail')
            ->get()
            ->map(function ($task) {
                $task->is_invoiced = $task->invoiceDetail()->exists();
                return $task;
            });
    
        return response()->json($tasks, 200);
    }
    

    public function taskPending()
    {
        $tasks = Task::whereDoesntHave('invoiceDetail')->get();
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
        $invoices = Invoice::with([
            'client',
            'agent',
            'invoiceDetails.task', // Nested eager loading for task
            'invoicePartials'
        ])->
        where('agent_id', $agentId)->get();
        if ($invoices->isEmpty()) {
            return response()->json(['message' => 'No invoices found for this agent.'], 404);
        }
        return response()->json($invoices, 200);
    }

    public function getInvoiceById($Id)
    {
        // Load the invoice with all necessary relationships, including nested relationships
        $invoice = Invoice::with([
            'client',
            'agent',
            'invoiceDetails.task', // Nested eager loading for task
            'invoicePartials'
        ])->find($Id);
    
        // Check if the invoice exists
        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found.'], 404);
        }
    
        // Return the invoice with all relationships as a JSON response
        return response()->json($invoice, 200);
    }


    public function client()
    {
        return response()->json(Client::all(), 200);
    }

    public function create(Request $request)
    {
        $taskIds = $request->query('task_ids', ''); // Comma-separated task IDs

        if (gettype($taskIds) == 'string') {
            $taskIdsArray = explode(',', $taskIds); // Multiple tasks
        } else {
            $taskIdsArray = $taskIds; // Single task
        }

        $selectedTasks = Task::with('invoiceDetail.invoice')->whereIn('id', $taskIdsArray)->get();

        foreach ($selectedTasks as $task) {
            if ($task->invoiceDetail) {
                return response()->json(['error' => 'Task already invoiced!'], 400);
            }
        }

        if ($request->input('user_id') != null) {
            $user = User::find($request->input('user_id'));
        } else {
            $user = Auth::user();
        }

        $agents = collect();
        $clients = collect();
        $branches = collect();
        $company = null;

        if ($user->role_id == Role::COMPANY) {
            $company = $user->company;
            $company = Company::with('branches.agents')->find($company->id);
            $agents = $company->branches->flatMap->agents;
            $clients = $agents->flatMap->clients;
            $branches = $company->branches;
        } elseif ($user->role_id == Role::AGENT) {
            $agent = $user->agent;
            $company = $agent->branch->company;
            $agents = $company->branches->flatMap->agents;
            $clients = $agents->flatMap->clients;
            $branches = $company->branches;
        }

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
            'title' => 'Invoice ' . $invoiceNumber . ' Created By ' . $user->name,
            'message' => 'Invoice ' . $invoiceNumber . ' has been created.'
        ]);

        $clientIds = $selectedTasks->pluck('client_id')->unique();
        $agentIds =  $selectedTasks->pluck('agent_id')->unique();
        $selectedAgent = Agent::find($agentIds->first());
        $selectedClient = $clientIds->count() >= 1 ? Client::find($clientIds->first()) : null;

        $agentId = $selectedAgent ? $selectedAgent->id : ($user->role_id == Role::COMPANY
            ? $agents->pluck('id')->toArray()
            : $user->agent->id);

        $tasks = $agentId
            ? Task::with('agent.branch')
            ->whereIn('agent_id', (array)$agentId)
            ->get()
            ->map(function ($task) {
                $task->agent_name = $task->agent->name ?? null;
                $task->branch_name = $task->agent->branch->name ?? null;
                $task->supplier_name = $task->supplier->name ?? null;
                return $task;
            })
            : collect();

        $suppliers = Supplier::all();
        $paymentGateways = ['Tap', 'Hesabe', 'MyFatoorah'];
        $todayDate = Carbon::now()->format('Y-m-d');
        $appUrl = config('app.url');

        return response()->json([
            'clients' => $clients,
            'agents' => $agents,
            'branches' => $branches,
            'agentId' => $agentId,
            'clientId' => $selectedClient?->id,
            'tasks' => $tasks,
            'company' => $company,
            'suppliers' => $suppliers,
            'invoiceNumber' => $invoiceNumber,
            'selectedTasks' => $selectedTasks,
            'selectedAgent' => $selectedAgent,
            'selectedClient' => $selectedClient,
            'paymentGateways' => $paymentGateways,
            'todayDate' => $todayDate,
            'appUrl' => $appUrl
        ]);
    }


    public function savePartial(Request $request)
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

    public function removePartial(Request $request)
    {
        $request->validate([
            'invoiceId' => 'required',
            'invoiceNumber' => 'required|string',
        ]);

        $invoiceId = $request->input('invoiceId');
        $invoiceNumber = $request->input('invoiceNumber');

        try {
            // Find the invoice partial to be deleted
            $invoicePartial = InvoicePartial::where('invoice_id', $invoiceId)
                ->first();

            // Check if the partial exists
            if (!$invoicePartial) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice partial not found!',
                ]);
            }

            // Delete the invoice partial
            $invoicePartial->delete();

            return response()->json([
                'success' => true,
                'message' => 'Invoice partial removed successfully!',
                'invoiceId' => $invoiceId,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to remove InvoicePartial: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove invoice partial!',
            ]);
        }
    }



    public function store(Request $request)
    {

        $request->validate([
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|integer',
            'tasks.*.description' => 'required|string',
            'tasks.*.remark' => 'nullable|string',
            'tasks.*.price' => 'required|numeric',
            'tasks.*.supplier_id' => 'required|integer',
            'tasks.*.client_id' => 'required|integer',
            'tasks.*.agent_id' => 'required|integer',
            'invdate' => 'required|date',
            'duedate' => 'required|date',
            'subTotal' => 'required|numeric',
            'clientId' => 'required|integer',
            'agentId' => 'required|integer',
            'invoiceNumber' => 'required|string',
            'currency' => 'required|string',
        ]);


        $tasks = $request->input('tasks');
        $duedate = $request->input('duedate');
        $invdate = $request->input('invdate');
        $amount = $request->input('subTotal');
        $clientId = $request->input(key: 'clientId');
        $agentId =  $request->input(key: 'agentId');
        $invoiceNumber = $request->input(key: 'invoiceNumber');
        $currency = $request->input('currency');


        $agent = Agent::where('id', operator: $agentId)->first();
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
                'agent_id' => $agentId,
                'client_id' => $clientId,
                'sub_amount' => $amount,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'unpaid',
                'invoice_date' => $invdate,
                'due_date' => $duedate,
                'payment_type' => 'full',
            ]);

            if (!empty($tasks)) {
                foreach ($tasks as $task) {
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
                            'task_description' => $task['description'],
                            'task_remark' => $task['remark'],
                            'client_notes' => $task['note'],
                            'task_price' =>  $task['price'],
                            'supplier_price' => $selectedtask->total,
                            'markup_price' => $task['price'] - $selectedtask->total,
                            'paid' => false,
                        ]);

                        $transaction = Transaction::create([
                            'branch_id' => $branchId,
                            'entity_id' => $companyId,
                            'entity_type' => 'company',
                            'transaction_type' => 'credit',
                            'amount' =>  $task['price'],
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
                            'description' => 'Payment: ' . $supplier->name,
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
                            'description' => 'Payment received from: ' . $client->name,
                            'debit' => 0,
                            'credit' => $task['price'],
                            'balance' => $task['price'],
                            'name' =>  $client->name,
                            'type' => 'receivable',
                            'type_reference_id' => $client->id
                        ]);



                        $markup = $task['price'] - $selectedtask->total;
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

            return response()->json([
                'success' => true,
                'message' => 'Invoice created successfully!',
                'invoiceId' => $invoice->id,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to create InvoiceDetails: ' . $e->getMessage());
            return response()->json('Invoice creation failed!');
        }
    }

    public function updateInvoice(Request $request)
    {
        $request->validate([
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|integer',
            'tasks.*.description' => 'required|string',
            'tasks.*.invprice' => 'required|numeric',
            'tasks.*.supplier_id' => 'required|integer',
            'tasks.*.client_id' => 'required|integer',
            'tasks.*.agent_id' => 'required|integer',
            'invdate' => 'required|date',
            'duedate' => 'required|date',
            'subTotal' => 'required|numeric',
            'clientId' => 'required|integer',
            'agentId' => 'required|integer',
            'invoiceNumber' => 'required|string',
            'currency' => 'required|string',
        ]);
    
        $tasks = $request->input('tasks');
        $duedate = $request->input('duedate');
        $invdate = $request->input('invdate');
        $amount = $request->input('subTotal');
        $clientId = $request->input('clientId');
        $agentId = $request->input('agentId');
        $invoiceNumber = $request->input('invoiceNumber');
        $currency = $request->input('currency');
    
        $agent = Agent::where('id', $agentId)->first();
        $companyId = $agent && $agent->branch && $agent->branch->company ? $agent->branch->company->id : null;
        $branchId = $agent ? $agent->branch_id : null;
    
        try {
            // 🔹 Find the existing invoice
            $invoice = Invoice::where('invoice_number', $invoiceNumber)->first();
    
            if (!$invoice) {
                return response()->json(['error' => 'Invoice not found.'], 404);
            }
    
            // 🔹 Delete related records before updating
            InvoiceDetail::where('invoice_id', $invoice->id)->delete();
            Transaction::where('invoice_id', $invoice->id)->delete();
            JournalEntry::where('invoice_id', $invoice->id)->delete();
    
            // 🔹 Update invoice
            $invoice->update([
                'agent_id' => $agentId,
                'client_id' => $clientId,
                'sub_amount' => $amount,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'unpaid',
                'invoice_date' => $invdate,
                'due_date' => $duedate,
            ]);
    
            // 🔹 Re-insert related records
            foreach ($tasks as $task) {
                try {
                    $selectedtask = Task::where('id', $task['id'])->first();
                    $supplier = Supplier::where('id', $task['supplier_id'])->first();
                    $client = Client::where('id', $task['client_id'])->first();
                    $agent = Agent::where('id', $task['agent_id'])->first();
    
                    // Create new InvoiceDetail
                    $invoiceDetail = InvoiceDetail::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoiceNumber,
                        'task_id' => $task['id'],
                        'task_description' => $task['description'],
                        'task_remark' => $task['remark'] ?? null,
                        'client_notes' => $task['client_notes'] ?? null,
                        'task_price' =>  $task['invprice'],
                        'supplier_price' => $selectedtask->total,
                        'markup_price' => $task['invprice'] - $selectedtask->total,
                        'paid' => false,
                    ]);
    
                    // Create a new Transaction
                    $transaction = Transaction::create([
                        'branch_id' => $branchId,
                        'entity_id' => $companyId,
                        'entity_type' => 'company',
                        'transaction_type' => 'credit',
                        'amount' =>  $task['invprice'],
                        'description' => 'Invoice:' . $invoiceNumber . ' Updated',
                        'invoice_id' => $invoice->id,
                        'reference_type' => 'Invoice',
                    ]);
    
                    // Update General Ledger Entries
                    JournalEntry::create([
                        'transaction_id' => $transaction->id,
                        'branch_id' => $branchId,
                        'company_id' => $companyId,
                        'invoice_id' =>  $invoice->id,
                        'account_id' =>  $supplier->id, // Example: assign supplier account
                        'invoiceDetail_id' =>  $invoiceDetail->id,
                        'transaction_date' => Carbon::now(),
                        'description' => 'Updated Payment: ' . $supplier->name,
                        'debit' => $selectedtask->total,
                        'credit' => 0,
                        'balance' => $selectedtask->total,
                        'name' => $supplier->name,
                        'type' => 'payable',
                    ]);
    
                    JournalEntry::create([
                        'transaction_id' => $transaction->id,
                        'branch_id' => $branchId,
                        'company_id' => $companyId,
                        'invoice_id' =>  $invoice->id,
                        'account_id' =>  $client->id, // Example: assign client account
                        'invoiceDetail_id' =>  $invoiceDetail->id,
                        'transaction_date' => Carbon::now(),
                        'description' => 'Updated Payment received from: ' . $client->name,
                        'debit' => 0,
                        'credit' => $task['invprice'],
                        'balance' => $task['invprice'],
                        'name' =>  $client->name,
                        'type' => 'receivable',
                    ]);
    
                    // Update Task Status
                    $selectedtask->status = 'Assigned';
                    $selectedtask->save();
                } catch (Exception $e) {
                    Log::error('Failed to update InvoiceDetails: ' . $e->getMessage());
                    return response()->json('Failed to update InvoiceDetails for task: ' . $task['description'], 500);
                }
            }
    
            return response()->json([
                'success' => true,
                'message' => 'Invoice updated successfully!',
                'invoiceId' => $invoice->id,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to update invoice: ' . $e->getMessage());
            return response()->json('Invoice update failed!', 500);
        }
    }
    

    public function deleteInvoice(Request $request, string $id)
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found!');
        }

        try {
            InvoiceDetail::where('invoice_id', $invoice->id)->delete();
            InvoicePartial::where('invoice_id', $invoice->id)->delete();
            JournalEntry::where('invoice_id', $invoice->id)->delete();
            Transaction::where('invoice_id', $invoice->id)->delete();

             $invoice->delete();

             return redirect()->route('invoices.index')->with('status', 'Invoice deleted successfully!');

        } catch (Exception $error) {
            logger('Failed to delete invoice: ' . $error->getMessage());
            return redirect()->back()->with('error', 'Failed to delete invoice!');
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

    public function modifyAssistant($assistantId)
    {
        $knowledgeBaseEntries = KnowledgeBase::select('topic', 'content')->get();

        $instruction = 'You are an assistant in a travel agency system. You will learn everything about this system and help users to get the information they need. You can ask for help if you need it. Below are the features that exist in the system:';

        foreach ($knowledgeBaseEntries as $entry) {
            $instruction .= $entry->topic . ': ' . $entry->content . '.';
        }

        $instruction .= 'You will use this information to help users managed their business in the travel agency system.\nYou will also help users to get the information they need.';

        $instruction .= 'For Example: \n\nUser: How many pending task do i have for today? \nAssistant: You have 3 pending tasks for today.';

        $instruction .= 'User: How much is the total amount of my invoices? \nAssistant: The total amount of your invoices is $1000.';

        $instruction .= 'User: Can you show me the details of my invoice? \nAssistant: Sure, here are the details of your invoice.';

        $instruction .= 'User: Please create invoice for client A. \nAssistant: Sure, I will create invoice for client A.';

        $instruction .= 'You also will use tools that exist such as functions to help you to get the information you need and to perform the task.';

        $data = [
            'description' => 'Travel Agency Assistant',
            'name' => 'Travel Agency Assistant',
            'instructions' => $instruction,
        ];

        return $this->aiService->modifyAssistant($assistantId, $data);
    }

    public function getUserTask($userId, Request $request)
    {
        $user = User::find($userId);
        $arguments = $request->input('arguments');

        $dateFrom = date('Y-m-d H:i:s', strtotime($arguments['date_from']));
        $dateTo = date('Y-m-d H:i:s', strtotime($arguments['date_to']));

        // $dateFrom = $arguments['date_from'];
        // $dateTo = $arguments['date_to'];
        return [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ];

        $agents = Agent::with(['branch' => function ($query) use ($user) {
            $query->where('company_id', $user->company_id);
        }])->get();

        // $clients = Client::whereIn('agent_id', $agents->pluck('id'))->get();

        // Get all agents for this company
        $agentIds = $agents->pluck('id'); // Get all agents for this company

        $tasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice')->whereIn('agent_id', $agentIds)
            ->where('created_at', '>=', $dateFrom)
            ->where('created_at', '<=', $dateTo)
            ->get(); // Retrieve tasks for this company

        if (isset($arguments['task_status'])) {
            $tasks = $tasks->where('status', $arguments['task_status']);
        }

        if (isset($arguments['task_output'])) {
            $tasks = $arguments['task_output'] == 'list' ? $tasks : $tasks->count();
            return (string)$tasks;
        }
        return response()->json($tasks);
    }

    public function getInvoices(int $userId)
    {
        $user = User::find($userId);

        if ($user->role_id == Role::ADMIN) {
            return Invoice::with('invoiceDetails', 'invoicePartials')->get()->select('invoice_number', 'client_id', 'agent_id', 'amount', 'status', 'invoice_date', 'paid_date', 'due_date', 'invoiceDetails')->toArray();
        } else if ($user->role_id == Role::COMPANY) {

            $agentsId = Agent::with(['branch' => function ($query) use ($user) {
                $query->where('company_id', $user->company_id);
            }])->get()->pluck('id');
            return Invoice::with('invoiceDetails', 'invoicePartials')->get()->select('invoice_number', 'client_id', 'agent_id', 'amount', 'status', 'invoice_date', 'paid_date', 'due_date', 'invoiceDetails', 'invoicePartials')->whereIn('agent_id', $agentsId)->toArray();
        } else if ($user->role_id == Role::AGENT) {
            return Invoice::with('invoiceDetails', 'invoicePartials')->get()->select('invoice_number', 'client_id', 'agent_id', 'amount', 'status', 'invoice_date', 'paid_date', 'due_date', 'invoiceDetails', 'invoicePartials')->where('agent_id', $user->agent->id)->toArray();
        } else {
            return [];
        }
    }
}
