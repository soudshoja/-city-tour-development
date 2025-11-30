<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelBooking extends Model
{   
    protected $fillable = [
        'prebook_id',
        'client_id',
        'payment_id',
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

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
    
    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
    
    public function tbo()
    {
        return $this->hasOne(TBO::class, 'hotel_booking_id');
    }
}
