# Claude Skills Architecture for Production Code Generation

**Document:** 02-SKILL_ARCHITECTURE.md
**Phase:** 02-core-skills
**Date:** 2026-03-09
**Scope:** DOTWconnect Hotel API skill design for SEARCH-01-04 and BOOK-01-02

---

## 1. Skill Design Principles for Code Generation

### 1.1 Core Philosophy

Effective Claude skills for code generation must serve as **production-intent documentation** that Claude can directly execute as code. Unlike traditional documentation, skills are:

- **API-first**: Every skill documents actual API contracts (endpoints, payloads, responses)
- **Example-driven**: Real request/response examples are more useful than prose descriptions
- **Error-explicit**: All failure modes documented with actual error payloads and handling patterns
- **Contextual**: Environment-specific (URLs, credentials, configuration) clearly separated from logic

### 1.2 The Three Failure Modes

Skills fail when Claude generates code that:

1. **Missing critical context** - API detail not documented (required fields, authentication format)
2. **Wrong abstraction level** - Service class too generic or too specific for the actual workflow
3. **Error handling gaps** - Success paths documented but failure modes underspecified

**Example from MyFatoorah skill:**
- ✅ Documents exact decimal precision: `InvoiceValue: 100.500` (3 decimals for KWD)
- ✅ Shows actual error response JSON with validation error structure
- ✅ Provides complete service class, not just endpoint list

### 1.3 Trigger Pattern Design

The YAML metadata at top of skill matters for Claude discovery:

```yaml
---
name: myfatoorah-integration
description: |
  Complete MyFatoorah payment gateway integration for Laravel with KNET support.
  Use this skill whenever the user mentions:
  - MyFatoorah, KNET payment, Laravel payment integration
  - Payment URLs, invoice creation, payment status checking
  - Webhook configuration, recurring payments
---
```

**Guidelines for trigger patterns:**
- List 6-10 concrete trigger phrases (not generic keywords)
- Include both problem language ("payment integration") and product language ("MyFatoorah")
- Add implementation patterns ("webhook configuration", "error handling")
- Lead with the most specific triggers (MyFatoorah) before generic ones (payment integration)

---

## 2. Prompt Structure for Accurate API Integration

### 2.1 Section Ordering

The most effective skills follow this structure:

```
1. Overview / Business Context
2. API Configuration (base URLs, auth headers)
3. Individual Endpoints (request → response)
4. Error Handling (error payloads, handling patterns)
5. Service Class Implementation (complete, production code)
6. Testing & Validation
7. Security Checklist
8. Configuration Examples (.env, config files)
```

**Why this order:** Claude reads top-to-bottom and needs API details before it can write correct service code.

### 2.2 Endpoint Documentation Template

For each API operation, provide:

```markdown
### 2. ExecutePayment (Required)

**Endpoint:** `POST /v2/ExecutePayment`

**Request:**
```json
{
  "PaymentMethodId": 1,
  "InvoiceValue": 100.500,
  "DisplayCurrencyIso": "KWD",
  ...
}
```

**Response (Success):**
```json
{
  "IsSuccess": true,
  "Data": {
    "InvoiceId": 927972,
    "PaymentURL": "...",
    "CustomerReference": "TOP-001"
  }
}
```

**Response (Error - Invalid Amount):**
```json
{
  "IsSuccess": false,
  "ValidationErrors": [
    {
      "Name": "InvoiceValue",
      "Error": "Invoice value must be greater than 0.100 KWD"
    }
  ]
}
```

**Critical Fields:**
- `InvoiceId`: Store this for status queries
- `PaymentURL`: Redirect customer or send via WhatsApp
```

### 2.3 What Makes API Documentation Claude-Friendly

**DON'T:**
```
The ExecutePayment endpoint creates an invoice. It accepts payment method ID and amount.
Returns the payment URL and invoice ID. Always check the response for errors.
```

**DO:**
```
### ExecutePayment: Create Payment Invoice and Get Payment URL

**Endpoint:** `POST /v2/ExecutePayment`

**Request:**
```json
{
  "PaymentMethodId": 1,        // Always 1 for KNET in Kuwait
  "InvoiceValue": 100.500,     // Exactly 3 decimals for KWD
  "DisplayCurrencyIso": "KWD",
  "CallBackUrl": "https://your-domain.com/payment/callback",
  "CustomerName": "Ahmed Al-Said",
  ...
}
```

**Key validation:**
- PaymentMethodId must be valid (get from InitiatePayment first, or use 1 for KNET)
- InvoiceValue MUST be float with 3 decimal places
- InvoiceValue must be >= 0.100 KWD
- CallBackUrl must be HTTPS
```

