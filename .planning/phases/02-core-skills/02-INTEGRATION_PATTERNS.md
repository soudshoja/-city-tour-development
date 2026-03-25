# DOTWconnect v4 Production Integration Patterns

**Research Date:** 2026-03-09
**Framework:** Laravel 11 with PHP 8.2+
**API Version:** DOTWconnect v4 (XML-based)
**Project Context:** Soud Laravel - Multi-tenant travel agency platform

---

## 1. XML Request/Response Handling in Laravel

### 1.1 Request Building Pattern

DOTWconnect v4 uses XML POST for all requests with strict format requirements. The Soud Laravel codebase demonstrates production patterns:

```php
<?php
namespace App\Services;

use SimpleXMLElement;
use Illuminate\Support\Facades\Log;

class DotwXmlRequestBuilder
{
    /**
     * Build authenticated XML request for DOTWconnect v4
     *
     * Format:
     * <customer>
     *   <username>USER</username>
     *   <password>MD5_HASH</password>
     *   <id>COMPANY_CODE</id>
     *   <source>1</source>
     *   <product>hotel</product>
     *   <request command="METHOD_NAME">
     *     <!-- Method-specific payload -->
     *   </request>
     * </customer>
     */
    public function buildRequest(
        string $method,
        array $credentials,  // [username, passwordMd5, companyCode]
        array $payload       // Method-specific parameters
    ): string {
        $xml = new SimpleXMLElement('<customer/>');

        // Authentication wrapper (MANDATORY - v4 requirement)
        $xml->addChild('username', $credentials['username']);
        $xml->addChild('password', $credentials['passwordMd5']);  // NEVER plain text
        $xml->addChild('id', $credentials['companyCode']);
        $xml->addChild('source', '1');                            // Fixed: always "1"
        $xml->addChild('product', 'hotel');                       // Fixed: always "hotel"

        // Method-specific request element
        $request = $xml->addChild('request');
        $request->addAttribute('command', $method);

        // Add payload children
        foreach ($payload as $key => $value) {
            $this->addXmlChild($request, $key, $value);
        }

        $xmlString = $xml->asXML();

        Log::channel('dotw')->debug('Building request', [
            'method' => $method,
            'company_code' => $credentials['companyCode'],  // Log company context, not credentials
            'payload_keys' => array_keys($payload),
        ]);

        return $xmlString;
    }

    /**
     * Recursively add child elements to XML
     * Handles nested arrays for complex structures
     */
    private function addXmlChild(SimpleXMLElement $parent, $key, $value): void
    {
        if (is_array($value)) {
            // Handle nested arrays (e.g., room configurations)
            $child = $parent->addChild($key);
            foreach ($value as $subKey => $subValue) {
                $this->addXmlChild($child, $subKey, $subValue);
            }
        } else {
            // Scalar value
            $parent->addChild($key, (string) $value);
        }
    }
}
```

### 1.2 Response Parsing Pattern

SimpleXML is susceptible to XXE (XML External Entity) attacks. **CRITICAL**: Always disable external entities before parsing untrusted XML.

```php
<?php
class DotwXmlResponseParser
{
    /**
     * Safely parse DOTW XML response
     *
     * SECURITY: Disables XXE protection by default in SimpleXML
     * Reference: https://www.stackhawk.com/blog/laravel-xml-external-entities-xxe-guide-examples-and-prevention/
     */
    public function parse(string $xmlString): SimpleXMLElement
    {
        // Disable external entity loading (XXE prevention)
        $previousValue = libxml_disable_entity_loader(true);

        try {
            // Suppress XML warnings to custom error handling
            libxml_use_internal_errors(true);

            $xml = simplexml_load_string($xmlString);

            if ($xml === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();

                throw new ParseException(
                    'Invalid XML response: ' . implode(', ', array_map(
                        fn($e) => $e->message,
                        $errors
                    ))
                );
            }

            return $xml;
        } finally {
            libxml_disable_entity_loader($previousValue);
        }
    }

    /**
     * Extract error information from DOTW response
     * DOTWconnect returns error codes in <error> elements
     */
    public function extractError(SimpleXMLElement $xml): ?array
    {
        if (isset($xml->error)) {
            return [
                'code' => (int) $xml->error->code,
                'message' => (string) $xml->error->message,
                'details' => (string) $xml->error->details ?? null,
            ];
        }

        return null;
    }

    /**
     * Extract allocation details token from room response
     * Required for blocking and confirmation steps
     *
     * v4 CRITICAL: This token must be preserved and passed to confirmBooking/savebooking
     * Token expires 3 minutes after blocking call
     */
    public function extractAllocationDetails(SimpleXMLElement $room): ?string
    {
        return isset($room->allocationDetails)
            ? (string) $room->allocationDetails
            : null;
    }

    /**
     * Extract rate/price information from room response
     */
    public function extractRateInfo(SimpleXMLElement $room): array
    {
        return [
            'room_id' => (string) $room->roomId,
            'room_type' => (string) $room->roomType,
            'capacity' => (int) $room->capacity,
            'price' => (float) $room->price,
            'currency' => (string) $room->currency,
            'allocation_details' => (string) $room->allocationDetails,
            'cancellation_policy' => (string) $room->cancellationPolicy ?? null,
        ];
    }
}
```

