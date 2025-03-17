<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Airline extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'iata_designator',
        'code',
        'icao_designator',
        'country_id'
    ];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }
}
