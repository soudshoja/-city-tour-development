<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Support\Facades\Auth;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\Company;
use App\Models\InvoiceDetails;
use App\Models\Task;
use Exception;
use Illuminate\Http\Request;
use App\Models\InvoiceSequence;
use Illuminate\Support\Facades\Log;

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

    // Fetch the company associated with the logged-in user
    $company = Auth::user()->company;

    $invoice = null; // No invoice exists yet, this can be passed as null

    return view('invoice.create', compact('clients', 'tasks', 'invoice', 'company'));
}


    /**
     * Store a newly created resource in storage.
     */
   public function store(Request $request)
{
    $tasks = $request->input('tasks');
    $amount = $request->input('total');
    $clientId = $request->input('clientId');
    $currency = $request->input('currency');
    $agentId = Agent::where('user_id', Auth::id())->first() ? Agent::where('user_id', Auth::id())->first()->id : null;

    try {
        $invoiceSequence = InvoiceSequence::lockForUpdate()->first();

        if (!$invoiceSequence) {
            $invoiceSequence = InvoiceSequence::create(['current_sequence' => 1]);
        }

        $currentSequence = $invoiceSequence->current_sequence;
        $invoiceNumber = $this->generateInvoiceNumber($currentSequence);

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
                    InvoiceDetails::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoiceNumber,
                        'task_id' => $task['taskId'],
                        'task_description' => $task['taskName'],
                        'task_remark' => $task['remark'],
                        'task_price' => $task['price'],
                    ]);
                } catch (Exception $e) {
                    Log::error('Failed to create InvoiceDetails: ' . $e->getMessage());
                    return redirect()->back()->with('error', 'Failed to create InvoiceDetails for task: ' . $task['taskName']);
                }
            }
        }

        return redirect()->route('invoice.companyAgentsInvoices')->with('success', 'Invoice created successfully!');
    } catch (Exception $e) {
        return redirect()->back()->with('error', 'Invoice creation failed!');
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
    if ($user->role !== 'company') {
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