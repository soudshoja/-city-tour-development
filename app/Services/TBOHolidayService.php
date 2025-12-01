<?php

namespace App\Services;

use App\Models\MapCity;
use App\Models\MapHotel;
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
            'password' => strlen($this->password) > 4 ? substr($this->password, 0, 2) . str_repeat('*', strlen($this->password) - 4) . substr($this->password, -2) : $this->password,
            'url' => $this->apiUrl . $url,
        ]);

        $response = Http::withBasicAuth($this->username, $this->password)
            ->timeout(120)
            ->get($this->apiUrl . $url);

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
            'password' => strlen($this->password) > 4 ? substr($this->password, 0, 2) . str_repeat('*', strlen($this->password) - 4) . substr($this->password, -2) : $this->password,
            'url' => $this->apiUrl . $url,
            'data' => $data
        ]);

        $response = Http::withBasicAuth($this->username, $this->password)
            ->timeout(120)
            ->post($this->apiUrl . $url, $data);

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

        return $response;
    }

    /**
     * Get hotels by city code from TBO API
     * 
     * @param string $cityCode TBO city code
     * @param bool $isDetailedResponse Get detailed hotel information
     * @return array List of hotels with codes and names
     */
    public function getHotelsByCity(string $cityCode, bool $isDetailedResponse = false): array
    {
        $url = '/TBOHotelCodeList';
        $cacheKey = "tbo_hotels_city_{$cityCode}_" . ($isDetailedResponse ? 'detailed' : 'simple');
        $cacheTime = 60 * 60 * 24; // 24 hours

        // Check cache first
        if (Cache::has($cacheKey)) {
            $this->logger->info('Returning hotels from cache', [
                'city_code' => $cityCode,
                'cache_key' => $cacheKey
            ]);
            return Cache::get($cacheKey);
        }

        $data = [
            'CityCode' => $cityCode,
            'IsDetailedResponse' => $isDetailedResponse
        ];

        $this->logger->info('Fetching hotels by city from TBO', [
            'city_code' => $cityCode,
            'is_detailed' => $isDetailedResponse
        ]);

        $response = $this->post($url, $data);

        // TBO returns Hotels array with detailed response
        $hotels = $response['Hotels'] ?? [];

        if (!empty($hotels)) {
            Cache::put($cacheKey, $hotels, $cacheTime);
            $this->logger->info('Cached hotels for city', [
                'city_code' => $cityCode,
                'hotel_count' => count($hotels)
            ]);
        }

        return $hotels;
    }

    /**
     * Get TBO city code by city name
     * Uses local database to find country, then fetches city list from TBO
     * 
     * @param string $cityName City name to search
     * @return string|null City code if found
     */
    public function getCityCodeByName(string $cityName): ?string
    {
        $cityName = trim($cityName);
        
        // Step 1: Find city in local database
        $mapCity = MapCity::where('name', 'like', '%' . $cityName . '%')->first();

        if (!$mapCity) {
            $this->logger->warning('City not found in local DB', [
                'city_name' => $cityName
            ]);
            return null;
        }

        // Step 2: Get country from relationship
        $country = $mapCity->country;

        if (!$country) {
            $this->logger->warning('Country not found for city in local DB', [
                'city_name' => $cityName,
                'city_id' => $mapCity->id
            ]);
            return null;
        }

        $countryIso = $country->iso;

        // Step 3: Get city list from TBO for that country (with caching)
        $cacheKey = "tbo_city_list_{$countryIso}";
        $cacheTime = 60 * 60 * 24 * 7; // 7 days

        // Check cache first
        if (Cache::has($cacheKey)) {
            $this->logger->info('Returning city list from cache', [
                'country_iso' => $countryIso,
                'cache_key' => $cacheKey
            ]);
            $cityList = Cache::get($cacheKey);
        } else {
            // Fetch from TBO API
            $this->logger->info('Fetching city list from TBO', [
                'country_iso' => $countryIso
            ]);

            $cityListResponse = $this->getCityList($countryIso);

            if (!isset($cityListResponse['Status']) || $cityListResponse['Status']['Code'] != 200) {
                $this->logger->warning('Failed to get city list from TBO', [
                    'country_iso' => $countryIso,
                    'response' => $cityListResponse
                ]);
                return null;
            }

            $cityList = $cityListResponse['CityList'] ?? [];

            // Cache the city list
            if (!empty($cityList)) {
                Cache::put($cacheKey, $cityList, $cacheTime);
                $this->logger->info('Cached city list for country', [
                    'country_iso' => $countryIso,
                    'city_count' => count($cityList)
                ]);
            }
        }

        // Step 4: Search for matching city in TBO list (case-insensitive)
        
        // Try exact match first
        foreach ($cityList as $city) {
            if (strcasecmp($city['Name'], $cityName) === 0) {
                $this->logger->info('Found exact city match in TBO', [
                    'search_name' => $cityName,
                    'found_name' => $city['Name'],
                    'city_code' => $city['Code'],
                    'country' => $countryIso
                ]);
                return $city['Code'];
            }
        }

        // Try partial match (contains)
        foreach ($cityList as $city) {
            if (stripos($city['Name'], $cityName) !== false) {
                $this->logger->info('Found partial city match in TBO', [
                    'search_name' => $cityName,
                    'found_name' => $city['Name'],
                    'city_code' => $city['Code'],
                    'country' => $countryIso
                ]);
                return $city['Code'];
            }
        }

        // City found in local DB but not in TBO
        $this->logger->warning('City found in local DB but not in TBO', [
            'search_name' => $cityName,
            'country_iso' => $countryIso,
            'tbo_city_count' => count($cityList)
        ]);

        return null;
    }

    /**
     * Find TBO hotel code by hotel name using TBO API
     * 
     * @param string $hotelName Hotel name to search for
     * @param string|null $cityName Required city name to search hotels
     * @return array Response with status and data
     */
    public function findHotelCodeByName(?string $hotelName = null, string $cityName): array
    {
        // Step 1: Get TBO city code from city name
        $cityCode = $this->getCityCodeByName($cityName);
        
        if (!$cityCode) {
            $this->logger->warning('City not found in TBO', [
                'city_name' => $cityName
            ]);
            
            return [
                'success' => false,
                'status' => 'city_not_found',
                'message' => "City not found: {$cityName}",
                'data' => null
            ];
        }

        // Step 2: Get all hotels in that city from TBO
        $hotels = $this->getHotelsByCity($cityCode, false);
        
        if (empty($hotels)) {
            $this->logger->warning('No hotels found in city', [
                'city_name' => $cityName,
                'city_code' => $cityCode
            ]);
            
            return [
                'success' => false,
                'status' => 'no_hotels_in_city',
                'message' => "No hotels found in {$cityName}",
                'data' => null
            ];
        }

        if(empty($hotelName)){
            // If no hotel name provided, return all hotels in city
            $hotelList = [];
            foreach ($hotels as $hotel) {
                $hotelList[] = [
                    'id' => (int)$hotel['HotelCode'],
                    'name' => $hotel['HotelName'],
                    'address' => $hotel['Address'] ?? null,
                    'rating' => $hotel['HotelRating'] ?? null,
                    'city_name' => $cityName
                ];
            }

            return [
                'success' => true,
                'status' => 'multiple_hotels_found',
                'message' => "Hotel list for {$cityName}",
                'data' => $hotelList
            ];
        }

        $hotelName = trim($hotelName);
        
        // Try exact match first
        $exactMatch = null;
        foreach ($hotels as $hotel) {
            if (strcasecmp($hotel['HotelName'], $hotelName) === 0) {
                $exactMatch = $hotel;
                break;
            }
        }

        if ($exactMatch) {
            $this->logger->info('Found exact hotel match from TBO', [
                'search_name' => $hotelName,
                'found_name' => $exactMatch['HotelName'],
                'hotel_code' => $exactMatch['HotelCode'],
                'city' => $cityName
            ]);
            
            return [
                'success' => true,
                'status' => 'hotel_found',
                'message' => 'Hotel found',
                'data' => (int)$exactMatch['HotelCode']
            ];
        }

        // Try partial match (contains)
        $partialMatches = [];
        
        foreach ($hotels as $hotel) {
            if (stripos($hotel['HotelName'], $hotelName) !== false) {
                $partialMatches[] = [
                    'id' => (int)$hotel['HotelCode'],
                    'name' => $hotel['HotelName'],
                    'address' => $hotel['Address'] ?? null,
                    'rating' => $hotel['HotelRating'] ?? null,
                    'city_name' => $cityName
                ];
            }
        }

        // If multiple matches found
        if (count($partialMatches) > 1) {
            $this->logger->info('Multiple hotel matches found from TBO', [
                'search_name' => $hotelName,
                'city' => $cityName,
                'match_count' => count($partialMatches)
            ]);
            
            return [
                'success' => false,
                'status' => 'multiple_hotels_found',
                'message' => 'Multiple hotels found, please specify your hotel from the list',
                'data' => $partialMatches
            ];
        }

        // If exactly one partial match found
        if (count($partialMatches) === 1) {
            $this->logger->info('Found single partial hotel match from TBO', [
                'search_name' => $hotelName,
                'found_name' => $partialMatches[0]['name'],
                'hotel_code' => $partialMatches[0]['id'],
                'city' => $cityName
            ]);
            
            return [
                'success' => true,
                'status' => 'hotel_found',
                'message' => 'Hotel found',
                'data' => $partialMatches[0]['id']
            ];
        }

        // No matches found
        $this->logger->warning('Hotel not found in TBO', [
            'search_name' => $hotelName,
            'city' => $cityName,
            'city_code' => $cityCode,
            'total_hotels_in_city' => count($hotels)
        ]);

        return [
            'success' => false,
            'status' => 'hotel_not_found',
            'message' => "Hotel '{$hotelName}' not found in {$cityName}",
            'data' => null
        ];
        // // Try partial match (contains) - case-insensitive
        // $partialMatch = collect($hotelCodeList)->first(function ($hotel) use ($hotelName, $cityName) {
        //     $nameContains = stripos($hotel['HotelName'], $hotelName) !== false;
            
        //     if ($cityName) {
        //         return $nameContains && strcasecmp($hotel['CityName'], $cityName) === 0;
        //     }
            
        //     return $nameContains;
        // });
        
        // if ($partialMatch) {
        //     $this->logger->info('Found partial hotel match', [
        //         'search_name' => $hotelName,
        //         'found_name' => $partialMatch['HotelName'],
        //         'hotel_code' => $partialMatch['HotelCode'],
        //         'city' => $partialMatch['CityName']
        //     ]);
        //     return (int)$partialMatch['HotelCode'];
        // }
        
        // $this->logger->warning('Hotel not found', [
        //     'search_name' => $hotelName,
        //     'city' => $cityName
        // ]);
        
        // return null;
    }
}
