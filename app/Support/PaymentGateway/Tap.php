<?php

namespace App\Support\PaymentGateway;

use App\Http\Traits\HttpRequestTrait;
use App\Models\Payment;
use App\Services\GatewayConfigService;
use Illuminate\Http\Request;

class Tap
{
    use HttpRequestTrait;

    public function createCharge(Request $request)
    {
        $request->validate([
            'finalAmount' => 'required|numeric|min:1',
            'client_name' => 'required|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'invoice_id' => 'nullable|integer|exists:invoices,id',
            'invoice_number' => 'required|string|max:255',
            'payment_id' => 'required|integer|exists:payments,id',
            'payment_gateway' => 'required|string|max:255',
            'invoice_partial_id' => 'nullable|array',
        ]);

        $data = [
            'amount' => $request->input('finalAmount'),
            'currency' => 'KWD',
            'save_card' => false,
            'customer' => [
                'first_name' => $request->input('client_name'),
                'email' => $request->input('client_email') ?? 'link@citycommerce.group',
            ],
            'source' => [
                'id' => 'src_all',
            ],
            'description' => 'Payment for invoice: ' . $request->input('invoice_id'),
            'metadata' => [
                'invoice_number' => $request->input('invoice_number'),
                'payment_id' => $request->input('payment_id'),
                'payment_gateway' => $request->input('payment_gateway'),
                'invoice_partial_id' => json_encode($request->input('invoice_partial_id') ?? []),
            ],
            'redirect' => [
                'url' => route('payment.process'),
            ],
        ];

        if (config('app.env') == 'production') {
            $data['post'] = [
                'url' => route('payment.webhook'),
            ];
        }

        $configService = new GatewayConfigService();
        $tapConfigResponse = $configService->getTapConfig();

        $payment = Payment::find($request->input('payment_id'));

        if($tapConfigResponse['status'] === 'error') {

            $payment->delete();

            return [
                'status' => 'error',
                'message' => $tapConfigResponse['message'],
            ];
        }
        
        $tapConfig = $tapConfigResponse['data'];

        $response = $this->postRequest(
            $tapConfig['url'] . '/charges',
            array(
                'Authorization: Bearer ' . $tapConfig['secret'],
                'Content-Type: application/json'
            ),
            json_encode($data),

        );

        logger('Create Charge response',$response);

        return $response;
    }

    public function getCharge($chargeId)
    {
        $configService = new GatewayConfigService();
        $tapConfigResponse = $configService->getTapConfig();

        if($tapConfigResponse['status'] === 'error') {
            return [
                'status' => 'error',
                'message' => $tapConfigResponse['message'],
            ];
        }

        $tapConfig = $tapConfigResponse['data'];

        $response = $this->getRequest(
            $tapConfig['url'] . '/charges/' . $chargeId,
            array(
                'Authorization: Bearer ' . $tapConfig['secret'],
            ),
            [],
        );

        logger('Get Charge response', $response);

        return $response;
    }
    // public function createCharge($req)
    // {

    //     $response = $this->postRequest('/charges', json_encode($req));

    //     logger($response);

    //     return $response;
    // }

    // public function getCharge($chargeId)
    // {

    //     $response = $this->getRequest('/charges/' . $chargeId);

    //     logger($response);

    //     return $response;
    // }
}
