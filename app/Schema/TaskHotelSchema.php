<?php

namespace App\Schema;

class TaskHotelSchema
{
    public static function getSchema(): array
    {
        return [
            'hotel_name' => [
                'type' => 'string',
                'description' => 'Name of the hotel.',
                'default' => '',
            ],
            'booking_time' => [
                'type' => 'datetime',
                'description' => 'Time when the hotel was booked.',
                'default' => null,
            ],
            'check_in' => [
                'type' => 'datetime',
                'description' => 'Check-in date and time.',
                'default' => null,
            ],
            'check_out' => [
                'type' => 'datetime',
                'description' => 'Check-out date and time.',
                'default' => null,
            ],
            'room_reference' => [
                'type' => 'string',
                'description' => 'Reference number for the room booking.',
                'default' => '',
            ],
            'room_number' => [
                'type' => 'string',
                'description' => 'Room number assigned to the booking.',
                'default' => '',
            ],
            'room_type' => [
                'type' => 'string',
                'description' => 'Type of room booked (e.g., single, double, suite).',
                'default' => '',
            ],
            'room_amount' => [
                'type' => 'float',
                'description' => 'Amount charged for the room.',
                'default' => 0.0,
            ],
            'room_details' => [
                'type' => 'string',
                'description' => 'Details about the room (e.g., amenities, features).',
                'default' => '',
            ],
            'room_promotion' => [
                'type' => 'string',
                'description' => 'Promotion or discount applied to the room booking.',
                'default' => '',
            ],
            'rate' => [
                'type' => 'float',
                'description' => 'Rate per night for the room.',
                'default' => 0.0,
            ],
            'meal_type' => [
                'type' => 'string',
                'description' => 'Type of meal included (e.g., breakfast, half-board, full-board).',
                'default' => '',
            ],
            'is_refundable' => [
                'type' => 'boolean',
                'description' => 'Indicates if the booking is refundable.',
                'default' => false,
            ],
            'supplements' => [
                'type' => 'string',
                'description' => 'Additional services or charges associated with the booking (e.g., spa, parking).',
                'default' => '',
            ],
        ];
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
