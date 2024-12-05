<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskHotelDetail extends Model
{
    protected $fillable = [
        'hotel_id',
        'booking_time',
        'check_in',
        'check_out',
        'room_number',
        'room_type',
        'room_amount',
        'room_details',
        'rate',
        'task_id',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function hotel()
    {
        return $this->hasOne(Hotel::class, 'hotel_id');
    }

    use HasFactory;
}
