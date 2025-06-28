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
        'rooms',
        'checkin',
        'checkout',
        'duration',
        'autocancel_date',
        'cancel_policy',
        'remarks'
    ];

    protected $casts = [
        'duration' => 'integer',
        'autocancel_date' => 'datetime',
        'cancel_policy' => 'array',
        'remarks' => 'array',
        'rooms' => 'array'
    ];
}
