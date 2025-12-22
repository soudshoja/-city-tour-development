<?php

namespace App\Support\PaymentGateway;

use App\Http\Traits\HttpRequestTrait;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Role;
use App\Models\Company;
use App\Models\Agent;
use App\Models\Accountant;
use App\Services\GatewayConfigService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class MyFatoorah
{
    use HttpRequestTrait;

    public function createCharge(Request $request)
    {   
        $request->validate([
            'final_amount' => 'required|numeric|min:1',
            'client_name' => 'required|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'invoice_id' => 'nullable|integer|exists:invoices,id',
            'invoice_number' => 'required|string|max:255',
            'payment_id' => 'required|integer|exists:payments,id',
            'payment_gateway' => 'required|string|max:255',
            'payment_method_id' => 'required|integer|exists:payment_methods,id',
            'invoice_partial_id' => 'nullable',
            'client_phone' => 'nullable|string|max:20',
        ]);

        $configService = new GatewayConfigService();
        $myfatoorahConfig = $configService->getMyFatoorahConfig();

        $payment = Payment::find($request->input('payment_id'));

        $company = $payment->agent->branch->company;

        if(!$company) {
            Log::error('MyFatoorah: Company not found for payment', ['payment_id' => $payment->id]);
            return response()->json(['error' => 'Company not found for the agent.'], 404);
        }

        if ($myfatoorahConfig['status'] === 'error') {
            Log::error('MyFatoorah: Configuration error', [
                'message' => $myfatoorahConfig['message'],
                'payment_id' => $payment->id
            ]);
            
            return [
                'status' => 'error',
                'message' => $myfatoorahConfig['message'] ?? 'MyFatoorah configuration is missing or inactive'
            ];
        }

        $myfatoorahConfig = $myfatoorahConfig['data'];

        $apiKey  = $myfatoorahConfig['api_key'];
        $baseUrl = $myfatoorahConfig['base_url'];
        
        $invoiceNumber = $request->input('invoice_number');

        $paymentMethodId = $request->input('payment_method_id');

        $paymentMethod = PaymentMethod::findOrFail($paymentMethodId);

        $customerName = $payment->client->full_name ?? 'Customer';

        if (strpos($customerName, '/') !== false) {
            $customerName = trim(explode('/', $customerName)[0]);
        }

        $clientPhone = $request->input('client_phone') ?? '50000000';

        if (isset($clientPhone) && strpos($clientPhone, '+') === 0) {
            $clientPhone = preg_replace('/^\+\d{1,3}/', '', $clientPhone);
            $clientPhone = ltrim($clientPhone, '0');
        }

        // Determine process type
        $process = $payment->invoice ? 'invoice' : 'topup';

        $userDefinedField = json_encode([
            'voucher_number'      => $payment->voucher_number,
            'process'             => $process,
            'invoice_partial_id'  => $request->input('invoice_partial_id'),
        ]);

        $companyId = $company->id;

        $company = $companyId ? Company::find($companyId) : null;
        $companyEmail = $company?->email ?? 'admin@citytravelers.co';

        $executePayload = [
            "PaymentMethodId"     => $paymentMethod->myfatoorah_id,
            "InvoiceValue"        => $request->input('final_amount'),
            "CustomerName"        => $request->client_name,
            "CustomerEmail"       => $companyEmail,
            "MobileCountryCode"   => $payment->client->country_code ?? '+965',
            "CustomerMobile"      => $clientPhone,
            "DisplayCurrencyIso"  => $payment->currency ?? "KWD",
            "ExpiryDate"         => now()->addDays(2)->toDateString(),
            "CallBackUrl"         => route('payments.callback'),
            "ErrorUrl"            => route('payments.error', ['payment_id' => $payment->id]),
            "Language"            => "en",
            "UserDefinedField"    => $userDefinedField,
            "InvoiceItems" => [
                [
                    "ItemName"   => "Voucher " . $payment->voucher_number,
                    "Quantity"   => 1,
                    "UnitPrice"  => $request->input('final_amount'),
                ]
            ],
        ];

        Log::info('MyFatoorah: ExecutePayment payload', ['payload' => $executePayload]);

        $executeResponse = Http::withHeaders([
            'Authorization' => "Bearer $apiKey",
            'Content-Type' => 'application/json',
        ])->post("$baseUrl/ExecutePayment", $executePayload);

        Log::info('MyFatoorah: ExecutePayment response', ['response' => $executeResponse->json()]);

        if (!$executeResponse->successful()) {
            $errorBody = $executeResponse->json();
            Log::error('MyFatoorah: ExecutePayment failed', [
                'response' => $errorBody,
                'status' => $executeResponse->status(),
                'payment_id' => $payment->id
            ]);
            
            return [
                'status' => 'error',
                'message' => $errorBody['Message'] ?? 'Payment initiation failed',
                'errors' => $errorBody['ValidationErrors'] ?? [],
                'response' => $errorBody
            ];
        }

        $resData = $executeResponse->json();

        // Validate response structure
        if (!isset($resData['Data']['InvoiceId']) || !isset($resData['Data']['PaymentURL'])) {
            Log::error('MyFatoorah: Invalid response structure', [
                'response' => $resData,
                'payment_id' => $payment->id
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Invalid response from MyFatoorah - missing required fields',
                'response' => $resData
            ];
        }

        Log::info('MyFatoorah: Charge created successfully', [
            'payment_id' => $payment->id,
            'voucher_number' => $payment->voucher_number,
            'invoice_id' => $resData['Data']['InvoiceId'],
            'payment_url' => $resData['Data']['PaymentURL']
        ]);

        return [
            'status' => 'success',
            'data' => $resData['Data'],
            'payment_url' => $resData['Data']['PaymentURL'],
            'invoice_id' => $resData['Data']['InvoiceId'],
            'expiry_date' => $resData['Data']['ExpiryDate'] ?? null
        ];
    }

    public function getCharge($chargeId)
    {
        $configService = new GatewayConfigService();
        $myfatoorahConfig = $configService->getMyFatoorahConfig();

        if($myfatoorahConfig['status'] === 'error') {
            return $myfatoorahConfig;
        }
        
        $response = $this->getRequest(
            $myfatoorahConfig['base_url'] . '/charges/' . $chargeId,
            array(
                'Authorization: Bearer ' . $myfatoorahConfig['api_key'],
            ),
            [],
        );

        logger('
        
        ', $response);

        return $response;
    }

}
