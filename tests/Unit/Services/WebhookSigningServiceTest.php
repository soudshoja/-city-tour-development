<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\WebhookSigningService;

class WebhookSigningServiceTest extends TestCase
{
    private WebhookSigningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WebhookSigningService();
    }

    public function test_sign_payload_generates_valid_signature()
    {
        $payload = '{"test":"data"}';
        $secret = 'test-secret-key';
        $method = 'POST';
        $path = '/webhook/test';

        $result = $this->service->signPayload($payload, $secret, $method, $path);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('signature', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertIsString($result['signature']);
        $this->assertIsInt($result['timestamp']);
        $this->assertEquals(64, strlen($result['signature'])); // SHA256 hex = 64 chars
    }

    public function test_verify_signature_accepts_valid_signature()
    {
        $payload = '{"test":"data"}';
        $secret = 'test-secret-key';
        $method = 'POST';
        $path = '/webhook/test';

        // Sign the payload
        $signedData = $this->service->signPayload($payload, $secret, $method, $path);

        // Verify the signature
        $result = $this->service->verifySignature(
            $payload,
            $signedData['signature'],
            $signedData['timestamp'],
            $secret,
            $method,
            $path
        );

        $this->assertTrue($result['valid']);
        $this->assertEquals('Signature verified', $result['reason']);
    }

    public function test_verify_signature_rejects_invalid_signature()
    {
        $payload = '{"test":"data"}';
        $secret = 'test-secret-key';
        $invalidSignature = 'invalid-signature-12345';
        $timestamp = time();

        $result = $this->service->verifySignature(
            $payload,
            $invalidSignature,
            $timestamp,
            $secret,
            'POST',
            '/webhook/test'
        );

        $this->assertFalse($result['valid']);
        $this->assertEquals('Signature mismatch', $result['reason']);
    }

    public function test_verify_signature_rejects_expired_timestamp()
    {
        $payload = '{"test":"data"}';
        $secret = 'test-secret-key';
        $oldTimestamp = time() - 400; // 400 seconds ago (exceeds 300s tolerance)

        $signedData = $this->service->signPayload($payload, $secret, 'POST', '/webhook/test', $oldTimestamp);

        $result = $this->service->verifySignature(
            $payload,
            $signedData['signature'],
            $oldTimestamp,
            $secret,
            'POST',
            '/webhook/test'
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Timestamp outside tolerance', $result['reason']);
    }

    public function test_verify_signature_rejects_tampered_payload()
    {
        $originalPayload = '{"test":"data"}';
        $tamperedPayload = '{"test":"tampered"}';
        $secret = 'test-secret-key';

        // Sign original payload
        $signedData = $this->service->signPayload($originalPayload, $secret, 'POST', '/webhook/test');

        // Try to verify with tampered payload
        $result = $this->service->verifySignature(
            $tamperedPayload,
            $signedData['signature'],
            $signedData['timestamp'],
            $secret,
            'POST',
            '/webhook/test'
        );

        $this->assertFalse($result['valid']);
        $this->assertEquals('Signature mismatch', $result['reason']);
    }

    public function test_generate_secret_creates_64_char_hex_string()
    {
        $secret = $this->service->generateSecret();

        $this->assertIsString($secret);
        $this->assertEquals(64, strlen($secret)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $secret);
    }

    public function test_hash_secret_creates_bcrypt_hash()
    {
        $plainSecret = 'my-secret-key';
        $hashed = $this->service->hashSecret($plainSecret);

        $this->assertIsString($hashed);
        $this->assertStringStartsWith('$2y$', $hashed); // bcrypt hash prefix
    }

    public function test_signatures_differ_for_different_methods()
    {
        $payload = '{"test":"data"}';
        $secret = 'test-secret-key';
        $path = '/webhook/test';
        $timestamp = time();

        $postSignature = $this->service->signPayload($payload, $secret, 'POST', $path, $timestamp);
        $getSignature = $this->service->signPayload($payload, $secret, 'GET', $path, $timestamp);

        $this->assertNotEquals($postSignature['signature'], $getSignature['signature']);
    }

    public function test_signatures_differ_for_different_paths()
    {
        $payload = '{"test":"data"}';
        $secret = 'test-secret-key';
        $timestamp = time();

        $path1Signature = $this->service->signPayload($payload, $secret, 'POST', '/webhook/test1', $timestamp);
        $path2Signature = $this->service->signPayload($payload, $secret, 'POST', '/webhook/test2', $timestamp);

        $this->assertNotEquals($path1Signature['signature'], $path2Signature['signature']);
    }
}
