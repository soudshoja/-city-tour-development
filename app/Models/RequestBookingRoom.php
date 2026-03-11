<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestBookingRoom extends Model
{

    protected $fillable = [
        'phone_number',
        'check_in',
        'check_out',
        'hotel',
        'city',
        'city_id',
        'occupancy',
        'disabled',
    ];

    protected $casts = [
        'check_in'  => 'date:Y-m-d',
        'check_out' => 'date:Y-m-d',
        'occupancy' => 'array',   
    ];

}
