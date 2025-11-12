<?php

namespace App\GraphQL\Queries;

use App\Services\HotelSearchService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SearchHotelRooms
{
    protected $hotelSearchService;

    public function __construct(HotelSearchService $hotelSearchService)
    {
        $this->hotelSearchService = $hotelSearchService;
    }

    /**
     * Resolve the hotel room search query
     *
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $input = $args['input'];

        $validator = Validator::make($input, [
            'telephone' => 'required|string',
            'hotel' => 'required|string',
            'city' => 'nullable|string',
            'checkIn' => 'required|date|after_or_equal:today',
            'checkOut' => 'required|date|after:checkIn',
            'roomCount' => 'nullable|integer',
            'nonRefundable' => 'nullable|boolean',
            'boardBasis' => 'nullable|string|max:4',
            'occupancy' => 'required|array',
            'occupancy.rooms' => 'required|string',
        ], [
            'telephone.required' => 'Telephone number is required.',
            'hotel.required' => 'Hotel name is required.',
            'checkIn.required' => 'Check-in date is required.',
            'checkIn.after_or_equal' => 'Check-in date must be today or later.',
            'checkOut.required' => 'Check-out date is required.',
            'checkOut.after' => 'Check-out date must be after check-in date.',
            'occupancy.required' => 'Occupancy details are required.',
            'occupancy.rooms.required' => 'Rooms must be specified in occupancy.',
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Validation failed: ' . $validator->errors()->first(),
                'data' => null,
            ];
        }

        $result = $this->hotelSearchService->searchHotelRooms(
            $input['telephone'],
            $input['hotel'],
            $input['checkIn'],
            $input['checkOut'],
            $input['occupancy'],
            $input['city'] ?? null,
            $input['roomCount'] ?? 1,
            $input['nonRefundable'] ?? null,
            $input['boardBasis'] ?? null
        );

        return $result;
    }
}
