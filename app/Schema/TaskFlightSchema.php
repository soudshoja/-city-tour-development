<?php

namespace App\Schema;

class TaskFlightSchema
{
    public static function getSchema()
    {
        return [
            'farebase' => [
                'type' => 'float',
                'description' => 'Fare basis of the flight.',
                'example' => '20.00',
                'default' => 0.0,
            ],
            'departure_time' => [
                'type' => 'datetime',
                'description' => 'Departure time of the flight.',
                'example' => '2024-10-16 14:00:00',
                'default' => null,
            ],
            'country_id_from' => [
                'type' => 'integer',
                'description' => 'Location of departure, must be a country ID.',
                'example' => 'Kuwait',
                'default' => null,
            ],
            'airport_from' => [
                'type' => 'string',
                'description' => 'Airport code or name for departure.',
                'example' => 'KWI',
                'default' => '',
            ],
            'terminal_from' => [
                'type' => 'string',
                'description' => 'Departure terminal.',
                'example' => '1',
                'default' => '',
            ],
            'arrival_time' => [
                'type' => 'datetime',
                'description' => 'Arrival time of the flight.',
                'example' => '2024-10-16 16:00:00',
                'default' => null,
            ],
            'duration_time' => [
                'type' => 'string',
                'description' => 'Duration of the flight in Xh Ym format (e.g., 2h 5m, 1h 45m, 3h).',
                'example' => '2h 5m',
                'default' => '',
            ],
            'country_id_to' => [
                'type' => 'integer',
                'description' => 'Location of arrival, must be a country ID.',
                'example' => 'Singapore',
                'default' => null,
            ],
            'airport_to' => [
                'type' => 'string',
                'description' => 'Airport code or name for arrival.',
                'example' => 'SIN',
                'default' => '',
            ],
            'terminal_to' => [
                'type' => 'string',
                'description' => 'Arrival terminal.',
                'example' => '1',
                'default' => '',
            ],
            'airline_id' => [
                'type' => 'integer',
                'description' => 'Airline ID.',
                'example' => 'Kuwait Airways',
                'default' => null,
            ],
            'flight_number' => [
                'type' => 'string',
                'description' => 'Flight number.',
                'example' => 'KU-123',
                'default' => '',
            ],
            'class_type' => [
                'type' => 'string',
                'description' => 'Class type of the flight.',
                'example' => 'economy',
                'default' => '',
            ],
            'baggage_allowed' => [
                'type' => 'string',
                'description' => 'Baggage allowance.',
                'example' => 'baggage allowed',
                'default' => '',
            ],
            'equipment' => [
                'type' => 'string',
                'description' => 'Equipment used in the flight.',
                'example' => 'equipment',
                'default' => '',
            ],
            'ticket_number' => [
                'type' => 'string',
                'description' => 'Flight ticket number.',
                'example' => '3580878589',
                'default' => '',
            ],
            'flight_meal' => [
                'type' => 'string',
                'description' => 'Meal options during the flight.',
                'example' => 'flight meal',
                'default' => '',
            ],
            'seat_no' => [
                'type' => 'string',
                'description' => 'Seat number.',
                'example' => 'seat no',
                'default' => '',
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

    public static function normalize(array $input)
    {
        $schema = static::getSchema();
        $normalized = [];
        foreach ($schema as $field => $meta) {
            $normalized[$field] = array_key_exists($field, $input)
                ? $input[$field]
                : ($meta['default'] ?? $meta['example'] ?? null);
        }
        return $normalized;
    }

}

