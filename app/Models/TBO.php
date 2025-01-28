<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TBO extends Model
{
    protected $table = 'tbo';

    protected $fillable = [
        'booking_code',
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
}
