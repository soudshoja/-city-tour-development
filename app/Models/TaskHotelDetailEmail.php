<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskHotelDetailEmail extends Model
{

    protected $table = 'task_hotel_details_emails';

    protected $fillable = [
        'task_id',
        'hotel_id',
        'booking_time',
        'check_in',
        'check_out',
        'room_number',
        'room_type',
        'room_amount',
        'room_details',
        'room_promotion',
        'rate',
        'meal_type',
        'is_refundable',
        'supplements',
    ];

    public $incrementing = true;
    protected $primaryKey = 'id';

    public function task()
    {
        return $this->belongsTo(TaskEmail::class, 'task_id');
    }

    use HasFactory;
}
