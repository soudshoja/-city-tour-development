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

    public function getDurationByCalculateAttribute()
    {
        $durationInMinutes = $this->departure_time->diffInMinutes($this->arrival_time);
        $hours = floor($durationInMinutes / 60);
        $minutes = $durationInMinutes % 60;
        return sprintf('%02d:%02d', $hours, $minutes);
    }

    public function getDeparturePlaceTimeAttribute()
    {
        return $this->countryFrom->name . ' (' . $this->airport_from . ') - ' . $this->departure_time->format('Y-m-d g:i A');
    }

    public function getArrivalPlaceTimeAttribute()
    {
        return $this->countryTo->name . ' (' . $this->airport_to . ') - ' . $this->arrival_time->format('Y-m-d g:i A');
    }

    public function getReadableDepartureTimeAttribute()
    {
        return $this->departure_time->format('F j, Y g:i A');
    }

    public function getReadableTimeRangeAttribute()
    {
        return $this->departure_time->format('g:i A') . ' - ' . $this->arrival_time->format('g:i A');
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