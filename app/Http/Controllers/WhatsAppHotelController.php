<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use App\Models\TemporaryOffer;
use App\Models\OfferedRoom;
use App\Models\MapHotel;
use App\Models\Prebooking;
use App\Models\HotelBooking;
use App\Models\RequestBookingRoom;
use App\Models\UserStep;
use App\Models\Country;
use App\Models\Agent;
use App\Models\TBO;
use App\Models\TBORoom;
use App\Services\HotelSearchService;
use App\Services\MagicHolidayService;
use App\Services\TBOHolidayService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;


class WhatsAppHotelController extends Controller
{
    protected $logger;

    public function __construct()
    {
        $this->logger = Log::channel('magic_holidays');
    }

    public function getAccessToken(Request $request)
    {
        $request->validate([
            'scopes' => 'required|array',
        ]);

        $magicHolidayService = new MagicHolidayService();

        $accessToken = $magicHolidayService->getAccessToken($request->scopes);

        return response()->json([
            'success' => true,
            'access_token' => $accessToken,
        ]);
    }

    public function saveBookingDetails(Request $request)
    {
        Log::channel('whatsapp')->info('saveBookingDetails: Incoming request', ['request' => $request->all()]);

        // Normalize common "null"/"undefined"/"" to actual null for partials
        foreach (['hotel', 'checkIn', 'checkOut'] as $k) {
            if ($request->has($k)) {
                $v = $request->input($k);
                if (is_string($v) && in_array(strtolower(trim($v)), ['null', 'undefined', ''])) {
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
            'checkOut'     => ['nullable', 'date', function ($attr, $value, $fail) use ($request) {
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

        $existing = RequestBookingRoom::where('phone_number', $request->phone_number)
            ->orderByDesc('id')
            ->first();

        $incomingOccupancy = $request->input('occupancy'); // array|null

        Log::channel('whatsapp')->info('saveBookingDetails: occupancy payload', [
            'type'  => gettype($incomingOccupancy),
            'value' => $incomingOccupancy,
        ]);

        try {
            if ($existing) {

                try {
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
                } catch (Exception $e) {
                    Log::channel('whatsapp')->error('saveBookingDetails: Update failed', [
                        'error'   => $e->getMessage(),
                        'trace'   => $e->getTraceAsString(),
                        'payload' => $request->all(),
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to save booking details.',
                    ], 500);
                }

                Log::channel('whatsapp')->info('saveBookingDetails: Updated existing booking record', $existing->toArray());

                $deletePreviousOffer = $this->deleteOffers(new Request([
                    'telephone' => $request->phone_number,
                ]));

                if ($deletePreviousOffer->getData()->success === false) {
                    Log::channel('whatsapp')->error('saveBookingDetails: Failed to delete previous offers', ['response' => $deletePreviousOffer]);
                    $warnings['offers'][] = 'Failed to delete previous offers.';

                    return response()->json([
                        'success'  => false,
                        'message'  => 'Something went wrong while deleting previous offers.',
                    ]);
                }

                return response()->json([
                    'success'       => true,
                    'message'       => 'You previous offer has been replaced with your new request.',
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

                try {
                    $row = new RequestBookingRoom();
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
                } catch (Exception $e) {
                    Log::channel('whatsapp')->error('saveBookingDetails: Create failed', [
                        'error'   => $e->getMessage(),
                        'trace'   => $e->getTraceAsString(),
                        'payload' => $request->all(),
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to save booking details.',
                    ], 500);
                }

                Log::channel('whatsapp')->info('saveBookingDetails: Created new booking record', $row->toArray());

                $deletePreviousOffer = $this->deleteOffers(new Request([
                    'telephone' => $request->phone_number,
                ]));

                if ($deletePreviousOffer->getData()->success === false) {
                    Log::channel('whatsapp')->error('saveBookingDetails: Failed to delete previous offers', ['response' => $deletePreviousOffer]);
                    $warnings['offers'][] = 'Failed to delete previous offers.';

                    return response()->json([
                        'success'  => false,
                        'message'  => 'Something went wrong while deleting previous offers.',
                    ]);
                }

                Log::channel('whatsapp')->info('saveBookingDetails: Created new booking record', $row->toArray());

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
                ->when(
                    $request->second_name,
                    fn($q) =>
                    $q->where('name', 'like', '%' . $request->second_name . '%')
                )
                ->whereHas(
                    'city',
                    fn($q) =>
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

            $chooseOffer = [];

            $temp = (object)[];
            foreach ($request->offers as $key => $offer) {
                $chooseOffer[$key] = [
                    'telephone' => $request->telephone,
                    'srk' => $request->srk,
                    'hotel_index' => $request->hotel_index,
                    'hotel_name' => $request->hotel_name,
                    'offer_index' => $offer['offer_index'],
                    'result_token' => $request->result_token,
                    'enquiry_id' => $request->enquiry_id,
                ];


                foreach ($offer['room_details'] as $room) {
                    if ($room['price'] > 0) {

                        $tempRoomName = $room['room_name'];
                        $tempRoomBasis = $room['board_basis'];

                        $tempName = $tempRoomName . ' ' . $tempRoomBasis;

                        if (property_exists($temp, $tempName)) {
                            if ($temp->{$tempName}['price'] > $room['price']) {
                                $temp->{$tempName} = [
                                    'room_name' => $room['room_name'],
                                    'board_basis' => $room['board_basis'],
                                    'non_refundable' => $room['non_refundable'],
                                    'info' => $room['info'] ?? '',
                                    'occupancy' => json_encode($room['occupancy'] ?? []),
                                    'price' => $room['price'],
                                    'currency' => $room['currency'] ?? 'KWD',
                                    'room_token' => $room['room_token'],
                                    'package_token' => $room['package_token'],
                                    'offer_index' => $offer['offer_index'],
                                ];
                            }
                        } else {
                            $temp->{$tempName} = [
                                'room_name' => $room['room_name'],
                                'board_basis' => $room['board_basis'],
                                'non_refundable' => $room['non_refundable'],
                                'info' => $room['info'] ?? '',
                                'occupancy' => json_encode($room['occupancy'] ?? []),
                                'price' => $room['price'],
                                'currency' => $room['currency'] ?? 'KWD',
                                'room_token' => $room['room_token'],
                                'package_token' => $room['package_token'],
                                'offer_index' => $offer['offer_index'],
                            ];
                        }
                    }
                }
            }

            Log::channel('whatsapp')->info('Room and basis with lowest price', (array)$temp);

            foreach ($chooseOffer as $key => $chosen) {
                foreach ($temp as $t) {
                    if ($t['offer_index'] == $chosen['offer_index']) {
                        $chooseOffer[$key]['room_details'][] = $t;
                    }
                }
            }

            Log::channel('whatsapp')->info('chooseOffer', $chooseOffer);

            foreach ($chooseOffer as $offer) {

                if (empty($offer['room_details'])) {
                    Log::channel('whatsapp')->warning('storeTemporaryOffer: No room details found for offer', ['offer_index' => $offer['offer_index']]);
                    continue; // Skip offers without room details
                }

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

                if (isset($offer['room_details'])) {
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
                'offers_id' => 'required|array|min:1',
            ]);

            // $offersId = array_filter($request->offers_id, fn($id) => is_string($id) && trim($id) !== '');

            $roomQuery = OfferedRoom::whereIn('id', $request->offers_id)->get();

            if ($roomQuery->count() === 0) {
                Log::channel('whatsapp')->warning('findOffer: No offers found for given offer IDs', ['offers_id' => $request->offers_id]);
                return response()->json([
                    'success' => false,
                    'message' => 'No offers found for the given offer IDs.'
                ], 404);
            }

            $tempOffer = $roomQuery->first()?->temporaryOffer;

            $rooms = $roomQuery;

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
                            'room_token' => $room->room_token,
                            'package_token' => $room->package_token,
                        ];
                    })->values(),
                ];
            })->values();

            $response = [
                'success' => true,
                'data' => [
                    'telephone' => $tempOffer->telephone,
                    'enquiry_id' => $tempOffer->enquiry_id,
                    'srk' => $tempOffer->srk,
                    'hotel_index' => (int) $tempOffer->hotel_index,
                    'hotel_name' => $tempOffer->hotel_name,
                    'result_token' => $tempOffer->result_token,
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

    public function findAllOffers(Request $request)
    {
        // -------------------- 1) Normalize inputs (no logs) --------------------
        // non_refundable: accept ANY/empty (remove), true/false/yes/no/1/0
        if ($request->exists('non_refundable')) {
            $raw = $request->input('non_refundable');
            if (is_string($raw)) {
                $v = strtolower(trim($raw));
                if ($v === 'any' || $v === '') {
                    $request->request->remove('non_refundable'); // do not filter by this
                } elseif (in_array($v, ['1', 'true', 'yes'], true)) {
                    $request->merge(['non_refundable' => 1]);
                } elseif (in_array($v, ['0', 'false', 'no'], true)) {
                    $request->merge(['non_refundable' => 0]);
                }
            }
        }

        // board_basis: if "any" or empty string -> remove filter entirely
        if ($request->exists('board_basis')) {
            $bbRaw = $request->input('board_basis');
            if (is_string($bbRaw) && in_array(strtolower(trim($bbRaw)), ['any', ''], true)) {
                $request->request->remove('board_basis'); // do not filter by board_basis
            }
        }

        // price_min / price_max: coerce strings -> numeric
        foreach (['price_min', 'price_max'] as $k) {
            if ($request->filled($k)) {
                $num = +$request->input($k);
                $request->merge([$k => $num]);
            }
        }

        // occupancy: decode if JSON string
        if ($request->exists('occupancy')) {
            $occ = $request->input('occupancy');
            if (is_string($occ)) {
                $decoded = json_decode($occ, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $request->merge(['occupancy' => $decoded]);
                }
            }
        }

        // -------------------- 2) Validate (custom messages) --------------------
        $validator = Validator::make(
            $request->all(),
            [
                'telephone'      => 'required|string',
                'board_basis'    => 'nullable|string',
                'non_refundable' => 'nullable|boolean',
                'price_min'      => 'nullable|numeric',
                'price_max'      => 'nullable|numeric',
                'occupancy'      => 'nullable|array',
            ],
            [
                'telephone.required'      => 'Please provide a phone number (telephone).',
                'non_refundable.boolean'  => 'non_refundable must be true/false or 1/0.',
                'price_min.numeric'       => 'price_min must be a number.',
                'price_max.numeric'       => 'price_max must be a number.',
                'occupancy.array'         => 'occupancy must be an array.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success'     => false,
                'message'     => 'Validation failed',
                'error_code'  => 'VALIDATION_ERROR',
                'errors'      => $validator->errors(),   // field-level messages
                'applied_filters' => self::summarizeFilters($request),
            ], 422);
        }

        try {
            // -------------------- 3) Load offers for this phone --------------------
            $offers = TemporaryOffer::where('telephone', $request->telephone)->orderBy('id', 'desc')->get();

            if ($offers === null) {
                return response()->json([
                    'success'     => false,
                    'message'     => 'No matching offer batch found for this phone number.',
                    'error_code'  => 'OFFERS_NOT_FOUND',
                    'errors'      => ['telephone' => ['No TemporaryOffer records found for the provided telephone.']],
                    'applied_filters' => self::summarizeFilters($request),
                ], 404);
            }

            $offerIds  = $offers->pluck('id')->toArray();

            $roomQuery = OfferedRoom::whereIn('temp_offer_id', $offerIds);

            // -------------------- 4) Apply filters (only if truly provided) --------------------
            // board_basis
            if ($request->exists('board_basis')) {
                $bb = $request->input('board_basis'); // could be null or string
                if ($bb === null) {
                    $roomQuery->whereNull('board_basis');
                } else {
                    $roomQuery->where('board_basis', 'like', '%' . $bb . '%');
                }
            }

            // non_refundable (only 0 or 1)
            if ($request->exists('non_refundable')) {
                $nr = $request->input('non_refundable'); // 0/1 or null
                if ($nr === 0 || $nr === 1 || $nr === '0' || $nr === '1') {
                    $roomQuery->where('non_refundable', (int)$nr);
                }
            }

            // price range
            if ($request->filled('price_min')) {
                $roomQuery->where('price', '>=', $request->price_min);
            }
            if ($request->filled('price_max')) {
                $roomQuery->where('price', '<=', $request->price_max);
            }

            // occupancy (LIKE match against stored JSON text)
            if ($request->exists('occupancy') && is_array($request->occupancy)) {
                $encoded = json_encode($request->occupancy);
                $roomQuery->where('occupancy', 'like', '%' . $encoded . '%');
            }

            // -------------------- 5) Execute --------------------
            $rooms = $roomQuery->get();

            if ($rooms->isEmpty()) {
                return response()->json([
                    'success'     => false,
                    'message'     => 'No matching room(s) found with the applied filters.',
                    'error_code'  => 'ROOMS_NOT_FOUND',
                    'errors'      => ['filters' => ['No rooms matched the selected criteria.']],
                    'applied_filters' => self::summarizeFilters($request),
                ], 404);
            }

            // -------------------- 6) Group and shape response --------------------
            $grouped = $rooms->groupBy(function ($room) {
                return $room->temporaryOffer->offer_index;
            })->map(function ($group /*, $offerIndex */) {
                return [
                    'room_details' => $group->map(function ($room) {
                        return [
                            'id'             => $room->id,
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

            return response()->json([
                'success' => true,
                'data'    => [
                    'telephone' => $request->telephone,
                    'offers'    => $grouped,
                ],
                'applied_filters' => self::summarizeFilters($request),
            ]);
        } catch (\Throwable $e) {
            // Generic, safe error payload for n8n; include developer_message for debugging
            return response()->json([
                'success'           => false,
                'message'           => 'An unexpected error occurred while searching for offers.',
                'error_code'        => 'INTERNAL_ERROR',
                'developer_message' => $e->getMessage(),   // visible to n8n; hide from end users in chat
                'applied_filters'   => self::summarizeFilters($request),
            ], 500);
        }
    }

    private static function summarizeFilters(Request $request): array
    {
        return [
            'telephone'      => $request->input('telephone'),
            'board_basis'    => $request->exists('board_basis') ? $request->input('board_basis') : '[none]',
            'non_refundable' => $request->exists('non_refundable') ? $request->input('non_refundable') : '[none]',
            'price_min'      => $request->filled('price_min') ? $request->input('price_min') : '[none]',
            'price_max'      => $request->filled('price_max') ? $request->input('price_max') : '[none]',
            'occupancy'      => $request->exists('occupancy') ? $request->input('occupancy') : '[none]',
        ];
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

            if ($cityId === null) {
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
                'phone_number' => 'required|string',
                'prebook_key' => 'required|string',
                'payment_id' => 'nullable|exists:payments,id',
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

            $countryCode = substr($request->phone_number, 0, 3);
            $phone = substr($request->phone_number, 3);

            $client = Client::where('phone', $phone)
                ->where('country_code', $countryCode)
                ->get();

            if ($client->count() > 1) {
                $client = null; // we just make it null to cater to b2b process
            } else {
                $client = $client->first();
            }

            $booking = HotelBooking::create([
                'prebook_id' => $prebook->id,
                'client_id' => $client ? $client->id : null,
                'payment_id' => $request->payment_id,
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
        } catch (Exception $e) {
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

    public function deleteOffers(Request $request)
    {
        Log::channel('whatsapp')->info('deleteOffer: Incoming request', ['request' => $request->all()]);

        $request->validate([
            'telephone' => 'required|string',
        ]);

        try {
            $offers = TemporaryOffer::where('telephone', $request->telephone)->get();

            if ($offers->isEmpty()) {
                Log::channel('whatsapp')->warning('deleteOffer: No temporary offers found', ['telephone' => $request->telephone]);
                return response()->json([
                    'success' => true,
                    'message' => 'No temporary offers found for this telephone number.',
                ], 404);
            }

            foreach ($offers as $offer) {
                OfferedRoom::where('temp_offer_id', $offer->id)->delete();
                $offer->delete();
            }

            Log::channel('whatsapp')->info('deleteOffer: Temporary offers deleted successfully', ['telephone' => $request->telephone]);

            return response()->json([
                'success' => true,
                'message' => 'Temporary offers deleted successfully.',
            ]);
        } catch (Exception $e) {
            Log::channel('whatsapp')->error('deleteOffer: Exception', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting temporary offers.',
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
                $hoursPassed = floor($minutesPassed / 60);
                $minutesRemainder = $minutesPassed % 60;
                $hoursLeft = max(0, 8 - $hoursPassed);
                $minutesLeftInHour = $hoursLeft > 0 ? (60 - $minutesRemainder) % 60 : 0;

                if ($hoursPassed >= 8) {
                    Log::channel('whatsapp')->info('getTemporaryOffersTimeLeft: Offer expired', [
                        'hours_passed' => $hoursPassed,
                        'time_left' => 'expired'
                    ]);
                    return response()->json([
                        'success' => false,
                        'data' => [
                            'hours_passed' => $hoursPassed,
                            'time_left' => 'expired'
                        ],
                        'message' => 'expired'
                    ], 410);
                }

                // Format passed time
                $passedString = '';
                if ($hoursPassed > 0) {
                    $passedString .= $hoursPassed . ' hour' . ($hoursPassed > 1 ? 's' : '');
                }
                if ($minutesRemainder > 0) {
                    if ($passedString) $passedString .= ' ';
                    $passedString .= $minutesRemainder . ' minute' . ($minutesRemainder > 1 ? 's' : '');
                }
                if ($passedString) {
                    $passedString .= ' ago';
                } else {
                    $passedString = 'just now';
                }

                // Format time left
                $leftString = '';
                if ($hoursLeft > 0) {
                    $leftString .= $hoursLeft . ' hour' . ($hoursLeft > 1 ? 's' : '');
                    if ($minutesLeftInHour > 0) {
                        $leftString .= ' ' . $minutesLeftInHour . ' minute' . ($minutesLeftInHour > 1 ? 's' : '');
                    }
                } else {
                    $leftString = $minutesLeftInHour . ' minute' . ($minutesLeftInHour == 1 ? '' : 's');
                }
                $leftString .= ' remaining';
            } else {
                $passedString = 'just now';
                $leftString = '0 minutes remaining';
            }
            $timeLeft = [
                'latest_created_at' => $latestCreatedAt ? $latestCreatedAt->toDateTimeString() : null,
                'time_passed' => $passedString,
                'time_left' => $leftString,
            ];

            Log::channel('whatsapp')->info('getTemporaryOffersTimeLeft: Success response', ['time_left' => $timeLeft]);

            return response()->json([
                'success' => true,
                'data' => $timeLeft,
                'message' => 'Temporary offers are available for 8 hours from the time of creation. You have ' . $leftString . ' since the last offer was created.',
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

    public function hotelBookingDetails($payment)
    {
        Log::info('Started to send out request of Magic Holiday Booking from n8n');

        preg_match('/PB-[A-Za-z0-9]+/', $payment->notes, $match);
        $prebookKey = $match[0] ?? null;

        if (!$prebookKey) {
            Log::info('No Prebook Key found in payment notes');
            return response()->json([
                'success' => false,
                'message' => 'No Prebook Key found in payment notes',
            ], 404);
        }

        $prebookData = Prebooking::where('prebook_key', $prebookKey)->first();

        if (!$prebookData) {
            Log::info('No Prebook data found for this key', ['key' => $prebookKey]);
            return response()->json([
                'success' => false,
                'message' => 'No Prebook data found for this key',
            ], 404);
        }

        Log::info('Prebook data from system database', ['data' => $prebookData]);

        $clientRef = (string) Str::uuid();
        $availabilityToken = $prebookData->availability_token;

        $rooms = $prebookData->rooms;
        $packageRoomToken = $rooms[0]['room_token'];
        $occupancy = $rooms[0]['occupancy'];

        if ($occupancy) {
            $adults = $occupancy['adults'] ?? 0;
            $children = count($occupancy['childrenAges'] ?? []);

            $type = $adults > 0 ? 'adult' : 'child';
            $lead = $adults > 0;

            Log::info('Traveler type & lead info', [
                'type' => $type,
                'lead' => $lead,
            ]);
        }

        $client = Client::where('id', $payment->client_id)->first();

        $sellingPrice = $payment->amount - ($payment->amount * 0.2);

        $payload =
            [
                'clientRef' => $clientRef,
                'availabilityToken' => $availabilityToken,
                'payment' => [
                    'method' => 'paynow',
                    'order' => [
                        'id' => $payment->id,
                    ],
                ],
                'priceModifiers' => [
                    'markup' => [
                        'value' => $payment->amount,
                        'currency' => $payment->currency ?? 'KWD',
                    ],
                    'commision' => [
                        'value' => 0,
                        'currency' => 'KWD',
                    ],
                    'sellingPrice' => [
                        'value' => $sellingPrice,
                        'currency' => $payment->currency ?? 'KWD',
                    ]
                ],
                'rooms' => [
                    [
                        'packageRoomToken' => $packageRoomToken,
                        'travelers' => [
                            [
                                'reference' => $client->id,
                                'type' => $type,
                                'lead' => $lead,
                                'title' => 'mr',
                                'firstName' => $client->first_name,
                                'lastName' => $client->last_name,
                                'email' => $client->email,
                                'phonePrefix' => $client->country_code,
                                'phone' => $client->phone,
                                'identificationNumbers' => [
                                    'fiscalIdentificationNumber' => $client->passport ?? $client->civil_no ?? 'N/A',
                                    'identityNo' => $client->passport ?? $client->civil_no ?? 'N/A',
                                ],
                                'address' => $client->address ?? 'Kuwait',
                                'country' => $client->address ?? 'Kuwait',
                                'city' => $client->address ?? 'Kuwait',
                                'postalCode' => 'N/A',
                            ],
                        ],
                    ],
                ],

                'comments' => '',
                'bosRef' => '',
                'agentRef' => '',
            ];

        Log::info('Final Payload Request', ['payload' => $payload]);

        return response()->json([
            'success' => true,
            'message' => 'Booking payload prepared successfully',
            'payload' => $payload,
        ]);
    }

    public function confirmBooking(Request $request)
    {
        $this->logger->info("B2B Confirm Booking Request", $request->all());

        $request->validate([
            "agent_phone" => "required|string",
            "email" => "nullable|email",
            "client_phone" => "nullable|string",
            "prebookKey" => "required|string",
            "first_name" => "required|string",
            "last_name" => "required|string",
            "payment_method" => "required|string|in:prepaid,credit",
        ]);

        $prebookKey = $request->prebookKey;

        $prebook = Prebooking::where("prebook_key", $prebookKey)->first();

        if (!$prebook) {
            return response()->json([
                "success" => false,
                "message" => "Invalid prebook key."
            ], 404);
        }

        $this->logger->info("Prebook found", ["prebook" => $prebook]);

        $bookingParams = $this->buildB2BBookingParameter($request, $prebookKey);

        if (!$bookingParams["success"]) {
            $this->logger->warning('Booking params preparation failed', [
                'prebook_key' => $prebookKey,
                'error' => $bookingParams['message']
            ]);
            return response()->json([
                "success" => false,
                "message" => $bookingParams["message"]
            ], 400);
        }

        $companyId = app(HotelSearchService::class)->findCompanyIdByPhone($request->agent_phone);
        if (!$companyId) {
            $this->logger->warning('Company ID not found via agent phone', [
                'phone' => $request->phone,
                'prebook_key' => $prebookKey,
            ]);
            return response()->json([
                "success" => false,
                "message" => "Unable to determine company from agent phone."
            ], 400);
        }

        $magic = new MagicHolidayService($companyId);
        $this->logger->info("Attempting booking with company ID resolved from agent phone", [
            'company_id' => $companyId,
            'agent_phone' => $request->agent_phone,
            'prebook_key' => $prebookKey,
        ]);

        $bookingResponse = $magic->storeBooking([
            'srk' => $bookingParams['srk'],
            'hotelId' => $bookingParams['hotelIndex'],
            'offerIndex' => $bookingParams['offerIndex'],
            'resultToken' => $bookingParams['resultToken'],
            'payload' => $bookingParams['payload'],
        ]);

        $this->logger->info("Magic Booking Response", $bookingResponse);

        if (($bookingResponse["status"] ?? null) !== 200) {
            return response()->json([
                "success" => false,
                "message" => "Booking failed with Magic Holiday.",
                "response" => $bookingResponse
            ], 400);
        }

        $reservationId = $bookingResponse["data"]["id"] ?? null;

        $hotelBooking = HotelBooking::create([
            "prebook_id" => $prebook->id,
            "client_id" => null,
            "payment_id" => null,
            "supplier_booking_id" => $reservationId,
            "client_ref" => $bookingParams['payload']['clientRef'],
            "status" => $bookingResponse["data"]["status"] ?? 'confirmed',
            "price" => $bookingResponse["data"]["price"]['selling']["value"] ?? 0,
            "currency" => $bookingResponse["data"]["price"]['selling']["currency"] ?? 'KWD',
            "booking_time" => now(),
        ]);

        $this->logger->info('Magic Holiday hotelBooking successful', [
            'prebook_key' => $prebookKey,
            'hotel_booking_id' => $hotelBooking->id,
            'supplier_booking_id' => $reservationId,
            'response' => $bookingResponse
        ]);

        $documentsResult = $magic->getAllReservationDocumentsWithUrls($reservationId);
        $cleanBooking = array_filter($hotelBooking->toArray(), fn($v) => !is_null($v));

        return response()->json([
            "success" => true,
            "message" => "Booking successfully confirmed.",
            "reservation_id" => $reservationId,
            "documents" => $documentsResult['documents'] ?? [],
            "booking" => $cleanBooking
        ]);
    }

    public function confirmTBOBooking(Request $request)
    {
        Log::channel('whatsapp')->info("TBO Confirm Booking Request", $request->all());

        $request->validate([
            "agent_phone" => "required|string",
            "email" => "nullable|email",
            "client_phone" => "nullable|string",
            "prebookKey" => "required|string",
            "first_name" => "required|string",
            "last_name" => "required|string",
            // "payment_method" => "required|string|in:prepaid,credit",
        ]);

        $prebookKey = $request->prebookKey;

        $prebook = TBO::where("prebook_key", $prebookKey)->first();

        if (!$prebook) {
            return response()->json([
                "success" => false,
                "message" => "Invalid TBO prebook key."
            ], 404);
        }

        Log::channel('whatsapp')->info("TBO Prebook found", ["prebook" => $prebook]);

        $bookingParams = $this->buildTBOBookingParameter($request, $prebookKey);

        if (!$bookingParams["success"]) {
            Log::channel('whatsapp')->warning('TBO booking params preparation failed', [
                'prebook_key' => $prebookKey,
                'error' => $bookingParams['message']
            ]);
            return response()->json([
                "success" => false,
                "message" => $bookingParams["message"]
            ], 400);
        }

        $clientReferenceId = $bookingParams['payload']['ClientReferenceId'];

        $hotelBooking = HotelBooking::create([
            "prebook_id" => null,
            "client_id" => null,
            "payment_id" => null,
            "supplier_booking_id" => null,
            "client_ref" => $clientReferenceId,
            "status" => 'pending',
            "price" => $prebook->total_fare,
            "currency" => $prebook->currency,
            "booking_time" => now(),
        ]);

        $prebook->update(['hotel_booking_id' => $hotelBooking->id]);

        Log::channel('whatsapp')->info("HotelBooking created with pending status and linked to TBO prebook", [
            'hotel_booking_id' => $hotelBooking->id,
            'tbo_id' => $prebook->id,
            'prebook_key' => $prebookKey,
            'status' => 'pending'
        ]);

        $tboService = new TBOHolidayService();
        
        Log::channel('whatsapp')->info("Attempting TBO booking", [
            'prebook_key' => $prebookKey,
            'booking_code' => $prebook->booking_code,
            'hotel_booking_id' => $hotelBooking->id,
        ]);

        $bookingResponse = $tboService->book($bookingParams['payload']);

        Log::channel('whatsapp')->info("TBO Booking Response", $bookingResponse);

        if (($bookingResponse["Status"]["Code"] ?? null) !== 200) {
            $hotelBooking->update(['status' => 'failed']);

            $prebook->update([
                'payment_status' => 'unpaid',
                'supplier_status' => 'failed'
            ]);

            Log::channel('whatsapp')->error('TBO booking failed', [
                'prebook_key' => $prebookKey,
                'hotel_booking_id' => $hotelBooking->id,
                'error' => $bookingResponse["Status"]["Description"] ?? 'Unknown error'
            ]);

            return response()->json([
                "success" => false,
                "message" => "Booking failed with TBO.",
                "hotel_booking_id" => $hotelBooking->id,
                "response" => $bookingResponse
            ], 400);
        }

        $confirmationNo = $bookingResponse["ConfirmationNumber"] ?? null;
        $bookingReferenceId = $bookingResponse["ClientReferenceId"] ?? null;
        $bookingStatus = 'confirmed';

        $hotelBooking->update([
            "supplier_booking_id" => $confirmationNo,
            "status" => $bookingStatus,
        ]);

        $prebook->update([
            'confirmation_no' => $confirmationNo,
            'booking_reference_id' => $bookingReferenceId,
            'payment_status' => 'pending',
            'supplier_status' => $bookingStatus,
        ]);

        Log::channel('whatsapp')->info('TBO booking successful', [
            'prebook_key' => $prebookKey,
            'hotel_booking_id' => $hotelBooking->id,
            'confirmation_no' => $confirmationNo,
            'supplier_status' => $bookingStatus,
            'response' => $bookingResponse
        ]);

        $detailedBookingResponse = null;
        try {
            $bookingDetailResponse = $tboService->getBookingDetail([
                'ConfirmationNumber' => $confirmationNo
            ]);

            if (isset($bookingDetailResponse['Status']['Code']) && $bookingDetailResponse['Status']['Code'] === 200) {
                $detailedBookingResponse = $bookingDetailResponse['BookingDetail'] ?? null;
                
                Log::channel('whatsapp')->info('TBO BookingDetail API response', [
                    'confirmation_no' => $confirmationNo,
                    'response' => $detailedBookingResponse
                ]);
            } else {
                Log::channel('whatsapp')->warning('TBO BookingDetail API failed', [
                    'confirmation_no' => $confirmationNo,
                    'error' => $bookingDetailResponse['Status']['Description'] ?? 'Unknown error'
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Failed to fetch booking details from TBO', [
                'confirmation_no' => $confirmationNo,
                'error' => $e->getMessage()
            ]);
        }

        $bookingDetail = $detailedBookingResponse ?? $bookingResponse["BookingDetail"] ?? [];
        $hotelDetails = $bookingDetail["Hotel"] ?? [];
        
        return response()->json([
            "success" => true,
            "message" => "TBO booking successfully confirmed.",
            "booking_details" => [
                "confirmation_no" => $confirmationNo,
                "booking_reference_id" => $bookingReferenceId,
                "booking_status" => $bookingStatus,
                "hotel_booking_id" => $hotelBooking->id,
                "payment_status" => $prebook->payment_status,
                "supplier_status" => $prebook->supplier_status,
                "prebook_key" => $prebookKey,
                "hotel" => [
                    "hotel_code" => $prebook->hotel_code,
                    "hotel_name" => $prebook->hotel_name,
                    "check_in" => $hotelDetails["CheckInDate"] ?? null,
                    "check_out" => $hotelDetails["CheckOutDate"] ?? null,
                ],
                "pricing" => [
                    "total_fare" => $prebook->total_fare,
                    "total_tax" => $prebook->total_tax,
                    "currency" => $prebook->currency,
                ],
                "room_details" => [
                    "room_name" => json_decode($prebook->room_name, true),
                    "meal_type" => $prebook->meal_type,
                    "is_refundable" => $prebook->is_refundable,
                    "room_quantity" => $prebook->room_quantity,
                ],
                "booking_time" => $hotelBooking->booking_time,
            ],
            "tbo_response" => $bookingDetail
        ]);
    }

    private function buildB2BBookingParameter(Request $request, string $prebookKey): array
    {
        $this->logger->info("Building B2B booking payload", ["prebookKey" => $prebookKey]);
        $prebook = Prebooking::where("prebook_key", $prebookKey)->first();

        if (!$prebook) {
            $this->logger->info('No Prebook data found for this key', ['prebook_key' => $prebookKey]);
            return [
                "success" => false,
                "message" => "Prebook not found."
            ];
        }

        $this->logger->info('Prebook data from system database', ['data' => $prebook]);

        $srk = $prebook->srk;
        $hotelIndex = $prebook->hotel_id;
        $offerIndex = $prebook->offer_index;
        $resultToken = $prebook->result_token;
        $availabilityToken = $prebook->availability_token;
        $package = is_string($prebook->package) ? json_decode($prebook->package, true) : $prebook->package;
        $packageRooms = $package["packageRooms"] ?? [];
        $rooms = $prebook->rooms ?? [];

        if (empty($rooms)) {
            $this->logger->error('No rooms found in prebook data', ['prebook_key' => $prebookKey]);
            return [
                "success" => false,
                "message" => "No rooms found in prebook data."
            ];
        }

        $roomsPayload = [];
        foreach ($rooms as $index => $room) {
            $roomToken = $room['room_token'] ?? null;
            $occupancy = $room['occupancy'] ?? ($packageRooms[$index]['occupancy'] ?? []);

            if (!$roomToken) {
                $this->logger->warning('Room token missing for room index', ['index' => $index]);
                continue;
            }

            if (!$occupancy) {
                $this->logger->warning('Occupancy data missing for room index', ['index' => $index]);
                continue;
            }

            $adults = $occupancy['adults'];
            $childrenAges = $occupancy['childrenAges'] ?? [];

            $this->logger->info('Building room payload', [
                'room_index' => $index,
                'room_token' => $roomToken,
                'adults' => $adults,
                'children' => count($childrenAges),
                'children_ages' => $childrenAges,
            ]);

            $travelers = [];
            $countryCode = null;
            $phone = null;

            if (!empty($request->client_phone)) {
                $codes = Country::pluck('dialing_code')->toArray();
                usort($codes, fn($a, $b) => strlen($b) <=> strlen($a));

                $countryCode = '+000';
                $phone = $request->client_phone;
                foreach ($codes as $code) {
                    if (str_starts_with($request->client_phone, $code)) {
                        $countryCode = $code;
                        $phone = substr($request->client_phone, strlen($code));
                        break;
                    }
                }
            }

            $travelers[] = [
                "reference" => "lead-1",
                "type" => "adult",
                "lead" => true,
                "title" => "mr",
                'firstName' => $request->first_name,
                'lastName' => $request->last_name,
                "email" => null,
                "phonePrefix" => $countryCode,
                "phone" => $phone,
            ];

            for ($i = 1; $i < $adults; $i++) {
                $travelers[] = [
                    "reference" => "adult-" . ($i + 1),
                    "type" => "adult",
                    "lead" => false,
                    "title" => "mr",
                    'firstName' => $request->first_name,
                    'lastName' => $request->last_name,
                    "email" => null,
                    "phonePrefix" => $countryCode,
                    "phone" => $phone,
                ];
            }

            foreach ($childrenAges as $childIndex => $age) {
                $birthDate = $prebook->checkin->copy()->subYears($age)->format('Y-m-d');

                $travelers[] = [
                    "reference" => "child-" . ($childIndex + 1),
                    "type" => "child",
                    "lead" => false,
                    "title" => "mstr",
                    'firstName' => $request->first_name . ' Child ' . ($childIndex + 1),
                    'lastName' => $request->last_name,
                    "birthDate" => $birthDate,
                ];
            }

            $roomsPayload[] = [
                "packageRoomToken" => $roomToken,
                "travelers" => $travelers
            ];

            $this->logger->info('Room payload built', [
                'room_index' => $index,
                'total_travelers' => count($travelers),
                'adult_count' => $adults,
                'child_count' => count($childrenAges),
            ]);
        }

        if (empty($roomsPayload)) {
            $this->logger->error('Failed to build any room payloads', ['prebook_key' => $prebookKey]);
            return [
                'success' => false,
                'message' => 'Failed to build room payloads',
            ];
        }

        $agent = Agent::where('phone_number', $request->agent_phone)->first();
        $agentInfo = [
            'name' => $agent->name ?? 'Unknown Agent',
            'email' => $agent->email ?? null,
            'phone' => $request->agent_phone,
        ];
        $this->logger->info("Agent resolved", $agentInfo);

        $payload = [
            "clientRef" => $prebookKey,
            "availabilityToken" => $availabilityToken,
            "payment" => [
                "method" => $request->payment_method,
            ],
            "rooms" => $roomsPayload,
            "comments" => "Booking via B2B API",
            "bosRef" => null,
            "agentRef" => 'Booking by ' . $request->agent_phone,
        ];

        $this->logger->info('Final Payload Request', [
            'prebook_key' => $prebookKey,
            'total_rooms' => count($roomsPayload),
            'payload' => $payload
        ]);

        return [
            "success" => true,
            'message' => 'Booking payload prepared successfully',
            "srk" => $srk,
            "hotelIndex" => $hotelIndex,
            "offerIndex" => $offerIndex,
            "resultToken" => $resultToken,
            "payload" => $payload,
        ];
    }

    private function buildTBOBookingParameter(Request $request, string $prebookKey): array
    {
        Log::channel('whatsapp')->info("Building TBO booking payload", ["prebookKey" => $prebookKey]);
        
        $prebook = TBO::with('rooms')->where("prebook_key", $prebookKey)->first();

        if (!$prebook) {
            Log::channel('whatsapp')->info('No TBO Prebook data found for this key', ['prebook_key' => $prebookKey]);
            return [
                "success" => false,
                "message" => "TBO prebook not found."
            ];
        }

        Log::channel('whatsapp')->info('TBO Prebook data from database', ['data' => $prebook]);

        $rooms = $prebook->rooms;

        if ($rooms->isEmpty()) {
            Log::channel('whatsapp')->error('No rooms found in TBO prebook data', ['prebook_key' => $prebookKey]);
            return [
                "success" => false,
                "message" => "No rooms found in TBO prebook data."
            ];
        }

        // Build CustomerDetails array
        $customerDetails = [];
        foreach ($rooms as $roomIndex => $room) {
            $customers = [];
            
            // Add adults
            for ($i = 0; $i < $room->adult_quantity; $i++) {
                $customers[] = [
                    'FirstName' => $request->first_name,
                    'LastName' => $request->last_name,
                    'Title' => 'Mr', // Default title
                    'Type' => 'Adult'
                ];
            }

            // Add children if any
            for ($i = 0; $i < $room->child_quantity; $i++) {
                $customers[] = [
                    'FirstName' => 'Child' . ($i + 1),
                    'LastName' => $request->last_name,
                    'Title' => 'Mstr', // Default title for children
                    'Type' => 'Child'
                ];
            }

            $customerDetails[] = [
                'CustomerNames' => $customers
            ];
        }

        // Generate unique client reference ID
        $clientReferenceId = $prebookKey . '-' . time();

        $payload = [
            'BookingCode' => $prebook->booking_code,
            'CustomerDetails' => $customerDetails,
            'ClientReferenceId' => $clientReferenceId,
            'BookingReferenceId' => $prebookKey,
            'TotalFare' => (float)$prebook->total_fare,
            'EmailId' => $request->email ?? 'noreply@example.com',
            'PhoneNumber' => $request->client_phone ?? $request->agent_phone,
            'PaymentMode' => 'Limit', // Default to Limit (prepaid/credit)
            'PaymentInfo' => [
                'CvvNumber' => '', // Empty as we're using Limit payment
            ]
        ];

        Log::channel('whatsapp')->info('Final TBO Payload Request', [
            'prebook_key' => $prebookKey,
            'booking_code' => $prebook->booking_code,
            'total_rooms' => count($customerDetails),
            'payload' => $payload
        ]);

        return [
            "success" => true,
            'message' => 'TBO booking payload prepared successfully',
            "payload" => $payload,
        ];
    }
}