### 1.3 Request/Response Compression

DOTWconnect v4 expects **Gzip compression** on both requests and responses.

```php
<?php
class DotwHttpClient
{
    private $baseUrl;
    private $timeout;
    private $logger;

    /**
     * Send request to DOTWconnect API with automatic gzip handling
     *
     * Gzip is MANDATORY for v4 API
     * Laravel's HTTP client handles automatic decompression if Content-Encoding: gzip
     */
    public function post(string $xmlBody): string
    {
        try {
            $response = Http::timeout($this->timeout)
                // Ensure gzip encoding for both request and response
                ->withHeaders([
                    'Content-Type' => 'text/xml',
                    'Content-Encoding' => 'gzip',
                    'Accept-Encoding' => 'gzip, deflate',
                ])
                // Laravel's HTTP client uses cURL by default, which auto-handles gzip
                ->post($this->baseUrl, $xmlBody);

            // Log request (NEVER log credentials, use company_id instead)
            $this->logger->info('DOTW API request', [
                'endpoint' => $this->baseUrl,
                'status' => $response->status(),
                'company_id' => $this->companyId,  // For audit trail, NOT credentials
                'request_size' => strlen($xmlBody),
            ]);

            if ($response->failed()) {
                throw new ApiException(
                    "DOTWconnect API returned {$response->status()}: {$response->body()}"
                );
            }

            return $response->body();
        } catch (ConnectionException $e) {
            $this->logger->error('DOTW connection failed', [
                'company_id' => $this->companyId,
                'error' => $e->getMessage(),
            ]);

            throw new DotwTimeoutException("Failed to connect to DOTWconnect: {$e->getMessage()}");
        }
    }
}
```

---

## 2. MD5 Password Encryption Patterns

### 2.1 Security Context for MD5 in DOTWconnect

**IMPORTANT**: While MD5 is cryptographically broken and NOT suitable for general password hashing, DOTWconnect v4 API **requires** MD5-hashed passwords as part of its authentication protocol. This is a legacy requirement, not a security choice.

**Key distinction:**
- DO NOT use MD5 for hashing user passwords in Laravel (use bcrypt/Argon2)
- DO use MD5 ONLY for DOTWconnect API credential transformations

### 2.2 Safe MD5 Implementation Pattern

```php
<?php
namespace App\Services;

use Illuminate\Support\Facades\Log;

class DotwCredentialManager
{
    /**
     * Hash DOTW password using MD5 (per DOTW v4 API requirement)
     *
     * v4 REQUIREMENT: Password must be MD5-hashed in XML <password> element
     * This is NOT for general password hashing - only for DOTW API auth
     *
     * Security practices:
     * 1. Never store plain-text DOTW passwords in config/logs
     * 2. Encrypt at-rest passwords in company_dotw_credentials table (using Laravel encryption)
     * 3. Hash to MD5 only when building request
     * 4. Never log MD5 hash or plain password
     */
    public static function hashForApi(string $plainPassword): string
    {
        return md5($plainPassword);
    }

    /**
     * Store credential securely in database
     *
     * Laravel's model encryption (using APP_KEY) protects at-rest passwords
     */
    public function storeCredential(
        int $companyId,
        string $username,
        string $plainPassword,  // Only in memory during setup
        string $companyCode,
        float $markupPercent = 0.0
    ): CompanyDotwCredential {
        // Laravel's model will auto-encrypt these values
        // See CompanyDotwCredential::$casts
        return CompanyDotwCredential::create([
            'company_id' => $companyId,
            'dotw_username' => $username,
            'dotw_password' => $plainPassword,  // Auto-encrypted by model
            'dotw_company_code' => $companyCode,
            'markup_percent' => $markupPercent,
        ]);
    }

    /**
     * Retrieve and use credential for API call
     *
     * Demonstrates credential lifecycle:
     * 1. Load from DB (auto-decrypted by Eloquent)
     * 2. Hash to MD5 for API
     * 3. Never store/log the MD5 hash
     */
    public function buildApiRequest(int $companyId): array
    {
        $credential = CompanyDotwCredential::where('company_id', $companyId)->firstOrFail();

        // At this point, Laravel has already decrypted dotw_password
        $passwordMd5 = self::hashForApi($credential->dotw_password);

        // Build request with MD5 hash
        $request = [
            'username' => $credential->dotw_username,
            'password_md5' => $passwordMd5,  // Only in memory, never persisted
            'company_code' => $credential->dotw_company_code,
        ];

        // Password is now out of scope - garbage collected
        // MD5 hash never stored or logged

        return $request;
    }
}
```

### 2.3 Database Schema for Encrypted Credentials

```php
<?php
// In migration or model
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyDotwCredential extends Model
{
    // Laravel Eloquent encryption - auto-encrypt/decrypt these fields
    protected $casts = [
        'dotw_password' => 'encrypted',  // AUTO-ENCRYPTED at rest
        'dotw_username' => 'string',
        'dotw_company_code' => 'string',
        'markup_percent' => 'float',
    ];

    protected $fillable = [
        'company_id',
        'dotw_username',
        'dotw_password',  // Encrypted automatically
        'dotw_company_code',
        'markup_percent',
    ];

    // Scope for multi-tenant isolation
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
```

