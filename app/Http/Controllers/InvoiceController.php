<?php

namespace App\Http\Controllers;

use App\Http\Traits\NotificationTrait;
use App\Models\Account;
use App\Models\Agent;
use Illuminate\Support\Facades\Auth;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\Company;
use App\Models\GeneralLedger;
use App\Models\InvoiceDetail;
use App\Models\Task;
use Exception;
use Illuminate\Http\Request;
use App\Models\InvoiceSequence;
use App\Models\Role;
use App\Models\Supplier;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redirect;

class InvoiceController extends Controller
{
    use NotificationTrait;

    public function index($id = null)
    {

        $user = Auth::user();


        if (is_null($id)) {
            $agent = Agent::find($id);
        } else {
            $agent = Agent::find($user->agent->id);
        }

        if ($user->role_id == Role::ADMIN) {
            // Admin can see all trips and tasks
            $invoices = Invoice::with('agent.branch', 'client')->where('agent_id', $id)->paginate(6);
        } elseif ($user->role_id == Role::COMPANY) {
            // Company can only see trips with tasks under their agents
            $agents = Agent::with(['branch' => function($query) use ($user) {
                $query->where('company_id', $user->company->id);
            }])->pluck('id');

            $invoices = Invoice::with('agent.branch', 'client')->where('agent_id', $id)->paginate(6);
        } elseif ($user->role_id == Role::AGENT) {
            // Agent can see their tasks
            $invoices = Invoice::with('agent.branch', 'client')->where('agent_id', $user->agent->id)->paginate(6);
        }

        return view('invoice.index', compact('invoices', 'agent'));
    }

    public function create(Request $request)
    {
        $taskIds = $request->query('task_ids', ''); // Comma-separated task IDs
        $taskIdsArray = explode(',', $taskIds); // Multiple tasks
        $selectedTasks = Task::with('invoiceDetail.invoice')->whereIn('id', $taskIdsArray)->get();
   
        foreach($selectedTasks as $task) {
            if ($task->invoiceDetail) {
                return Redirect::route('tasks.index')->with('error', 'Task already invoiced!');
            }
        }
        $user = Auth::user();
    
        if ($user->role_id == Role::COMPANY) {
            $company = $user->company;
            
            $agents = Agent::with(['branch' => function($query) use ($user) {
                $query->where('company_id', $user->company->id);
            }])->get();

        } elseif ($user->role_id == Role::AGENT) {
            $agent = $user->agent;
            $company = Company::find($agent->company_id);
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
            'title' => 'Invoice Created',
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
            $selectedAgent =null;
        }

        // if selected agent is null, get all agents under the company if the user is a company, if not get the agent data from the user
        $agentId =  $selectedAgent == null ? $user->role_id == Role::COMPANY ? $agentsId = array_map(function ($agent) {
            return $agent['id'];
        }, $agents->toArray()) : $user->agent->id : $selectedAgent->id;

        $clientId = $selectedClient ? $selectedClient->id : null;
        
        $clients = Client::with(['agent.branch' => function ($query) use ($user) {
            $query->where('company_id', $user->company->id);
        }])->get();

        $tasks = null; 
        if ($user->role_id == Role::AGENT) {
            $tasks = $agentId ? Task::where('agent_id', $agentId)->get() : collect();
        }
        $suppliers = Supplier::all();
   
        $todayDate = Carbon::now()->format('Y-m-d');

        $appUrl = config('app.url');

        return view('invoice.create', compact(
            'clients', 
            'agents',
            'agentId', 
            'clientId', 
            'tasks', 
            'company', 
            'suppliers', 
            'invoiceNumber', 
            'selectedTasks', 
            'selectedAgent',
            'selectedClient',
            'todayDate',
            'appUrl'
        ));
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
            'tasks.*.remark' => 'nullable|string',
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
        $clientId = $request->input(key: 'clientId');
        $agentId =  $request->input(key: 'agentId');
        $invoiceNumber = $request->input(key: 'invoiceNumber');
        $currency = $request->input('currency');


        $agent = Agent::where('id', operator: $agentId)->first();
        $companyId = $agent ? $agent->company_id : null;
        Log::info('Company ID:', ['companyId' => $companyId]);

        $receivableAccount = Account::where('name', 'like', '%Receivable%')
            ->where('company_id', $companyId)
            ->first();

        Log::info('clientId:', ['clientId' => $clientId]);

        if ($receivableAccount) {
            $filteredReceivableChildAccount = $receivableAccount->children()
                ->where('reference_id', $clientId) // Filter by child reference_id
                ->first(); // Get the first matching child account
            Log::info('filteredReceivableChildAccount:', ['filteredReceivableChildAccount' => $filteredReceivableChildAccount]);
            $ReceivablechildAccountId = $filteredReceivableChildAccount ? $filteredReceivableChildAccount->id : null;
        } else {
            $ReceivablechildAccountId = null; // Handle case when no parent account is found
        }


        $payableAccount =  Account::where('name', 'like', '%Payable%')
            ->where('company_id', $companyId)
            ->first();

        $incomeAccount =  Account::where('name', 'like', '%Income On Sales%')
            ->where('company_id', $companyId)
            ->first();

        if ($incomeAccount) {
            Log::info('incomeAccount', ['incomeAccount' => $incomeAccount]);
            $filteredIncomeChildAccount = $incomeAccount->children()
                ->where('reference_id', $agentId) // Filter by child reference_id
                ->first(); // Get the first matching child account
            Log::info('filteredIncomeChildAccount', ['filteredIncomeChildAccount' => $filteredIncomeChildAccount]);
            $IncomechildAccountId = $filteredIncomeChildAccount ? $filteredIncomeChildAccount->id : null;
        } else {
            $IncomechildAccountId = null; // Handle case when no parent account is found
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
                'due_date' => $duedate
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
                            'task_price' =>  $task['invprice'],
                            'supplier_price' => $selectedtask->total,
                            'markup_price' => $task['invprice'] - $selectedtask->total,
                            'paid' => false,
                        ]);

