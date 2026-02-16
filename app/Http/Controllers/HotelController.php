<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Traits\AjaxSearchable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    use AjaxSearchable;

    // AJAX
    public function searchHotel(Request $request): JsonResponse
    {
        return $this->ajaxSearch(
            $request,
            Hotel::query(),
            allowedColumns: ['name', 'address', 'city', 'state', 'country', 'description'],
            orderBy: 'name',
            limit: 20
        );
    }
}