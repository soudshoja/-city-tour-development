<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapHotelImage extends Model
{
    protected $connection = 'mysql_map';

    protected $table = 'hotel_images';

    protected $fillable = [
        'hotel_id',
        'url',
        'source',
        'name',
    ];

    public function hotel()
    {
        return $this->belongsTo(MapHotel::class, 'hotel_id', 'id');
    }
}
