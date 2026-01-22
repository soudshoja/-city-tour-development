<?php

namespace App\Schema;

class TaskInsuranceSchema
{
    public static function getSchema(): array
    {
        return [
            'date' => [
                'type' => 'string',
                'description' => 'Coverage ONLY (YYYY). Being label as السنة',
                'default' => null,
            ],
            'paid_leaves' => [
                'type' => 'integer',
                'description' => 'Number of approved paid leave days related to this coverage (if applicable). Being label as إجازة التأمين',
                'default' => null,
            ],
            'document_reference' => [
                'type' => 'string',
                'description' => 'Reference shown on the document (e.g., policy/certificate/application reference).',
                'default' => null,
            ],
            'insurance_type' => [
                'type' => 'string',
                'description' => 'Type of insurance.',
                'default' => null,
            ],
            'destination' => [
                'type' => 'string',
                'description' => 'Coverage destination/region (e.g., Worldwide, Schengen, Country name).',
                'default' => null,
            ],
            'plan_type' => [
                'type' => 'string',
                'description' => 'Plan type (e.g., Family Plan, Individual, Couple).',
                'default' => null,
            ],
            'duration' => [
                'type' => 'string',
                'description' => 'Covered duration text (e.g., "Up to 30 days", "14 days"). Leave as provided; do not derive dates.',
                'default' => null,
            ],
            'package' => [
                'type' => 'string',
                'description' => 'Package/tier name (e.g., Worldwide (Silver), Gold, Basic).',
                'default' => null,
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
