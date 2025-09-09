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
use App\Models\Branch;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\Charge;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Credit;
use App\Models\MyFatoorahPayment;
use App\Services\ChargeService;
use Carbon\Carbon;
use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Http;
use App\Support\PaymentGateway\Tap;
use App\Support\PaymentGateway\MyFatoorah;
use App\Mail\PaymentLinkEmail;
use MyFatoorah\Library\API\Payment\MyFatoorahPaymentEmbedded;
use MyFatoorah\Library\API\Payment\MyFatoorahPaymentStatus;
use Google\Rpc\Context\AttributeContext\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Arr;
use App\Http\Controllers\ClientController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use App\Services\GatewayConfigService;
use App\Support\PaymentGateway\UPayment;
use Illuminate\Support\Facades\Cache;

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

    // public function showPaymentPage()
    // {
    //     $invoice = [
    //         'number' => 'INV12345',
    //         'amount' => 1000.00, // Example amount
    //     ];
    //     $paymentGateways = ['PayPal', 'Stripe', 'Bank Transfer'];

    //     return view('payment.choose', compact('invoice', 'paymentGateways'));
    // }

    public function create($companyId, $invoiceNumber, Request $request)
    {
        $request->validate([
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email',
            'client_phone' => 'required|string|max:15',
            'total_amount' => 'required|numeric',
            'payment_gateway' => 'required|string',
            'payment_method' => 'nullable|string',
            'invoice_partial_id' => 'required|array'
        ]);
       
        Log::info('Received payment request', $request->all());

        $invoice = Invoice::with(['agent.branch', 'client'])
            ->where('invoice_number', $invoiceNumber)
            ->whereHas('agent.branch', fn($q) => $q->where('company_id', $companyId))
            ->first();

        if(!$invoice){
            return auth()->user() ? redirect()->back()->with('error', 'Invoice not found!') : abort(404, 'Invoice not found!');
        }

        if(!$invoice->client){
            return auth()->user() ? redirect()->back()->with('error', 'Client not found for this invoice!') : abort(404, 'Client not found for this invoice!');
        }

        $client = $invoice->client;

        $data = [
            'invoice' => $invoice,
            'client_id' => $client->id,
            'client_name' => $client->full_name,
            'client_email' => $client->email,
            'client_phone' => $client->phone,
            'total_amount' => $request->total_amount,
            'payment_gateway' => $request->payment_gateway,
            'payment_method' => $request->payment_method,
            'invoice_partial_id' =>  $request->invoice_partial_id,
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

        $response = json_decode($this->initiatePayment($data)->content(), true);

        if (isset($response['error'])) {
            if(auth()->user()){
                return redirect()->back()->with('error', $response['error']);
            }
            return abort(400);
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


    public function initiatePayment($data) : JsonResponse
    {
        $invoice = $data['invoice'];

        $company = $invoice->agent->branch->company;

        if(!$company){
            Log::error('Company not found for the invoice', ['invoice_id' => $invoice->id]);

            return response()->json(['error' => 'Company not found for the invoice.'], 500);
        }

        $invoicePartialIds = $data['invoice_partial_id'] ?? [];

        $selectedPartials = InvoicePartial::whereIn('id', $invoicePartialIds)->get();

        if ($selectedPartials->isEmpty()) {
            return response()->json(['error' => 'No invoice partials selected for payment.'], 400);
        }

        $baseAmount = $selectedPartials->sum('amount');
        $companyId = $invoice->agent->branch->company_id;

        $voucherSequence = Sequence::firstOrCreate(['company_id' => $companyId], ['current_sequence' => 1]);
        $currentSequence = $voucherSequence->current_sequence;
        $voucherNumber = $this->generateVoucherNumber($currentSequence);
        $voucherSequence->current_sequence++;
        $voucherSequence->save();

        $finalAmount = $data['total_amount'];
      
        if ($invoice->payment_type === 'partial' || $invoice->payment_type === 'split') {
            Payment::where('invoice_id', $invoice->id)
                ->whereIn('status', ['initiate', 'pending'])
                ->delete();

            Log::info('Deleted previous uncompleted payments for partial invoice.', ['invoice_id' => $invoice->id]);
        } else {
            $existingPayment = Payment::where('invoice_id', $invoice->id)
                ->whereIn('status', ['initiate', 'pending'])
                ->whereNotNull('payment_url')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($existingPayment) {
                if ($existingPayment->expiry_date && Carbon::parse($existingPayment->expiry_date)->isPast()) {
                    Log::info('Found an expired payment link. A new one will be generated.', ['payment_id' => $existingPayment->id]);
                    $existingPayment->delete();
                } else {
                    Log::info('Reusing existing payment link.', ['payment_id' => $existingPayment->id, 'url' => $existingPayment->payment_url]);
                    return response()->json([
                        'success' => 'Reusing existing payment link.',
                        'url' => $existingPayment->payment_url,
                    ]);
                }
            }
        }

        $payment = Payment::create([
            'voucher_number' => $voucherNumber,
            'from' => $invoice->client->full_name,
            'pay_to' => $invoice->agent->branch->company->name,
            'currency' => 'KWD',
            'payment_date' => Carbon::now(),
            'amount' => $baseAmount,
            'payment_gateway' => $data['payment_gateway'],
            'payment_method_id' => $data['payment_method'],
            'status' => 'pending',
            'payment_reference' => $invoice->id,
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'agent_id' => $invoice->agent_id
        ]);

        $paymentReference = null;
        $paymentUrl = null;
        $expiryDate = now()->addDays(2);

        // if(config('app.env') === 'local'){
        //     $data['payment_gateway'] = 'upayment'; // for testing
        // }

        if (strtolower($data['payment_gateway']) === 'tap') {

            $tap = new Tap();

            $requestTap = new Request([
                'finalAmount' => $finalAmount,
                'client_name' => $data['client_name'],
                'client_email' => $data['client_email'],
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'payment_id' => $payment->id,
                'payment_gateway' => $payment->payment_gateway,
                'invoice_partial_id' => $data['invoice_partial_id'],
                'description' => 'Payment for invoice: ' . $invoice->id,
            ]);

            Log::info('requestTap', ['requestTap' => $requestTap]);

            $response = $tap->createCharge($requestTap);

            logger('response', ['response' => $response]);

            if (isset($response['errors'])) {
                return response()->json(['error' => $response['errors'][0]['description']], 500);
            }

            $paymentReference = $response['id'];
            $paymentUrl = $response['transaction']['url'];

            // $payment->status = 'initiate';
            // $payment->save();

            // return response()->json([
            //     'success' => 'Payment initiated successfully',
            //     'url' => $response['transaction']['url'],
            // ]);

        } else if (strtolower($data['payment_gateway']) === 'myfatoorah') {
    
            $myFatoorah = new MyFatoorah();

            $requestFatoorah = new Request([
                'final_amount' => $finalAmount,
                'client_name' => $data['client_name'],
                'client_email' => $data['client_email'],
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'payment_id' => $payment->id,
                'payment_gateway' => $payment->payment_gateway,
                'payment_method_id' => $data['payment_method'],
                'invoice_partial_id' => $data['invoice_partial_id'],
                'client_phone' => $data['client_phone'],
            ]);

            Log::info('requestFatoorah', ['requestFatoorah' => $requestFatoorah]);

            $response = $myFatoorah->createCharge($requestFatoorah);

            Log::info('MyFatoorah: ExecutePayment response', ['response' => $response]);

            $paymentReference = $response['Data']['InvoiceId'] ?? null;
            $paymentUrl = $response['Data']['PaymentURL'] ?? null;
            
            if(isset($response['Data']['ExpiryDate'])) {
                $expiryDate = $response['Data']['ExpiryDate'];
            }

        } else if (strtolower($data['payment_gateway']) === 'upayment'){
            $uPayment = new UPayment();

            $requestUPayment = new Request([
                'final_amount' => $finalAmount,
                'client_id' => $data['client_id'],
                'client_name' => $data['client_name'],
                'client_email' => $data['client_email'],
                'client_phone' => $data['client_phone'],
                'company_email' => $company->email,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'payment_id' => $payment->id,
                'payment_number' => $payment->voucher_number,
                'payment_gateway' => 'google-pay',
                'invoice_partial_id' => $data['invoice_partial_id'],
                'currency' => $invoice->currency,
            ]);

            Log::info('requestUPayment', ['requestUPayment' => $requestUPayment]);

            $response = $uPayment->makeCharge($requestUPayment);

            Log::info('UPayment: createCharge response', ['response' => $response]);

            if (!$response['status']) {
                return response()->json(['error' => $response['message']], 500);
            }

            $paymentReference = $response['data']['trackId'] ?? null;
            $paymentUrl = $response['data']['link'] ?? null;

            if(isset($response['transaction']['expiryDate'])) {
                $expiryDate = $response['transaction']['expiryDate'];
            }

        } else {
            $payment->delete();
            return response()->json(['error' => 'Unsupported payment method'], 400);
        }

        if($paymentReference && $paymentUrl) {

            $payment->payment_reference = $paymentReference;
            $payment->payment_url = $paymentUrl;
            $payment->expiry_date = $expiryDate;
            $payment->status = 'initiate';
            $payment->save();

            return response()->json([
                'success' => 'Payment initiated successfully',
                'url' => $paymentUrl,
            ]);

        } else {
            Log::error('Failed to initiate payment: Missing payment reference or URL.', [
                'payment_id' => $payment->id,
                'payment_gateway' => $payment->payment_gateway,
                'payment_reference' => $paymentReference,
                'payment_url' => $paymentUrl
            ]);

            $payment->delete();

            return response()->json(['error' => 'Failed to initiate payment.'], 500);
        }
    }

    public function process(Request $request)
    {
        Log::info('Tap process:', ['process' => $request]);
        $tap = new Tap();

        $tap_id = $request->tap_id;

        $response = $tap->getCharge($tap_id);

        if (isset($response['errors'])) {

            // $this->storeNotification([
            //     'user_id' => Auth::id(),
            //     'title' => 'Payment Failed',
            //     'message' => 'Payment failed: ' . $response['errors'][0]['description'],
            // ]);

            Log::error('Payment failed', ['errors' => $response['errors']]);

            abort(500, 'Payment failed');
        }

        $invoiceNumber = $response['metadata']['invoice_number'];
        $paymentId = $response['metadata']['payment_id'];

        if ($response['status'] != 'CAPTURED') {
            $invoice = Invoice::with('agent.branch', 'client')->where('invoice_number', $invoiceNumber)->first();
            $paymentId = $response['metadata']['payment_id'] ?? null;
            Transaction::create([
                'branch_id' => $invoice->agent->branch->id,
                'company_id' => $invoice->agent->branch->company->id,
                'entity_id' => $invoice->agent->branch->company->id,
                'entity_type' => 'company',
                'transaction_type' => 'credit',
                'transaction_date' => $invoice->invoice_date,
                'amount' => $response['amount'],
                'description' => 'Tap payment failed: ' . $invoiceNumber,
                'invoice_id' => $invoice->id,
                'payment_id' => $paymentId,
                'payment_reference' => $response['id'],
                'reference_type' => 'Invoice'
            ]);

            // $this->storeNotification([
            //     'user_id' => Auth::id(),
            //     'title' => 'Payment Failed',
            //     'message' => 'Payment failed: ' . $response['status'],
            // ]);

            Log::error('Payment failed', [
                'status' => $response['status'],
                'response' => $response,
            ]);

            return redirect()->route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoiceNumber])->with('error', 'Payment failed');
        }

        $paymentId = $response['metadata']['payment_id'];
        $paymentGateway = $response['metadata']['payment_gateway'];
        $invoicePartialIds = json_decode($response['metadata']['invoice_partial_id'], true);
        $totalPaidAmount = $response['amount'];

        if ($paymentId) {
            $payment = Payment::with(['invoice.agent.branch.company'])->find($paymentId);
        
            if ($payment && $payment->invoice) {
                $invoice = $payment->invoice;
            } elseif ($payment && $payment->agent) {
                $companyId = optional($payment->agent->branch)->company_id;
                $invoice = Invoice::with(['agent.branch.company', 'client', 'invoiceDetails.task'])
                    ->where('invoice_number', $invoiceNumber)
                    ->whereHas('agent.branch', fn($q) => $q->where('company_id', $companyId))
                    ->first();
            }
        }

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

        $chargeRecord = Charge::where('name', $paymentGateway)
            ->where('company_id', $invoice->agent->branch->company->id)
            ->select('amount', 'acc_bank_id', 'acc_fee_bank_id', 'acc_fee_id', 'paid_by')
            ->first();

        if ($chargeRecord) {
            $gatewayFee = ChargeService::TapCharge([
                'amount' => $payment->amount,
                'client_id' => $invoice->client_id,
                'agent_id' => $invoice->agent_id,
                'currency' => $invoice->currency
            ], $paymentGateway)['gatewayFee'] ?? 0;
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
        } else {
            Log::error('Charge record not found for payment gateway', ['payment_gateway' => $paymentGateway, 'company_id' => $invoice->agent->branch->company->id]);
            return redirect()->route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoiceNumber])->with('error', 'Something went wrong, please try again later.');
        }

        if (!$invoice) {
            Log::error('Invoice not found', ['invoice_number' => $invoiceNumber]);
            return redirect()->route('invoice.index')->with('error', 'Something went wrong, please try again later.');
        }

        if (!empty($invoiceDetails)) {
            try {

                $invoiceDetail = $invoiceDetails->first();

                // Check if there's at least one invoice detail to process
                if (!$invoiceDetail) {
                    Log::error('No invoice details found for processing', ['invoice_number' => $invoiceNumber]);
                    return redirect()->route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoiceNumber])->with('error', 'Something went wrong, please try again later.');
                }

                $selectedtask = Task::where('id', $invoiceDetail->task_id)->first();
                $client = Client::where('id', operator: $selectedtask->client_id)->first();

                $receivableAccount = Account::where('name', 'Clients')->first();
                $receivableAccountId = $receivableAccount->id;

                if (!$receivableAccount || !$receivableAccountId) {
                    Log::error('Receivable account not found', ['company_id' => $invoice->agent->branch->company->id]);
                    return redirect()->route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoiceNumber])->with('error', 'Something went wrong, please try again later.');
                }

                if (!$invoice->agent || !$invoice->agent->branch || !$invoice->agent->branch->company) {
                    Log::error('Agent or branch or company not found for invoice', ['invoice_id' => $invoice->id]);
                    return redirect()->route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoiceNumber])->with('error', 'Something went wrong, please try again later.');
                }

                // Create a transaction record first
                $transaction = Transaction::create([
                    'branch_id' =>  $invoice->agent->branch->id,
                    'company_id' =>  $invoice->agent->branch->company->id,
                    'entity_id' =>  $invoice->agent->branch->company->id,
                    'entity_type' => 'company',
                    'transaction_type' => 'debit',
                    'amount' => $totalPaidAmount,
                    'description' => 'Tap payment success: ' . $invoiceNumber,
                    'invoice_id' => $invoice->id,
                    'payment_id' => $paymentId,
                    'payment_reference' => $response['id'],
                    'reference_type' => 'Invoice',
                    'transaction_date' => now(),
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
                    'transaction_date' => $invoice->invoice_date,
                    'description' => 'Client Pays via ' . $bankPaymentFee->name . ' by (Assets): ' . $client->full_name,
                    'debit' => 0,
                    'credit' => $totalPaidAmount,
                    'balance' => $invoiceDetail['task_price'] - $totalPaidAmount,
                    'name' =>  $client->full_name,
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
                        'transaction_date' => $invoice->invoice_date,
                        'description' => 'Client Pays by ' . $client->full_name . ' via (Assets): ' . $bankPaymentFee->name,
                        'debit' => $totalPaidAmount,
                        'credit' => 0,
                        'balance' => $invoiceDetail['task_price'] - $totalPaidAmount,
                        'name' =>  $bankPaymentFee->name,
                        'type' => 'bank',
                        'voucher_number' => $payment->voucher_number,
                        'type_reference_id' => $bankPaymentFee->id
                    ]);

                    $bankPaymentFee->actual_balance += $invoiceDetail['task_price']; // Add to cash/bank account
                    $bankPaymentFee->save();
                }

                $paidBy = $chargeRecord->paid_by ?? null;
                if ($tapAccount) {
                    $tapAccount->actual_balance += $gatewayFee; // Add to expenses account
                    $tapAccount->save();

                    JournalEntry::create([
                        'transaction_id' => $transaction->id,
                        'company_id' => $invoice->agent->branch->company->id,
                        'branch_id' => $invoice->agent->branch->id,
                        'account_id' =>  $tapAccount->id,
                        'invoice_id' =>  $invoice->id,
                        'invoice_detail_id' =>  $invoiceDetail->id,
                        'voucher_number' => $payment->voucher_number,
                        'transaction_date' => $invoice->invoice_date,
                        'description' => ($paidBy === 'Company' ? 'Company Pays Gateway Fee: ' : 'Client Pays Gateway Fee: ') . $tapAccount->name,
                        'debit' => $gatewayFee,
                        'credit' => 0,
                        'balance' => $tapAccount->actual_balance,
                        'name' =>  $tapAccount->name,
                        'type' => 'charges',
                        'type_reference_id' => $tapAccount->id
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed in invoice processing', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return redirect()->route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoiceNumber])
                    ->with('error', 'Something went wrong while processing the invoice. Please try again later.');
            }
        }

        $selectedPartials = InvoicePartial::whereIn('id', $invoicePartialIds)->get();
        $companyId = $invoice->agent->branch->company_id;

        foreach ($selectedPartials as $invoicePartial) {
            $chargeResult = [];

            if (strtolower($payment->payment_gateway) === 'tap') {
                $chargeData = [
                    'amount'    => $invoicePartial->amount,
                    'client_id' => $payment->client_id,
                    'agent_id'  => $payment->agent_id,
                    'currency'  => $payment->currency,
                ];
                $chargeResult = ChargeService::TapCharge($chargeData, $payment->payment_gateway);
            }

            $invoicePartial->service_charge = $chargeResult['fee'];
            $invoicePartial->amount = $chargeResult['finalAmount'];
            $invoicePartial->payment_id = $payment->id;
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

        // Cash payments remain unpaid until receipt voucher is processed
        // Only credit_payment type was removed as per business requirements

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
        return redirect()->route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number])
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
            'name' => $client->full_name,
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

        return redirect()->route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number])
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

    public function getPaymentStatusMyFatoorah($invoiceId) : JsonResponse
    {
        $configService = new GatewayConfigService();
        $myfatoorahConfig = $configService->getMyFatoorahConfig();

        if(!$myfatoorahConfig['status'] || !$myfatoorahConfig['data']) {
            Log::error('MyFatoorah configuration is missing or inactive');
            return response()->json([
                'status' => 'error',
                'message' => $myfatoorahConfig['message'] ?? 'MyFatoorah configuration is missing or inactive'
            ], 500);
        }

        $myfatoorahConfig = $myfatoorahConfig['data'];

        $apiKey  = $myfatoorahConfig['api_key'];
        $baseUrl = $myfatoorahConfig['base_url'];

        Log::info('getPaymentStatusMyFatoorah called with invoice_id: ', [
            'invoice_id' => $invoiceId,
            'apiKey' => $apiKey,
            'baseUrl' => $baseUrl,
        ]);

        $response = Http::withHeaders([
            'Authorization' => "Bearer $apiKey",
            'Content-Type' => 'application/json',
        ])->post("$baseUrl/getPaymentStatus", [
            "Key" => $invoiceId,
            "KeyType" => "InvoiceId"
        ]);

        Log::info('getPaymentStatusMyFatoorah Response: ', $response->json());

        if(!$response->successful()){

            $message = $response->json()['Message'] ?? 'Unknown error';

            Log::error('Failed to fetch payment status from MyFatoorah', [
                'invoiceId' => $invoiceId,
                'response' => $response->body()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $message
            ], 500);
        }

        $responseData = $response->json();
        $data = $responseData['Data'] ?? [];

        if( empty($data)) {
            Log::error('No data found in MyFatoorah response', ['response' => $responseData]);
            return response()->json([
                'status' => 'error',
                'message' => 'No data found in MyFatoorah response'
            ], 404);
        }

        $invoiceTransactions = $data['InvoiceTransactions'] ?? '[]';
        $authCode = data_get($invoiceTransactions, '0.AuthorizationId');

        $invoiceStatus = $data['InvoiceStatus'] ?? null;

        if( !$invoiceStatus) {
            Log::error('Invoice status not found in MyFatoorah response', ['response' => $responseData]);
            return response()->json([
                'status' => 'error',
                'message' => 'Invoice status not found in MyFatoorah response'
            ], 404);
        }

        $invoiceValue = $data['InvoiceValue'] ?? null;

        if(!$invoiceValue) {
            Log::error('Invoice value not found in MyFatoorah response', ['response' => $responseData]);
            return response()->json([
                'status' => 'error',
                'message' => 'Invoice value not found in MyFatoorah response'
            ], 404);
        }

        if($invoiceStatus === 'Paid') {
            $invoiceId = $response->json()['Data']['InvoiceId'] ?? null;

            if (!$invoiceId) {
                Log::info('Invoice ID not found in MyFatoorah portal');
                return response()->json([
                    'status' => 'error',
                    'message' => 'No such Invoice ID found in MyFatoorah portal'
                ], 400);
            }

            $existingInvoiceId = Payment::where('payment_reference', $invoiceId)->exists();
           
            if ($existingInvoiceId) {
                Log::info('Invoice ID has already been imported');
                return response()->json([
                    'status' => 'error',
                    'message' => 'A payment with this Invoice ID has already been imported'
                ], 400);
            }
        } else {
            Log::info('Invoice status is not Paid', ['invoiceStatus' => $invoiceStatus]);
            return response()->json([
                'status' => 'error',
                'message' => 'Invoice status is not Paid'
            ], 400);
        }

        $userDefined = json_decode($data['UserDefinedField'] ?? '{}', true);
        $paymentMethodId = PaymentMethod::where('english_name')->value('id');

        return response()->json([
            'status' => 'success',
            'message' => 'Payment status fetched successfully',
            'data' => $data,
            'amount' => $invoiceValue,
            'invoice_status' => $invoiceStatus,
            'invoice_id' => $data['InvoiceId'] ?? null,
            'invoice_reference' => $data['InvoiceReference'],
            'customer_name' => $data['CustomerName'] ?? null,
            'created_date' => $data['CreatedDate'] ?? null,
            'payment_gateway' => Arr::get($userDefined, 'payment_gateway', 'MyFatoorah'),
            'payment_method_id' => $paymentMethodId,
            'auth_code' => $authCode,
            'user_defined' => $userDefined,
        ]);
    }

    public function importMyFatoorahFromInvoice(Request $request) : JsonResponse
    {
        Log::info('Starting to import MyFatoorah payment from invoice');

        $request->validate([
            'import_invoice_id' => 'required|string',
            'receiverName' => 'required|string',
            'agentName' => 'required|string',
        ]);

        $importinvoiceId = $request->input('import_invoice_id');

        $response = $this->getPaymentStatusMyFatoorah($importinvoiceId)->getData(true);

        if($response['status'] === 'error') {
            Log::error('Error fetching payment status from MyFatoorah', ['message' => $response['message']]);
            return response()->json([
                'status' => 'error',
                'message' => $response['message']
            ], 400);
        }

        $agentId = Agent::where('name', $request->input('agentName'))->value('id');
        $clientId = Client::where('name', $request->input('receiverName'))->value('id');

        if (!$agentId || !$clientId) {

            Log::error('Invoice ID, Client, or Agent is missing', [
                'clientId' => $clientId,
                'agentId' => $agentId,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong, please ensure all fields are filled correctly.'
            ], 400);
        }
  
        $data = [
            'invoice_id' => $importinvoiceId,
            'payment_gateway' => $response['payment_gateway'],
            'payment_method' => $response['payment_method_id'],
            'amount' => $response['amount'],
            'client_id' => $clientId,
            'agent_id' => $agentId,
            'notes' => 'Imported from MyFatoorah Portal with Invoice ID: ' . $response['invoice_id'],
            'source' => 'import',
        ];

        $response = $this->paymentStoreLinkProcess(new Request($data));

        if( $response['status'] === 'error') {
            Log::error('Error during payment store link process', ['message' => $response['message']]);
            return response()->json([
                'status' => 'error',
                'message' => $response['message']
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Payment imported successfully',
            'data' => [
                'client_id' => $clientId,
                'agent_id' => $agentId,
            ]
        ]);
    }

    public function importMyFatoorahFromPayment(Request $request) : RedirectResponse
    {
        $request->validate([
            'import_invoice_id' => 'required|string',
        ]);

        $invoiceId = $request->input('import_invoice_id');

        $response = $this->getPaymentStatusMyFatoorah($invoiceId)->getData(true);

        if ($response['status'] === 'error') {
            Log::error('Error fetching payment status from MyFatoorah', ['message' => $response['message']]);
            return redirect()->back()->with('error', $response['message']);
        }

        return redirect()->route('payment.link.create')->withInput([
            'invoice_id' => $response['invoice_id'],
            'payment_gateway' => $response['payment_gateway'],
            'payment_method' => $response['payment_method_id'],
            'amount' => $response['amount'],
            'notes' => 'Imported from MyFatoorah Portal with Invoice ID: ' . $response['invoice_id'],
            'source' => 'import',
            'invoice_reference' => $response['invoice_reference'],
            'auth_code' => $response['auth_code'],
        ]);
    }

    public function paymentLink(Request $request)
    {
        $user = Auth::user();

        if ($user->role_id == Role::ADMIN) {
            $agents = Agent::all();
            $agentsId = $agents->pluck('id')->toArray();
        } else if ($user->role_id == Role::COMPANY) {
            $branches = Branch::where('company_id', $user->company->id)->get();
            $agents = Agent::where('branch_id', $branches->pluck('id')->toArray())->get();
            $agentsId = $agents->pluck('id')->toArray();
        } else if ($user->role_id == Role::BRANCH) {
            $agents = Agent::where('branch_id', $user->branch->id)->get();
            $agentsId = $agents->pluck('id')->toArray();
        } else if ($user->role_id == Role::AGENT) {
            $agents = Agent::where('id', $user->agent->id)->get();
            $agentsId = $agents->pluck('id')->toArray();
        } else {
            return redirect()->back()->with('error', 'You are not authorized to view payment links.');
        }

    $clients = Client::where(function ($query) use ($agentsId) {
        $query->whereIn('agent_id', $agentsId)
              ->orWhereHas('agents', function ($q) use ($agentsId) {
                  $q->whereIn('agent_id', $agentsId);
              });
    })->get();        $payments = Payment::with('invoice')
            ->where(function ($query) use ($agentsId) {
                $query->whereHas('invoice', function ($payment) use ($agentsId) {
                    $payment->whereIn('agent_id', $agentsId);
                })->orWhereIn('agent_id', $agentsId);
            });      

        if ($request->boolean('clear')) {
            session()->forget('filter');
            return redirect()->route('payment.link.index', array_filter([
                'q' => $request->query('q'),
            ]));
        }        

        if ($search = $request->query('q')) {
            $payments = $payments->where(function ($query) use ($search) {
                $query->where('payment_reference', 'like', '%' . $search . '%')
                    ->orWhere('payment_gateway', 'like', '%' . $search . '%')
                    ->orWhere('voucher_number', 'like', '%' . $search . '%')
                    ->orWhereHas('paymentMethod', function ($q) use ($search) {
                        $q->where('english_name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('agent', function ($q) use ($search) {
                        $q->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('first_name', 'like', '%' . $search . '%')
                            ->orWhere('middle_name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%')
                            ->orWhere('country_code', 'like', '%' . $search . '%')
                            ->orWhere('phone', 'like', '%' . $search . '%');
                    });
            });
        }

        $incoming = collect($request->input('filter', []))
            ->filter(fn($v) => is_array($v) ? array_filter($v, fn($x)=>$x!=='' && $x!==null) : $v !== '' && $v !== null)
            ->all();
        if ($request->has('filter')) {
            session(['filter' => array_replace(session('filter', []), $incoming)]);
            return redirect()->route('payment.link.index', ['q' => $request->query('q')]);
        }
        $filters = session('filter', []);

        $payments->when(data_get($filters, 'client_id'), fn($q,$v)=>$q->where('client_id',$v));
        $payments->when(data_get($filters, 'agent_id'), fn($q,$v)=>$q->where('agent_id',$v));
        $payments->when(data_get($filters, 'payment_method_id'), fn($q,$v)=>$q->where('payment_method_id',$v));
        $payments->when(data_get($filters, 'created_by'), fn($q,$v)=>$q->where('created_by',$v));
        $payments->when(data_get($filters, 'payment_gateway'), fn($q,$v)=>$q->whereIn('payment_gateway',(array)$v));
        $payments->when(data_get($filters, 'status'), fn($q,$v)=>$q->whereIn('status',(array)$v));

        $payments = $payments->orderBy('created_at', 'desc')->paginate(15)->appends($request->only('q'));

        $payments->getCollection()->transform(function ($payment) {
            if ($payment->payment_gateway === 'MyFatoorah') {
                $mfPayment = MyFatoorahPayment::where('payment_int_id', $payment->id)->first();
                $payment->invoice_ref = $mfPayment->invoice_ref ?? null;
            } else {
                $payment->invoice_ref = null;
            }
            return $payment;
        });

        $paymentGateways = Charge::where('type', ChargeType::PAYMENT_GATEWAY)
            ->where('is_active', true)->get();
        $paymentMethods = PaymentMethod::where('is_active', true)->get();
        $users = User::whereIn('id', Payment::select('created_by')->distinct()->pluck('created_by'))->get();
        $status = ['pending','initiate','completed','failed','cancelled'];

        return view('payment.link.index', compact(
            'payments',
            'clients',
            'agents',
            'paymentGateways',
            'paymentMethods',
            'users',
            'status',
            'filters',
        ));
    }

    public function paymentCreateLink()
    {
        $user = Auth::user();
        if ($user->role_id == Role::ADMIN) {

            $agents = Agent::all();
            $agentsId = $agents->pluck('id')->toArray();
        } else if ($user->role_id == Role::COMPANY) {

            $branches = Branch::where('company_id', $user->company->id)->get();
            $agents = Agent::where('branch_id', $branches->pluck('id')->toArray())->get();
            $agentsId = $agents->pluck('id')->toArray();
        } else if ($user->role_id == Role::BRANCH) {

            $agents = Agent::where('branch_id', $user->branch->id)->get();
            $agentsId = $agents->pluck('id')->toArray();
        } else if ($user->role_id == Role::AGENT) {

            $agents = Agent::where('id', $user->agent->id)->get();
            $agentsId = $agents->pluck('id')->toArray();
        } else {
            return redirect()->back()->with('error', 'You are not authorized to create payment links.');
        }

    $clients = Client::where(function ($query) use ($agentsId) {
        $query->whereIn('agent_id', $agentsId)
              ->orWhereHas('agents', function ($q) use ($agentsId) {
                  $q->whereIn('agent_id', $agentsId);
              });
    })->get();        $invoices = Invoice::all();
        $payments = Payment::all();
        $currencies = Currency::all();
        $paymentGateways = Charge::where('type', ChargeType::PAYMENT_GATEWAY)
            ->where('is_active', true)->get();
        $paymentMethods = PaymentMethod::where('is_active', true)->get();

        return view('payment.link.create', compact(
            'payments',
            'clients',
            'agents',
            'invoices',
            'currencies',
            'paymentGateways',
            'paymentMethods'
        ));
    }

    public function paymentStoreLinkProcess(Request $request)
    {
        $source = $request->input('source');
        $invoiceId = $request->input('invoice_id');
        $invoiceReference = $request->input('invoice_reference');
        $authCode = $request->input('auth_code');

        $request->validate([
            'payment_gateway' => 'required',
            'payment_method' => 'nullable',
            'amount' => 'required|numeric',
            'client_id' => 'nullable',
            'agent_id' => 'nullable',
            'invoice_id' => 'nullable',
            'invoice_reference' => 'nullable',
            'auth_code' => 'nullable',
            'notes' => 'nullable|string|max:255'
        ]);

        $companyId = null;
        $user = Auth::user();

        if ($user->role_id == Role::COMPANY) {
            $companyId = $user->company->id;
        } elseif ($user->role_id == Role::BRANCH) {
            $companyId = $user->branch->company->id;
        } elseif ($user->role_id == Role::AGENT) {
            $companyId = $user->agent->branch->company->id;
        }

        $voucherSequence = Sequence::firstOrCreate(['company_id' => $companyId], ['current_sequence' => 1]);
        $client = Client::find($request->client_id);
        $agent = Agent::find($request->agent_id);

        if (!$client) {
            return ['status' => 'error', 'message' => 'Client cannot be found'];
        }

        if (!$agent) {
            return ['status' => 'error', 'message' => 'Agent cannot be found'];
        }

        $currentSequence = $voucherSequence->current_sequence;
        $voucherNumber = $this->generateVoucherNumber($currentSequence);
        
        try {
            $voucherSequence->current_sequence++;
            $voucherSequence->save();
        } catch (Exception $e) {
            logger('Failed to save voucher sequence', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        try {
            $data = [
                'voucher_number' => $voucherNumber,
                'payment_reference' => $invoiceId,
                'invoice_reference' => $invoiceReference,
                'auth_code' => $authCode,
                'from' => $client->full_name,
                'pay_to' => $agent->branch->company->name,
                'currency' => 'KWD',
                'payment_date' => Carbon::now(),
                'amount' => $request->amount,
                'payment_gateway' => $request->payment_gateway,
                'payment_method_id' => $request->payment_method,
                'status' => $source === 'import' ? 'completed' : 'pending',
                'client_id' => $client->id,
                'agent_id' => $agent->id,
                'notes' => $request->notes,
                'created_by' => Auth::id()
            ];
            
            $payment = Payment::create($data);
            Log::info('Created Payment:', $data);

        } catch (Exception $e) {
            logger('Failed to create payment', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
        /* $clientController = new ClientController(); */

        if ($source === 'import') {
        try {
            $request = new Request([
                'payment_id' => $payment->id,
                'payment_gateway' => $payment->payment_gateway,
                'payment_method' => $payment->paymentMethod?->myfatoorah_id,
                'amount' => $payment->amount,
                'client_id' => $payment->client_id,
                'agent_id' => $payment->agent_id,
                'invoice_id' => $payment->payment_reference,
                'fatoorah_payment_id' => $payment->paymentId,
                'notes' => $payment->notes,
                'source' => 'import',
            ]);

            $result = $this->paymentLinkProcess($request);
            Log::info('Add Credit & Journal for import payment response');
        } catch (\Exception $e) {
            Log::error('Add Credit & Journal for import payment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        }

            return [
                'status' => 'success',
                'message' => 'Payment Link Created',
                'clientEmail' => $client->email,
                'data' => $payment
            ];
    }

    public function paymentStoreLink(Request $request)
    {
        $response = $this->paymentStoreLinkProcess($request);
        if ($response['status'] === 'error') {
            return redirect()->back()->with('error', $response['message']);
        }

        // dd($response['data']);
        $voucherNumber = $response['data']['voucher_number'];
        $paymentUrl = url('/payment/link/show/' . $voucherNumber);
        // Mail::to($response['clientEmail'])->send(new PaymentLinkEmail($paymentUrl));
        return redirect()->route('payment.link.index')->with('success', 'Payment link created successfully!');
    }

    public function paymentShowLink($companyId, $voucherNumber)
    {
        $payment = Payment::with(['agent.branch.company', 'client'])
            ->where('voucher_number', $voucherNumber)
            ->whereHas('agent.branch', fn ($q) => $q->where('company_id', $companyId))
            ->first();

        if (!$payment) {
            return auth()->user() ? redirect()->route('payment.link.index') : abort(404);
        }

        if (!$payment->client) {
            return auth()->user() ? redirect()->route('payment.link.index') : abort(404);
        }

        if (!$payment->agent) {
            return auth()->user() ? redirect()->route('payment.link.index') : abort(404);
        }

        $payment = Payment::with('agent', 'client')->where('id', $payment->id)->first();

        $fatoorahPayment = $payment->findMyFatoorahPayment();

        $invoiceRef = null;
        $authorizationId = null;

        if ($fatoorahPayment) {
            $payloadData = $fatoorahPayment->payload;
            
            if (is_array($payloadData) && isset($payloadData['Data'])) {
                $invoiceRef = $payloadData['Data']['InvoiceReference'] ?? 'N/A';
                $transactions = $payloadData['Data']['InvoiceTransactions'] ?? [];
                if (!empty($transactions)) {
                    $authorizationId = $transactions[0]['AuthorizationId'] ?? 'N/A';
                }
            }
        }

        $companyId = optional($payment->agent->branch)->company_id;
        $chargeResult = [];
        $gatewayFee = 0;
        $finalAmount = 0;
        $paidBy = 'Company';
        $chargeData = [
            'amount'    => $payment->amount,
            'client_id' => $payment->client_id,
            'agent_id'  => $payment->agent_id,
            'currency'  => $payment->currency,
        ];

        if ($payment->status === 'completed' && is_null($payment->service_charge)) {
            if ($payment->invoice) {
                $invoicePartial = InvoicePartial::where('invoice_id', $payment->invoice->id)->first();
                if ($invoicePartial) {
                    $gatewayFee = $invoicePartial->service_charge ?? 0;
                    $finalAmount = $payment->amount;
                    $paidBy = ($gatewayFee > 0) ? 'Client' : 'Company';
                } else {
                    $gatewayFee = 0;
                    $finalAmount = $payment->amount;
                    $paidBy = 'Company';
                }
            } else {
                $tempChargeResult = $payment->payment_gateway === 'MyFatoorah'
                    ? ChargeService::FatoorahCharge($payment->amount, $payment->payment_method_id, $companyId)
                    : ChargeService::TapCharge($chargeData, $payment->payment_gateway ?? 'Tap');

                $gatewayFee = $tempChargeResult['fee'] ?? 0;
                $finalAmount = $payment->amount;
                $paidBy = ($gatewayFee > 0) ? 'Client' : 'Company';
            }
        } else if ($payment->status !== 'completed') {
            $chargeData = [
                'amount'     => $payment->amount,
                'currency'   => $payment->currency,
                'client_id'  => $payment->client_id,
                'agent_id'   => $payment->agent_id,
            ];

            $chargeResult = $payment->payment_gateway === 'MyFatoorah'
                ? ChargeService::FatoorahCharge($payment->amount, $payment->payment_method_id, $companyId)
                : ChargeService::TapCharge($chargeData, $payment->payment_gateway ?? 'Tap');

            $gatewayFee = $chargeResult['fee'] ?? 0;
            $finalAmount = $chargeResult['finalAmount'] ?? $payment->amount;
            $paidBy = $chargeResult['paid_by'] ?? 'Company';

            $payment->service_charge = ($chargeResult['paid_by'] === 'Company') ? 0 : $chargeResult['fee'];
            $payment->save();
        }

        return view('payment.link.show', compact('payment', 'chargeResult', 'gatewayFee', 'finalAmount', 'paidBy', 'invoiceRef', 'authorizationId'));
    }

    public function paymentLinkInitiate(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|exists:payments,id',
        ]);

        $payment = Payment::with('invoice')->find($request->payment_id);

        if (!$payment) {

            if(auth()->user()){
                return redirect()->back()->with('error', 'Payment not found.');
            }

            return abort(404);
        }

        $process = 'topup';
        if ($payment->invoice) {
            $process = 'invoice';
        }
        $paymentGateway = $payment->payment_gateway;
        $paymentMethod = $payment->paymentMethod?->myfatoorah_id;

        if (strtolower($paymentGateway) === 'tap') {
            $tap = new Tap();

            $chargeResult = ChargeService::TapCharge([
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'client_id' => $payment->client_id,
                'agent_id' => $payment->agent_id
            ], 'Tap');
            $finalAmount = $chargeResult['finalAmount'];

            $requestTap = new Request([
                'finalAmount' => $finalAmount,
                'client_name' => $payment->client->full_name,
                'client_email' => $payment->client->email,
                'voucher_number' => $payment->voucher_number,
                'payment_id' => $payment->id,
                'payment_gateway' => $paymentGateway,
                'description' => 'Payment for' . $payment->voucher_number,
                'process' => $process,
            ]);

            Log::info('requestTap', ['requestTap' => $requestTap]);

            $response = $tap->createCharge($requestTap);
            logger('Payment link initiate response', ['response' => $response]);

            if (isset($response['errors'])) {
                return redirect()->back()->with('error', $response['errors'][0]['description']);
            }

            $paymentUrl = $response['transaction']['url'];
            return redirect($paymentUrl);
        } else if (strtolower($paymentGateway) === 'myfatoorah') {
            $configService = new GatewayConfigService();
            $myfatoorahConfig = $configService->getMyFatoorahConfig();

            if(!$myfatoorahConfig['status'] || !$myfatoorahConfig['data']) {
                return redirect()->back()->with('error', $myfatoorahConfig['message'] ?? 'MyFatoorah configuration is missing or inactive');
            }

            $myfatoorahConfig = $myfatoorahConfig['data'];
    
            $apiKey  = $myfatoorahConfig['api_key'];
            $baseUrl = $myfatoorahConfig['base_url'];

            $payment = Payment::with('agent', 'client')->where('id', $payment->id)->first();
            $companyId = optional($payment->agent->branch)->company_id;

            if ($payment->status === 'initiate') {
             
                if ($payment->payment_url && $payment->expiry_date && now()->lt($payment->expiry_date)) {
                    Log::info('Reusing existing payment URL', [
                        'invoice_id' => $payment->payment_reference,
                        'url' => $payment->payment_url,
                        'expires_at' => $payment->expiry_date,
                    ]);

                    return redirect($payment->payment_url);
                }
                Log::info('Old payment URL expired, reinitiating new payment');
                return $this->paymentLinkReinitiate($payment->payment_reference);
            }


            //filter record
            $firstName = $payment->client->first_name;
            $middleName = $payment->client->middle_name ?? '';
            $lastName = $payment->client->last_name ?? '';

            $customerName = trim("$firstName $middleName $lastName");

            $client = $payment->client;
            $clientPhone = $client->phone ?? null;

            if (isset($clientPhone) && strpos($clientPhone, '+') === 0) {
                // Remove country code if present (e.g., +96512345678 -> 12345678)
                $clientPhone = preg_replace('/^\+\d{1,3}/', '', $clientPhone);
                $clientPhone = ltrim($clientPhone, '0'); // Optionally remove leading zero
            }

            $chargeResult = ChargeService::FatoorahCharge($payment->amount, $payment->payment_method_id, $companyId);

            $finalAmount = $chargeResult['finalAmount'];

            $executePayload = [
                "PaymentMethodId"     => $paymentMethod,
                "InvoiceValue"        => $finalAmount,
                "CustomerName"       => $customerName ?? 'Customer',
                "CustomerEmail"       => 'shoja@citytravelers.co',
                "MobileCountryCode"   => $client->country_code ?? '+965',
                "CustomerMobile"      => $clientPhone ?? '50000000',
                "DisplayCurrencyIso"  => $payment->currency ?? 'KWD',
                "CallBackUrl"         => route('payments.callback'),
                "ErrorUrl"            => route('payments.error', ['payment_id' => $payment->id]),
                // "ErrorUrl"            => route('payments.error'),
                "Language"            => "en",
                "UserDefinedField"   => json_encode([
                    'voucher_number' => $payment->voucher_number,
                    'payment_id' => $payment->id,
                    'payment_gateway' => $paymentGateway,
                    'payment_method' => $paymentMethod,
                    'process' => $process,
                ]),
                "InvoiceItems" => [
                    [
                        "ItemName"   => "Voucher " . $payment->voucher_number,
                        "Quantity"   => 1,
                        "UnitPrice"  => $finalAmount,
                    ]
                ],
            ];
            // dd($executePayload);
            $executeResponse = Http::withHeaders([
                'Authorization' => "Bearer $apiKey",
                'Content-Type' => 'application/json',
            ])->post("$baseUrl/ExecutePayment", $executePayload);

            if (!$executeResponse->successful()) {
                Log::error('MyFatoorah: ExecutePayment failed', ['response' => $executeResponse->body()]);
                return redirect()->back()->with('error', 'ExecutePayment failed.');
            }

            $resData = $executeResponse->json();
            $invoiceUrl = $resData['Data']['PaymentURL'] ?? null;
            $mfInvoiceId = $resData['Data']['InvoiceId'] ?? null;
            $expiryDateURL = $resData['Data']['ExpiryDate'] ?? null;

            if ($invoiceUrl && $mfInvoiceId) {
                $payment->payment_reference = $mfInvoiceId;
                $payment->payment_url = $invoiceUrl;
                $payment->expiry_date = $expiryDateURL ? Carbon::parse($expiryDateURL) : now()->addDays(2);
                $payment->status = 'initiate';
                $payment->save();

                Log::info('MyFatoorah payment initiated', [
                    'old_invoice_id' => $mfInvoiceId,
                    'old_url' => $invoiceUrl,
                    'old_expires_at' => $payment->expiry_date,
                ]);
                return redirect($invoiceUrl);
            }

            return redirect()->back()->with('error', 'MyFatoorah response missing PaymentURL or InvoiceId.');
        }

        return redirect()->route('payment.link.index')->with('success', 'Payment initiated successfully!');
    }

    public function paymentLinkReinitiate($paymentReference)
    {
        if (!$paymentReference) {
            return redirect()->back()->with('error', 'Missing payment reference for reinitiation.');
        }

        Log::info('Reinitiating MyFatoorah payment', ['payment_reference' => $paymentReference]);

        $payment = Payment::with('client', 'agent.branch')->where('payment_reference', $paymentReference)->first();

        if (!$payment || $payment->status !== 'initiate') {
            return redirect()->back()->with('error', 'Invalid or already processed payment.');
        }

        $configService = new GatewayConfigService();
        $myfatoorahConfig = $configService->getMyFatoorahConfig();

        if(!$myfatoorahConfig['status'] || !$myfatoorahConfig['data']) {
            return redirect()->back()->with('error', $myfatoorahConfig['message'] ?? 'MyFatoorah configuration is missing or inactive');
        }

        $myfatoorahConfig = $myfatoorahConfig['data'];

        $apiKey  = $myfatoorahConfig['api_key'];
        $baseUrl = $myfatoorahConfig['base_url'];

        $companyId = optional($payment->agent->branch)->company_id;

        $firstName = $payment->client->first_name;
        $middleName = $payment->client->middle_name ?? '';
        $lastName = $payment->client->last_name ?? '';

        $customerName = trim("$firstName $middleName $lastName");

        $client = $payment->client;
        $clientPhone = $client->phone ?? '50000000';
        if (isset($clientPhone) && strpos($clientPhone, '+') === 0) {
            $clientPhone = preg_replace('/^\+\d{1,3}/', '', $clientPhone);
            $clientPhone = ltrim($clientPhone, '0');
        }

        $chargeResult = ChargeService::FatoorahCharge($payment->amount, $payment->payment_method_id, $companyId);
        $finalAmount = $chargeResult['finalAmount'];

        $executePayload = [
            "PaymentMethodId"     => $payment->paymentMethod?->myfatoorah_id,
            "InvoiceValue"        => $finalAmount,
            "CustomerName"        => $customerName,
            "CustomerEmail"       => $client->email ?? 'email@example.com',
            "MobileCountryCode"   => $client->country_code ?? '+965',
            "CustomerMobile"      => $clientPhone,
            "DisplayCurrencyIso"  => $payment->currency ?? 'KWD',
            "CallBackUrl"         => route('payments.callback'),
            "ErrorUrl"            => route('payments.error', ['payment_id' => $payment->id]),
            "Language"            => "en",
            "UserDefinedField"    => json_encode([
                'voucher_number'   => $payment->voucher_number,
                'payment_id'       => $payment->id,
                'payment_gateway'  => $payment->payment_gateway,
                'payment_method'   => $payment->paymentMethod?->myfatoorah_id,
                'process'          => $payment->invoice ? 'invoice' : 'topup',
            ]),
            "InvoiceItems" => [
                [
                    "ItemName"   => "Voucher " . $payment->voucher_number,
                    "Quantity"   => 1,
                    "UnitPrice"  => $finalAmount,
                ]
            ],
        ];

        // dd($executePayload);

        $executeResponse = Http::withHeaders([
            'Authorization' => "Bearer $apiKey",
            'Content-Type'  => 'application/json',
        ])->post("$baseUrl/ExecutePayment", $executePayload);

        if (!$executeResponse->successful()) {
            Log::error('Reinitiate MyFatoorah ExecutePayment failed', ['response' => $executeResponse->body()]);
            
            return auth()->user() ? redirect()->route('invoices.index')->with('error', 'Failed to reinitiate payment.') : abort(500);
        }

        $resData = $executeResponse->json();
        $invoiceUrl = $resData['Data']['PaymentURL'] ?? null;
        $mfInvoiceId = $resData['Data']['InvoiceId'] ?? null;

        if ($invoiceUrl && $mfInvoiceId) {
            $payment->payment_reference = $mfInvoiceId;
            $payment->status = 'initiate';
            $payment->save();

            return redirect($invoiceUrl);
        }

        return auth()->user() ? redirect()->route('invoices.index')->with('error', 'Failed to retrieve reinitiation URL.') : abort(500);
    }

    public function paymentLinkProcess(Request $request)
    {   
        $source = $request->input('source');
        if ($source === 'import') {

            if(!auth()->user()){
                return abort(403, 'Unauthorized action.');
            }

            $paymentId = $request->payment_id;
            $payment = Payment::findorFail($paymentId);

            $clientController = new ClientController;

            $addCreditResponse = $clientController->addCredit($payment);

            if (isset($addCreditResponse['error'])) {
                logger('Failed to add credit to client', [
                    'message' => $addCreditResponse['error'],
                    'payment_id' => $paymentId,
                ]);
                return redirect()->back()->with('error', 'Payment cannot be updated');
            }

            $liabilitiesAccount = Account::where('name', 'like', '%Liabilities%')
                ->where('company_id', $payment->agent->branch->company->id)
                ->first();

            if (!$liabilitiesAccount) {
                return redirect()->back()->with('error', 'Liabilities account not found.');
            }

            $clientAdvance = Account::where('name', 'Client')
                ->where('company_id', $payment->agent->branch->company->id)
                ->where('root_id', $liabilitiesAccount->id)
                ->first();

            if (!$clientAdvance) {
                return redirect()->back()->with('error', 'Client advance account not found.');
            }

            DB::beginTransaction();

            try {
                $transaction = Transaction::create([
                    'branch_id' => $payment->agent->branch->id,
                    'company_id' => $payment->agent->branch->company->id,
                    'entity_id' => $payment->agent->branch->company->id,
                    'entity_type' => 'company',
                    'transaction_type' => 'debit',
                    'amount' => $payment->amount,
                    'description' => 'Topup success by ' . $payment->client->full_name,
                    'payment_id' => $payment->id,
                    'invoice_id' => $payment->invoice_id,
                    'payment_reference' => $payment->payment_reference,
                    'reference_type' => 'Payment',
                    'transaction_date' => now(),
                ]);

                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $payment->agent->branch->id,
                    'company_id' => $payment->agent->branch->company->id,
                    'invoice_id' => $payment->invoice_id,
                    'account_id' => $clientAdvance->id,
                    'transaction_date' => now(),
                    'description' => 'Advance Payment in voucher number: ' . $payment->voucher_number,
                    'debit' => 0,
                    'credit' => $payment->amount,
                    'balance' => $clientAdvance->actual_balance - $payment->amount,
                    'name' => $payment->client->full_name,
                    'type' => 'receivable',
                    'voucher_number' => $payment->voucher_number,
                    'type_reference_id' => $clientAdvance->id
                ]);

                Log::info('Successfully created transaction and journal entry for import payment of myFatoorah from the portal');
            } catch (Exception $e) {
                DB::rollBack();
                logger('Failed to create journal entry', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return redirect()->back()->with('error', 'Payment cannot be updated');
            }
            DB::commit();

            return redirect()->back()->with('success', 'Import payment from myFatoorah portal is success!');
        } else if ($request->tap_id) {
            $tapId = $request->tap_id;
            $tap = new Tap();
            $response = $tap->getCharge($tapId);
        } else {
            if(auth()->user()){
                return redirect()->back()->with('error', 'Payment not found.');
            }

            return abort(404);
        }

        if (isset($response['errors'])) {

            if (auth()->user()) {
                return redirect()->back()->with('error', $response['errors'][0]['description']);
            }

            return abort(500);
        }

        if ($response['status'] != 'CAPTURED') {
            $payment = Payment::with('client', 'agent.branch')->find($response['metadata']['payment_id']);
            Transaction::create([
                'branch_id' => $payment->agent->branch->id,
                'company_id' => $payment->agent->branch->company->id,
                'entity_id' => $payment->agent->branch->company->id,
                'entity_type' => 'company',
                'transaction_type' => 'debit',
                'amount' => $payment->amount,
                'description' => 'Topup failed by ' . $payment->client->full_name,
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'payment_reference' => $response['id'],
                'reference_type' => 'Payment',
                'transaction_date' => now(),
            ]);

            if (auth()->user()) {
                return redirect()->back()->with('error', 'Payment ' . strtolower($response['status']));
            }

            return abort(500);
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

        $finalPaidAmount = $response['amount'];
        $baseAmount = $payment->amount;
        $companyId = $payment->agent->branch->company_id;
        $serviceFeePaid = 0;

        if (strtolower($payment->payment_gateway) === 'tap') {
            $chargeData = [
                'amount'    => $baseAmount,
                'client_id' => $payment->client_id,
                'agent_id'  => $payment->agent_id,
                'currency'  => $payment->currency,
            ];
            $chargeResult = ChargeService::TapCharge($chargeData, $payment->payment_gateway);
            $serviceFeePaid = $chargeResult['fee'] ?? 0;
        }

        $process = $response['metadata']['process'];

        if ($process == 'topup') {
            $clientController = new ClientController;

            $addCreditResponse = $clientController->addCredit($payment);

            if (isset($addCreditResponse['error'])) {
                logger('Failed to add credit to client', [
                    'message' => $addCreditResponse['error'],
                    'payment_id' => $paymentId,
                ]);

                if (auth()->user()) {
                    return redirect()->back()->with('error', 'Payment cannot be updated');
                } else {
                    return abort(500);
                }
            }

            $liabilitiesAccount = Account::where('name', 'like', '%Liabilities%')
                ->where('company_id', $payment->agent->branch->company->id)
                ->first();

            if (!$liabilitiesAccount) {

                if (auth()->user()) {
                    return redirect()->back()->with('error', 'Liabilities account not found.');
                } else {
                    return abort(500);
                }
            }

            $clientAdvance = Account::where('name', 'Client')
                ->where('company_id', $payment->agent->branch->company->id)
                ->where('root_id', $liabilitiesAccount->id)
                ->first();

            if (!$clientAdvance) {
                if (auth()->user()) {
                    return redirect()->back()->with('error', 'Client advance account not found.');
                } else {
                    return abort(500);
                }
            }

            DB::beginTransaction();

            try {
                $transaction = Transaction::create([
                    'branch_id' => $payment->agent->branch->id,
                    'company_id' => $payment->agent->branch->company->id,
                    'entity_id' => $payment->agent->branch->company->id,
                    'entity_type' => 'company',
                    'transaction_type' => 'debit',
                    'amount' => $payment->amount,
                    'description' => 'Topup success by ' . $payment->client->full_name,
                    'payment_id' => $payment->id,
                    'invoice_id' => $payment->invoice_id,
                    'payment_reference' => $response['id'],
                    'reference_type' => 'Payment',
                    'transaction_date' => now(),
                ]);

                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $payment->agent->branch->id,
                    'company_id' => $payment->agent->branch->company->id,
                    'invoice_id' => $payment->invoice_id,
                    'account_id' => $clientAdvance->id,
                    'transaction_date' => now(),
                    'description' => 'Advance Payment in voucher number: ' . $payment->voucher_number,
                    'debit' => 0,
                    'credit' => $payment->amount,
                    'balance' => $clientAdvance->actual_balance - $payment->amount,
                    'name' => $payment->client->full_name,
                    'type' => 'receivable',
                    'voucher_number' => $payment->voucher_number,
                    'type_reference_id' => $clientAdvance->id
                ]);
            } catch (Exception $e) {
                DB::rollBack();
                logger('Failed to create journal entry', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                if (auth()->user()) {
                    return redirect()->back()->with('error', 'Payment cannot be updated');
                } else {
                    return abort(500);
                }
            }
            DB::commit();
        } else if ($process == 'invoice') {
            $invoice = Invoice::where('id', $payment->invoice_id)->first();
            if (!$invoice) {
                if (auth()->user()) {
                    return redirect()->back()->with('error', 'Invoice not found.');
                } else {
                    return abort(500);
                }
            }
        } else {

            if (auth()->user()) {
                return redirect()->back()->with('error', 'Invalid process type.');
            } else {
                return abort(500);
            }
            
        }
        
        if (strtolower($payment->payment_gateway) === 'tap') {
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
                'amount' => $finalPaidAmount,
                'currency' => 'KWD',
                'date_created' => $dateCreated,
                'date_completed' => $dateCompleted,
                'date_transaction' => $dateTransaction,
                'receipt_id' => $response['receipt']['id'],
                'receipt_email' => $response['receipt']['email'],
                'receipt_sms' => $response['receipt']['sms'],
            ]);
        }

        try {
            $payment->amount = $finalPaidAmount;
            $payment->service_charge = $serviceFeePaid;
            $payment->status = 'completed';
            $payment->completed = 1;
            $payment->payment_reference = $response['id'];
            $payment->save();

            if ($process == 'invoice') {
                $invoice->status = 'paid';
                $invoice->paid_date = now();
                $invoice->save();
            }

            // if ($invoice->is_client_credit == 2) {
            //     $creditSubmit = Credit::create([
            //         'company_id'  => $invoice->client->agent->branch->company_id,
            //         'client_id'   => $invoice->client->id,
            //         'invoice_id'  => $invoice->id,
            //         'type'        => 'Topup',
            //         'description' => 'Topup Client Credit for ' . $invoice->client->full_name,
            //         'amount'      => $invoice->amount,
            //     ]);    
            // }

            // $croppedOriginalInvoiceNo = Str::before($invoice->invoice_number, '-TC-');

            // $originalInvoice = Invoice::where('invoice_number', $croppedOriginalInvoiceNo)
            //     ->where('is_client_credit', 1)
            //     ->where('status', 'unpaid')
            //     ->first();

            // if ($originalInvoice) {
            //     $originalInvoice->status = 'paid';
            //     $originalInvoice->paid_date = now();
            //     $originalInvoice->save();

            //     $creditSubmit = Credit::create([
            //         'company_id'  => $originalInvoice->client->agent->branch->company_id,
            //         'client_id'   => $originalInvoice->client->id,
            //         'invoice_id'  => $originalInvoice->id,
            //         'type'        => 'Topup',
            //         'description' => 'Payment for ' . $originalInvoice->invoice_number,
            //         'amount'      => -($invoice->amount),
            //     ]);  
            // }

            //dd($process);

            if(auth()->user()){
            return redirect()->route('payment.link.show', ['companyId' => $payment->agent->branch->company->id, 'voucherNumber' => $payment->voucher_number])->with('success', 'Payment successful!');
            } else {
                return redirect()->route('payment.success');
            }
        } catch (Exception $e) {
            logger('Failed to update payment status', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (auth()->user()) {
                return redirect()->route('payment.link.show', ['companyId' => $payment->agent->branch->company->id, 'voucherNumber' => $payment->voucher_number])->with('error', 'Payment cannot be updated.');
            } else {
                return abort(500);
            }
        }

        if (auth()->user()) {
            return redirect()->route('payment.link.show', ['companyId' => $payment->agent->branch->company->id, 'voucherNumber' => $payment->voucher_number])->with('success', 'Payment successful!');
        } else {
            return redirect()->route('payment.success');
        }
    }

    public function handleMyFatoorahCallback(Request $request)
    {
        try {
            Log::info('MyFatoorah callback received', ['request' => $request->all()]);

            $paymentId = $request->query('paymentId') ?? $request->input('paymentId');

            if (!$paymentId) {
                return redirect()->to('/invoices')->with('error', 'Invalid payment callback data.');
            }

            $eventKey = 'mf:callback:' . $paymentId;
            $lock = Cache::lock($eventKey, 40);
            if (!$lock->get()) {
                Log::warning('Duplicate MyFatoorah callback suppressed by lock', ['key' => $eventKey]);
                return response('OK', 200);
            }

            try {
                $configService = new GatewayConfigService();
                $myfatoorahConfig = $configService->getMyFatoorahConfig();

                if(!$myfatoorahConfig['status'] || !$myfatoorahConfig['data']) {
                    return redirect()->to('/invoices')->with('error', $myfatoorahConfig['message'] ?? 'MyFatoorah configuration is missing or inactive');
                }

                $myfatoorahConfig = $myfatoorahConfig['data'];
                $apiKey  = $myfatoorahConfig['api_key'];
                $baseUrl = $myfatoorahConfig['base_url'];

                $statusResponse = Http::withHeaders([
                    'Authorization' => "Bearer $apiKey",
                    'Content-Type' => 'application/json',
                ])->post("$baseUrl/getPaymentStatus", [
                    "Key" => $paymentId,
                    "KeyType" => "PaymentId"
                ]);

                if (!$statusResponse->successful()) {
                    Log::error('Failed to verify payment status', ['response' => $statusResponse->body()]);
                    return redirect()->to('/invoices')->with('error', 'Failed to verify payment status.');
                }

                $statusData = $statusResponse->json();
                Log::info('MyFatoorah payment status', $statusData);

                $userDefinedField   = !empty($statusData['Data']['UserDefinedField']) ? json_decode($statusData['Data']['UserDefinedField'], true) : [];
                $invoiceId = $statusData['Data']['InvoiceId'] ?? null;
                $voucherNumber = $userDefinedField['voucher_number'] ?? null;
                $invoiceStatus = strtolower($statusData['Data']['InvoiceStatus'] ?? '');
                $selectedPartialIds = $userDefinedField['invoice_partial_id'] ?? [];

                if (!$invoiceId || $invoiceStatus !== 'paid') {
                    return redirect()->to('/invoices')->with('error', 'Payment was not completed.');
                }

                //Find the Payment by MyFatoorah InvoiceId
                if ($invoiceId) {
                    $payment = Payment::where('payment_reference', $invoiceId)->first();
                } elseif ($voucherNumber) {
                    $payment = Payment::where('voucher_number', $voucherNumber)->first();
                } else {
                    Log::error('Neither invoiceId nor voucherNumber found for payment matching');
                    return redirect()->to('/invoices')->with('error', 'Payment reference not found.');
                }

                if (!$payment) {
                    Log::error('Payment not found', ['invoiceId' => $invoiceId]);
                    return redirect()->to('/invoices')->with('error', 'Payment record not found.');
                }

                if ($payment->status === 'completed') {
                    Log::info('Callback ignored: payment already completed', ['payment_id' => $payment->id]);
                    return response('OK', 200);
                }

                $process = $userDefinedField['process'] ?? 'invoice';

                if ($process == 'topup') {
                    $clientController = new ClientController;
                    $addCreditResponse = $clientController->addCredit($payment);

                    if (isset($addCreditResponse['error'])) {
                        logger('Failed to add credit to client', [
                            'message' => $addCreditResponse['error'],
                            'payment_id' => $paymentId,
                        ]);
                        return redirect()->route('invoices.index')->with('error', $addCreditResponse['error']);
                    }

                    $liabilitiesAccount = Account::where('name', 'like', '%Liabilities%')
                        ->where('company_id', $payment->agent->branch->company->id)
                        ->first();
                    if (!$liabilitiesAccount) {
                        return redirect()->route('invoices.index')->with('error', 'Liabilities account not found.');
                    }

                    $clientAdvance = Account::where('name', 'Client')
                        ->where('company_id', $payment->agent->branch->company->id)
                        ->where('root_id', $liabilitiesAccount->id)
                        ->first();
                    if (!$clientAdvance) {
                        return redirect()->route('invoices.index')->with('error', 'Client advance account not found.');
                    }

                    DB::beginTransaction();
                    try {
                        $transaction = Transaction::create([
                            'branch_id' => $payment->agent->branch->id,
                            'company_id' => $payment->agent->branch->company->id,
                            'entity_id' => $payment->agent->branch->company->id,
                            'entity_type' => 'company',
                            'transaction_type' => 'debit',
                            'amount' => $payment->amount,
                            'description' => 'Topup success by ' . $payment->client->full_name,
                            'payment_id' => $payment->id,
                            'invoice_id' => $payment->invoice_id,
                            'payment_reference' => $statusData['Data']['InvoiceReference'],
                            'reference_type' => 'Payment',
                            'transaction_date' => now(),
                        ]);

                        JournalEntry::create([
                            'transaction_id' => $transaction->id,
                            'branch_id' => $payment->agent->branch->id,
                            'company_id' => $payment->agent->branch->company->id,
                            'invoice_id' => $payment->invoice_id,
                            'account_id' => $clientAdvance->id,
                            'transaction_date' => now(),
                            'description' => 'Advance Payment in voucher number: ' . $payment->voucher_number,
                            'debit' => 0,
                            'credit' => $payment->amount,
                            'balance' => $clientAdvance->actual_balance - $payment->amount,
                            'name' => $payment->client->full_name,
                            'type' => 'receivable',
                            'voucher_number' => $payment->voucher_number,
                            'type_reference_id' => $clientAdvance->id
                        ]);
                    } catch (Exception $e) {
                        DB::rollBack();
                        logger('Failed to create journal entry', [
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        return redirect()->route('invoices.index')->with('error', 'Payment cannot be updated');
                    }
                    DB::commit();
                }

                $finalPaidAmount = $statusData['Data']['InvoiceValue'];

                $payment->status = 'completed';
                $payment->amount = $finalPaidAmount;
                $payment->save();

                if ($payment->invoice) {
                    DB::transaction(function () use ($payment, $selectedPartialIds, $finalPaidAmount) {
                        if (!empty($selectedPartialIds)) {
                            $partials = InvoicePartial::where('invoice_id', $payment->invoice_id)
                                ->whereIn('id', $selectedPartialIds)
                                ->get();
                    
                            foreach ($partials as $partial) {
                                $partial->status = 'paid';
                                $partial->payment_id = $payment->id;
                                $partial->amount = $finalPaidAmount;
                                $partial->save();
                            }
                        }
                    
                        $invoice = $payment->invoice()->with('invoicePartials:id,invoice_id,status')->first();
                        $hasUnpaid = $invoice->invoicePartials()->where('status', '!=', 'paid')->exists();
                        $hasPaid   = $invoice->invoicePartials()->where('status', 'paid')->exists();
                    
                        if (!$hasUnpaid && $hasPaid) {
                            $invoice->status = 'paid';
                        } elseif ($hasUnpaid && $hasPaid) {
                            $invoice->status = 'partial';
                        }
                        $invoice->save();

                        if ($invoice->status === 'paid' && $invoice->refund && $invoice->refund->status === 'processed') {
                            $invoice->refund->update(['status' => 'completed']);
                        }
                    });

                    $transaction = $statusData['Data']['InvoiceTransactions'][0] ?? [];

                    MyFatoorahPayment::create([
                        'payment_int_id' => $payment->id,
                        'payment_id' => $transaction['PaymentId'] ?? null,
                        'invoice_id' => $statusData['Data']['InvoiceId'],
                        'invoice_ref' => $statusData['Data']['InvoiceReference'],
                        'invoice_status' => $statusData['Data']['InvoiceStatus'],
                        'customer_reference' => $payment->invoice->invoice_number,
                        'payload' => $statusData,
                    ]);


                    try {
                        // Get financial accounts
                        $chargeRecord = Charge::where('name', 'LIKE', '%MyFatoorah%')
                            ->where('company_id', $payment->invoice->agent->branch->company->id)
                            ->first();

                        if (!$chargeRecord) {
                            return redirect()->back()->with('error', 'Charge account not configured.');
                        }

                        $bankPaymentFee = Account::find($chargeRecord->acc_fee_bank_id);
                        $mFAccount = Account::find($chargeRecord->acc_fee_id);
                        $receivableAccount = Account::where('name', 'Clients')->first();

                        if (!$bankPaymentFee || !$mFAccount || !$receivableAccount) {
                            throw new \Exception('One or more financial accounts not found.');
                        }

                        // Create transaction
                        try {
                            $transaction = Transaction::create([
                                'branch_id' => $payment->invoice->agent->branch->id,
                                'company_id' => $payment->invoice->agent->branch->company->id,
                                'entity_id' => $payment->invoice->agent->branch->company->id,
                                'entity_type' => 'company',
                                'transaction_type' => 'debit',
                                'amount' => $statusData['Data']['InvoiceValue'],
                                'description' => 'MyFatoorah payment success: ' . $payment->invoice->invoice_number,
                                'invoice_id' => $payment->invoice->id,
                                'payment_id' => $payment->id,
                                'payment_reference' => $statusData['Data']['InvoiceReference'],
                                'reference_type' => 'Invoice',
                                'transaction_date' => now(),
                            ]);
                        } catch (\Exception $e) {
                            throw new \Exception('Failed to create transaction: ' . $e->getMessage());
                        }

                        $invoiceDetail = InvoiceDetail::where('invoice_number', $payment->invoice->invoice_number)->first();
                        $client = $payment->invoice->client;

                        if (!$invoiceDetail || !$client) {
                            throw new \Exception('Invoice detail or client not found.');
                        }

                        // Receivable Journal
                        try {
                            JournalEntry::create([
                                'transaction_id' => $transaction->id,
                                'branch_id' => $payment->invoice->agent->branch->id,
                                'company_id' => $payment->invoice->agent->branch->company->id,
                                'invoice_id' => $payment->invoice->id,
                                'account_id' => $receivableAccount->id,
                                'invoice_detail_id' => $invoiceDetail->id,
                                'transaction_date' => now(),
                                'description' => 'Client payment received via MyFatoorah',
                                'debit' => 0,
                                'credit' => $statusData['Data']['InvoiceValue'],
                                'balance' => $invoiceDetail->task_price - $statusData['Data']['InvoiceValue'],
                                'name' => $client->full_name,
                                'type' => 'receivable',
                                'voucher_number' => $payment->voucher_number,
                                'type_reference_id' => $receivableAccount->id,
                            ]);
                        } catch (\Exception $e) {
                            throw new \Exception('Failed to create receivable journal entry: ' . $e->getMessage());
                        }

                        try {
                            $gatewayFee = ChargeService::FatoorahCharge($payment->amount, $payment->payment_method_id, $payment->agent->branch->company_id)['gatewayFee'] ?? 0;
                        } catch (Exception $e) {
                            Log::error('FatoorahCharge exception', [
                                'message' => $e->getMessage(),
                                'paymentMethod' => $payment->payment_method_id,
                                'company_id' => $payment->agent->branch->company_id,
                            ]);
                            $gatewayFee = 0;
                        }

                        $netAmount = $statusData['Data']['InvoiceValue']; // Bank Journal (net payment)

                        try {
                            JournalEntry::create([
                                'transaction_id' => $transaction->id,
                                'branch_id' => $payment->invoice->agent->branch->id,
                                'company_id' => $payment->invoice->agent->branch->company->id,
                                'invoice_id' => $payment->invoice->id,
                                'invoice_detail_id' => $invoiceDetail->id,
                                'account_id' => $bankPaymentFee->id,
                                'transaction_date' => now(),
                                'description' => 'Net payment received',
                                'debit' => $netAmount,
                                'credit' => 0,
                                'balance' => $invoiceDetail->task_price - $statusData['Data']['InvoiceValue'],
                                'name' => $bankPaymentFee->name,
                                'type' => 'bank',
                                'voucher_number' => $payment->voucher_number,
                                'type_reference_id' => $bankPaymentFee->id,
                            ]);
                        } catch (\Exception $e) {
                            throw new \Exception('Failed to create bank journal entry: ' . $e->getMessage());
                        }

                        try {
                            $bankPaymentFee->actual_balance += $netAmount;
                            $bankPaymentFee->save();
                        } catch (\Exception $e) {
                            throw new \Exception('Failed to update bank account balance: ' . $e->getMessage());
                        }

                        $paidBy = $payment->paymentMethod?->paid_by ?? null;
                        // Fee Journal (expense)
                        try {
                            $mFAccount->actual_balance += $gatewayFee;
                            $mFAccount->save();
                            JournalEntry::create([
                                'transaction_id' => $transaction->id,
                                'branch_id' => $payment->invoice->agent->branch->id,
                                'company_id' => $payment->invoice->agent->branch->company->id,
                                'invoice_id' => $payment->invoice->id,
                                'invoice_detail_id' => $invoiceDetail->id,
                                'account_id' => $mFAccount->id,
                                'transaction_date' => now(),
                                'description' => ($paidBy === 'Company' ? 'Company Pays Gateway Fee: ' : 'Client Pays Gateway Fee: ') . $mFAccount->name,
                                'debit' => $gatewayFee,
                                'credit' => 0,
                                'balance' => $mFAccount->actual_balance,
                                'name' => $mFAccount->name,
                                'type' => 'charges',
                                'voucher_number' => $payment->voucher_number,
                                'type_reference_id' => $mFAccount->id,
                            ]);
                        } catch (\Exception $e) {
                            throw new \Exception('Failed to create fee journal entry: ' . $e->getMessage());
                        }

                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('Payment processing failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                        return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
                    }

                    return redirect()->route('invoice.show', ['companyId' => $payment->agent->branch->company_id, 'invoiceNumber' => $payment->invoice->invoice_number])
                        ->with('status', 'Payment successful! Thank you for your payment.');
                } else {
                    $transaction = $statusData['Data']['InvoiceTransactions'][0] ?? [];

                    MyFatoorahPayment::updateOrCreate(
                        [
                            'payment_int_id'   => $payment->id,
                            'payment_id'       => $transaction['PaymentId'] ?? null,
                        ],
                        [
                            'invoice_id'       => $statusData['Data']['InvoiceId'],
                            'invoice_ref'       => $statusData['Data']['InvoiceReference'],
                            'invoice_status'   => $statusData['Data']['InvoiceStatus'],
                            'customer_reference' => $payment->voucher_number,
                            'payload'          => $statusData,
                        ]
                    );

                    return redirect()->route('payment.link.show', ['companyId' => $payment->agent->branch->company_id, 'voucherNumber' => $payment->voucher_number])
                        ->with('success', 'Payment successful!');
                }
            } finally {
                optional($lock)->release();
            }
        } catch (\Exception $e) {
            Log::error('MyFatoorah callback exception', ['message' => $e->getMessage()]);
            return redirect()->to('/invoices')->with('error', 'Something went wrong. Please contact support.');

            return redirect()->route('payment.link.show', ['companyId' => $payment->agent->branch->company->id, 'voucherNumber' => $payment->voucher_number])->with('success', 'Payment successful!');
        }
    }

    public function handleMyFatoorahError(Request $request)
    {
        Log::error('MyFatoorah error callback', [
            'request' => $request->all(),
            'query' => $request->query(),
            'input' => $request->input(),
        ]);

        $paymentId = $request->query('paymentId') ?? $request->input('paymentId');

        // Optionally update payment status as failed or cancelled
        if ($paymentId) {
            $payment = Payment::where('payment_reference', $paymentId)->first();
            if ($payment) {
                $payment->status = 'failed';
                $payment->save();
            }
        }

        if ($request->has('invoice_id')) {
            $invoice = Invoice::with('agent.branch', 'client')->find($request->input('invoice_id'));
            $paymentId = $request->query('paymentId') ?? $request->input('paymentId');
            Transaction::create([
                'branch_id' => $invoice->agent->branch->id,
                'company_id' => $invoice->agent->branch->company->id,
                'entity_id' => $invoice->agent->branch->company->id,
                'entity_type' => 'company',
                'transaction_type' => 'credit',
                'amount' => $invoice->amount,
                'description' => 'MyFatoorah payment failed: ' . $invoice->invoice_number,
                'invoice_id' => $invoice->id,
                'payment_id' => $invoice->payment->id,
                'payment_reference' => $invoice->payment->payment_reference,
                'reference_type' => 'Invoice',
                'transaction_date' => now(),
            ]);

            return redirect()->route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number])->with('error', 'Payment failed');
        }

        if ($request->has('payment_id')) {
            $payment = Payment::with('client', 'agent.branch')->find($request->input('payment_id'));
            Transaction::create([
                'branch_id' => $payment->agent->branch->id,
                'company_id' => $payment->agent->branch->company->id,
                'entity_id' => $payment->agent->branch->company->id,
                'entity_type' => 'company',
                'transaction_type' => 'debit',
                'amount' => $payment->amount,
                'description' => 'Topup failed by ' . $payment->client->full_name,
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'payment_reference' => $payment->payment_reference,
                'reference_type' => 'Payment',
                'transaction_date' => now(),
            ]);
        }

        return redirect()->route('payment.link.index')->with('error', 'Payment was not completed or was cancelled.');
    }

    public function paymentUpdateLink($paymentId, Request $request)
    {
        $payment = Payment::find($paymentId);
        if (!$payment) {
            return redirect()->back()->with('error', 'Payment not found.');
        }

        if ($clientId = $request->client_id) {
            $client = Client::find($clientId);
            if (!$client) {
                return redirect()->back()->with('error', 'Client not found.');
            }

            $payment->client_id = $clientId;
        } else {
            $client = $payment->client;
            if (!$client) {
                return redirect()->back()->with('error', 'Client not found.');
            }
        }

        if ($request->agent_id) $payment->agent_id = $request->agent_id;
        if ($request->dial_code) $client->country_code = $request->dial_code;
        if ($request->phone) $client->phone = $request->phone;
        if ($request->payment_gateway) $payment->payment_gateway = $request->payment_gateway;
        if ($request->payment_method_id) $payment->payment_method_id = $request->payment_method_id;
        if ($request->amount) $payment->amount = $request->amount;

        try {
            $payment->update();
            $client->update();
        } catch (Exception $e) {
            Log::error('Failed to update payment link', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()->with('error', 'Failed to update payment link.');
        }

        return redirect()->route('payment.link.index')->with('success', 'Payment link updated successfully!');
    }

    public function paymentDeleteLink($paymentId)
    {
        $payment = Payment::find($paymentId);
        if (!$payment) {
            return redirect()->back()->with('error', 'Payment not found.');
        }

        try {
            $payment->delete();
        } catch (Exception $e) {
            Log::error('Failed to delete payment link', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()->with('error', 'Failed to delete payment link.');
        }

        return redirect()->route('payment.link.index')->with('success', 'Payment link deleted successfully!');
    }


    public function shareLink($paymentId) {}

    public function handleWebhookFatoorah(Request $request)
    {
        $secretKey = config('services.myfatoorah.webhook_secret_key');

        $incomingSignature = $request->header('MyFatoorah-Signature');
        Log::info('Received Signature From MyFatoorah: ' . $incomingSignature);

        $rawBody = $request->getContent();
        Log::info('Raw Body: ' . $rawBody);
        if ($rawBody) {
            $data = json_decode($rawBody, true);
        } else {
            Log::error('Webhook body is empty');
            return response()->json(['error' => 'Empty body received'], 400);
        }

        $generatedSignature = hash_hmac('sha256', $rawBody, $secretKey);
        Log::info('Our Generated Signature: ' . $generatedSignature);

        if (!hash_equals($generatedSignature, $incomingSignature)) {
            Log::error('Invalid signature', [
                'received_signature' => $incomingSignature,
                'generated_signature' => $generatedSignature,
            ]);
            return response()->json(['error' => 'Unauthorized request'], 403);
        }

        Log::info('MyFatoorah Webhook Received', ['body' => json_decode($rawBody, true)]);

        $data = $request->input('Data');
        $invoice = $data['Invoice'];

        $invoiceId = $invoice['Id'];
        $invoiceStatus = $invoice['Status'];

        Log::info('Looking for Payment with Invoice ID: ' . $invoiceId);

        $payment = Payment::where('payment_reference', $invoiceId)->first();
        Log::info('Looking in Payments table for payment_reference: ' . $invoiceId);

        if ($payment) {
            $payment->status = $invoiceStatus;
            $payment->save();

            Log::info('Payment Status Updated', [
                'payment_reference' => $invoiceId,
                'new_status' => $invoiceStatus
            ]);
        } else {
            Log::warning('No matching payment found for Invoice ID: ' . $invoiceId);
        }
        return response()->json(['message' => 'Webhook processed successfully'], 200);
    }

    private function generateSignature($data, $secretKey)
    {
        return hash_hmac('sha256', $data, $secretKey);
    }

    public function handleUPaymentCallback(Request $request) {
        try {
            Log::info('UPayment callback received', ['request' => $request->all()]);

            $trackId = $request->query('trackId') ?? $request->input('trackId') ?? $request->input('track_id');

            if (!$trackId) {
                Log::error('UPayment callback missing trackId', ['request' => $request->all()]);
                return redirect()->to('/invoices')->with('error', 'Invalid payment callback data.');
            }

            $uPayment = new UPayment();
            $statusResponse = $uPayment->getPaymentStatus($trackId);

            Log::info('UPayment status response', ['response' => $statusResponse]);

            if (!$statusResponse['status'] || !isset($statusResponse['data']['transaction'])) {
                Log::error('Failed to get UPayment status', ['response' => $statusResponse]);
                return redirect()->to('/invoices')->with('error', 'Failed to verify payment status.');
            }

            $transaction = $statusResponse['data']['transaction'];
            $result = $transaction['result'] ?? '';
            $status = $transaction['status'] ?? '';
            $orderId = $transaction['order_id'] ?? '';
            $paymentId = $transaction['payment_id'] ?? '';
            $totalPaidAmount = floatval($transaction['total_price'] ?? 0);

            // Check if payment was successful
            if (strtoupper($result) !== 'CAPTURED' || strtolower($status) !== 'done') {
                Log::error('UPayment transaction not successful', [
                    'result' => $result,
                    'status' => $status,
                    'track_id' => $trackId
                ]);
                return redirect()->to('/invoices')->with('error', 'Payment was not completed successfully.');
            }

            // Find the payment record by track_id
            $payment = Payment::where('payment_reference', $trackId)->first();
            
            if (!$payment) {
                Log::error('Payment not found for UPayment track_id', ['track_id' => $trackId]);
                return redirect()->to('/invoices')->with('error', 'Payment record not found.');
            }

            // Determine if this is a topup or invoice payment
            $process = $payment->invoice ? 'invoice' : 'topup';

            Log::info('Processing UPayment', [
                'process' => $process,
                'payment_id' => $payment->id,
                'total_amount' => $totalPaidAmount
            ]);

            if ($process == 'topup') {
                $clientController = new ClientController;

                $addCreditResponse = $clientController->addCredit($payment);

                if (isset($addCreditResponse['error'])) {
                    Log::error('Failed to add credit to client', [
                        'message' => $addCreditResponse['error'],
                        'payment_id' => $payment->id,
                    ]);
                    return redirect()->route('invoices.index')->with('error', $addCreditResponse['error']);
                }

                $liabilitiesAccount = Account::where('name', 'like', '%Liabilities%')
                    ->where('company_id', $payment->agent->branch->company->id)
                    ->first();

                if (!$liabilitiesAccount) {
                    return redirect()->route('invoices.index')->with('error', 'Liabilities account not found.');
                }

                $clientAdvance = Account::where('name', 'Client')
                    ->where('company_id', $payment->agent->branch->company->id)
                    ->where('root_id', $liabilitiesAccount->id)
                    ->first();

                if (!$clientAdvance) {
                    return redirect()->route('invoices.index')->with('error', 'Client advance account not found.');
                }

                DB::beginTransaction();

                try {
                    $transactionRecord = Transaction::create([
                        'branch_id' => $payment->agent->branch->id,
                        'company_id' => $payment->agent->branch->company->id,
                        'entity_id' => $payment->agent->branch->company->id,
                        'entity_type' => 'company',
                        'transaction_type' => 'debit',
                        'amount' => $payment->amount,
                        'description' => 'Topup success by ' . $payment->client->full_name,
                        'payment_id' => $payment->id,
                        'invoice_id' => $payment->invoice_id,
                        'payment_reference' => $trackId,
                        'reference_type' => 'Payment',
                        'transaction_date' => now(),
                    ]);

                    JournalEntry::create([
                        'transaction_id' => $transactionRecord->id,
                        'branch_id' => $payment->agent->branch->id,
                        'company_id' => $payment->agent->branch->company->id,
                        'invoice_id' => $payment->invoice_id,
                        'account_id' => $clientAdvance->id,
                        'transaction_date' => now(),
                        'description' => 'Advance Payment in voucher number: ' . $payment->voucher_number,
                        'debit' => 0,
                        'credit' => $payment->amount,
                        'balance' => $clientAdvance->actual_balance - $payment->amount,
                        'name' => $payment->client->full_name,
                        'type' => 'receivable',
                        'voucher_number' => $payment->voucher_number,
                        'type_reference_id' => $clientAdvance->id
                    ]);

                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    Log::error('Failed to create journal entry for UPayment topup', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    return redirect()->route('invoices.index')->with('error', 'Payment cannot be updated');
                }
            }

            // Get charge configuration for UPayment
            $companyId = optional($payment->agent->branch)->company_id;
            $chargeResult = ChargeService::UPaymentCharge($payment->amount, $payment->payment_method_id, $companyId);

            // Mark payment as completed
            $payment->status = 'completed';
            $payment->amount = $totalPaidAmount;
            $payment->completed = 1;
            $payment->payment_reference = $trackId;
            $payment->save();

            // Update invoice partials if this is an invoice payment
            if ($payment->invoice) {
                DB::transaction(function () use ($payment) {
                    $partials = InvoicePartial::where('invoice_id', $payment->invoice_id)->get();
                    
                    foreach ($partials as $partial) {
                        $partial->status = 'paid';
                        $partial->payment_id = $payment->id;
                        $partial->save();
                    }

                    // Update invoice status
                    $invoice = $payment->invoice;
                    $invoice->status = 'paid';
                    $invoice->paid_date = now();
                    $invoice->save();
                });

                // Create journal entries for invoice payment
                $this->createUPaymentJournalEntries($payment, $totalPaidAmount, $chargeResult);

                return redirect()->route('invoice.show', [
                    'companyId' => $payment->agent->branch->company_id, 
                    'invoiceNumber' => $payment->invoice->invoice_number
                ])->with('status', 'Payment successful! Thank you for your payment.');
            } else {
                return redirect()->route('payment.link.show', [
                    'companyId' => $payment->agent->branch->company_id, 
                    'voucherNumber' => $payment->voucher_number
                ])->with('success', 'Payment successful!');
            }

        } catch (\Exception $e) {
            Log::error('UPayment callback exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->to('/invoices')->with('error', 'Something went wrong. Please contact support.');
        }
    }

    /**
     * Create journal entries for UPayment transactions
     */
    private function createUPaymentJournalEntries($payment, $totalPaidAmount, $chargeResult)
    {
        try {
            $invoice = $payment->invoice;
            $companyId = $payment->agent->branch->company->id;

            // Get required accounts
            $chargeRecord = Charge::where('name', 'UPayment')
                ->where('company_id', $companyId)
                ->first();

            if (!$chargeRecord) {
                Log::warning('UPayment charge record not found', ['company_id' => $companyId]);
                return;
            }

            $bankPaymentFee = Account::find($chargeRecord->acc_fee_bank_id);
            $uPaymentAccount = Account::find($chargeRecord->acc_fee_id);
            $receivableAccount = Account::where('name', 'Clients')->first();

            if (!$bankPaymentFee || !$uPaymentAccount || !$receivableAccount) {
                Log::error('Required accounts not found for UPayment journal entries', [
                    'bank_account_id' => $chargeRecord->acc_fee_bank_id,
                    'upayment_account_id' => $chargeRecord->acc_fee_id,
                    'receivable_account' => $receivableAccount?->id
                ]);
                return;
            }

            $invoiceDetail = InvoiceDetail::where('invoice_number', $invoice->invoice_number)->first();
            $client = $invoice->client;

            DB::beginTransaction();

            try {
                // Create main transaction
                $transaction = Transaction::create([
                    'branch_id' => $invoice->agent->branch->id,
                    'company_id' => $companyId,
                    'entity_id' => $companyId,
                    'entity_type' => 'company',
                    'transaction_type' => 'debit',
                    'amount' => $totalPaidAmount,
                    'description' => 'Payment via UPayment for Invoice: ' . $invoice->invoice_number,
                    'invoice_id' => $invoice->id,
                    'reference_type' => 'Invoice',
                    'transaction_date' => now(),
                ]);

                // Receivable Journal Entry (Credit)
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $invoice->agent->branch->id,
                    'company_id' => $companyId,
                    'invoice_id' => $invoice->id,
                    'account_id' => $receivableAccount->id,
                    'invoice_detail_id' => $invoiceDetail->id,
                    'transaction_date' => now(),
                    'description' => 'Client payment received via UPayment',
                    'debit' => 0,
                    'credit' => $totalPaidAmount,
                    'balance' => $invoiceDetail->task_price - $totalPaidAmount,
                    'name' => $client->full_name,
                    'type' => 'receivable',
                    'voucher_number' => $payment->voucher_number,
                    'type_reference_id' => $receivableAccount->id,
                ]);

                // Bank assets (net amount excluding fee)
                $netAmount = $totalPaidAmount - $chargeRecord->amount;
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $invoice->agent->branch->id,
                    'company_id' => $companyId,
                    'invoice_id' => $invoice->id,
                    'invoice_detail_id' => $invoiceDetail->id,
                    'account_id' => $bankPaymentFee->id,
                    'transaction_date' => now(),
                    'description' => 'Net payment received via UPayment',
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

                // Fee Journal Entry (Expense)
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $invoice->agent->branch->id,
                    'company_id' => $companyId,
                    'invoice_id' => $invoice->id,
                    'invoice_detail_id' => $invoiceDetail->id,
                    'account_id' => $uPaymentAccount->id,
                    'transaction_date' => now(),
                    'description' => 'UPayment service fee',
                    'debit' => $chargeRecord->amount,
                    'credit' => 0,
                    'balance' => $uPaymentAccount->actual_balance + $chargeRecord->amount,
                    'name' => $uPaymentAccount->name,
                    'type' => 'charges',
                    'voucher_number' => $payment->voucher_number,
                    'type_reference_id' => $uPaymentAccount->id,
                ]);

                $uPaymentAccount->actual_balance += $chargeRecord->amount;
                $uPaymentAccount->save();

                DB::commit();

                Log::info('UPayment journal entries created successfully', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $transaction->id,
                    'total_amount' => $totalPaidAmount,
                    'net_amount' => $netAmount,
                    'fee_amount' => $chargeRecord->amount
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Failed to create UPayment journal entries', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('UPayment journal entry creation failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function handleUPaymentError(Request $request) {
        Log::error('UPayment error callback', [
            'request' => $request->all(),
            'query' => $request->query(),
            'input' => $request->input(),
        ]);

        return Auth::user() ? redirect()->route('invoices.index')->with('error', 'Payment was not completed or was cancelled.') : redirect()->route('payment.failed');
    }

    public function handleUPaymentNoti()
    {
        Log::info('UPayment notification received', ['request' => request()->all()]);

        return response()->json(['message' => 'Notification received'], 200);
    }

    public function success()
    {
        return view('payments.success');
    }

    public function failed()
    {
        return view('payments.failed');
    }
}
