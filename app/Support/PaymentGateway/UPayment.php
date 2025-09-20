<?php

namespace App\Support\PaymentGateway;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\GatewayConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UPayment
{
    protected $configService;

    public function __construct()
    {
        // Initialize with your UPayment configuration
        $this->configService = new GatewayConfigService;
    }

    public function makeCharge(Request $request)
    {
        $request->validate([
            'final_amount' => 'required|numeric|min:1',
            'client_id' => 'required|integer|exists:clients,id',
            'client_name' => 'required|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'client_phone' => 'nullable|string|max:20',
            'company_email' => 'required|email|max:255',
            'invoice_id' => 'nullable|integer|exists:invoices,id',
            'invoice_number' => 'nullable|string|max:255',
            'payment_id' => 'required|integer|exists:payments,id',
            'payment_number' => 'required|string|max:255',
            'payment_method_id' => 'required|integer|exists:payment_methods,id',
            'invoice_partial_id' => 'nullable|array',
            'currency' => 'required|string|max:10',
        ]);

        $uPaymentConfig = $this->configService->getUPaymentConfig();

        if ($uPaymentConfig['status'] === 'error') {
            $payment = Payment::find($request->input('payment_id'));
            if ($payment) {
                $payment->delete();
            }

            return [
                'status' => 'error',
                'message' => $uPaymentConfig['message'],
            ];
        }

        $uPaymentConfig = $uPaymentConfig['data'];

        $uPaymentApiKey = $uPaymentConfig['api_key'];
        $uPaymentBaseUrl = rtrim($uPaymentConfig['base_url'], '/');

        $paymentGateway = 'knet'; //Default to knet
        $paymentMethod = PaymentMethod::find($request->input('payment_method_id'));

        if($paymentMethod) $paymentGateway = $paymentMethod->code ?? 'knet';

        $orderId = $request->input('invoice_id') ?? $request->input('payment_id');
        $orderReference = $request->input('invoice_number') ?? $request->input('payment_number');
        $requestData = [
            // 'products' => [
            //     [
            //         'name' => 'Sample Product',
            //         'description' => 'Sample product description',
            //         'price' => 10.50,
            //         'quantity' => 1,
            //     ],
            // ],
            'order' => [
                'id' => (string) $orderId,
                'reference' => $orderReference,
                'description' => 'Payment for invoice: ' . $orderReference,
                'currency' => $request->input('currency', 'KWD'),
                'amount' => $request->input('final_amount'),
            ],
            'paymentGateway' => [
                'src' => $paymentGateway,
            ],
            'language' => 'en',
            'tokens' => [
                'customerUniqueToken' => null,
            ],
            'reference' => [
                'id' => (string) $request->input('payment_id') . '-' . $request->input('payment_number'),
            ],
            'customer' => [
                'uniqueId' => (string) $request->client_id,
                'name' => $request->input('client_name'),
                'email' => $request->input('client_email') ?? $request->input('company_email'),
                'mobile' => $request->input('client_phone'),
            ],
            // 'customerExtraData' => 'Extra data here',
            // 'extraMerchantData' => [
            //     'amount' => 10.50,
            //     'knetCharge' => 0.50,
            //     'knetChargeType' => 'fixed',
            //     'ccCharge' => 0.75,
            //     'ccChargeType' => 'fixed',
            //     'ibanNumber' => 'KW00CBKU000000000000000000000',
            // ],
            'returnUrl' => route('payment.uPayment.callback'),
            'cancelUrl' => route('payment.uPayment.error'),
            'notificationUrl' => route('payment.uPayment.notifications'),
            'plugin' => [
                // 'src' => 'woocommerce',
            ],
            // 'paymentLinkExpiryInMinutes' => 60,
        ];

        Log::info('UPayment Charge Request', ['request' => $requestData]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $uPaymentApiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post( $uPaymentBaseUrl . '/charge', $requestData);

        Log::info('UPayment Charge Response', ['response' => $response->json()]);

       return $response->json();
    }

    public function getPaymentStatus($trackId)
    {

        $uPaymentConfig = $this->configService->getUPaymentConfig();

        if ($uPaymentConfig['status'] === 'error') {
            return [
                'status' => 'error',
                'message' => $uPaymentConfig['message'],
            ];
        }

        $uPaymentConfig = $uPaymentConfig['data'];

        $uPaymentApiKey = $uPaymentConfig['api_key'];
        $uPaymentBaseUrl = rtrim($uPaymentConfig['base_url'], '/');

        $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $uPaymentApiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get( $uPaymentBaseUrl . '/get-payment-status/' . $trackId);


        return $response->json();
    }

}