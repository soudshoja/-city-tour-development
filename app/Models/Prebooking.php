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
        'remarks',
        'service_dates',
        'package',
        'payment_methods',
        'booking_options',
        'price_breakdown',
        'taxes',
    ];

    protected $casts = [
        'duration' => 'integer',
        'autocancel_date' => 'datetime',
        'cancel_policy' => 'array',
        'remarks' => 'array',
        'rooms' => 'array',
        'service_dates' => 'array',
        'package' => 'array',
        'payment_methods' => 'array',
        'booking_options' => 'array',
        'price_breakdown' => 'array',
        'taxes' => 'array',
    ];

    public function hotel()
    {
        return $this->belongsTo(MapHotel::class);
    }
}
