<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskFlightDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'task_id',
        'farebase',
        'departure_time',
        'country_id_from',
        'airport_from',
        'terminal_from',
        'arrival_time',
        'duration_time',
        'country_id_to',
        'airport_to',
        'terminal_to',
        'airline_id',
        'flight_number',
        'ticket_number',    
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
        if(!$this->departure_time) {
            return 'N/A';
        }
        return $this->departure_time->format('F j, Y g:i A');
    }

    public function getReadableArrivalTimeAttribute()
    {
        if(!$this->arrival_time) {
            return 'N/A';
        }
        return $this->arrival_time->format('F j, Y g:i A');
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

class TaskFlightDetailSchema
{
    public static function getSchema()
    {
        return [
            'farebase' => [
                'type' => 'float',
                'description' => 'Fare basis of the flight.',
                'example' => '20.00',
            ],
            'departure_time' => [
                'type' => 'datetime',
                'description' => 'Departure time of the flight.',
                'example' => '2024-10-16 14:00:00',
            ],
            'country_id_from' => [
                'type' => 'integer',
                'description' => 'Location of departure, must be a country ID.',
                'example' => 'Kuwait',
            ],
            'airport_from' => [
                'type' => 'string',
                'description' => 'Airport code or name for departure.',
                'example' => 'KWI',
            ],
            'terminal_from' => [
                'type' => 'string',
                'description' => 'Departure terminal.',
                'example' => '1',
            ],
            'arrival_time' => [
                'type' => 'datetime',
                'description' => 'Arrival time of the flight.',
                'example' => '2024-10-16 16:00:00',
            ],
            'duration_time' => [
                'type' => 'string',
                'description' => 'Duration of the flight in Xh Ym format (e.g., 2h 5m, 1h 45m, 3h).',
                'example' => '2h 5m',
            ],
            'country_id_to' => [
                'type' => 'integer',
                'description' => 'Location of arrival, must be a country ID.',
                'example' => 'Singapore',
            ],
            'airport_to' => [
                'type' => 'string',
                'description' => 'Airport code or name for arrival.',
                'example' => 'SIN',
            ],
            'terminal_to' => [
                'type' => 'string',
                'description' => 'Arrival terminal.',
                'example' => '1',
            ],
            'airline_id' => [
                'type' => 'integer',
                'description' => 'Airline ID.',
                'example' => 'Kuwait Airways',
            ],
            'flight_number' => [
                'type' => 'string',
                'description' => 'Flight number.',
                'example' => 'KU-123',
            ],
            'class_type' => [
                'type' => 'string',
                'description' => 'Class type of the flight.',
                'example' => 'economy',
            ],
            'baggage_allowed' => [
                'type' => 'string',
                'description' => 'Baggage allowance.',
                'example' => 'baggage allowed',
            ],
            'equipment' => [
                'type' => 'string',
                'description' => 'Equipment used in the flight.',
                'example' => 'equipment',
            ],
            'ticket_number' => [
                'type' => 'string',
                'description' => 'Flight ticket number.',
                'example' => '3580878589',
            ],
            'flight_meal' => [
                'type' => 'string',
                'description' => 'Meal options during the flight.',
                'example' => 'flight meal',
            ],
            'seat_no' => [
                'type' => 'string',
                'description' => 'Seat number.',
                'example' => 'seat no',
            ],
        ];
    }

    public static function example(){
        $schema = static::getSchema();
        $example = [];

        foreach ($schema as $field => $details){
            $example[$field] = $details['example'] ?? '';
        }

        return $example;
    }
}