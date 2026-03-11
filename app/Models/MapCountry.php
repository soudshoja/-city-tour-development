<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapCountry extends Model
{
    protected $connection = 'mysql_map';

    protected $table = 'countries';

    protected $fillable = [
        'id',
        'name',
        'iso',
        'services',
    ];
}
