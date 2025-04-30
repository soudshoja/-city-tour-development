<?php

namespace App\Http\Controllers;

use App\Enums\ChargeType;
use App\Http\Traits\NotificationTrait;
use App\Services\WhatsAppNotificationService;
use Illuminate\Support\Facades\Log;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\JournalEntry;
use App\Models\TapPayment;
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
use App\Models\Currency;
use App\Models\Role;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Redirect;
use App\Support\PaymentGateway\Tap;
use Google\Rpc\Context\AttributeContext\Response;

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
            'user_id' => $invoice->agent->id,
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
            $voucherSequence = Sequence::create([
                'sequence_for' => 'VOUCHER',
                'current_sequence' => 1
            ]);
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
            'payment_date' => Carbon::now(),
            'amount' => $data['total_amount'],
            'payment_method' => $data['payment_method'],
            'status' => 'pending',
            'payment_reference' => $invoice->id,
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'agent_id' => $invoice->agent_id
        ]);

        if (strtolower($data['payment_method']) === 'tap') {

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
                'description' => 'Payment for invoice: ' . $invoice->id,
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

        if (strtolower($data['payment_method']) === 'myfatoorah') {
            $mfController = new \App\Http\Controllers\MyFatoorahController();

            $queryParams = http_build_query([
                'oid' => $invoice->id,
            ]);

            $payment->status = 'initiate';
            $payment->save();

            return response()->json([
                'success' => 'Redirecting to MyFatoorah',
                'url' => route('myfatoorah.paynow') . '?' . $queryParams
                // 'url' => route('myfatoorah.paynow') . '?' . http_build_query(['oid' => $invoice->id])

            ]);
        }
        return response()->json(['error' => 'Unsupported payment method'], 400);
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

        // $receivableAccount = Account::where('name', 'like', '%Accounts Receivable – Clients%')
        //     ->where('company_id', $invoice->agent->branch->company->id)
        //     ->first();

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

                $selectedtask = Task::where('id', $invoiceDetail->task_id)->first();
                //$selectedtask = Task::where('id', $invoiceDetail['task_id'])->first();
                $supplier = Supplier::where('id', operator: $selectedtask->supplier_id)->first();
                $client = Client::where('id', operator: $selectedtask->client_id)->first();
                $agent = Agent::where('id', operator: $selectedtask->agent_id)->first();

                $receivableAccount = Account::where('name', 'Clients')->first();
                $receivableAccountId = $receivableAccount->id;
                //dd($receivableAccount, $client->name);

                if (!$invoice->agent || !$invoice->agent->branch || !$invoice->agent->branch->company) {
                    return response()->json(['error' => 'Invalid invoice/agent/branch/company structure'], 400);
                }
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
                $payment->account_id = $receivableAccountId;
                $payment->save();

                $dateCreated = Carbon::createFromTimestampMs($response['transaction']['date']['created'])->format('Y-m-d H:i:s');
                $dateCompleted = Carbon::createFromTimestampMs($response['transaction']['date']['completed'])->format('Y-m-d H:i:s');
                $dateTransaction = Carbon::createFromTimestampMs($response['transaction']['date']['transaction'])->format('Y-m-d H:i:s');

                TapPayment::create([
                    'payment_id' =>  $payment->id,
                    'tap_id' =>  $response['id'],
                    'authorization_id' =>  $response['transaction']['authorization_id'],
                    'timezone' => $response['transaction']['timezone'],
                    'expiry_period' => $response['transaction']['expiry']['period'],
                    'expiry_type' => $response['transaction']['expiry']['type'],
                    'amount' => $totalPaidAmount,
                    'currency' => 'KWD',
                    'date_created' => $dateCreated,
                    'date_completed' => $dateCompleted,
                    'date_transaction' => $dateTransaction,
                    'receipt_id' => $response['receipt']['id'],
                    'receipt_email' => $response['receipt']['email'],
                    'receipt_sms' => $response['receipt']['sms'],
                ]);

                // Create record to receivable account (OK)
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $invoice->agent->branch->id,
                    'company_id' => $invoice->agent->branch->company->id,
                    'invoice_id' =>  $invoice->id,
                    'account_id' =>  $receivableAccountId,
                    'invoice_detail_id' =>  $invoiceDetail->id,
                    'transaction_date' => Carbon::now(),
                        'description' => 'Client Pays via '.$bankPaymentFee->name.' by (Assets): ' . $client->name,
                    'debit' => 0,
                    'credit' => $totalPaidAmount,
                        'balance' => $invoiceDetail['task_price']-$totalPaidAmount,
                    'name' =>  $client->name,
                    'type' => 'receivable',
                    'voucher_number' => $payment->voucher_number,
                    'type_reference_id' => $receivableAccountId
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
                            'description' => 'Client Pays by '. $client->name .' via (Assets): ' . $bankPaymentFee->name,
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
                            'description' => 'Record Payment Gateway Charge (Expenses): '. $tapAccount->name,
                        'debit' => $defaultPaymentGatewayFee,
                        'credit' => 0,
                        'balance' => $tapAccount->actual_balance,
                        'name' =>  $tapAccount->name,
                        'type' => 'charges',
                        'type_reference_id' => $tapAccount->id
                    ]);

                    $tapAccount->actual_balance += $defaultPaymentGatewayFee; // Add to expenses account
                    $tapAccount->save();

                        //$selectedtask->status = 'Completed';
                        $selectedtask->status = 'ticketed';
                        $selectedtask->save();

                }


                //dd($e->getMessage());

            } catch (\Exception $e) {
                Log::error('Failed in invoice processing', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

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

    public function processMyFatoorah(array $data)
    {
        $focus = $data['Data']['focusTransaction'];
        $invoiceId = $data['Data']['CustomerReference']; // You stored this in CustomerReference
        $paymentReference = $focus['PaymentId'];
        // $totalPaidAmount = $focus['TransationValue'];
        $totalPaidAmount = floatval(str_replace(',', '', $focus['TransationValue']));
        $totalPaidAmount = round($totalPaidAmount, 2);
        $paymentGateway = $focus['PaymentGateway'];

        // STEP 1: Fetch the invoice
        $invoice = Invoice::with('agent.branch', 'client')->find($invoiceId);
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found.');
        }

        // STEP 2: Fetch related payment
        $payment = Payment::where('invoice_id', $invoice->id)
            ->where('payment_reference', $invoice->id)
            ->where('status', 'initiate')
            ->latest()->first();

        if (!$payment) {
            return redirect()->back()->with('error', 'Payment not found.');
        }

        // STEP 3: Update payment record
        $payment->status = 'completed';
        $payment->completed = 1;
        $payment->payment_reference = $paymentReference;
        $payment->save();

        // STEP 4: Get financial accounts
        $chargeRecord = Charge::where('name', 'LIKE', $paymentGateway)
            ->where('company_id', $invoice->agent->branch->company->id)
            ->first();

        if (!$chargeRecord) {
            return redirect()->back()->with('error', 'Charge account not configured.');
        }

        $bankPaymentFee = Account::find($chargeRecord->acc_fee_bank_id);
        $tapAccount = Account::find($chargeRecord->acc_fee_id);
        $receivableAccount = Account::where('name', 'Clients')->first();

        // STEP 5: Create transaction
        $transaction = Transaction::create([
            'branch_id' => $invoice->agent->branch->id,
            'company_id' => $invoice->agent->branch->company->id,
            'entity_id' => $invoice->agent->branch->company->id,
            'entity_type' => 'company',
            'transaction_type' => 'debit',
            'amount' => $totalPaidAmount,
            'date' => now(),
            'description' => 'Payment via MyFatoorah for Invoice: ' . $invoice->invoice_number,
            'invoice_id' => $invoice->id,
            'reference_type' => 'Invoice',
        ]);

        $invoiceDetail = InvoiceDetail::where('invoice_number', $invoice->invoice_number)->first();
        $client = $invoice->client;

        // Receivable Journal
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'branch_id' => $invoice->agent->branch->id,
            'company_id' => $invoice->agent->branch->company->id,
            'invoice_id' => $invoice->id,
            'account_id' => $receivableAccount->id,
            'invoice_detail_id' => $invoiceDetail->id,
            'transaction_date' => now(),
            'description' => 'Client payment received via MyFatoorah',
            'debit' => 0,
            'credit' => $totalPaidAmount,
            'balance' => $invoiceDetail->task_price - $totalPaidAmount,
            'name' => $client->name,
            'type' => 'receivable',
            'voucher_number' => $payment->voucher_number,
            'type_reference_id' => $receivableAccount->id,
        ]);

        // Bank assets (excluding fee)
        $netAmount = $totalPaidAmount - $chargeRecord->amount;
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'branch_id' => $invoice->agent->branch->id,
            'company_id' => $invoice->agent->branch->company->id,
            'invoice_id' => $invoice->id,
            'invoice_detail_id' => $invoiceDetail->id,
            'account_id' => $bankPaymentFee->id,
            'transaction_date' => now(),
            'description' => 'Net payment received',
            'debit' => $netAmount,
            'credit' => 0,
            'balance' => $invoiceDetail->task_price - $totalPaidAmount,
            'name' => $bankPaymentFee->name,
            'type' => 'bank',
            'voucher_number' => $payment->voucher_number,
            'type_reference_id' => $bankPaymentFee->id,
        ]);
        $bankPaymentFee->actual_balance += $netAmount;
        $bankPaymentFee->save();

        // Fee as expense
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'branch_id' => $invoice->agent->branch->id,
            'company_id' => $invoice->agent->branch->company->id,
            'invoice_id' => $invoice->id,
            'invoice_detail_id' => $invoiceDetail->id,
            'account_id' => $tapAccount->id,
            'transaction_date' => now(),
            'description' => 'MyFatoorah service fee',
            'debit' => $chargeRecord->amount,
            'credit' => 0,
            'balance' => $tapAccount->actual_balance + $chargeRecord->amount,
            'name' => $tapAccount->name,
            'type' => 'charges',
            'voucher_number' => $payment->voucher_number,
            'type_reference_id' => $tapAccount->id,
        ]);
        $tapAccount->actual_balance += $chargeRecord->amount;
        $tapAccount->save();

        // STEP 6: Update invoice status
        $invoice->status = 'paid';
        $invoice->paid_date = now();
        $invoice->save();

        // STEP 7: Update invoice partials (optional based on your use case)
        InvoicePartial::where('invoice_id', $invoice->id)->update([
            'status' => 'paid',
            'payment_id' => $payment->id
        ]);

        $this->storeNotification([
            'user_id' => $invoice->agent->id,
            'title' => 'Payment Successful',
            'message' => 'Payment received via MyFatoorah for invoice ' . $invoice->invoice_number,
        ]);

        return redirect()->route('invoice.show', ['invoiceNumber' => $invoice->invoice_number])
            ->with('status', 'Payment successful via MyFatoorah!');
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

    public function paymentLink(){
        $user = Auth::user();

        if($user->role_id == Role::ADMIN){
            $agents = Agent::all();
            $agentsId = $agents->pluck('id')->toArray();
        }else if($user->role_id == Role::COMPANY){
            $agents = Agent::where('company_id', $user->company_id)->get();
            $agentsId = $agents->pluck('id')->toArray();
        }else if($user->role_id == Role::BRANCH){
            $agents = Agent::where('branch_id', $user->branch_id)->get();
            $agentsId = $agents->pluck('id')->toArray();
        }else if($user->role_id == Role::AGENT){
            $agents = Agent::where('id', $user->id)->get();
            $agentsId = $agents->pluck('id')->toArray();
        }else {
            return redirect()->back()->with('error', 'You are not authorized to view payment links.');
        }

        $clients = Client::whereIn('agent_id', $agentsId)->get();
        $payments = Payment::with('invoice')->orderBy('created_at', 'desc')->get();

        $payments = $payments->filter(function ($payment) use ($agentsId) {
            if($payment->invoice){
                return in_array($payment->invoice->agent_id, $agentsId);
            }
            return in_array($payment->agent_id, $agentsId);
        })->values();

        // $invoice = Invoice::where('id', $payment->invoice_id)->first();

        // if (!$invoice) {
        //     return redirect()->back()->with('error', 'Invoice not found.');
        // }

        return view('payment.link.index', compact(
            'payments',
            'clients',
            'agents'
        ));
    }

    public function paymentCreateLink()
    {
        $user = Auth::user();
        if($user->role_id == Role::ADMIN){
            $agents = Agent::all();
            $agentsId = $agents->pluck('id')->toArray();
        }else if($user->role_id == Role::COMPANY){
            $agents = Agent::where('company_id', $user->company_id)->get();
            $agentsId = $agents->pluck('id')->toArray();
        }else if($user->role_id == Role::BRANCH){
            $agents = Agent::where('branch_id', $user->branch_id)->get();
            $agentsId = $agents->pluck('id')->toArray();
        }else if($user->role_id == Role::AGENT){
            $agents = Agent::where('id', $user->id)->get();
            $agentsId = $agents->pluck('id')->toArray();
        }else {
            return redirect()->back()->with('error', 'You are not authorized to create payment links.');
        }

        $clients = Client::whereIn('agent_id', $agentsId)->get();
        $invoices = Invoice::all();
        $payments = Payment::all();
        $currencies = Currency::all();
        $paymentGateways = Charge::where('type', ChargeType::PAYMENT_GATEWAY)
            ->get();

        return view('payment.link.create', compact(
            'payments',
            'clients',
            'agents',
            'invoices',
            'currencies',
            'paymentGateways'
        ));
    }

    public function paymentStoreLinkProcess(Request $request)
    {
      $request->validate([
        'payment_gateway' => 'required',
        'amount' => 'required|numeric',
        'notes' => 'nullable|string|max:255',
        'client_id' => 'required',
        'agent_id' => 'nullable',
        'invoice_id' => 'nullable'
      ]); 

        $voucherSequence = Sequence::where('sequence_for', 'VOUCHER')->lockForUpdate()->first();

        if (!$voucherSequence) {
            $voucherSequence = Sequence::create([
                'sequence_for' => 'VOUCHER',
                'current_sequence' => 1
            ]);
        }

        $client = Client::where('id', $request->client_id)->first();

        if(!$client) {
            return [
                'status' => 'error',
                'message' => 'Client cannot be found',
            ];
        }

        $agent = Agent::where('id', $request->agent_id)->first();

        if(!$agent) {
            return [
                'status' => 'error',
                'message' => 'Agent cannot be found'
            ];
        }

        $currentSequence = $voucherSequence->current_sequence;
        $voucherNumber = $this->generateVoucherNumber($currentSequence);
        try{
            $voucherSequence->current_sequence++;
            $voucherSequence->save();
        } catch (Exception $e) {
            logger('Failed to save voucher sequence', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        try{
            $data = [
                'voucher_number' => $voucherNumber,
                'from' => $client->name,
                'pay_to' => $agent->branch->company->name,
                'currency' => 'KWD',
                'payment_date' => Carbon::now(),
                'amount' => $request->amount,
                'payment_method' => $request->payment_gateway,
                'status' => 'pending',
                'client_id' => $client->id,
                'agent_id' => $agent->id,
                'notes' => $request->notes,
            ];

            if ($request->invoice_id !== null) {
                $data['invoice_id'] = $request->invoice_id;
            }

            $payment = Payment::create($data);
       
        } catch (Exception $e){
            logger('Failed to create payment', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Payment Link Created',
            'data' => $payment
        ];
    }

    public function paymentStoreLink(Request $request)
    {
        $response = $this->paymentStoreLinkProcess($request);
        if ($response['status'] === 'error') {
            return redirect()->back()->with('error', $response['message']);
        }

        return redirect()->route('payment.link.index')->with('success', 'Payment link created successfully!');
    }

    public function paymentShowLink($paymentId)
    {
        $payment = Payment::with('agent', 'client')->where('id', $paymentId)->first();

        if (!$payment) {
            return auth()->user() ? redirect()->route('payment.link.index') : abort(404);
        }

        if(!$payment->client){
            return auth()->user() ? redirect()->route('payment.link.index') : abort(404);
        }

        if(!$payment->agent){
            return auth()->user() ? redirect()->route('payment.link.index') : abort(404);
        }

        return view('payment.link.show', compact('payment'));
    }

    public function paymentLinkInitiate(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|exists:payments,id',
        ]);

        $payment = Payment::with('invoice')->find($request->payment_id);

        if (!$payment) {
            return redirect()->back()->with('error', 'Payment not found.');
        }

        $process = 'topup';
        if($payment->invoice){
            $process = 'invoice';
        }
        $paymentMethod = $payment->payment_method;

        if (strtolower($paymentMethod) === 'tap') {
            $requestTap = [
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'save_card' => false,
                'customer' => [
                    'first_name' => $payment->client->name,
                    'email' => $payment->client->email,
                ],
                'source' => [
                    'id' => 'src_all',
                ],
                'description' => 'Payment for' . $payment->voucher_number,
                'metadata' => [
                    'voucher_number' => $payment->voucher_number,
                    'payment_id' => $payment->id,
                    'payment_gateway' => $paymentMethod,
                    'process' => $process,
                ],
                'redirect' => [
                    'url' => route('payment.link.process'),
                ],
            ];

            if(config('app.env') == 'production'){
                $requestTap['post'] = [
                    'url' => route('payment.link.webhook'),
                ];
            }

            $tap = new Tap();
            $response = $tap->createCharge($requestTap);

            if (isset($response['errors'])) {
                return redirect()->back()->with('error', $response['errors'][0]['description']);
            }

            return redirect($response['transaction']['url']);
        }

        return redirect()->route('payment.link.index')->with('success', 'Payment initiated successfully!');
    }

    public function paymentLinkProcess(Request $request)
    {
        $tapId = $request->tap_id;

        $tap = new Tap();

        $response = $tap->getCharge($tapId);

        if (isset($response['errors'])) {
            return redirect()->back()->with('error', $response['errors'][0]['description']);
        }

        if ($response['status'] != 'CAPTURED') {
            return redirect()->back()->with('error', 'Payment error');
        }

        $paymentId = $response['metadata']['payment_id'];

        $payment = Payment::with('invoice')->find($paymentId);


        if (!$payment) {
            logger('Payment id returned from tap not found', [
                'payment_id' => $paymentId,
                'tap_id' => $tapId,
            ]);
            return redirect()->back()->with('error', 'Payment not found.');
        }

        $process = $response['metadata']['process'];

        if($process == 'topup'){
            $clientController = new ClientController;
    
            $addCreditResponse = $clientController->addCredit($payment);
            
            if(isset($addCreditResponse['error'])) {
                logger('Failed to add credit to client', [
                    'message' => $addCreditResponse['error'],
                    'payment_id' => $paymentId,
                ]);
                return redirect()->back()->with('error', 'Payment cannot be updated');
            }
        } else if ($process == 'invoice'){
            $invoice = Invoice::where('id', $payment->invoice_id)->first();
            if (!$invoice) {
                return redirect()->back()->with('error', 'Invoice not found.');
            }
        } else {
            return redirect()->back()->with('error', 'Invalid process type.');

        }
        
        try {
            $payment->status = 'completed';
            $payment->completed = 1;
            $payment->payment_reference = $response['id'];
            $payment->save();

            if($process == 'invoice'){
                $invoice->status = 'paid';
                $invoice->paid_date = now();
                $invoice->save();
            }

        } catch (Exception $e) {
            logger('Failed to update payment status', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('payment.link.index')->with('error', 'Payment cannot be updated');
        }

        return redirect()->route('payment.link.index')->with('success', 'Payment successful!');
    }

    public function paymentUpdateLink($paymentId, Request $request)
    {
        $payment = Payment::find($paymentId);

        if (!$payment) {
            return redirect()->back()->with('error', 'Payment not found.');
        }

        $payment->update($request->all());
        $payment->save();
        return redirect()->route('payment.link.index')->with('success', 'Payment link updated successfully!');
    }

    public function shareLink($paymentId)
    {

    }
}
