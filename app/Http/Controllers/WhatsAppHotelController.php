<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TemporaryOffer;
use App\Models\OfferedRoom;
use App\Models\MapHotel;
use App\Models\Prebooking;
use App\Models\Hotel;
use App\Models\HotelBooking;
use App\Models\RequestBookingRoom;
use App\Models\UserStep;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WhatsAppHotelController extends Controller
{
    // public function getListOfHotels(Request $request)
    // {
    //     Log::channel('whatsapp')->info('getListOfHotels: Incoming request', ['request' => $request->all()]);
    //     try {
    //         $request->validate([
    //             'first_name' => 'required|string',
    //             'second_name' => 'required|string',
    //             'city' => 'required|string',
    //             'checkIn' => 'required|date',
    //             'checkOut' => 'required|date',
    //             'phone_number' => 'required|string',
    //             'occupancy' => 'required|array',
    //             'occupancy.rooms' => 'required|array',
    //             'occupancy.rooms.*.adults' => 'required|integer|min:1',
    //             'occupancy.rooms.*.childrenAges' => 'nullable|array',
    //         ]);

    //         $hotel = MapHotel::where('name', 'like', '%' . $request->first_name . '%')
    //             ->where('name', 'like', '%' . $request->second_name . '%')
    //             ->whereHas('city', function ($query) use ($request) {
    //                 $query->where('name', 'like', '%' . $request->city . '%');
    //             })
    //             ->get()
    //             ->map(function ($hotel) {
    //                 return [
    //                     'hotel_name' => $hotel->name,
    //                     'hotel_address' => $hotel->address,
    //                 ];
    //             })->toArray();

    //         $rooms = $request->occupancy['rooms'];

    //         if (!$hotel) {
    //             Log::channel('whatsapp')->warning('getListOfHotels: Hotel not found', ['request' => $request->all()]);
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Hotel not found.',
    //             ], 404);
    //         }

    //         $requestBookingRoomId = [];
    //         foreach ($rooms as $index => $room) {
    //             if (!isset($room['adults']) || $room['adults'] < 1) {
    //                 Log::channel('whatsapp')->warning('getListOfHotels: Each room must have at least one adult', ['room' => $room]);
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'Each room must have at least one adult.',
    //                 ], 422);
    //             }

    //             $requestBookingRoom = RequestBookingRoom::create([
    //                 'phone_number' => $request->phone_number,
    //                 'check_in' => $request->checkIn,
    //                 'check_out' => $request->checkOut,
    //                 'adults' => $room['adults'],
    //                 'children_ages' => isset($room['childrenAges']) ? json_encode($room['childrenAges']) : null,
    //             ]);

    //             if(!$requestBookingRoom) {
    //                 Log::channel('whatsapp')->error('getListOfHotels: Failed to create booking request', ['room' => $room]);
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'Failed to create booking request.',
    //                 ], 500);
    //             }

    //             $requestBookingRoomId[] = $requestBookingRoom->id;
    //         }

    //         $response = [
    //             'success' => true,
    //             'message' => 'Hotels found successfully.',
    //             'hotels' => $hotel,
    //             'request_booking_room_id' => $requestBookingRoomId,
    //         ];
    //         Log::channel('whatsapp')->info('getListOfHotels: Success response', ['response' => $response]);
    //         return response()->json($response);

    //     } catch (Exception $e) {
    //         Log::channel('whatsapp')->error('getListOfHotels: Exception', ['error' => $e->getMessage()]);
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'An error occurred.',
    //         ], 500);
    //     }
    // }
    public function saveBookingDetails(Request $request)
{
    Log::channel('whatsapp')->info('saveBookingDetails: Incoming request', ['request' => $request->all()]);

    $validator = Validator::make($request->all(), [
        'phone_number'    => 'required|string',
        'checkIn'         => 'required|date',
        'checkOut'        => 'required|date|after_or_equal:checkIn',
        'occupancy'       => 'required|array',
        'occupancy.rooms' => 'required|array|min:1',
        'hotel'           => 'nullable|string', // optional, we still save without it
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success'       => false,
            'message'       => 'Validation failed',
            'errors'        => $validator->errors(),
            'saved_count'   => 0,
            'saved_ids'     => [],
            'skipped_count' => 0,
        ], 422);
    }

    // ---- Resolve hotel (exact match) and city (same pattern you used) ----
    $matchedHotelName = null;
    $matchedCityId    = null;
    $matchedCityName  = null;
    $warnings         = [];

    if ($request->filled('hotel')) {
        $inputHotelName = trim($request->hotel);

        $matched = \App\Models\MapHotel::with(['city:id,name'])
            ->where('name', $inputHotelName)   // exact match (use whereRaw BINARY for case-sensitive)
            ->first();

        if ($matched) {
            $matchedHotelName = $matched->name;
            if ($matched->city) {
                $matchedCityId   = $matched->city->id ?? null;
                $matchedCityName = $matched->city->name ?? null;
            } else {
                $warnings['city'][] = 'Hotel matched but has no city associated.';
            }
        } else {
            $warnings['hotel'][] = 'Hotel not found with exact name; saved/updated without hotel/city.';
        }
    } else {
        $warnings['hotel'][] = 'Hotel not provided; saved/updated without hotel/city.';
    }

    // ---- We will update the latest record for this phone_number ----
    $existing = \App\Models\RequestBookingRoom::where('phone_number', $request->phone_number)
        ->orderByDesc('id')
        ->first();

    // take the FIRST room only for update
    $room      = $request->input('occupancy.rooms.0'); // guaranteed to exist due to validation
    $roomValid = Validator::make($room, [
        'adults'         => 'required|integer|min:1',
        'childrenAges'   => 'nullable|array',
        'childrenAges.*' => 'integer|min:0',
    ]);
    if ($roomValid->fails()) {
        return response()->json([
            'success'       => false,
            'message'       => 'Room validation failed',
            'errors'        => ['rooms.0' => $roomValid->errors()],
            'saved_count'   => 0,
            'saved_ids'     => [],
            'skipped_count' => 0,
        ], 422);
    }

    if (count($request->input('occupancy.rooms')) > 1) {
        $warnings['rooms'][] = 'Multiple rooms were sent; only the first room was used to update the latest record.';
    }

    try {
        if ($existing) {
            // ------- UPDATE existing latest record -------
            $existing->check_in      = $request->checkIn;
            $existing->check_out     = $request->checkOut;
            $existing->adults        = $room['adults'];
            $existing->children_ages = isset($room['childrenAges']) ? json_encode($room['childrenAges']) : null;

            // set hotel/city only if matched
            $existing->hotel   = $matchedHotelName;  // may be null
            $existing->city_id = $matchedCityId;     // may be null
            $existing->city    = $matchedCityName;   // may be null

            $existing->save();

            return response()->json([
                'success'         => true,
                'message'         => 'Booking details updated.',
                'saved_count'     => 1,
                'saved_ids'       => [$existing->id],
                'skipped_count'   => 0,
                'warnings'        => $warnings,
            ]);
        } else {
            // ------- If nothing exists yet, create one (change to 404 if you prefer strict update-only) -------
            $row = new \App\Models\RequestBookingRoom();
            $row->phone_number  = $request->phone_number;
            $row->check_in      = $request->checkIn;
            $row->check_out     = $request->checkOut;
            $row->adults        = $room['adults'];
            $row->children_ages = isset($room['childrenAges']) ? json_encode($room['childrenAges']) : null;
            $row->hotel         = $matchedHotelName;  // may be null
            $row->city_id       = $matchedCityId;     // may be null
            $row->city          = $matchedCityName;   // may be null
            $row->save();

            return response()->json([
                'success'         => true,
                'message'         => 'No existing record found; a new booking record was created.',
                'saved_count'     => 1,
                'saved_ids'       => [$row->id],
                'skipped_count'   => 0,
                'warnings'        => $warnings,
            ]);
        }
    } catch (\Exception $e) {
        Log::channel('whatsapp')->error('saveBookingDetails: Update/create failed', [
            'error' => $e->getMessage(),
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Failed to save booking details.',
        ], 500);
    }
}










    public function listHotels(Request $request)
    {
        Log::channel('whatsapp')->info('listHotels: Incoming request', ['request' => $request->all()]);

        $validator = Validator::make($request->all(), [
            'first_name'  => 'required|string',
            'second_name' => 'required|string',
            'city'        => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            $friendlyMessages = [];

            if (isset($errors['first_name']) || isset($errors['second_name'])) {
                $friendlyMessages[] = "hotel name";
            }
            if (isset($errors['city'])) {
                $friendlyMessages[] = "city";
            }

            $msg = "Sorry, I didn’t get the " . implode(" and ", $friendlyMessages) . ". Please provide it.";

            return response()->json([
                'success' => false,
                'message' => $msg,
                'errors'  => $errors, // still keep raw errors for debugging/logging
            ], 422);
        }

        try {
            $hotels = MapHotel::query()
                ->where('name', 'like', '%' . $request->first_name . '%')
                ->where('name', 'like', '%' . $request->second_name . '%')
                ->whereHas('city', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->city . '%');
                })
                ->get()
                ->map(fn($h) => [
                    'hotel_name'    => $h->name,
                    'hotel_address' => $h->address,
                ])->values()->toArray();

            if (empty($hotels)) {
                return response()->json(['success' => false, 'message' => 'No hotels found with the given details.'], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Hotels found.',
                'hotels'  => $hotels,
            ]);
        } catch (Exception $e) {
            Log::channel('whatsapp')->error('listHotels: Exception', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'An error occurred.'], 500);
        }
    }


    // public function getHotelDetails(Request $request)
    // {
    //     Log::channel('whatsapp')->info('getHotelDetails: Incoming request', ['request' => $request->all()]);
    //     try {
    //         $request->validate([
    //             'hotel_name' => 'required|string',
    //             'phone_number' => 'required|string',
    //         ]);

    //         $hotel = MapHotel::with(['city:id,name'])
    //             ->where('name', 'like', '%' . $request->hotel_name . '%')
    //             ->first();

    //         if (!$hotel) {
    //             Log::channel('whatsapp')->warning('getHotelDetails: Hotel not found', ['hotel_name' => $request->hotel_name]);
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Hotel not found.',
    //             ], 404);
    //         }

    //         $bookingRequest = RequestBookingRoom::where('phone_number', $request->phone_number)
    //             ->get()
    //             ->map(function ($item) {
    //                 return [
    //                     'id' => $item->id,
    //                     'phone_number' => $item->phone_number,
    //                     'check_in' => $item->check_in ? date('Y-m-d', strtotime($item->check_in)) : null,
    //                     'check_out' => $item->check_out ? date('Y-m-d', strtotime($item->check_out)) : null,
    //                     'adults' => $item->adults,
    //                     'children_ages' => $item->children_ages ? json_decode($item->children_ages, true) : [],
    //                 ];
    //             })
    //             ->toArray();

    //         if (!$bookingRequest) {
    //             Log::channel('whatsapp')->warning('getHotelDetails: No booking request found', ['phone_number' => $request->phone_number]);
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'No booking request found for this phone number.',
    //             ], 400);
    //         }

    //         $checkIn = $bookingRequest[0]['check_in'] ?? null;
    //         $checkOut = $bookingRequest[0]['check_out'] ?? null;

    //         if( !$checkIn || !$checkOut) {
    //             Log::channel('whatsapp')->warning('getHotelDetails: Check-in or check-out date not found', ['booking_request' => $bookingRequest]);
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Check-in or check-out date not found.',
    //             ], 400);
    //         }

    //         $bookingRequest = collect($bookingRequest)->map(function ($item) {
    //             return [
    //                 'adults' => $item['adults'],
    //                 'childrenAges' => $item['children_ages'],
    //             ];
    //         })->toArray();

    //         $cityId = $hotel->city->id ?? null;

    //         if($cityId === null) {
    //             Log::channel('whatsapp')->warning('getHotelDetails: City not found for hotel', ['hotel' => $hotel->name]);
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'City not found for this hotel.',
    //             ], 404);
    //         }

    //         $cityName = $hotel->city->name;

    //         $response = [
    //             'success' => true,
    //             'hotel' => [
    //                 'hotel_name' => $hotel->name,
    //                 'hotel_address' => $hotel->address,
    //                 'city_id' => $cityId,
    //                 'city_name' => $cityName,
    //                 'check_in' => $checkIn,
    //                 'check_out' => $checkOut,
    //                 'booking_request' => $bookingRequest,
    //             ],
    //         ];
    //         Log::channel('whatsapp')->info('getHotelDetails: Success response', ['response' => $response]);
    //         return response()->json($response);
    //     } catch (\Exception $e) {
    //         Log::channel('whatsapp')->error('getHotelDetails: Exception', ['error' => $e->getMessage()]);
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'An error occurred.',
    //         ], 500);
    //     }
    // }
    public function getHotelDetails(Request $request)
    {
        Log::channel('whatsapp')->info('getHotelDetails: Incoming request', ['request' => $request->all()]);
        try {
            $request->validate([
                'phone_number' => 'required|string',
            ]);

            // Last (newest) record for this phone_number
            $last = RequestBookingRoom::where('phone_number', $request->phone_number)
                ->orderByDesc('id')
                ->first();

            if (!$last) {
                Log::channel('whatsapp')->warning('getHotelDetails: No booking request found', ['phone_number' => $request->phone_number]);
                return response()->json([
                    'success' => false,
                    'message' => 'No booking request found for this phone number.',
                ], 400);
            }

            $checkIn  = $last->check_in  ? date('Y-m-d', strtotime($last->check_in))  : null;
            $checkOut = $last->check_out ? date('Y-m-d', strtotime($last->check_out)) : null;

            if (!$checkIn || !$checkOut) {
                Log::channel('whatsapp')->warning('getHotelDetails: Check-in or check-out date not found', ['last_id' => $last->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Check-in or check-out date not found.',
                ], 400);
            }

            // ONLY the last record
            $rooms = [[
                'adults'       => (int) $last->adults,
                'childrenAges' => $last->children_ages ? json_decode($last->children_ages, true) : [],
            ]];

            $response = [
                'success' => true,
                'booking' => [
                    'hotel'           => $last->hotel,    // may be null
                    'city_id'         => $last->city_id,  // may be null
                    'check_in'        => $checkIn,
                    'check_out'       => $checkOut,
                    'booking_request' => $rooms,
                ],
            ];

            Log::channel('whatsapp')->info('getHotelDetails: Success response', ['response' => $response]);
            return response()->json($response);

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('getHotelDetails: Exception', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
            ], 500);
        }
    }




    public function storeTemporaryOffer(Request $request)
    {
        Log::channel('whatsapp')->info('storeTemporaryOffer: Incoming request', ['request' => $request->all()]);
        try {
            $request->validate([
                'telephone' => 'required|string',
                'srk' => 'required|string',
                'hotel_index' => 'required|integer',
                'hotel_name' => 'required|string',
                'result_token' => 'required|string',
                'enquiry_id' => 'required|string',
                'offers' => 'required|array|min:1',
                'offers.*.offer_index' => 'required|string',
                'offers.*.room_details' => 'required|array|min:1',
                'offers.*.room_details.*.room_name' => 'required|string',
                'offers.*.room_details.*.board_basis' => 'required|string',
                'offers.*.room_details.*.non_refundable' => 'nullable|boolean',
                'offers.*.room_details.*.room_token' => 'required|string',
                'offers.*.room_details.*.price' => 'required|numeric',
                'offers.*.room_details.*.currency' => 'nullable|string',
                'offers.*.room_details.*.package_token' => 'required|string',
                'offers.*.room_details.*.info' => 'nullable|string',
                'offers.*.room_details.*.occupancy' => 'nullable|array',
            ]);

            TemporaryOffer::where('telephone', $request->telephone)->delete();

            $allOffers = [];

            foreach ($request->offers as $offer) {
                $tempOffer = TemporaryOffer::create([
                    'telephone' => $request->telephone,
                    'srk' => $request->srk,
                    'hotel_index' => $request->hotel_index,
                    'hotel_name' => $request->hotel_name,
                    'offer_index' => $offer['offer_index'],
                    'result_token' => $request->result_token,
                    'enquiry_id' => $request->enquiry_id,
                ]);

                $roomModels = [];

                foreach ($offer['room_details'] as $room) {
                    $roomModels[] = OfferedRoom::create([
                        'temp_offer_id' => $tempOffer->id,
                        'room_name' => $room['room_name'],
                        'board_basis' => $room['board_basis'],
                        'non_refundable' => $room['non_refundable'],
                        'info' => $room['info'] ?? '',
                        'occupancy' => json_encode($room['occupancy'] ?? []),
                        'price' => $room['price'],
                        'currency' => $room['currency'] ?? 'KWD',
                        'room_token' => $room['room_token'],
                        'package_token' => $room['package_token'],
                    ]);
                }

                $allOffers[] = [
                    'offer_index' => $tempOffer->offer_index,
                    'room_details' => collect($roomModels)->map(fn($r) => [
                        'room_name' => $r->room_name,
                        'board_basis' => $r->board_basis,
                        'non_refundable' => $r->non_refundable,
                        'room_token' => $r->room_token,
                        'occupancy' => json_decode($r->occupancy, true) ?: [],
                        'package_token' => $r->package_token,
                        'price' => $r->price,
                        'currency' => $r->currency,
                    ])
                ];
            }

            $response = [
                'success' => true,
                'message' => 'Hotel offers saved successfully.',
                'hotel' => $request->hotel_name,
                'offers' => $allOffers,
            ];
            Log::channel('whatsapp')->info('storeTemporaryOffer: Success response', ['response' => $response]);
            return response()->json($response, 201);
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('storeTemporaryOffer: Exception', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
            ], 500);
        }
    }

    public function findOffer(Request $request)
    {
        Log::channel('whatsapp')->info('findOffer: Incoming request', ['request' => $request->all()]);
        try {
            $request->validate([
                'telephone' => 'required|string',
                'room_name' => 'required|string',
                'board_basis' => 'nullable|string',
                'non_refundable' => 'nullable|boolean',
                'price' => 'nullable|numeric',
                'occupancy' => 'nullable|array',
            ]);

            $offers = TemporaryOffer::where('telephone', $request->telephone)->get();

            if ($offers->isEmpty()) {
                Log::channel('whatsapp')->warning('findOffer: No matching offer found', ['telephone' => $request->telephone]);
                return response()->json([
                    'success' => false,
                    'message' => 'No matching offer found.'
                ], 404);
            }

            $offer = $offers->first();

            $roomQuery = OfferedRoom::whereIn('temp_offer_id', $offers->pluck('id'));

            if ($request->filled('room_name')) {
                $roomQuery->where('room_name', 'like', '%' . $request->room_name . '%');
            }

            if ($request->has('board_basis')) {
                if (is_null($request->board_basis)) {
                    $roomQuery->whereNull('board_basis');
                } else {
                    $roomQuery->where('board_basis', 'like', '%' . $request->board_basis . '%');
                }
            }

            if ($request->has('non_refundable')) {
                $roomQuery->where('non_refundable', $request->non_refundable);
            }

            if ($request->has('price')) {
                $roomQuery->where('price', $request->price);
            }

            if( $request->has('occupancy')) {
                $roomQuery->where('occupancy', 'like', '%' . json_encode($request->occupancy) . '%');
            }

            $rooms = $roomQuery->get();

            if ($rooms->isEmpty()) {
                Log::channel('whatsapp')->warning('findOffer: No matching room(s) found', ['request' => $request->all()]);
                return response()->json([
                    'success' => false,
                    'message' => 'No matching room(s) found.'
                ], 404);
            }

            $groupedOffers = $rooms->groupBy(function ($room) {
                return $room->temporaryOffer->offer_index;
            })->map(function ($group, $offerIndex) {
                return [
                    'offer_index' => $offerIndex,
                    'room_details' => $group->map(function ($room) {
                        return [
                            'room_name' => $room->room_name,
                            'board_basis' => $room->board_basis,
                            'non_refundable' => (bool) $room->non_refundable,
                            'room_token' => $room->room_token,
                            'package_token' => $room->package_token,
                            'price' => (float) $room->price,
                            'currency' => $room->currency ?? 'KWD',
                            'occupancy' => json_decode($room->occupancy, true) ?: [],
                        ];
                    })->values(),
                ];
            })->values();

            $response = [
                'success' => true,
                'data' => [
                    'telephone' => $offer->telephone,
                    'enquiry_id' => $offer->enquiry_id,
                    'srk' => $offer->srk,
                    'hotel_index' => (int) $offer->hotel_index,
                    'hotel_name' => $offer->hotel_name,
                    'result_token' => $offer->result_token,
                    'offers' => $groupedOffers,
                ],
            ];
            Log::channel('whatsapp')->info('findOffer: Success response', ['response' => $response]);
            return response()->json($response);
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('findOffer: Exception', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
            ], 500);
        }
    }

    public function findAllOffers(Request $request){
        Log::channel('whatsapp')->info('findOffer: Incoming request', ['request' => $request->all()]);
        try {
            $request->validate([
                'telephone' => 'required|string',
                // 'room_name' => 'required|string',
                'board_basis' => 'nullable|string',
                'non_refundable' => 'nullable|boolean',
                'price' => 'nullable|numeric',
                'occupancy' => 'nullable|array',
            ]);

            $offers = TemporaryOffer::where('telephone', $request->telephone)->get();

            $roomQuery = OfferedRoom::whereIn('temp_offer_id', $offers->pluck('id'));

            if ($offers->isEmpty()) {
                Log::channel('whatsapp')->warning('findOffer: No matching offer found', ['telephone' => $request->telephone]);
                return response()->json([
                    'success' => false,
                    'message' => 'No matching offer found.'
                ], 404);
            }

            if ($request->has('board_basis')) {
                if (is_null($request->board_basis)) {
                    $roomQuery->whereNull('board_basis');
                } else {
                    $roomQuery->where('board_basis', 'like', '%' . $request->board_basis . '%');
                }
            }

            if ($request->has('non_refundable')) {
                $roomQuery->where('non_refundable', $request->non_refundable);
            }

            if ($request->has('price')) {
                $roomQuery->where('price', $request->price);
            }

            if ($request->has('occupancy')) {
                $roomQuery->where('occupancy', 'like', '%' . json_encode($request->occupancy) . '%');
            }

            $rooms = $roomQuery->get();

            if ($rooms->isEmpty()) {
                Log::channel('whatsapp')->warning('findOffer: No matching room(s) found', ['request' => $request->all()]);
                return response()->json([
                    'success' => false,
                    'message' => 'No matching room(s) found.'
                ], 404);
            }

            $groupedOffers = $rooms->groupBy(function ($room) {
                return $room->temporaryOffer->offer_index;
            })->map(function ($group, $offerIndex) {
                return [
                    'offer_index' => $offerIndex,
                    'room_details' => $group->map(function ($room) {
                        return [
                            'room_name' => $room->room_name,
                            'board_basis' => $room->board_basis,
                            'non_refundable' => (bool) $room->non_refundable,
                            'room_token' => $room->room_token,
                            'package_token' => $room->package_token,
                            'price' => (float) $room->price,
                            'currency' => $room->currency ?? 'KWD',
                            'occupancy' => json_decode($room->occupancy, true) ?: [],
                        ];
                    })->values(),
                ];
            })->values();

            $response = [
                'success' => true,
                'data' => [
                    'telephone' => $request->telephone,
                    'offers' => $groupedOffers,
                ],
            ];
            Log::channel('whatsapp')->info('findOffer: Success response', ['response' => $response]);
            return response()->json($response);
        } catch (Exception $e) {
            Log::channel('whatsapp')->error('findOffer: Exception', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
            ], 500);
        }
    }

    public function storePrebook(Request $request)
    {
        Log::channel('whatsapp')->info('storePrebook: Incoming request', ['request' => $request->all()]);
        try {
            $request->validate([
                'telephone' => 'required|string',
                'availability_token' => 'required|string',
                'srk' => 'required|string',
                'package_token' => 'required|string',
                'hotel_id' => 'required|integer',
                'offer_index' => 'required|string',
                'result_token' => 'required|string',
                'rooms' => 'required|array|min:1',
                'rooms.*.room_token' => 'required|string',
                'rooms.*.room_name' => 'required|string',
                'rooms.*.board_basis' => 'required|string',
                'rooms.*.non_refundable' => 'nullable|boolean',
                'rooms.*.price' => 'required|numeric',
                'rooms.*.currency' => 'required|string',
                'rooms.*.occupancy' => 'nullable|array',
                'checkin' => 'required|date',
                'checkout' => 'required|date',
                'duration' => 'nullable|integer',
                'autocancel_date' => 'nullable|date',
                'cancel_policy' => 'nullable|array',
                'remarks' => 'nullable|array',
            ]);

            $prebookKey = 'PB-' . substr(uniqid(), -5);

            $prebook = Prebooking::create([
                'prebook_key' => $prebookKey,
                'telephone' => $request->telephone,
                'availability_token' => $request->availability_token,
                'srk' => $request->srk,
                'package_token' => $request->package_token,
                'hotel_id' => $request->hotel_id,
                'offer_index' => $request->offer_index,
                'result_token' => $request->result_token,
                'rooms' => $request->rooms,
                'checkin' => $request->checkin,
                'checkout' => $request->checkout,
                'duration' => $request->duration,
                'autocancel_date' => $request->autocancel_date ?? null,
                'cancel_policy' => json_encode($request->cancel_policy) ?? null,
                'remarks' => json_encode($request->remarks) ?? null,
            ]);

            $response = [
                'success' => true,
                'prebook_key' => $prebookKey,
                'prebooking_id' => $prebook->id,
            ];
            Log::channel('whatsapp')->info('storePrebook: Success response', ['response' => $response]);
            return response()->json($response);
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('storePrebook: Exception', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
            ], 500);
        }
    }

    public function getPrebookDetails(Request $request)
    {
        Log::channel('whatsapp')->info('getPrebookDetails: Incoming request', ['request' => $request->all()]);
        try {
            $request->validate([
                'telephone' => 'required|string',
                'prebook_key' => 'required|string',
            ]);

            $prebook = Prebooking::where('telephone', $request->telephone)
                ->where('prebook_key', $request->prebook_key)
                ->first();

            if (!$prebook) {
                Log::channel('whatsapp')->warning('getPrebookDetails: Prebooking not found', ['telephone' => $request->telephone, 'prebook_key' => $request->prebook_key]);
                return response()->json([
                    'success' => false,
                    'message' => 'Prebooking not found.',
                ], 404);
            }

            $hotel = MapHotel::with('city:id,name')->find($prebook->hotel_id);
            $hotelName = $hotel->name ?? null;
            $cityId = $hotel->city->id ?? null;
            $cityName = $hotel->city->name;
            
            if($cityId === null) {
                Log::channel('whatsapp')->warning('getHotelDetails: City not found for hotel', ['hotel' => $hotel->name]);
                return response()->json([
                    'success' => false,
                    'message' => 'City not found for this hotel.',
                ], 404);
            }

            $response = [
                'success' => true,
                'data' => [
                    'prebook_key' => $prebook->prebook_key,
                    'telephone' => $prebook->telephone,
                    'availability_token' => $prebook->availability_token,
                    'srk' => $prebook->srk,
                    'package_token' => $prebook->package_token,
                    'hotel_id' => $prebook->hotel_id,
                    'hotel_name' => $hotelName,
                    'city_name' => $cityName,
                    'offer_index' => $prebook->offer_index,
                    'result_token' => $prebook->result_token,
                    'rooms' => is_string($prebook->rooms) ? json_decode($prebook->rooms, true) : $prebook->rooms,
                    'checkin' => $prebook->checkin,
                    'checkout' => $prebook->checkout,
                    'duration' => $prebook->duration,
                    'autocancel_date' => $prebook->autocancel_date,
                    'cancel_policy' => json_decode($prebook->cancel_policy, true),
                    'remarks' => json_decode($prebook->remarks, true),
                ],
            ];
            Log::channel('whatsapp')->info('getPrebookDetails: Success response', ['response' => $response]);
            return response()->json($response);
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('getPrebookDetails: Exception', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
            ], 500);
        }
    }

    public function storeBooking(Request $request)
    {
        Log::channel('whatsapp')->info('storeBooking: Incoming request', ['request' => $request->all()]);
        try {
            $request->validate([
                'prebook_key' => 'required|string',
                'supplier_booking_id' => 'required|string',
                'client_ref' => 'required|string',
                'status' => 'required|string',
                'price' => 'required|numeric',
                'currency' => 'required|string',
                'booking_time' => 'required|date'
            ]);

            $prebook = Prebooking::where('prebook_key', $request->prebook_key)->first();

            if (!$prebook) {
                Log::channel('whatsapp')->warning('storeBooking: Prebooking not found', ['prebook_key' => $request->prebook_key]);
                return response()->json([
                    'success' => false,
                    'message' => 'Prebooking not found.',
                ], 404);
            }

            $booking = HotelBooking::create([
                'prebook_id' => $prebook->id,
                'supplier_booking_id' => $request->supplier_booking_id,
                'client_ref' => $request->client_ref,
                'status' => $request->status,
                'price' => $request->price,
                'currency' => $request->currency,
                'booking_time' => $request->booking_time,
            ]);

            $response = [
                'success' => true,
                'booking_id' => $booking->id,
            ];
            Log::channel('whatsapp')->info('storeBooking: Success response', ['response' => $response]);
            return response()->json($response);
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('storeBooking: Exception', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
            ], 500);
        }
    }

    public function deleteBookingRequest(Request $request)
    {
        Log::channel('whatsapp')->info('deleteBookingRequest: Incoming request', ['request' => $request->all()]);

        $request->validate([
            'phone_number' => 'required|string',
        ]);

        try {
            $bookingRequests = RequestBookingRoom::where('phone_number', $request->phone_number)->get();

            if ($bookingRequests->isEmpty()) {
                Log::channel('whatsapp')->warning('deleteBookingRequest: No booking requests found', ['phone_number' => $request->phone_number]);
                return response()->json([
                    'success' => false,
                    'message' => 'No booking requests found for this phone number.',
                ], 404);
            }

            foreach ($bookingRequests as $bookingRequest) {
                $bookingRequest->delete();
            }

            Log::channel('whatsapp')->info('deleteBookingRequest: Booking requests deleted successfully', ['phone_number' => $request->phone_number]);

            return response()->json([
                'success' => true,
                'message' => 'Booking requests deleted successfully.',
            ]);

        } catch (Exception $e) {
            Log::channel('whatsapp')->error('deleteBookingRequest: Exception', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting booking requests.',
            ], 500);
        }
    }

    public function temporaryOffersTimeLeft(Request $request)
    {
        Log::channel('whatsapp')->info('getTemporaryOffersTimeLeft: Incoming request', ['request' => $request->all()]);

        try {
            $request->validate([
                'telephone' => 'required|string',
            ]);

            $offers = TemporaryOffer::where('telephone', $request->telephone)->get();

            if ($offers->isEmpty()) {
                Log::channel('whatsapp')->warning('getTemporaryOffersTimeLeft: No temporary offers found', ['telephone' => $request->telephone]);
                return response()->json([
                    'success' => false,
                    'message' => 'No temporary offers found for this telephone number.',
                ], 200);
            }

            $latestCreatedAt = $offers->max('created_at');
            if ($latestCreatedAt) {
                $secondsPassed = now()->diffInSeconds($latestCreatedAt);
                $secondsPassed = -$secondsPassed; // Ensure we get a positive value for time passed
                $minutesPassed = floor($secondsPassed / 60);
                $secondsRemainder = $secondsPassed % 60;
                $minutesLeft = max(0, 15 - $minutesPassed - ($secondsRemainder > 0 ? 1 : 0));

                if ($minutesPassed >= 15) {
                    Log::channel('whatsapp')->info('getTemporaryOffersTimeLeft: Offer expired', [
                        'minutes_passed' => $minutesPassed,
                        'time_left' => 'expired'
                    ]);
                    return response()->json([
                        'success' => false,
                        'data' => [
                            'minutes_passed' => $minutesPassed,
                            'time_left' => 'expired'
                        ],
                        'message' => 'expired'
                    ], 410);
                }

                // Format passed time
                $passedString = '';
                if ($minutesPassed > 0) {
                    $passedString .= $minutesPassed . ' minute' . ($minutesPassed > 1 ? 's' : '');
                }
                if ($secondsRemainder > 0) {
                    if ($passedString) $passedString .= ' ';
                    $passedString .= $secondsRemainder . ' second' . ($secondsRemainder > 1 ? 's' : '');
                }
                if ($passedString) {
                    $passedString .= ' ago';
                } else {
                    $passedString = 'just now';
                }
                $leftString = $minutesLeft . ' minute' . ($minutesLeft == 1 ? '' : 's') . ' remaining';
            } else {
                $passedString = 'just now';
                $leftString = '0 minutes remaining';
            }
            $timeLeft = [
                'latest_created_at' => $latestCreatedAt ? $latestCreatedAt->toDateTimeString() : null,
                'minutes_passed' => $passedString,
                'time_left' => $leftString,
            ];

            Log::channel('whatsapp')->info('getTemporaryOffersTimeLeft: Success response', ['time_left' => $timeLeft]);

            return response()->json([
                'success' => true,
                'data' => $timeLeft,
                'message' => 'Temporary offers are available for 15 minutes from the time of creation. You have ' . $leftString . ' since the last offer was created.',
            ]);
        } catch (Exception $e) {
            Log::channel('whatsapp')->error('getTemporaryOffersTimeLeft: Exception', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving time left for temporary offers.',
            ], 500);
        }
    }

    public function storeStep(Request $request)
    {
        Log::channel('whatsapp')->info('storeStep: Incoming request', ['request' => $request->all()]);
        
        try {
            $request->validate([
                'phone' => 'required|string',
                'step' => 'required|string',
                'hotel' => 'nullable|string',
            ]);

            // Check if step already exists for this phone number
            $existingStep = UserStep::where('phone', $request->phone)->first();

            if ($existingStep) {
                // Update existing step
                $existingStep->update([
                    'step' => $request->step,
                    'hotel' => $request->hotel,
                ]);
                
                $userStep = $existingStep;
                $message = 'Step updated successfully.';
            } else {
                // Create new step
                $userStep = UserStep::create([
                    'phone' => $request->phone,
                    'step' => $request->step,
                    'hotel' => $request->hotel,
                ]);
                
                $message = 'Step created successfully.';
            }

            $response = [
                'success' => true,
                'message' => $message,
                'data' => [
                    'id' => $userStep->id,
                    'phone' => $userStep->phone,
                    'step' => $userStep->step,
                    'hotel' => $userStep->hotel,
                    'created_at' => $userStep->created_at,
                    'updated_at' => $userStep->updated_at,
                ],
            ];

            Log::channel('whatsapp')->info('storeStep: Success response', ['response' => $response]);
            return response()->json($response);
            
        } catch (Exception $e) {
            Log::channel('whatsapp')->error('storeStep: Exception', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function retrieveStep(Request $request)
    {
        Log::channel('whatsapp')->info('retrieveStep: Incoming request', ['request' => $request->all()]);
        
        try {
            $request->validate([
                'phone' => 'required|string',
            ]);

            $userStep = UserStep::where('phone', $request->phone)->first();

            if (!$userStep) {
                Log::channel('whatsapp')->warning('retrieveStep: Step not found', ['phone' => $request->phone]);
                return response()->json([
                    'success' => false,
                    'message' => 'No step found for this phone number.',
                ], 404);
            }

            $response = [
                'success' => true,
                'data' => [
                    'id' => $userStep->id,
                    'phone' => $userStep->phone,
                    'step' => $userStep->step,
                    'hotel' => $userStep->hotel,
                    'created_at' => $userStep->created_at,
                    'updated_at' => $userStep->updated_at,
                ],
            ];

            Log::channel('whatsapp')->info('retrieveStep: Success response', ['response' => $response]);
            return response()->json($response);
            
        } catch (Exception $e) {
            Log::channel('whatsapp')->error('retrieveStep: Exception', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateStep(Request $request)
    {
        Log::channel('whatsapp')->info('updateStep: Incoming request', ['request' => $request->all()]);
        
        try {
            $request->validate([
                'phone' => 'required|string',
                'step' => 'nullable|string',
                'hotel' => 'nullable|string',
            ]);

            $userStep = UserStep::where('phone', $request->phone)->first();

            if (!$userStep) {
                Log::channel('whatsapp')->warning('updateStep: Step not found', ['phone' => $request->phone]);
                return response()->json([
                    'success' => false,
                    'message' => 'No step found for this phone number.',
                ], 404);
            }

            // Update only provided fields
            $updateData = [];
            if ($request->has('step')) {
                $updateData['step'] = $request->step;
            }
            if ($request->has('hotel')) {
                $updateData['hotel'] = $request->hotel;
            }

            $userStep->update($updateData);

            $response = [
                'success' => true,
                'message' => 'Step updated successfully.',
                'data' => [
                    'id' => $userStep->id,
                    'phone' => $userStep->phone,
                    'step' => $userStep->step,
                    'hotel' => $userStep->hotel,
                    'created_at' => $userStep->created_at,
                    'updated_at' => $userStep->updated_at,
                ],
            ];

            Log::channel('whatsapp')->info('updateStep: Success response', ['response' => $response]);
            return response()->json($response);
            
        } catch (Exception $e) {
            Log::channel('whatsapp')->error('updateStep: Exception', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteStep(Request $request)
    {
        Log::channel('whatsapp')->info('deleteStep: Incoming request', ['request' => $request->all()]);
        
        try {
            $request->validate([
                'phone' => 'required|string',
            ]);

            $userStep = UserStep::where('phone', $request->phone)->first();

            if (!$userStep) {
                Log::channel('whatsapp')->warning('deleteStep: Step not found', ['phone' => $request->phone]);
                return response()->json([
                    'success' => false,
                    'message' => 'No step found for this phone number.',
                ], 404);
            }

            $userStep->delete();

            $response = [
                'success' => true,
                'message' => 'Step deleted successfully.',
            ];

            Log::channel('whatsapp')->info('deleteStep: Success response', ['response' => $response]);
            return response()->json($response);
            
        } catch (Exception $e) {
            Log::channel('whatsapp')->error('deleteStep: Exception', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
