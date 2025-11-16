<?php

namespace App\GraphQL\Mutations;

use App\Models\Agent;
use App\Services\HotelSearchService;
use App\GraphQL\Mutations\GetFilteredHotels;
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
        $roomcount = $input['roomcount'] ?? 1;
        $nonRefundable = $input['nonRefundable'] ?? null;
        $boardBasis = $input['boardBasis'] ?? null;

        // STEP 1 — AGENT INFO
        $agent = Agent::where('phone_number', $telephone)->first();
        $agentInfo = [
            'agentName' => $agent->name ?? null,
            'email' => $agent->email ?? null,
        ];

        $this->logger->info("Agent resolved", $agentInfo);

        // STEP 2 — CALL Getfiltered
        $filterInput = [
            'destination' => [
                'city' => ['name' => $cityName]
            ],
            'checkin'  => $checkIn,
            'checkout' => $checkOut,
            'occupancy' => [
                'leaderNationality' => $occupancy['leaderNationality'] ?? 1,
                'rooms' => $occupancy['rooms']
            ],
            'filters' => [
                'name' => $hotelName,
                'classification' => $input['stars'] ?? [],
                'minPrice' => $input['minPrice'] ?? null,
                'maxPrice' => $input['maxPrice'] ?? null
            ]
        ];
        $this->logger->info("Calling Getfiltered", $filterInput);

        $filtered = (new GetFilteredHotels())->__invoke(null, ['input' => $filterInput]);
        $this->logger->info("Getfiltered response", $filtered);

        // 0 MATCHES
        if (empty($filtered['hotels'])) {
            return [
                '__typename' => 'B2BMultipleHotelMatch',
                'agentInfo' => $agentInfo,
                'status' => 'not_found',
                'message' => 'No hotels found with applied filters.',
                'hotels' => []
            ];
        }

        // MULTIPLE MATCHES
        if (count($filtered['hotels']) > 1) {
            return [
                '__typename' => 'B2BMultipleHotelMatch',
                'agentInfo' => $agentInfo,
                'status' => 'multiple_matches',
                'message' => 'Multiple hotels match your search.',
                'hotels' => array_map(function ($h) {
                    return [
                        'name' => $h['name'],
                        'address' => $h['address'] ?? null
                    ];
                }, $filtered['hotels'])
            ];
        }

        // EXACT MATCH → room search
        $hotel = $filtered['hotels'][0];
        $this->logger->info("Exact hotel match", $hotel);

        $search = new HotelSearchService();
        $searchResult = $search->searchHotelRooms(
            telephone: $telephone,
            hotelName: $hotel['name'],
            checkIn: $checkIn,
            checkOut: $checkOut,
            occupancy: $occupancy,
            cityName: $cityName ?? null,
            roomCount: $roomcount,
            nonRefundable: $nonRefundable,
            boardBasis: $boardBasis
        );

        return [
            '__typename' => 'B2BHotelSearchSuccess',
            'agentInfo' => $agentInfo,
            'searchResult' => $searchResult
        ];
    }
}
