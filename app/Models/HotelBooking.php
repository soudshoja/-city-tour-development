<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelBooking extends Model
{   
    protected $fillable = [
        'prebook_id',
        'supplier_booking_id',
        'client_ref',
        'status',
        'price',
        'currency',
        'booking_time'
    ];

    public function prebooking()
    {
        return $this->belongsTo(Prebooking::class, 'prebook_id');
    }
}
