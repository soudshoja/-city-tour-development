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

    // Normalize common "null"/"undefined"/"" to actual null for partials
    foreach (['hotel','checkIn','checkOut'] as $k) {
        if ($request->has($k)) {
            $v = $request->input($k);
            if (is_string($v) && in_array(strtolower(trim($v)), ['null','undefined',''])) {
                $request->merge([$k => null]);
            }
        }
    }

    // 🔧 Normalize occupancy: if it's a JSON string, decode it to array before validation
    if ($request->has('occupancy')) {
        $occ = $request->input('occupancy');
        if (is_string($occ)) {
            $decoded = json_decode($occ, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $request->merge(['occupancy' => $decoded]);
            } elseif (trim($occ) === '' || strtolower(trim($occ)) === 'null') {
                $request->merge(['occupancy' => null]);
            }
        }
    }

    // Upsert validation: only phone_number is required. Others optional.
    $validator = Validator::make($request->all(), [
        'phone_number' => 'required|string',
        'checkIn'      => 'nullable|date',
        'checkOut'     => ['nullable','date', function ($attr, $value, $fail) use ($request) {
            if ($value && $request->filled('checkIn') && strtotime($value) < strtotime($request->checkIn)) {
                $fail('The check out must be a date after or equal to check in.');
            }
        }],
        'hotel'        => 'nullable|string',

        // occupancy is an ARRAY of rooms when present
        'occupancy'                  => 'nullable|array',
        'occupancy.*.adults'         => 'required_with:occupancy|integer|min:1',
        'occupancy.*.childrenAges'   => 'nullable|array',
        'occupancy.*.childrenAges.*' => 'integer|min:0',
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

    // Exact hotel match (and city) if hotel provided
    $matchedHotelName = null;
    $matchedCityId    = null;
    $matchedCityName  = null;
    $warnings         = [];

    if ($request->exists('hotel')) {
        if ($request->filled('hotel')) {
            $matched = \App\Models\MapHotel::with(['city:id,name'])
                ->where('name', trim($request->hotel)) // exact match
                ->first();

            if ($matched) {
                $matchedHotelName = $matched->name;
                $matchedCityId    = $matched->city->id   ?? null;
                $matchedCityName  = $matched->city->name ?? null;
            } else {
                $warnings['hotel'][] = 'Hotel not found with exact name; hotel/city not changed.';
            }
        } else {
            $warnings['hotel'][] = 'Hotel was null or empty; hotel/city not changed.';
        }
    }

    // Latest record for this phone (if any)
    $existing = \App\Models\RequestBookingRoom::where('phone_number', $request->phone_number)
        ->orderByDesc('id')
        ->first();

    // Occupancy array if provided
    $incomingOccupancy = $request->input('occupancy'); // array|null

    // (Optional) log what PHP actually received for debugging
    Log::channel('whatsapp')->info('saveBookingDetails: occupancy payload', [
        'type'  => gettype($incomingOccupancy),
        'value' => $incomingOccupancy,
    ]);

    try {
        if ($existing) {
            // -------- UPDATE (partial) --------
            if ($request->filled('checkIn'))  $existing->check_in  = $request->checkIn;
            if ($request->filled('checkOut')) $existing->check_out = $request->checkOut;

            if ($request->exists('occupancy')) {
                // allow clear with []
                $existing->occupancy = is_array($incomingOccupancy) ? array_values($incomingOccupancy) : null;
            }

            if ($request->exists('hotel')) {
                if ($matchedHotelName !== null) {
                    $existing->hotel   = $matchedHotelName;
                    $existing->city_id = $matchedCityId;
                    $existing->city    = $matchedCityName;
                }
            }

            $existing->save();

            return response()->json([
                'success'       => true,
                'message'       => 'Booking details updated (upsert).',
                'saved_count'   => 1,
                'saved_ids'     => [$existing->id],
                'warnings'      => $warnings,
                'updated_snapshot' => [
                    'hotel'      => $existing->hotel,
                    'city_id'    => $existing->city_id,
                    'city'       => $existing->city,
                    // these assume you have casts; else use date('Y-m-d', strtotime(...))
                    'check_in'   => optional($existing->check_in)->format('Y-m-d'),
                    'check_out'  => optional($existing->check_out)->format('Y-m-d'),
                    'occupancy'  => $existing->occupancy, // array via casts
                ],
            ]);
        } else {
            // -------- CREATE (upsert) --------
            $row = new \App\Models\RequestBookingRoom();
            $row->phone_number = $request->phone_number;

            if ($request->filled('checkIn'))  $row->check_in  = $request->checkIn;
            if ($request->filled('checkOut')) $row->check_out = $request->checkOut;

            if ($request->exists('occupancy')) {
                $row->occupancy = is_array($incomingOccupancy) ? array_values($incomingOccupancy) : null;
            }

            if ($matchedHotelName !== null) {
                $row->hotel   = $matchedHotelName;
                $row->city_id = $matchedCityId;
                $row->city    = $matchedCityName;
            }

            $row->save();

            return response()->json([
                'success'       => true,
                'message'       => 'Booking created (upsert).',
                'saved_count'   => 1,
                'saved_ids'     => [$row->id],
                'warnings'      => $warnings,
                'updated_snapshot' => [
                    'hotel'      => $row->hotel,
                    'city_id'    => $row->city_id,
                    'city'       => $row->city,
                    'check_in'   => $row->check_in ? date('Y-m-d', strtotime($row->check_in)) : null,
                    'check_out'  => $row->check_out ? date('Y-m-d', strtotime($row->check_out)) : null,
                    'occupancy'  => $row->occupancy,
                ],
            ]);
        }
    } catch (\Exception $e) {
        Log::channel('whatsapp')->error('saveBookingDetails: Upsert failed', [
            'error'   => $e->getMessage(),
            'trace'   => $e->getTraceAsString(),
            'payload' => $request->all(),
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
            'second_name' => 'nullable|string',
            'city'        => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            $missing = [];

            if (isset($errors['first_name'])) {
                $missing[] = "hotel name";
            }
            if (isset($errors['city'])) {
                $missing[] = "city";
            }

            // Combine missing fields into a natural sentence
            $msg = match (count($missing)) {
                1 => "Sorry, I didn’t get the " . $missing[0] . ". Please provide it.",
                2 => "Sorry, I didn’t get the " . implode(" and ", $missing) . ". Please provide them.",
                default => "Some required details are missing. Please provide the hotel name and city."
            };

            return response()->json([
                'success' => false,
                'message' => $msg,
                'errors'  => $errors, // keep for logs/debugging
            ], 422);
        }

        try {
            $hotels = MapHotel::query()
                ->where('name', 'like', '%' . $request->first_name . '%')
                ->when($request->second_name, fn($q) =>
                    $q->where('name', 'like', '%' . $request->second_name . '%')
                )
                ->whereHas('city', fn($q) =>
                    $q->where('name', 'like', '%' . $request->city . '%')
                )
                ->get()
                ->map(fn($h) => [
                    'hotel_name'    => $h->name,
                    'hotel_address' => $h->address,
                ])->values()->toArray();

            if (empty($hotels)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hotels found with the given details.',
                ], 404);
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

        // Fetch latest record for phone_number
        $last = RequestBookingRoom::where('phone_number', $request->phone_number)
            ->orderByDesc('id')
            ->first();

        if (!$last) {
            Log::channel('whatsapp')->warning('getHotelDetails: No booking request found', [
                'phone_number' => $request->phone_number
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No booking request found for this phone number.',
            ], 400);
        }

        $checkIn  = $last->check_in  ? date('Y-m-d', strtotime($last->check_in))  : null;
        $checkOut = $last->check_out ? date('Y-m-d', strtotime($last->check_out)) : null;

        if (!$checkIn || !$checkOut) {
            Log::channel('whatsapp')->warning('getHotelDetails: Check-in or check-out date not found', [
                'last_id' => $last->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Check-in or check-out date not found.',
            ], 400);
        }

        // Determine occupancy data — prefer JSON column, fallback to legacy fields
        $occupancy = [];

        // If you have `$casts = ['occupancy' => 'array']`, it will already be an array.
        if (!is_null($last->occupancy)) {
            if (is_array($last->occupancy)) {
                $occupancy = $last->occupancy;
            } elseif (is_string($last->occupancy) && $last->occupancy !== '') {
                $decoded = json_decode($last->occupancy, true);
                $occupancy = is_array($decoded) ? $decoded : [];
            } else {
                $occupancy = [];
            }
        } else {
            // Fallback to legacy columns if occupancy is null
            $occupancy = [[
                'adults'       => (int) ($last->adults ?? 0),
                'childrenAges' => $last->children_ages ? (json_decode($last->children_ages, true) ?: []) : [],
            ]];
        }


        $response = [
            'success' => true,
            'booking' => [
                'hotel'      => $last->hotel,
                'city_id'    => $last->city_id,
                'check_in'   => $checkIn,
                'check_out'  => $checkOut,
                'occupancy'  => $occupancy, // renamed from booking_request
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

            // TemporaryOffer::where('telephone', $request->telephone)->delete();

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

  public function findAllOffers(Request $request)
    {
        Log::channel('whatsapp')->info('findOffer: Incoming request', ['request' => $request->all()]);

        try {
            // -------- 1) Normalize BEFORE validation --------
            // non_refundable: accept ANY/empty (remove), true/false/yes/no/1/0
            if ($request->exists('non_refundable')) {
                $raw = $request->input('non_refundable');

                if (is_string($raw)) {
                    $v = strtolower(trim($raw));
                    if ($v === 'any' || $v === '') {
                        $request->request->remove('non_refundable');
                        Log::channel('whatsapp')->info('findOffer: normalized non_refundable -> removed (ANY/empty)');
                    } elseif (in_array($v, ['1', 'true', 'yes'], true)) {
                        $request->merge(['non_refundable' => 1]);
                        Log::channel('whatsapp')->info('findOffer: normalized non_refundable -> 1');
                    } elseif (in_array($v, ['0', 'false', 'no'], true)) {
                        $request->merge(['non_refundable' => 0]);
                        Log::channel('whatsapp')->info('findOffer: normalized non_refundable -> 0');
                    }
                }
            }

            // board_basis: if "any" or empty -> remove filter entirely
            if ($request->exists('board_basis')) {
                $bbRaw = $request->input('board_basis');
                if (is_string($bbRaw) && in_array(strtolower(trim($bbRaw)), ['any', ''], true)) {
                    $request->request->remove('board_basis');
                    Log::channel('whatsapp')->info('findOffer: normalized board_basis -> removed (ANY/empty)');
                }
            }

            // price numbers: coerce strings -> numeric
            foreach (['price_min', 'price_max'] as $k) {
                if ($request->filled($k)) {
                    $num = +$request->input($k);
                    $request->merge([$k => $num]);
                    Log::channel('whatsapp')->info("findOffer: normalized {$k}", ['value' => $num]);
                }
            }

            // occupancy: decode if JSON string
            if ($request->exists('occupancy')) {
                $occ = $request->input('occupancy');
                if (is_string($occ)) {
                    $decoded = json_decode($occ, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $request->merge(['occupancy' => $decoded]);
                        Log::channel('whatsapp')->info('findOffer: normalized occupancy from JSON string');
                    }
                }
            }

            // -------- 2) Validate --------
            $request->validate([
                'telephone'      => 'required|string',
                'board_basis'    => 'nullable|string',
                'non_refundable' => 'nullable|boolean',
                'price_min'      => 'nullable|numeric',
                'price_max'      => 'nullable|numeric',
                'occupancy'      => 'nullable|array',
            ]);

            // -------- 3) Load offers for phone --------
            $offers = TemporaryOffer::where('telephone', $request->telephone)->get();

            if ($offers->isEmpty()) {
                Log::channel('whatsapp')->warning('findOffer: No matching TemporaryOffer for telephone', [
                    'telephone' => $request->telephone,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'No matching offer found.',
                ], 404);
            }

            $offerIds = $offers->pluck('id');
            Log::channel('whatsapp')->info('findOffer: Matched TemporaryOffer IDs', ['ids' => $offerIds->values()]);

            // Define the query BEFORE applying any filters
            $roomQuery = OfferedRoom::whereIn('temp_offer_id', $offerIds);

            // -------- 4) Apply filters (only when truly provided) --------

            // board_basis:
            if ($request->exists('board_basis')) {
                $bb = $request->input('board_basis'); // could be null or string
                if ($bb === null) {
                    $roomQuery->whereNull('board_basis');
                    Log::channel('whatsapp')->info('findOffer: filter board_basis -> IS NULL');
                } else {
                    $roomQuery->where('board_basis', 'like', '%' . $bb . '%');
                    Log::channel('whatsapp')->info('findOffer: filter board_basis LIKE', ['value' => $bb]);
                }
            } else {
                Log::channel('whatsapp')->info('findOffer: no board_basis filter applied');
            }

            // non_refundable: apply ONLY if explicitly 0 or 1
            if ($request->exists('non_refundable')) {
                $nr = $request->input('non_refundable');
                if ($nr === 0 || $nr === 1 || $nr === '0' || $nr === '1') {
                    $roomQuery->where('non_refundable', (int)$nr);
                    Log::channel('whatsapp')->info('findOffer: filter non_refundable =', ['value' => (int)$nr]);
                } else {
                    Log::channel('whatsapp')->info('findOffer: non_refundable present but null/invalid -> filter NOT applied');
                }
            }

            // price range:
            if ($request->filled('price_min')) {
                $roomQuery->where('price', '>=', $request->price_min);
                Log::channel('whatsapp')->info('findOffer: filter price >=', ['min' => $request->price_min]);
            }
            if ($request->filled('price_max')) {
                $roomQuery->where('price', '<=', $request->price_max);
                Log::channel('whatsapp')->info('findOffer: filter price <=', ['max' => $request->price_max]);
            }

            // occupancy: LIKE on stored JSON text
            if ($request->exists('occupancy') && is_array($request->occupancy)) {
                $encoded = json_encode($request->occupancy);
                $roomQuery->where('occupancy', 'like', '%' . $encoded . '%');
                Log::channel('whatsapp')->info('findOffer: filter occupancy LIKE', ['needle' => $encoded]);
            }

            // -------- 5) Execute --------
            $rooms = $roomQuery->get();

            if ($rooms->isEmpty()) {
                Log::channel('whatsapp')->warning('findOffer: No matching room(s) after filters', [
                    'telephone'      => $request->telephone,
                    'board_basis'    => $request->exists('board_basis') ? $request->board_basis : '[none]',
                    'non_refundable' => $request->exists('non_refundable') ? $request->non_refundable : '[none]',
                    'price_min'      => $request->filled('price_min') ? $request->price_min : '[none]',
                    'price_max'      => $request->filled('price_max') ? $request->price_max : '[none]',
                    'occupancy'      => $request->exists('occupancy') ? $request->occupancy : '[none]',
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'No matching room(s) found.',
                ], 404);
            }

            // -------- 6) Group and shape response --------
            $groupedOffers = $rooms->groupBy(function ($room) {
                return $room->temporaryOffer->offer_index;
            })->map(function ($group /*, $offerIndex */) {
                return [
                    'room_details' => $group->map(function ($room) {
                        return [
                            'room_name'      => $room->room_name,
                            'board_basis'    => $room->board_basis,
                            'non_refundable' => (bool)$room->non_refundable,
                            'price'          => (float)$room->price,
                            'currency'       => $room->currency ?? 'KWD',
                            'occupancy'      => json_decode($room->occupancy, true) ?: [],
                        ];
                    })->values(),
                ];
            })->values();

            $response = [
                'success' => true,
                'data' => [
                    'telephone' => $request->telephone,
                    'offers'    => $groupedOffers,
                ],
            ];

            Log::channel('whatsapp')->info('findOffer: Success response', [
                'offer_groups'   => $groupedOffers->count(),
                'rooms_returned' => $rooms->count(),
            ]);

            return response()->json($response);

        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('findOffer: Exception occurred', [
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

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
