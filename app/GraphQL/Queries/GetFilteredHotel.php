<?php

namespace App\GraphQL\Queries;

use App\Services\MagicHolidayService;
use Illuminate\Support\Facades\Validator;

class GetFilteredHotel
{
    protected $magicService;

    public function __construct(MagicHolidayService $magicService)
    {
        $this->magicService = $magicService;
    }

    public function __invoke($_, array $args)
    {
        $input = $args['input'] ?? [];

        $validator = Validator::make($input, [
            'destination.city.id' => 'required|integer',
            'checkin' => 'required|date',
            'checkout' => 'required|date|after:checkin',
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Validation failed: ' . $validator->errors()->first(),
                'hotels' => [],
            ];
        }

        $cityId = $input['destination']['city']['id'];

        $occupancy = [
            'leaderNationality' => $input['occupancy']['leaderNationality'] ?? 1,
            'rooms' => [],
        ];

        if (!empty($input['occupancy']['rooms']) && is_array($input['occupancy']['rooms'])) {
            foreach ($input['occupancy']['rooms'] as $room) {
                $occupancy['rooms'][] = [
                    'adults' => $room['adults'] ?? 2,
                    'childrenAges' => $room['childrenAges'] ?? [],
                ];
            }
        }

        $payload = [
            'destination' => ['city' => ['id' => $cityId]],
            'checkIn' => $input['checkin'],
            'checkOut' => $input['checkout'],
            'occupancy' => $occupancy,
            'sellingChannel' => $input['sellingChannel'] ?? 'B2B',
            'language' => $input['language'] ?? 'en_GB',
            'timeout' => $input['timeout'] ?? 20,
            'providers' => $input['providers'] ?? ['expediarapid'],
            'filters' => $input['filters'] ?? [],
        ];

        try {
            $this->magicService->findByCity($payload);

            return [
                'success' => true,
                'message' => 'Input validated successfully. You can now run the mutation for filtered results.',
                'hotels' => [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error validating input: ' . $e->getMessage(),
                'hotels' => [],
            ];
        }
    }
}