### 2.4 Authentication Documentation

Always show the exact header format:

```markdown
### Authentication

All requests require Bearer token authentication:

**Header format:**
```
Authorization: Bearer {your_api_key}
Content-Type: application/json
```

**Example cURL:**
```bash
curl -H "Authorization: Bearer YOUR_KEY" \
     -H "Content-Type: application/json" \
     https://apitest.myfatoorah.com/v2/ExecutePayment
```
```

Claude will copy this pattern directly into HTTP client calls.

---

## 3. Test Case Patterns

### 3.1 Three-Tier Testing Structure

Effective skills include test patterns at three levels:

**Tier 1: Unit Tests** - Single function/method
```php
public function test_normalizePhoneNumber() {
    $service = new ResayilWhatsAppService();

    $this->assertEquals('+96512345678', $service->normalizePhoneNumber('12345678'));
    $this->assertEquals('+96512345678', $service->normalizePhoneNumber('96512345678'));
    $this->assertEquals('+96512345678', $service->normalizePhoneNumber('+96512345678'));

    // Invalid
    $this->expectException(InvalidPhoneNumberException::class);
    $service->normalizePhoneNumber('abc');
}
```

**Tier 2: Integration Tests** - Service + external API mock
```php
public function test_sendOTP_successFlow() {
    Http::fake([
        'https://wa.resayil.io/api/v1/messages/send' => Http::response([
            'success' => true,
            'message_id' => 'wamid.xxx'
        ])
    ]);

    $result = $this->service->sendOTP('+96512345678', '123456');

    $this->assertTrue($result['success']);
    $this->assertNotNull($result['message_id']);
}
```

**Tier 3: End-to-End Tests** - Full workflow (search → block → book)
```php
public function test_hotelBookingWorkflow() {
    // 1. Search hotels
    $searchResult = $this->dotwService->searchHotels(
        destination: 'dubai',
        checkIn: now()->addDay()->toDateString(),
        checkOut: now()->addDays(3)->toDateString(),
        rooms: [['adults' => 2, 'children' => 0]]
    );
    $this->assertGreater(count($searchResult['hotels']), 0);

    // 2. Get rates for first hotel
    $hotel = $searchResult['hotels'][0];
    $rates = $this->dotwService->getRoomRates(
        hotelCode: $hotel['code'],
        checkIn: $searchResult['checkIn'],
        checkOut: $searchResult['checkOut'],
        rooms: $searchResult['rooms']
    );
    $this->assertGreater(count($rates['rooms']), 0);

    // 3. Block rate
    $block = $this->dotwService->blockRates(
        hotelCode: $hotel['code'],
        selectedRoomType: $rates['rooms'][0]['code'],
        selectedRateBasis: $rates['rooms'][0]['rates'][0]['rateBasis'],
        allocationDetails: $rates['allocationDetails']
    );
    $this->assertNotNull($block['preBookKey']);

    // 4. Create booking
    $booking = $this->dotwService->createPreBooking(
        preBookKey: $block['preBookKey'],
        passengers: [
            [
                'firstName' => 'Ahmed',
                'lastName' => 'Al-Said',
                'nationality' => 'KW',
                'email' => '[email protected]'
            ]
        ]
    );
    $this->assertTrue($booking['success']);
    $this->assertNotNull($booking['confirmationCode']);
}
```

### 3.2 Test Case Documentation in Skills

The best skills include a **Testing** section:

```markdown
## Testing

### Test Mode Configuration

Use API sandbox URLs with test credentials:

```env
DOTW_API_URL=https://xmldev.dotwconnect.com  # Sandbox
DOTW_USERNAME=your_sandbox_user
DOTW_PASSWORD=your_sandbox_pass
DOTW_COMPANY_CODE=TEST
```

### Test Scenarios

**1. Successful Hotel Search**
```php
$result = $service->searchHotels(
    destination: 364,  // Dubai test city code
    checkIn: '2026-04-01',
    checkOut: '2026-04-03',
    rooms: [['adults' => 2]]
);
// Expect: array with 'hotels' key, 10-20 hotel results
```

**2. Rate No Longer Available**
```php
// Book a hotel, then immediately try to book same rate twice
// Second booking should fail with "rate no longer available" error
```

**3. Expired Allocation Token**
```php
// Get rates, wait 4 minutes (allocation expires after 3)
// Try to block same rate
// Expect: "Allocation expired" error
```
```

