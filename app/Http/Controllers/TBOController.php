<?php

namespace App\Http\Controllers;

use App\Models\TBO;
use App\Models\TBORoom;
use App\Services\TBOHolidayService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class TBOController extends Controller
{
    private $tboService;
    
    public function __construct()
    {
        $this->tboService = new TBOHolidayService();
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

        $this->tboService = new TBOHolidayService($request->url, $request->username, $request->password);

        return Redirect::route('suppliers.tbo.index')->with('success', 'Credentials set successfully');
    }

    public function index(Request $request)
    {
        $pastBookings = [];
        $countries = $this->tboService->getCountryList();

        if(isset($countries['Status']['Code']) && $countries['Status']['Code'] !== 200){
            return redirect()->route('suppliers.index')->withErrors($countries['Status']['Description']);
        }

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

        $startDate = date('Y-m-d', strtotime('-30 days'));
        $endDate = date('Y-m-d');

        $pastBookings = $this->tboService->getBookingDetailByDate($startDate, $endDate);
        
        if(isset($pastBookings['error'])) {
            return redirect()->route('suppliers.index')->withErrors($pastBookings['error']);
        }

        return view('suppliers.tbo.index', compact('countries', 'pastBookings', 'startDate', 'endDate'));
    }

    public function bookIndex(Request $request)
    {
        $hotelList = [];
        $cityList = [];
        $hotelCode = '';
        $guestNationality = '';
        $countryList = $this->tboService->getCountryList();

        if(isset($countryList['Status']['Code']) && $countryList['Status']['Code'] !== 200){
            return Redirect::back()->with('error', $countryList['Status']['Description']);
        }

        $countryCode = '';
        $cityCode = '';
        $hotelCode = '';

        if($request->query('countryCode'))
        {
            $countryCode = $request->query('countryCode');
            $cityListResponse = $this->tboService->getCityList($countryCode);

            if($cityListResponse['Status']['Code'] !== 200){
                return Redirect::back()->with('error', $cityListResponse['Status']['Description']);
            }

            $cityList = $cityListResponse['CityList'];
        }

        if($request->query('cityCode'))
        {
            $cityCode = $request->query('cityCode');
            $hotelListResponse = $this->tboService->getHotelCityList($cityCode);

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

        $data = [
            'CheckIn' => $request->checkInDate,
            'CheckOut' => $request->checkOutDate,
            'HotelCodes' => (string)$request->hotel,
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

        $response = $this->tboService->search($data);

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

        $response = $this->tboService->preBook($request->bookingCode);

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

        $data = [
            'BookingCode' => $request->booking_code,
            'CustomerDetails' => $customerDetails,
            'ClientReferenceId' => $request->client_reference_id,
            'BookingReferenceId' => $request->booking_reference_id,
            'TotalFare' => (float)$request->total_fare,
            'EmailId' => $request->email_id,
            'PhoneNumber' => $request->phone_number,
            'PaymentMode' => $request->payment_mode,
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

        $response = $this->tboService->book($data);

        logger('Booking Response: ', $response);

        if($response['Status']['Code'] !== 200){

            if($response['Status']['Code'] === 400 && $response['Status']['Description'] === 'Session Expired'){
              $tboRequest = TBO::with('rooms')->find($tboId);

              $tboRequest->rooms()->delete();
              $tboRequest->delete();  

                 return Redirect::route('suppliers.tbo.search.index')->with('error', 'Session Expired. Please try again');
            }
            return Redirect::route('suppliers.tbo.prebook.show', $tboId)->withInput()->withErrors($response['Status']['Description']);
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

        $response = $this->tboService->getBookingDetail($data);

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

        return $this->tboService->getBookingDetailByDate($startDate, $endDate);
    }

    public function cancel(string $confirmationNo)
    {
        $response = $this->tboService->cancel($confirmationNo);

        logger('Cancel Response: ', $response);

        if($response['Status']['Code'] !== 200){
            return Redirect::back()->withErrors($response['Status']['Description']);
        }

        return Redirect::route('suppliers.tbo.index')->with('success', 'Booking cancelled successfully');
    }

    public function countryList()
    {
        return $this->tboService->getCountryList();
    }

    private function cityList($countryCode)
    {
        return $this->tboService->getCityList($countryCode);
    }

    public function cityListPage($countryCode)
    {
        $cities = $this->tboService->getCityList($countryCode);

        if($cities['Status']['Code'] !== 200){
            return Redirect::back()->withErrors($cities['Status']['Description']);
        }

        $cities = $cities['CityList'];

        return view('suppliers.tbo.city', compact('cities'));
    }

    public function hotelCityList($cityCode)
    {
        return $this->tboService->getHotelCityList($cityCode);
    }

    public function hotelCodeList()
    {
        return $this->tboService->getHotelCodeList();
    }

    public function hotelDetails(int $hotelCode)
    {
        $response = $this->tboService->getHotelDetails($hotelCode);

        return response()->json($response, $response['Status']['Code']);
    }

    public function getAllDestinations()
    {
        if(!auth()->user()->hasRole('admin'))
        {
            return Redirect::back()->withErrors('You are not authorized to perform this action');
        }

        $countries = $this->tboService->getCountryList();

        return $countries;
    }

}
