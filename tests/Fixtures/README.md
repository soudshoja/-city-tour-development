# Test Fixtures for Document Processing

This directory contains comprehensive test fixtures and factories for all document types in the Soud Laravel application.

## Directory Structure

```
tests/Fixtures/
├── air/                              # AIR file fixtures (text format)
│   ├── sample_booking.txt           # Valid AIR booking file
│   ├── sample_reissue.txt           # Valid AIR reissue file
│   └── malformed.txt                # Invalid AIR format for error testing
├── pdf/                              # PDF extraction expected results (JSON)
│   ├── expected_extraction.json      # Standard PDF extraction result
│   ├── flight_booking.json           # Flight booking PDF example
│   └── invoice.json                  # Invoice PDF example
├── image/                            # Image/OCR extraction results (JSON)
│   ├── expected_ocr.json             # Standard OCR extraction result
│   └── passport_extraction.json      # Passport document OCR example
├── email/                            # Email fixture samples (JSON)
│   ├── booking_confirmation.json     # Booking confirmation email
│   ├── supplier_notification.json    # Supplier settlement email
│   └── with_attachment.json          # Email with multiple attachments
├── FixtureLoader.php                 # Helper class for loading fixtures
└── N8nResponseFactory.php            # Factory for mock N8n responses
```

## Fixture Types

### 1. AIR File Fixtures (`air/`)

Plain text files containing travel industry AIR (Airline Reporting) format data.

#### Files:
- **sample_booking.txt** - Valid AIR file with complete flight booking data
  - Contains: PNR, passenger info, flight segments, pricing, taxes
  - Status: Issued
  - Expected to parse successfully

- **sample_reissue.txt** - Valid AIR file with reissue transaction
  - Contains: Reissued flight segments with updated pricing
  - Status: Reissue (R flag)
  - Tests reissue processing logic

- **malformed.txt** - Invalid AIR format for error handling
  - Missing required segments
  - Incorrect structure
  - Should trigger parsing errors

### 2. PDF Extraction Fixtures (`pdf/`)

JSON files representing expected OCR/extraction results from PDF documents.

#### Files:
- **expected_extraction.json** - Generic PDF extraction template
  - Standard structure for PDF extraction results
  - Includes confidence scores and processing metadata
  - Use as reference for PDF extraction assertions

- **flight_booking.json** - Flight booking confirmation PDF
  - Sample: Qatar Airways business class booking
  - Includes: PNR, passenger, flights, pricing
  - High confidence score (0.99)

- **invoice.json** - Supplier invoice document
  - Travel agency invoice with line items
  - Includes: vendor, billing, tax calculation
  - Demonstrates invoice extraction structure

### 3. Image/OCR Fixtures (`image/`)

JSON files representing expected OCR results from images.

#### Files:
- **expected_ocr.json** - Generic OCR extraction template
  - Raw OCR text and structured data extraction
  - Includes confidence scores
  - Tests OCR processing pipeline

- **passport_extraction.json** - Passport document OCR
  - Extracts: Document number, name, DOB, expiry, MRZ
  - Validates document status
  - High precision results (0.99)

### 4. Email Fixtures (`email/`)

JSON files representing email content and extracted data.

#### Files:
- **booking_confirmation.json** - Airline booking confirmation email
  - Sample: British Airways booking confirmation
  - Extracts: Booking ref, passenger, flights, pricing
  - Includes both text and HTML content

- **supplier_notification.json** - Supplier monthly settlement email
  - Sample: Gulf Air settlement report
  - Extracts: Settlement period, totals, commission, breakdown
  - Includes attachment metadata

- **with_attachment.json** - Email with multiple document attachments
  - Sample: Complete travel itinerary email
  - Includes: E-ticket, hotel, car rental, insurance docs
  - 5 PDF attachments with hashes
  - Complex data extraction from email body

## Helper Classes

### FixtureLoader

Helper class for loading and managing test fixtures.

#### Key Methods:

