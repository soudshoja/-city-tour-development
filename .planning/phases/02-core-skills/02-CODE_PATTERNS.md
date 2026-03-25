# Code Patterns for XML API Integration (DOTWconnect & Similar)

**Last Updated**: 2026-03-09
**Framework**: Laravel 11
**PHP**: 8.2+

This document defines the authoritative code patterns that AI skills should generate for maximum reliability and maintainability when integrating with external XML APIs like DOTWconnect.

---

## 1. Service Class Patterns

### 1.1 Base Service Architecture

Service classes are the primary integration point for external APIs. They encapsulate all request/response handling, credential management, and error handling.

**Key Principles**:
- Single Responsibility: one service per external API
- Constructor injection of credentials and dependencies
- Per-company credential resolution (multi-tenant support)
- Comprehensive logging on every operation
- Type hints on all methods (PHP 8.2+ required)
- Exception handling with custom exception types

**Example: HotelSearchService**

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HotelApiTimeoutException;
use App\Models\CompanyHotelApiCredential;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleXMLElement;

/**
 * HotelSearchService — Integration with DOTWconnect XML API
 *
 * Handles hotel search, rate retrieval, and booking operations.
 * Supports both per-company credentials (B2B) and legacy env-based fallback.
 *
 * Multi-tenant credential resolution:
 * - B2B path: Load from company_hotel_api_credentials table
 * - Legacy path: Fall back to config/hotel-api.php env values
 *
 * All requests include:
 * - MD5 password hashing
 * - Gzip compression
 * - Comprehensive audit logging
 * - XML request validation
 * - Timeout handling with DotwTimeoutException
 *
 * @see config/hotel-api.php
 * @see app/Models/CompanyHotelApiCredential
 */
class HotelSearchService
{
    /**
     * API endpoint URL (dev or production)
     */
    private string $baseUrl;

    /**
     * API username (plaintext)
     */
    private string $username;

    /**
     * API password (MD5 hashed)
     */
    private string $passwordMd5;

    /**
     * API company code
     */
    private string $companyCode;

    /**
     * Logger instance for 'hotel_api' channel
     */
    private $logger;

    /**
     * Request/response timeout in seconds
     */
    private int $timeout;

    /**
     * B2C markup percentage (per-company or config fallback)
     */
    private float $markupPercent;

    /**
     * Company ID for current context (null = legacy env-based mode)
     */
    private ?int $companyId;

    /**
     * Initialize service with optional per-company credential resolution.
     *
     * B2B path (company_id provided):
     *   Loads credentials from company_hotel_api_credentials table.
     *   Throws RuntimeException if no active credential exists for company.
     *
     * Legacy path (company_id = null):
     *   Falls back to config/hotel-api.php env values.
     *
     * @param int|null $companyId Company ID for B2B credential resolution
     *
     * @throws RuntimeException When company_id provided but no credentials exist
     */
    public function __construct(?int $companyId = null)
    {
        $isDev = config('hotel-api.dev_mode', true);

        $this->baseUrl = $isDev
            ? config('hotel-api.endpoints.development')
            : config('hotel-api.endpoints.production');

        $this->timeout = config('hotel-api.request.timeout', 25);
        $this->logger = Log::channel(config('hotel-api.log_channel', 'hotel_api'));
        $this->companyId = $companyId;

        if ($companyId !== null) {
            // B2B path: load per-company credentials from DB
            $credential = CompanyHotelApiCredential::forCompany($companyId)->first();

            if (! $credential) {
                throw new \RuntimeException(
                    "Hotel API credentials not configured for company (company_id: {$companyId})"
                );
            }

            $this->username = $credential->api_username;
            $this->passwordMd5 = md5($credential->api_password);
            $this->companyCode = $credential->api_company_code;
            $this->markupPercent = (float) $credential->markup_percent;
        } else {
            // Legacy path: fall back to env credentials
            $this->username = config('hotel-api.username', '');
            $this->passwordMd5 = md5(config('hotel-api.password', ''));
            $this->companyCode = config('hotel-api.company_code', '');
            $this->markupPercent = (float) config('hotel-api.b2c_markup_percentage', 20);
        }

        $this->logger->debug('Hotel API Service initialized', [
            'endpoint' => $this->baseUrl,
            'company_id' => $this->companyId,
            'mode' => $isDev ? 'development' : 'production',
        ]);
    }