---

## 4. Error Handling Strategies

### 4.1 Error Documentation Pattern

For each error type, document:

1. **When it occurs** - Under what conditions
2. **Actual error response** - JSON/XML as returned by API
3. **How to handle** - Code pattern for recovery or user messaging
4. **N8N-friendly messaging** - Actionable text for workflow automation

**Example from MyFatoorah skill:**

```markdown
### Error: Invalid Payment Method

**When:** PaymentMethodId doesn't exist or isn't available for this merchant

**Actual Response:**
```json
{
  "IsSuccess": false,
  "Message": "Payment method is not available",
  "ValidationErrors": [
    {
      "Name": "PaymentMethodId",
      "Error": "Payment method is not available"
    }
  ]
}
```

**Laravel Handling:**
```php
try {
    $response = $myfatoorah->executePayment($paymentMethodId, $amount);
} catch (\Exception $e) {
    if (str_contains($e->getMessage(), 'Payment method is not available')) {
        // Fall back to InitiatePayment to get valid methods
        $methods = $myfatoorah->initiatePayment($amount);
        $knetId = $methods['Data']['PaymentMethods'][0]['PaymentMethodId'];
        // Retry with valid method
        return $myfatoorah->executePayment($knetId, $amount);
    }
}
```

**N8N Message:**
```
"Payment method unavailable. Please reconfigure payment gateway or contact admin."
```
```

### 4.2 Resilience Patterns

Document common patterns for resilience:

**Retry with Exponential Backoff**
```php
function callWithRetry(callable $fn, int $maxAttempts = 3) {
    $attempt = 0;
    $delay = 1000; // 1 second, in milliseconds

    while ($attempt < $maxAttempts) {
        try {
            return $fn();
        } catch (ApiTimeoutException $e) {
            if ($attempt >= $maxAttempts - 1) {
                throw $e;
            }

            usleep($delay * 1000); // Convert to microseconds
            $delay *= 2; // Exponential backoff: 1s, 2s, 4s
            $attempt++;
        }
    }
}
```

**Circuit Breaker Pattern**
```php
public function searchHotels($destination, $dates) {
    // Check if circuit is open
    if (Cache::get('dotw_circuit_breaker_open')) {
        // Return cached results if available
        $cached = Cache::get("dotw_search_{$destination}_{$dates}");
        if ($cached) {
            return [...$cached, 'cached' => true, 'reason' => 'Circuit breaker active'];
        }

        throw new ServiceUnavailableException(
            'DOTW API temporarily unavailable. Try again in 30 seconds.'
        );
    }

    try {
        return $this->dotwService->searchHotels($destination, $dates);
    } catch (ApiException $e) {
        // Track failure
        $failures = Cache::increment('dotw_failures_last_minute', 1, 60);

        if ($failures >= 5) {
            Cache::put('dotw_circuit_breaker_open', true, 60); // Open for 60 seconds
        }

        throw $e;
    }
}
```

### 4.3 Error Handling Checklist

Skills should include a checklist for common error scenarios:

```markdown
## Error Handling Checklist

- [ ] **Invalid credentials** - Show helpful message directing to config
- [ ] **API timeout** - Implement retry logic with exponential backoff
- [ ] **Rate limiting** - Cache results, implement request queuing
- [ ] **Data validation** - Validate all inputs before API call
- [ ] **Network errors** - Distinguish timeout from 500 vs connection refused
- [ ] **Partial failures** - Handle partial responses (some hotels ok, some fail)
- [ ] **Stale data** - Implement cache expiry and refresh logic
```

---

## 5. Documentation Requirements

### 5.1 README Structure for Skills

Every skill should include a `README.md` in the skill directory:

