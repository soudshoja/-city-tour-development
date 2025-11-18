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
            'hotelCode' => 'required|integer',
            'guestNationality' => 'required|string',
            'checkIn' => 'required|date|after_or_equal:today',
            'checkOut' => 'required|date|after:checkIn',
            'rooms' => 'required|array|min:1',
            'rooms.*.adults' => 'required|integer|min:1',
            'rooms.*.children' => 'required|integer|min:0',
            'rooms.*.childAges' => 'array',
        ], [
            'hotelCode.required' => 'Hotel code is required.',
            'guestNationality.required' => 'Guest nationality is required.',
            'checkIn.required' => 'Check-in date is required.',
            'checkIn.after_or_equal' => 'Check-in date must be today or later.',
            'checkOut.required' => 'Check-out date is required.',
            'checkOut.after' => 'Check-out date must be after check-in date.',
            'rooms.required' => 'Room details are required.',
            'rooms.*.adults.required' => 'Number of adults per room is required.',
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Validation failed: ' . $validator->errors()->first(),
                'data' => null,
            ];
        }

        try {
            $result = $this->searchTBOHotelRooms(
                $input['hotelCode'],
                $input['guestNationality'],
                $input['checkIn'],
                $input['checkOut'],
                $input['rooms']
            );

            return $result;
        } catch (Exception $e) {
            $this->logger->error('TBO hotel search failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Hotel search failed: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    protected function searchTBOHotelRooms(
        int $hotelCode,
        string $guestNationality,
        string $checkIn,
        string $checkOut,
        array $rooms
    ): array {
        $this->logger->info('Starting TBO hotel search', [
            'hotel_code' => $hotelCode,
            'guest_nationality' => $guestNationality,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'rooms' => $rooms,
        ]);

        $paxRooms = array_map(function ($room) {
            return [
                'adults' => (string)$room['adults'],
                'children' => $room['children'],
                'childrenAges' => $room['childAges'] ?? [],
            ];
        }, $rooms);

        $searchData = [
            'CheckIn' => $checkIn,
            'CheckOut' => $checkOut,
            'HotelCodes' => $hotelCode,
            'GuestNationality' => $guestNationality,
            'PaxRooms' => $paxRooms,
            'ResponseTime' => 23,
            'IsDetailedResponse' => false,
            'Filters' => [
                'Refundable' => false,
                'NoOfRooms' => count($rooms),
                'MealType' => 'All',
            ]
        ];

        $response = $this->tboService->search($searchData);

        if (!isset($response['Status']) || $response['Status']['Code'] !== 200) {
            $errorMessage = $response['Status']['Description'] ?? 'Unknown error from TBO API';
            throw new Exception($errorMessage);
        }

        if (!isset($response['HotelResult']) || empty($response['HotelResult'])) {
            return [
                'success' => false,
                'message' => 'No hotels found for the specified criteria.',
                'data' => null,
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
                'message' => 'No available rooms found.',
                'data' => null,
            ];
        }

        usort($allRooms, fn($a, $b) => $a['total_fare'] <=> $b['total_fare']);

        $cheapestRoom = $allRooms[0];
        $hotel = $cheapestRoom['hotel'];
        $room = $cheapestRoom['room'];

        // Get hotel details to retrieve hotel name
        $hotelDetails = $this->tboService->getHotelDetails($hotel['HotelCode']);
        $hotelName = $hotelDetails['HotelDetails']['HotelName'] ?? 'Hotel ' . $hotel['HotelCode'];

        $prebookResponse = $this->tboService->preBook($room['BookingCode']);

        if (!isset($prebookResponse['Status']) || $prebookResponse['Status']['Code'] !== 200) {
            $errorMessage = $prebookResponse['Status']['Description'] ?? 'Prebook failed';
            
            return [
                'success' => false,
                'message' => 'Prebook failed: ' . $errorMessage,
                'data' => null,
            ];
        }

        $prebookHotel = $prebookResponse['HotelResult'][0] ?? null;
        $prebookRoom = $prebookHotel['Rooms'][0] ?? null;

        if (!$prebookRoom) {
            return [
                'success' => false,
                'message' => 'Prebook response missing room details.',
                'data' => null,
            ];
        }

        $tboPrebook = TBO::create([
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

        return [
            'success' => true,
            'message' => 'TBO hotel search completed successfully.',
            'data' => [
                'hotel_code' => $hotel['HotelCode'],
                'hotel_name' => $hotelName,
                'room_count' => 1,
                'rooms' => [
                    [
                        'success' => true,
                        'error' => null,
                        'room' => $roomDetails,
                        'prebook' => [
                            'prebookKey' => 'TBO-' . $tboPrebook->id,
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
                    ],
                ],
            ],
        ];
    }
}
