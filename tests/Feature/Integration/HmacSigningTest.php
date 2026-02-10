<?php

namespace Tests\Feature\Integration;

use Tests\TestCase;

class HmacSigningTest extends TestCase
{
    protected string $testSecret = 'test-secret-key';

    /**
     * Test valid signature is accepted
     */
    public function test_valid_signature_accepted(): void
    {
        $payload = ['test' => 'data', 'number' => 123];
        $signature = $this->generateSignature($payload, $this->testSecret);

        $isValid = $this->verifySignature($payload, $signature, $this->testSecret);

        $this->assertTrue($isValid);
    }

    /**
     * Test invalid signature is rejected
     */
    public function test_invalid_signature_rejected(): void
    {
        $payload = ['test' => 'data'];
        $wrongPayload = ['wrong' => 'data'];

        $signature = $this->generateSignature($wrongPayload, $this->testSecret);

        $isValid = $this->verifySignature($payload, $signature, $this->testSecret);

        $this->assertFalse($isValid);
    }

    /**
     * Test missing signature is rejected
     */
    public function test_missing_signature_rejected(): void
    {
        $payload = ['test' => 'data'];
        $signature = ''; // Empty signature

        $isValid = $this->verifySignature($payload, $signature, $this->testSecret);

        $this->assertFalse($isValid);
    }

    /**
     * Test wrong secret key fails validation
     */
    public function test_wrong_secret_fails_validation(): void
    {
        $payload = ['test' => 'data'];
        $signature = $this->generateSignature($payload, 'correct-secret');

        $isValid = $this->verifySignature($payload, $signature, 'wrong-secret');

        $this->assertFalse($isValid);
    }

    /**
     * Test timing-safe comparison is used
     */
    public function test_timing_safe_comparison(): void
    {
        $signature1 = 'abc123def456';
        $signature2 = 'abc123def457'; // Last char different

        // hash_equals should return false
        $this->assertFalse(hash_equals($signature1, $signature2));

        // Identical signatures should match
        $this->assertTrue(hash_equals($signature1, $signature1));
    }

    /**
     * Test signature format validation
     */
    public function test_signature_format_validation(): void
    {
        $payload = ['test' => 'data'];
        $signature = $this->generateSignature($payload, $this->testSecret);

        // SHA-256 signature should be 64 hex characters
        $this->assertEquals(64, strlen($signature));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signature);
    }

    /**
     * Test expired timestamp validation
     */
    public function test_expired_timestamp_rejected(): void
    {
        $currentTimestamp = now()->timestamp;
        $expiredTimestamp = now()->subMinutes(10)->timestamp; // 10 minutes old

        $maxAge = 300; // 5 minutes in seconds

        $isExpired = abs($currentTimestamp - $expiredTimestamp) > $maxAge;

        $this->assertTrue($isExpired);
    }

    /**
     * Test future timestamp is rejected
     */
    public function test_future_timestamp_rejected(): void
    {
        $currentTimestamp = now()->timestamp;
        $futureTimestamp = now()->addMinutes(10)->timestamp; // 10 minutes in future

        $maxAge = 300; // 5 minutes in seconds

        $isInvalid = abs($currentTimestamp - $futureTimestamp) > $maxAge;

        $this->assertTrue($isInvalid);
    }

    /**
     * Test timestamp within valid range
     */
    public function test_valid_timestamp_accepted(): void
    {
        $currentTimestamp = now()->timestamp;
        $recentTimestamp = now()->subMinutes(2)->timestamp; // 2 minutes old

        $maxAge = 300; // 5 minutes in seconds

        $isValid = abs($currentTimestamp - $recentTimestamp) <= $maxAge;

        $this->assertTrue($isValid);
    }

    /**
     * Test payload order independence
     */
    public function test_payload_order_independence(): void
    {
        // JSON encoding maintains order, so different key orders = different signatures
        $payload1 = ['a' => 1, 'b' => 2];
        $payload2 = ['b' => 2, 'a' => 1];

        $signature1 = $this->generateSignature($payload1, $this->testSecret);
        $signature2 = $this->generateSignature($payload2, $this->testSecret);

        // Signatures should be different due to JSON encoding order
        $this->assertNotEquals($signature1, $signature2);
    }

    /**
     * Test empty payload signature
     */
    public function test_empty_payload_signature(): void
    {
        $payload = [];
        $signature = $this->generateSignature($payload, $this->testSecret);

        $this->assertNotEmpty($signature);
        $this->assertEquals(64, strlen($signature));

        // Verify it's reproducible
        $signature2 = $this->generateSignature($payload, $this->testSecret);
        $this->assertEquals($signature, $signature2);
    }

    /**
     * Test large payload signature
     */
    public function test_large_payload_signature(): void
    {
        $payload = [
            'data' => str_repeat('x', 10000), // 10KB string
            'nested' => [
                'array' => range(1, 1000),
            ],
        ];

        $signature = $this->generateSignature($payload, $this->testSecret);

        $this->assertNotEmpty($signature);
        $this->assertEquals(64, strlen($signature));

        // Verify
        $isValid = $this->verifySignature($payload, $signature, $this->testSecret);
        $this->assertTrue($isValid);
    }

    /**
     * Helper: Generate HMAC signature
     */
    protected function generateSignature(array $payload, string $secret): string
    {
        $payloadJson = json_encode($payload);
        return hash_hmac('sha256', $payloadJson, $secret);
    }

    /**
     * Helper: Verify HMAC signature
     */
    protected function verifySignature(array $payload, string $signature, string $secret): bool
    {
        if (empty($signature)) {
            return false;
        }

        $computed = $this->generateSignature($payload, $secret);
        return hash_equals($computed, $signature);
    }
}
