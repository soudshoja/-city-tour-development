<?php

namespace App\Schema;

class TaskVisaSchema
{
    public static function getSchema(): array
    {
        return [
            'visa_type' => [
                'type' => 'string',
                'description' => 'Type or category of visa (e.g., common, business, student).',
                'example'     => 'common',
                'default' => '',
            ],
            'application_number' => [
                'type' => 'string',
                'description' => 'Issuing authority’s application or reference number.',
                'example'     => '8637300',
                'default' => '',
            ],
            'expiry_date' => [
                'type' => 'date',
                'description' => 'Date when the visa expires (Y-m-d).',
                'example' => '2024-02-10',
                'default' => null,
            ],
            'number_of_entries' => [
                'type' => 'string',
                'description' => 'Entries permitted. Use enum when known, or a number.',
                'enum'        => ['single', 'double', 'multiple'],
                'example'     => 'single',
                'default' => '',
            ],
            'stay_duration'=> [
                'type' => 'int',
                'description' => 'Maximum stay per entry in days.',
                'example'     => 14,
                'default' => null,
            ],
            'issuing_country' => [
                'type' => 'string',
                'description' => 'Country that issued the visa.',
                'example'     => 'Kuwait',
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