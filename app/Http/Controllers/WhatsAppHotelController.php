<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TemporaryOffer;
use App\Models\OfferedRoom;
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
            'enquiry_id' => 'required|string',
            'room_details' => 'required|array|min:1',
            'room_details.*.room_name' => 'required|string',
            'room_details.*.board_basis' => 'required|string',
            'room_details.*.non_refundable' => 'required|boolean',
            'room_details.*.room_token' => 'required|string',
            'room_details.*.price' => 'required|numeric',
            'room_details.*.currency' => 'nullable|string',
            'room_details.*.package_token' => 'required|string',
            'room_details.*.info' => 'nullable|string',
        ]);

        $tempOffer = TemporaryOffer::create([
            'telephone' => $request->telephone,
            'srk' => $request->srk,
            'hotel_index' => $request->hotel_index,
            'hotel_name' => $request->hotel_name,
            'offer_index' => $request->offer_index,
            'result_token' => $request->result_token,
            'enquiry_id' => $request->enquiry_id,
        ]);

        $rooms = collect($request->room_details)->map(function ($room) use ($tempOffer) {
            return OfferedRoom::create([
                'temp_offer_id' => $tempOffer->id,
                'room_name' => $room['room_name'],
                'board_basis' => $room['board_basis'],
                'non_refundable' => $room['non_refundable'],
                'info' => $room['info'] ?? '',
                'price' => $room['price'],
                'currency' => $room['currency'],
                'room_token' => $room['room_token'],
                'package_token' => $room['package_token'],
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Room offers saved successfully.',
            'offerDetails' => [
                'hotelName' => $request->hotel_name,
                'roomDetails' => $rooms->map(fn($room) => [
                    'roomName' => $room->room_name,
                    'boardBasis' => $room->board_basis,
                    'nonRefundable' => $room->non_refundable,
                    'info' => $room->info,
                    'roomToken' => $room->room_token,
                    'price' => $room->price,
                    'currency' => $room->currency,
                ]),
            ],
        ], 201);
    }

    public function findOffer(Request $request)
    {
        $request->validate([
            'telephone' => 'required|string',
            'room_name' => 'required|string',
        ]);

        $offer = TemporaryOffer::where('telephone', $request->telephone)
            ->latest()
            ->first();

        if (!$offer) {
            return response()->json([
                'success' => false,
                'message' => 'No matching offer found.'
            ], 404);
        }

        $room = $offer->offeredRoom()
            ->where('room_name', $request->room_name)
            ->first();

        if (!$room) {
            return response()->json([
                'success' => false,
                'message' => 'No matching room found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'offer' => $offer,
                'room' => $room
            ]
        ]);
    }
}
