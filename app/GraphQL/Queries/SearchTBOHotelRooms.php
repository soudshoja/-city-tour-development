<?php

namespace App\GraphQL\Queries;

use App\Services\TBOHolidayService;
use App\Models\TBO;
use App\Models\TBORoom;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class SearchTBOHotelRooms
{
    protected $tboService;
    protected $logger;

    public function __construct()
    {
        $this->tboService = new TBOHolidayService();
        $this->logger = Log::channel('tbo');
    }

    public function __invoke($_, array $args)
    {
        $input = $args['input'];

        $validator = Validator::make($input, [
            'telephone' => 'required|string',
            'hotelCode' => 'required_without:hotel|integer',
            'hotel' => 'required_without:hotelCode|string',
            'city' => 'required_with:hotel|string',
            'guestNationality' => 'required|string|size:2',
            'checkIn' => 'required|date|after_or_equal:today',
            'checkOut' => 'required|date|after:checkIn',
            'occupancy' => 'required',
            'noOfRooms' => 'nullable|integer|min:1',
            'refundable' => 'nullable|boolean',
            'mealType' => 'nullable|string|in:All,WithMeal,RoomOnly',
            'priceMin' => 'nullable|numeric|min:0',
            'priceMax' => 'nullable|numeric|min:0',
        ], [
            'telephone.required' => 'Telephone number is required.',
            'hotelCode.required_without' => 'Hotel code or hotel name is required.',
            'hotel.required_without' => 'Hotel name or hotel code is required.',
            'city.required_with' => 'City name is required when searching by hotel name.',
            'guestNationality.required' => 'Guest nationality is required.',
            'guestNationality.size' => 'Guest nationality must be a 2-letter country code.',
            'checkIn.required' => 'Check-in date is required.',
            'checkIn.after_or_equal' => 'Check-in date must be today or later.',
            'checkOut.required' => 'Check-out date is required.',
            'checkOut.after' => 'Check-out date must be after check-in date.',
            'occupancy.required' => 'Occupancy is required.',
            'mealType.in' => 'Meal type must be one of: All, WithMeal, RoomOnly.',
        ]);

        $this->logger->info('TBO hotel search input', ['input' => $input]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'status' => 'validation_error',
                'message' => 'Validation failed: ' . $validator->errors()->first(),
                'data' => null,
                'hotelOptions' => null,
            ];
        }

        try {
            // Get hotel code - either from input or search by name
            $hotelCode = $input['hotelCode'] ?? null;
            
            if (!$hotelCode) {
                $findCodeResponse = $this->tboService->findHotelCodeByName(
                    $input['hotel'],
                    $input['city'] ?? null
                );

                // Handle multiple hotels found
                if ($findCodeResponse['status'] === 'multiple_hotels_found') {
                    return [
                        'success' => false,
                        'status' => 'multiple_hotels_found',
                        'message' => $findCodeResponse['message'],
                        'data' => null,
                        'hotelOptions' => $findCodeResponse['data']
                    ];
                }

                // Handle city required
                if ($findCodeResponse['status'] === 'city_required') {
                    return [
                        'success' => false,
                        'status' => 'city_required',
                        'message' => $findCodeResponse['message'],
                        'data' => null,
                        'hotelOptions' => null
                    ];
                }

                // Handle city not found
                if ($findCodeResponse['status'] === 'city_not_found') {
                    return [
                        'success' => false,
                        'status' => 'city_not_found',
                        'message' => $findCodeResponse['message'],
                        'data' => null,
                        'hotelOptions' => null
                    ];
                }

                // Handle no hotels in city
                if ($findCodeResponse['status'] === 'no_hotels_in_city') {
                    return [
                        'success' => false,
                        'status' => 'no_hotels_in_city',
                        'message' => $findCodeResponse['message'],
                        'data' => null,
                        'hotelOptions' => null
                    ];
                }

                // Handle hotel not found
                if ($findCodeResponse['status'] === 'hotel_not_found') {
                    return [
                        'success' => false,
                        'status' => 'hotel_not_found',
                        'message' => $findCodeResponse['message'],
                        'data' => null,
                        'hotelOptions' => null
                    ];
                }

                // Handle hotel found
                if ($findCodeResponse['status'] === 'hotel_found') {
                    $hotelCode = $findCodeResponse['data'];
                    
                    $this->logger->info('Found hotel code for hotel name', [
                        'hotel_name' => $input['hotel'],
                        'city' => $input['city'] ?? null,
                        'hotel_code' => $hotelCode
                    ]);
                } else {
                    // Unexpected status
                    $this->logger->error('Unexpected status from findHotelCodeByName', [
                        'hotel_name' => $input['hotel'],
                        'city' => $input['city'] ?? null,
                        'response' => $findCodeResponse
                    ]);

                    return [
                        'success' => false,
                        'status' => $findCodeResponse['status'] ?? 'error',
                        'message' => $findCodeResponse['message'] ?? 'Unable to retrieve hotel code.',
                        'data' => null,
                        'hotelOptions' => null
                    ];
                }
            }

            // Parse occupancy to rooms array
            $rooms = $this->parseOccupancy($input['occupancy']);

            $result = $this->searchTBOHotelRooms(
                $hotelCode,
                $input['guestNationality'],
                $input['checkIn'],
                $input['checkOut'],
                $rooms,
                $input['noOfRooms'] ?? null,
                $input['refundable'] ?? null,
                $input['mealType'] ?? 'All',
                $input['priceMin'] ?? null,
                $input['priceMax'] ?? null
            );

            return $result;
        } catch (Exception $e) {
            $this->logger->error('TBO hotel search failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'status' => 'error',
                'message' => 'Hotel search failed: ' . $e->getMessage(),
                'data' => null,
                'hotelOptions' => null,
            ];
        }
    }

    /**
     * Parse occupancy data from either string (Magic Holiday format) or array (TBO format)
     *
     * @param mixed $occupancy
     * @return array
     */
    protected function parseOccupancy($occupancy): array
    {
        // If occupancy is already an array of rooms
        if (is_array($occupancy) && isset($occupancy[0]) && is_array($occupancy[0])) {
            // Convert new format [{'adults':1,'childrenAges':[]}] to TBO format
            return array_map(function($room) {
                $childrenAges = $room['childrenAges'] ?? $room['childAges'] ?? [];
                return [
                    'adults' => $room['adults'],
                    'children' => count($childrenAges), // Infer children count from ages array
                    'childAges' => $childrenAges
                ];
            }, $occupancy);
        }

        // If occupancy has 'rooms' key with string value (Magic Holiday format)
        if (is_array($occupancy) && isset($occupancy['rooms']) && is_string($occupancy['rooms'])) {
            return $this->parseRoomsString($occupancy['rooms']);
        }

        // If occupancy is a string directly
        if (is_string($occupancy)) {
            // Try to decode JSON format first: "[{'adults':1,'childrenAges':[]}]"
            $decoded = json_decode(str_replace("'", '"', $occupancy), true);
            if (is_array($decoded) && isset($decoded[0]) && is_array($decoded[0])) {
                // Parse the decoded array using the array logic
                return array_map(function($room) {
                    $childrenAges = $room['childrenAges'] ?? $room['childAges'] ?? [];
                    return [
                        'adults' => $room['adults'],
                        'children' => count($childrenAges),
                        'childAges' => $childrenAges
                    ];
                }, $decoded);
            }
            
            // Otherwise, use old format: "2,1|1,0"
            return $this->parseRoomsString($occupancy);
        }

        // Default: try to use as TBO format
        return $occupancy;
    }

    /**
     * Parse Magic Holiday rooms string format (e.g., "2,1|1,0")
     * Format: adults,children|adults,children
     *
     * @param string $roomsString
     * @return array
     */
    protected function parseRoomsString(string $roomsString): array
    {
        $rooms = [];
        $roomsData = explode('|', $roomsString);

        foreach ($roomsData as $roomData) {
            $parts = explode(',', $roomData);
            $adults = (int)($parts[0] ?? 1);
            $children = (int)($parts[1] ?? 0);

            $rooms[] = [
                'adults' => $adults,
                'children' => $children,
                'childAges' => []
            ];
        }

        return $rooms;
    }

    protected function searchTBOHotelRooms(
        int $hotelCode,
        string $guestNationality,
        string $checkIn,
        string $checkOut,
        array $rooms,
        ?int $noOfRooms = null,
        ?bool $refundable = null,
        string $mealType = 'All',
        ?float $priceMin = null,
        ?float $priceMax = null
    ): array {
        $hasPriceFilter = ($priceMin !== null || $priceMax !== null);
        
        $this->logger->info('Starting TBO hotel search', [
            'hotel_code' => $hotelCode,
            'guest_nationality' => $guestNationality,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'rooms' => $rooms,
            'filters' => [
                'noOfRooms' => $noOfRooms,
                'refundable' => $refundable,
                'mealType' => $mealType,
                'priceMin' => $priceMin,
                'priceMax' => $priceMax,
                'hasPriceFilter' => $hasPriceFilter,
            ],
        ]);

        $paxRooms = array_map(function ($room) {
            return [
                'Adults' => (string)$room['adults'],
                'Children' => $room['children'],
                'ChildrenAges' => $room['childAges'] ?? [],
            ];
        }, $rooms);

        $filters = [];
        
        if (!$hasPriceFilter) {
            $filters['NoOfRooms'] = $noOfRooms ?? 1;
        }
        
        if ($refundable !== null) {
            $filters['Refundable'] = $refundable;

            if(app()->environment() !== 'production'){
                $filters['Refundable'] = true;
            }
        }
        
        if ($mealType !== null) {
            $filters['MealType'] = $mealType;
        }

        $searchData = [
            'CheckIn' => $checkIn,
            'CheckOut' => $checkOut,
            'HotelCodes' => $hotelCode,
            'GuestNationality' => $guestNationality,
            'PaxRooms' => $paxRooms,
            'ResponseTime' => 23,
            'IsDetailedResponse' => false,
            'Filters' => $filters
        ];

        $response = $this->tboService->search($searchData);

        if (!isset($response['Status']) || $response['Status']['Code'] !== 200) {
            $errorMessage = $response['Status']['Description'] ?? 'Unknown error from TBO API';
            throw new Exception($errorMessage);
        }

        if (!isset($response['HotelResult']) || empty($response['HotelResult'])) {
            return [
                'success' => false,
                'status' => 'no_results',
                'message' => 'No hotels found for the specified criteria.',
                'data' => null,
                'hotelOptions' => null,
            ];
        }

        $hotelResults = $response['HotelResult'];
        $allRooms = [];

        foreach ($hotelResults as $hotel) {
            if (!isset($hotel['Rooms']) || empty($hotel['Rooms'])) {
                continue;
            }

            foreach ($hotel['Rooms'] as $room) {
                $allRooms[] = [
                    'hotel' => $hotel,
                    'room' => $room,
                    'total_fare' => $room['TotalFare'] ?? 0,
                ];
            }
        }

        if (empty($allRooms)) {
            return [
                'success' => false,
                'status' => 'no_rooms',
                'message' => 'No available rooms found.',
                'data' => null,
                'hotelOptions' => null,
            ];
        }

        if ($hasPriceFilter) {
            $allRooms = array_filter($allRooms, function($roomData) use ($priceMin, $priceMax) {
                $price = $roomData['total_fare'];
                if ($priceMin !== null && $price < $priceMin) {
                    return false;
                }
                if ($priceMax !== null && $price > $priceMax) {
                    return false;
                }
                return true;
            });

            if (empty($allRooms)) {
                return [
                    'success' => false,
                    'status' => 'no_rooms_in_price_range',
                    'message' => 'No rooms found within the specified price range.',
                    'data' => null,
                    'hotelOptions' => null,
                ];
            }
        }

        usort($allRooms, fn($a, $b) => $a['total_fare'] <=> $b['total_fare']);

        $roomsToPrebook = array_slice($allRooms, 0, $noOfRooms ?? 1);
        
        // Get hotel details once
        $firstRoom = $roomsToPrebook[0];
        $hotel = $firstRoom['hotel'];
        $hotelDetails = $this->tboService->getHotelDetails($hotel['HotelCode']);
        $hotelName = $hotelDetails['HotelDetails']['HotelName'] ?? 'Hotel ' . $hotel['HotelCode'];

        // Prebook all selected rooms
        $prebookedRooms = [];
        
        foreach ($roomsToPrebook as $roomData) {
            $room = $roomData['room'];
            
            $prebookResponse = $this->tboService->preBook($room['BookingCode']);

            if (!isset($prebookResponse['Status']) || $prebookResponse['Status']['Code'] !== 200) {
                $this->logger->warning('Prebook failed for one room', [
                    'booking_code' => $room['BookingCode'],
                    'error' => $prebookResponse['Status']['Description'] ?? 'Unknown error'
                ]);
                continue; // Skip this room and continue with others
            }

            $prebookHotel = $prebookResponse['HotelResult'][0] ?? null;
            $prebookRoom = $prebookHotel['Rooms'][0] ?? null;

            if (!$prebookRoom) {
                $this->logger->warning('Prebook response missing room details', [
                    'booking_code' => $room['BookingCode']
                ]);
                continue;
            }

            // Generate unique prebook key (same format as Magic Holiday)
            $prebookKey = 'TBO-' . strtoupper(substr(uniqid(), -8));

            $this->logger->info('Generated prebook key for TBO', [
                'prebook_key' => $prebookKey,
                'booking_code' => $prebookRoom['BookingCode'],
                'hotel_code' => $hotel['HotelCode']
            ]);

            $tboPrebook = TBO::create([
                'prebook_key' => $prebookKey,
                'booking_code' => $prebookRoom['BookingCode'],
                'hotel_code' => $hotel['HotelCode'],
                'hotel_name' => $hotelName,
                'room_quantity' => count($rooms),
                'room_name' => json_encode($prebookRoom['Name']),
                'currency' => $hotel['Currency'],
                'inclusion' => $prebookRoom['Inclusion'] ?? '',
                'day_rates' => 'day rates',
                'total_fare' => $prebookRoom['TotalFare'],
                'total_tax' => $prebookRoom['TotalTax'],
                'extra_guest_charges' => $prebookRoom['ExtraGuestCharges'] ?? '0',
                'room_promotion' => json_encode($prebookRoom['RoomPromotion'] ?? []),
                'meal_type' => $prebookRoom['MealType'] ?? '',
                'is_refundable' => $prebookRoom['IsRefundable'] ?? false,
                'with_transfer' => $prebookRoom['WithTransfer'] ?? false,
            ]);

            foreach ($rooms as $index => $roomInput) {
                TBORoom::create([
                    'tbo_id' => $tboPrebook->id,
                    'room_name' => $prebookRoom['Name'][$index] ?? 'Room ' . ($index + 1),
                    'adult_quantity' => $roomInput['adults'],
                    'child_quantity' => $roomInput['children'],
                ]);
            }

            $roomDetails = array_map(function ($roomInput, $index) use ($prebookRoom) {
                return [
                    'room_name' => $prebookRoom['Name'][$index] ?? 'Room ' . ($index + 1),
                    'board_basis' => $prebookRoom['MealType'] ?? '',
                    'non_refundable' => !($prebookRoom['IsRefundable'] ?? false),
                    'price' => $prebookRoom['TotalFare'] / count($prebookRoom['Name']),
                    'currency' => $prebookRoom['Currency'] ?? 'USD',
                    'info' => $prebookRoom['Inclusion'] ?? null,
                    'occupancy' => [
                        'adults' => $roomInput['adults'],
                        'children' => $roomInput['children'],
                        'childAges' => $roomInput['childAges'] ?? [],
                    ],
                ];
            }, $rooms, array_keys($rooms));

            $prebookedRooms[] = [
                'success' => true,
                'error' => null,
                'room' => $roomDetails,
                'prebook' => [
                    'prebookKey' => $prebookKey,
                    'tboId' => $tboPrebook->id,
                    'bookingCode' => $prebookRoom['BookingCode'],
                    'serviceDates' => [
                        'checkIn' => $checkIn,
                        'checkOut' => $checkOut,
                    ],
                    'package' => [
                        'price' => [
                            'selling' => [
                                'value' => $prebookRoom['TotalFare'],
                                'currency' => $hotel['Currency'],
                            ],
                        ],
                    ],
                    'totalFare' => $prebookRoom['TotalFare'],
                    'totalTax' => $prebookRoom['TotalTax'],
                    'currency' => $hotel['Currency'],
                    'mealType' => $prebookRoom['MealType'] ?? '',
                    'isRefundable' => $prebookRoom['IsRefundable'] ?? false,
                ],
            ];
        }

        if (empty($prebookedRooms)) {
            return [
                'success' => false,
                'status' => 'prebook_failed',
                'message' => 'All prebook attempts failed.',
                'data' => null,
                'hotelOptions' => null,
            ];
        }

        return [
            'success' => true,
            'status' => 'hotel_found',
            'message' => 'TBO hotel search completed successfully.',
            'data' => [
                'hotel_code' => $hotel['HotelCode'],
                'hotel_name' => $hotelName,
                'room_count' => count($prebookedRooms),
                'rooms' => $prebookedRooms,
            ],
        ];
    }
}
