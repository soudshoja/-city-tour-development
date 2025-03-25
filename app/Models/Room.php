<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = [
        'task_hotel_details_id',
        'name',
        'adult_quantity',
        'child_quantity',
    ];

    public function taskHotelDetail()
    {
        return $this->belongsTo(TaskHotelDetail::class, 'task_hotel_details_id');
    }
}
