<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestBookingRoom extends Model
{
    protected $fillable = [
        'phone_number',
        'check_in',
        'check_out',
        'adults',
        'children_ages',
    ];

    // protected $casts = [
    //     'check_in' => 'datetime',
    //     'check_out' => 'datetime',
    // ];
}