```markdown
# DOTWconnect Hotel API Skill

Complete hotel search, rate browsing, blocking, and booking for DOTW V4 XML API.

## Quick Start

### 1. Install

```bash
cp -r dotwconnect-hotel-api ~/.claude/skills/
```

### 2. Configure

```env
DOTW_API_URL=https://xmldev.dotwconnect.com
DOTW_USERNAME=sandbox_user
DOTW_PASSWORD=sandbox_pass
DOTW_COMPANY_CODE=TEST123
```

### 3. Test

```php
$dotw = new DotwService();
$result = $dotw->searchHotels('dubai', '2026-04-01', '2026-04-03');
dd($result);
```

## API Capabilities

| Operation | Cost | Blocking | Notes |
|-----------|------|----------|-------|
| searchHotels | 0.1 API call | No | Returns cheapest rate per hotel |
| getRoomRates | 0.1 API call | No | Details for all room types |
| blockRates | 0.2 API call | Yes (3 min) | Locks rate temporarily |
| confirmBooking | 1.0 API call | Yes | Final confirmation |

## Errors & Recovery

| Error | Cause | Recovery |
|-------|-------|----------|
| RATE_NOT_AVAILABLE | Rate sold out | Search again, show alternatives |
| ALLOCATION_EXPIRED | 3+ min since getRoomRates | Retry getRoomRates |
| INVALID_CREDENTIALS | Sandbox/production mismatch | Check DOTW_USERNAME, DOTW_PASSWORD |

## Testing Checklist

- [ ] Search returns hotels
- [ ] Rates show all room types
- [ ] Blocking locks allocation for 3 min
- [ ] Booking confirmation creates reference

## Support

See `SKILL.md` for full API documentation.
```

### 5.2 Self-Documenting Code

Skills are more effective when the generated code itself is self-documenting:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * DOTWconnect Hotel Search & Booking Service
 *
 * Integrates with DOTW V4 XML API for hotel search, rate browsing, rate blocking,
 * and booking confirmation. Supports multi-room configurations, currency conversion,
 * and B2C markup pricing.
 *
 * Configuration:
 * - DOTW_API_URL: API endpoint (sandbox or production)
 * - DOTW_USERNAME: DOTW account username
 * - DOTW_PASSWORD: DOTW account password
 * - DOTW_COMPANY_CODE: DOTW company code
 * - DOTW_B2C_MARKUP_PERCENT: Default B2C markup (default: 20)
 *
 * @see https://www.dotwconnect.com/en/xml-api.php
 */
class DotwService
{
    private string $apiUrl;
    private string $username;
    private string $password;
    private string $companyCode;
    private float $markupPercent;

    public function __construct()
    {
        $this->apiUrl = config('dotw.api_url');
        $this->username = config('dotw.username');
        $this->password = config('dotw.password');
        $this->companyCode = config('dotw.company_code');
        $this->markupPercent = config('dotw.b2c_markup_percent', 20);
    }

    /**
     * Search for hotels in a destination
     *
     * @param int $destinationCityCode DOTW city code (e.g., 364 for Dubai)
     * @param string $checkInDate Format: YYYY-MM-DD
     * @param string $checkOutDate Format: YYYY-MM-DD (must be after check-in)
     * @param array $rooms Room configurations: [['adults' => 2, 'children' => 0]]
     * @param array $filters Optional DOTW filters (rating, price_range, amenities)
     *
     * @return array Hotels with cheapest rates, cached for 2.5 minutes
     *
     * @throws DotwApiException If API returns error
     * @throws InvalidArgumentException If dates or room config invalid
     *
     * Example:
     * ```php
     * $result = $dotw->searchHotels(
     *     destinationCityCode: 364,
     *     checkInDate: '2026-04-01',
     *     checkOutDate: '2026-04-03',
     *     rooms: [['adults' => 2, 'children' => 0]]
     * );
     *
     * foreach ($result['hotels'] as $hotel) {
     *     echo $hotel['name'];
     *     echo $hotel['cheapestRate']['total'] . ' KWD'; // Includes B2C markup
     * }
     * ```
     */
    public function searchHotels(
        int $destinationCityCode,
        string $checkInDate,
        string $checkOutDate,
        array $rooms = [['adults' => 2, 'children' => 0]],
        array $filters = []
    ): array
    {
        // Validation
        $this->validateDates($checkInDate, $checkOutDate);
        $this->validateRooms($rooms);

        // Caching: 2.5 minutes
        $cacheKey = $this->getCacheKey('search', $destinationCityCode, $checkInDate, $checkOutDate, $rooms);
        if ($cached = Cache::get($cacheKey)) {
            return [...$cached, 'cached' => true];
        }

        // Build DOTW XML request
        $requestXml = $this->buildSearchHotelsXml($destinationCityCode, $checkInDate, $checkOutDate, $rooms, $filters);

        // Call API
        $responseXml = $this->callDotwApi($requestXml);

        // Parse response and apply B2C markup
        $hotels = $this->parseSearchResponse($responseXml);
        $hotels = $this->applyB2cMarkup($hotels);

        // Cache result
        Cache::put($cacheKey, $hotels, now()->addSeconds(150));

        return [...$hotels, 'cached' => false];
    }

