<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\TBO;
use App\Models\TBORoom;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class TBOController extends Controller
{
    private $apiUrl;
    private $username;
    private $password;
    
    public function __construct()
    {
        $this->apiUrl = session('tbo.url') ?? config('services.tbo.url');
        $this->username = session('tbo.username') ?? config('services.tbo.username');
        $this->password = session('tbo.password') ?? config('services.tbo.password');
    }

    public function destroyTBOSession()
    {
        session()->forget('tbo');

        return Redirect::route('suppliers.tbo.index')->with('success', 'credentials reset');
    }

    public function setCredentials(Request $request)
    {
        $request->validate([
            'url' => 'required',
            'username' => 'required',
            'password' => 'required'
        ]);

        session([
            'tbo' => [
                'url' => $request->url,
                'username' => $request->username,
                'password' => $request->password
            ]
        ]);

        $this->apiUrl = $request->url;
        $this->username = $request->username;
        $this->password = $request->password;

        return Redirect::route('suppliers.tbo.index')->with('success', 'Credentials set successfully');
    }

    public function index(Request $request)
    {
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

        $startDate = date('Y-m-d', strtotime('-60 days'));
        $endDate = date('Y-m-d');

        $data = new Request([
            'startDate' => $startDate,  
            'endDate' => $endDate
        ]);

        $pastBookings = $this->bookingDetailByDate($data);
        
        if(isset($pastBookings['error'])) {
            return Redirect::back()->withErrors($pastBookings['error']);
        }

        return view('suppliers.tbo.index', compact('countries', 'pastBookings', 'startDate', 'endDate'));
    }

    public function tboGetAuthentication(string $url) 
    {
        logger("TBO GET URL: " . $this->apiUrl . $url);

        $response = Http::withBasicAuth($this->username, $this->password)->get($this->apiUrl . $url);
      
        return $response->json();
    }

    public function tboPostAuthentication(string $url,array $data)
    {
        logger("TBO POST URL: " . $this->apiUrl . $url);

        logger("Data: ", $data);

        $response = Http::withBasicAuth($this->username, $this->password)->post($this->apiUrl . $url, $data);
        
        // might cause error
        return $response->json();
    }

    public function bookIndex(Request $request)
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

        return view('suppliers.tbo.book.index', compact(
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

        logger("Search Response: ", $response);

        return $response;
    }
 
    public function preBookStore(Request $request)
    {
        $request->validate([
            'bookingCode' => 'required',
            'hotelName' => 'required',
            'rooms' => 'array|required',
            'rooms.*.adults' => 'required',
            'rooms.*.children' => 'required', 
        ]);

        $url = '/PreBook';

        $data = [
            'BookingCode' => $request->bookingCode,
        ];

        $response = $this->tboPostAuthentication($url, $data);

        logger('Prebook Response: ', $response);

        if($response['Status']['Code'] !== 200){
            return Redirect::back()->withInput()->withErrors($response['Status']['Description']);
        }

        $hotelResult = $response['HotelResult'];

        foreach ($hotelResult as $hotel) {
            foreach ($hotel['Rooms'] as $rooms) {
                try {
                    $tboPreBook = TBO::create([
                        'booking_code' => $rooms['BookingCode'],
                        'hotel_code' => $hotel['HotelCode'],
                        'hotel_name' => $request->hotelName,
                        'room_quantity' => count($request->rooms),
                        'room_name' => json_encode($rooms['Name']),
                        'currency' => $hotel['Currency'],
                        'inclusion' => $rooms['Inclusion'],
                        'day_rates' => json_encode($rooms['DayRates']),
                        'total_fare' => $rooms['TotalFare'],
                        'total_tax' => $rooms['TotalTax'],
                        'extra_guest_charges' => $rooms['ExtraGuestCharges'] ?? '0',
                        'room_promotion' => json_encode($rooms['RoomPromotion']),
                        'meal_type' => $rooms['MealType'],
                        'is_refundable' => $rooms['IsRefundable'],
                        'with_transfer' => $rooms['WithTransfer'] ?? false,
                    ]);

                    foreach($request->rooms as $key => $room){
                        try{
                            TBORoom::create([
                                'tbo_id' => $tboPreBook->id,
                                'room_name' => $rooms['Name'][$key],
                                'adult_quantity' => $room['adults'],
                                'child_quantity' => $room['children'],
                            ]);
                        } catch (Exception $e) {

                            $tboPreBook->delete();
                            logger('Error: '. $e->getMessage());
                            return Redirect::back()->with('error', 'Something went wrong. Please try again or contact support');
                        }
                    }
                } catch (Exception $e) {
                    logger('Error: '. $e->getMessage()); 
                    return Redirect::back()->withInput()->with('error', 'Something went wrong. Please try again or contact support');
                }
            }
        }

        $tboPreBook = TBO::with('rooms')->find($tboPreBook->id);

        return view('suppliers.tbo.book.prebook-show', compact('tboPreBook'))->with('success', 'Prebook successful');
    }

    public function preBookIndex()
    {
        $tboPreBooks = TBO::all()->sortByDesc('created_at');

        return view('suppliers.tbo.book.prebook-index', compact('tboPreBooks'));
    }

    public function preBookShow($tboId)
    {
        $tboPreBook = TBO::with('rooms')->find($tboId);
        return view('suppliers.tbo.book.prebook-show', compact('tboPreBook'));
    }

    public function book(Request $request)
    {
        $request->validate([
            'tbo_id' => 'required',
            'booking_code' => 'required',
            // 'adult' => 'array|required',
            // 'adult.*.title' => 'required',
            // 'adult.*.first_name' => 'required',
            // 'adult.*.last_name' => 'required',
            // 'child' => 'array|nullable',
            // 'child.*.title' => 'required_if:child,1',
            // 'child.*.first_name' => 'required_if:child,1',
            // 'child.*.last_name' => 'required_if:child,1',
            'rooms' => 'array|required',
            'rooms.*.adults' => 'required',
            'rooms.*.adults.*.title' => 'required',
            'rooms.*.adults.*.first_name' => 'required',
            'rooms.*.adults.*.last_name' => 'required',
            'rooms.*.children' => 'nullable',
            'rooms.*.children.*.title' => 'required_if:rooms.*.children,1',
            'rooms.*.children.*.first_name' => 'required_if:rooms.*.children,1',
            'rooms.*.children.*.last_name' => 'required_if:rooms.*.children,1',
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

        $customerDetails = [];
        foreach ($request->rooms as $key => $room) {
            $customers = [];
            foreach ($room['adults'] as $adult) {
                $customers[] = [
                    'FirstName' => $adult['first_name'],
                    'LastName' => $adult['last_name'],
                    'Title' => $adult['title'],
                    'Type' => 'Adult'
                ];
            }

            if(isset($room['children'])){
                foreach($room['children'] as $child){
                    $customers[] = [
                        'FirstName' => $child['first_name'],
                        'LastName' => $child['last_name'],
                        'Title' => $child['title'],
                        'Type' => 'Child'
                    ];
                }
            }

            $customerDetails[] = [
                'CustomerNames' => $customers
            ];
        }
        // foreach ($adults as $adult) {
        //     $customers[] = [
        //         'FirstName' => $adult['first_name'],
        //         'LastName' => $adult['last_name'],
        //         'Title' => $adult['title'],
        //         'Type' => 'Adult'
        //     ];
        // }

        // if ($children) {
        //     foreach ($children as $child) {
        //         $customers[] = [
        //             'FirstName' => $child['first_name'],
        //             'LastName' => $child['last_name'],
        //             'Title' => $child['title'],
        //             'Type' => 'Child'
        //         ];
        //     }
        // }


        $data = [
            'BookingCode' => $request->booking_code,
            'CustomerDetails' => $customerDetails,
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

        if($request->payment_mode === 'NewCard'){
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

        logger('Booking Response: ', $response);

        if($response['Status']['Code'] !== 200){

            if($response['Status']['Code'] === 400 && $response['Status']['Description'] === 'Session Expired'){
              $tboRequest = TBO::with('rooms')->find($tboId);

              $tboRequest->rooms()->delete();
              $tboRequest->delete();  

                 return Redirect::route('suppliers.tbo.search.index')->with('error', 'Session Expired. Please try again');
            }
            return Redirect::route('suppliers.tbo.prebook.show', $tboId)->withInput()->withErrors($response['Status']['Description']);
            // return back()->withInput()->withErrors($response['Status']['Description']);
        }
            $tboRequest = TBO::with('rooms')->find($tboId);

            $tboRequest->rooms()->delete();
            $tboRequest->delete();  

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

        logger('Booking Detail Response: ', $response);

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

        // logger('Booking Detail By Date Response: ', $response);

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

        logger('Cancel Response: ', $response);

        if($response['Status']['Code'] !== 200){
            return Redirect::back()->withErrors($response['Status']['Description']);
        }

        return Redirect::route('suppliers.tbo.index')->with('success', 'Booking cancelled successfully');
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

        return $this->tboPostAuthentication($url, $data);
        
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

        return $response;
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

    public function getAllDestinations()
    {

        Gate::authorize('role:admin');

        // get all countries

        // get all cities

        // get all hotels

        // return as 
    }

}
