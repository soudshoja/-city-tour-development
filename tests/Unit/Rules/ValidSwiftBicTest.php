<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidSwiftBic;
use PHPUnit\Framework\TestCase;

/**
 * T14 "Supplier bank details per currency" -- SWIFT/BIC structural validation, no external
 * service.
 */
class ValidSwiftBicTest extends TestCase
{
    private function fails(string $value): bool
    {
        $failed = false;
        (new ValidSwiftBic)->validate('swift_bic', $value, function () use (&$failed) {
            $failed = true;
        });

        return $failed;
    }

    public function test_null_and_empty_are_allowed_the_field_is_optional(): void
    {
        $this->assertFalse($this->fails(''));
    }

    public function test_an_8_character_bic_passes(): void
    {
        $this->assertFalse($this->fails('DEUTDEFF'));
    }

    public function test_an_11_character_bic_with_branch_code_passes(): void
    {
        $this->assertFalse($this->fails('DEUTDEFF500'));
    }

    public function test_lowercase_is_normalized_and_passes(): void
    {
        $this->assertFalse($this->fails('deutdeff'));
    }

    public function test_wrong_length_fails(): void
    {
        $this->assertTrue($this->fails('DEUTDEFF5'), '9 characters is neither 8 nor 11.');
        $this->assertTrue($this->fails('DEUT'));
    }

    public function test_digits_in_the_bank_or_country_code_positions_fail(): void
    {
        // Positions 1-6 (bank code + country code) must be letters only.
        $this->assertTrue($this->fails('D3UTDEFF'));
    }

    public function test_completely_malformed_value_fails(): void
    {
        $this->assertTrue($this->fails('not-a-bic!!'));
    }
}
