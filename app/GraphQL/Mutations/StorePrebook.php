<?php

namespace App\GraphQL\Mutations;

use App\Services\HotelSearchService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StorePrebook
{
    protected $hotelSearchService;

    public function __construct(HotelSearchService $hotelSearchService)
    {
        $this->hotelSearchService = $hotelSearchService;
    }

    /**
     * Handle the storePrebook mutation
     *
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $input = $args['input'];

        $validator = Validator::make($input, [
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
            'checkout' => 'required|date|after:checkin',
            'duration' => 'nullable|integer',
            'autocancel_date' => 'nullable|date',
            'cancel_policy' => 'nullable|array',
            'remarks' => 'nullable|array',
            'service_dates' => 'nullable|array',
            'package' => 'nullable|array',
            'payment_methods' => 'nullable|array',
            'booking_options' => 'nullable|array',
            'price_breakdown' => 'nullable|array',
            'taxes' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            $message = 'Validation failed: ' . $validator->errors()->first();
            Log::warning('StorePrebook: validation failed', [
                'errors' => $validator->errors()->toArray(),
            ]);

            return [
                'success' => false,
                'message' => $message,
            ];
        }

        Log::info('StorePrebook: passing validated input to service', [
            'telephone' => $input['telephone'],
            'hotel_id' => $input['hotel_id'],
            'offer_index' => $input['offer_index'],
        ]);

        return $this->hotelSearchService->storePrebook($input);
    }
}
