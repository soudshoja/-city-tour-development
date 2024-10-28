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
use Illuminate\Support\Facades\Redirect;
use App\Support\PaymentGateway\Tap;

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

    public function create($invoiceNumber, Request $request)
    {

        $request->validate([
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email',
            'client_phone' => 'required|string|max:15',
            // 'selected_items' => 'required|array',
            'total_amount' => 'required|numeric',
            'payment_method' => 'required|string'
        ]);

        $invoice = Invoice::with('agent.company', 'client')->where('invoice_number', $invoiceNumber)->first();

        $payment = Payment::create([
            'client_id' => $invoice->client->id,
            'invoice_id' => $invoice->id,
            'agent_id' => $invoice->agent->id,
            'payment_date' => Carbon::now(),
            'amount' => $request->total_amount,
            'payment_method' => $request->payment_method,
            'status'  => 'pending',
        ]);

        $data = [
            'amount' => $request->total_amount,
            'currency' => 'KWD',
            'save_card' => false,
            'customer' => [
                'first_name' => $request->client_name,
                'email' => $request->client_email,
            ],
            'source' => [
                'id' => 'src_all',
            ],
            'description' => 'Payment for order ',
            'metadata' => [
                'invoice_number' => $invoiceNumber,
                'payment_id' => $payment->id,

            ],
            'redirect' => [
                'url' => route('payment.process'),
            ],
            'post' => [
                'url' => route('payment.webhook'),
            ],
        ];

        if ($clientPhone = $request->client_phone) {
            $data['customer']['phone'] = $clientPhone;
        }

        if ($clientMiddleName = $request->client_middle_name) {
            $data['customer']['middle_name'] = $clientMiddleName;
        }

        if ($clientLastName = $request->client_last_name) {
            $data['customer']['last_name'] = $clientLastName;
        }

        if ($request->selected_items) {
            foreach ($request->selected_items as $key => $item) {
                $selectedItemKey = 'selected_item_' . $key;
                $data['metadata'][$selectedItemKey] = $item;
            }
        }

        $tap = new Tap();

        $response = $tap->createCharge($data);
        if (isset($response['error'])) {
            return redirect()->back()->with('error', $response['error']['description']);
        }

        $payment->payment_reference = $response['id'];
        $payment->status = 'initiate';
        $payment->save();

        return redirect($response['transaction']['url']);
    }

    public function process(Request $request)
    {
        $tap = new Tap();

        $tap_id = $request->tap_id;

        $response = $tap->getCharge($tap_id);

        if (isset($response['errors'])) {

            return Redirect::route('dashboard')->with('error', $response['errors'][0]['description']);
        }

        if ($response['status'] != 'CAPTURED') {
            return Redirect::route('dashboard')->with('error', 'Payment error');
        }

        $clientName = $response['customer']['first_name'];
        $clientEmail = $response['customer']['email'];
        if (isset($response['customer']['phone'])) {
            $clientPhone = $response['customer']['phone'];
        }
        $totalAmount = $response['amount'];
        $paymentId = $response['metadata']['payment_id'];
        $invoiceNumber = $response['metadata']['invoice_number'];

        foreach ($response['metadata'] as $key => $value) {
            if (strpos($key, 'selected_item_') !== false) {
                $selectedItems[] = $value;
            }
        }


        // Fetch the invoice to get payment details
        $invoice = Invoice::with('agent.company', 'client')->where('invoice_number', $invoiceNumber)->first();

        $invoiceDetails = InvoiceDetails::with('task')
            ->whereIn('id', $selectedItems)
            ->get();

        $receivableAccounts = Account::where('name', 'like', '%Receivable%')
            ->where('level', 3)
            ->where('company_id', $invoice->agent->company->id)
            ->first();

        $cashAccount = Account::where('name', 'Cash') // or bank account
            ->where('level', 3)
            ->where('company_id', $invoice->agent->company->id)
            ->first();

        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found.');
        }
        // dd($invoiceDetails);
        if (!empty($invoiceDetails)) {
            // dd($invoiceDetails);
            foreach ($invoiceDetails as $invoicedetail) {
                try {

                    // Create a transaction record first
                    $transaction = Transaction::create([
                        'invoice_id' => $invoice->id,
                        'company_id'  =>  $invoice->agent->company->id,
                        'client_id' =>  $invoice->client->id,
                        'transaction_date' => Carbon::now(),
                        'amount' => $invoicedetail['task_price'],
                        'status'  => 'completed',
                        'description' => 'pay to Invoice:' . $invoiceNumber,
                    ]);

                    $payment = Payment::find($paymentId);
                    $payment->status = 'completed';
                    $payment->transaction_id = $transaction->id;
                    $payment->save();

                    // Update the accounts receivable entry
                    GeneralLedger::create([
                        'transaction_id' => $payment->transaction_id,
                        'company_id' => $invoice->agent->company->id,
                        'account_id' =>  $receivableAccounts->id,
                        'transaction_date' => Carbon::now(),
                        'description' => 'Payment Received for Invoice: ' . $invoiceNumber,
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

        // try {

        //     $agentPhoneNumber = $invoice->agent->phone_number;
        //     $agencyPhoneNumber = $invoice->agent->company->phone;

        //     $whatsAppService = new WhatsAppNotificationService();

        //     // Notify agent and agency
        //     $whatsAppService->sendWhatsAppMessage($agentPhoneNumber, "A new payment has been made from citytour.");
        //     $whatsAppService->sendWhatsAppMessage($agencyPhoneNumber, "A new payment has been made by client XYZ.");

        //     // Handle successful payment
        // } catch (Exception $e) {
        //     // Handle payment failure
        //     return redirect()->back()->with('error', 'Payment failed: ' . $e->getMessage());
        // }
        return redirect()->route('invoice.show', ['invoiceNumber' => $invoice->invoice_number])
            ->with('status', 'Payment successful! Thank you for your payment.');
    }

    public function check($tap_id)
    {
        $tap = new Tap();

        $response = $tap->getCharge($tap_id);

        if (isset($response['errors'])) {
            return response()->json(['error' => $response['errors'][0]['description']], 500);
        }

        return response()->json($response);
    }

    public function webhook(Request $request)
    {
        Log::info('Webhook received: ' . $request->getContent());
    }
}