                        $transaction = Transaction::create([
                            'entity_id' => $companyId,
                            'entity_type' => 'company',
                            'transaction_type' => 'credit',
                            'amount'=>  $task['invprice'],
                            'date'=> Carbon::now(),
                            'description'=> 'Invoice:' . $invoiceNumber . ' Generated',
                            'invoice_id'=> $invoice->id,
                            'reference_type' =>'Invoice', 
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
                            'account_id' =>  $PayablechildAccountId,
                            'invoice_id' =>  $invoice->id,
                            'invoiceDetail_id' =>  $invoiceDetail->id,
                            'transaction_date' => Carbon::now(),
                            'description' => 'Payment need to be made to: ' . $supplier->name,
                            'debit' => $selectedtask->total,
                            'credit' =>0,
                            'balance' => $selectedtask->total,
                            'name' => $supplier->name,
                            'type' => 'payable'
                        ]);


                        $filteredPayableChildAccount->actual_balance += $selectedtask->total;
                        $filteredPayableChildAccount->save();
                        Log::info('balance', ['filteredPayableChildAccount' => $filteredPayableChildAccount->actual_balance]);


                        $parentPayableAccount = $filteredPayableChildAccount->parent; // Get the parent account
                        if ($parentPayableAccount) {
                            // Sum all child balances

                            $totalBalance = $parentPayableAccount->children()->sum('actual_balance');
                            $parentPayableAccount->actual_balance = $totalBalance; // Update the parent's balance
                            $parentPayableAccount->save(); // Save the parent account
                        }


                        // Try to create receivable account
                        GeneralLedger::create([
                            'transaction_id' => $transaction->id,
                            'company_id' => $companyId,
                            'invoice_id' =>  $invoice->id,
                            'invoiceDetail_id' =>  $invoiceDetail->id,
                            'account_id' =>  $ReceivablechildAccountId,
                            'transaction_date' => Carbon::now(),
                            'description' => 'Payment need to be received from: ' . $client->name,
                            'debit' => 0,
                            'credit' => $task['invprice'],
                            'balance' => $task['invprice'],
                            'name' =>  $client->name,
                            'type' => 'receivable'
                        ]);

                        $filteredReceivableChildAccount->actual_balance += $task['price'];
                        $filteredReceivableChildAccount->save();
                        Log::info('filteredReceivableChildAccount', ['filteredReceivableChildAccount' => $filteredReceivableChildAccount]);

