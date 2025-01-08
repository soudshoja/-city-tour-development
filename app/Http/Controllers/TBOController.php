<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use PhpParser\Node\Expr\Throw_;

class TBOController extends Controller
{
    private $apiUrl;
    private $username;
    private $password;

    public function __construct()
    {
        $this->apiUrl = config('services.tbo.url');
        $this->username = config('services.tbo.username');
        $this->password = config('services.tbo.password');
    }

    public function index(Request $request)
    {
        if(auth()->user()->role_id !== Role::COMPANY) {
            abort(403, 'Unauthorized action.');
        }

        $countries = $this->countryList();

        $currentPage = $request->query('page', 1);
        $perPage = 20;
        $offset = ($currentPage - 1) * $perPage;
        $currentPageData = array_slice($countries, $offset, $perPage);

        $countries = new LengthAwarePaginator(
            $currentPageData,
            count($countries),
            $perPage,
            $currentPage
        );

        $countries->setPath($request->url());

        $startDate = date('Y-m-d', strtotime('-100 days'));
        $endDate = date('Y-m-d');

        $data = new Request([
            'startDate' => $startDate,  
            'endDate' => $endDate
        ]);

        $pastBookings = $this->bookingDetail($data);
        return view('suppliers.tbo.index', compact('countries', 'pastBookings', 'startDate', 'endDate'));
    }

    public function tboGetAuthentication(string $url) 
    {
        return Http::withBasicAuth($this->username, $this->password)->get($this->apiUrl . $url);
    }

    public function tboPostAuthentication(string $url,array $data)
    {
        return Http::withBasicAuth($this->username, $this->password)->post($this->apiUrl . $url, $data);
    }

    public function search()
    {
        return 'hello';
    }
    
    public function prebook($parameter)
    {
    }

    public function bookingDetail(Request $request)
    {
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');
   
        $url = '/BookingDetailsbasedondate';
        
        $response = $this->tboPostAuthentication($url, [
            "FromDate" => $startDate,
            "ToDate" => $endDate
        ]);

        if($response['Status']['Code'] !== 200){
            
            return response()->json([
                'error' => $response['Status']['Description']
            ]);
        }

        return $response['BookingDetail'];
    }

    public function countryList()
    {
        $url = '/CountryList';
        $cacheKey = 'country_list';
        $cacheTime = 60 * 60; // Cache for 1 hour

        if (cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }

        $response = $this->tboGetAuthentication($url)['CountryList'];

        cache()->put($cacheKey, $response, $cacheTime);

        return $response;
    }

    public function cityList($countryCode)
    {
        $url = '/CityList';

        $data = [
            "CountryCode" => $countryCode
        ];

        $cities = $this->tboPostAuthentication($url, $data)['CityList'];

        return view('suppliers.tbo.city', compact('cities'));
    }

    public function hotelCityList($cityCode)
    {
        $url = '/TBOHotelCodeList';

        $data = [
            'CityCode' => $cityCode,
            'isDetailedResponse' => "false"
        ];

        $response = $this->tboPostAuthentication($url, $data);

        if($response['Status']['Code'] !== 200){
            return response()->json([
                'error' => $response['Status']['Description']
            ]);
        }

        return $response['Hotels'];
    }

    public function hotelCodeList()
    {
        $url = '/HotelCodeList';

        $response = $this->tboGetAuthentication($url);

        return $response;
    }

    public function hotelDetails($hotelCode)
    {
        $url = '/HotelDetails';

        $data = [
            'Hotelcodes' => $hotelCode,
            'Language' => "EN"
        ];

        $response = $this->tboPostAuthentication($url, $data);
        return $response;
    }

}
