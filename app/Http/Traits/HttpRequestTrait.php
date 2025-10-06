<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Log;

trait HttpRequestTrait
{
    public function getRequest($url, $header = [], $params = [])
    {
        $curl = curl_init();

        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CAINFO => base_path('cacert.pem'),
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => $header,
        ));

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            Log::error('Curl error: ' . curl_error($curl));
        }

        curl_close($curl);

        return json_decode($response, true);
    }

    public function postRequest($url, $header, $data)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CAINFO => base_path('cacert.pem'),
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $header,
        ));

        $response = curl_exec($curl);
        logger(json_encode($curl));

        if (curl_errno($curl)) {
            Log::error('Curl error: ' . curl_error($curl));
        }

        curl_close($curl);

        return json_decode($response, true);
    }

    public function putRequest($url, $header, $data)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CAINFO => base_path('cacert.pem'),
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $header,
        ));

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            Log::error('Curl error: ' . curl_error($curl));
        }

        curl_close($curl);

        return json_decode($response, true);
    }

    public function deleteRequest($url, $header)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CAINFO => base_path('cacert.pem'),
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => $header,
        ));

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            Log::error('Curl error: ' . curl_error($curl));
        }

        curl_close($curl);

        return json_decode($response, true);
    }
}
