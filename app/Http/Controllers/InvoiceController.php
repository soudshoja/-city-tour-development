<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Agent;
use Illuminate\Support\Facades\Auth;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\Company;
use App\Models\GeneralLedger;
use App\Models\InvoiceDetails;
use App\Models\Task;
use Exception;
use Illuminate\Http\Request;
use App\Models\InvoiceSequence;
use App\Models\Role;
use App\Models\Supplier;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InvoiceController extends Controller
{

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
            $invoices = Invoice::with('agent.company', 'client')->where('agent_id', $id)->paginate(6);
        } elseif ($user->role_id == Role::COMPANY) {
            // Company can only see trips with tasks under their agents
            $agents = Agent::where('company_id', $user->company->id)->pluck('id');
            $invoices = Invoice::with('agent.company', 'client')->where('agent_id', $id)->paginate(6);
        } elseif ($user->role_id == Role::AGENT) {
            // Agent can see their tasks
            $invoices = Invoice::with('agent.company', 'client')->where('agent_id', $user->agent->id)->paginate(6);
        }

        return view('invoice.index', compact('invoices', 'agent'));
    }

    public function create()
    {
        $user = Auth::user();

        if ($user->role_id == Role::COMPANY) {
            $company = Auth::user()->company;
        } elseif ($user->role_id == Role::AGENT) {
            $agent = Auth::user()->agent;
            $company = Company::where('id', $agent->company_id)->first();
        }


        $invoiceSequence = InvoiceSequence::lockForUpdate()->first();

        if (!$invoiceSequence) {
            $invoiceSequence = InvoiceSequence::create(['current_sequence' => 1]);
        }

        $currentSequence = $invoiceSequence->current_sequence;
        $invoiceNumber = $this->generateInvoiceNumber($currentSequence);

        $invoiceSequence->current_sequence++;
        $invoiceSequence->save();


        $agentId = Agent::where('user_id', Auth::id())->first() ? Agent::where('user_id', Auth::id())->first()->id : null;
        $clients = Client::where('agent_id', $agentId)->get();
        $tasks = Task::where('agent_id', $agentId)->get();
        $suppliers = Supplier::all();


        // Fetch the company associated with the logged-in user


        $invoice = null; // No invoice exists yet, this can be passed as null

        return view('invoice.create', compact('clients', 'tasks', 'invoice', 'company', 'suppliers', 'invoiceNumber'));
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $tasks = $request->input('tasks');
        $params = $request->input('params');
        $subamount = $request->input('subtotal');
        $amount = $request->input('subtotal');
        $clientId = $request->input(key: 'clientId');
        $agentId = Agent::where('user_id', Auth::id())->first() ? Agent::where('user_id', Auth::id())->first()->id : null;
        $invoiceNumber = data_get($params, 'invoiceNumber');


        $agent = Agent::where('user_id', Auth::id())->first();
        $agentId = $agent ? $agent->id : null;
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
                'sub_amount' => $subamount,
                'amount' => $amount,
                'currency' => data_get($params, 'currency'),
                'status' => 'unpaid',
                'invoice_date' => data_get($params, 'invoiceDate'),
                'due_date' => data_get($params, 'dueDate'),
                'label' => data_get($params, 'label'),
                'account_number' => data_get($params, 'accNo'),
                'bank_name' => data_get($params, 'bankName'),
                'swift_no' => data_get($params, 'swiftNo'),
                'iban_no' => data_get($params, 'ibanNo'),
                'country' => data_get($params, 'country'),
                'tax' => data_get($params, 'tax'),
                'discount' => data_get($params, 'discount'),
                'shipping' => data_get($params, 'shippingCharge'),
                'accept_payment' => data_get($params, 'paymentMethod'),
            ]);

            if (!empty($tasks)) {
                foreach ($tasks as $task) {
                    try {

                        $selectedtask = Task::where('id', operator: $task['id'])->first();

                        // Create a transaction record first
                        $transaction = Transaction::create([
                            'invoice_id' => $invoice->id,
                            'company_id'  => $companyId,
                            'client_id' => $clientId,
                            'transaction_date' => Carbon::now(),
                            'amount' => $task['price'],
                            'status'  => 'pending',
                            'description' => 'Invoice:' . $invoiceNumber . ' Generated',
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
                            'transaction_date' => Carbon::now(),
                            'description' => 'Accounts Payable for Supplier: ' . $selectedtask->supplier->name,
                            'debit' => 0,
                            'credit' => $selectedtask->total,
                            'balance' => $filteredPayableChildAccount->actual_balance + $selectedtask->total,

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
                            'account_id' =>  $ReceivablechildAccountId,
                            'transaction_date' => Carbon::now(),
                            'description' => 'Accounts Receivable for Invoice: ' . $invoiceNumber,
                            'debit' => $task['price'],
                            'credit' => 0,
                            'balance' => $filteredReceivableChildAccount->actual_balance + $task['price'],

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

                        $markup = $task['price'] - $selectedtask->total;
                        // Try to create income
                        GeneralLedger::create([
                            'transaction_id' => $transaction->id,
                            'company_id' => $companyId,
                            'account_id' => $IncomechildAccountId,
                            'transaction_date' => Carbon::now(),
                            'description' => 'Accounts Receivable for Invoice: ' . $invoiceNumber,
                            'debit' => 0,
                            'credit' => $markup,
                            'balance' => $filteredIncomeChildAccount->actual_balance + $markup,

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



                        InvoiceDetails::create([
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoiceNumber,
                            'task_id' => $task['id'],
                            'task_description' => $task['description'],
                            'task_remark' => $task['remark'],
                            'task_price' => $task['total'],
                            'supplier_price' => $selectedtask->total,
                            'markup_price' => $task['total'] - $selectedtask->total,
                            'paid' => false,
                        ]);
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
        $agents = Agent::where('company_id', $user->company->id)->pluck('id');

        // Get invoices related to those agents
        $invoices = Invoice::with('agent.company', 'client')->whereIn('agent_id', $agents)->paginate(10);

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
        $invoice = Invoice::where('invoice_number', $invoiceNumber)->first();

        // Check if the invoice exists
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found!');
        }


        // Fetch the invoice details as a list
        $invoiceDetails = InvoiceDetails::where('invoice_number', $invoiceNumber)->get();
        // Retrieve the transaction related to the invoice
        $transaction = Transaction::where('invoice_id', $invoice->id)->first();

        return view('invoice.show', compact('invoice', 'invoiceDetails', 'transaction'));
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
        //
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
}
