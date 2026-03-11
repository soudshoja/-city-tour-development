<?php

namespace App\GraphQL\Scalars;

use Carbon\Carbon;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Error\Error;

class ISODateTimeScalar extends ScalarType
{
    public string $name = 'ISODateTime';
    public ?string $description = 'DateTime scalar that supports ISO-8601 with timezone (e.g. 2025-07-09T19:00:00+03:00).';

    public function serialize($value)
    {
        return $value instanceof Carbon ? $value->toIso8601String() : (string) $value;
    }

    public function parseValue($value)
    {
        try {
            // Carbon handles ISO8601 with timezone correctly
            return Carbon::parse($value);
        } catch (\Exception $e) {
            throw new Error("Invalid ISODateTime format: {$e->getMessage()}");
        }
    }

    public function parseLiteral($ast, array $variables = null)
    {
        return $this->parseValue($ast->value);
    }
}
