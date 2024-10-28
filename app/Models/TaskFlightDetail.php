<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskFlightDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'farebase',
        'departure_time',
        'departure_from',
        'airport_from',
        'arrival_time',
        'terminal_to',
        'arrive_to',
        'airport_to',
        'terminal_from',
        'airline_id',
        'flight_number',
        'class_type',
        'baggage_allowed',
        'equipment',
        'flight_meal',
        'seat_no',
        'task_id',
    ];


    public function task()
    {
        return $this->belongsTo(Agent::class, 'task_id');
    }

}