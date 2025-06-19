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
        'hotel_name',
        'offer_index',
        'room_name',
        'board_basis',
        'refundable',
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