---

## 3. 3-Minute Rate Lock Management

### 3.1 Understanding Rate Lock Lifecycle

DOTWconnect v4 has a **mandatory 3-minute rate lock window**:

1. **Preview call** (getRooms without blocking): No lock, just pricing info
2. **Block call** (getRooms with blocking): Locks rates for 3 minutes, returns `allocationDetails` token
3. **Confirmation window**: 3 minutes to call confirmBooking or savebooking with the token
4. **Expiration**: After 3 minutes, token becomes invalid, must search again

### 3.2 Rate Lock State Management

```php
<?php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use App\Models\RateLockToken;
use Carbon\Carbon;

class RateLockManager
{
    /**
     * Store rate lock after blocking getRooms call
     *
     * The allocationDetails token from DOTW API represents a rate lock
     * We track it locally for validation and expiration detection
     */
    public function storeRateLock(
        int $companyId,
        string $hotelId,
        string $roomId,
        string $allocationDetailsToken,
        float $rate,
        array $searchCriteria
    ): string {
        $lockId = uniqid('lock_', true);

        // Store in database for audit trail
        RateLockToken::create([
            'company_id' => $companyId,
            'lock_id' => $lockId,
            'hotel_id' => $hotelId,
            'room_id' => $roomId,
            'allocation_details' => $allocationDetailsToken,
            'locked_rate' => $rate,
            'search_criteria' => json_encode($searchCriteria),
            'expires_at' => now()->addMinutes(3),
        ]);

        // Also store in cache for fast lookup (3-minute TTL)
        Cache::put(
            "rate_lock:{$lockId}",
            [
                'allocation_details' => $allocationDetailsToken,
                'hotel_id' => $hotelId,
                'room_id' => $roomId,
                'rate' => $rate,
            ],
            now()->addMinutes(3)
        );

        Log::channel('dotw')->info('Rate lock stored', [
            'company_id' => $companyId,
            'lock_id' => $lockId,
            'hotel_id' => $hotelId,
            'expires_at' => now()->addMinutes(3)->toIso8601String(),
        ]);

        return $lockId;
    }

    /**
     * Validate rate lock before confirmation
     *
     * Checks:
     * 1. Token exists in cache (not expired)
     * 2. Token matches requested hotel/room
     * 3. Rate matches (prevent rate hacks)
     */
    public function validateRateLock(string $lockId, string $hotelId, string $roomId): bool
    {
        $lock = Cache::get("rate_lock:{$lockId}");

        if (!$lock) {
            Log::channel('dotw')->warning('Rate lock expired', [
                'lock_id' => $lockId,
                'reason' => 'Not found in cache (3-minute window exceeded)',
            ]);
            return false;
        }

        if ($lock['hotel_id'] !== $hotelId || $lock['room_id'] !== $roomId) {
            Log::channel('dotw')->warning('Rate lock mismatch', [
                'lock_id' => $lockId,
                'expected' => ['hotel' => $hotelId, 'room' => $roomId],
                'actual' => ['hotel' => $lock['hotel_id'], 'room' => $lock['room_id']],
            ]);
            return false;
        }

        return true;
    }

    /**
     * Retrieve rate lock and allocation token for confirmation
     */
    public function getRateLock(string $lockId): ?array
    {
        return Cache::get("rate_lock:{$lockId}");
    }

    /**
     * Clear rate lock after confirmation or expiration
     */
    public function clearRateLock(string $lockId): void
    {
        Cache::forget("rate_lock:{$lockId}");

        RateLockToken::where('lock_id', $lockId)->update([
            'status' => 'expired',
            'cleared_at' => now(),
        ]);
    }

    /**
     * Check if rate lock is about to expire (warning before 30 seconds)
     * Useful for UI to warn agent: "Rate lock expires in 30 seconds"
     */
    public function getTimeRemaining(string $lockId): ?int
    {
        $dbRecord = RateLockToken::where('lock_id', $lockId)->first();

        if (!$dbRecord || !$dbRecord->expires_at) {
            return null;
        }

        $secondsRemaining = $dbRecord->expires_at->diffInSeconds(now(), false);

        return max(0, $secondsRemaining);
    }
}
```

### 3.3 Cache Configuration for Rate Locks

```php
<?php
// config/dotw.php
return [
    // ...
    'rate_lock' => [
        'ttl_seconds' => 180,              // 3 minutes - DOTW API requirement
        'warning_threshold' => 30,         // Warn UI when 30 seconds remain
        'cache_driver' => env('CACHE_DRIVER', 'redis'),  // Use Redis for distributed systems
    ],
];
```

### 3.4 Error Handling: Rate Lock Expiration

