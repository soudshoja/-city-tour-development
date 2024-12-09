<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Airline extends Model
{
    //     id	bigint unsigned	NO	PRI
    // name	varchar(255)	YES	
    // iata_designator	varchar(8)	YES	UNI
    // code	varchar(8)	YES	UNI
    // icao_designator	varchar(8)	YES	UNI
    // country_id	int	NO	MUL
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
