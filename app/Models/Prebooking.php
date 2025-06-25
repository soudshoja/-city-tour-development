<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prebooking extends Model
{   
    protected $fillable = [
        'prebook_key',
        'telephone',
        'availability_token',
        'srk',
        'package_token',
        'hotel_id',
        'offer_index',
        'result_token',
        'room_token',
        'room_name',
        'non_refundable',
        'board_basis',
        'price',
        'currency',
        'checkin',
        'checkout',
        'duration',
        'occupancy',
        'autocancel_date',
        'cancel_policy',
        'remarks'
    ];
}