```php
<?php
class DotwService
{
    public function confirmBooking(
        int $companyId,
        string $lockId,
        array $passengerDetails
    ): array {
        // Validate rate lock exists and hasn't expired
        $rateLock = app(RateLockManager::class)->getRateLock($lockId);

        if (!$rateLock) {
            throw new RateLockExpiredException(
                'Rate lock expired. Please search again and select hotel/room.',
                'RATE_LOCK_EXPIRED'
            );
        }

        // Check time remaining
        $secondsRemaining = app(RateLockManager::class)->getTimeRemaining($lockId);
        if ($secondsRemaining < 0) {
            throw new RateLockExpiredException(
                'Rate lock expired 3 minutes ago.',
                'RATE_LOCK_EXPIRED'
            );
        }

        // Proceed with confirmation using allocationDetails from rate lock
        $xml = $this->buildConfirmBookingRequest(
            $companyId,
            $rateLock['allocation_details'],
            $passengerDetails
        );

        return $this->submitRequest('confirmBooking', $xml);
    }
}
```

---

## 4. Error Handling for Rate Expiration

### 4.1 DOTWconnect Error Response Pattern

```php
<?php
/**
 * DOTW returns errors in two ways:
 * 1. HTTP error responses (4xx, 5xx)
 * 2. 200 OK with error details in XML body
 */

class DotwErrorHandler
{
    private $errorCodeRegistry;

    public function __construct(ErrorCodeRegistry $registry)
    {
        $this->errorCodeRegistry = $registry;
    }

    /**
     * Parse and categorize DOTW error responses
     */
    public function handleResponse(SimpleXMLElement $xml, int $httpStatus): void
    {
        // Check for error element in response body
        if (isset($xml->error)) {
            $errorCode = (int) $xml->error->code;
            $errorMessage = (string) $xml->error->message;

            // Map DOTW error codes to application exceptions
            match ($errorCode) {
                // Rate lock expiration (v4 specific)
                1100 => throw new RateLockExpiredException(
                    'Allocation details expired. Rate lock exceeded 3 minutes.',
                    'ALLOCATION_EXPIRED'
                ),
                1101 => throw new RateLockExpiredException(
                    'Invalid allocation token.',
                    'INVALID_ALLOCATION'
                ),
                // Invalid hotel/room combination
                1200 => throw new InvalidHotelException(
                    'Hotel ID not found or not available.',
                    'HOTEL_NOT_FOUND'
                ),
                // Rate changed during lock window (shouldn't happen, but handle it)
                1300 => throw new RateChangedException(
                    'Rate changed during the 3-minute lock window.',
                    'RATE_CHANGED'
                ),
                // Server-side errors
                5000 => throw new DotwServerException(
                    'DOTW server error. Please retry.',
                    'SERVER_ERROR'
                ),
                // Generic errors
                default => throw new DotwApiException(
                    "DOTW Error {$errorCode}: {$errorMessage}",
                    (string) $errorCode
                )
            };
        }

        // Check HTTP status
        if ($httpStatus >= 500) {
            throw new DotwServerException(
                "DOTW server returned {$httpStatus}",
                'HTTP_' . $httpStatus
            );
        }

        if ($httpStatus >= 400) {
            throw new DotwClientException(
                "Invalid request: {$httpStatus}",
                'HTTP_' . $httpStatus
            );
        }
    }
}
```

### 4.2 User-Facing Error Messages

```php
<?php
class ErrorMessageTranslator
{
    /**
     * Convert technical errors to user-friendly messages for UI
     */
    public static function forUI(Exception $exception): string
    {
        return match (get_class($exception)) {
            RateLockExpiredException::class =>
                'Rate lock expired (3 minute limit). Please search again and try another hotel.',

            RateChangedException::class =>
                'Hotel rate changed during booking. Please search again for current rates.',

            InvalidHotelException::class =>
                'Hotel is no longer available. Please search again.',

            DotwTimeoutException::class =>
                'Connection to hotel system took too long. Please try again.',

            default =>
                'Booking system error. Please try again or contact support.'
        };
    }
}
```

---

## 5. Booking State Persistence (Database Schema)

### 5.1 Core Schema for Rate Locks and Bookings

```php
<?php
// Migration: create_rate_lock_tokens_table
Schema::create('rate_lock_tokens', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('company_id')->index();
    $table->string('lock_id')->unique();  // Unique identifier for this lock
    $table->string('hotel_id');
    $table->string('room_id');

    // CRITICAL: The allocation token from DOTW API
    // Must be preserved exactly as returned
    $table->text('allocation_details');

    // Rate information at time of lock
    $table->decimal('locked_rate', 12, 4);
    $table->string('currency', 3)->default('USD');

    // Search context (for audit)
    $table->json('search_criteria');

    // Lifecycle tracking
    $table->timestamp('locked_at')->useCurrent();
    $table->timestamp('expires_at');
    $table->enum('status', ['active', 'confirmed', 'expired'])->default('active');
    $table->timestamp('cleared_at')->nullable();

    // User context
    $table->unsignedBigInteger('agent_id')->nullable();
    $table->unsignedBigInteger('user_id')->nullable();

    $table->timestamps();

    $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
    $table->index(['hotel_id', 'room_id']);
    $table->index(['expires_at']);
});
```

