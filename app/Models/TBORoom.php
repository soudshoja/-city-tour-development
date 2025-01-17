<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TBORoom extends Model
{
    protected $table = 'tbo_room';

    protected $fillable = [
        'tbo_id',
        'room_name',
        'adult_quantity',
        'child_quantity',
    ];

    public function tbo()
    {
        return $this->belongsTo(TBO::class, 'tbo_id');
    }
}

