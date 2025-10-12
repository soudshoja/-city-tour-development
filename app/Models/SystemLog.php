<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    protected $fillable = [
        'user_id',
        'model',
        'current_value',
        'new_value',
        'remarks',
    ];
}
