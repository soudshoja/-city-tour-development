<?php

namespace App\Http\Controllers;

use App\Http\Traits\Notification;
use App\Services\WhatsAppNotificationService;
use Illuminate\Support\Facades\Log;
use App\Models\InvoiceDetail;
use App\Models\GeneralLedger;
use Illuminate\Support\Facades\Auth;
use App\Models\Sequence;
use App\Models\Supplier;
use App\Models\Client;
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
    use Notification;

    public function index(string $invoiceNumber)
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

        $invoice = Invoice::with('agent.branch', 'client')->where('invoice_number', $invoiceNumber)->first();

        $data = [
            'invoice' => $invoice,
            'client_name' => $request->client_name,
            'client_email' => $request->client_email,
            'client_phone' => $request->client_phone,
            'total_amount' => $request->total_amount,
            'payment_method' => $request->payment_method,
            'selected_items' => $request->selected_items,
            'redirect_url' => route('payment.process'),
            'webhook_url' => route('payment.webhook'),
        ];


        if ($clientMiddleName = $request->client_middle_name) {
            $data['client_middle_name'] = $clientMiddleName;
        }

        if ($clientLastName = $request->client_last_name) {
            $data['client_last_name'] = $clientLastName;
        }

        if ($clientMiddleName = $request->client_middle_name) {
            $data['customer']['middle_name'] = $clientMiddleName;
        }

        if ($request->selected_items) {
            $data['selected_items'] = $request->selected_items;
        }

        $response = json_decode($this->initiatePayment($data)->content(), true);

        if (isset($response['error'])) {
            return redirect()->back()->with('error', $response['error']);
        }

        $this->storeNotification([
            'user_id' => Auth::id(),
            'title' => 'Payment Initiated',
            'message' => 'Payment has been initiated for invoice: ' . $invoiceNumber,
        ]);

        return redirect($response['url']);
    }

    private function generateVoucherNumber($sequence)
    {
        $year = now()->year;
        return sprintf('VOU-%s-%05d', $year, $sequence);
    }



    public function initiatePayment($data)
    {

                    
        $voucherSequence = Sequence::where('sequence_for', 'VOUCHER')->lockForUpdate()->first();

        if (!$voucherSequence) {
            $voucherSequence = Sequence::create(['current_sequence' => 1]);
        }

        $currentSequence = $voucherSequence->current_sequence;
        $voucherNumber = $this->generateVoucherNumber($currentSequence);

        $voucherSequence->current_sequence++;
        $voucherSequence->save();

        $invoice = $data['invoice'];


        $payment = Payment::create([
            'voucher_number' => $voucherNumber,
            'from' => $invoice->client->name,
            'pay_to' => $invoice->agent->branch->company->name,
            'currency' => 'KWD',
            'payment_date' =>  Carbon::now(),
            'amount' =>  $data['total_amount'],
            'payment_method' => $data['payment_method'],
            'status'  => 'pending',
            'payment_reference' => $invoice->id,
            'invoice_id' => $invoice->id,
        ]);

        $requestTap = [
            'amount' => $data['total_amount'],
            'currency' => 'KWD',
            'save_card' => false,
            'customer' => [
                'first_name' => $data['client_name'],
                'email' => $data['client_email'],
            ],
            'source' => [
                'id' => 'src_all',
            ],
            'description' => 'Payment for order ',
            'metadata' => [
                'invoice_number' => $invoice->invoice_number,
                'payment_id' => $payment->id,
            ],
            'redirect' => [
                'url' => $data['redirect_url'],
            ],
            'post' => [
                'url' => $data['webhook_url'],
            ],
        ];

        if (isset($data['selected_items'])) {
            foreach ($data['selected_items'] as $key => $item) {
                $selectedItemKey = 'selected_item_' . $key;
                $data['metadata'][$selectedItemKey] = $item;
            }
        }

        $tap = new Tap();

        $response = $tap->createCharge($requestTap);
        if (isset($response['error'])) {
            return response()->json(['error' => $response['error']['description']], 500);
        }

        $payment->payment_reference = $response['id'];
        $payment->status = 'initiate';
        $payment->save();

        return response()->json([
            'success' => 'Payment initiated successfully',
            'url' => $response['transaction']['url'],
        ]);
    }

    public function process(Request $request)
    {
        $tap = new Tap();

        $tap_id = $request->tap_id;

        $response = $tap->getCharge($tap_id);

        if (isset($response['errors'])) {

            $this->storeNotification([
                'user_id' => Auth::id(),
                'title' => 'Payment Failed',
                'message' => 'Payment failed: ' . $response['errors'][0]['description'],
            ]);

            return Redirect::route('dashboard')->with('error', $response['errors'][0]['description']);
        }

        if ($response['status'] != 'CAPTURED') {

            $this->storeNotification([
                'user_id' => Auth::id(),
                'title' => 'Payment Failed',
                'message' => 'Payment failed: ' . $response['status'],
            ]);

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

        $this->storeNotification([
            'user_id' => Auth::id(),
            'title' => 'Payment Successful',
            'message' => 'Payment successful for invoice: ' . $invoiceNumber,
        ]);

        // Fetch the invoice to get payment details
        $invoice = Invoice::with('agent.branch', 'client')->where('invoice_number', $invoiceNumber)->first();

        $invoiceDetails = InvoiceDetail::with('task')
            ->where('invoice_number', $invoiceNumber)
            ->get();

        $receivableAccount = Account::where('name', 'like', '%Receivable%')
            ->where('company_id', $invoice->agent->company->id)
            ->first();

        Log::info('company_id:', ['company_id' => $invoice->agent->company->id]);

        if ($receivableAccount) {
            $filteredReceivableChildAccount = $receivableAccount->children()
                ->where('reference_id', $invoice->client->id) // Filter by child reference_id
                ->first(); // Get the first matching child account
            Log::info('filteredReceivableChildAccount:', ['filteredReceivableChildAccount' => $filteredReceivableChildAccount]);
            $ReceivablechildAccountId = $filteredReceivableChildAccount ? $filteredReceivableChildAccount->id : null;
        } else {
            $ReceivablechildAccountId = null; // Handle case when no parent account is found
        }


        $bankAccount = Account::where('name', 'Payment Gateway') // or bank account
            ->where('company_id', $invoice->agent->company->id)
            ->first();



        $tapAccount = Account::where('name', 'Tap Charges') // or bank account
            ->where('company_id', $invoice->agent->company->id)
            ->first();


        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found.');
        }
        // dd($invoiceDetails);
        if (!empty($invoiceDetails)) {
            // dd($invoiceDetails);
            foreach ($invoiceDetails as $invoiceDetail) {
                try {

                    $selectedtask = Task::where('id', $invoiceDetail['task_id'])->first();
                    $supplier = Supplier::where('id', operator: $selectedtask->supplier_id)->first();
                    $client = Client::where('id', operator: $selectedtask->client_id)->first();
                    $agent = Agent::where('id', operator: $selectedtask->agent_id)->first();
                    // Create a transaction record first
                    $transaction = Transaction::create([
                        'entity_id' =>  $invoice->agent->company->id,
                        'entity_type' => 'company',
                        'transaction_type' => 'debit',
                        'amount'=> $invoiceDetail['task_price'],
                        'date'=> Carbon::now(),
                        'description'=> 'pay to Invoice:' . $invoiceNumber,
                        'invoice_id'=> $invoice->id,
                        'reference_type' =>'Invoice', 
                    ]);

                    $payment = Payment::find($paymentId);
                    $payment->status = 'completed';
                    $payment->account_id = $filteredReceivableChildAccount->id;
                    $payment->save();

                    // // Update the accounts receivable entry
                    // GeneralLedger::create([
                    //     'transaction_id' => $transaction->id,
                    //     'company_id' => $invoice->agent->company->id,
                    //     'account_id' =>  $filteredReceivableChildAccount->id,
                    //     'invoice_id' =>  $invoice->id,
                    //     'transaction_date' => Carbon::now(),
                    //     'description' => 'Payment received from: ' . $client->name,
                    //     'debit' => $invoiceDetail['task_price'],
                    //     'credit' =>0,
                    //     'balance' => $invoiceDetail['task_price'],
                    //     'name' =>  $client->name,
                    //     'type' => 'receivable',
                    //     'voucher_number' => $payment->voucher_number,
                    // ]);

                    // Update the receivable account balance
                    $filteredReceivableChildAccount->actual_balance -= $invoiceDetail['task_price'];
                    $filteredReceivableChildAccount->save();


                    // Update Cash/Bank Account
                    if ($bankAccount) {
                        GeneralLedger::create([
                            'transaction_id' => $transaction->id,
                            'company_id' => $invoice->agent->company->id,
                            'account_id' =>  $bankAccount->id,
                            'invoice_id' =>  $invoice->id,
                            'invoice_detail_id' =>  $invoiceDetail->id,
                            'transaction_date' => Carbon::now(),
                            'description' => 'Payment transfered to: ' . $bankAccount->name,
                            'debit' => $invoiceDetail['task_price'],
                            'credit' =>0,
                            'balance' => $invoiceDetail['task_price'],
                            'name' =>  $bankAccount->name,
                            'type' => 'bank',
                            'voucher_number' => $payment->voucher_number,
                        ]);

                        $bankAccount->actual_balance += $invoiceDetail['task_price']; // Add to cash/bank account
                        $bankAccount->save();
                    }

                    if ($tapAccount) {
                        GeneralLedger::create([
                            'transaction_id' => $payment->id,
                            'company_id' => $invoice->agent->company->id,
                            'account_id' =>  $tapAccount->id,
                            'invoice_id' =>  $invoice->id,
                            'invoice_detail_id' =>  $invoiceDetail->id,
                            'voucher_number' => $payment->voucher_number,
                            'transaction_date' => Carbon::now(),
                            'description' => 'Payment Charged For:'. $tapAccount->name,
                            'debit' => 0,
                            'credit' => 0.35,
                            'balance' => $tapAccount->actual_balance += 0.35,
                            'name' =>  $tapAccount->name,
                            'type' => 'charges',
                        ]);

                        $tapAccount->actual_balance += 0.35; // Add to expenses account
                        $tapAccount->save();
                    }


                    $selectedtask->status = 'Completed';
                    $selectedtask->save();

                } catch (Exception $e) {
                    // Log the error if something goes wrong with a specific task
                    Log::error('Failed to create InvoiceDetails: ' . $e->getMessage());
                    return response()->json(['error' => 'Failed to create InvoiceDetails for task: ' . $invoiceDetail['task_description']], 500);
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

    public function paymentClientRedirect($invoiceNumber)
    {
        $invoice = Invoice::where('invoice_number', $invoiceNumber)->first();

        $data = [
            'invoice' => $invoice,
            'total_amount' => $invoice->amount,
            'payment_method' => 'debit_card', // change to get from tap check charges later
            'client_name' => $invoice->client->name,
            'client_email' => $invoice->client->email,
            'invoice_number' => $invoice->invoice_number,
            'redirect_url' => route('payment.process'),
            'webhook_url' => route('payment.webhook'),
        ];

        $response = json_decode($this->initiatePayment($data)->content(), true);

        if (isset($response['error'])) {
            return Redirect::route('payment.error', ['invoiceNumber' => $invoiceNumber]);
        }

        return redirect($response['url']);
    }

    public function paymentClientProcess(Request $request)
    {
        $tap = new Tap();

        $tap_id = $request->tap_id;

        $response = $tap->getCharge($tap_id);

        if (isset($response['errors'])) {
            return view('clients.response', ['status' => 'error', 'message' => 'Payment error']);
        }

        if ($response['status'] != 'CAPTURED') {
            return view('clients.response', ['status' => 'error', 'message' => 'Payment error']);
        }

        return view('clients.response', ['status' => 'success', 'message' => 'Payment successful!']);
    }
}
