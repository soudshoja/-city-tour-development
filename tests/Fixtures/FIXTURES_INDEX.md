# Fixtures Index

Quick reference guide to all available test fixtures.

## AIR Files (3)

### 1. sample_booking.txt
- **Path**: `tests/Fixtures/air/sample_booking.txt`
- **Status**: ✅ Valid
- **Type**: Flight booking
- **Load**: `FixtureLoader::loadAirSample('sample_booking')`
- **Contains**:
  - PNR: ABC123XYZ
  - Passenger: ALAZMI/ABDULLAH M A Z
  - Segments: 4 flights (KWI→BAH→BKK→BAH→KWI)
  - Pricing: KWD 176.700 total
  - Status: Issued
- **Use Cases**: Parsing valid AIR files, multi-segment flights

### 2. sample_reissue.txt
- **Path**: `tests/Fixtures/air/sample_reissue.txt`
- **Status**: ✅ Valid
- **Type**: Reissue transaction
- **Load**: `FixtureLoader::loadAirSample('sample_reissue')`
- **Contains**:
  - PNR: ABC123XYZ (reissue)
  - Passenger: AHMED/KHALID A H
  - Segments: 4 flights (KWI→DXB→BKK→DXB→KWI)
  - New pricing: KWD 165.000
  - Status: Reissue (R flag)
- **Use Cases**: Processing reissues, price changes

### 3. malformed.txt
- **Path**: `tests/Fixtures/air/malformed.txt`
- **Status**: ❌ Invalid
- **Type**: Corrupted/invalid format
- **Load**: `FixtureLoader::loadAirSample('malformed')`
- **Issues**:
  - Missing required segments
  - No ENDX terminator
  - Incomplete field structure
- **Use Cases**: Error handling, validation tests

## PDF Extraction Fixtures (3)

### 1. expected_extraction.json
- **Path**: `tests/Fixtures/pdf/expected_extraction.json`
- **Type**: Generic flight booking
- **Load**: `FixtureLoader::loadPdfExpectedResult('expected_extraction')`
- **Structure**:
  ```
  Booking: ABC123XYZ
  Passenger: John Doe
  Flights: 2 (JFK→LAX, LAX→JFK)
  Price: USD 1250.00
  Confidence: 0.98
  ```
- **Use Cases**: Template reference, assertion base

### 2. flight_booking.json
- **Path**: `tests/Fixtures/pdf/flight_booking.json`
- **Type**: Business class booking
- **Load**: `FixtureLoader::loadPdfExpectedResult('flight_booking')`
- **Details**:
  ```
  Booking: QR987654
  Passenger: Sarah Smith
  Airline: Qatar Airways (QR)
  Flight: QR0084 (LHR→DOH)
  Seat: 28B (Business)
  Price: GBP 3850.50
  Confidence: 0.99
  ```
- **Use Cases**: Business class extraction, premium pricing

### 3. invoice.json
- **Path**: `tests/Fixtures/pdf/invoice.json`
- **Type**: Supplier invoice
- **Load**: `FixtureLoader::loadPdfExpectedResult('invoice')`
- **Details**:
  ```
  Invoice: INV-2025-001234
  Vendor: Travel Services Ltd
  Items:
    - Commission: USD 5000.00
    - Service Fee: USD 500.00
  Total: USD 6325.00 (incl. 15% tax)
  Confidence: 0.97
  ```
- **Use Cases**: Invoice processing, tax calculation

## Image/OCR Fixtures (2)

### 1. expected_ocr.json
- **Path**: `tests/Fixtures/image/expected_ocr.json`
- **Type**: Generic OCR extraction
- **Load**: `FixtureLoader::loadImageExpectedResult('expected_ocr')`
- **Structure**:
  ```
  Text: Booking confirmation text
  Confirmation: BK-123456
  Passenger: Jane Doe
  Route: New York JFK → Paris CDG
  Date: 2025-03-15
  Price: USD 2450.00
  Confidence: 0.95
  Language: English
  ```
- **Use Cases**: OCR template, text extraction

### 2. passport_extraction.json
- **Path**: `tests/Fixtures/image/passport_extraction.json`
- **Type**: Passport document
- **Load**: `FixtureLoader::loadImageExpectedResult('passport_extraction')`
- **Details**:
  ```
  Type: Passport (P)
  Country: UAE (AE)
  Number: A12345678
  Name: Ahmed Al Mansouri
  DOB: 1985-06-15
  Expiry: 2030-01-19
  Status: Valid (not expired)
  Confidence: 0.99
  Includes: MRZ lines
  ```
- **Use Cases**: Identity document OCR, expiry validation

## Email Fixtures (3)

### 1. booking_confirmation.json
- **Path**: `tests/Fixtures/email/booking_confirmation.json`
- **Type**: Flight booking confirmation
- **Load**: `FixtureLoader::loadEmailSample('booking_confirmation')`
- **Details**:
  ```
  From: bookings@airline.com
  To: customer@example.com
  Subject: BA booking confirmation - PNR: ABC123XYZ
  Passenger: Michael Johnson
  Flights: 2 legs (LHR⇌JFK)
  Seats: 1A (Business)
  Dates: Mar 20 → Mar 27
  Price: GBP 4250.00
  Confidence: 0.96
  ```
- **Use Cases**: Email content parsing, booking extraction