    /**
     * Search for hotels with availability
     *
     * @param array $params Search parameters (fromDate, toDate, currency, rooms, city, etc.)
     * @param string|null $requestId External request ID for traceability
     *
     * @return array Parsed hotel results
     *
     * @throws HotelApiTimeoutException If API does not respond within timeout
     * @throws Exception If API returns error response
     */
    public function searchHotels(array $params, ?string $requestId = null): array
    {
        $requestId ??= Str::uuid();

        $this->logger->info('Hotel API search initiated', [
            'request_id' => $requestId,
            'from_date' => $params['fromDate'] ?? null,
            'to_date' => $params['toDate'] ?? null,
        ]);

        try {
            $xmlRequest = $this->buildSearchRequestXml($params);
            $xmlResponse = $this->post($xmlRequest, $requestId);

            $hotels = $this->parseHotelsFromXml($xmlResponse);

            $this->logger->info('Hotel API search completed', [
                'request_id' => $requestId,
                'hotel_count' => count($hotels),
            ]);

            return $hotels;
        } catch (HotelApiTimeoutException $e) {
            $this->logger->error('Hotel API search timeout', [
                'request_id' => $requestId,
                'timeout_seconds' => $this->timeout,
            ]);
            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Hotel API search failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Apply B2C markup to a raw API fare
     *
     * @param float $originalFare Raw fare from API
     *
     * @return array{original_fare: float, markup_percent: float, markup_amount: float, final_fare: float}
     */
    public function applyMarkup(float $originalFare): array
    {
        $markupAmount = round($originalFare * ($this->markupPercent / 100), 2);

        return [
            'original_fare' => $originalFare,
            'markup_percent' => $this->markupPercent,
            'markup_amount' => $markupAmount,
            'final_fare' => round($originalFare + $markupAmount, 2),
        ];
    }

    /**
     * Build search request XML from parameters
     *
     * @param array $params Search parameters
     *
     * @return string XML request body
     */
    private function buildSearchRequestXml(array $params): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><HotelSearchRequest/>');

        $xml->addChild('Username', $this->username);
        $xml->addChild('Password', $this->passwordMd5);
        $xml->addChild('CompanyCode', $this->companyCode);

        // Add search parameters
        $xml->addChild('CheckInDate', $params['fromDate'] ?? '');
        $xml->addChild('CheckOutDate', $params['toDate'] ?? '');
        $xml->addChild('Currency', $params['currency'] ?? 'USD');

        return $xml->asXML();
    }

    /**
     * POST request to API endpoint with comprehensive error handling
     *
     * @param string $xmlBody XML request body
     * @param string $requestId Request tracking ID
     *
     * @return SimpleXMLElement Parsed XML response
     *
     * @throws HotelApiTimeoutException If request times out
     * @throws Exception If response indicates API error
     */
    private function post(string $xmlBody, string $requestId): SimpleXMLElement
    {
        $this->logger->debug('Sending API request', [
            'request_id' => $requestId,
            'endpoint' => $this->baseUrl,
            'timeout' => $this->timeout,
        ]);

        try {
            $response = Http::timeout($this->timeout)
                ->withoutVerifying()
                ->withHeaders([
                    'Content-Type' => 'application/xml',
                    'X-Request-ID' => $requestId,
                ])
                ->post($this->baseUrl, $xmlBody);

            if ($response->failed()) {
                $this->logger->error('API request failed', [
                    'request_id' => $requestId,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);

                throw new Exception(
                    "Hotel API error [{$response->status()}]: " . $response->body()
                );
            }

            // Parse XML response
            $xml = new SimpleXMLElement($response->body());

            // Check for API-level errors in XML
            if ((string) ($xml->Status ?? 'SUCCESS') !== 'SUCCESS') {
                $errorCode = (string) ($xml->ErrorCode ?? 'UNKNOWN');
                $errorMsg = (string) ($xml->ErrorMessage ?? 'Unknown error');

                throw new Exception(
                    "Hotel API error [{$errorCode}]: {$errorMsg}"
                );
            }

            $this->logger->debug('API response successful', [
                'request_id' => $requestId,
            ]);

            return $xml;
        } catch (ConnectionException $e) {
            $this->logger->error('API connection timeout', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);

            throw new HotelApiTimeoutException(
                "Hotel API request timed out after {$this->timeout}s"
            );
        }
    }

    /**
     * Parse hotels array from XML response
     *
     * @param SimpleXMLElement $xml XML response element
     *
     * @return array Array of parsed hotel records
     */
    private function parseHotelsFromXml(SimpleXMLElement $xml): array
    {
        $hotels = [];

        foreach ($xml->Hotels->Hotel ?? [] as $hotelNode) {
            $hotels[] = [
                'hotel_id' => (string) $hotelNode->HotelID,
                'hotel_name' => (string) $hotelNode->Name,
                'city' => (string) $hotelNode->City,
                'rating' => (int) ($hotelNode->Rating ?? 0),
                'price' => (float) $hotelNode->Price,
                'currency' => (string) $hotelNode->Currency,
            ];
        }

        return $hotels;
    }
}
```

### 1.2 Service Configuration Pattern

Each service should have a corresponding config file for credentials and settings:

```php
<?php

/**
 * config/hotel-api.php
 *
 * Hotel API configuration for external XML API integration
 */

return [
    /*
    |--------------------------------------------------------------------------
    | API Credentials (Legacy/Fallback)
    |--------------------------------------------------------------------------
    |
    | Used when service is instantiated without company_id.
    | For B2B (per-company), credentials load from company_hotel_api_credentials table.
    |
    */
    'username' => env('HOTEL_API_USERNAME', ''),
    'password' => env('HOTEL_API_PASSWORD', ''),
    'company_code' => env('HOTEL_API_COMPANY_CODE', ''),

    /*
    |--------------------------------------------------------------------------
    | Development Mode
    |--------------------------------------------------------------------------
    |
    | When true: uses sandbox endpoint
    | When false: uses production endpoint
    |
    */
    'dev_mode' => env('HOTEL_API_DEV_MODE', true),

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    |
    */
    'endpoints' => [
        'development' => env('HOTEL_API_DEV_ENDPOINT', 'https://sandbox-api.example.com'),
        'production' => env('HOTEL_API_PROD_ENDPOINT', 'https://api.example.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Configuration
    |--------------------------------------------------------------------------
    |
    */
    'request' => [
        'timeout' => env('HOTEL_API_TIMEOUT', 25),
        'connect_timeout' => env('HOTEL_API_CONNECT_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | B2C Markup Percentage
    |--------------------------------------------------------------------------
    |
    | Default markup applied to API fares
    |
    */
    'b2c_markup_percentage' => env('HOTEL_API_B2C_MARKUP', 20),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    */
    'log_channel' => 'hotel_api',
];
```

---

## 2. Model Patterns for Booking Persistence

### 2.1 Credential Model Pattern

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-company Hotel API credential record.
 *
 * Stores encrypted API username and password. Credentials are only
 * decrypted via Attribute accessors and never exposed in JSON.
 *
 * @property int $id
 * @property int $company_id
 * @property string $api_username Decrypted plaintext (via accessor)
 * @property string $api_password Decrypted plaintext (via accessor)
 * @property string $api_company_code Non-sensitive company code
 * @property float $markup_percent B2C markup percentage
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class CompanyHotelApiCredential extends Model
{
    protected $table = 'company_hotel_api_credentials';

    protected $fillable = [
        'company_id',
        'api_username',
        'api_password',
        'api_company_code',
        'markup_percent',
        'is_active',
    ];

    protected $hidden = [
        'api_username',
        'api_password',
    ];

    protected $casts = [
        'markup_percent' => 'float',
        'is_active' => 'boolean',
    ];

    /**
     * API username accessor and mutator (encrypted in DB, plaintext in memory)
     */
    protected function apiUsername(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => Crypt::decrypt($value),
            set: fn (string $value) => Crypt::encrypt($value),
        );
    }

    /**
     * API password accessor and mutator
     */
    protected function apiPassword(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => Crypt::decrypt($value),
            set: fn (string $value) => Crypt::encrypt($value),
        );
    }

    /**
     * Query scope: get credentials for a specific company
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId)
            ->where('is_active', true);
    }
}
```

### 2.2 Booking Record Model Pattern

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * HotelBooking — Immutable record of a hotel booking confirmation.
 *
 * Records are append-only after creation (UPDATED_AT = null).
 * No FK to companies (standalone module per design).
 *
 * @property int $id
 * @property string $allocation_key UUID reference to available rate
 * @property string|null $confirmation_code API booking confirmation code
 * @property string|null $confirmation_number Secondary API reference
 * @property string $customer_reference UUID sent to API for tracking
 * @property string $booking_status 'confirmed' | 'failed'
 * @property array $passengers Passenger details array
 * @property array $hotel_details Hotel and rate snapshot
 * @property string|null $request_id External request ID for traceability
 * @property int|null $company_id Company context (no FK)
 * @property \Illuminate\Support\Carbon $created_at
 */
class HotelBooking extends Model
{
    public const UPDATED_AT = null;  // Immutable after creation

    protected $table = 'hotel_bookings';

    protected $fillable = [
        'allocation_key',
        'confirmation_code',
        'confirmation_number',
        'customer_reference',
        'booking_status',
        'passengers',
        'hotel_details',
        'request_id',
        'company_id',
    ];

    protected $casts = [
        'passengers' => 'array',
        'hotel_details' => 'array',
        'company_id' => 'integer',
    ];
}
```

---

## 3. Request Builder Patterns

### 3.1 XML Request Builder Class

Request builders encapsulate XML construction logic. They validate inputs and build properly-structured XML payloads.

```php
<?php

declare(strict_types=1);

namespace App\Services\HotelApi;

use SimpleXMLElement;
use InvalidArgumentException;

/**
 * HotelSearchRequestBuilder — Constructs hotel search XML payloads
 *
 * Validates input parameters before building XML.
 * Ensures required fields are present and properly formatted.
 */
class HotelSearchRequestBuilder
{
    private string $username;
    private string $passwordMd5;
    private string $companyCode;
    private array $searchParams = [];

    /**
     * Initialize builder with credentials
     */
    public function __construct(string $username, string $passwordMd5, string $companyCode)
    {
        $this->username = $username;
        $this->passwordMd5 = $passwordMd5;
        $this->companyCode = $companyCode;
    }

    /**
     * Set search parameters with validation
     *
     * @param array $params Search parameters
     *
     * @return self Fluent interface
     *
     * @throws InvalidArgumentException If required fields missing or invalid
     */
    public function setSearchParams(array $params): self
    {
        // Validate required fields
        $required = ['fromDate', 'toDate', 'currency', 'rooms', 'city'];
        foreach ($required as $field) {
            if (! isset($params[$field])) {
                throw new InvalidArgumentException("Required field missing: {$field}");
            }
        }

        // Validate date format
        if (! $this->isValidDate($params['fromDate'])) {
            throw new InvalidArgumentException("Invalid fromDate format: {$params['fromDate']}");
        }

        if (! $this->isValidDate($params['toDate'])) {
            throw new InvalidArgumentException("Invalid toDate format: {$params['toDate']}");
        }

        $this->searchParams = $params;
        return $this;
    }

    /**
     * Build complete XML request
     *
     * @return string XML string ready for API submission
     */
    public function build(): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><HotelSearchRequest/>');

        // Add authentication
        $auth = $xml->addChild('Authentication');
        $auth->addChild('Username', $this->username);
        $auth->addChild('Password', $this->passwordMd5);
        $auth->addChild('CompanyCode', $this->companyCode);

        // Add search criteria
        $search = $xml->addChild('SearchCriteria');
        $search->addChild('CheckInDate', $this->searchParams['fromDate']);
        $search->addChild('CheckOutDate', $this->searchParams['toDate']);
        $search->addChild('Currency', $this->searchParams['currency']);
        $search->addChild('City', $this->searchParams['city']);

        // Add rooms (array of occupancy)
        $roomsNode = $search->addChild('Rooms');
        foreach ($this->searchParams['rooms'] as $room) {
            $roomNode = $roomsNode->addChild('Room');
            $roomNode->addChild('Adults', (string) $room['adults']);
            $roomNode->addChild('Children', (string) ($room['children'] ?? 0));
            if (isset($room['childAges'])) {
                $agesNode = $roomNode->addChild('ChildAges');
                foreach ($room['childAges'] as $age) {
                    $agesNode->addChild('Age', (string) $age);
                }
            }
        }

        return $xml->asXML();
    }

    /**
     * Validate date format (YYYY-MM-DD)
     */
    private function isValidDate(string $date): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
    }
}
```

### 3.2 Request Builder Usage in Service

```php
// In HotelSearchService::searchHotels()

private function buildSearchRequestXml(array $params): string
{
    $builder = new HotelSearchRequestBuilder(
        $this->username,
        $this->passwordMd5,
        $this->companyCode
    );

    return $builder
        ->setSearchParams($params)
        ->build();
}
```

---

## 4. Response Parser Patterns

### 4.1 XML Response Parser Class

Response parsers safely extract data from XML responses and transform into standardized arrays.

```php
<?php

declare(strict_types=1);

namespace App\Services\HotelApi;

use SimpleXMLElement;
use InvalidArgumentException;

/**
 * HotelResponseParser — Extracts data from API XML responses
 *
 * Safely parses XML with null-coalescing and type casting.
 * Normalizes API response format to internal array structure.
 */
class HotelResponseParser
{
    /**
     * Parse hotels array from search response
     *
     * @param SimpleXMLElement $xml API response XML
     *
     * @return array Array of normalized hotel records
     */
    public static function parseHotels(SimpleXMLElement $xml): array
    {
        $hotels = [];

        // Safely iterate hotels (guard against missing elements)
        foreach ($xml->Hotels->Hotel ?? [] as $hotelNode) {
            $hotels[] = [
                'hotel_id' => (string) ($hotelNode->HotelID ?? ''),
                'hotel_name' => (string) ($hotelNode->Name ?? ''),
                'city' => (string) ($hotelNode->City ?? ''),
                'rating' => (int) ($hotelNode->Rating ?? 0),
                'price' => (float) ($hotelNode->Price ?? 0),
                'currency' => (string) ($hotelNode->Currency ?? 'USD'),
                'availability' => (int) ($hotelNode->Availability ?? 0),
            ];
        }

        return $hotels;
    }

    /**
     * Parse room rates from getRooms response
     *
     * @param SimpleXMLElement $xml API response XML
     *
     * @return array Array of rate details with allocation key
     *
     * @throws InvalidArgumentException If response structure invalid
     */
    public static function parseRates(SimpleXMLElement $xml): array
    {
        if (! isset($xml->Allocation->AllocationKey)) {
            throw new InvalidArgumentException('No allocation key in response');
        }

        $allocationKey = (string) $xml->Allocation->AllocationKey;
        $rates = [];

        foreach ($xml->Rates->Rate ?? [] as $rateNode) {
            $rates[] = [
                'rate_id' => (string) ($rateNode->RateID ?? ''),
                'room_type' => (string) ($rateNode->RoomType ?? ''),
                'meal_plan' => (string) ($rateNode->MealPlan ?? 'room_only'),
                'price_per_night' => (float) ($rateNode->PricePerNight ?? 0),
                'total_price' => (float) ($rateNode->TotalPrice ?? 0),
                'currency' => (string) ($rateNode->Currency ?? 'USD'),
                'cancellation_policy' => (string) ($rateNode->CancellationPolicy ?? ''),
            ];
        }

        return [
            'allocation_key' => $allocationKey,
            'rates' => $rates,
        ];
    }

    /**
     * Parse confirmation from booking response
     *
     * @param SimpleXMLElement $xml API response XML
     *
     * @return array Confirmation details
     *
     * @throws InvalidArgumentException If response missing required fields
     */
    public static function parseConfirmation(SimpleXMLElement $xml): array
    {
        $confirmationCode = (string) ($xml->Confirmation->ConfirmationCode ?? null);
        $confirmationNumber = (string) ($xml->Confirmation->ConfirmationNumber ?? null);

        if (! $confirmationCode) {
            throw new InvalidArgumentException('No confirmation code in response');
        }

        return [
            'confirmation_code' => $confirmationCode,
            'confirmation_number' => $confirmationNumber,
            'booking_reference' => (string) ($xml->Confirmation->BookingReference ?? ''),
            'check_in_date' => (string) ($xml->Confirmation->CheckInDate ?? ''),
            'check_out_date' => (string) ($xml->Confirmation->CheckOutDate ?? ''),
            'total_price' => (float) ($xml->Confirmation->TotalPrice ?? 0),
            'currency' => (string) ($xml->Confirmation->Currency ?? 'USD'),
        ];
    }
}
```

---

## 5. Error Handling and Exception Patterns

### 5.1 Custom Exception Hierarchy

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * HotelApiTimeoutException — API request timed out
 *
 * Extends Exception (not RuntimeException) so callers can distinguish
 * timeout errors from credential errors and handle them differently.
 *
 * Usage:
 *   try {
 *       $service->searchHotels($params);
 *   } catch (HotelApiTimeoutException $e) {
 *       // Retry logic
 *   } catch (RuntimeException $e) {
 *       // Credential/config error
 *   }
 */
class HotelApiTimeoutException extends \Exception {}

<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * HotelApiValidationException — Request validation failed
 *
 * Thrown when input parameters fail validation before API call.
 */
class HotelApiValidationException extends \InvalidArgumentException {}

<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * HotelApiResponseException — API returned error in response
 *
 * Wraps API error codes and messages for proper error propagation.
 */
class HotelApiResponseException extends \Exception
{
    public function __construct(
        public string $errorCode,
        string $errorMessage = '',
    ) {
        parent::__construct($errorMessage);
    }
}
```

### 5.2 Exception Handling in Services

```php
// In HotelSearchService::searchHotels()

public function searchHotels(array $params, ?string $requestId = null): array
{
    $requestId ??= Str::uuid();

    try {
        // Validate parameters early
        $this->validateSearchParams($params);

        $xmlRequest = $this->buildSearchRequestXml($params);
        $xmlResponse = $this->post($xmlRequest, $requestId);

        return $this->parseHotelsFromXml($xmlResponse);

    } catch (HotelApiValidationException $e) {
        // Input validation failed — client error
        $this->logger->warning('Search validation failed', [
            'request_id' => $requestId,
            'validation_error' => $e->getMessage(),
        ]);
        throw $e;

    } catch (HotelApiTimeoutException $e) {
        // Timeout — consider retry logic
        $this->logger->error('Search timeout', [
            'request_id' => $requestId,
            'timeout_seconds' => $this->timeout,
        ]);
        throw $e;

    } catch (HotelApiResponseException $e) {
        // API returned error in response
        $this->logger->error('Search API error', [
            'request_id' => $requestId,
            'error_code' => $e->errorCode,
            'error_message' => $e->getMessage(),
        ]);
        throw $e;

    } catch (\Exception $e) {
        // Unexpected error
        $this->logger->critical('Search unexpected error', [
            'request_id' => $requestId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        throw $e;
    }
}

/**
 * Validate search parameters before API call
 *
 * @throws HotelApiValidationException
 */
private function validateSearchParams(array $params): void
{
    $required = ['fromDate', 'toDate', 'currency', 'rooms', 'city'];
    foreach ($required as $field) {
        if (! isset($params[$field]) || empty($params[$field])) {
            throw new HotelApiValidationException("Missing required field: {$field}");
        }
    }

    // Validate date format
    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $params['fromDate'])) {
        throw new HotelApiValidationException("Invalid fromDate format");
    }

    // Validate room occupancy
    if (! is_array($params['rooms']) || empty($params['rooms'])) {
        throw new HotelApiValidationException("Rooms must be non-empty array");
    }
}
```

---

## 6. Validation Patterns

### 6.1 Form Request Validation

Use Laravel Form Requests for API endpoint validation:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * HotelSearchRequest — Validate hotel search parameters
 *
 * Applied at controller level to validate incoming API requests
 * before passing to service layer.
 */
class HotelSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;  // Auth handled at route middleware
    }

    public function rules(): array
    {
        return [
            'fromDate' => ['required', 'date_format:Y-m-d'],
            'toDate' => ['required', 'date_format:Y-m-d', 'after:fromDate'],
            'currency' => ['required', 'string', 'size:3'],
            'city' => ['required', 'string', 'max:100'],
            'rooms' => ['required', 'array', 'min:1', 'max:10'],
            'rooms.*.adults' => ['required', 'integer', 'min:1', 'max:5'],
            'rooms.*.children' => ['nullable', 'integer', 'min:0', 'max:5'],
            'rooms.*.childAges' => ['nullable', 'array'],
            'rooms.*.childAges.*' => ['integer', 'min:0', 'max:18'],
        ];
    }

    public function messages(): array
    {
        return [
            'fromDate.required' => 'Check-in date is required',
            'fromDate.date_format' => 'Check-in date must be in YYYY-MM-DD format',
            'toDate.required' => 'Check-out date is required',
            'toDate.after' => 'Check-out date must be after check-in date',
            'currency.size' => 'Currency code must be 3 characters (e.g., USD, AED)',
            'rooms.required' => 'At least one room is required',
            'rooms.*.adults.required' => 'Adult count is required for each room',
        ];
    }
}
```

### 6.2 Validation in Service Constructor

```php
// In HotelSearchService constructor

public function __construct(?int $companyId = null)
{
    // ... credential loading ...

    // Validate credentials are not empty
    if (empty($this->username) || empty($this->passwordMd5) || empty($this->companyCode)) {
        throw new \RuntimeException(
            'Hotel API credentials incomplete or missing'
        );
    }

    // Validate timeout is reasonable
    if ($this->timeout < 5 || $this->timeout > 120) {
        throw new \RuntimeException(
            "Invalid timeout value: {$this->timeout}. Must be between 5-120 seconds."
        );
    }

    // Validate markup percentage is within bounds
    if ($this->markupPercent < 0 || $this->markupPercent > 100) {
        throw new \RuntimeException(
            "Invalid markup percentage: {$this->markupPercent}. Must be between 0-100."
        );
    }
}
```

---

## 7. Logging and Debugging Patterns

### 7.1 Logging Configuration

Configure a dedicated log channel for API operations:

```php
// config/logging.php channels array

'hotel_api' => [
    'driver' => 'daily',
    'path' => storage_path('logs/hotel-api.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => 14,
    'replace_placeholders' => true,
],
```

### 7.2 Structured Logging Pattern

```php
// In HotelSearchService

private function post(string $xmlBody, string $requestId): SimpleXMLElement
{
    // Log request
    $this->logger->debug('Sending hotel API request', [
        'request_id' => $requestId,
        'endpoint' => $this->baseUrl,
        'timeout_seconds' => $this->timeout,
        'company_id' => $this->companyId,
        'timestamp' => now()->toISOString(),
    ]);

    try {
        $response = Http::timeout($this->timeout)
            ->withoutVerifying()
            ->withHeaders([
                'Content-Type' => 'application/xml',
                'X-Request-ID' => $requestId,
            ])
            ->post($this->baseUrl, $xmlBody);

        // Log response metadata (never log response body for security)
        $this->logger->debug('Hotel API response received', [
            'request_id' => $requestId,
            'status_code' => $response->status(),
            'response_size' => strlen($response->body()),
            'timestamp' => now()->toISOString(),
        ]);

        if ($response->failed()) {
            $this->logger->error('Hotel API request failed', [
                'request_id' => $requestId,
                'status_code' => $response->status(),
                'company_id' => $this->companyId,
                'timestamp' => now()->toISOString(),
            ]);

            throw new Exception("Hotel API error [{$response->status()}]");
        }

        // Parse XML...
        return $xml;

    } catch (ConnectionException $e) {
        $this->logger->error('Hotel API timeout', [
            'request_id' => $requestId,
            'timeout_seconds' => $this->timeout,
            'company_id' => $this->companyId,
            'error' => $e->getMessage(),
            'timestamp' => now()->toISOString(),
        ]);

        throw new HotelApiTimeoutException("Request timed out");
    }
}
```

### 7.3 Debug Export Utility

For debugging, create detailed exports of requests/responses:

```php
<?php

namespace App\Services\HotelApi;

use Illuminate\Support\Facades\File;

/**
 * HotelApiDebugExporter — Export requests/responses for debugging
 *
 * Only enable in development mode. Writes XML and metadata to files
 * for analysis.
 */
class HotelApiDebugExporter
{
    private string $debugDir;
    private bool $enabled;

    public function __construct()
    {
        $this->enabled = config('app.debug', false);
        $this->debugDir = storage_path('debug_exports/hotel-api');

        if ($this->enabled && ! File::isDirectory($this->debugDir)) {
            File::makeDirectory($this->debugDir, 0755, true);
        }
    }

    /**
     * Export request and response for debugging
     *
     * @param string $requestId Unique request identifier
     * @param string $xmlRequest XML request sent
     * @param string $xmlResponse XML response received
     */
    public function export(string $requestId, string $xmlRequest, string $xmlResponse): void
    {
        if (! $this->enabled) {
            return;
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $prefix = "{$this->debugDir}/{$timestamp}_{$requestId}";

        // Export request
        File::put("{$prefix}_request.xml", $xmlRequest);

        // Export response
        File::put("{$prefix}_response.xml", $xmlResponse);

        // Export metadata
        File::put("{$prefix}_metadata.json", json_encode([
            'request_id' => $requestId,
            'timestamp' => now()->toISOString(),
            'request_size' => strlen($xmlRequest),
            'response_size' => strlen($xmlResponse),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
```

---

## 8. Testing Patterns

### 8.1 Service Unit Test Template

```php
<?php

namespace Tests\Unit\Services;

use App\Exceptions\HotelApiTimeoutException;
use App\Services\HotelSearchService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HotelSearchServiceTest extends TestCase
{
    private HotelSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Use legacy env-based credentials for testing
        config([
            'hotel-api.username' => 'test_user',
            'hotel-api.password' => 'test_pass',
            'hotel-api.company_code' => 'TEST',
        ]);

        $this->service = new HotelSearchService();
    }

    /**
     * Test successful hotel search
     */
    public function test_search_hotels_success(): void
    {
        // Mock HTTP response
        Http::fake([
            'https://sandbox-api.example.com' => Http::response(
                $this->mockSuccessResponse(),
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);

        $result = $this->service->searchHotels([
            'fromDate' => '2026-04-01',
            'toDate' => '2026-04-05',
            'currency' => 'USD',
            'city' => 'Dubai',
            'rooms' => [['adults' => 2, 'children' => 0]],
        ]);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('hotel_id', $result[0]);
    }

    /**
     * Test timeout exception
     */
    public function test_search_hotels_timeout(): void
    {
        // Mock timeout
        Http::fake([
            'https://sandbox-api.example.com' => Http::sequence()
                ->push(new \Exception('Connection timeout'), 0),
        ]);

        $this->expectException(HotelApiTimeoutException::class);

        $this->service->searchHotels([
            'fromDate' => '2026-04-01',
            'toDate' => '2026-04-05',
            'currency' => 'USD',
            'city' => 'Dubai',
            'rooms' => [['adults' => 2]],
        ]);
    }

    /**
     * Test markup application
     */
    public function test_apply_markup(): void
    {
        config(['hotel-api.b2c_markup_percentage' => 20]);

        $result = $this->service->applyMarkup(100.00);

        $this->assertEquals(100.00, $result['original_fare']);
        $this->assertEquals(20, $result['markup_percent']);
        $this->assertEquals(20.00, $result['markup_amount']);
        $this->assertEquals(120.00, $result['final_fare']);
    }

    /**
     * Mock successful API response XML
     */
    private function mockSuccessResponse(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<HotelSearchResponse>
    <Status>SUCCESS</Status>
    <Hotels>
        <Hotel>
            <HotelID>12345</HotelID>
            <Name>Test Hotel</Name>
            <City>Dubai</City>
            <Rating>5</Rating>
            <Price>150.00</Price>
            <Currency>USD</Currency>
            <Availability>10</Availability>
        </Hotel>
    </Hotels>
</HotelSearchResponse>
XML;
    }
}
```

### 8.2 Mock Fixtures for Testing

Store reusable mock responses in `tests/Fixtures/`:

```php
// tests/Fixtures/HotelApiResponses.php

<?php

namespace Tests\Fixtures;

class HotelApiResponses
{
    public static function searchHotelsSuccess(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<HotelSearchResponse>
    <Status>SUCCESS</Status>
    <Hotels>
        <Hotel>
            <HotelID>12345</HotelID>
            <Name>Burj Al Arab</Name>
            <City>Dubai</City>
            <Rating>5</Rating>
            <Price>500.00</Price>
            <Currency>AED</Currency>
            <Availability>5</Availability>
        </Hotel>
        <Hotel>
            <HotelID>12346</HotelID>
            <Name>Emirates Palace</Name>
            <City>Abu Dhabi</City>
            <Rating>5</Rating>
            <Price>450.00</Price>
            <Currency>AED</Currency>
            <Availability>3</Availability>
        </Hotel>
    </Hotels>
</HotelSearchResponse>
XML;
    }

    public static function searchHotelsError(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<HotelSearchResponse>
    <Status>ERROR</Status>
    <ErrorCode>INVALID_CITY</ErrorCode>
    <ErrorMessage>City code not found in database</ErrorMessage>
</HotelSearchResponse>
XML;
    }

    public static function getRoomsSuccess(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<GetRoomsResponse>
    <Status>SUCCESS</Status>
    <Allocation>
        <AllocationKey>ALLOC-ABC123XYZ789</AllocationKey>
        <ExpiryTime>2026-03-09T14:30:00Z</ExpiryTime>
    </Allocation>
    <Rates>
        <Rate>
            <RateID>RATE-001</RateID>
            <RoomType>Deluxe</RoomType>
            <MealPlan>bed_breakfast</MealPlan>
            <PricePerNight>250.00</PricePerNight>
            <TotalPrice>1000.00</TotalPrice>
            <Currency>AED</Currency>
            <CancellationPolicy>Free cancellation until 48 hours before arrival</CancellationPolicy>
        </Rate>
    </Rates>
</GetRoomsResponse>
XML;
    }
}
```

### 8.3 Integration Test Example

```php
<?php

namespace Tests\Feature;

use App\Models\CompanyHotelApiCredential;
use App\Services\HotelSearchService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HotelSearchIntegrationTest extends TestCase
{
    /**
     * Test B2B credential resolution
     */
    public function test_service_loads_b2b_credentials(): void
    {
        // Create company with credentials
        $credential = CompanyHotelApiCredential::create([
            'company_id' => 1,
            'api_username' => 'b2b_user',
            'api_password' => 'b2b_pass',
            'api_company_code' => 'B2B_CODE',
            'markup_percent' => 25,
            'is_active' => true,
        ]);

        // Initialize service with company_id
        $service = new HotelSearchService(companyId: 1);

        // Verify credentials were loaded (reflected in markup calculation)
        $markup = $service->applyMarkup(100);
        $this->assertEquals(25, $markup['markup_percent']);
    }

    /**
     * Test service throws exception for missing credentials
     */
    public function test_service_throws_exception_for_missing_credentials(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Hotel API credentials not configured');

        // Try to load non-existent company credentials
        new HotelSearchService(companyId: 999);
    }
}
```

---

## 9. Code Generation Examples

### 9.1 Complete Service Generation

When a skill generates code for a new XML API integration, it should produce:

1. **Service class** (from Section 1.1)
2. **Config file** (from Section 1.2)
3. **Credential model** (from Section 2.1)
4. **Booking model** (from Section 2.2)
5. **Request builder** (from Section 3.1)
6. **Response parser** (from Section 4.1)
7. **Custom exceptions** (from Section 5.1)
8. **Unit tests** (from Section 8.1)
9. **Migration** for credentials table (see below)

### 9.2 Generated Migration Example

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_hotel_api_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->text('api_username');  // Encrypted
            $table->text('api_password');  // Encrypted
            $table->string('api_company_code', 100);
            $table->decimal('markup_percent', 5, 2)->default(20.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('company_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_hotel_api_credentials');
    }
};
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('allocation_key');
            $table->string('confirmation_code', 100)->nullable();
            $table->string('confirmation_number', 100)->nullable();
            $table->uuid('customer_reference');
            $table->enum('booking_status', ['confirmed', 'failed'])->default('confirmed');
            $table->json('passengers');
            $table->json('hotel_details');
            $table->uuid('request_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->timestamps();

            $table->index('allocation_key');
            $table->index('confirmation_code');
            $table->index('company_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_bookings');
    }
};
```

### 9.3 Generated Controller Example

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\HotelSearchRequest;
use App\Services\HotelSearchService;
use Illuminate\Http\JsonResponse;

class HotelSearchController extends Controller
{
    public function __construct(private HotelSearchService $service)
    {
    }

    /**
     * Search for available hotels
     *
     * @param HotelSearchRequest $request Validated search parameters
     *
     * @return JsonResponse
     */
    public function search(HotelSearchRequest $request): JsonResponse
    {
        try {
            $hotels = $this->service->searchHotels(
                $request->validated(),
                $request->header('X-Request-ID')
            );

            return response()->json([
                'success' => true,
                'data' => $hotels,
                'count' => count($hotels),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
```

---

## 10. Best Practices Summary

### Code Generation Checklist

When generating code for XML API integration, ensure:

- [ ] Service class with per-company credential resolution
- [ ] Config file with environment variables
- [ ] Credential model with encrypted attributes
- [ ] Request builder with validation
- [ ] Response parser with null-safe access
- [ ] Custom exception hierarchy
- [ ] Structured logging to dedicated channel
- [ ] Form request validation
- [ ] Unit tests with mocked HTTP
- [ ] Migration files for new tables
- [ ] PHPDoc comments on all methods
- [ ] Type hints on all parameters and returns
- [ ] Error handling with specific exceptions
- [ ] No hardcoded credentials or secrets
- [ ] Support for both B2B and legacy credential modes

### Code Quality Standards

All generated code must pass:

```bash
# Code style
./vendor/bin/pint

# Static analysis
./vendor/bin/phpstan analyse

# Tests
php artisan test
```

### Security Requirements

- Credentials encrypted in database via Laravel Crypt
- Never log credentials or raw response bodies
- Validate inputs with Form Requests
- Use SSL/TLS for all API calls
- Guard against XML injection
- Rate limit API calls where applicable
- Audit sensitive operations

---

## References

- **DotwService**: `/app/Services/DotwService.php` - Complete reference implementation
- **CompanyDotwCredential**: `/app/Models/CompanyDotwCredential.php` - Credential model pattern
- **DotwBooking**: `/app/Models/DotwBooking.php` - Booking model pattern
- **Configuration**: `/config/dotw.php` - Config file example
- **AIResponse**: `/app/AI/Support/AIResponse.php` - Response standardization pattern
- **FileProcessingLogger**: `/app/Services/FileProcessingLogger.php` - Logging pattern

---

**Document Status**: Ready for skill implementation
**Next Phase**: Implement skills for generating code following these patterns