### 5.2 Booking State Model

```php
<?php
// app/Models/RateLockToken.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateLockToken extends Model
{
    protected $fillable = [
        'company_id',
        'lock_id',
        'hotel_id',
        'room_id',
        'allocation_details',
        'locked_rate',
        'currency',
        'search_criteria',
        'agent_id',
        'user_id',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'search_criteria' => 'json',
        'locked_rate' => 'decimal:4',
        'expires_at' => 'datetime',
        'locked_at' => 'datetime',
        'cleared_at' => 'datetime',
    ];

    // Multi-tenant scoping
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // Active locks only
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '>', now());
    }

    // Check if expired
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }
}
```

### 5.3 Booking Workflow States

```php
<?php
// Migration: create_dotw_bookings_table
Schema::create('dotw_bookings', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('company_id')->index();
    $table->unsignedBigInteger('client_id')->index();

    // Hotel and room details
    $table->string('hotel_id');
    $table->string('room_id');
    $table->string('hotel_name');
    $table->string('room_type');

    // Search context
    $table->date('check_in_date');
    $table->date('check_out_date');
    $table->json('search_criteria');

    // Rate information
    $table->decimal('rate', 12, 4);
    $table->string('currency', 3);

    // Workflow state machine
    enum('state', [
        'initial',           // Just created from search
        'rate_locked',       // getRooms blocking called
        'saved',            // savebooking called (deferred flow)
        'confirmed',        // confirmBooking or bookItinerary called
        'cancelled',        // deleteItinerary called
        'expired',          // Rate lock expired without confirmation
        'error',            // Error during confirmation
    ])->default('initial');

    // Confirmation details
    $table->string('dotw_confirmation_number')->nullable();
    $table->string('allocation_details')->nullable();
    $table->unsignedBigInteger('rate_lock_id')->nullable();

    // Passenger information (encrypted for PII compliance)
    $table->json('passenger_details')->nullable();  // encrypted cast

    // Audit trail
    $table->unsignedBigInteger('created_by')->nullable();
    $table->unsignedBigInteger('confirmed_by')->nullable();
    $table->timestamp('confirmed_at')->nullable();
    $table->timestamp('rate_locked_at')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->foreign('company_id')->references('id')->on('companies');
    $table->foreign('client_id')->references('id')->on('clients');
    $table->foreign('rate_lock_id')->references('id')->on('rate_lock_tokens');

    $table->index(['state', 'company_id']);
    $table->index(['dotw_confirmation_number']);
});
```

---

## 6. Integration with Soud Laravel Multi-Tenant System

### 6.1 Company Isolation Pattern

```php
<?php
namespace App\Services;

use App\Models\CompanyDotwCredential;
use Illuminate\Support\Facades\Auth;

class TenantAwareDotwService
{
    /**
     * Get current company ID from authenticated user
     *
     * Soud Laravel structure: User → Agent → Company
     * Every user is scoped to exactly one company
     */
    private function getCurrentCompanyId(): int
    {
        $user = Auth::user();

        if (!$user || !$user->company_id) {
            throw new UnauthorizedException('User is not associated with a company');
        }

        return $user->company_id;
    }

    /**
     * Initialize DOTW service with company isolation
     *
     * Ensures credentials loaded from company_dotw_credentials table
     * Prevents Company A from using Company B's credentials
     */
    public function searchHotels(
        string $destination,
        string $checkInDate,
        string $checkOutDate,
        array $rooms
    ): array {
        $companyId = $this->getCurrentCompanyId();

        // Load credentials specific to this company
        $dotwService = new DotwService($companyId);

        // All subsequent DOTW calls use this company's credentials
        return $dotwService->searchHotels($destination, $checkInDate, $checkOutDate, $rooms);
    }

    /**
     * Rate lock is company-specific
     *
     * When storing rate locks, ALWAYS include company_id
     * When retrieving, ALWAYS filter by company_id
     */
    public function storeRateLock(
        string $hotelId,
        string $allocationToken
    ): string {
        $companyId = $this->getCurrentCompanyId();

        // Store with company_id
        return app(RateLockManager::class)->storeRateLock(
            $companyId,
            $hotelId,
            // ... other params
        );
    }
}
```

### 6.2 Agent Context and Audit Trail

```php
<?php
class DotwAuditTrail
{
    /**
     * Log DOTW activity with full tenant context
     *
     * Soud structure: Agent belongs to Branch, Branch belongs to Company
     * Audit should track the full hierarchy for reconciliation
     */
    public function logHotelSearch(
        int $companyId,
        ?int $agentId,
        string $destination,
        int $resultCount
    ): void {
        DotwAuditLog::create([
            'company_id' => $companyId,
            'agent_id' => $agentId,
            'action' => 'search_hotels',
            'parameters' => json_encode([
                'destination' => $destination,
            ]),
            'result_count' => $resultCount,
            'timestamp' => now(),
        ]);
    }

    /**
     * Log rate lock for compliance
     */
    public function logRateLock(
        int $companyId,
        int $agentId,
        string $hotelId,
        string $lockId,
        float $rate
    ): void {
        DotwAuditLog::create([
            'company_id' => $companyId,
            'agent_id' => $agentId,
            'action' => 'rate_locked',
            'parameters' => json_encode([
                'hotel_id' => $hotelId,
                'lock_id' => $lockId,
                'rate' => $rate,
            ]),
            'timestamp' => now(),
        ]);
    }
}
```

