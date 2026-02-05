<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HotelController extends Controller {
    
    // AJAX
    public function searchHotel(Request $request): JsonResponse {
        $searchTerm = $request->input('search', '');

        $hotelQuery = Hotel::query();

        if ($searchTerm) {
            $hotelQuery->where(function($query) use ($searchTerm) {
            $query->where('name', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('address', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('city', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('state', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('country', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('description', 'LIKE', '%' . $searchTerm . '%');
            });
        }

        $hotels = $hotelQuery
                ->select('id', 'name')
                ->orderBy('name', 'asc')
                ->limit(20)
                ->get();

        $formattedHotels = $hotels->map(function ($hotel) {
            return [
                'id'   => $hotel->id,
                'name' => $hotel->name,
            ];
        });

        return response()->json($formattedHotels);
    }
}