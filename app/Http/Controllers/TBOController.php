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
        logger("TBO GET URL: " . $this->apiUrl . $url);

        return Http::withBasicAuth($this->username, $this->password)->get($this->apiUrl . $url);
    }

    public function tboPostAuthentication(string $url,array $data)
    {
        logger("TBO POST URL: " . $this->apiUrl . $url);

        logger("Data: ", $data);

        return Http::withBasicAuth($this->username, $this->password)->post($this->apiUrl . $url, $data);

    }

    public function searchIndex(Request $request)
    {
        $hotelList = [];
        $cityList = [];
        $hotelCode = '';
        $guestNationality = '';
        $countryList = $this->countryList();

        $countryCode = '';
        $cityCode = '';
        $hotelCode = '';

        if($request->query('countryCode'))
        {
            $countryCode = $request->query('countryCode');
            $cityListResponse = $this->cityList($countryCode);

            if($cityListResponse['Status']['Code'] !== 200){
                return Redirect::back()->with('error', $cityListResponse['Status']['Description']);
            }

            $cityList = $cityListResponse['CityList'];
        }

        if($request->query('cityCode'))
        {
            $cityCode = $request->query('cityCode');
            $hotelListResponse = $this->hotelCityList($cityCode);

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
            'countryCode',
            'cityCode',
            'hotelCode',
            'countryList',
            'cityList',
            'hotelList',
            'checkIn',
            'checkOut',
            'guestNationality'
        ));
    }

    public function search(Request $request)
    { 
        $request->validate([
            'checkInDate' => 'required|date',
            'checkOutDate' => 'required|date',
            'hotel' => 'required',
            'guestNationality' => 'required',
            'rooms' => 'array|required',
        ]);
        
        $url = '/Search';

        $data = [
            'CheckIn' => $request->checkInDate,
            'CheckOut' => $request->checkOutDate,
            'HotelCodes' => (integer)$request->hotel,
            'GuestNationality' => $request->guestNationality,
            'PaxRooms' => $request->rooms,
            'ResponseTime' => 23,
            'IsDetailedResponse' => false,
            'Filters' => [
                'Refundable' => false,
                'NoOfRooms' => 0,
                'MealType' => 'All', // All, WithMeal and RoomOnly
            ]
        ];

        $response = $this->tboPostAuthentication($url, $data);

        logger("Search Response: ", $response->json());

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

        $response = $this->tboPostAuthentication($url, $data);

        logger('Prebook Response: ', $response->json());

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

    public function preBookIndex()
    {
        $tboPreBooks = TBO::all();

        return view('suppliers.tbo.book.prebook-index', compact('tboPreBooks'));
    }

    public function preBookShow($tboId)
    {
        $tboPreBook = TBO::find($tboId);

        return view('suppliers.tbo.book.prebook-show', compact('tboPreBook'));
    }

    public function book(Request $request)
    {

        $request->validate([
            'tbo_id' => 'required',
            'booking_code' => 'required',
            // 'CustomerDetails' => 'array|required',
            // 'CustomerDetails.CustomerNames' => 'array|required',
            'first_name' => 'required',
            'last_name' => 'required',
            'title' => 'required',
            'type' => 'required',
            'client_reference_id' => 'required',
            'booking_reference_id' => 'required',
            'total_fare' => 'numeric|required',
            'email_id' => 'required',
            'phone_number' => 'required',
            'payment_mode' => 'required',
            // 'PaymentInfo' => 'array|required_if:PaymentMode,NewCard',
            'cvv' => 'required',
            'card_number' => 'required_if:payment_mode,NewCard',
            'expired_month' => 'required_if:payment_mode,NewCard',
            'expired_year' => 'required_if:payment_mode,NewCard',
            'card_first_name' => 'required_if:payment_mode,NewCard',
            'card_last_name' => 'required_if:payment_mode,NewCard',
            'billing_amount' => 'required_if:payment_mode,NewCard',
            'billing_currency' => 'required_if:payment_mode,NewCard',
            'address_line_1' => 'required_if:payment_mode,NewCard',
            'address_line_2' => 'required_if:payment_mode,NewCard',
            'card_city' => 'required_if:payment_mode,NewCard',
            'card_postal_code' => 'required_if:payment_mode,NewCard',
            'card_country_code' => 'required_if:payment_mode,NewCard'
        ]);

        $tboId = $request->tbo_id;
        $url = '/Book';

        $data = [
            'BookingCode' => $request->booking_code,
            'CustomerDetails' => [
                'CustomerName' => [
                    'FirstName' => $request->first_name,
                    'LastName' => $request->last_name,
                    'Title' => $request->title,
                    'Type' => $request->type
                ]
            ],
            'ClientReferenceId' => $request->client_reference_id,
            'BookingReferenceId' => $request->booking_reference_id,
            'TotalFare' => (float)$request->total_fare,
            'EmailId' => $request->email_id,
            'PhoneNumber' => $request->phone_number,
            'PaymentMode' => $request->payment_mode,
            // 'PaymentInfo' => $request->PaymentInfo,
            'PaymentInfo' => [
                'CvvNumber' => $request->cvv,
            ]
        ];

        if($request->PaymentMode === 'NewCard'){
            $data['PaymentInfo']['CardNumber'] = $request->card_number;
            $data['PaymentInfo']['CardExpirationMonth'] = $request->expired_month;
            $data['PaymentInfo']['CardExpirationYear'] = $request->expired_year;
            $data['PaymentInfo']['CardHolderFirstName'] = $request->card_first_name;
            $data['PaymentInfo']['CardHolderLastName'] = $request->card_last_name;
            $data['PaymentInfo']['BillingAmount'] = $request->billing_amount;
            $data['PaymentInfo']['BillingCurrency'] = $request->billing_currency;
            $data['PaymentInfo']['CardHolderAddress']['AddressLine1'] = $request->address_line_1;
            $data['PaymentInfo']['CardHolderAddress']['AddressLine2'] = $request->address_line_2;
            $data['PaymentInfo']['CardHolderAddress']['City'] = $request->card_city;
            $data['PaymentInfo']['CardHolderAddress']['PostalCode'] = $request->card_postal_code;
            $data['PaymentInfo']['CardHolderAddress']['CountryCode'] = $request->card_country_code;
        } 

        $response = $this->tboPostAuthentication($url, $data);

        logger('Booking Response: ', $response->json());

        if($response['Status']['Code'] !== 200){
            return Redirect::route('suppliers.tbo.prebook.show', ['tboId' => $tboId])->withErrors($response['Status']['Description']);
        }

        return Redirect::route('suppliers.tbo.index')->with('success', 'Booking successful');
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

        logger('Booking Detail Response: ', $response->json());

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

        logger('Booking Detail By Date Response: ', $response->json());

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

        $data = [
            'ConfirmationNumber' => $confirmationNo
        ];

        $response = $this->tboPostAuthentication($url, $data);

        logger('Cancel Response: ', $response->json());

        if($response['Status']['Code'] !== 200){
            return Redirect::back()->withErrors($response['Status']['Description']);
        }

        return Redirect::route('tbo.index')->with('success', 'Booking cancelled successfully');
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
