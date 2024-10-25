<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskFlightDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'departure_time',
        'departure_from',
        'arrival_time',
        'arrive_to',
        'terminal',
        'airline_id',
        'flight_number',
        'class',
        'baggage_allowed',
        'equipment',
        'flight_meal',
        'task_id',
    ];


    public function task()
    {
        return $this->belongsTo(Agent::class, 'task_id');
    }

}