<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TemporaryOffer;
use App\Models\MapHotel;

class WhatsAppHotelController extends Controller
{
    public function getCityIdFromHotelName(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $hotel = MapHotel::where('name', $request->name)->first();

        if ($hotel) {
            return response()->json([
                'success' => true,
                'city_id' => $hotel->city_id,
                'hotel_name' => $hotel->name,
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Hotel not found.',
            ], 404);
        }
    }

    public function storeTemporaryOffer(Request $request)
    {
        $request->validate([
            'telephone' => 'required|string',
            'srk' => 'required|string',
            'hotel_index' => 'required|integer',
            'hotel_name' => 'required|string',
            'offer_index' => 'required|string',
            'result_token' => 'required|string',
            'package_token' => 'required|string',
            'enquiry_id' => 'required|string',
            'room_details' => 'required|array|min:1',
            'room_details.*.room_name' => 'required|string',
            'room_details.*.board_basis' => 'required|string',
            'room_details.*.refundable' => 'required|boolean',
            'room_details.*.room_token' => 'required|string',
            'room_details.*.min_price' => 'required|numeric',
        ]);
    
        $saved = [];
    
        foreach ($request->room_details as $room) {
            $saved[] = TemporaryOffer::create([
                'telephone' => $request->telephone,
                'srk' => $request->srk,
                'hotel_index' => $request->hotel_index,
                'hotel_name' => $request->hotel_name,
                'offer_index' => $request->offer_index,
                'room_name' => $room['room_name'],
                'board_basis' => $room['board_basis'],
                'refundable' => $room['refundable'],
                'room_token' => $room['room_token'],
                'result_token' => $request->result_token,
                'package_token' => $request->package_token,
                'min_price' => $room['min_price'],
                'enquiry_id' => $request->enquiry_id,
            ]);
        }
    
        return response()->json([
            'success' => true,
            'message' => 'All room offers stored successfully.',
            'offerDetails' => [
                'hotelName' => $request->hotel_name,
                'roomDetails' => collect($saved)->map(fn($item) => [
                    'roomName' => $item->room_name,
                    'boardBasis' => $item->board_basis,
                    'nonRefundable' => !$item->refundable,
                    'roomToken' => $item->room_token,
                    'min_price' => $item->min_price,
                ]),
            ],
        ], 201);
    }

    public function findOffer(Request $request)
    {
        TemporaryOffer::where('created_at', '<', now()->subMinutes(10))->delete();

        $request->validate([
            'telephone' => 'required|string',
            'room_name' => 'required|string',
        ]);

        $offer = TemporaryOffer::where('telephone', $request->telephone)
            ->where('room_name', $request->room_name)
            ->orderByDesc('created_at')
            ->first();

        if (!$offer) {
            return response()->json([
                'success' => false,
                'message' => 'No matching offer found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $offer
        ]);
    }
}
