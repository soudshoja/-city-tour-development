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
use RuntimeException;

class Hesabe 
{
    use HttpRequestTrait;

    protected $apiKey;
    protected $baseUrl;
    protected $merchantCode;
    protected $ivKey;
    protected $accessCode;

    public function __construct()
    {
        $configService = new GatewayConfigService();
        $hesabeConfig = $configService->getHesabeConfig();

        if ($hesabeConfig['status'] === 'error') {
            Log::error('[HESABE] Configuration error', [
                'message' => $hesabeConfig['message']
            ]);
            throw new RuntimeException('Hesabe configuration is missing or inactive');
        }

        $hesabeConfig = $hesabeConfig['data'];

        $this->apiKey  = $hesabeConfig['api_key'];
        $this->baseUrl = $hesabeConfig['base_url'];
        $this->merchantCode = $hesabeConfig['merchant_code'];
        $this->ivKey = $hesabeConfig['iv_key'];
        $this->accessCode = $hesabeConfig['access_code'];
    }

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
            'type' => 'required|in:invoice,topup',
            // 'payment_transaction_id' => 'nullable|string|max:255',
        ]);
         
        $auth = Auth::user();
        $payment = Payment::find($request->input('payment_id'));

        $company = $payment->agent->branch->company;

        if(!$company){
            Log::error('[HESABE] Company not found for payment', ['payment_id' => $payment->id]);
            return [
                'success' => false,
                'message' => 'Company not found for the agent.',
            ];
        }

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
            'merchantCode' => $this->merchantCode,
            'paymentType' => $myfatoorahId,
            'orderReferenceNumber' => $orderReference,
            'name' => $request->client_name,
            'mobile_number' => $clientPhone,
            'email' => $companyEmail,
            /* 'saveCard' => 'boolean',
            'cardId' => 'required|string',
            'authorize' => 'boolean', */            
            'variable1' => $request->type,
            'version' => '2.0',
            'responseUrl' => route('payment.hesabe.response'),
            'failureUrl' => route('payment.hesabe.failure'),
            'webhookUrl' => route('payment.hesabe.webhook'),
        ];

        if($request->type === 'invoice'){
            $requestData['variable2'] = (string) $request->invoice_partial_id;
        }

        // if($request->has('payment_transaction_id')){
        //     $requestData['variable3'] = $request->payment_transaction_id;
        // }

        Log::info('[HESABE] CheckoutPayment request data', [
            'data' => $requestData,
            'api_key' => $this->apiKey,
            'iv_key' => $this->ivKey,
            'access_code' => $this->accessCode,
        ]);


        $requestDataJson = json_encode($requestData);

        $encryptedData = HesabeCrypt::encrypt($requestDataJson, $this->apiKey, $this->ivKey);

        $hesabePayload = [
            'data' => $encryptedData,
        ];

        Log::info('[HESABE] CheckoutPayment payload', [
            'url' => $this->baseUrl . '/checkout',
            'payload' => $hesabePayload
        ]);

        $checkoutResponse = Http::withHeaders([
            'accessCode' => $this->accessCode,
            'Accept' => 'application/json',
        ])->timeout(60)
        ->post("$this->baseUrl/checkout", $hesabePayload);
        
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

        $decryptedData = HesabeCrypt::decrypt($response , $this->apiKey, $this->ivKey);

        Log::info('[HESABE] Decrypted CheckoutPayment response', ['decrypted_response' => $decryptedData]);

        if (!$decryptedData) {
            return [
                'success' => false,
                'message' => 'Failed to decrypt Hesabe response.',
            ];
        }

        $responseData = json_decode($decryptedData, true);

        $url = $this->baseUrl . '/payment?data=' . $responseData['response']['data'];

        return  [
            'success' => true,
            'payment_url' => $url,
            'order_reference' => $orderReference,
            'token' => $responseData['token'],
        ];
    }

    public function getPaymentStatus(string $token)
    {
        Log::info('[HESABE] GetPaymentStatus request', ['token' => $token]);

        $response = Http::withHeaders([
            'accessCode' => $this->accessCode,
            'Accept' => 'application/json',
        ])->get("$this->baseUrl/api/transaction/$token");

        Log::info('[HESABE] GetPaymentStatus response', ['response' => $response->json()]);
    
        return $response;
    }
}