---

## 7. Testing Patterns with Mock XML Responses

### 7.1 Unit Tests with Mocked Responses

```php
<?php
namespace Tests\Unit;

use Tests\TestCase;
use App\Services\DotwService;
use Illuminate\Support\Facades\Http;

class DotwXmlHandlingTest extends TestCase
{
    public function test_parse_valid_search_response()
    {
        // Arrange: Mock DOTW response
        $mockXmlResponse = <<<'XML'
<?xml version="1.0"?>
<response>
    <hotel>
        <id>123456</id>
        <name>Test Hotel Dubai</name>
        <city>DXB</city>
        <starRating>5</starRating>
        <price>500.00</price>
        <currency>USD</currency>
    </hotel>
</response>
XML;

        Http::fake([
            'xmldev.dotwconnect.com/*' => Http::response($mockXmlResponse)
        ]);

        // Act
        $dotwService = new DotwService();
        $result = $dotwService->searchHotels('DXB', '2026-05-01', '2026-05-03', []);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('123456', $result[0]['id']);
    }

    /**
     * Test rate lock flow with mocked responses
     * Demonstrates v4 mandatory dual getRooms pattern
     */
    public function test_rate_lock_workflow()
    {
        // Step 1: Preview getRooms (no blocking)
        Http::fake([
            '*' => Http::sequence()
                // Response 1: getRooms without blocking
                ->push(<<<'XML'
<?xml version="1.0"?>
<response>
    <room>
        <roomId>RM001</roomId>
        <roomType>Double</roomType>
        <price>299.00</price>
        <allocationDetails>PREVIEW_TOKEN_123</allocationDetails>
    </room>
</response>
XML
                )
                // Response 2: getRooms with blocking
                ->push(<<<'XML'
<?xml version="1.0"?>
<response>
    <room>
        <roomId>RM001</roomId>
        <roomType>Double</roomType>
        <price>299.00</price>
        <allocationDetails>BLOCKED_TOKEN_XYZ</allocationDetails>
        <status>locked</status>
    </room>
</response>
XML
                )
        ]);

        // Act
        $service = new DotwService();

        // Preview call
        $preview = $service->getRooms('123456', '2026-05-01', '2026-05-03', [], false);
        $this->assertEquals('RM001', $preview[0]['room_id']);

        // Blocking call (must happen within seconds)
        $locked = $service->getRooms('123456', '2026-05-01', '2026-05-03', [], true);
        $this->assertEquals('BLOCKED_TOKEN_XYZ', $locked[0]['allocation_details']);
        $this->assertEquals('locked', $locked[0]['status']);
    }

    /**
     * Test error handling for expired rate lock
     */
    public function test_rate_lock_expiration_error()
    {
        $mockErrorResponse = <<<'XML'
<?xml version="1.0"?>
<response>
    <error>
        <code>1100</code>
        <message>Allocation details expired</message>
        <details>Rate lock exceeded 3 minutes</details>
    </error>
</response>
XML;

        Http::fake(['*' => Http::response($mockErrorResponse, 200)]);

        $service = new DotwService();

        $this->expectException(RateLockExpiredException::class);
        $service->confirmBooking(123456, 'EXPIRED_TOKEN', []);
    }
}
```

### 7.2 XXE Security Tests

```php
<?php
namespace Tests\Unit\Security;

use Tests\TestCase;
use App\Services\DotwXmlResponseParser;

class XxeSecurityTest extends TestCase
{
    /**
     * Test that XXE attacks are prevented
     *
     * References: https://www.stackhawk.com/blog/laravel-xml-external-entities-xxe-guide-examples-and-prevention/
     */
    public function test_xxe_injection_prevented()
    {
        $xxePayload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
<response>
    <hotel>
        <name>&xxe;</name>
    </hotel>
</response>
XML;

        $parser = new DotwXmlResponseParser();

        // Should not throw, and should return null or safe result
        $result = $parser->parse($xxePayload);

        // Verify payload didn't execute
        $this->assertFalse(strpos($result->asXML(), 'root:'));
    }

    /**
     * Test that billion laughs attack is prevented
     */
    public function test_billion_laughs_prevented()
    {
        $billionLaughs = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE lolz [
  <!ENTITY lol "lol">
  <!ENTITY lol2 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;">
  <!ENTITY lol3 "&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;">
]>
<lolz>&lol3;</lolz>
XML;

        $parser = new DotwXmlResponseParser();

        // Should not cause memory exhaustion
        $result = $parser->parse($billionLaughs);

        // Memory should be reasonable
        $memoryUsed = memory_get_usage();
        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed); // < 10MB
    }
}
```

### 7.3 Feature Tests with Database