                        $parentReceivableAccount = $filteredReceivableChildAccount->parent; // Get the parent account
                        if ($parentReceivableAccount) {
                            // Sum all child balances

                            Log::info('parentReceivableAccount:', ['parentReceivableAccount' => $parentReceivableAccount]);
                            $totalBalance = $parentReceivableAccount->children()->sum('actual_balance');
                            $parentReceivableAccount->actual_balance = $totalBalance; // Update the parent's balance
                            $parentReceivableAccount->save(); // Save the parent account
                        }

                        Log::info('price:', ['price' => $task['price']]);
                        Log::info('selectedtask->total:', ['selectedtask->total' => $selectedtask->total]);

                        $markup = $task['invprice'] - $selectedtask->total;
                        // Try to create income
                        GeneralLedger::create([
                            'transaction_id' => $transaction->id,
                            'company_id' => $companyId,
                            'account_id' => $IncomechildAccountId,
                            'invoice_id' =>  $invoice->id,
                            'invoiceDetail_id' =>  $invoiceDetail->id,
                            'transaction_date' => Carbon::now(),
                            'description' => 'Price markup by Agent: ' . $agent->name,
                            'debit' => 0,
                            'credit' => $markup,
                            'balance' => $markup,
                            'name' =>   $agent->name,
                            'type' => 'income'
                        ]);


                        Log::info('markup:', ['markup' => $markup]);
                        Log::info('filteredIncomeChildAccount:', ['filteredIncomeChildAccount' => $filteredIncomeChildAccount->actual_balance]);
                        $filteredIncomeChildAccount->actual_balance += $markup;
                        $filteredIncomeChildAccount->save();

                        $parentIncomeAccount = $filteredIncomeChildAccount->parent; // Get the parent account
                        if ($parentIncomeAccount) {
                            // Sum all child balances
                            $totalBalance = $parentIncomeAccount->children()->sum('actual_balance');
                            $parentIncomeAccount->actual_balance = $totalBalance; // Update the parent's balance
                            $parentIncomeAccount->save(); // Save the parent account
                        }
                       
                        $selectedtask->status = 'Assigned';
                        $selectedtask->save();

                    } catch (Exception $e) {
                        Log::error('Failed to create InvoiceDetails: ' . $e->getMessage());
                        return response()->json('Failed to create InvoiceDetails for task: ' . $task['description']);
                    }
                }
            }

            return response()->json('Invoice created successfully!');
        } catch (Exception $e) {
            Log::error('Failed to create InvoiceDetails: ' . $e->getMessage());
            return response()->json('Invoice creation failed!');
        }
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


    private function generateInvoiceNumber($sequence)
    {
        $year = now()->year;
        return sprintf('INV-%s-%05d', $year, $sequence);
    }

    public function companyAgentsInvoices()
    {
        $user = Auth::user();

        // Ensure that the user is a company
        if ($user->role_id !== Role::COMPANY) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        // Get all agents under the company
        $agents = Agent::with(['branch' => function($query) use ($user) {
            $query->where('company_id', $user->company->id);
        }])->pluck('id');

        // Get invoices related to those agents
        $invoices = Invoice::with('agent.branch', 'client')->whereIn('agent_id', $agents)->paginate(10);

        // Get clients related to the agents
        $clients = Client::whereIn('agent_id', $agents)->get();

        // Get tasks related to the agents
        $tasks = Task::whereIn('agent_id', $agents)->get();

        $totalInvoices = $invoices->total();

        return view('invoice.companyAgentsInvoices', compact('invoices', 'clients', 'tasks', 'totalInvoices'));
    }


    /**
     * Display the specified resource.
     */



    public function show(string $invoiceNumber)
    {

        // Retrieve the invoice based on the invoice number
        $invoice = Invoice::where('invoice_number', $invoiceNumber)->with('agent.branch.company', 'client', 'invoiceDetails')->first();

        // Check if the invoice exists
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found!');
        }

        $invoiceDetails = $invoice->invoiceDetails;

        return view('invoice.show', compact('invoice', 'invoiceDetails'));
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
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json(['error' => 'Invoice not found!'], 404);
        }

        try{
            $invoice->update($request->all());

            return redirect()->back()->with('status', 'Invoice updated successfully!');
        }catch(Exception $error){
            logger('Failed to update invoice: ' . $error->getMessage());
            return redirect()->back()->with('error', 'Failed to update invoice!');
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
}