    // ... more methods ...
}
```

---

## 6. Examples of Well-Structured Skills

### 6.1 The MyFatoorah Skill Pattern

**What it does well:**

1. **Organization**: Sections are in logical order (config → endpoints → errors → implementation → testing)
2. **Completeness**: Includes BOTH request and error responses
3. **Concreteness**: Every example is actual JSON, not prose descriptions
4. **Progression**: Simple endpoints first (InitiatePayment), then complex (recurring payments)
5. **Resilience**: Documents retry logic, webhook handling, duplicate detection
6. **Security**: Dedicated "Security Checklist" section

**Key sections:**
- ✅ API Endpoints with request/response
- ✅ Error Handling (validation errors, API errors, network errors)
- ✅ Complete Service Class (450+ lines of production code)
- ✅ Testing (test mode, test cards, test scenarios)
- ✅ Security & Best Practices

### 6.2 The Resayil WhatsApp Skill Pattern

**What it does well:**

1. **Use-case driven**: Organized around actual workflows (send OTP, payment link, confirmation)
2. **Bilingual support**: Explicitly documents Arabic/English message formatting
3. **Phone number normalization**: Clear utility function with test cases
4. **Rate limiting**: Documents both API limits and Laravel rate limiting
5. **Webhook integration**: Shows incoming message handling

**Key sections:**
- ✅ Phone number format with normalization function
- ✅ Message templates with actual bilingual examples
- ✅ Complete service class
- ✅ Rate limiting patterns (both API and Laravel)
- ✅ Error handling with fallback patterns (SMS backup)

### 6.3 The DOTWconnect Skill (What We Need to Build)

**Should follow patterns from MyFatoorah + Resayil + project-specific:**

1. **Multi-step workflows**: Search → Get Rates → Block → Book (4 distinct operations)
2. **Stateful operations**: Rate blocking requires allocation tokens, time-dependent expiry
3. **Complex data structures**: Multi-room configurations, passenger details, rate breakdowns
4. **Context preservation**: Message tracking (resayil_message_id), audit logging
5. **Error scenarios unique to DOTW**:
   - Rate no longer available
   - Hotel sold out
   - Allocation expired
   - Invalid allocation token

---

## 7. DOTWconnect Skill Design Specification

### 7.1 Skill Metadata

```yaml
---
name: dotwconnect-hotel-api
description: |
  Complete DOTW V4 hotel search, rate browsing, blocking, and booking integration
  for Laravel. Supports multi-room configurations, B2C markup pricing, and
  message tracking for WhatsApp workflows.

  Use this skill whenever the user mentions:
  - DOTW hotel API, DOTW V4, DOTWconnect
  - Hotel search, booking, rate blocking
  - Multi-room hotel configurations
  - Travel agency booking workflows
  - B2B hotel API integration
  - DOTW XML API, allocation tokens, rate availability

  This skill provides production-ready Laravel code for hotel search operations,
  rate availability checking, 3-minute rate blocking, and booking confirmation
  with full error handling, caching, and audit logging.
---
```

### 7.2 Recommended Skill Sections (Order Matters)

```
1. Overview & Business Context
2. DOTW API Configuration
   - Base URL (sandbox vs production)
   - Authentication (username/password)
   - Company code, markup settings
3. Core Operations (in workflow order)
   - Hotel Search (searchHotels)
   - Get Room Rates (getRoomRates)
   - Block Rates (blockRates)
   - Create Booking (confirmBooking)
4. Data Structures
   - Room configuration format
   - Passenger details structure
   - Rate response format
   - Allocation token structure
