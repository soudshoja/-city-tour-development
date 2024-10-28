<?php

namespace App\Support\PaymentGateway;

class Tap
{

    public function getRequest($url, $params = [])
    {

        $curl = curl_init();


        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => config('services.tap.url') . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CAINFO => base_path('cacert.pem'),
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . config('services.tap.secret'),
            ),
        ));

        $response = curl_exec($curl);

        return json_decode($response, true);
    }

    public function postRequest($url, $data)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => config('services.tap.url') . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CAINFO => base_path('cacert.pem'),
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . config('services.tap.secret'),
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        return json_decode($response, true);
    }

    public function createCharge($req)
    {

        $response = $this->postRequest('/charges', json_encode($req));

        logger($response);

        return $response;
    }

    public function getCharge($chargeId)
    {

        $response = $this->getRequest('/charges/' . $chargeId);

        logger($response);

        return $response;
    }
}