```php
<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\CompanyDotwCredential;
use App\Models\RateLockToken;
use Illuminate\Support\Facades\Http;

class DotwBookingFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test company with DOTW credentials
        $this->company = Company::factory()->create();

        CompanyDotwCredential::create([
            'company_id' => $this->company->id,
            'dotw_username' => 'test_user',
            'dotw_password' => 'test_password',  // Will be encrypted by model
            'dotw_company_code' => 'TEST123',
            'markup_percent' => 10.0,
        ]);

        $this->agent = Agent::factory()
            ->for($this->company)
            ->create();
    }

    /**
     * Test complete immediate confirmation workflow
     */
    public function test_immediate_confirmation_flow()
    {
        Http::fake(['*' => Http::sequence()
            ->push(searchHotelsResponse())
            ->push(getRoomsPreviewResponse())
            ->push(getRoomsBlockResponse())
            ->push(confirmBookingResponse())
        ]);

        // Step 1: Search
        $response = $this->actingAs($this->agent)
            ->post('/api/v1/hotels/search', [
                'destination' => 'DXB',
                'check_in' => '2026-05-01',
                'check_out' => '2026-05-03',
                'rooms' => [['adults' => 2]],
            ]);

        $hotelId = $response['hotels'][0]['id'];

        // Step 2: Preview getRooms
        $response = $this->post("/api/v1/hotels/{$hotelId}/rooms", [
            'check_in' => '2026-05-01',
            'check_out' => '2026-05-03',
            'rooms' => [['adults' => 2]],
            'blocking' => false,
        ]);

        // Step 3: Block getRooms
        $response = $this->post("/api/v1/hotels/{$hotelId}/rooms", [
            'check_in' => '2026-05-01',
            'check_out' => '2026-05-03',
            'rooms' => [['adults' => 2]],
            'blocking' => true,
        ]);

        $lockId = $response['lock_id'];

        // Step 4: Confirm
        $response = $this->post("/api/v1/bookings/confirm", [
            'lock_id' => $lockId,
            'passenger' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
            ],
        ]);

        // Assertions
        $this->assertEquals(200, $response->status());
        $this->assertDatabaseHas('dotw_bookings', [
            'company_id' => $this->company->id,
            'state' => 'confirmed',
            'dotw_confirmation_number' => $response['confirmation_number'],
        ]);
    }
}
```

---

## 8. Security Best Practices

### 8.1 Secrets Management

```php
<?php
/**
 * DOTW Credential Security Checklist
 */

// ✅ CORRECT: Store in encrypted column
CompanyDotwCredential::create([
    'dotw_password' => $plainPassword,  // Auto-encrypted by model cast
]);

// ❌ WRONG: Store in plain text
Config::set('dotw.password', $plainPassword);

// ✅ CORRECT: Hash only for API use
$md5Hash = md5($credential->dotw_password);

// ❌ WRONG: Store MD5 hash
Cache::put('dotw_password_md5', md5(config('dotw.password')));
```

### 8.2 Logging and Auditing

```php
<?php
// ✅ CORRECT: Log company context, never credentials
Log::channel('dotw')->info('Rate lock created', [
    'company_id' => $companyId,
    'lock_id' => $lockId,
    'hotel_id' => $hotelId,
    'rate' => $rate,
]);

// ❌ WRONG: Logging credentials
Log::error('API failed', [
    'username' => $credential->dotw_username,
    'password' => $credential->dotw_password,
]);

// ✅ CORRECT: Audit trail in database
DotwAuditLog::create([
    'company_id' => $companyId,
    'action' => 'confirmBooking',
    'hotel_id' => $hotelId,
    'result' => 'success',
]);
```

### 8.3 XXE Prevention

```php
<?php
// ✅ CORRECT: Disable external entities
$previousValue = libxml_disable_entity_loader(true);
try {
    $xml = simplexml_load_string($xmlString);
} finally {
    libxml_disable_entity_loader($previousValue);
}

// ❌ WRONG: Parse untrusted XML without safeguards
$xml = simplexml_load_string($untrustedXml);
```

### 8.4 Multi-Tenant Isolation

```php
<?php
// ✅ CORRECT: Always filter by company_id
RateLockToken::where('company_id', $companyId)
    ->where('lock_id', $lockId)
    ->first();

// ❌ WRONG: Query without company_id
RateLockToken::where('lock_id', $lockId)->first();
// Company A could retrieve Company B's rate lock!

// ✅ CORRECT: Use global scope on model
class RateLockToken extends Model {
    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }
}

// Then queries automatically filter
RateLockToken::where('lock_id', $lockId)->first();  // Safe now
```

---

## 9. Performance Considerations

### 9.1 Rate Lock Cache Strategy

```php
<?php
/**
 * Rate locks require sub-second lookup performance
 * Cache choice depends on deployment architecture
 */

// Single-server deployment
'cache' => env('CACHE_DRIVER', 'file'),

// Multi-server deployment (REQUIRED for production)
'cache' => 'redis',  // Redis provides:
                     // - Sub-millisecond lookup
                     // - Distributed expiration (TTL)
                     // - Atomic operations

// Configuration in config/dotw.php
'rate_lock' => [
    'cache_driver' => env('CACHE_DRIVER', 'redis'),
    'ttl_seconds' => 180,
],
```

