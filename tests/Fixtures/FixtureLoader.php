<?php

namespace Tests\Fixtures;

use Exception;
use Illuminate\Support\Facades\File;

/**
 * FixtureLoader - Helper class for loading test fixtures
 *
 * Provides methods to load test data for all document types:
 * - AIR files (plain text format)
 * - PDF extraction results (JSON)
 * - Image/OCR results (JSON)
 * - Email fixtures (JSON)
 */
class FixtureLoader
{
    /**
     * Base path for all fixtures
     */
    protected static string $basePath = '';

    /**
     * Initialize fixture base path
     */
    public static function initialize(string $basePath = null): void
    {
        self::$basePath = $basePath ?? base_path('tests/Fixtures');
    }

    /**
     * Load AIR file sample by name
     *
     * @param string $name - Fixture name (e.g., 'sample_booking', 'sample_reissue', 'malformed')
     * @return string - File content
     * @throws Exception
     */
    public static function loadAirSample(string $name): string
    {
        if (empty(self::$basePath)) {
            self::initialize();
        }

        $filePath = self::$basePath . '/air/' . $name . '.txt';

        if (!File::exists($filePath)) {
            throw new Exception("AIR fixture not found: {$filePath}");
        }

        return File::get($filePath);
    }

    /**
     * Load PDF extraction expected result
     *
     * @param string $name - Fixture name (e.g., 'expected_extraction', 'flight_booking', 'invoice')
     * @return array - Decoded JSON fixture
     * @throws Exception
     */
    public static function loadPdfExpectedResult(string $name): array
    {
        if (empty(self::$basePath)) {
            self::initialize();
        }

        $filePath = self::$basePath . '/pdf/' . $name . '.json';

        if (!File::exists($filePath)) {
            throw new Exception("PDF fixture not found: {$filePath}");
        }

        $content = File::get($filePath);
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in PDF fixture {$name}: " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Load Image/OCR extraction expected result
     *
     * @param string $name - Fixture name (e.g., 'expected_ocr', 'passport_extraction')
     * @return array - Decoded JSON fixture
     * @throws Exception
     */
    public static function loadImageExpectedResult(string $name): array
    {
        if (empty(self::$basePath)) {
            self::initialize();
        }

        $filePath = self::$basePath . '/image/' . $name . '.json';

        if (!File::exists($filePath)) {
            throw new Exception("Image fixture not found: {$filePath}");
        }

        $content = File::get($filePath);
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in Image fixture {$name}: " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Load email sample fixture
     *
     * @param string $name - Fixture name (e.g., 'booking_confirmation', 'supplier_notification', 'with_attachment')
     * @return array - Decoded JSON fixture
     * @throws Exception
     */
    public static function loadEmailSample(string $name): array
    {
        if (empty(self::$basePath)) {
            self::initialize();
        }

        $filePath = self::$basePath . '/email/' . $name . '.json';

        if (!File::exists($filePath)) {
            throw new Exception("Email fixture not found: {$filePath}");
        }

        $content = File::get($filePath);
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in Email fixture {$name}: " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Generate a mock N8n callback payload
     *
     * @param string $documentId - UUID of the document
     * @param string $type - Document type: 'air', 'pdf', 'image', 'email'
     * @param string $status - Status: 'success', 'failed', 'timeout', 'deferred'
     * @param array $overrides - Override values for the callback
     * @return array - N8n callback payload
     * @throws Exception
     */
    public static function generateN8nCallback(
        string $documentId,
        string $type = 'pdf',
        string $status = 'success',
        array $overrides = []
    ): array {
        $basePayload = [
            'documentId' => $documentId,
            'documentType' => $type,
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'executionId' => 'exec_' . uniqid(),
            'workflowId' => 'workflow_' . uniqid(),
        ];

        // Add status-specific data
        switch ($status) {
            case 'success':
                $basePayload['extractedData'] = self::getDefaultExtractionForType($type);
                $basePayload['processingTimeMs'] = rand(500, 5000);
                $basePayload['confidenceScore'] = rand(85, 100) / 100;
                break;

            case 'failed':
                $basePayload['errorCode'] = $overrides['errorCode'] ?? 'ERR_EXTRACTION_FAILED';
                $basePayload['errorMessage'] = $overrides['errorMessage'] ?? 'Failed to extract data from document';
                $basePayload['errorContext'] = [
                    'reason' => 'processing_error',
                    'details' => 'N8n workflow failed to process document',
                ];
                break;

            case 'timeout':
                $basePayload['errorCode'] = 'ERR_TIMEOUT';
                $basePayload['errorMessage'] = 'Processing timeout exceeded';
                $basePayload['timeoutMs'] = 30000;
                break;

            case 'deferred':
                $basePayload['status'] = 'deferred';
                $basePayload['retryAfterMs'] = rand(5000, 30000);
                $basePayload['reason'] = $overrides['reason'] ?? 'Rate limit reached';
                break;
        }

        return array_merge($basePayload, $overrides);
    }

    /**
     * Generate a valid webhook payload for testing
     *
     * @param array $overrides - Values to override in the default payload
     * @return array - Webhook payload
     */
    public static function generateWebhookPayload(array $overrides = []): array
    {
        $documentId = $overrides['documentId'] ?? 'doc_' . uniqid();
        $type = $overrides['documentType'] ?? 'pdf';
        $status = $overrides['status'] ?? 'success';

        $defaultPayload = [
            'documentId' => $documentId,
            'documentType' => $type,
            'status' => $status,
            'executionId' => $overrides['executionId'] ?? 'exec_' . uniqid(),
            'workflowId' => $overrides['workflowId'] ?? 'workflow_001',
            'timestamp' => $overrides['timestamp'] ?? now()->toIso8601String(),
            'callbackUrl' => config('services.n8n.callback_url'),
        ];

        // Add extraction data for successful callbacks
        if ($status === 'success') {
            $defaultPayload['extractedData'] = $overrides['extractedData'] ?? self::getDefaultExtractionForType($type);
            $defaultPayload['processingTimeMs'] = $overrides['processingTimeMs'] ?? rand(500, 5000);
            $defaultPayload['confidenceScore'] = $overrides['confidenceScore'] ?? 0.95;
        }

        // Add error data for failed callbacks
        if ($status === 'failed') {
            $defaultPayload['errorCode'] = $overrides['errorCode'] ?? 'ERR_GENERIC';
            $defaultPayload['errorMessage'] = $overrides['errorMessage'] ?? 'An error occurred';
            $defaultPayload['errorContext'] = $overrides['errorContext'] ?? [];
        }

        return $defaultPayload;
    }

    /**
     * Get default extraction result for a document type
     *
     * @param string $type - Document type: 'air', 'pdf', 'image', 'email'
     * @return array - Default extraction structure
     */
    protected static function getDefaultExtractionForType(string $type): array
    {
        return match ($type) {
            'air' => [
                'pnr' => 'ABC123',
                'status' => 'issued',
                'passengers' => [
                    [
                        'name' => 'Test Passenger',
                        'ticket_number' => 'T-K072-123456789',
                        'price' => 500.00,
                        'currency' => 'USD',
                    ]
                ],
            ],
            'pdf' => [
                'document_type' => 'flight_booking',
                'booking_reference' => 'BK123456',
                'passenger_name' => 'Test Passenger',
                'total_price' => 1500.00,
                'currency' => 'USD',
            ],
            'image' => [
                'raw_text' => 'Sample OCR text from image',
                'document_type' => 'passport',
                'passport_number' => 'A12345678',
                'name' => 'Test Passenger',
            ],
            'email' => [
                'email_type' => 'booking_confirmation',
                'booking_reference' => 'BK123456',
                'passenger_name' => 'Test Passenger',
                'from_address' => 'bookings@airline.com',
            ],
            default => [],
        };
    }

    /**
     * Get fixture file path without loading
     * Useful for testing file existence or getting paths
     *
     * @param string $type - Document type: 'air', 'pdf', 'image', 'email'
     * @param string $name - Fixture name
     * @return string - Full path to fixture file
     */
    public static function getFixturePath(string $type, string $name): string
    {
        if (empty(self::$basePath)) {
            self::initialize();
        }

        $extension = $type === 'air' ? 'txt' : 'json';
        return self::$basePath . '/' . $type . '/' . $name . '.' . $extension;
    }

    /**
     * List all available fixtures for a document type
     *
     * @param string $type - Document type: 'air', 'pdf', 'image', 'email'
     * @return array - Array of available fixture names
     */
    public static function listFixtures(string $type): array
    {
        if (empty(self::$basePath)) {
            self::initialize();
        }

        $dir = self::$basePath . '/' . $type;
        $extension = $type === 'air' ? 'txt' : 'json';

        if (!File::isDirectory($dir)) {
            return [];
        }

        $fixtures = [];
        foreach (File::files($dir) as $file) {
            if ($file->getExtension() === ltrim($extension, '.')) {
                $fixtures[] = $file->getFilenameWithoutExtension();
            }
        }

        return $fixtures;
    }
}