### 2. supplier_notification.json
- **Path**: `tests/Fixtures/email/supplier_notification.json`
- **Type**: Settlement report
- **Load**: `FixtureLoader::loadEmailSample('supplier_notification')`
- **Details**:
  ```
  From: Gulf Air (noreply@gulfair.com)
  Subject: Monthly Settlement - Feb 2025
  Period: Feb 1-28, 2025
  Bookings: 156 pax
  Amount: KWD 45850.00
  Commission: KWD 4585.00
  Breakdown:
    - Business: 45 pax, KWD 28500.00
    - Economy: 111 pax, KWD 17350.00
  Attachment: Excel file
  Confidence: 0.94
  ```
- **Use Cases**: Settlement parsing, aggregated data

### 3. with_attachment.json
- **Path**: `tests/Fixtures/email/with_attachment.json`
- **Type**: Complete itinerary with documents
- **Load**: `FixtureLoader::loadEmailSample('with_attachment')`
- **Details**:
  ```
  Subject: Complete Travel Itinerary - Booking XYZ789
  Booking: XYZ789
  Trip: Mar 15 - Apr 2, 2025
  Locations: Los Angeles
  Price: USD 3750.00
  Flights: AA100, AA250 (2 legs)
  Hotel: Beach Resort Hotel
  Car: Hertz Sedan
  Attachments: 5 PDFs
    1. E-Ticket-XYZ789.pdf
    2. Hotel-Confirmation-123456.pdf
    3. CarRental-Agreement.pdf
    4. Insurance-Certificate.pdf
    5. Travel-Tips.pdf
  Confidence: 0.98
  ```
- **Use Cases**: Multi-document emails, attachment handling

## Helper Classes (2)

### 1. FixtureLoader.php
**Location**: `tests/Fixtures/FixtureLoader.php`

**Methods**:
```
loadAirSample($name)                      → string
loadPdfExpectedResult($name)               → array
loadImageExpectedResult($name)             → array
loadEmailSample($name)                     → array
generateN8nCallback($id, $type, $status)  → array
generateWebhookPayload($overrides)        → array
getFixturePath($type, $name)               → string
listFixtures($type)                        → array
initialize($basePath)                      → void
```

### 2. N8nResponseFactory.php
**Location**: `tests/Fixtures/N8nResponseFactory.php`

**Methods**:
```
success($id, $data, $options)              → array
failure($id, $code, $message, $options)    → array
timeout($id, $options)                     → array
deferred($id, $reason, $options)           → array
invalidFormat($id, $format)                → array
corruptedFile($id, $reason)                → array
missingFields($id, $fields)                → array
rateLimited($id, $seconds)                 → array
serviceUnavailable($id, $service)          → array
partialSuccess($id, $data, $warnings)      → array
batch($documents, $options)                → array
```

## Usage Quick Reference

### Load a Fixture
```php
// AIR file (returns raw text)
$airContent = FixtureLoader::loadAirSample('sample_booking');

// PDF extraction (returns array)
$pdfData = FixtureLoader::loadPdfExpectedResult('flight_booking');

// OCR result (returns array)
$ocrData = FixtureLoader::loadImageExpectedResult('passport_extraction');

// Email content (returns array)
$emailData = FixtureLoader::loadEmailSample('booking_confirmation');
```

### Generate Mock N8n Response
```php
// Success
$response = N8nResponseFactory::success('doc-123', ['bookingRef' => 'ABC']);

// Failure
$response = N8nResponseFactory::failure('doc-123', 'ERR_TIMEOUT');

// Deferred
$response = N8nResponseFactory::deferred('doc-123', 'Manual review needed');

// Batch
$batch = N8nResponseFactory::batch([$success, $failure, $timeout]);
```

### List Available Fixtures
```php
$airFiles = FixtureLoader::listFixtures('air');
// Returns: ['sample_booking', 'sample_reissue', 'malformed']

$pdfFiles = FixtureLoader::listFixtures('pdf');
// Returns: ['expected_extraction', 'flight_booking', 'invoice']
```

## Common Test Patterns

### Testing Parser
```php
$content = FixtureLoader::loadAirSample('sample_booking');
$result = $parser->parse($content);
$this->assertNotNull($result);
```

### Testing Webhook
```php
$response = N8nResponseFactory::success('doc-uuid', $extractedData);
$this->postJson('/api/webhooks', $response);
```

### Testing Error Handling
```php
$error = N8nResponseFactory::missingFields('doc-uuid', ['passenger']);
$this->processError($error);
```

### Testing Assertions
```php
$expected = FixtureLoader::loadPdfExpectedResult('flight_booking');
$this->assertEquals($expected['extracted_data'], $actual);
```

## Statistics

| Category | Count | Details |
|----------|-------|---------|
| AIR Files | 3 | 1 booking, 1 reissue, 1 malformed |
| PDF Fixtures | 3 | 2 bookings, 1 invoice |
| OCR Fixtures | 2 | 1 generic, 1 passport |
| Email Fixtures | 3 | 1 confirmation, 1 settlement, 1 complex |
| Helper Classes | 2 | Loader + Factory |
| Total Files | 14+ | Plus documentation |

## File Sizes

```
AIR Files:     ~3 KB
PDF Fixtures:  ~4 KB
OCR Fixtures:  ~2 KB
Email Fixtures: ~8 KB
FixtureLoader: ~8 KB
N8nResponseFactory: ~12 KB
Documentation: ~15 KB
TOTAL: ~52 KB
```

## Maintenance

### Adding New Fixtures
1. Create file in appropriate directory
2. Follow naming convention
3. Update this index
4. Add to README examples

### Updating Documentation
- Update README.md for detailed info
- Update this index for quick reference
- Update SUMMARY.md for changes

## Next Steps

After implementing these fixtures:
1. Create unit tests using FixtureLoader
2. Add integration tests with N8nResponseFactory
3. Create feature tests for webhooks
4. Implement error handling tests

See README.md for complete documentation and examples.
