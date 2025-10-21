<?php

namespace App\Support\PaymentGateway;

use App\Http\Traits\HttpRequestTrait;
use App\Models\Payment;
use App\Models\Company;
use App\Models\Agent;
use App\Models\Accountant;
use App\Models\User;
use App\Models\Role;
use App\Services\GatewayConfigService;
use App\Services\HesabeCrypt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class Hesabe 
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
         
        $auth = Auth::user();
        $configService = new GatewayConfigService();
        $hesabeConfig = $configService->getHesabeConfig();

        $payment = Payment::find($request->input('payment_id'));

        $company = $payment->agent->branch->company;

        if(!$company){
            Log::error('Hesabe: Company not found for payment', ['payment_id' => $payment->id]);
            return response()->json(['error' => 'Company not found for the agent.'], 404);
        }

        if ($hesabeConfig['status'] === 'error') {
            $payment->delete();

            return response()->json(['error' => $hesabeConfig['message'], 500]);
        }

        $hesabeConfig = $hesabeConfig['data'];

        $apiKey = $hesabeConfig['api_key'];
        $baseUrl = $hesabeConfig['base_url'];
        $merchantCode = $hesabeConfig['merchant_code'];
        $encryptionKey = $hesabeConfig['secret_key'];
        $ivKey = $hesabeConfig['iv_key'];
        $accessCode = $hesabeConfig['access_code'];

        $orderReference = $request->input('invoice_number');
        $paymentMethodId = $request->input('payment_method_id');
        $customerName = $invoice->client->first_name ?? 'Customer';

        if (strpos($customerName, '/') !== false) {
            $customerName = trim(explode('/', $customerName)[0]);
        }

        $clientPhone = $data['client_phone'] ?? '50000000';

        if (isset($clientPhone) && strpos($clientPhone, '+') === 0) {
            $clientPhone = preg_replace('/^\+\d{1,3}/', '', $clientPhone);
            $clientPhone = ltrim($clientPhone, '0');
        }

        $companyId = $company->id;

        $company = $companyId ? Company::find($companyId) : null;
        $companyEmail = $company?->email ?? 'admin@citytravelers.co';

        $requestData = [
            'amount'        => $request->final_amount,
            'currency'      => 'KWD',
            'merchantCode' => $merchantCode,
            'paymentType' => '1',
            'orderReferenceNumber' => $orderReference,
            'name' => $request->client_name,
            'mobile_number' => $clientPhone,
            'email' => $companyEmail,
            /* 'saveCard' => 'boolean',
            'cardId' => 'required|string',
            'authorize' => 'boolean', */            
            'version' => '2.0',
            'responseUrl' => route('hesabe.response'),
            'failureUrl' => route('hesabe.failure'),
            /*'webhookUrl' => 'required|string',*/        
        ];

        $requestDataJson = json_encode($requestData);

        $encryptedData = HesabeCrypt::encrypt($requestDataJson, $encryptionKey, $ivKey);

        $hesabePayload = [
            'data' => $encryptedData,
        ];

        $checkoutResponse = Http::withHeaders([
            'accessCode' => $accessCode,
            'Accept' => 'application/json',
        ])->post("$baseUrl/checkout", $hesabePayload);
        
        Log::info();
        
        if (!$checkoutResponse->successful()) {
            return response()->json(['error' => 'CheckoutPayment failed.'], 500);
        }

        return $checkoutResponse->json();
    }
}