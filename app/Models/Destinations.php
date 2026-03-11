<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Destinations extends Model
{
    protected $fillable = [
        'map_id',
        'name',
        'code',
        'address',
        'city'
    ];
}