```php
// Load AIR file content
FixtureLoader::loadAirSample('sample_booking');  // Returns string

// Load PDF expected results
FixtureLoader::loadPdfExpectedResult('flight_booking');  // Returns array

// Load OCR results
FixtureLoader::loadImageExpectedResult('passport_extraction');  // Returns array

// Load email fixtures
FixtureLoader::loadEmailSample('booking_confirmation');  // Returns array

// Generate mock N8n callback
FixtureLoader::generateN8nCallback('doc-uuid', 'pdf', 'success', [
    'extractedData' => [...],
]);

// Generate webhook payload
FixtureLoader::generateWebhookPayload([
    'documentId' => 'doc-123',
    'status' => 'success',
]);

// List available fixtures
FixtureLoader::listFixtures('air');  // Returns ['sample_booking', 'sample_reissue', 'malformed']

// Get fixture path
FixtureLoader::getFixturePath('pdf', 'flight_booking');  // Returns full path
```

### N8nResponseFactory

Factory for creating realistic mock N8n webhook responses for testing.

#### Key Methods:

```php
// Successful extraction response
N8nResponseFactory::success('doc-uuid', [
    'bookingRef' => 'ABC123',
    'passengerName' => 'John Doe',
]);

// Failure response
N8nResponseFactory::failure('doc-uuid', 'ERR_EXTRACTION_FAILED', 'Could not extract data');

// Timeout response
N8nResponseFactory::timeout('doc-uuid', ['timeoutMs' => 30000]);

// Deferred response (AIR files)
N8nResponseFactory::deferred('doc-uuid', 'Complex AIR file requires manual review');

// Specific error types
N8nResponseFactory::invalidFormat('doc-uuid');
N8nResponseFactory::corruptedFile('doc-uuid');
N8nResponseFactory::missingFields('doc-uuid', ['passengerName', 'bookingRef']);
N8nResponseFactory::rateLimited('doc-uuid');
N8nResponseFactory::serviceUnavailable('doc-uuid');

// Partial success (with warnings)
N8nResponseFactory::partialSuccess('doc-uuid', $data, [
    'Some fields could not be extracted',
]);

// Batch processing
N8nResponseFactory::batch([
    N8nResponseFactory::success('doc-1', [...]),
    N8nResponseFactory::failure('doc-2', 'ERR_INVALID_FORMAT'),
]);
```

#### Error Codes:
- `ERR_INVALID_FORMAT` - Invalid or unsupported document format
- `ERR_UNSUPPORTED_TYPE` - Document type not supported
- `ERR_EXTRACTION_FAILED` - Failed to extract data
- `ERR_TIMEOUT` - Processing timeout exceeded
- `ERR_RATE_LIMIT` - Rate limit exceeded
- `ERR_SERVICE_UNAVAILABLE` - Service unavailable
- `ERR_CORRUPTED_FILE` - File corrupted or invalid
- `ERR_MISSING_FIELDS` - Missing required fields

## Usage Examples

### Testing AIR File Parsing

```php
<?php

namespace Tests\Unit\Services;

use Tests\Fixtures\FixtureLoader;

class AirParserTest extends TestCase
{
    public function test_parse_valid_booking()
    {
        $content = FixtureLoader::loadAirSample('sample_booking');
        $parser = new AirFileParser();

        $result = $parser->parse($content);

        $this->assertNotNull($result);
        $this->assertEquals('issued', $result['passengers'][0]['status']);
    }

    public function test_handle_malformed_air_file()
    {
        $content = FixtureLoader::loadAirSample('malformed');
        $parser = new AirFileParser();

        $this->expectException(ParsingException::class);
        $parser->parse($content);
    }
}
```

### Testing PDF Extraction with Mock N8n Responses

