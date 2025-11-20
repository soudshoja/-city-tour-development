<?php

namespace App\GraphQL\Mutations;

use App\Models\Agent;
use App\Services\HotelSearchService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Nuwave\Lighthouse\Exceptions\ValidationException;

class B2BHotelSearchWithPrebook
{
    protected $logger;

    public function __construct()
    {
        $this->logger = Log::channel('magic_holidays');
    }

    public function __invoke($_, array $args)
    {
        $this->logger->info("B2BHotelSearchWithPrebook Start", ['input' => $args['input']]);
        $input = $args['input'];

        $validator = Validator::make($input, [
            'telephone' => 'required|string',
            'hotel' => 'required|string',
            'city' => 'required|string',
            'checkIn' => 'required|date|after_or_equal:today',
            'checkOut' => 'required|date|after:checkIn',
            'roomCount' => 'nullable|integer',
            'nonRefundable' => 'nullable|boolean',
            'boardBasis' => 'nullable|string|max:4',
            'occupancy' => 'required|array',
            'occupancy.rooms' => 'required|string',
            'roomName' => 'nullable|string',
            'nationality' => 'nullable|string',
        ], [
            'telephone.required' => 'Telephone number is required.',
            'hotel.required' => 'Hotel name is required.',
            'city.required' => 'City name is required.',
            'checkIn.required' => 'Check-in date is required.',
            'checkIn.after_or_equal' => 'Check-in date must be today or later.',
            'checkOut.required' => 'Check-out date is required.',
            'checkOut.after' => 'Check-out date must be after check-in date.',
            'occupancy.required' => 'Occupancy details are required.',
            'occupancy.rooms.required' => 'Rooms must be specified in occupancy.',
        ]);

        if($validator->fails()) {
            throw new ValidationException(
                'Validation failed',
                $validator
            );
        }

        $telephone = $input['telephone'] ?? null;
        $hotelName = $input['hotel'] ?? null;
        $cityName = $input['city'] ?? null;
        $checkIn = date('Y-m-d', strtotime($input['checkIn']));
        $checkOut = date('Y-m-d', strtotime($input['checkOut']));
        $occupancy = $input['occupancy'] ?? null;
        $roomCount = $input['roomCount'] ?? 1;
        $nonRefundable = $input['nonRefundable'] ?? null;
        $boardBasis = $input['boardBasis'] ?? null;
        $roomName = $input['roomName'] ?? null;
        $nationality = $input['nationality'] ?? null;

        $agent = Agent::where('phone_number', $telephone)->first();
        $agentInfo = [
            'agentName' => $agent->name ?? null,
            'email' => $agent->email ?? null,
        ];

        $this->logger->info("Agent resolved", $agentInfo);

        $search = new HotelSearchService();
        $searchResult = $search->searchHotelRooms(
            telephone: $telephone,
            hotelName: $hotelName,
            checkIn: $checkIn,
            checkOut: $checkOut,
            occupancy: $occupancy,
            cityName: $cityName ?? null,
            roomCount: $roomCount,
            nonRefundable: $nonRefundable,
            boardBasis: $boardBasis,
            roomName: $roomName ?? null,
            isMarkup: false,
            nationality: $nationality
        );

        if (!$searchResult['success']) {
            if (isset($searchResult['multiple_hotels'])) {
                return [
                    '__typename' => 'B2BMultipleHotelMatch',
                    'agentInfo' => $agentInfo,
                    'status' => 'multiple_matches',
                    'message' => $searchResult['message'] ?? 'Multiple hotels match your search.',
                    'hotels' => $searchResult['multiple_hotels']
                ];
            }

            return [
                '__typename' => 'B2BMultipleHotelMatch',
                'agentInfo' => $agentInfo,
                'status' => 'not_found',
                'message' => $searchResult['message'] ?? 'No hotels found.',
                'hotels' => []
            ];
        }

        return [
            '__typename' => 'B2BHotelSearchSuccess',
            'agentInfo' => $agentInfo,
            'searchResult' => $searchResult
        ];
    }
}
