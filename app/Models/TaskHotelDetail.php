<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskHotelDetail extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'task_id',
        'hotel_id',
        'booking_time',
        'check_in',
        'check_out',
        'room_reference',
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

    public function getReadableCheckInAttribute()
    {
        return date('l, F d, Y', strtotime($this->check_in));
    }

    public function getReadableCheckOutAttribute()
    {
        return date('l, F d, Y', strtotime($this->check_out));
    }

    public function getDateCheckInAttribute()
    {
        return date('M', strtotime($this->check_in));
    }

    public function getDayCheckInAttribute()
    {
        return date('d', strtotime($this->check_in));
    }

    public function getYearCheckInAttribute()
    {
        return date('Y', strtotime($this->check_in));
    }

    public function getNightsAttribute()
    {
        $checkIn = new \DateTime($this->check_in);
        $checkOut = new \DateTime($this->check_out);
        return $checkIn->diff($checkOut)->days;
    }

    public function getRoomNameAttribute()
    {
        if ($this->room_details) {
            $roomDetails = json_decode($this->room_details, true);
            return $roomDetails['name'] ?? 'N/A';
        } else {
            return 'N/A';
        }
        return 'N/A';
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function hotel()
    {
        //return $this->hasOne(Hotel::class, 'id');
        return $this->belongsTo(Hotel::class, 'hotel_id');
    }

    public function room()
    {
        return $this->hasOne(Room::class, 'task_hotel_details_id');
    }

    use HasFactory;
}
