<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemporaryOffer extends Model
{   
    protected $fillable = [
        'telephone',
        'enquiry_id',
        'srk',
        'hotel_index',
        'hotel_name',
        'offer_index',
        'result_token'
    ];

    public function offeredRoom()
    {
        return $this->hasMany(OfferedRoom::class, 'temp_offer_id');
    }
}
