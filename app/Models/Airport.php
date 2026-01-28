<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Airport extends Model
{
    protected $fillable = [
        'name',
        'name_ar',
        'iata_code',
        'icao_code',
        'city_id',
        'country_id',
        'timezone',
        'latitude',
        'longitude',
        'altitude',
        'is_active',
    ];
}
