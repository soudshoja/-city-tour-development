<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * T14 "Supplier bank details per currency" -- structural IBAN validation only (format + length),
 * NEVER an external verification service (owner spec: "no external service"). Checks:
 *   1. Shape: 2 letters (ISO 3166-1 alpha-2 country code) + 2 digits (checksum) + up to 30
 *      alphanumeric characters (BBAN), matching the ISO 13616 layout every real IBAN follows.
 *   2. Per-country length: ISO 13616 fixes a total length per country (e.g. DE=22, GB=22,
 *      KW=30, FR=27); {@see self::LENGTH_BY_COUNTRY} carries the common set. An unknown country
 *      code falls back to the general 15-34 bound only (never rejected outright -- the registry
 *      is a courtesy, not a gate on every country in the world).
 *   3. Mod-97 checksum (ISO 7064 MOD 97-10): rearrange BBAN+country+check digits, letters -> two
 *      digit numbers (A=10..Z=35), and the whole numeral mod 97 must equal 1. This catches
 *      transposition/typo errors without ever calling out to a bank registry.
 */
final class ValidIban implements ValidationRule
{
    /** ISO 13616 fixed total length per country -- the common set; not exhaustive by design. */
    private const LENGTH_BY_COUNTRY = [
        'AE' => 23, 'AT' => 20, 'BE' => 16, 'BH' => 22, 'CH' => 21, 'CY' => 28, 'DE' => 22,
        'DK' => 18, 'EG' => 29, 'ES' => 24, 'FI' => 18, 'FR' => 27, 'GB' => 22, 'GR' => 27,
        'IE' => 22, 'IT' => 27, 'JO' => 30, 'KW' => 30, 'LB' => 28, 'LU' => 20, 'NL' => 18,
        'NO' => 15, 'PL' => 28, 'PT' => 25, 'QA' => 29, 'SA' => 24, 'SE' => 24, 'TR' => 26,
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $iban = strtoupper(str_replace(' ', '', (string) $value));

        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
            $fail('The :attribute is not a valid IBAN (expected 2-letter country code + 2-digit checksum + account identifier).');

            return;
        }

        $length = mb_strlen($iban);

        if ($length < 15 || $length > 34) {
            $fail('The :attribute is not a valid IBAN length.');

            return;
        }

        $country = mb_substr($iban, 0, 2);
        $expectedLength = self::LENGTH_BY_COUNTRY[$country] ?? null;

        if ($expectedLength !== null && $length !== $expectedLength) {
            $fail("The :attribute is not a valid IBAN length for {$country} (expected {$expectedLength} characters).");

            return;
        }

        if (! self::passesMod97Checksum($iban)) {
            $fail('The :attribute failed the IBAN checksum.');
        }
    }

    private static function passesMod97Checksum(string $iban): bool
    {
        $rearranged = mb_substr($iban, 4).mb_substr($iban, 0, 4);

        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        // bcmod isn't guaranteed available; do the mod-97 reduction on chunks of the digit string
        // instead of requiring the bcmath extension for what is otherwise a pure-string check.
        $remainder = 0;
        foreach (str_split($numeric) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder === 1;
    }
}
