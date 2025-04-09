<?php

namespace App\Http\Controllers;

use App\Http\Traits\NotificationTrait;
use App\Services\WhatsAppNotificationService;
use Illuminate\Support\Facades\Log;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\Auth;
use App\Models\Sequence;
use App\Models\Supplier;
use App\Models\Client;
use App\Models\Agent;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Account;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Charge;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Redirect;
use App\Support\PaymentGateway\Tap;

class PaymentController extends Controller
{
    use NotificationTrait;

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

    public function showPaymentPage()
    {
        $invoice = [
            'number' => 'INV12345',
            'amount' => 1000.00, // Example amount
        ];
        $paymentGateways = ['PayPal', 'Stripe', 'Bank Transfer'];

        return view('payment.choose', compact('invoice', 'paymentGateways'));
    }

    public function create($invoiceNumber, Request $request)
    {
        $request->validate([
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email',
            'client_phone' => 'required|string|max:15',
            // 'selected_items' => 'required|array',
            'total_amount' => 'required|numeric',
            'payment_method' => 'required|string',
            'invoice_partial_id' => 'required|array'
        ]);

        $invoice = Invoice::with('agent.branch', 'client')->where('invoice_number', $invoiceNumber)->first();
            
         // Process selected partials (if needed for further logic)
       $selectedPartials = InvoicePartial::whereIn('id', $request->invoice_partial_id)->get();


        $data = [
            'invoice' => $invoice,
            'client_name' => $request->client_name,
            'client_email' => $request->client_email,
            'client_phone' => $request->client_phone,
            'total_amount' => $request->total_amount,
            'payment_method' => $request->payment_method,
            'invoice_partial_id' =>  $request->invoice_partial_id,
            'selected_partials' => $selectedPartials,
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
        $selectedPartials = $data['selected_partials'];

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
                'payment_gateway' => $payment->payment_method,
                'invoice_partial_id' => json_encode($data['invoice_partial_id']),
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
        Log::info('requestTap', ['requestTap' => $requestTap]);
        $response = $tap->createCharge($requestTap);
        
        logger('response', ['response' => $response]);

        if (isset($response['errors'])) {
            return response()->json(['error' => $response['errors'][0]['description']], 500);
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
    {   //dd($request);
        Log::info('process:', ['process' => $request]);
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
        $paymentGateway = $response['metadata']['payment_gateway'];
       // $invoicePartialIds = $response['metadata']['invoice_partial_id'];
        $invoicePartialIds = json_decode($response['metadata']['invoice_partial_id'], true);
        
        //dd($paymentGateway);

        $this->storeNotification([
            'user_id' => Auth::id(),
            'title' => 'Payment Successful',
            'message' => 'Payment successful for invoice: ' . $invoiceNumber,
        ]);

        $totalPaidAmount = $response['amount'];

        // Fetch the invoice to get payment details
        $invoice = Invoice::with('agent.branch', 'client')->where('invoice_number', $invoiceNumber)->first();

        $invoiceDetails = InvoiceDetail::with('task')
            ->where('invoice_number', $invoiceNumber)
            ->get();

        $receivableAccount = Account::where('name', 'like', '%Accounts Receivable – Clients%')
            ->where('company_id', $invoice->agent->branch->company->id)
            ->first();

        Log::info('company_id:', ['company_id' => $invoice->agent->branch->company->id]);


        $bankAccount = Account::where('name', 'Payment Gateway') // or bank account
            ->where('company_id', $invoice->agent->branch->company->id)
            ->first();

        $chargeRecord = Charge::where('name', 'LIKE', $paymentGateway)
            ->where('company_id', $invoice->agent->branch->company->id)
            ->select('amount', 'acc_bank_id', 'acc_fee_bank_id', 'acc_fee_id')
            ->first(); 

        if ($chargeRecord) {
            $defaultPaymentGatewayFee = $chargeRecord->amount;
            $coaBankIdRec = $chargeRecord->acc_bank_id; //COA (Assets) for Debited Bank Account
            $coaFeeIdRec = $chargeRecord->acc_fee_id; //COA (Expenses) for Payment Gateway Fee
            $coaBankFeeIdRec = $chargeRecord->acc_fee_bank_id; //COA (Assets) for Bank Account for the selected Payment Gateway

            $bankAccountAccRecord = Account::where('id', $coaBankIdRec)
            ->where('company_id', $invoice->agent->branch->company->id)
            ->first();

            $tapAccount = Account::where('id', $coaFeeIdRec)
            ->where('company_id', $invoice->agent->branch->company->id)
            ->first();

            $bankPaymentFee = Account::where('id', $coaBankFeeIdRec)
            ->where('company_id', $invoice->agent->branch->company->id)
            ->first();

            //dd($bankAccountAccRecord->id,$tapAccount->id,$bankPaymentFee->id);
        }


        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found.');
        }
        //dd($invoiceDetails);

        if (!empty($invoiceDetails)) {

            // dd($invoiceDetails);
            //foreach ($invoiceDetails as $invoiceDetail) {
                try {

                    $invoiceDetail = $invoiceDetails->first();

                    // Check if there's at least one invoice detail to process
                    if (!$invoiceDetail) {
                        return response()->json(['error' => 'No invoice details found'], 400);
                    }

                    $selectedtask = Task::where('id', $invoiceDetail['task_id'])->first();
                    $supplier = Supplier::where('id', operator: $selectedtask->supplier_id)->first();
                    $client = Client::where('id', operator: $selectedtask->client_id)->first();
                    $agent = Agent::where('id', operator: $selectedtask->agent_id)->first();

                    // Create a transaction record first
                    $transaction = Transaction::create([
                        'branch_id' =>  $invoice->agent->branch->id,
                        'company_id' =>  $invoice->agent->branch->company->id,
                        'entity_id' =>  $invoice->agent->branch->company->id,
                        'entity_type' => 'company',
                        'transaction_type' => 'debit',
                        'amount'=> $totalPaidAmount,
                        'date'=> Carbon::now(),
                        'description'=> 'Pay to Invoice:' . $invoiceNumber,
                        'invoice_id'=> $invoice->id,
                        'reference_type' =>'Invoice', 
                    ]);
                    
                    $payment = Payment::find($paymentId);
                    $payment->status = 'completed';
                    $payment->completed = '0';
                    $payment->account_id = $receivableAccount->id;
                    $payment->save();


                    // Create record to receivable account (OK)
                    JournalEntry::create([
                        'transaction_id' => $transaction->id,
                        'branch_id' => $invoice->agent->branch->id,
                        'company_id' => $invoice->agent->branch->company->id,
                        'invoice_id' =>  $invoice->id,
                        'account_id' =>  $receivableAccount->id,
                        'invoice_detail_id' =>  $invoiceDetail->id,
                        'transaction_date' => Carbon::now(),
                        'description' => 'Payment received from: ' . $client->name,
                        'debit' => 0,
                        'credit' => $totalPaidAmount,
                        'balance' => $invoiceDetail['task_price']-$totalPaidAmount,
                        'name' =>  $client->name,
                        'type' => 'receivable',
                        'voucher_number' => $payment->voucher_number,
                        'type_reference_id' => $receivableAccount->id
                    ]);

                    
                    // Create record to payment_gateway assets coa account (OK)
                    if ($bankPaymentFee) {
                        JournalEntry::create([
                            'transaction_id' => $transaction->id,
                            'company_id' => $invoice->agent->branch->company->id,
                            'branch_id' => $invoice->agent->branch->id,
                            'account_id' =>  $bankPaymentFee->id,
                            'invoice_id' =>  $invoice->id,
                            'invoice_detail_id' =>  $invoiceDetail->id,
                            'transaction_date' => Carbon::now(),
                            'description' => 'Payment transfered to: ' . $bankPaymentFee->name,
                            'debit' => $totalPaidAmount-$defaultPaymentGatewayFee,
                            'credit' =>0,
                            'balance' => $invoiceDetail['task_price']-$totalPaidAmount, 
                            'name' =>  $bankPaymentFee->name,
                            'type' => 'bank',
                            'voucher_number' => $payment->voucher_number,
                            'type_reference_id' => $bankPaymentFee->id
                        ]);

                        $bankPaymentFee->actual_balance += $invoiceDetail['task_price']; // Add to cash/bank account
                        $bankPaymentFee->save();

                        
                    }
                    //dd($transaction->id,$bankAccountAccRecord);
                    
                    // Create record to payment_gateway expense coa account (OK)
                    $tapAccount->actual_balance += $defaultPaymentGatewayFee;

                    if ($tapAccount) {
                        JournalEntry::create([
                            'transaction_id' => $transaction->id,
                            'company_id' => $invoice->agent->branch->company->id,
                            'branch_id' => $invoice->agent->branch->id,
                            'account_id' =>  $tapAccount->id,
                            'invoice_id' =>  $invoice->id,
                            'invoice_detail_id' =>  $invoiceDetail->id,
                            'voucher_number' => $payment->voucher_number,
                            'transaction_date' => Carbon::now(),
                            'description' => 'Payment gateway charged by: '. $tapAccount->name,
                            'debit' => $defaultPaymentGatewayFee,
                            'credit' => 0,
                            'balance' => $tapAccount->actual_balance,
                            'name' =>  $tapAccount->name,
                            'type' => 'charges',
                            'type_reference_id' => $tapAccount->id
                        ]);

                        $tapAccount->actual_balance += $defaultPaymentGatewayFee; // Add to expenses account
                        $tapAccount->save();

                    }


                    $selectedtask->status = 'Completed';
                    $selectedtask->save();

                    //dd($e->getMessage());

                } catch (Exception $e) {
                    // Log the error if something goes wrong with a specific task
                    Log::error('Failed to create InvoiceDetails: ' . $e->getMessage());
                    return response()->json(['error' => 'Failed to create InvoiceDetails for task: ' . $invoiceDetail['task_description']], 500);
                }
            //}
        }


        $selectedPartials = InvoicePartial::whereIn('id', $invoicePartialIds)->get();

        foreach ($selectedPartials as $invoicePartial) {
            $invoicePartial->payment_id = $payment->id; // Save the payment ID to each partial
            $invoicePartial->status = 'paid'; 
            $invoicePartial->save();
        }
        Log::info('selectedPartials:', ['selectedPartials' => $selectedPartials]);
        $invoicePartials = InvoicePartial::where('invoice_id', $invoice->id)->get();
        // Update the invoice status based on the payment received
        Log::info('invoicePartials:', ['invoicePartials' => $invoicePartials]);
        $paidCount = $invoicePartials->where('status', 'paid')->count();
        Log::info('paidCount:', ['paidCount' => $paidCount]);
        // Determine the invoice status based on the number of paid partials
        if ($paidCount === $invoicePartials->count()) {
            $invoice->status = 'paid'; // All partials are paid
        } elseif ($paidCount > 0) {
            $invoice->status = 'partial'; // Some partials are paid
        } else {
            $invoice->status = 'unpaid'; // No partials are paid
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
            'payment_method' => 'payment_gateway', // change to get from tap check charges later
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
