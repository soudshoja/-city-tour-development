<?php

namespace App\GraphQL\Mutations;

use App\Models\Agent;
use App\Services\HotelSearchService;
use Illuminate\Support\Facades\Log;

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