```php
<?php

namespace Tests\Feature\DocumentProcessing;

use Tests\Fixtures\FixtureLoader;
use Tests\Fixtures\N8nResponseFactory;

class PdfExtractionTest extends TestCase
{
    public function test_handle_successful_pdf_extraction()
    {
        $documentId = 'doc-' . uniqid();
        $expectedData = FixtureLoader::loadPdfExpectedResult('flight_booking');

        // Mock N8n webhook response
        $response = N8nResponseFactory::success(
            $documentId,
            $expectedData['extracted_data']
        );

        // Simulate processing
        $this->processDocumentCallback($response);

        // Assert results in database
        $this->assertDatabaseHas('document_processing_logs', [
            'document_id' => $documentId,
            'status' => 'completed',
        ]);
    }

    public function test_handle_pdf_extraction_timeout()
    {
        $documentId = 'doc-' . uniqid();

        $response = N8nResponseFactory::timeout($documentId, [
            'timeoutMs' => 30000,
        ]);

        $this->processDocumentCallback($response);

        // Should be retried
        $log = DocumentProcessingLog::find($documentId);
        $this->assertTrue($log->errors()->first()->isTransient());
    }
}
```

### Testing Email Parsing with Attachments

```php
<?php

namespace Tests\Unit\EmailProcessing;

use Tests\Fixtures\FixtureLoader;

class EmailParserTest extends TestCase
{
    public function test_extract_booking_confirmation_email()
    {
        $fixture = FixtureLoader::loadEmailSample('booking_confirmation');
        $parser = new EmailParser();

        $result = $parser->parseEmailContent($fixture);

        $this->assertEquals('BA', $result['outbound_flight']['airline_code']);
        $this->assertEquals('BA112', $result['outbound_flight']['flight_number']);
    }

    public function test_handle_email_with_multiple_attachments()
    {
        $fixture = FixtureLoader::loadEmailSample('with_attachment');

        $this->assertCount(5, $fixture['attachments']);
        $this->assertEquals('E-Ticket-XYZ789.pdf', $fixture['attachments'][0]['filename']);
    }
}
```

### Testing Webhook Payload Processing

```php
<?php

namespace Tests\Feature\Webhooks;

use Tests\Fixtures\FixtureLoader;
use Tests\Fixtures\N8nResponseFactory;

class WebhookProcessingTest extends TestCase
{
    public function test_process_document_success_callback()
    {
        $documentId = 'doc-' . uniqid();
        $payload = FixtureLoader::generateWebhookPayload([
            'documentId' => $documentId,
            'documentType' => 'pdf',
            'status' => 'success',
            'extractedData' => [
                'bookingRef' => 'BK123456',
                'totalPrice' => 1500.00,
            ],
        ]);

        // Send webhook
        $this->postJson('/api/webhooks/document-processing', $payload)
            ->assertSuccessful();

        // Verify processing
        $log = DocumentProcessingLog::where('document_id', $documentId)->first();
        $this->assertEquals('completed', $log->status);
    }
}
```

## Adding New Fixtures

### Adding a New AIR File Fixture

1. Create a `.txt` file in `tests/Fixtures/air/`
2. Use valid AIR format (see existing samples)
3. Name it descriptively: `{description}.txt`
4. Load in tests: `FixtureLoader::loadAirSample('{description}')`

### Adding a New PDF/Image Fixture

1. Create a `.json` file in appropriate directory
2. Use this structure:
```json
{
    "document_type": "...",
    "extraction_status": "success",
    "extracted_data": { ... },
    "confidence_score": 0.95,
    "processing_time_ms": 1500
}
```
3. Load in tests: `FixtureLoader::loadPdfExpectedResult('{name}')`

### Adding a New Email Fixture

1. Create a `.json` file in `tests/Fixtures/email/`
2. Include both raw and structured data
3. Add realistic metadata
4. Load in tests: `FixtureLoader::loadEmailSample('{name}')`

## Important Notes

- All fixtures use realistic, anonymized data for testing
- JSON fixtures should be valid and well-formatted
- AIR files should follow proper format specifications
- Confidence scores are 0-1 decimal format
- Processing times are in milliseconds
- Timestamps use ISO 8601 format

## Integration with Tests

These fixtures are designed for:
- Unit testing parsers and extractors
- Integration testing webhook handling
- Mocking N8n workflow responses
- Testing error handling and retry logic
- Performance testing with realistic data

Use `FixtureLoader` and `N8nResponseFactory` in your test classes to maintain consistency and avoid duplicating fixture data.
