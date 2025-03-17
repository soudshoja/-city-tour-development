<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskFlightDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'farebase',
        'departure_time',
        'country_id_from',
        'airport_from',
        'terminal_from',
        'arrival_time',
        'country_id_to',
        'airport_to',
        'terminal_to',
        'airline_id',
        'flight_number',
        'class_type',
        'baggage_allowed',
        'equipment',
        'flight_meal',
        'seat_no',
    ];

    protected function casts(): array
    {
        return [
            'departure_time' => 'datetime: H:i',
            'arrival_time' => 'datetime: H:i',
            'created_at' => 'datetime: Y-m-d',
            'updated_at' => 'datetime: Y-m-d',
        ];
    }

    public function countryFrom()
    {
        return $this->belongsTo(Country::class, 'country_id_from');
    }

    public function countryTo()
    {
        return $this->belongsTo(Country::class, 'country_id_to');
    }

    public function task()
    {
        return $this->belongsTo(Agent::class, 'task_id');
    }


}