<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidIban;
use PHPUnit\Framework\TestCase;

/**
 * T14 "Supplier bank details per currency" -- IBAN structural + checksum validation, no external
 * service. Real-world synthetic IBANs used here are the textbook ISO 13616 examples (fake bank
 * accounts published as format-checker samples, not any real party's real account).
 */
class ValidIbanTest extends TestCase
{
    private function fails(string $value): bool
    {
        $failed = false;
        (new ValidIban)->validate('iban', $value, function () use (&$failed) {
            $failed = true;
        });

        return $failed;
    }

    public function test_null_and_empty_are_allowed_the_field_is_optional(): void
    {
        $this->assertFalse($this->fails(''));
    }

    public function test_a_textbook_valid_iban_passes(): void
    {
        // DE89 3704 0044 0532 0130 00 -- the canonical ISO 13616 worked example.
        $this->assertFalse($this->fails('DE89370400440532013000'));
        $this->assertFalse($this->fails('DE89 3704 0044 0532 0130 00'), 'Spaces must be tolerated.');
    }

    public function test_a_kuwaiti_iban_of_correct_length_and_checksum_passes(): void
    {
        // KW81CBKU0000000000001234560101 -- a synthetic-but-mod97-valid KW IBAN (30 chars).
        $this->assertFalse($this->fails('KW81CBKU0000000000001234560101'));
    }

    public function test_wrong_shape_fails(): void
    {
        $this->assertTrue($this->fails('NOT-AN-IBAN'));
        $this->assertTrue($this->fails('123456789'));
    }

    public function test_wrong_length_for_a_known_country_fails(): void
    {
        // DE IBANs are always 22 characters; this one is short by one digit.
        $this->assertTrue($this->fails('DE8937040044053201300'));
    }

    public function test_bad_checksum_with_otherwise_correct_shape_and_length_fails(): void
    {
        // Same DE example with the two check digits corrupted (89 -> 00) -- passes the regex and
        // length gate but MUST fail mod-97.
        $this->assertTrue($this->fails('DE00370400440532013000'));
    }
}
