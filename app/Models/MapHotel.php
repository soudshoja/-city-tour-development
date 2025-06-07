<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapHotel extends Model
{   
    protected $connection = 'mysql_map';

    protected $table = 'hotels';

    protected $fillable = [
        'id',
        'name',
        'type',
        'address',
        'telephone',
        'fax',
        'email',
        'zipCode',
        'stars',
        'recommended',
        'specialDeal',
    ];

    public function city()
    {
        return $this->belongsTo(MapCity::class, 'city_id', 'id');
    }

    public function scopeByCity($query, $cityId)
    {
        return $query->where('city_id', $cityId);
    }
}
