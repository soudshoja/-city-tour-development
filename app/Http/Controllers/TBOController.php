<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Role;
use App\Models\TBO;
use Exception;
use Google\Protobuf\Field\Kind;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
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

        $pastBookings = $this->bookingDetailByDate($data);
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

    public function searchIndex(Request $request)
    {
        $hotelList = [];
        $cityList = [];
        $hotelCode = '';
        $guestNationality = '';
        $countryList = $this->countryList();

        if($request->query('countryCode'))
        {
            $cityListResponse = $this->cityList($request->query('countryCode'));

            if($cityListResponse['Status']['Code'] !== 200){
                return Redirect::back()->with('error', $cityListResponse['Status']['Description']);
            }

            $cityList = $cityListResponse['CityList'];
        }

        if($request->query('cityCode'))
        {
            $hotelListResponse = $this->hotelCityList($request->query('cityCode'));

            if($hotelListResponse['Status']['Code'] !== 200){
                $hotelList = [];
                return Redirect::back()->with('error', $hotelListResponse['Status']['Description']);
            }

            $hotelList = $hotelListResponse['Hotels'];
        }

        if($request->query('checkIn'))
        {
            $checkIn = $request->query('checkIn');
        } else {
            $checkIn = date('Y-m-d');
        }

        if($request->query('checkOut'))
        {
            $checkOut = $request->query('checkOut');
        } else {
            $checkOut = date('Y-m-d', strtotime('+1 day'));
        }

        if($request->query('hotelCode')) $hotelCode = $request->query('hotelCode');

        if($request->query('guestNationality')) $guestNationality = $request->query('guestNationality');

        return view('suppliers.tbo.search.index', compact(
            'countryList',
            'cityList',
            'hotelList',
            'checkIn',
            'checkOut',
            'hotelCode',
            'guestNationality'
        ));
    }

    public function search(Request $request)
    { 
        $request->validate([
            'checkInDate' => 'required|date',
            'checkOutDate' => 'required|date',
            'hotel' => 'required',
            'guestNationality' => 'required'
        ]);
        
        $url = '/Search';

        $data = [
            'CheckIn' => $request->checkInDate,
            'CheckOut' => $request->checkOutDate,
            'HotelCodes' => $request->hotel,
            'GuestNationality' => $request->guestNationality,
            'PaxRooms' => [
                [
                    'Adults' => 1,
                    'Children' => 0,
                    'ChildrenAges' => [],
                ]
            ],
            'ResponseTime' => 23,
            'IsDetailedResponse' => false,
            'Filters' => [
                'Refundable' => false,
                'NoOfRooms' => 0,
                'MealType' => 'All', // All, WithMeal and RoomOnly
            ]
        ];

        logger('SEARCH URL: ' . $url);
        logger('search request : ', $data);

        $response = $this->tboPostAuthentication($url, $data);

        logger('search response : ', $response->json());

        return $response->json();
    }
 
    public function preBookStore(Request $request)
    {
        $request->validate([
            'bookingCode' => 'required',
        ]);

        $url = '/PreBook';

        $data = [
            'BookingCode' => $request->bookingCode,
        ];

        logger('PREBOOK URL: ' . $url);
        logger('request : ', $data);
        $response = $this->tboPostAuthentication($url, $data);

        if($response['Status']['Code'] !== 200){
            return Redirect::back()->withErrors($response['Status']['Description']);
        }

        $hotelResult = $response['HotelResult'];

        foreach ($hotelResult as $hotel) {
            foreach ($hotel['Rooms'] as $rooms) {
                try {
                    $tboPreBook = TBO::create([
                        'booking_code' => $rooms['BookingCode'],
                        'hotel_code' => $hotel['HotelCode'],
                        'room_name' => json_encode($rooms['Name']),
                        'currency' => $hotel['Currency'],
                        'inclusion' => $rooms['Inclusion'],
                        'day_rates' => json_encode($rooms['DayRates']),
                        'total_fare' => $rooms['TotalFare'],
                        'total_tax' => $rooms['TotalTax'],
                        'extra_guest_charges' => $rooms['ExtraGuestCharges'] ?? '0',
                        'room_promotion' => json_encode($rooms['RoomPromotion']),
                        'cancel_policies' => json_encode($rooms['CancelPolicies']),
                        'meal_type' => $rooms['MealType'],
                        'is_refundable' => $rooms['IsRefundable'],
                        'with_transfer' => $rooms['WithTransfer'] ?? false,
                    ]);
                } catch (Exception $e) {
                    return Redirect::back()->with('error', $e->getMessage());
                }
            }
        }
        
        return $this->preBookShow($tboPreBook->id);
    }

    public function preBookShow($tboId)
    {
        $tboPreBook = TBO::find($tboId);

        return view('suppliers.tbo.book.prebook', compact('tboPreBook'));
    }

    public function book(Request $request)
    {
        $request->validate([
            'BookingCode' => 'required',
            'CustomerDetails' => 'array|required',
            'CustomerDetails.CustomerNames' => 'array|required',
            'CustomerDetails.CustomerNames.FirstName' => 'required',
            'CustomerDetails.CustomerNames.LastName' => 'required',
            'CustomerDetails.CustomerNames.Title' => 'required',
            'CustomerDetails.CustomerNames.Type' => 'required',
            'ClientReferenceId' => 'required',
            'BookingReferenceId' => 'required',
            'TotalFare' => 'numeric|required',
            'EmailId' => 'required',
            'PhoneNumber' => 'required',
            'PaymentMode' => 'required',
            'PaymentInfo' => 'array|required_if:PaymentMode,NewCard',
            'PaymentInfo.CvvNumber' => 'required',
            'PaymentInfo.CardNumber' => 'required_if:PaymentMode,NewCard',
            'PaymentInfo.CardExpirationMonth' => 'required_if:PaymentMode,NewCard',
            'PaymentInfo.CardExpirationYear' => 'required_if:PaymentMode,NewCard',
            'PaymentInfo.CardHolderFirstName' => 'required_if:PaymentMode,NewCard',
            'PaymentInfo.CardHolderLastName' => 'required_if:PaymentMode,NewCard',
            'PaymentInfo.BillingAmount' => 'required_if:PaymentMode,NewCard',
            'PaymentInfo.BillingCurrency' => 'required_if:PaymentMode,NewCard',
            'PaymentInfo.CardHolderAddress.AddressLine1' => 'required_if:PaymentMode,NewCard',
            'PaymentInfo.CardHolderAddress.AddressLine2' => 'required_if:PaymentMode,NewCard',
            'PaymentInfo.CardHolderAddress.City' => 'required_if:PaymentMode,NewCard',
            'PaymentInfo.CardHolderAddress.PostalCode' => 'required_if:PaymentMode,NewCard'
        ]);

        $url = '/Book';

        $data = [
            'BookingCode' => $request->BookingCode,
            'CustomerDetails' => $request->CustomerDetails,
            'ClientReferenceId' => $request->ClientReferenceId,
            'BookingReferenceId' => $request->BookingReferenceId,
            'TotalFare' => (float)$request->TotalFare,
            'EmailId' => $request->EmailId,
            'PhoneNumber' => $request->PhoneNumber,
            'PaymentMode' => $request->PaymentMode,
            'PaymentInfo' => $request->PaymentInfo, 
            'PaymentInfo' => [
                'CvvNumber' => $request->PaymentInfo['CvvNumber']
            ]
        ];

        if($request->PaymentMode === 'NewCard'){
            $data['PaymentInfo']['CardNumber'] = $request->PaymentInfo['CardNumber'];
            $data['PaymentInfo']['CardExpirationMonth'] = $request->PaymentInfo['CardExpirationMonth'];
            $data['PaymentInfo']['CardExpirationYear'] = $request->PaymentInfo['CardExpirationYear'];
            $data['PaymentInfo']['CardHolderFirstName'] = $request->PaymentInfo['CardHolderFirstName'];
            $data['PaymentInfo']['CardHolderLastName'] = $request->PaymentInfo['CardHolderLastName'];
            $data['PaymentInfo']['BillingAmount'] = $request->PaymentInfo['BillingAmount'];
            $data['PaymentInfo']['BillingCurrency'] = $request->PaymentInfo['BillingCurrency'];
            $data['PaymentInfo']['CardHolderAddress']['AddressLine1'] = $request->PaymentInfo['CardHolderAddress']['AddressLine1'];
            $data['PaymentInfo']['CardHolderAddress']['AddressLine2'] = $request->PaymentInfo['CardHolderAddress']['AddressLine2'];
            $data['PaymentInfo']['CardHolderAddress']['City'] = $request->PaymentInfo['CardHolderAddress']['City'];
            $data['PaymentInfo']['CardHolderAddress']['PostalCode'] = $request->PaymentInfo['CardHolderAddress']['PostalCode'];
        }

        logger('BOOK URL: ' . $url);
        logger('request : ', $data);

        $response = $this->tboPostAuthentication($url, $data);

        logger('response', $response->json());

        if($response['Status']['Code'] !== 200){
            return   Redirect::back()->withErrors($response['Status']['Description']);
        }

        return Redirect::back()->with('success', 'Booking successful');
    }

    public function bookingDetail(Request $request)
    {
        $data = [];

        if(isset($request->confirmationNumber))
        {
            $data['ConfirmationNumber'] = $request->confirmationNumber;
        } elseif(isset($request->BookingReferenceId)) {
            $data['BookingReferenceId'] = $request->BookingReferenceId;
        } else {
            return Redirect::back()->withErrors('Please provide a valid reference number');
        }

        $url = '/BookingDetail';
       
        $data['PaymentMethod'] = 'Limit';

        $response = $this->tboPostAuthentication($url, $data);

        if($response['Status']['Code'] !== 200){
            return [
                'error' => $response['Status']['Description']
            ];
        }

        return $response['BookingDetail'];
    }

    public function bookingDetailByDate(Request $request)
    {
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');
   
        $url = '/BookingDetailsbasedondate';
        
        $response = $this->tboPostAuthentication($url, [
            "FromDate" => $startDate,
            "ToDate" => $endDate
        ]);

        if($response['Status']['Code'] !== 200){
            
            return [
                'error' => $response['Status']['Description']
            ];
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

    private function cityList($countryCode)
    {
        $url = '/CityList';

        $data = [
            "CountryCode" => $countryCode
        ];

        $response = $this->tboPostAuthentication($url, $data);
        
        return $response->json();
    }

    public function cityListPage($countryCode)
    {
        $cities = $this->cityList($countryCode);

        if($cities['Status']['Code'] !== 200){
            return Redirect::back()->withErrors($cities['Status']['Description']);
        }

        $cities = $cities['CityList'];

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

        return $response->json();
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

        $response = $this->tboPostAuthentication($url, $data)['HotelDetails'];
        return $response;
    }

}
