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
            $payment->delete();

            return response()->json(['error' => $myfatoorahConfig['message']], 500);
        }

        $myfatoorahConfig = $myfatoorahConfig['data'];

        $apiKey  = $myfatoorahConfig['api_key'];
        $baseUrl = $myfatoorahConfig['base_url'];
        
        $invoiceNumber = $request->input('invoice_number');

        $paymentMethodId = $request->input('payment_method_id');

        $paymentMethod = PaymentMethod::findOrFail($paymentMethodId);

        $customerName = $invoice->client->full_name ?? 'Customer';

        if (strpos($customerName, '/') !== false) {
            $customerName = trim(explode('/', $customerName)[0]);
        }

        $clientPhone = $data['client_phone'] ?? '50000000';

        if (isset($clientPhone) && strpos($clientPhone, '+') === 0) {
            $clientPhone = preg_replace('/^\+\d{1,3}/', '', $clientPhone);
            $clientPhone = ltrim($clientPhone, '0');
        }

        $userDefinedField = json_encode([
            'invoice_id'          => $request->input('invoice_id'),
            'invoice_partial_id' => $request->input('invoice_partial_id'),
        ]);

        $companyId = $company->id;

        $company = $companyId ? Company::find($companyId) : null;
        $companyEmail = $company?->email ?? 'admin@citytravelers.co';

        $executePayload = [
            "PaymentMethodId"     => $paymentMethod->myfatoorah_id,
            "InvoiceValue"        => $request->input('final_amount'),
            "CustomerName"        => $customerName,
            "CustomerEmail"       => $companyEmail,
            "MobileCountryCode"   => $client->country_code ?? '+965',
            "CustomerMobile"      => $clientPhone,
            "DisplayCurrencyIso"  => "KWD",
            "CallBackUrl"         => route('payments.callback'),
            "ErrorUrl"            => route('payments.error', ['invoice_id' => $request->input('invoice_id')]),
            "Language"            => "en",
            "CustomerReference"   => $invoiceNumber,
            "UserDefinedField"    => $userDefinedField,
            "InvoiceItems" => [
                [
                    "ItemName"   => "Invoice " . $invoiceNumber,
                    "Quantity"   => 1,
                    "UnitPrice"  => $request->input('final_amount'),
                ]
            ],
        ];

        $executeResponse = Http::withHeaders([
            'Authorization' => "Bearer $apiKey",
            'Content-Type' => 'application/json',
        ])->post("$baseUrl/ExecutePayment", $executePayload);

        Log::info('MyFatoorah: ExecutePayment response', ['response' => $executeResponse->json()]);

        if (!$executeResponse->successful()) {
            return response()->json(['error' => 'ExecutePayment failed.'], 500);
        }

        return $executeResponse->json();
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