5. Error Handling
   - By operation (searchHotels errors, getRoomRates errors, etc.)
   - Common patterns (rate no longer available, allocation expired)
   - Recovery strategies
6. Service Class Implementation
   - Complete DotwService class (300-500 lines)
   - Utility methods (cache keys, validation, etc.)
7. Caching & Performance
   - 2.5 min cache for searches
   - 3 min allocation blocking
   - Cache key structure
8. Message Tracking & Audit
   - DotwAuditLog model
   - How to log operations with resayil_message_id
9. B2C Markup & Pricing
   - Markup calculation
   - Markup display in responses
10. GraphQL Integration (if applicable)
    - Schema snippets for searchHotels, getRoomRates, blockRates, createPreBooking
11. Testing
    - Test credentials
    - Test scenarios (search → book workflow)
    - Test assertion patterns
12. Security & Configuration
    - .env variables
    - config/dotw.php file
    - Security checklist
```

### 7.3 Critical Content for DOTW Skill

**Must include:**

1. **Exact XML Request/Response Format**
   - Example searchHotels XML request (with room config)
   - Example searchHotels XML response (showing rate structure)
   - Example blockRates/getRoomRates request and response
   - Example confirmBooking request and response

2. **Rate Blocking Mechanics**
   ```
   - Allocation token is valid for 3 minutes
   - Calling getRoomRates returns allocation token
   - Must pass allocation token to blockRates
   - blockRates locks rate for 3 more minutes (from block time, not from initial getRoomRates)
   - After blockRates succeeds, 3-minute countdown begins
   ```

3. **Error Scenarios**
   - Rate not available (sold out)
   - Hotel inventory depleted
   - Allocation token expired/invalid
   - Company credentials not configured
   - DOTW API timeout

4. **Test Workflow Code**
   ```php
   // Full end-to-end test (search → rates → block → book)
   // With assertions and error handling
   ```

5. **Message Tracking Integration**
   - How to log resayil_message_id to audit log
   - How to link booking back to WhatsApp conversation

6. **B2C Markup Implementation**
   - Where to apply 20% markup (usually to base_rate)
   - How to show markup transparency in response
   - How to allow per-company markup customization

---

## 8. Implementation Checklist

For creating the DOTWconnect skill, verify:

### Content Completeness
- [ ] Overview explains DOTW V4 capabilities (what it does, what it doesn't)
- [ ] Authentication section shows exact header format
- [ ] Each operation has: endpoint, request example, success response, error response
- [ ] Error handling documents BOTH validation errors and business logic errors
- [ ] Service class is production-ready (error handling, logging, validation)
- [ ] Complete test workflow from search to booking
- [ ] Configuration examples (.env, config/dotw.php, GraphQL schema)

### Code Quality
- [ ] All code examples use type hints
- [ ] All code includes PHPDoc comments
- [ ] Error handling patterns shown for common errors
- [ ] Service methods have clear responsibilities (single operation per method)
- [ ] Database models included (DotwAuditLog, DotwPrebook, DotwBooking)

### Claude-Usability
- [ ] Trigger phrases are specific and product-aware
- [ ] API examples are complete JSON/XML (not partial)
- [ ] Error responses show actual API response format, not descriptions
- [ ] Service class can be copy-pasted with minor env config
- [ ] Test cases demonstrate assertions and error scenarios

### Integration Points
- [ ] Message tracking (resayil_message_id) integration documented
- [ ] Cache strategy clearly explained (2.5 min search, 3 min block)
- [ ] B2C markup applied and transparent
- [ ] GraphQL schema snippets (if applicable)

---

## Summary: The Three-Part Structure

**Effective skills balance:**

1. **Completeness**: Every API detail documented (request, response, errors)
2. **Concreteness**: Real examples, not prose descriptions
3. **Executability**: Code can be copy-pasted with minimal changes

The MyFatoorah and Resayil skills exemplify this balance. The DOTWconnect skill should extend this pattern with stateful operations (rate blocking), complex data structures (multi-room configs), and message tracking integration specific to the project.

---

**Next Steps:**
- [ ] Create `/home/user/.claude/skills/dotwconnect-hotel-api/SKILL.md` following this architecture
- [ ] Include complete service class (300-500 lines)
- [ ] Test with Claude: "Generate a hotel booking workflow using DOTWconnect"
- [ ] Iterate based on code quality (check for missing error handling, etc.)
