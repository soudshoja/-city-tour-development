<?php

namespace App\Support\PaymentGateway;

use App\Http\Traits\HttpRequestTrait;
use App\Services\GatewayConfigService;

class MyFatoorah
{
    use HttpRequestTrait;

    public function createCharge($req)
    {
        $configService = new GatewayConfigService();
        $myfatoorahConfig = $configService->getMyFatoorahConfig();

        $response = $this->postRequest(
            $myfatoorahConfig['base_url'] . '/charges',
            array(
                'Authorization: Bearer ' . $myfatoorahConfig['api_key'],
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
        $myfatoorahConfig = $configService->getMyFatoorahConfig();
        
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
