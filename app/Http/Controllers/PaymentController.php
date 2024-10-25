<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppNotificationService;
use Illuminate\Support\Facades\Log;
use App\Models\InvoiceDetails;
use App\Models\GeneralLedger;
use Illuminate\Support\Facades\Auth;
use App\Models\Agent;
use App\Models\Task;
use Illuminate\Http\Request;
use App\Models\Invoice; 
use App\Models\Account; 
use App\Models\Payment; 
use App\Models\Transaction; 
use Carbon\Carbon;
use Exception;

class PaymentController extends Controller
{

    public function index(string $invoiceNumber)
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
    
            return view('payment.index', compact('invoice', 'invoiceDetails', 'transaction'));
    }

    public function process($invoiceNumber, Request $request)
    {

        $request->validate([
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email',
            'client_phone' => 'required|string|max:15',
            'selected_items' => 'required|array',
            'total_amount' => 'required|numeric',
            'payment_method' => 'required|string'
        ]);

        // Fetch the invoice to get payment details
        $invoice = Invoice::with('agent.company', 'client')->where('invoice_number', $invoiceNumber)->first();
       

    // Retrieve selected item IDs
    $selectedItems = $request->input('selected_items', []);
    
    $invoiceDetails = InvoiceDetails::with('task')
    ->whereIn('id', $selectedItems) 
    ->get();


    $receivableAccounts = Account::where('account_name', 'like', '%Receivable%')
    ->where('company_id', $invoice->agent->company->id)
    ->first();


    $cashAccount = Account::where('account_name', 'Cash') // or bank account
    ->where('company_id', $invoice->agent->company->id)
    ->first();

        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found.');
        }

        if (is_array($invoiceDetails) && !empty($invoiceDetails)) {
            foreach ($invoiceDetails as $invoicedetail) {
                try {
                    $selectedtask = Task::where('id', $invoicedetail['task_id'])->first();

                    // Create a transaction record first
                    $transaction = Transaction::create([
                        'invoice_id' => $invoice->id,
                        'company_id'  =>  $invoice->agent->company->id,
                        'client_id' =>  $invoice->client->id,
                        'transaction_date' => Carbon::now(),
                        'amount' => $invoicedetail['task_price'],
                        'status'  => 'paid', 
                        'description'=> 'pay to Invoice:' + $invoiceNumber,
                    ]);

                    $payment = Payment::create([            
                        'transaction_id'=>  $transaction->id,
                        'invoice_id' => $invoice->id,
                        'client_id'=> $invoice->client->id,
                        'agent_id'=> $invoice->agent->id,
                        'payment_date' => Carbon::now(), 
                        'amount'=> $invoicedetail['task_price'],
                        'payment_method' => $request->payment_method,
                        'bank_name' => $request->bank_name,
                        'status'  => 'paid',
                    ]);

                        // Update the accounts receivable entry
                       GeneralLedger::create([
                        'transaction_id'=> $payment->payment_id,
                        'company_id' => $invoice->agent->company->id,
                        'account_id'=>  $receivableAccounts->account_id,
                        'transaction_date' => Carbon::now(), 
                        'description'=> 'Payment Received for Invoice: ' . $invoiceNumber,
                        'debit' => 0,
                        'credit' => $invoicedetail['task_price'],
                        'balance' => $receivableAccounts->balance - $invoicedetail['task_price'],
              
                    ]);

                     // Update the receivable account balance
                     $receivableAccounts->balance -= $invoicedetail['task_price'];
                     $receivableAccounts->save();

                     // Update Cash/Bank Account
                    if ($cashAccount) {
                        $cashAccount->balance += $invoicedetail['task_price']; // Add to cash/bank account
                        $cashAccount->save();
                    }


                } catch (Exception $e) {
                    // Log the error if something goes wrong with a specific task
                    Log::error('Failed to create InvoiceDetails: ' . $e->getMessage());
                    return response()->json(['error' => 'Failed to create InvoiceDetails for task: ' . $invoicedetail['task_description']], 500);
                }
            }
        }

            // Update the invoice status based on the payment received
            $totalPaid = Payment::where('invoice_id', $invoice->id)->sum('amount');
            if ($totalPaid >= $invoice->amount) {
                $invoice->status = 'paid';
            } else {
                $invoice->status = 'partial'; // Change to 'partial' if not fully paid
            }
            $invoice->paid_date = now();
            $invoice->save();

        try {

            $agentPhoneNumber = $invoice->agent->phone_number;
            $agencyPhoneNumber = $invoice->agent->company->phone;

            $whatsAppService = new WhatsAppNotificationService();

            // Notify agent and agency
            $whatsAppService->sendWhatsAppMessage($agentPhoneNumber, "A new payment has been made from citytour.");
            $whatsAppService->sendWhatsAppMessage($agencyPhoneNumber, "A new payment has been made by client XYZ.");
        
            // Handle successful payment
            return redirect()->route('invoice.show', ['invoiceNumber' => $invoice->invoice_number])
                             ->with('status', 'Payment successful! Thank you for your payment.');
        } catch (Exception $e) {
            // Handle payment failure
            return redirect()->back()->with('error', 'Payment failed: ' . $e->getMessage());
        }
    }



}
