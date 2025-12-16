<?php

namespace App\Support\PaymentGateway;

use App\Http\Traits\HttpRequestTrait;
use App\Models\Payment;
use App\Models\Company;
use App\Models\Agent;
use App\Models\Accountant;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Role;
use App\Services\GatewayConfigService;
use App\Services\HesabeCrypt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class Hesabe 
{
    use HttpRequestTrait;

    public function createCharge(Request $request) : array
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
            Log::error('[HESABE] Company not found for payment', ['payment_id' => $payment->id]);
            return [
                'success' => false,
                'message' => 'Company not found for the agent.',
            ];
        }

        if ($hesabeConfig['status'] === 'error') {
            $payment->delete();

            return [
                'success' => false,
                'message' => $hesabeConfig['message'],
            ];
        }

        $hesabeConfig = $hesabeConfig['data'];

        $apiKey = $hesabeConfig['api_key'];
        $baseUrl = $hesabeConfig['base_url'];
        $merchantCode = $hesabeConfig['merchant_code'];
        $ivKey = $hesabeConfig['iv_key'];
        $accessCode = $hesabeConfig['access_code'];

        $orderReference = $request->input('invoice_number');
        $paymentMethodId = $request->input('payment_method_id');

        $paymentMethod = PaymentMethod::find($paymentMethodId);

        $myfatoorahId = $paymentMethod ? $paymentMethod->myfatoorah_id : null;


        if(!$myfatoorahId){
            Log::error('[HESABE] Payment method MyFatoorah ID not found', ['payment_method_id' => $paymentMethodId]);
            return [
                'success' => false,
                'message' => 'Payment method configuration is missing. Please contact support.',
            ];
        }


        $clientPhone = $request->input('client_phone') ?? '50000000';

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
            'paymentType' => $myfatoorahId,
            'orderReferenceNumber' => $orderReference,
            'name' => $request->client_name,
            'mobile_number' => $clientPhone,
            'email' => $companyEmail,
            /* 'saveCard' => 'boolean',
            'cardId' => 'required|string',
            'authorize' => 'boolean', */            
            'version' => '2.0',
            'responseUrl' => route('payment.hesabe.response'),
            'failureUrl' => route('payment.hesabe.failure'),
            'webhookUrl' => route('payment.hesabe.webhook'),
        ];

        $requestDataJson = json_encode($requestData);


        $encryptedData = HesabeCrypt::encrypt($requestDataJson, $apiKey, $ivKey);

        $hesabePayload = [
            'data' => $encryptedData,
        ];

        Log::info('[HESABE] CheckoutPayment payload', ['payload' => $hesabePayload]);

        $checkoutResponse = Http::withHeaders([
            'accessCode' => $accessCode,
            'Accept' => 'application/json',
        ])->post("$baseUrl/checkout", $hesabePayload);
        
        Log::info('[HESABE] CheckoutPayment response', ['response' => $checkoutResponse->body()]);
        
        if (!$checkoutResponse->successful()) {
            return [
                'success' => false,
                'message' => 'HTTP ' . $checkoutResponse->status(),
                'data'    => [
                    'body'    => $checkoutResponse->body(),
                ],
            ];
        }
        $response = $checkoutResponse->body();

        $decryptedData = HesabeCrypt::decrypt($response , $apiKey, $ivKey);

        Log::info('[HESABE] Decrypted CheckoutPayment response', ['decrypted_response' => $decryptedData]);

        if (!$decryptedData) {
            return [
                'success' => false,
                'message' => 'Failed to decrypt Hesabe response.',
            ];
        }

        $responseData = json_decode($decryptedData, true);

        $url = $baseUrl . '/payment?data=' . $responseData['response']['data'];

        return  [
            'success' => true,
            'payment_url' => $url,
            'order_reference' => $orderReference,
        ];
    }
}