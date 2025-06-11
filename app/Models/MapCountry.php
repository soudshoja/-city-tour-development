<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapCountry extends Model
{
    protected $connection = 'mysql_map';

    protected $table = 'Countries';

    protected $fillable = [
        'id',
        'name',
        'iso',
        'services',
    ];
}
