<?php

namespace App\Support\PaymentGateway;

use App\Http\Traits\HttpRequestTrait;

class Tap
{
    use HttpRequestTrait;

    public function createCharge($req)
    {
        $response = $this->postRequest(
            config('services.tap.url') . '/charges',
            array(
                'Authorization: Bearer ' . config('services.tap.secret'),
                'Content-Type: application/json'
            ),
            json_encode($req),

        );

        logger($response);

        return $response;
    }

    public function getCharge($chargeId)
    {
        $response = $this->getRequest(
            config('services.tap.url') . '/charges/' . $chargeId,
            array(
                'Authorization: Bearer ' . config('services.tap.secret'),
            ),
            [],
        );

        logger($response);

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
