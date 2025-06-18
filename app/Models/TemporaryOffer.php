<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemporaryOffer extends Model
{   
    protected $fillable = [
        'id',
        'telephone',
        'enquiry_id',
        'srk',
        'hotel_index',
        'offer_index',
        'room_name',
        'room_token',
        'result_token',
        'package_token',
        'min_price'
    ];

    public function hotel()
    {
        return $this->belongsTo(MapHotel::class, 'hotel_index', 'id');
    }
}