### 9.2 XML Parsing Performance

```php
<?php
/**
 * Performance considerations for XML handling
 */

class DotwPerformanceOptimizations
{
    /**
     * For large search results (100+ hotels), use streaming parser
     * instead of loading entire XML into memory
     */
    public function parseSearchResults(string $xmlBody): Generator
    {
        $reader = new XMLReader();
        $reader->XML($xmlBody);

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'hotel') {
                yield new SimpleXMLElement($reader->readOuterXml());
            }
        }

        $reader->close();
    }

    /**
     * MD5 hashing is fast, but cache for same credentials
     */
    public function getPasswordHash(int $companyId): string
    {
        return Cache::remember(
            "dotw_pwd_hash:{$companyId}",
            3600,  // 1 hour
            function () use ($companyId) {
                $credential = CompanyDotwCredential::where('company_id', $companyId)->first();
                return md5($credential->dotw_password);
            }
        );
    }
}
```

---

## 10. Monitoring and Observability

### 10.1 Health Checks

```php
<?php
namespace App\Console\Commands;

use App\Services\DotwService;
use Illuminate\Console\Command;

class CheckDotwHealth extends Command
{
    public function handle()
    {
        foreach (Company::all() as $company) {
            try {
                $service = new DotwService($company->id);

                // Quick health check: search for a known hotel
                $result = $service->searchHotels('DXB', '2026-05-01', '2026-05-02', []);

                Log::channel('dotw')->info('DOTW health check passed', [
                    'company_id' => $company->id,
                    'timestamp' => now(),
                ]);
            } catch (\Exception $e) {
                Log::channel('dotw')->error('DOTW health check failed', [
                    'company_id' => $company->id,
                    'error' => $e->getMessage(),
                    'timestamp' => now(),
                ]);
            }
        }
    }
}
```

### 10.2 Metrics

```php
<?php
/**
 * Track key DOTWconnect metrics
 */

class DotwMetrics
{
    public function recordSearchLatency(int $milliseconds, int $resultCount): void
    {
        // For Prometheus or similar
        event(new DotwSearchCompleted([
            'latency_ms' => $milliseconds,
            'result_count' => $resultCount,
            'timestamp' => now(),
        ]));
    }

    public function recordRateLockExpiration(int $companyId): void
    {
        // Alert: rate lock expired before confirmation
        Log::channel('metrics')->warning('Rate lock expired', [
            'company_id' => $companyId,
            'metric_type' => 'rate_lock_expiration',
        ]);
    }

    public function recordConfirmationTime(int $seconds): void
    {
        // Track time from lock to confirmation
        // Alert if > 170 seconds (3 min - 10 sec buffer)
        if ($seconds > 170) {
            Log::warning('Confirmation near rate lock expiry', [
                'seconds_used' => $seconds,
                'buffer_remaining' => 180 - $seconds,
            ]);
        }
    }
}
```

---

## Sources

### XML Handling in Laravel
- [Laravel XML External Entities (XXE) Guide: Examples and Prevention](https://www.stackhawk.com/blog/laravel-xml-external-entities-xxe-guide-examples-and-prevention/)
- [Easily Read and Write XML in PHP - Laravel News](https://laravel-news.com/xml-wrangler)

### Password Security
- [PHP: Password Hashing - Manual](https://www.php.net/manual/en/faq.passwords.php)
- [PHP Security in 2025: Salts, Hashing, and Safer Passwords](https://www.codeforest.net/php-security-using-salt-to-improve-password-protection/)

### Rate Limiting and State Management
- [Rate Limiting - Laravel 11.x Official Documentation](https://laravel.com/docs/11.x/rate-limiting)
- [Illuminate\Cache\RateLimiter - Laravel API](https://laravel.com/api/9.x/Illuminate/Cache/RateLimiter.html)

### Soud Laravel Existing Implementation
- `/app/Services/DotwService.php` - Core v4 API client (multi-tenant, MD5 hashing, error handling)
- `/app/Services/DotwCacheService.php` - Search result caching with company isolation
- `/app/Services/DotwAuditService.php` - Audit logging for compliance
- `/app/Models/CompanyDotwCredential.php` - Encrypted credential storage

---

## Summary

**v4 Critical Requirements in Production:**

1. **XML Handling:** Use SimpleXML with XXE prevention enabled; never parse untrusted XML without security measures
2. **MD5 Passwords:** Hash ONLY at API call time; never store MD5 hashes or plain passwords in logs
3. **Rate Lock Window:** Implement 3-minute TTL cache with database audit trail; validate before confirmation
4. **Error Recovery:** Handle rate lock expiration gracefully; provide clear user messages
5. **Multi-Tenancy:** Filter all queries by company_id; use global scopes where applicable
6. **Testing:** Mock responses for all workflows; test both success and error paths
7. **Security:** Encrypt credentials at-rest; log only company context and transaction IDs, never secrets
8. **Monitoring:** Track rate lock expiration rates; alert on slow confirmations; health check regularly

**Next Phase:** Implement concrete integration following these patterns within the Soud Laravel application structure.
