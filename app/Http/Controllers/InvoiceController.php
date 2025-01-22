<?php

namespace App\Http\Controllers;

use App\Actions\CreateInvoiceAction;
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
use App\Models\GeneralLedger;
use App\Models\InvoiceDetail;
use App\Models\Task;
use Exception;
use Illuminate\Http\Request;
use App\Models\InvoiceSequence;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redirect;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    use NotificationTrait;
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
            $agents = Agent::with(['branch' => function ($query) use ($user) {
                $query->where('company_id', $user->company->id);
            }])->pluck('id');

            $invoices = Invoice::with('agent.branch', 'client')->where('agent_id', $id)->paginate(6);
        } elseif ($user->role_id == Role::AGENT) {
            // Agent can see their tasks
            $invoices = Invoice::with('agent.branch', 'client')->where('agent_id', $user->agent->id)->paginate(6);
        }

        return view('invoice.index', compact('invoices', 'agent'));
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

        $taskIds = $request->query('task_ids', ''); // Comma-separated task IDs

        if (gettype($taskIds) == 'string') {
            $taskIdsArray = explode(',', $taskIds); // Multiple tasks
        } else {
            $taskIdsArray = $taskIds; // Single task
        }

        $tasks1 = Task::with('supplier', 'agent.branch', 'invoiceDetail.invoice', 'flightDetails.countryFrom', 'flightDetails.countryTo', 'hotelDetails.hotel');

        $selectedTasks = $tasks1->whereIn('id', $taskIdsArray)->get();

        foreach ($selectedTasks as $task) {
            if ($task->invoiceDetail) {
                return Redirect::route('tasks.index')->with('error', 'Task already invoiced!');
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

        $agents = collect();
        $clients = collect();

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

        // if selected agent is null, get all agents under the company if the user is a company, if not get the agent data from the user
        $agentId =  $selectedAgent == null ? $user->role_id == Role::COMPANY ? $agentsId = array_map(function ($agent) {
            return $agent['id'];
        }, $agents->toArray()) : $user->agent->id : $selectedAgent->id;

      
        $clientId = $selectedClient ? $selectedClient->id : null;


        if ($user->role_id == Role::AGENT) {

            $tasks = $agentId 
                ? Task::with('supplier', 'agent.branch', 'invoiceDetail.invoice', 'flightDetails.countryFrom', 'flightDetails.countryTo', 'hotelDetails.hotel')
                    ->where('agent_id', $agentId)
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
        } else {
            $tasks = $agentId 
                ? Task::with('supplier', 'agent.branch', 'invoiceDetail.invoice', 'flightDetails.countryFrom', 'flightDetails.countryTo', 'hotelDetails.hotel')
                    ->whereIn('agent_id', (array)$agentId)
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
                
        }

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
            'company',
            'suppliers',
            'invoiceNumber',
            'selectedTasks',
            'selectedAgent',
            'selectedClient',
            'paymentGateways',
            'todayDate',
            'appUrl'
        ));
    }


    public function edit(string $invoiceNumber)
    {

        $user = Auth::user();
        $agents = collect();
        if ($user->role_id == Role::COMPANY) {
            $company = $user->company;
            $company = Company::with('branches.agents')->find($company->id);
            $agents = $company->branches->flatMap->agents;
        } elseif ($user->role_id == Role::AGENT) {
            $agent = $user->agent;
            $company = $agent->branch->company;
            $agents = $company->branches->flatMap->agents;
        }

        // Retrieve the invoice based on the invoice number
        $invoice = Invoice::where('invoice_number', $invoiceNumber)->with('agent.branch.company', 'client', 'invoiceDetails.task')->first();

        // Check if the invoice exists
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found!');
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
        $selectedTasks = $invoice->invoiceDetails->map(function ($invoiceDetail) use ($invoice) {
            $task = $invoiceDetail->task;
            $task->agent_name = $task->agent->name ?? null; // Add agent_name dynamically
            $task->branch_name = $task->agent->branch->name ?? null;
            $task->task_price = $invoiceDetail->task_price;
            $task->invprice = (float) $invoice->amount;
            return $task;
        });
        $selectedAgent = $invoice->agent;
        $selectedClient = $invoice->client;

        $suppliers = Supplier::all();
        $paymentGateways = ['Tap', 'Hesabe', 'MyFatoorah'];
        $invoiceDate = $invoice->invoice_date;
        $invprice = $invoice->amount;
        $dueDate =  $invoice->due_date;

        $appUrl = config('app.url');

        return view('invoice.edit', compact(
            'clients',
            'invoice',
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
            'paymentGateways',
            'invoiceDate',
            'invprice',
            'dueDate',
            'appUrl'
        ));
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


        $agent = Agent::where('id', $agentId)->first();
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
                            'task_remark' => $task['remark'] ?? null,
                            'client_notes' => $task['note'] ?? null,
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
                            'branch_id' => $branchId,
                            'company_id' => $companyId,
                            'account_id' =>  $payableAccount->id,
                            'invoice_id' =>  $invoice->id,
                            'invoiceDetail_id' =>  $invoiceDetail->id,
                            'transaction_date' => Carbon::now(),
                            'description' => 'Payment: ' . $supplier->name,
                            'debit' => $selectedtask->total,
                            'credit' => 0,
                            'balance' => $selectedtask->total,
                            'name' => $supplier->name,
                            'type' => 'payable',
                        ]);


                        // Try to create receivable account
                        GeneralLedger::create([
                            'transaction_id' => $transaction->id,
                            'branch_id' => $branchId,
                            'company_id' => $companyId,
                            'invoice_id' =>  $invoice->id,
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
                        ]);



                        $markup = $task['invprice'] - $selectedtask->total;
                        // Try to create income
                        GeneralLedger::create([
                            'transaction_id' => $transaction->id,
                            'branch_id' => $branchId,
                            'company_id' => $companyId,
                            'account_id' => $incomeAccount->id,
                            'invoice_id' =>  $invoice->id,
                            'invoiceDetail_id' =>  $invoiceDetail->id,
                            'transaction_date' => Carbon::now(),
                            'description' => 'Price markup by Agent: ' . $agent->name,
                            'debit' => 0,
                            'credit' => $markup,
                            'balance' => $markup,
                            'name' =>   $agent->name,
                            'type' => 'income',
                        ]);


                        $selectedtask->status = 'Assigned';
                        $selectedtask->save();
                    } catch (Exception $e) {
                        Log::error('Failed to create InvoiceDetails: ' . $e->getMessage());
                        return response()->json('Failed to create InvoiceDetails for task: ' . $task['description'], 500);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Invoice created successfully!',
                'invoiceId' => $invoice->id,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to create invoice: ' . $e->getMessage());
            return response()->json('Invoice creation failed!', 500);
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


    public function generateInvoiceNumber($sequence)
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
        $agents = Agent::with(['branch' => function ($query) use ($user) {
            $query->where('company_id', $user->company_id);
        }])->get();

        $agentIds = $agents->pluck('id');
        // Get invoices related to those agents
        $invoices = Invoice::with('agent.branch','invoiceDetails.task','client')->whereIn('agent_id', $agentIds)->paginate(10);

        // Get clients related to the agents
        $clients = Client::whereIn('agent_id', $agentIds)->get();

        // Get tasks related to the agents
        $tasks = Task::whereIn('agent_id', $agentIds)->get();
        $suppliers = Supplier::all();
        $branches = $user->role_id == Role::ADMIN ? Branch::all() : Branch::where('company_id', $user->company->id)->get();
        $types = Task::distinct()->pluck('type');
        $totalInvoices = $invoices->total();

        return view('invoice.companyAgentsInvoices', compact('invoices', 'types', 'suppliers','branches', 'agents', 'clients', 'tasks', 'totalInvoices'));
    }


    /**
     * Display the specified resource.
     */



    public function show(string $invoiceNumber)
    {

        // Retrieve the invoice based on the invoice number
        $invoice = Invoice::where('invoice_number', $invoiceNumber)->with('agent.branch.company', 'client', 'invoiceDetails')->first();
        $invoicePartials = InvoicePartial::where('invoice_number', $invoiceNumber)->with('client', 'invoice')->get();

        // Check if the invoice exists
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found!');
        }

        $paymentGateway = $invoicePartials->first()?->payment_gateway ?? 'tap';

        $invoiceDetails = $invoice->invoiceDetails;
        $company = $invoice->agent->branch->company;

        return view('invoice.show', compact('invoice', 'invoiceDetails', 'invoicePartials', 'paymentGateway', 'company'));
    }

    public function generatePdf(string $invoiceNumber)
    {
        $invoice = Invoice::where('invoice_number', $invoiceNumber)->with('agent.branch.company', 'client', 'invoiceDetails')->first();
        $invoicePartials = InvoicePartial::where('invoice_number', $invoiceNumber)->with('client', 'invoice')->get();
        $invoiceDetails = $invoice->invoiceDetails;

        $paymentGateway = $invoicePartials->first()?->payment_gateway ?? 'tap';

        $pdf = Pdf::loadView('invoice.pdf', compact('invoice', 'invoiceDetails', 'invoicePartials', 'paymentGateway'));

        return $pdf->download("Invoice_{$invoiceNumber}.pdf");
    }


    public function split(string $invoiceNumber, int $clientId)
    {

        // Retrieve the invoice based on the invoice number
        $invoice = Invoice::where('invoice_number', $invoiceNumber)->with('agent.branch.company', 'client', 'invoiceDetails')->first();
        $invoicePartial = InvoicePartial::where('invoice_number', $invoiceNumber)->where('client_id', $clientId)->with('client', 'invoice')->first();
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
    public function update(Request $request, string $id)
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json(['error' => 'Invoice not found!'], 404);
        }

        try {
            $invoice->update($request->all());

            return redirect()->back()->with('status', 'Invoice updated successfully!');
        } catch (Exception $error) {
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
