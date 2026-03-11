<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferedRoom extends Model
{
    protected $fillable = [
        'temp_offer_id',
        'room_name',
        'board_basis',
        'non_refundable',
        'info',
        'occupancy',
        'price',
        'currency',
        'room_token',
        'package_token',
    ];

    public function temporaryOffer()
    {
        return $this->belongsTo(TemporaryOffer::class, 'temp_offer_id');
    }
}
