<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TBOHolidayService
{
    private $apiUrl;
    private $username;
    private $password;
    private $logger;
    
    public function __construct(?string $url = null, ?string $username = null, ?string $password = null)
    {
        $this->apiUrl = $url ?? session('tbo.url') ?? config('services.tbo.url');
        $this->username = $username ?? session('tbo.username') ?? config('services.tbo.username');
        $this->password = $password ?? session('tbo.password') ?? config('services.tbo.password');
        $this->logger = Log::channel('tbo');
    }

    public function get(string $url)
    {
        $this->logger->info("TBO Get request", [
            'username' => $this->username,
            'password' => strlen($this->password) > 5 ? substr($this->password, 0, 2) . str_repeat('*', strlen($this->password) - 2) : $this->password,
            'url' => $this->apiUrl . $url,
        ]);

        $response = Http::withBasicAuth($this->username, $this->password)->get($this->apiUrl . $url);

        $this->logger->info("TBO Get Response", [
            'status' => $response->status(),
            'body' => $response->json()
        ]);
      
        return $response->json();
    }

    public function post(string $url, array $data)
    {
        $this->logger->info("TBO Post request", [
            'username' => $this->username,
            'password' => strlen($this->password) > 5 ? substr($this->password, 0, 2) . str_repeat('*', strlen($this->password) - 2) : $this->password,
            'url' => $this->apiUrl . $url,
            'data' => $data
        ]);

        $response = Http::withBasicAuth($this->username, $this->password)->post($this->apiUrl . $url, $data);

        $this->logger->info("TBO Post Response", [
            'status' => $response->status(),
            'body' => $response->json()
        ]);
        
        return $response->json();
    }

    public function search(array $data)
    {
        $url = '/Search';
        return $this->post($url, $data);
    }

    public function preBook(string $bookingCode)
    {
        $url = '/PreBook';
        $data = ['BookingCode' => $bookingCode];
        return $this->post($url, $data);
    }

    public function book(array $data)
    {
        $url = '/Book';
        return $this->post($url, $data);
    }

    public function getBookingDetail(array $data)
    {
        $url = '/BookingDetail';
        $data['PaymentMethod'] = 'Limit';
        return $this->post($url, $data);
    }

    public function getBookingDetailByDate(string $startDate, string $endDate)
    {
        $url = '/BookingDetailsbasedondate';
        
        $response = $this->post($url, [
            "FromDate" => $startDate,
            "ToDate" => $endDate
        ]);

        $this->logger->info('Booking Detail By Date Response: ', $response);

        if($response['Status']['Code'] !== 200){
            return [
                'error' => $response['Status']['Description']
            ];
        }

        return $response['BookingDetail'];
    }

    public function cancel(string $confirmationNo)
    {
        $url = '/Cancel';
        $data = ['ConfirmationNumber' => $confirmationNo];
        return $this->post($url, $data);
    }

    public function getCountryList()
    {
        $url = '/CountryList';
        $cacheKey = 'tbo_country_list';
        $cacheTime = 60 * 60 * 24;

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $response = $this->get($url);

        if($response['Status']['Code'] == 200){
            $response = $response['CountryList'];
            Cache::put($cacheKey, $response, $cacheTime);
        }

        return $response;
    }

    public function getCityList(string $countryCode)
    {
        $url = '/CityList';
        $data = ["CountryCode" => $countryCode];
        return $this->post($url, $data);
    }

    public function getHotelCityList(string $cityCode)
    {
        $url = '/TBOHotelCodeList';
        $data = [
            'CityCode' => $cityCode,
            'isDetailedResponse' => "false"
        ];

        /* hotelCodeList only returns an array of hotel codes
           no status code is returned

           example: 
           
           'HotelCodes' => [
                '100001',
                '100002',
                '100003',
           ]

           while all api calls return a status code

           example:

              'Status' => [
                 'Code' => 200,
                 'Description' => 'Success'
              ]
              'RelatedData' => Data[]

            due to this, the response is not consistent with other api calls

            Please be aware of this when using this function
    */

        return $this->post($url, $data);
    }

    public function getHotelCodeList()
    {
        $url = '/HotelCodeList';
        $cacheKey = 'tbo_hotel_code_list';
        $cacheTime = 60 * 60 * 24;

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $response = $this->get($url);

        if (isset($response['HotelCodes']) && count($response['HotelCodes']) > 0) {
            $response = $response['HotelCodes'];
            Cache::put($cacheKey, $response, $cacheTime);
        }

        return $response;
    }

    public function getHotelDetails(int $hotelCode, string $language = "EN")
    {
        $url = '/HotelDetails';
        $data = [
            'Hotelcodes' => $hotelCode,
            'Language' => $language
        ];
        
        $response = $this->post($url, $data);
        return $response['HotelDetails'];
    }
}
