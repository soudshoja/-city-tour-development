<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class WebhookSigningService
{
    const SIGNATURE_ALGORITHM = 'sha256';
    const SIGNATURE_HEADER = 'X-Signature-SHA256';
    const TIMESTAMP_HEADER = 'X-Signature-Timestamp';
    const TIMESTAMP_TOLERANCE_SECONDS = 300; // 5 minutes

    /**
     * Sign a webhook payload with client secret
     *
     * @param string $payload JSON payload as string
     * @param string $secret Plaintext webhook secret
     * @param string $method HTTP method (POST, PUT, etc.)
     * @param string $path Request path
     * @param int|null $timestamp Unix timestamp (uses current time if null)
     * @return array ['signature' => 'hex', 'timestamp' => int]
     */
    public function signPayload(
        string $payload,
        string $secret,
        string $method = 'POST',
        string $path = '',
        ?int $timestamp = null
    ): array {
        $timestamp = $timestamp ?? time();

        // Build signing message
        $message = $this->buildSigningMessage($method, $path, $timestamp, $payload);

        // Create HMAC-SHA256 signature
        $signature = hash_hmac(self::SIGNATURE_ALGORITHM, $message, $secret);

        Log::debug('[Webhook] Signed payload', [
            'method' => $method,
            'path' => $path,
            'timestamp' => $timestamp,
            'signature' => substr($signature, 0, 16) . '...',
            'payload_length' => strlen($payload),
        ]);

        return [
            'signature' => $signature,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * Verify webhook signature from incoming request
     *
     * @param string $payload Request body as string
     * @param string $signatureProvided Signature from X-Signature-SHA256 header
     * @param int $timestampProvided Timestamp from X-Signature-Timestamp header
     * @param string $secret Plaintext webhook secret to verify against
     * @param string $method HTTP method
     * @param string $path Request path
     * @return array ['valid' => bool, 'reason' => string, 'computed_signature' => string]
     */
    public function verifySignature(
        string $payload,
        string $signatureProvided,
        int $timestampProvided,
        string $secret,
        string $method = 'POST',
        string $path = ''
    ): array {
        // Step 1: Check timestamp (prevent replay attacks)
        $timestampDiff = abs(time() - $timestampProvided);
        if ($timestampDiff > self::TIMESTAMP_TOLERANCE_SECONDS) {
            return [
                'valid' => false,
                'reason' => "Timestamp outside tolerance window ({$timestampDiff}s > " . self::TIMESTAMP_TOLERANCE_SECONDS . "s)",
                'computed_signature' => '',
            ];
        }

        // Step 2: Compute expected signature
        $message = $this->buildSigningMessage($method, $path, $timestampProvided, $payload);
        $computedSignature = hash_hmac(self::SIGNATURE_ALGORITHM, $message, $secret);

        // Step 3: Compare signatures (constant-time comparison)
        $valid = hash_equals($computedSignature, $signatureProvided);

        if (!$valid) {
            Log::warning('[Webhook] Signature mismatch', [
                'provided' => substr($signatureProvided, 0, 16) . '...',
                'computed' => substr($computedSignature, 0, 16) . '...',
                'method' => $method,
                'path' => $path,
            ]);
        }

        return [
            'valid' => $valid,
            'reason' => $valid ? 'Signature verified' : 'Signature mismatch',
            'computed_signature' => $computedSignature,
        ];
    }

    /**
     * Build message to be signed
     * Format: "{METHOD} {PATH}\n{TIMESTAMP}\n{PAYLOAD}"
     */
    private function buildSigningMessage(
        string $method,
        string $path,
        int $timestamp,
        string $payload
    ): string {
        return "{$method} {$path}\n{$timestamp}\n{$payload}";
    }

    /**
     * Hash secret for storage (never store plaintext)
     */
    public function hashSecret(string $secret): string
    {
        return Hash::make($secret);
    }

    /**
     * Generate a new webhook secret
     * @return string Hex-encoded 32-byte random secret
     */
    public function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }
}
