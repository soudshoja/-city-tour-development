<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * T14 "Supplier bank details per currency" -- structural SWIFT/BIC validation only (ISO 9362
 * shape + length), never an external verification service. A valid BIC is exactly 8 or 11
 * characters: 4 letters (bank code) + 2 letters (ISO 3166-1 country code) + 2 alphanumeric
 * (location code) + an optional 3 alphanumeric (branch code, "XXX" for the primary office).
 */
final class ValidSwiftBic implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $bic = strtoupper(trim((string) $value));

        if (! preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $bic)) {
            $fail('The :attribute is not a valid SWIFT/BIC code (expected 8 or 11 characters: bank code + country code + location code + optional branch code).');
        }
    }
}
