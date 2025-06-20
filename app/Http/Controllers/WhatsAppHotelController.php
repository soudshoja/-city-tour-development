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
            'result_token' => 'required|string',
            'enquiry_id' => 'required|string',
            'offers' => 'required|array|min:1',
            'offers.*.offer_index' => 'required|string',
            'offers.*.room_details' => 'required|array|min:1',
            'offers.*.room_details.*.room_name' => 'required|string',
            'offers.*.room_details.*.board_basis' => 'required|string',
            'offers.*.room_details.*.non_refundable' => 'nullable|boolean',
            'offers.*.room_details.*.room_token' => 'required|string',
            'offers.*.room_details.*.price' => 'required|numeric',
            'offers.*.room_details.*.currency' => 'nullable|string',
            'offers.*.room_details.*.package_token' => 'required|string',
            'offers.*.room_details.*.info' => 'nullable|string',
        ]);

        $allOffers = [];

        foreach ($request->offers as $offer) {
            $tempOffer = TemporaryOffer::create([
                'telephone' => $request->telephone,
                'srk' => $request->srk,
                'hotel_index' => $request->hotel_index,
                'hotel_name' => $request->hotel_name,
                'offer_index' => $offer['offer_index'],
                'result_token' => $request->result_token,
                'enquiry_id' => $request->enquiry_id,
            ]);

            $roomModels = [];

            foreach ($offer['room_details'] as $room) {
                $roomModels[] = OfferedRoom::create([
                    'temp_offer_id' => $tempOffer->id,
                    'room_name' => $room['room_name'],
                    'board_basis' => $room['board_basis'],
                    'non_refundable' => $room['non_refundable'],
                    'info' => $room['info'] ?? '',
                    'price' => $room['price'],
                    'currency' => $room['currency'] ?? 'KWD',
                    'room_token' => $room['room_token'],
                    'package_token' => $room['package_token'],
                ]);
            }

            $allOffers[] = [
                'offer_index' => $tempOffer->offer_index,
                'room_details' => collect($roomModels)->map(fn($r) => [
                    'room_name' => $r->room_name,
                    'board_basis' => $r->board_basis,
                    'non_refundable' => $r->non_refundable,
                    'room_token' => $r->room_token,
                    'package_token' => $r->package_token,
                    'price' => $r->price,
                    'currency' => $r->currency,
                ])
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Hotel offers saved successfully.',
            'hotel' => $request->hotel_name,
            'offers' => $allOffers,
        ], 201);
    }

    public function findOffer(Request $request)
    {
        $request->validate([
            'telephone' => 'required|string',
            'room_name' => 'required|string',
            'board_basis' => 'nullable|string',
            'non_refundable' => 'nullable|boolean',
        ]);

        $offers = TemporaryOffer::where('telephone', $request->telephone)->get();

        if ($offers->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No matching offer found.'
            ], 404);
        }

        $offer = $offers->first();

        $roomQuery = OfferedRoom::whereIn('temp_offer_id', $offers->pluck('id'));

        if ($request->filled('room_name')) {
            $roomQuery->where('room_name', 'like', '%' . $request->room_name . '%');
        }

        if ($request->has('board_basis')) {
            if (is_null($request->board_basis)) {
                $roomQuery->whereNull('board_basis');
            } else {
                $roomQuery->where('board_basis', 'like', '%' . $request->board_basis . '%');
            }
        }

        if ($request->has('non_refundable')) {
            $roomQuery->where('non_refundable', $request->non_refundable);
        }

        $rooms = $roomQuery->get();

        if ($rooms->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No matching room(s) found.'
            ], 404);
        }

        $groupedOffers = $rooms->groupBy(function ($room) {
            return $room->temporaryOffer->offer_index;
        })->map(function ($group, $offerIndex) {
            return [
                'offer_index' => $offerIndex,
                'room_details' => $group->map(function ($room) {
                    return [
                        'room_name' => $room->room_name,
                        'board_basis' => $room->board_basis,
                        'non_refundable' => (bool) $room->non_refundable,
                        'room_token' => $room->room_token,
                        'package_token' => $room->package_token,
                        'price' => (float) $room->price,
                        'currency' => $room->currency ?? 'KWD',
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'telephone' => $offer->telephone,
                'enquiry_id' => $offer->enquiry_id,
                'srk' => $offer->srk,
                'hotel_index' => (int) $offer->hotel_index,
                'hotel_name' => $offer->hotel_name,
                'result_token' => $offer->result_token,
                'offers' => $groupedOffers,
            ],
        ]);
    }
}
