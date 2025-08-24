<?php

namespace App\Support\PaymentGateway;

use App\Http\Traits\HttpRequestTrait;
use App\Services\GatewayConfigService;

class Tap
{
    use HttpRequestTrait;

    public function createCharge($req)
    {
        $configService = new GatewayConfigService();
        $tapConfig = $configService->getTapConfig();

        $response = $this->postRequest(
            $tapConfig['url'] . '/charges',
            array(
                'Authorization: Bearer ' . $tapConfig['secret'],
                'Content-Type: application/json'
            ),
            json_encode($req),

        );

        logger('Create Charge response',$response);

        return $response;
    }

    public function getCharge($chargeId)
    {
        $configService = new GatewayConfigService();
        $tapConfig = $configService->getTapConfig();

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
