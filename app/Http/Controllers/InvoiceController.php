<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\GeneralLedger;
use Illuminate\Support\Facades\Auth;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\Company;
use App\Models\InvoiceDetails;
use App\Models\CoaCategory;
use App\Models\Account;
use App\Models\Task;
use App\Models\Suppliers;
use Exception;
use Illuminate\Http\Request;
use App\Models\InvoiceSequence;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InvoiceController extends Controller
{

    public function index($id = null)
    {

        $user = Auth::user();
     
        if (is_null($id)) {
            $agent = Agent::find($id); 
        }else{
            $agent = Agent::find( $user->agent->id);    
        }

        if ($user->role == 'admin') {
            // Admin can see all trips and tasks
            $invoices = Invoice::with('agent.company', 'client')->where('agent_id', $id)->paginate(6);
        } elseif ($user->role == 'company') {
            // Company can only see trips with tasks under their agents
            $agents = Agent::where('company_id', $user->company->id)->pluck('id');
            $invoices = Invoice::with('agent.company', 'client')->where('agent_id', $id)->paginate(6);
        } elseif ($user->role == 'agent') {
            // Agent can see their tasks
            $invoices = Invoice::with('agent.company', 'client')->where('agent_id', $user->agent->id)->paginate(6);
        }

        return view('invoice.index', compact('invoices','agent'));
    }

    public function create()
    {
        $agentId = Agent::where('user_id', Auth::id())->first() ? Agent::where('user_id', Auth::id())->first()->id : null;
        $clients = Client::where('agent_id', $agentId)->get();
        $tasks = Task::where('status', 'pending')->get();
        $suppliers = Suppliers::all();
        return view('invoice.create', compact('clients', 'tasks', 'suppliers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $tasks = $request->input('tasks');
        $amount = $request->input('total');
        $tax = $request->input('tax');
        $clientId = $request->input('clientId');
        $currency = $request->input('currency');

        $agent = Agent::where('user_id', Auth::id())->first();
        $agentId = $agent ? $agent->id : null;
        $companyId = $agent ? $agent->company_id : null;

        $receivableAccounts = Account::where('account_name', 'like', '%Receivable%')
        ->where('company_id', $companyId)
        ->first();
        $payableAccounts =  Account::where('account_name', 'like', '%Payable%')
        ->where('company_id', $companyId)
        ->first();


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

                        $selectedtask = Task::where('id', $task['taskId'])->first();

                        // Create a transaction record first
                        $transaction = Transaction::create([
                            'invoice_id' => $invoice->id,
                            'company_id'  => $companyId,
                            'client_id' => $clientId,
                            'transaction_date' => Carbon::now(),
                            'amount' => $task['price'],
                            'status'  => 'pending', 
                            'description'=> 'Invoice:' + $invoiceNumber + ' Generated',
                        ]);
                            
                        // Try to create payable account
                        GeneralLedger::create([
                            'transaction_id'=> $transaction->id,
                            'company_id' => $companyId,
                            'account_id'=>  $payableAccounts->account_id,
                            'transaction_date' => Carbon::now(), 
                            'description'=> 'Accounts Payable for Supplier: ' . $selectedtask->supplier->name,
                            'debit' => 0,
                            'credit' => $selectedtask->total,
                            'balance' => $payableAccounts->balance + $selectedtask->total,
                  
                        ]);

                        $payableAccounts->balance += $selectedtask->total;
                        $payableAccounts->save();

                        // Try to create receivable account
                        GeneralLedger::create([
                            'transaction_id'=> $transaction->id,
                            'company_id' => $companyId,
                            'account_id'=>  $receivableAccounts->account_id,
                            'transaction_date' => Carbon::now(), 
                            'description'=> 'Accounts Receivable for Invoice: ' . $invoiceNumber,
                            'debit' => $task['price'],
                            'credit' => 0,
                            'balance' => $receivableAccounts->balance + $task['price'],
                    
                        ]);

                        $receivableAccounts->balance += $task['price'];
                        $receivableAccounts->save();


                          // Try to create each invoice detail
                        InvoiceDetails::create([
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoiceNumber,
                            'task_id' => $task['taskId'],
                            'task_description' => $task['taskName'],
                            'task_remark' => $task['remark'],
                            'task_price' => $task['price'],
                            'supplier_price' => $selectedtask->total,
                            'markup_price' => $task['price']-$selectedtask->total,
                            'paid' =>false,
                        ]);

                    } catch (Exception $e) {
                        // Log the error if something goes wrong with a specific task
                        Log::error('Failed to create InvoiceDetails: ' . $e->getMessage());
                        return response()->json(['error' => 'Failed to create InvoiceDetails for task: ' . $task['taskName']], 500);
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'redirect_url' => route('invoice.show', ['invoiceNumber' => $invoice->invoice_number])
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