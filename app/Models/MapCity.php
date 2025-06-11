<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapCity extends Model
{
    protected $connection = 'mysql_map';

    protected $table = 'Cities';

    protected $fillable = [
        'id',
        'name',
        'country_id',
        'services',
        'code',
    ];

    public function country()
    {
        return $this->belongsTo(MapCountry::class, 'country_id', 'id');
    }
}
