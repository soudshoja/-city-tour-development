<?php

namespace App\Support\PaymentGateway;

use App\Http\Traits\HttpRequestTrait;

class MyFatoorah
{
    use HttpRequestTrait;

    public function createCharge($req)
    {
        $response = $this->postRequest(
            config('services.myfatoorah.base_url') . '/charges',
            array(
                'Authorization: Bearer ' . config('services.myfatoorah.api_key'),
                'Content-Type: application/json'
            ),
            json_encode($req),

        );

        logger('Create Charge response',$response);

        return $response;
    }

    public function getCharge($chargeId)
    {
        $response = $this->getRequest(
            config('services.myfatoorah.base_url') . '/charges/' . $chargeId,
            array(
                'Authorization: Bearer ' . config('services.myfatoorah.api_key'),
            ),
            [],
        );

        logger('
        
        ', $response);

        return $response;
    }

}
