<?php

namespace App\Http\Controllers;

use App\Http\Traits\NotificationTrait;
use App\Models\Account;
use App\Models\Agent;
use Illuminate\Support\Facades\Auth;
use App\Models\Client;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\InvoicePartial;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\InvoiceDetail;
use App\Models\Task;
use Exception;
use Illuminate\Http\Request;
use App\Models\InvoiceSequence;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Credit;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redirect;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceController extends Controller
{
    use NotificationTrait;
    use NotificationTrait;

    public function index()
    {
 
        $user = Auth::user();

       // Gate::authorize('viewAny', Invoice::class);

        // Get all agents under the company
        if($user->role_id == Role::ADMIN){
            $agents = Agent::with(['branch'])->get();
        } else if($user->role_id == Role::COMPANY){
            $agents = Agent::with(['branch' => function ($query) use ($user) {
                $query->where('company_id', $user->company->id);
            }])->get();
            $companyId = $user->company->id;
        } else if($user->role_id == Role::AGENT){
            $agents = Agent::with('branch')->where('id', $user->agent->id)->get();
            $companyId = $user->agent->branch->company_id;
        } else {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $agentIds = $agents->pluck('id');
        // Get invoices related to those agents
        // $invoices = Invoice::with('agent.branch','invoiceDetails.task','client')->whereIn('agent_id', $agentIds)->paginate(500);
     
        $invoices = Invoice::with([
            'agent.branch', 
            'invoiceDetails.task.supplier',
            // 'invoiceDetails.task.hotelDetails.room', 
            'client'
        ])
        ->whereIn('agent_id', $agentIds)
        ->whereHas('agent.branch', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })
        ->paginate(500);
    // Get clients related to the agents
        $clients = Client::whereIn('agent_id', $agentIds)->get();
      
        // Get tasks related to the agents
        $tasks = Task::whereIn('agent_id', $agentIds)->get();

        $suppliers = Supplier::all();
        $branches = $user->role_id == Role::ADMIN ? Branch::all() : Branch::where('company_id', $companyId)->get();
        $types = Task::distinct()->pluck('type');
        $totalInvoices = $invoices->total();

        return view('invoice.index', compact('invoices', 'types', 'suppliers','branches', 'agents', 'clients', 'tasks', 'totalInvoices'));
    }

    public function salelist()
    {
        $user = Auth::user();

        // Ensure that the user is a company
        if ($user->role_id !== Role::COMPANY) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        // Get all agents under the company
        $agents = Agent::with(['branch' => function ($query) use ($user) {
            $query->where('branch_id', $user->company->branch->id);
        }])->pluck('id');

        // Get invoices related to those agents
        $invoices = Invoice::where('status', 'paid')->with('agent.branch', 'client')->whereIn('agent_id', $agents)->paginate(10);

        // Get clients related to the agents
        $clients = Client::whereIn('agent_id', $agents)->get();

        // Get tasks related to the agents
        $tasks = Task::whereIn('agent_id', $agents)->get();

        $totalInvoices = $invoices->total();

        return view('invoice.salelist', compact('invoices', 'clients', 'tasks', 'totalInvoices'));
    }


    public function create(Request $request)
    {   
        if (auth()->user()->role_id == Role::ADMIN) {
            return view('invoice.maintenance'); // Show the maintenance page
        }

        $taskIds = $request->query('task_ids', ''); // Comma-separated task IDs
        $taskIdsArray = [];

        $disableButtons = false;

        if (!empty($taskIds)) {
            if (gettype($taskIds) == 'string') {
                $taskIdsArray = explode(',', $taskIds); // Multiple tasks
            } else {
                $taskIdsArray = $taskIds; // Single task
            }
        
            foreach ($taskIdsArray as $taskId) {
                $task = Task::find($taskId);
        
                if (!$task) {
                    return Redirect::route('tasks.index')->with('error', 'Task not found!');
                }
        
                if (!$task->is_complete) {
                    return Redirect::route('tasks.index')->with('error', 'Task does not have full information!');
                }
            }

            $disableButtons = true;
        }
        $taskIdsArray = array_map('intval', $taskIdsArray);
        $taskIdsArray = Arr::flatten($taskIdsArray);
        
        if (count($taskIdsArray) !== count(Arr::flatten($taskIdsArray, 1))) {
            throw new InvalidArgumentException('Nested arrays may not be passed to whereIn method.');
        }

        $tasks = Task::with('supplier', 'agent.branch', 'invoiceDetail.invoice', 'flightDetails.countryFrom', 'flightDetails.countryTo', 'hotelDetails.hotel');
       
        $selectedTasks = (clone $tasks)->whereIn('id', $taskIdsArray)->get();
        
        foreach ($selectedTasks as $task) {
            if ($task->invoiceDetail) {
                return Redirect::route('invoice.edit', ['invoiceNumber' => $task->invoiceDetail->invoice->invoice_number]);                    
            }
            
            //check miss data
            if($task->flightDetails) {
                if (!isset($task->flightDetails->country_id_to) || !isset($task->flightDetails->country_id_from)) {
                    return redirect()->back()->with('error', 'The task record is missing important flight data.');
                }
            }
            if($task->hotelDetails) {
                //dd($task->hotelDetails->hotel->id);
                if (!isset($task->hotelDetails->hotel)) {
                    return redirect()->back()->with('error', 'The task record is missing important hotel data.');
                }                
            }

        }

        $selectedTasks = $selectedTasks->map(function ($task) {
            $task->agent_name = $task->agent->name ?? null;
            $task->branch_name = $task->agent->branch->name ?? null;
            $task->supplier_name = $task->supplier->name ?? null;
            return $task;
        });

        if ($request->input('user_id') != null) {
            $user = User::find($request->input('user_id'));
        } else {
            $user = Auth::user();
        }

        $selectedCompany = null;
        $agents = collect();
        $clients = collect();

        if($user->role_id == Role::ADMIN){
            $agents = Agent::get();
            $clients = Client::get();
            $branches = Branch::get();
            $companies = Company::get();
            
        }elseif ($user->role_id == Role::COMPANY) {
            
            $company = Company::with('branches.agents')->find($user->company->id);
            $agents = $company->branches->flatMap->agents;
            $clients = $agents->flatMap->clients;
            $branches = $company->branches;
            $selectedCompany = $company;

        } elseif ($user->role_id == Role::AGENT) {
            $agent = $user->agent;
            $company = $agent->branch->company;
            $agents = $company->branches->flatMap->agents;
            $clients = $agents->flatMap->clients;
            $branches = $company->branches;
            $selectedCompany = $company;
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

        // Fetch tasks
        // Handle client association
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

       $payments = Payment::whereIn('agent_id', $agents->pluck('id'))
                ->where('invoice_id', null)
                ->get();
         
        // if selected agent is null, get all agents under the company if the user is a company, if not get the agent data from the user
        // $agentId =  $selectedAgent == null ? $user->role_id == Role::COMPANY ? $agentsId = array_map(function ($agent) {
        //     return $agent['id'];
        // }, $agents->toArray()) : $user->agent->id : $selectedAgent->id;

        if($user->role_id == Role::ADMIN){
            $agentId = Agent::get()->pluck('id');
        } else if($user->role_id == Role::COMPANY){
            $agentId = $user->company->branches->flatMap->agents->pluck('id');
        } else if($user->role_id == Role::BRANCH){
            $agentId = $user->branch->agents->pluck('id');
        } else if($user->role_id == Role::AGENT){
            $agentId = (array)$user->agent->id;
        } else {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }
        $agentId = $selectedAgent ? $selectedAgent->id : $agentId;
        $agentId = is_array($agentId) ? $agentId : [$agentId];
        $clientId = $selectedClient ? $selectedClient->id : null;
        // Log::info('agentId', ['agentId' => $agentId]);
        // dd(gettype($agentId));
        $tasks = $agentId
            ? (clone $tasks)
            ->whereIn('agent_id', $agentId)
            ->get()
            ->filter(function ($task) {
                // Filter out tasks that already have an invoice detail
                return !$task->invoiceDetail;
            })
            ->map(function ($task) {
                $task->agent_name = $task->agent->name ?? null;
                $task->branch_name = $task->agent->branch->name ?? null;
                $task->supplier_name = $task->supplier->name ?? null;
                $task->quantity = 1;
                return $task;
            })
            : collect();
        // Log::info('tasks', ['tasks' => $tasks]);

        //dd($task->flightDetails->countryTo);

        $suppliers = Supplier::all();
        $paymentGateways = ['Tap', 'Hesabe', 'MyFatoorah'];
        $todayDate = Carbon::now()->format('Y-m-d');

        $appUrl = config('app.url');
        return view('invoice.create', compact(
            'clients',
            'agents',
            'branches',
            'agentId',
            'clientId',
            'tasks',
            'suppliers',
            'invoiceNumber',
            'selectedTasks',
            'selectedAgent',
            'selectedClient',
            'selectedCompany',
            'paymentGateways',
            'todayDate',
            'appUrl',
            'disableButtons',
            'payments'
        ));
    }


    public function edit(string $invoiceNumber)
    {
        $user = Auth::user();
        $agents = collect();
        $branches = collect();
        if ($user->role_id == Role::ADMIN) {
            return view('invoice.maintenance'); // Show the maintenance page
        } elseif ($user->role_id == Role::COMPANY) {
            $company = $user->company;
            $company = Company::with('branches.agents')->find($company->id);
            $agents = $company->branches->flatMap->agents;
            $branches = $company->branches;
        } elseif ($user->role_id == Role::AGENT) {
            $agent = $user->agent;
            $company = $agent->branch->company;
            $agents = $company->branches->flatMap->agents;
            $branches = $company->branches;
        }

        // Retrieve the invoice based on the invoice number
        $invoice = Invoice::where('invoice_number', $invoiceNumber)->with('agent.branch.company', 'client', 'invoiceDetails.task')->first();
        // Check if the invoice exists
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found!');
        }

        if($invoice->status == 'paid'){
            return redirect()->route('invoices.index')->with('error', 'Cannot edit a paid invoice!');
        }

        $clients = Client::with(['agent.branch' => function ($query) {
            if (auth()->user()->role_id == Role::COMPANY) {
                $companyId = auth()->user()->company->id;
            } elseif (auth()->user()->role_id == Role::AGENT) {
                $companyId = auth()->user()->agent->branch->company_id;
            }
            $query->where('company_id', $companyId);
        }])->get();

        $invoiceDetails = $invoice->invoiceDetails;
        $agentId = $invoice->agent_id;
        $clientId = $invoice->client_id;
        $tasks = $agents->flatMap->tasks->map(function ($task) {
            $task->agent_name = $task->agent->name ?? null; // Add agent_name dynamically
            $task->branch_name = $task->agent->branch->name ?? null; // Add branch_name dynamically
            return $task;
        });
        $selectedTasks = $invoice->invoiceDetails
        ->filter(fn($invoiceDetail) => $invoiceDetail->task) // Remove null tasks
        ->map(function ($invoiceDetail) use ($invoice) {
            $task = $invoiceDetail->task;
            $task->agent_name = optional($task->agent)->name;
            $task->branch_name = optional(optional($task->agent)->branch)->name;
            $task->task_price = $invoiceDetail->task_price;
            $task->invprice = (float) $invoice->amount;
            return $task;
        });
    
        $selectedAgent = $invoice->agent;
        $selectedClient = $invoice->client;
        //dd('testing',$clients);

        $suppliers = Supplier::all();
        $paymentGateways = ['Tap', 'Hesabe', 'MyFatoorah'];
        $invoiceDate = $invoice->invoice_date;
        $invprice = $invoice->amount;
        $dueDate =  $invoice->due_date;

        $appUrl = config('app.url');

        // Check if the credit has been used for this invoice
        $creditUsed = Credit::where('client_id', $invoice->client_id)
            ->where('invoice_id', $invoice->id)
            ->first();

        return view('invoice.edit', compact(
            'clients',
            'invoice',
            'agents',
            'branches',
            'agentId',
            'clientId',
            'tasks',
            'company',
            'suppliers',
            'invoiceNumber',
            'selectedTasks',
            'selectedAgent',
            'selectedClient',
            'paymentGateways',
            'invoiceDate',
            'invprice',
            'dueDate',
            'appUrl',
            'creditUsed'
        ));
    }


    public function savePartial(Request $request)
    {
        $request->validate([
            'invoiceId' => 'required',
            'date' => 'nullable',
            'clientId' => 'required',
            'amount' => 'required',
            'type' => 'required|string',
            'invoiceNumber' => 'required|string',
            'gateway' => 'required|string',
            'credit' => 'nullable|boolean'
        ]);

        $invoiceId = $request->input('invoiceId');
        $invoiceNumber = $request->input('invoiceNumber');
        $clientId = $request->input('clientId');
        $type = $request->input('type');
        $date = $request->input('date');
        $amount = $request->input('amount');
        $gateway = $request->input('gateway');
        $credit = $request->input('credit', false); // Default to false if not provided

        $invoice = Invoice::where('invoice_number', $invoiceNumber)->with('agent.branch.company', 'client', 'invoiceDetails.task')->first();

        $client = Client::find($clientId);
        $balanceCredit = Credit::getTotalCreditsByClient($clientId);
        //dd($credit, $balanceCredit);
        if ($credit) {
            if ($amount > $balanceCredit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client credit is not enough!',
                ]);
            }
        }

        try {

            $invoicepartial = InvoicePartial::create([
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'client_id' => $clientId,
                'amount' => $amount,
                'status' => $credit ? 'paid' : 'unpaid',
                'expiry_date' => $date,
                'type' => $type,
                'payment_gateway' => $gateway,
            ]);

            $invoice->payment_type = $type;
            $invoice->status = $credit ? 'paid' : 'unpaid';
            $invoice->is_client_credit = $credit;
            $invoice->save();

        } catch (Exception $e) {
            Log::error('Failed to create InvoiceDetails: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create invoice!',
            ]);
        }


        if ($invoice->is_client_credit == true) {
            // $newAmountCredit = $client->credit - $amount;
            // $clientController = new ClientController();
            // $clientController->updateCredit($clientId, $newAmountCredit);
            // Create Credit Record
            try{
                $creditSubmit = Credit::create([
                    'company_id'  => $invoice->client->agent->branch->company_id,
                    'client_id'   => $clientId,
                    'type'        => 'Invoice',
                    'description' => 'Payment by Credit: ' . $invoice->invoice_number,
                    'amount'      => -($invoice->amount),
                ]);
            } catch (Exception $e) {
                Log::error('Failed to create Credit: ' . $e->getMessage());
                return response()->json('Something Went Wrong', 500);
            }

        }

        $invoiceDetail = InvoiceDetail::where('invoice_id', $invoiceId)->first();
        $tasksId = $invoice->invoiceDetails->pluck('task_id')->toArray();

        $tasks = Task::with('invoiceDetail','agent')->whereIn('id', $tasksId)->get();

        DB::beginTransaction();
        try {
            $transaction = Transaction::create([
                'company_id' => $tasks[0]->company_id,
                'branch_id' => $tasks[0]->agent->branch_id,
                'entity_id' => $tasks[0]->company_id,
                'entity_type' => 'company',
                'transaction_type' => 'credit',
                'amount' =>  $invoice->amount,
                'date' => Carbon::now(),
                'description' => 'Invoice:' . $invoiceNumber . ' Generated',
                'invoice_id' => $invoice->id,
                'reference_type' => 'Invoice',
            ]);
        } catch (Exception $e) {

            DB::rollBack();

            Log::error('Failed to create Transactions: ' . $e->getMessage());
            return response()->json('Something Went Wrong', 500);
        }
        DB::commit();
      

        DB::beginTransaction();

        foreach($tasks as $task){
            Log::info('Preparing to add journal entry', [
                'task_id' => $task->id ?? null,
                'invoice_id' => $invoiceId,
                'invoice_detail_id' => $invoiceDetail->id ?? null,
                'transaction_id' => $transaction->id ?? null,
                'client_name' => $invoice->client->name ?? null,
                'task' => $task,
            ]);
            
            $response = $this->addJournalEntry(
                $task,
                $invoiceId,
                $invoiceDetail->id,
                $transaction->id,
                $client->name,
            );

            if ($response['status'] == 'error') {
                DB::rollBack();
                Log::error('Journal entry creation failed', ['response' => $response]);
                return response()->json($response['message'], 500);
            }
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Invoice Partial created successfully!',
            'invoiceId' => $invoiceId,
        ]);
     
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


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|integer',
            'tasks.*.description' => 'required|string',
            'tasks.*.invprice' => 'required|numeric',
            'tasks.*.supplier_id' => 'required|integer',
            'tasks.*.client_id' => 'required|integer',
            'tasks.*.agent_id' => 'required|integer',
            'tasks.*.total' => 'required|numeric',
            'invdate' => 'required|date',
            'duedate' => 'nullable|date',
            'subTotal' => 'required|numeric',
            'clientId' => 'required|integer',
            'agentId' => 'required|integer',
            'invoiceNumber' => 'required|string',
            'currency' => 'required|string',
            'payment_id' => 'nullable|integer',
        ]);


        $tasks = $request->input('tasks');
        $duedate = $request->input('duedate');
        $invdate = $request->input('invdate');
        $amount = $request->input('subTotal');
        $clientId = $request->input(key: 'clientId');
        $agentId =  $request->input(key: 'agentId');
        $invoiceNumber = $request->input(key: 'invoiceNumber');
        $currency = $request->input('currency');


        $agent = Agent::where('id', $agentId)->first();
        $companyId = $agent && $agent->branch && $agent->branch->company ? $agent->branch->company->id : null;
        $branchId = $agent ? $agent->branch_id : null;


        if(!$agent || !$companyId || !$branchId) {

            Log::error('Some of this data is missing', [
                'agent' => $agent,
                'companyId' => $companyId,
                'branchId' => $branchId,
            ]);

            return response()->json('Agent or company not found!', 404);
        }

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
            ]);
        } catch (Exception $e) {
            Log::error('Failed to create invoice: ' . $e->getMessage());
            return response()->json('Invoice creation failed!', 500);
        }


        if (!empty($tasks)) {
            foreach ($tasks as $task) {

                $selectedtask = Task::where('id', operator: $task['id'])->first();
                $supplier = Supplier::where('id', operator: $task['supplier_id'])->first();
                $client = Client::where('id', operator: $task['client_id'])->first();
                $agent = Agent::where('id', operator: $task['agent_id'])->first();

                if (!$selectedtask || !$supplier || !$client || !$agent) {

                    Log::error('Failed to find task, supplier, client, or agent: ' . $task['description']);

                    return response()->json('Something went wrong', 404);
                }

                try {
                    $invoiceDetail =  InvoiceDetail::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoiceNumber,
                        'task_id' => $task['id'],
                        'task_description' => $task['description'],
                        'task_remark' => $task['remark'] ?? null,
                        'client_notes' => $task['note'] ?? null,
                        'task_price' =>  $task['invprice'],
                        'supplier_price' => $selectedtask->total,
                        'markup_price' => $task['invprice'] - $selectedtask->total,
                        'paid' => false,
                    ]);
                } catch (Exception $e) {
                    $invoice->delete();
                    Log::error('Failed to create InvoiceDetails: ' . $e->getMessage());
                    return response()->json('Something Went Wrong', 500);
                }

             

                // Log::info('filteredPayableChild', ['filteredPayableChild' => $payableAccount->children()]);
                // if ($payableAccount) {
                //     $filteredPayableChildAccount = $payableAccount->children()
                //         ->where('reference_id', $task['supplier_id']) // Filter by child reference_id
                //         ->first(); // Get the first matching child account
                //     Log::info('filteredPayableChildAccount', ['filteredPayableChildAccount' => $filteredPayableChildAccount]);
                //     $PayablechildAccountId = $filteredPayableChildAccount ? $filteredPayableChildAccount->id : null;
                // } else {
                //     $PayablechildAccountId = null; // Handle case when no parent account is found
                // }

         

            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Invoice created successfully!',
            'invoiceId' => $invoice->id,
        ]);
    }

    public function addJournalEntry(
        $task,
        $invoiceId,
        $invoiceDetailId,
        $transactionId,
        $clientName,
    ) {

        Log::info('addJournalEntry method called', [
    'task_id' => $task->id ?? null,
    'invoice_id' => $invoiceId,
]);
        //dd($task, $invoiceId, $invoiceDetailId, $transactionId, $clientName);

        $invoice = Invoice::where('id', $invoiceId)->first();

        if(!$invoice) {
            Log::error('Invoice not found', ['invoice_id' => $invoiceId]);
            return [
                'status' => 'error',
                'message' => 'Invoice not found!',
            ];
        }

        $accountsToBeUpdate = [];

        Log::info('Client credit flag and accounts to update', [
            'is_client_credit' => $invoice->is_client_credit,
            'accountsToBeUpdate' => $accountsToBeUpdate,
        ]);

        if($invoice->is_client_credit){
            $liabilities = Account::where('name', 'like', 'Liabilities%')
                ->where('company_id', $task->company_id)
                ->first();
            
            if (!$liabilities) {
                Log::error('Missing liabilities account', ['task_id' => $task->id ?? null, 'company_id' => $task->company_id ?? null]);
                return [
                    'status' => 'error',
                    'message' => 'Account not found!',
                ];
            }
            
            $advances = Account::where('name', 'Advances')
                ->where('company_id', $task->company_id)
                ->where('parent_id', $liabilities->id)
                ->first();
            
            if (!$advances) {
                Log::error('Missing advances account', ['task_id' => $task->id ?? null, 'company_id' => $task->company_id ?? null]);
                return [
                    'status' => 'error',
                    'message' => 'Account not found!',
                ];
            }

            $clientAdvance = Account::where('name', 'like', '%Clients%')
                ->where('company_id', $task->company_id)
                ->where('parent_id', $advances->id)
                ->where('root_id', $liabilities->id)
                ->first();
            
            if ($clientAdvance) {
                $clientAdvance->description = 'Invoice created for (Assets): ' . $clientName;
                $clientAdvance->debit_credit = 'debit';
                $clientAdvance->amount = $task->invoiceDetail->task_price;

                $accountsToBeUpdate[] = $clientAdvance;
            }

        } else {
            $accountReceivable = Account::where('name', 'Accounts Receivable')
                ->where('company_id', $task->company_id)
                ->first();

            if (!$accountReceivable) {
                Log::error('Missing accountReceivable', ['task_id' => $task->id ?? null, 'company_id' => $task->company_id ?? null]);
                return [
                    'status' => 'error',
                    'message' => 'Account not found!',
                ];
            }

            $clientAccount = Account::where('name', 'like', '%Clients%')
                ->where('company_id', $task->company_id)
                ->where('parent_id', $accountReceivable->id)
                ->first();

            if ($clientAccount) {
                $clientAccount->description = 'Invoice created for (Assets): ' . $clientName;
                $clientAccount->debit_credit = 'debit';
                $clientAccount->amount = $task->invoiceDetail->task_price;

                $accountsToBeUpdate[] = $clientAccount;
            }
        }

        if ($task['type'] == 'flight') {
            $detailsAccount =  Account::where('name', 'like', 'Flight Booking%')
                ->where('company_id', $task->company_id)
                ->first();
        } else {
            $detailsAccount =  Account::where('name', 'like', '%Hotel Booking%')
                ->where('company_id', $task->company_id)
                ->first();
        }

        if ($detailsAccount) {
            $detailsAccount->description = 'Invoice created for (Income): ' . $task['additional_info'];
            $detailsAccount->debit_credit = 'credit';
            $detailsAccount->amount = $task->invoiceDetail->task_price;

            $accountsToBeUpdate[] = $detailsAccount;
        }
        $commissionCalculate = 0.15 * ($task->invoiceDetail->task_price - $task->total);

        $commissionExpenses =  Account::where('name', 'like', 'Commissions Expense (Agents)%')
            ->where('company_id', $task->company_id)
            ->first();

        if ($commissionExpenses) {
            $commissionExpenses->description = 'Agents Commissions for (Expenses): ' . $task['agent']['name'];
            $commissionExpenses->debit_credit = 'debit';
            $commissionExpenses->amount = $commissionCalculate;

            $accountsToBeUpdate[] = $commissionExpenses;
        }

        $AccruedCommissionsAgent = Account::where('name', 'like', 'Commissions (Agents)%')
            ->where('company_id', $task->company_id)
            ->first();

        if ($AccruedCommissionsAgent) {
            $AccruedCommissionsAgent->description = 'Agents Commissions for (Liabilities): ' . $task['agent']['name'];
            $AccruedCommissionsAgent->debit_credit = 'credit';
            $AccruedCommissionsAgent->amount = $commissionCalculate;

            $accountsToBeUpdate[] = $AccruedCommissionsAgent;
        }


        if ( (!$advances && !$clientAccount) || !$detailsAccount || !$commissionExpenses || !$AccruedCommissionsAgent) {
            Log::error(
                'Failed to find account for journal entry',
                [
                    'advances' => $advances,
                    'clientAccount' => $clientAccount,
                    'detailsAccount' => $detailsAccount,
                    'commissionExpenses' => $commissionExpenses,
                    'AccruedCommissionsAgent' => $AccruedCommissionsAgent,
                    'task' => $task,
                ]
            );
            return [
                'status' => 'error',
                'message' => 'Account not found!',
            ];
        }

        try {
            foreach ($accountsToBeUpdate as $account) {
                $journalDataCreate = [
                    'transaction_id' => $transactionId,
                    'branch_id' => $task->agent->branch_id ?? null,
                    'company_id' => $task->company_id ?? null,
                    'account_id' =>  $account->id ?? null,
                    'invoice_id' =>  $invoiceId,
                    'invoice_detail_id' =>  $invoiceDetailId,
                    'transaction_date' => now(),
                    'description' => $account->description ?? '',
                    'debit' => $account->debit_credit == 'debit' ? $account->amount : 0,
                    'credit' => $account->debit_credit == 'credit' ? $account->amount : 0,
                    'balance' => $account->balance ?? 0,
                    'name' =>  $account->name ?? '',
                    'type' => $account->debit_credit == 'debit' ? 'receivable' : 'payable',
                    // Add other required fields with sensible defaults or nulls
                    'currency' => $task->currency ?? 'USD',
                    'exchange_rate' => $task->exchange_rate ?? 1.00,
                    'amount' => $account->amount ?? 0,
                ];

                // Log the data before creation
                foreach (['transaction_id', 'branch_id', 'company_id', 'account_id', 'invoice_id', 'invoice_detail_id', 'name', 'description'] as $field) {
                    if (empty($journalDataCreate[$field]) && $journalDataCreate[$field] !== 0) {
                        Log::error("Missing required field for JournalEntry: $field", [
                            'journalDataCreate' => $journalDataCreate,
                            'task' => $task,
                            'account' => $account,
                        ]);
                    }
                }

                Log::info('Creating JournalEntry', $journalDataCreate);

                JournalEntry::create($journalDataCreate);
            }
        } catch (Exception $e) {
            Log::error('Failed to create Journal Entry: ' . $e->getMessage(), [
                'task' => $task,
                'accountsToBeUpdate' => $accountsToBeUpdate,
            ]);
            return [
                'status' => 'error',
                'message' => 'Failed to create journal entry',
            ];
        }
        return ['status' => 'success'];
    }


    public function clientAdd(Request $request)
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
            return response()->json('Client add successfully!');
        } catch (Exception $e) {
            Log::error('Failed to create Client: ' . $e->getMessage());
            return response()->json('Client creation failed!');
        }
    }


    public function generateInvoiceNumber($sequence)
    {
        $year = now()->year;
        return sprintf('INV-%s-%05d', $year, $sequence);
    }



    public function link()
    {
        $user = Auth::user();

       // Gate::authorize('viewAny', Invoice::class);

        // Get all agents under the company
        $agents = Agent::with(['branch' => function ($query) use ($user) {
            $query->where('company_id', $user->company_id);
        }])->get();

        $agentIds = $agents->pluck('id');
        // Get invoices related to those agents
        // $invoices = Invoice::with([
        //     'agent.branch', 
        //     'invoiceDetails.task.supplier', 
        //     'invoicePartials', 
        //     'client'
        // ])->whereIn('agent_id', $agentIds)
        //   ->whereHas('invoiceDetails.task.supplier') // Ensures only invoices with suppliers are retrieved
        //   ->paginate(500);

        $invoices = Invoice::with([
            'agent.branch', 
            'invoiceDetails.task.supplier', 
            'invoicePartials', 
            'client'
        ])
        ->whereIn('agent_id', $agentIds)
        ->whereHas('invoiceDetails.task.supplier') // Only invoices with suppliers
        ->whereHas('agent.branch', function ($query) use ($user) {
            $query->where('company_id', $user->company->id);
        })
        ->paginate(500);

        // Get clients related to the agents
        $clients = Client::whereIn('agent_id', $agentIds)->get();

        // Get tasks related to the agents
        $tasks = Task::whereIn('agent_id', $agentIds)->get();
        $suppliers = Supplier::all();
        $branches = $user->role_id == Role::ADMIN ? Branch::all() : Branch::where('company_id', $user->company->id)->get();
        $types = Task::distinct()->pluck('type');
        $totalInvoices = $invoices->total();

        return view('invoice.link', compact('invoices', 'types', 'suppliers','branches', 'agents', 'clients', 'tasks', 'totalInvoices'));
    }

    /**
     * Display the specified resource.
     */



    public function show(string $invoiceNumber)
    {

        // Retrieve the invoice based on the invoice number
        $invoice = Invoice::where('invoice_number', $invoiceNumber)->with('agent.branch.company', 'client', 'invoiceDetails')->first();
        $invoicePartials = InvoicePartial::where('invoice_number', $invoiceNumber)->with('client', 'invoice', 'payment')->get();

        // Check if the invoice exists
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found!');
        }

        $checkUtilizeCredit = Credit::where('invoice_id', $invoice->id)
            ->where('company_id', $invoice->agent->branch->company_id)
            ->where('type', 'Invoice')
            ->first();

        $paymentGateway = $invoicePartials->first()?->payment_gateway ?? 'tap';
        $paidPartials = $invoicePartials->where('status', 'paid');
        $invoiceDetails = $invoice->invoiceDetails;
        $company = $invoice->agent->branch->company;

        return view('invoice.show', compact('invoice', 'invoiceDetails', 'invoicePartials', 'paidPartials', 'paymentGateway', 'company', 'checkUtilizeCredit'));
    }

    public function generatePdf(string $invoiceNumber)
    {

        $invoice = Invoice::where('invoice_number', $invoiceNumber)->with('agent.branch.company', 'client', 'invoiceDetails')->first();
        Log::info('invoice', ['invoice' => $invoice]);
        $invoicePartials = InvoicePartial::where('invoice_number', $invoiceNumber)->with('client', 'invoice')->get();
        $invoiceDetails = $invoice->invoiceDetails;

        $paymentGateway = $invoicePartials->first()?->payment_gateway ?? 'tap';

        $pdf = Pdf::loadView('invoice.pdf', compact('invoice', 'invoiceDetails', 'invoicePartials', 'paymentGateway'));

        return $pdf->download("Invoice_{$invoiceNumber}.pdf");
    }


    public function split(string $invoiceNumber, int $clientId, int $partialId)
    {

        // Retrieve the invoice based on the invoice number
        $invoice = Invoice::where('invoice_number', $invoiceNumber)->with('agent.branch.company', 'client', 'invoiceDetails')->first();
        $invoicePartial = InvoicePartial::where('id', $partialId)->where('invoice_number', $invoiceNumber)->where('client_id', $clientId)->with('client', 'invoice')->first();
        $invoicePartial->expiry_date = \Carbon\Carbon::parse($invoicePartial->expiry_date);
        // Check if the invoice exists
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found!');
        }

        $invoiceDetails = $invoice->invoiceDetails;

        return view('invoice.split', compact('invoice', 'invoiceDetails', 'invoicePartial'));
    }



    public function sendInvoice(string $invoiceNumber)
    {

        // Retrieve the invoice based on the invoice number
        $invoice = Invoice::where('invoice_number', $invoiceNumber)->first();

        // Check if the invoice exists
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found!');
        }


        // Fetch the invoice details as a list
        $invoiceDetails = InvoiceDetail::where('invoice_number', $invoiceNumber)->get();
        // Retrieve the transaction related to the invoice
        $transaction = Transaction::where('invoice_id', $invoice->id)->first();

        return view('invoice.clientInvoice', compact('invoice', 'invoiceDetails', 'transaction'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
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
                        'client_notes' => $task['note'] ?? null,
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
                        'date' => Carbon::now(),
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
    

    public function delete(Request $request, string $id)
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function updateStatus(Request $request, Invoice $invoice)
    {

        $request->validate([
            'status' => 'required|string',
        ]);

        $invoice->status = $request->input('status');
        $invoice->save();

        return redirect()->route('invoice.index')->with('status', 'Invoice status updated successfully!');
    }

    public function getTaskInvoiceStatus($taskId)
    {
        $task = Task::find($taskId);

        if (!$task) {
            return response()->json(['error' => 'Task not found!'], 404);
        }

        $invoiceDetail = InvoiceDetail::where('task_id', $taskId)->first();

        if (!$invoiceDetail) {
            return response()->json(['error' => 'Invoice detail not found!'], 404);
        }

        return response()->json(['status' => $invoiceDetail->paid]);
    }

    public function createInvoiceLinkWithClientCredit(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|integer',
            'selected_option' => 'required|string',
            'payment_gateway' => 'nullable|string',
        ]);

        $invoiceId = $request->input('invoice_id');
        $option = $request->input('selected_option');
        $gateway = $request->input('payment_gateway');

        $invoice = Invoice::find($invoiceId);

        if (!$invoice || !$invoice->client) {
            logger('Invoice or client not found', ['invoiceId' => $invoiceId]);
            return redirect()->back()->with('error', 'Something went wrong!');
        }

        $client = $invoice->client;
        $agent = $invoice->agent;
        $amount = $invoice->amount;
        $balanceCredit = Credit::getTotalCreditsByClient($client->id);
        $balance = $amount - ($balanceCredit);

        if ($option === 'use_credit') {           
            if ($balanceCredit <= 0) {
                return redirect()->back()->with('error', 'Client has no available credit balance.');
            }

            try {

                $creditSubmit = Credit::create([
                    'company_id'  => $invoice->client->agent->branch->company_id,
                    'client_id'   => $invoice->client->id,
                    'invoice_id'  => $invoice->id,
                    'type'        => 'Invoice',
                    'description' => 'Payment for ' . $invoice->invoice_number,
                    'amount'      => -($balanceCredit),
                ]);    

                $existingPartial = InvoicePartial::where('invoice_id', $invoice->id)->first();

                if (!$existingPartial) {
                    $invoicePartial = InvoicePartial::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'client_id' => $client->id,
                        'agent_id' => $agent->id,
                        'amount' => $amount,
                        'status' => 'unpaid',
                        'type' => 'full',
                        'payment_gateway' => $gateway,
                    ]);
                }

                // Save the invoice type
                $invoice->payment_type = 'full';
                $invoice->save();

                // // Create Payment Request
                // $paymentRequest = new Request([
                //     'client_id' => $client->id,
                //     'agent_id' => $agent->id,
                //     'invoice_id' => $invoice->id,
                //     'amount' => $balance,
                //     'type' => 'full',
                //     'payment_gateway' => $gateway,
                //     'notes' => 'Payment link created for invoice: ' . $invoice->invoice_number . ' with balance of: ' . $balance,
                // ]);

                // $paymentController = new PaymentController();
                // $response = $paymentController->paymentStoreLinkProcess($paymentRequest);

                // if ($response['status'] === 'error') {
                //     $invoicePartial->delete();
                //     return redirect()->back()->with('error', 'Failed to create payment link.');
                // }

                // $payment = $response['data'];

                return redirect()->route('invoice.show', $invoice->invoice_number)->with('success', 'Client credit applied. Invoice link created successfully!');

            } catch (Exception $e) {
                logger('Failed to apply client credit: ' . $e->getMessage());
                return redirect()->back()->with('error', 'Failed to apply client credit.');
            }
        }

        if ($option === 'generate_yes' || $option === 'generate_no') {
            if (!$gateway) {
                return redirect()->back()->with('error', 'Payment gateway is required.');
            }

            $balanceCredit = Credit::getTotalCreditsByClient($client->id);
            $balance = $amount - $balanceCredit;
            if ($balanceCredit <= 0) {
                return redirect()->back()->with('error', 'Client has no available credit balance.');
            }
            
            try {
                // Reset client credit
                // $client->credit = 0;
                // $client->save();

                //utilize the credit
                $creditSubmit = Credit::create([
                    'company_id'  => $invoice->client->agent->branch->company_id,
                    'client_id'   => $invoice->client->id,
                    'type'        => 'Invoice',
                    'description' => 'Payment for ' . $invoice->invoice_number,
                    'amount'      => -($balanceCredit),
                ]);                

                // Create InvoicePartial
                $existingPartial = InvoicePartial::where('invoice_id', $invoice->id)->first();

                if (!$existingPartial) {
                    $invoicePartial = InvoicePartial::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'client_id' => $client->id,
                        'agent_id' => $agent->id,
                        'amount' => $amount,
                        'status' => 'unpaid',
                        'type' => 'full',
                        'payment_gateway' => $gateway,
                    ]);
                }

                // Create Payment Request
                $paymentRequest = new Request([
                    'client_id' => $client->id,
                    'agent_id' => $agent->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $balance,
                    'type' => 'full',
                    'payment_gateway' => $gateway,
                    'notes' => 'Payment link created for invoice: ' . $invoice->invoice_number . ' with balance of: ' . $balance,
                ]);

                $paymentController = new PaymentController();
                $response = $paymentController->paymentStoreLinkProcess($paymentRequest);

                if ($response['status'] === 'error') {
                    $invoicePartial->delete();
                    return redirect()->back()->with('error', 'Failed to create payment link.');
                }

                $payment = $response['data'];
                return redirect()->route('payment.link.show', $payment->id)->with('status', 'Invoice link created successfully!');
            } catch (Exception $e) {
                logger('Failed to create invoice/payment link: ' . $e->getMessage());
                return redirect()->back()->with('error', 'Something went wrong!');
            }
        }

        return redirect()->back()->with('error', 'Invalid option selected.');
    }



    public function createInvoiceWithLoss(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|integer',
            'payment_gateway' => 'required|string',
        ]);
    }
}
