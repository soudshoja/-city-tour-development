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

    public function create($invoiceNumber, Request $request)
    {
        $request->validate([
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email',
            'client_phone' => 'required|string|max:15',
            // 'selected_items' => 'required|array',
            'total_amount' => 'required|numeric',
            'payment_gateway' => 'required|string',
            'payment_method' => 'nullable|string',
            'invoice_partial_id' => 'required|array'
        ]);
        Log::info('Received payment request', $request->all());

        $invoice = Invoice::with('agent.branch', 'client')->where('invoice_number', $invoiceNumber)->first();
        $selectedPartials = InvoicePartial::whereIn('id', $request->invoice_partial_id)->get();

        $data = [
            'invoice' => $invoice,
            'client_name' => $request->client_name,
            'client_email' => $request->client_email,
            'client_phone' => $request->client_phone,
            'total_amount' => $request->total_amount,
            'payment_gateway' => $request->payment_gateway,
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

        $client = $invoice->client;

        if (!$client) {
            return response()->json(['error' => 'Client not found for the invoice'], 404);
        }

        $invoicePartialIds = $data['invoice_partial_id'] ?? [];
        $selectedPartials = InvoicePartial::whereIn('id', $invoicePartialIds)->get();

        if ($selectedPartials->isEmpty()) {
            return response()->json(['error' => 'No invoice partials selected for payment.'], 400);
        }

        $totalServiceFee = 0;
        $baseAmount = $selectedPartials->sum('amount');
        $companyId = $invoice->agent->branch->company_id;

        foreach ($selectedPartials as $partial) {
            $chargeResult = [];
            if (strtolower($data['payment_gateway']) === 'tap') {
                $chargeData = [
                    'amount'    => $partial->amount,
                    'client_id' => $invoice->client_id,
                    'agent_id'  => $invoice->agent_id,
                    'currency'  => $invoice->currency,
                ];
                $chargeResult = ChargeService::TapCharge($chargeData, $data['payment_gateway']);
            } elseif (strtolower($data['payment_gateway']) === 'myfatoorah') {
                $chargeResult = ChargeService::FatoorahCharge($partial->amount, $data['payment_method'], $companyId);
            }
            if ($chargeResult['paid_by'] !== 'Company') {
                $totalServiceFee += $chargeResult['fee'];
            }
        }
        $finalAmount = $baseAmount + $totalServiceFee;

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
            'from' => $invoice->client->name,
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

        if (strtolower($data['payment_gateway']) === 'tap') {

            $requestTap = [
                'amount' => $finalAmount,
                'currency' => 'KWD',
                'save_card' => false,
                'customer' => [
                    'first_name' => $data['client_name'],
                    'email' => $data['client_email'] ?? 'link@citycommerce.group',
                ],
                'source' => [
                    'id' => 'src_all',
                ],
                'description' => 'Payment for invoice: ' . $invoice->id,
                'metadata' => [
                    'invoice_number' => $invoice->invoice_number,
                    'payment_id' => $payment->id,
                    'payment_gateway' => $payment->payment_gateway,
                    'invoice_partial_id' => json_encode($data['invoice_partial_id']),
                ],
                'redirect' => [
                    'url' => $data['redirect_url'],
                ],
                'post' => [
                    'url' => $data['webhook_url'],
                ],
            ];

            if (config('app.env') == 'production') {
                $requestTap['post'] = [
                    'url' => route('payment.link.webhook'),
                ];
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

        if (strtolower($data['payment_gateway']) === 'myfatoorah') {
            $apiKey = config('services.myfatoorah.api_key');
            $baseUrl = config('services.myfatoorah.base_url');
            $invoiceNumber = $invoice->invoice_number;
            $paymentMethodId = $data['payment_method'];

            $customerName = $invoice->client->name ?? 'Customer';
            if (strpos($customerName, '/') !== false) {
                $customerName = trim(explode('/', $customerName)[0]);
            }

            $clientPhone = $data['client_phone'] ?? '50000000';

            if (isset($clientPhone) && strpos($clientPhone, '+') === 0) {
                $clientPhone = preg_replace('/^\+\d{1,3}/', '', $clientPhone);
                $clientPhone = ltrim($clientPhone, '0');
            }

            $executePayload = [
                "PaymentMethodId"     => $paymentMethodId,
                "InvoiceValue"        => $finalAmount,
                "CustomerName"        => $customerName,
                "CustomerEmail"       => 'shoja@citytravelers.co',
                "MobileCountryCode"   => $client->country_code ?? '+965',
                "CustomerMobile"      => $clientPhone,
                "DisplayCurrencyIso"  => "KWD",
                "CallBackUrl"         => route('payments.callback'),
                "ErrorUrl"            => route('payments.error', ['invoice_id' => $invoice->id]),
                "Language"            => "en",
                "CustomerReference"   => $invoiceNumber,
                "UserDefinedField"    => (string) $invoice->id,
                "InvoiceItems" => [
                    [
                        "ItemName"   => "Invoice " . $invoiceNumber,
                        "Quantity"   => 1,
                        "UnitPrice"  => $finalAmount,
                    ]
                ],
            ];

            $executeResponse = Http::withHeaders([
                'Authorization' => "Bearer $apiKey",
                'Content-Type' => 'application/json',
            ])->post("$baseUrl/ExecutePayment", $executePayload);
            if (!$executeResponse->successful()) {
                Log::error('MyFatoorah: ExecutePayment failed', ['response' => $executeResponse->body()]);
                return response()->json(['error' => 'ExecutePayment failed.'], 500);
            }

            $resData = $executeResponse->json();
            Log::info('MyFatoorah: ExecutePayment response', ['response' => $resData]);

            $invoiceUrl = $resData['Data']['PaymentURL'] ?? null;
            $mfInvoiceId = $resData['Data']['InvoiceId'] ?? null;
            $expiryDateURL = $resData['Data']['ExpiryDate'] ?? null;

            if ($invoiceUrl && $mfInvoiceId) {
                $payment->payment_reference = $mfInvoiceId;
                $payment->payment_url = $invoiceUrl;
                $payment->expiry_date = $expiryDateURL ? Carbon::parse($expiryDateURL) : now()->addDays(2);
                $payment->status = 'initiate';
                $payment->save();

                return response()->json([
                    'success' => 'Redirecting to MyFatoorah',
                    'url' => $invoiceUrl,
                ]);
            }

            $payment->delete();
            return response()->json(['error' => 'PaymentURL or InvoiceId missing.'], 500);
        }

        $payment->delete();
        return response()->json(['error' => 'Unsupported payment method'], 400);
    }

    public function process(Request $request)
    {
        Log::info('process:', ['process' => $request]);
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

        if ($response['status'] != 'CAPTURED') {
            $invoice = Invoice::with('agent.branch', 'client')->where('invoice_number', $invoiceNumber)->first();
            $paymentId = $response['metadata']['payment_id'] ?? null;
            Transaction::create([
                'branch_id' => $invoice->agent->branch->id,
                'company_id' => $invoice->agent->branch->company->id,
                'entity_id' => $invoice->agent->branch->company->id,
                'entity_type' => 'company',
                'transaction_type' => 'credit',
                'amount' => $response['amount'],
                'description' => 'Payment failed: ' . $invoiceNumber,
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

            return redirect()->route('invoice.show', ['invoiceNumber' => $invoiceNumber])->with('error', 'Payment failed');
        }

        $clientName = $response['customer']['first_name'];
        $clientEmail = $response['customer']['email'];
        if (isset($response['customer']['phone'])) {
            $clientPhone = $response['customer']['phone'];
        }
        $totalAmount = $response['amount'];
        $paymentId = $response['metadata']['payment_id'];
        $paymentGateway = $response['metadata']['payment_gateway'];
        // $invoicePartialIds = $response['metadata']['invoice_partial_id'];
        $invoicePartialIds = json_decode($response['metadata']['invoice_partial_id'], true);

        if (!$paymentGateway) {
            Log::error('Payment gateway not found in response', ['response' => $response]);
            return redirect()->route('invoice.show', ['invoiceNumber' => $invoiceNumber])->with('error', 'Something went wrong, please try again later.');
        }

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

        $chargeRecord = Charge::where('name', $paymentGateway)
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
        } else {
            Log::error('Charge record not found for payment gateway', ['payment_gateway' => $paymentGateway, 'company_id' => $invoice->agent->branch->company->id]);
            return redirect()->route('invoice.show', ['invoiceNumber' => $invoiceNumber])->with('error', 'Something went wrong, please try again later.');
        }


        if (!$invoice) {
            Log::error('Invoice not found', ['invoice_number' => $invoiceNumber]);
            return redirect()->route('invoice.show', ['invoiceNumber' => $invoiceNumber])->with('error', 'Something went wrong, please try again later.');
        }
        //dd($invoiceDetails);

        if (!empty($invoiceDetails)) {

            // dd($invoiceDetails);
            //foreach ($invoiceDetails as $invoiceDetail) {
            try {

                $invoiceDetail = $invoiceDetails->first();

                // Check if there's at least one invoice detail to process
                if (!$invoiceDetail) {
                    Log::error('No invoice details found for processing', ['invoice_number' => $invoiceNumber]);
                    return redirect()->route('invoice.show', ['invoiceNumber' => $invoiceNumber])->with('error', 'Something went wrong, please try again later.');
                }

                $selectedtask = Task::where('id', $invoiceDetail->task_id)->first();
                //$selectedtask = Task::where('id', $invoiceDetail['task_id'])->first();
                $supplier = Supplier::where('id', operator: $selectedtask->supplier_id)->first();
                $client = Client::where('id', operator: $selectedtask->client_id)->first();
                $agent = Agent::where('id', operator: $selectedtask->agent_id)->first();

                $receivableAccount = Account::where('name', 'Clients')->first();
                $receivableAccountId = $receivableAccount->id;
                //dd($receivableAccount, $client->name);

                if (!$receivableAccount || !$receivableAccountId) {
                    Log::error('Receivable account not found', ['company_id' => $invoice->agent->branch->company->id]);
                    return redirect()->route('invoice.show', ['invoiceNumber' => $invoiceNumber])->with('error', 'Something went wrong, please try again later.');
                }

                if (!$invoice->agent || !$invoice->agent->branch || !$invoice->agent->branch->company) {
                    Log::error('Agent or branch or company not found for invoice', ['invoice_id' => $invoice->id]);
                    return redirect()->route('invoice.show', ['invoiceNumber' => $invoiceNumber])->with('error', 'Something went wrong, please try again later.');
                }

                // Create a transaction record first
                $transaction = Transaction::create([
                    'branch_id' =>  $invoice->agent->branch->id,
                    'company_id' =>  $invoice->agent->branch->company->id,
                    'entity_id' =>  $invoice->agent->branch->company->id,
                    'entity_type' => 'company',
                    'transaction_type' => 'debit',
                    'amount' => $totalPaidAmount,
                    'description' => 'Invoice ' . $invoiceNumber . ' paid successfully',
                    'invoice_id' => $invoice->id,
                    'payment_id' => $paymentId,
                    'payment_reference' => $response['id'],
                    'reference_type' => 'Invoice',
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
                    'description' => 'Client Pays via ' . $bankPaymentFee->name . ' by (Assets): ' . $client->name,
                    'debit' => 0,
                    'credit' => $totalPaidAmount,
                    'balance' => $invoiceDetail['task_price'] - $totalPaidAmount,
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
                        'description' => 'Client Pays by ' . $client->name . ' via (Assets): ' . $bankPaymentFee->name,
                        'debit' => $totalPaidAmount, //$totalPaidAmount-$defaultPaymentGatewayFee
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
                        'description' => 'Record Payment Gateway Charge (Expenses): ' . $tapAccount->name,
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
                    // $selectedtask->status = 'ticketed';
                    // $selectedtask->save();

                }


                //dd($e->getMessage());

            } catch (\Exception $e) {
                Log::error('Failed in invoice processing', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return redirect()->route('invoice.show', ['invoiceNumber' => $invoiceNumber])
                    ->with('error', 'Something went wrong while processing the invoice. Please try again later.');
            }
            //}
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
            } elseif (strtolower($payment->payment_gateway) === 'myfatoorah') {
                $chargeResult = ChargeService::FatoorahCharge($invoicePartial->amount, $payment->payment_method_id, $companyId);
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

    public function importPaidFatoorah(Request $request)
{
    Log::info('Starting to import MyFatoorah payment from portal');

    $paymentId = $request->input('import_payment_id');
    if (!$paymentId) {
        return redirect()->back()->with('error', 'heh lemah, payment ID is required for import la wehhh');
    }

    $apiKey = config('services.myfatoorah.api_key');
    $baseUrl = config('services.myfatoorah.base_url');

    $response = Http::withHeaders([
        'Authorization' => "Bearer $apiKey",
        'Content-Type' => 'application/json',
    ])->post("$baseUrl/getPaymentStatus", [
        "Key" => $paymentId,
        "KeyType" => "PaymentId"
    ]);

    if (!$response->successful()) {
        Log::error('Failed to fetch payment status from MyFatoorah', ['response' => $response->body()]);
        return redirect()->back()->with('error', 'Failed to fetch payment status.');
    }

    $responseData = $response->json();
    $invoiceStatus = $responseData['Data']['InvoiceStatus'] ?? null;
    $userDefined = json_decode($responseData['Data']['UserDefinedField'] ?? '{}', true);

    if ($invoiceStatus === 'Paid') {
        $paymentGateway = Arr::get($userDefined, 'payment_gateway');
        $paymentMethod = collect($responseData['Data']['InvoiceTransactions'] ?? [])
            ->firstWhere('TransactionStatus', 'Succss')['PaymentGateway'] ?? null;
        $amount = $responseData['Data']['InvoiceValue'] ?? 0;
        $clientId = Arr::get($userDefined, 'client_id'); // You can adjust this based on your logic
        $agentId = Arr::get($userDefined, 'agent_id');   // Same here

        Log::info('Redirecting to form with pre-filled data', [
            'payment_gateway' => $paymentGateway,
            'payment_method' => $paymentMethod,
            'amount' => $amount,
            'client_id' => $clientId,
            'agent_id' => $agentId,
            'notes' => 'Imported from MyFatoorah',
        ]);

        return redirect()
            ->route('payment.link.create') // Make sure this route exists
            ->withInput([
                'payment_gateway' => $paymentGateway,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'client_id' => $clientId,
                'agent_id' => $agentId,
                'notes' => 'Imported from MyFatoorah',
            ]);
    }

    Log::error('Payment not marked as paid', ['invoiceStatus' => $invoiceStatus]);
    return redirect()->back()->with('error', 'Payment not found or not paid.');
}



    public function paymentLink()
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

        $clients = Client::whereIn('agent_id', $agentsId)->get();
        $payments = Payment::with('invoice')
            ->where(function ($query) use ($agentsId) {
                $query->whereHas('invoice', function ($payment) use ($agentsId) {
                    $payment->whereIn('agent_id', $agentsId);
                })->orWhereIn('agent_id', $agentsId);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $payments->getCollection()->transform(function ($payment) {
            if ($payment->payment_gateway === 'MyFatoorah') {
                $mfPayment = MyFatoorahPayment::where('payment_int_id', $payment->id)->first();
                $payment->invoice_ref = $mfPayment->invoice_ref ?? null;
            } else {
                $payment->invoice_ref = null;
            }
            return $payment;
        });

        // $invoice = Invoice::where('id', $payment->invoice_id)->first();

        // if (!$invoice) {
        //     return redirect()->back()->with('error', 'Invoice not found.');
        // }
        $paymentGateways = Charge::where('type', ChargeType::PAYMENT_GATEWAY)
            ->where('is_active', true)->get();
        $paymentMethods = PaymentMethod::where('is_active', true)->get();

        return view('payment.link.index', compact(
            'payments',
            'clients',
            'agents',
            'paymentGateways',
            'paymentMethods'
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

        $clients = Client::whereIn('agent_id', $agentsId)->get();
        $invoices = Invoice::all();
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
        $request->validate([
            'source' => 'nullable|string'
        ]);

        if ($request->source === 'import')
        {
            Log::info('Processing payment link creation from import source');

            $request->validate([
                'payment_gateway' => 'required',
                'payment_method' => 'nullable|string',
                'amount' => 'required|numeric',
                'notes' => 'nullable|string|max:255',
                'client_id' => 'nullable',
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

            if (!$client) {
                return [
                    'status' => 'error',
                    'message' => 'Client cannot be found',
                ];
            }

            $agent = Agent::where('id', $request->agent_id)->first();

            if (!$agent) {
                return [
                    'status' => 'error',
                    'message' => 'Agent cannot be found'
                ];
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
                return [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }

            try {
                $data = [
                    'voucher_number' => $voucherNumber,
                    'from' => $client->name,
                    'pay_to' => $agent->branch->company->name,
                    'currency' => 'KWD',
                    'payment_date' => Carbon::now(),
                    'amount' => $request->amount,
                    'payment_gateway' => $request->payment_gateway,
                    'payment_method_id' => $request->payment_method,
                    'status' => 'completed',
                    'client_id' => $client->id,
                    'agent_id' => $agent->id,
                    'notes' => $request->notes,
                ];

                if ($request->invoice_id !== null) {
                    $data['invoice_id'] = $request->invoice_id;
                }

                $data['created_by'] = Auth::id();

                $payment = Payment::create($data);
                Log::info('Payment created successfully', ['payment' => $payment]);
            } catch (Exception $e) {
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
                'clientEmail' => $client->email,
                'data' => $payment
            ];
        } else {
            $request->validate([
                'payment_gateway' => 'required',
                'payment_method' => 'nullable|string',
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

            if (!$client) {
                return [
                    'status' => 'error',
                    'message' => 'Client cannot be found',
                ];
            }

            $agent = Agent::where('id', $request->agent_id)->first();

            if (!$agent) {
                return [
                    'status' => 'error',
                    'message' => 'Agent cannot be found'
                ];
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
                return [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }

            try {
                $data = [
                    'voucher_number' => $voucherNumber,
                    'from' => $client->name,
                    'pay_to' => $agent->branch->company->name,
                    'currency' => 'KWD',
                    'payment_date' => Carbon::now(),
                    'amount' => $request->amount,
                    'payment_gateway' => $request->payment_gateway,
                    'payment_method_id' => $request->payment_method,
                    'status' => 'pending',
                    'client_id' => $client->id,
                    'agent_id' => $agent->id,
                    'notes' => $request->notes,
                ];

                if ($request->invoice_id !== null) {
                    $data['invoice_id'] = $request->invoice_id;
                }

                $data['created_by'] = Auth::id();

                $payment = Payment::create($data);
            } catch (Exception $e) {
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
                'clientEmail' => $client->email,
                'data' => $payment
            ];
        }
       
    }

    public function paymentStoreLink(Request $request)
    {
        $response = $this->paymentStoreLinkProcess($request);
        if ($response['status'] === 'error') {
            return redirect()->back()->with('error', $response['message']);
        }

        //dd($response['data']);
        $voucherNumber = $response['data']['voucher_number'];
        $paymentUrl = url('/payment/link/show/' . $voucherNumber);
        // Mail::to($response['clientEmail'])->send(new PaymentLinkEmail($paymentUrl));
        return redirect()->route('payment.link.index')->with('success', 'Payment link created successfully!');
    }

    public function paymentShowLink($voucherNumber)
    {
        $payment = Payment::with('agent', 'client')->where('voucher_number', $voucherNumber)->first();

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
        $companyId = optional($payment->agent->branch)->company_id;

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

        $companyLogoPath = public_path('images/CityLogo.png');
        $companyLogoData = base64_encode(file_get_contents($companyLogoPath));
        $companyLogoSrc = 'data:image/png;base64,' . $companyLogoData;

        return view('payment.link.show', compact('payment', 'chargeResult', 'gatewayFee', 'finalAmount', 'paidBy', 'companyLogoSrc'));
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
        if ($payment->invoice) {
            $process = 'invoice';
        }
        $paymentGateway = $payment->payment_gateway;
        $paymentMethod = $payment->payment_method_id;

        if (strtolower($paymentGateway) === 'tap') {

            $chargeResult = ChargeService::TapCharge([
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'client_id' => $payment->client_id,
                'agent_id' => $payment->agent_id
            ], 'Tap');
            $finalAmount = $chargeResult['finalAmount'];
            $gatewayFee = $chargeResult['fee'];
            $paidBy = $chargeResult['paid_by'];

            $requestTap = [
                'amount' => $finalAmount,
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
                    'payment_gateway' => $paymentGateway,
                    'process' => $process,
                ],
                'redirect' => [
                    'url' => route('payment.link.process'),
                ],
            ];

            if (config('app.env') == 'production') {
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

        if (strtolower($paymentGateway) === 'myfatoorah') {
            $apiKey = config('services.myfatoorah.api_key');
            $baseUrl = config('services.myfatoorah.base_url');

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
            $customerName = optional($payment->client)->name ?? 'Customer';
            if (strpos($customerName, '/') !== false) {
                $customerName = trim(explode('/', $customerName)[0]);
            }

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
            //dd($executePayload);
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

        $apiKey = config('services.myfatoorah.api_key');
        $baseUrl = config('services.myfatoorah.base_url');

        $companyId = optional($payment->agent->branch)->company_id;

        $customerName = optional($payment->client)->name ?? 'Customer';
        if (strpos($customerName, '/') !== false) {
            $customerName = trim(explode('/', $customerName)[0]);
        }

        $client = $payment->client;
        $clientPhone = $client->phone ?? '50000000';
        if (isset($clientPhone) && strpos($clientPhone, '+') === 0) {
            $clientPhone = preg_replace('/^\+\d{1,3}/', '', $clientPhone);
            $clientPhone = ltrim($clientPhone, '0');
        }

        $chargeResult = ChargeService::FatoorahCharge($payment->amount, $payment->payment_method_id, $companyId);
        $finalAmount = $chargeResult['finalAmount'];

        $executePayload = [
            "PaymentMethodId"     => $payment->payment_method_id,
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
                'payment_method'   => $payment->payment_method_id,
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

        $executeResponse = Http::withHeaders([
            'Authorization' => "Bearer $apiKey",
            'Content-Type'  => 'application/json',
        ])->post("$baseUrl/ExecutePayment", $executePayload);

        if (!$executeResponse->successful()) {
            Log::error('Reinitiate MyFatoorah ExecutePayment failed', ['response' => $executeResponse->body()]);
            return redirect()->to('/invoices')->with('error', 'Failed to reinitiate payment.');
        }

        $resData = $executeResponse->json();
        $invoiceUrl = $resData['Data']['PaymentURL'] ?? null;
        $mfInvoiceId = $resData['Data']['InvoiceId'] ?? null;

        if ($invoiceUrl && $mfInvoiceId) {
            // Optionally update the same payment record (or skip if already stored)
            $payment->payment_reference = $mfInvoiceId;
            $payment->status = 'initiate';
            $payment->save();

            return redirect($invoiceUrl);
        }

        return redirect()->to('/invoices')->with('error', 'Failed to retrieve reinitiation URL.');
    }

    public function paymentLinkProcess(Request $request)
    {
        if ($request->tap_id) {
            $tapId = $request->tap_id;
            $tap = new Tap();
            $response = $tap->getCharge($tapId);
        } else {
            return redirect()->back()->with('error', 'Payment not found.');
        }

        if (isset($response['errors'])) {
            return redirect()->back()->with('error', $response['errors'][0]['description']);
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
                'description' => 'Topup failed by ' . $payment->client->name,
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'payment_reference' => $response['id'],
                'reference_type' => 'Payment',
            ]);
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
        } elseif (strtolower($payment->payment_gateway) === 'myfatoorah') {
            $chargeResult = ChargeService::FatoorahCharge($baseAmount, $payment->payment_method_id, $companyId);
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
                    'description' => 'Topup success by ' . $payment->client->name,
                    'payment_id' => $payment->id,
                    'invoice_id' => $payment->invoice_id,
                    'payment_reference' => $response['id'],
                    'reference_type' => 'Payment',
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
                    'name' => $payment->client->name,
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
                return redirect()->back()->with('error', 'Payment cannot be updated');
            }
            DB::commit();
        } else if ($process == 'invoice') {
            $invoice = Invoice::where('id', $payment->invoice_id)->first();
            if (!$invoice) {
                return redirect()->back()->with('error', 'Invoice not found.');
            }
        } else {
            return redirect()->back()->with('error', 'Invalid process type.');
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
            //         'description' => 'Topup Client Credit for ' . $invoice->client->name,
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

            return redirect()->route('payment.link.show', ['voucherNumber' => $payment->voucher_number])->with('success', 'Payment successful!');
        } catch (Exception $e) {
            logger('Failed to update payment status', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('payment.link.show', ['voucherNumber' => $payment->voucher_number])->with('error', 'Payment cannot be updated.');
        }

        return redirect()->route('payment.link.show', ['voucherNumber' => $payment->voucher_number])->with('success', 'Payment successful!');
    }

    public function handleMyFatoorahCallback(Request $request)
    {
        try {
            Log::info('MyFatoorah callback received', ['request' => $request->all()]);

            $paymentId = $request->query('paymentId') ?? $request->input('paymentId');

            if (!$paymentId) {
                return redirect()->to('/invoices')->with('error', 'Invalid payment callback data.');
            }

            //Get payment status from MyFatoorah
            $apiKey = config('services.myfatoorah.api_key');
            $baseUrl = config('services.myfatoorah.base_url');

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

            $invoiceId = $statusData['Data']['InvoiceId'] ?? null;
            $voucherNumber = $statusData['Data']['UserDefinedField'] ?? null;
            $invoiceStatus = strtolower($statusData['Data']['InvoiceStatus'] ?? '');
            $userDefinedField = $statusData['Data']['UserDefinedField'] ?? null;

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

            if ($statusData['Data']['UserDefinedField']) {
                $userDefinedField = json_decode($statusData['Data']['UserDefinedField'], true);
            } else {
                $userDefinedField = [];
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
                        'description' => 'Topup success by ' . $payment->client->name,
                        'payment_id' => $payment->id,
                        'invoice_id' => $payment->invoice_id,
                        'payment_reference' => $statusData['Data']['InvoiceReference'],
                        'reference_type' => 'Payment',
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
                        'name' => $payment->client->name,
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
            $companyId = optional($payment->agent->branch)->company_id;
            $chargeResult = ChargeService::FatoorahCharge($payment->amount, $payment->payment_method_id, $companyId);
            $serviceFeePaid = $chargeResult['fee'] ?? 0;

            //Mark payment as completed
            $payment->status = 'completed';
            $payment->amount = $finalPaidAmount;
            $payment->save();

            if ($payment->invoice) {
                $payment->invoice->status = 'paid';
                $payment->invoice->save();

                $matchingPartial = $payment->invoice->invoicePartials
                    ->where('invoice_number', $payment->invoice->invoice_number)
                    ->first();

                if ($matchingPartial) {
                    $matchingPartial->amount = $finalPaidAmount;
                    $matchingPartial->status = 'paid';
                    $matchingPartial->payment_id = $payment->id; // Save the payment ID to each partial
                    $matchingPartial->save();
                }

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
                            'name' => $client->name,
                            'type' => 'receivable',
                            'voucher_number' => $payment->voucher_number,
                            'type_reference_id' => $receivableAccount->id,
                        ]);
                    } catch (\Exception $e) {
                        throw new \Exception('Failed to create receivable journal entry: ' . $e->getMessage());
                    }

                    // Bank Journal (net payment)
                    $netAmount = $statusData['Data']['InvoiceValue'] - $chargeRecord->amount;

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

                    // Fee Journal (expense)
                    try {
                        JournalEntry::create([
                            'transaction_id' => $transaction->id,
                            'branch_id' => $payment->invoice->agent->branch->id,
                            'company_id' => $payment->invoice->agent->branch->company->id,
                            'invoice_id' => $payment->invoice->id,
                            'invoice_detail_id' => $invoiceDetail->id,
                            'account_id' => $mFAccount->id,
                            'transaction_date' => now(),
                            'description' => 'MyFatoorah service fee',
                            'debit' => $chargeRecord->amount,
                            'credit' => 0,
                            'balance' => $mFAccount->actual_balance + $chargeRecord->amount,
                            'name' => $mFAccount->name,
                            'type' => 'charges',
                            'voucher_number' => $payment->voucher_number,
                            'type_reference_id' => $mFAccount->id,
                        ]);
                    } catch (\Exception $e) {
                        throw new \Exception('Failed to create fee journal entry: ' . $e->getMessage());
                    }

                    try {
                        $mFAccount->actual_balance += $chargeRecord->amount;
                        $mFAccount->save();
                    } catch (\Exception $e) {
                        throw new \Exception('Failed to update fee account balance: ' . $e->getMessage());
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Payment processing failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                    return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
                }
                return redirect()->route('invoice.show', $payment->invoice->invoice_number)->with('success', 'Payment completed successfully!');
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

                //return redirect()->route('payment.link.index')->with('success', 'Payment completed successfully using voucher!');   
                return redirect()->route('payment.link.show', ['voucherNumber' => $payment->voucher_number])->with('success', 'Payment successful!');
            }
        } catch (\Exception $e) {
            Log::error('MyFatoorah callback exception', ['message' => $e->getMessage()]);
            return redirect()->to('/invoices')->with('error', 'Something went wrong. Please contact support.');

            return redirect()->route('payment.link.show', ['voucherNumber' => $payment->voucher_number])->with('success', 'Payment successful!');
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
                'reference_type' => 'Invoice'
            ]);

            return redirect()->route('invoice.show', ['invoiceNumber' => $invoice->invoice_number])->with('error', 'Payment failed');
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
                'description' => 'Topup failed by ' . $payment->client->name,
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'payment_reference' => $payment->payment_reference,
                'reference_type' => 'Payment',
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
        $secretKey = env('MYFATOORAH_SECRET_KEY');

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
}
