<?php

namespace App\Schema;

class TaskFlightSchema
{
    public static function getSchema()
    {
        return [
            'farebase' => [
                'type' => 'float',
                'description' => 'Base fare amount of the flight ticket before taxes and fees. Look for labels like "Base Fare", "Fare", "Net Fare", or similar pricing information.',
                'example' => '20.00',
                'default' => 0.0,
            ],
            'departure_time' => [
                'type' => 'datetime',
                'description' => 'Scheduled departure date and time of the flight. Look for departure time, ETD (Estimated Time of Departure), or scheduled departure information in format YYYY-MM-DD HH:MM:SS.',
                'example' => '2024-10-16 14:00:00',
                'default' => null,
            ],
            'country_id_from' => [
                'type' => 'integer',
                'description' => 'Departure country name or country where the flight originates from. Extract the full country name from departure city/airport information.',
                'example' => 'Kuwait',
                'default' => null,
            ],
            'airport_from' => [
                'type' => 'string',
                'description' => 'Departure airport code (IATA/ICAO) or airport name. Look for 3-letter codes like KWI, DXB, or full airport names in departure information.',
                'example' => 'KWI',
                'default' => '',
            ],
            'terminal_from' => [
                'type' => 'string',
                'description' => 'Departure terminal number or identifier. Look for terminal information associated with departure details, often labeled as "Terminal", "Term", or "T".',
                'example' => '1',
                'default' => '',
            ],
            'arrival_time' => [
                'type' => 'datetime',
                'description' => 'Scheduled arrival date and time of the flight. Look for arrival time, ETA (Estimated Time of Arrival), or scheduled arrival information in format YYYY-MM-DD HH:MM:SS.',
                'example' => '2024-10-16 16:00:00',
                'default' => null,
            ],
            'duration_time' => [
                'type' => 'string',
                'description' => 'Total flight duration or travel time. Look for flight duration, travel time, or time difference between departure and arrival in format like "2h 5m", "1h 45m", or "3h".',
                'example' => '2h 5m',
                'default' => '',
            ],
            'country_id_to' => [
                'type' => 'integer',
                'description' => 'Arrival country name or destination country where the flight arrives. Extract the full country name from arrival city/airport information.',
                'example' => 'Singapore',
                'default' => null,
            ],
            'airport_to' => [
                'type' => 'string',
                'description' => 'Arrival airport code (IATA/ICAO) or airport name. Look for 3-letter codes like SIN, LHR, or full airport names in arrival/destination information.',
                'example' => 'SIN',
                'default' => '',
            ],
            'terminal_to' => [
                'type' => 'string',
                'description' => 'Arrival terminal number or identifier. Look for terminal information associated with arrival details, often labeled as "Terminal", "Term", or "T".',
                'example' => '1',
                'default' => '',
            ],
            'airline_id' => [
                'type' => 'integer',
                'description' => 'Airline name or carrier operating the flight. Look for airline names like "Kuwait Airways", "Emirates", "British Airways", or airline codes in flight information.',
                'example' => 'Kuwait Airways',
                'default' => null,
            ],
            'flight_number' => [
                'type' => 'string',
                'description' => 'Flight number or flight code. Look for alphanumeric flight identifiers like "KU-123", "EK456", "BA789", usually prefixed with airline code.',
                'example' => 'KU-123',
                'default' => '',
            ],
            'class_type' => [
                'type' => 'string',
                'description' => 'Travel class or cabin class of the booking. Look for class information like "Economy", "Business", "First Class", "Premium Economy", or class codes like "Y", "C", "F". Other than that, do not use and set it null.',
                'example' => 'economy',
                'default' => '',
            ],
            'baggage_allowed' => [
                'type' => 'string',
                'description' => 'Baggage allowance or luggage policy details. Look for baggage information, weight limits, piece allowance, or baggage policies mentioned in the document.',
                'example' => 'baggage allowed',
                'default' => '',
            ],
            'equipment' => [
                'type' => 'string',
                'description' => 'Aircraft type or equipment used for the flight. Look for aircraft model information like "Boeing 777", "Airbus A380", or aircraft codes.',
                'example' => 'equipment',
                'default' => '',
            ],
            'ticket_number' => [
                'type' => 'string',
                'description' => 'Flight ticket number or e-ticket number. Look for ticket numbers, e-ticket references, or document numbers, usually 10-13 digit numbers that may be prefixed with airline codes.',
                'example' => '3580878589',
                'default' => '',
            ],
            'flight_meal' => [
                'type' => 'string',
                'description' => 'Meal service or special meal requests for the flight. Look for meal information, dietary requirements, or meal codes mentioned in the booking details.',
                'example' => 'flight meal',
                'default' => '',
            ],
            'seat_no' => [
                'type' => 'string',
                'description' => 'Assigned seat number on the aircraft. Look for seat assignments, seat numbers, or seating information like "12A", "25F", "1B" in the ticket details.',
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
