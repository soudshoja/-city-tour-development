<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapHotelDescription extends Model
{
    protected $connection = 'mysql_map';

    protected $table = 'hotel_descriptions';

    protected $fillable = [
        'hotel_id',
        'description',
        'language',
        'name',
        'address',
        'room_description',
        'location_description',
        'location_description_source',
        'facilities_description',
        'facilities_description_source',
        'description_short',
        'description_short_source',
        'description_full',
        'description_full_source',
        'essential_information',
        'essential_information_source'
    ];

    public function hotel()
    {
        return $this->belongsTo(MapHotel::class, 'hotel_id', 'id');
    }
}
