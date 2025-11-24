<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TBO extends Model
{
    protected $table = 'tbo';

    protected $fillable = [
        'hotel_booking_id',
        'prebook_key',
        'booking_code',
        'confirmation_no',
        'booking_reference_id',
        'payment_status',
        'supplier_status',
        'hotel_code',
        'hotel_name',
        'room_name',
        'room_quantity',
        'inclusion',
        'currency',
        'day_rates',
        'total_fare',
        'total_tax',
        'extra_guest_charges',
        'room_promotion',
        'cancel_policies',
        'meal_type',
        'is_refundable',
        'with_transfer',
    ];

    public function rooms()
    {
        return $this->hasMany(TBORoom::class, 'tbo_id');
    }

    public function hotelBooking()
    {
        return $this->belongsTo(HotelBooking::class, 'hotel_booking_id');
    }
}
