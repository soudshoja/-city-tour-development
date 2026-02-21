# DOTW Services Documentation

## Overview

The DOTW (DOTWconnect) module provides a comprehensive service layer for integrating with the DOTWconnect V4 XML-based hotel booking API. The services are designed to handle multi-tenant B2B operations with per-company credential isolation, request/response caching, circuit breaker protection, and comprehensive audit logging.

### Service Layer Architecture

The DOTW services are organized into four complementary services:

| Service | Purpose | Key Responsibility |
|---------|---------|-------------------|
| **DotwService** | Core API integration | XML request building, SOAP communication, response parsing |
| **DotwCacheService** | Search result caching | Per-company, order-independent caching with TTL management |
| **DotwCircuitBreakerService** | Failure protection | Prevents API hammering during outages (5 failures/60s threshold) |
| **DotwAuditService** | Compliance logging | Sanitizes and records all operations to `dotw_audit_logs` |

---

## DotwService

The main service for all DOTWconnect API operations.

### Initialization

#### Constructor

```php
public function __construct(
    ?int $companyId = null,
    ?DotwAuditService $auditService = null
)
```

**Parameters:**
- `$companyId` (int|null): Company ID for B2B credential resolution. If provided, credentials are loaded from the `company_dotw_credentials` table via `CompanyDotwCredential::forCompany()`. If null, falls back to environment variables in `config/dotw.php` (legacy backward-compatible mode).
- `$auditService` (DotwAuditService|null): Optional audit service instance. If null, a default instance is created.

**Throws:**
- `RuntimeException` - When `$companyId` is provided but no active credential row exists for that company.

**Credential Resolution Paths:**

1. **B2B Path** (company_id provided):
   - Loads from `company_dotw_credentials` table
   - Credentials: `dotw_username`, `dotw_password`, `dotw_company_code`, `markup_percent`
   - Password is MD5-hashed internally
   - Multi-tenant isolation guaranteed

2. **Legacy Path** (company_id = null):
   - Falls back to environment variables
   - `DOTW_USERNAME`, `DOTW_PASSWORD`, `DOTW_COMPANY_CODE`, `DOTW_B2C_MARKUP`
   - Used for backward compatibility with existing callers

**Example:**

```php
// B2B multi-tenant initialization
$service = new DotwService(companyId: $company->id);

// Legacy single-tenant initialization
$service = new DotwService();
```

### Configuration & Timeout

- **Request Timeout**: 25 seconds (configurable via `DOTW_TIMEOUT` env var)
- **Connect Timeout**: 30 seconds (configurable via `DOTW_CONNECT_TIMEOUT` env var)
- **Endpoints**: Development (sandbox) or Production based on `DOTW_DEV_MODE`
- **Logger Channel**: 'dotw' (configure in `config/logging.php`)

### Public Methods

#### Search Operations

##### searchHotels()

```php
public function searchHotels(
    array $params,
    ?string $resayilMessageId = null,
    ?string $resayilQuoteId = null,
    ?int $companyId = null
): array
```

Searches for available hotels in a destination.

**Parameters:**
- `$params`: Search parameters array:
  - `fromDate` (string, YYYY-MM-DD): Check-in date
  - `toDate` (string, YYYY-MM-DD): Check-out date
  - `currency` (string): Currency code (e.g., USD, AED)
  - `rooms` (array): Room occupancy details
  - `city` (string): Destination city code
  - `filters` (array, optional): Filter conditions (rating, price, chain, etc.)
  - `resultsPerPage` (int, default: 20): Pagination limit
  - `page` (int, default: 1): Page number
- `$resayilMessageId` (string|null): WhatsApp message_id from X-Resayil-Message-ID header (for WhatsApp B2B integration)
- `$resayilQuoteId` (string|null): Quoted message_id from X-Resayil-Quote-ID header (for WhatsApp replies)
- `$companyId` (int|null): Company context override (null uses constructor companyId)

**Returns:**
- Array of hotels with simplified rate data (cheapest per meal plan per room type)
- Does NOT include allocationDetails (for rate blocking) — see `getRooms()` first

**SOAP Call:**
- Command: `searchhotels`
- Returns: cheapest rates only (V4 simplified protocol)

**Throws:**
- `Exception` - If API returns error code or validation fails

**Audit:**
- Logged with operation type `OP_SEARCH`

**Example:**

```php
$service = new DotwService($companyId);

$results = $service->searchHotels([
    'fromDate' => '2026-03-15',
    'toDate' => '2026-03-18',
    'currency' => 'USD',
    'city' => 'JED',
    'rooms' => [
        [
            'adultsCode' => 2,
            'children' => [],
            'rateBasis' => DotwService::RATE_BASIS_ALL,
            'passengerNationality' => 'SA',
            'passengerCountryOfResidence' => 'SA',
        ]
    ],
    'resultsPerPage' => 50,
    'page' => 1,
]);

// Returns array of hotels with rooms and roomTypes
```

##### getRooms()

```php
public function getRooms(
    array $params,
    bool $blocking = false,
    ?string $resayilMessageId = null,
    ?string $resayilQuoteId = null,
    ?int $companyId = null
): array
```

Gets detailed room rates for a hotel (called twice in V4 flow).

**Dual-Call Pattern (V4 Specification):**
1. **First call** (`$blocking = false`): Browse available rates and get allocationDetails
2. **Second call** (`$blocking = true`): Lock the rate for 3 minutes before booking

**Parameters:**
- `$params`: Room parameters array:
  - `fromDate` (string, YYYY-MM-DD): Check-in date
  - `toDate` (string, YYYY-MM-DD): Check-out date
  - `currency` (string): Currency code
  - `productId` (string): Hotel ID from search results
  - `rooms` (array): Room occupancy details
  - `roomTypeSelected` (array, required when $blocking=true): Selected room with:
    - `code` (string): Room type code
    - `selectedRateBasis` (int): Meal plan code
    - `allocationDetails` (string): From first getRooms call
  - `fields` (array, optional): Custom fields to return
- `$blocking` (bool): Whether to lock the rate (3-minute allocation)
- `$resayilMessageId`, `$resayilQuoteId`, `$companyId`: WhatsApp and company context

**Returns:**
- Array of rooms with rate details:
  - `roomTypeCode`: Room type identifier
  - `roomName`: Display name
  - `details`: Array of rate basis options with:
    - `id`: Rate basis ID
    - `status`: 'checked' (if blocking succeeded), 'unchecked', or 'unavailable'
    - `price`: Total price
    - `taxes`: Tax amount
    - `allocationDetails`: Token for blocking (only on first call)
    - `cancellationRules`: Array of cancellation policies

**SOAP Call:**
- Command: `getrooms`
- Mandatory dual call (browse then block)

**Throws:**
- `Exception` - If API error or rate unavailable after blocking

**Validation:**
- When `$blocking = true`, each rate's status attribute must be 'checked' or exception is thrown

**Audit:**
- Logged with operation type `OP_RATES` (browse) or `OP_BLOCK` (locking)

**Example:**

```php
// First call: browse rates without locking
$roomsResponse = $service->getRooms([
    'fromDate' => '2026-03-15',
    'toDate' => '2026-03-18',
    'currency' => 'USD',
    'productId' => '12345',
    'rooms' => [
        [
            'adultsCode' => 2,
            'children' => [],
            'rateBasis' => DotwService::RATE_BASIS_ALL,
            'passengerNationality' => 'SA',
            'passengerCountryOfResidence' => 'SA',
        ]
    ],
], blocking: false);

// Extract allocationDetails from response
$allocationDetails = $roomsResponse[0]['details'][0]['allocationDetails'];

// Second call: lock the rate
$blockedRooms = $service->getRooms([
    'fromDate' => '2026-03-15',
    'toDate' => '2026-03-18',
    'currency' => 'USD',
    'productId' => '12345',
    'rooms' => [
        [
            'adultsCode' => 2,
            'children' => [],
            'rateBasis' => DotwService::RATE_BASIS_ALL,
            'passengerNationality' => 'SA',
            'passengerCountryOfResidence' => 'SA',
        ]
    ],
    'roomTypeSelected' => [
        'code' => 'STD',
        'selectedRateBasis' => DotwService::RATE_BASIS_BB,
        'allocationDetails' => $allocationDetails,
    ],
], blocking: true);
```

#### Booking Operations

##### confirmBooking()

```php
public function confirmBooking(
    array $params,
    ?string $resayilMessageId = null,
    ?string $resayilQuoteId = null,
    ?int $companyId = null
): array
```

Confirms a hotel booking immediately (direct flow).

**Parameters:**
- `$params`: Booking parameters:
  - `fromDate`, `toDate`, `currency`, `productId`: Standard booking details
  - `sendCommunicationTo` (string): Guest email for confirmation
  - `customerReference` (string): Your internal booking reference
  - `rooms` (array): Room booking details with:
    - `roomTypeCode`, `selectedRateBasis`, `allocationDetails`: From getRooms blocking
    - `adultsCode`, `actualAdults`, `children`, `actualChildren`
    - `beddingPreference` (int): 0 = no preference, 1 = twin, 2 = double
    - `passengers` (array): Guest details with `salutation`, `firstName`, `lastName`
    - `specialRequests` (array, optional): Special requirements
- Other parameters as in searchHotels

**Returns:**
- Confirmation response:
  - `bookingCode` (string): DOTW booking reference
  - `confirmationNumber` (string): Confirmation number
  - `status` (string): 'confirmed' or other status
  - `paymentGuaranteedBy` (string): Payment guarantee info

**SOAP Call:**
- Command: `confirmbooking`
- Direct confirmation (immediate)

**Throws:**
- `Exception` - If confirmation fails

**Audit:**
- Logged with operation type `OP_BOOK`

**Example:**

```php
$confirmation = $service->confirmBooking([
    'fromDate' => '2026-03-15',
    'toDate' => '2026-03-18',
    'currency' => 'USD',
    'productId' => '12345',
    'sendCommunicationTo' => 'guest@example.com',
    'customerReference' => 'MY-REF-001',
    'rooms' => [
        [
            'roomTypeCode' => 'STD',
            'selectedRateBasis' => '1332',
            'allocationDetails' => '...from_getrooms...',
            'adultsCode' => 2,
            'actualAdults' => 2,
            'children' => [],
            'actualChildren' => [],
            'beddingPreference' => 2,
            'passengers' => [
                [
                    'salutation' => 1, // 1=Mr, 2=Mrs, etc
                    'firstName' => 'John',
                    'lastName' => 'Doe',
                ],
                [
                    'salutation' => 2,
                    'firstName' => 'Jane',
                    'lastName' => 'Doe',
                ],
            ],
            'specialRequests' => [
                'High floor please',
                'Room with view',
            ],
        ],
    ],
]);

// Use bookingCode for future queries or cancellations
```

##### saveBooking()

```php
public function saveBooking(
    array $params,
    ?string $resayilMessageId = null,
    ?string $resayilQuoteId = null,
    ?int $companyId = null
): array
```

Saves a booking for later confirmation (Non-Refundable Advance Purchase rates).

**Parameters:**
- Same as `confirmBooking()` but returns itinerary code instead of confirming immediately

**Returns:**
- Itinerary response:
  - `itineraryCode` (string): Code to use in `bookItinerary()` call
  - `status` (string): 'saved'

**SOAP Call:**
- Command: `savebooking`
- Creates an itinerary for later confirmation

**Throws:**
- `Exception` - If save fails

**Audit:**
- Logged with operation type `OP_BOOK`

**Usage Pattern:**

```php
// Step 1: Save booking (non-refundable rates)
$itinerary = $service->saveBooking([...same params as confirmBooking...]);

// Step 2: Later, confirm the saved itinerary
$confirmation = $service->bookItinerary($itinerary['itineraryCode']);
```

##### bookItinerary()

```php
public function bookItinerary(
    string $bookingCode,
    ?string $resayilMessageId = null,
    ?string $resayilQuoteId = null,
    ?int $companyId = null
): array
```

Confirms a previously saved itinerary.

**Parameters:**
- `$bookingCode` (string): Itinerary code from `saveBooking()` response
- Other parameters for WhatsApp and company context

**Returns:**
- Confirmation response (same as `confirmBooking()`)

**SOAP Call:**
- Command: `bookitinerary`

**Throws:**
- `Exception` - If confirmation fails

**Audit:**
- Logged with operation type `OP_BOOK`

#### Cancellation & Modification

##### cancelBooking()

```php
public function cancelBooking(array $params): array
```

Cancels an existing booking using a two-step process.

**Two-Step Cancellation Flow:**

1. **Query** (confirm='no'): Get cancellation charge without applying it
2. **Confirm** (confirm='yes'): Apply the penalty and get refund amount

**Parameters:**
- `$params`: Cancellation parameters:
  - `bookingCode` (string): DOTW booking reference
  - `bookingType` (string, default: 'Hotel'): Booking type
  - `confirm` (string): 'yes' or 'no'
  - `penaltyApplied` (float, required on second call): Charge amount

**Returns:**
- Cancellation result:
  - `bookingCode` (string): The cancelled booking
  - `refund` (float): Refund amount
  - `charge` (float): Cancellation charge applied
  - `status` (string): Cancellation status

**SOAP Call:**
- Command: `cancelbooking`
- Two separate HTTP POST requests

**Example:**

```php
// Step 1: Query cancellation charges
$cancelQuery = $service->cancelBooking([
    'bookingCode' => 'DOTW123456',
    'confirm' => 'no',
]);

$penalty = $cancelQuery['charge'];

// Step 2: Confirm cancellation with penalty
$cancelConfirm = $service->cancelBooking([
    'bookingCode' => 'DOTW123456',
    'confirm' => 'yes',
    'penaltyApplied' => $penalty,
]);

// Use refund amount for refund processing
$refundAmount = $cancelConfirm['refund'];
```

##### getBookingDetail()

```php
public function getBookingDetail(string $bookingCode): array
```

Retrieves full details of an existing booking.

**Parameters:**
- `$bookingCode` (string): DOTW booking reference

**Returns:**
- Booking details:
  - `bookingCode` (string)
  - `hotelName` (string)
  - `checkIn` (string, YYYY-MM-DD)
  - `checkOut` (string, YYYY-MM-DD)
  - `status` (string)
  - `totalPrice` (float)
  - `currency` (string)

**SOAP Call:**
- Command: `getbookingdetails`

**Example:**

```php
$details = $service->getBookingDetail('DOTW123456');
echo "Hotel: {$details['hotelName']}";
echo "Price: {$details['totalPrice']} {$details['currency']}";
```

#### Reference Data Operations

##### getCountryList()

```php
public function getCountryList(): array
```

Gets all available countries for passenger nationality/residence selection.

**Returns:**
- Array of countries with codes:
  ```php
  [
      ['code' => 'SA', 'name' => 'Saudi Arabia'],
      ['code' => 'AE', 'name' => 'United Arab Emirates'],
      ...
  ]
  ```

**SOAP Call:**
- Command: `getallcountries`

**Note:** Results should be cached (TTL 86400s) for performance.

##### getCityList()

```php
public function getCityList(string $countryCode): array
```

Gets all cities available in a specific country.

**Parameters:**
- `$countryCode` (string): Country code from `getCountryList()`

**Returns:**
- Array of cities:
  ```php
  [
      ['code' => 'JED', 'name' => 'Jeddah'],
      ['code' => 'RYD', 'name' => 'Riyadh'],
      ...
  ]
  ```

**SOAP Call:**
- Command: `getservingcities`

##### getHotelClassifications()

```php
public function getHotelClassifications(): array
```

Gets hotel star rating classifications.

**Returns:**
- Array of classifications:
  ```php
  [
      ['id' => '1', 'name' => '1 Star'],
      ['id' => '5', 'name' => '5 Star'],
      ...
  ]
  ```

**SOAP Call:**
- Command: `gethotelclassificationids`

### Pricing & Markup

#### applyMarkup()

```php
public function applyMarkup(float $originalFare): array
```

Applies B2C markup to a raw DOTW fare.

**Parameters:**
- `$originalFare` (float): Raw fare from DOTW API

**Returns:**
- Markup details array:
  ```php
  [
      'original_fare' => 100.00,
      'markup_percent' => 20.0,
      'markup_amount' => 20.00,
      'final_fare' => 120.00,
  ]
  ```

**Markup Source:**
- B2B path: from `company_dotw_credentials.markup_percent`
- Legacy path: from `config('dotw.b2c_markup_percentage')`

**Example:**

```php
$markup = $service->applyMarkup(100.00); // Using company's configured 20%
// Returns: ['original_fare' => 100.00, 'markup_percent' => 20.0, 'markup_amount' => 20.00, 'final_fare' => 120.00]
```

### Rate Basis Constants

```php
DotwService::RATE_BASIS_ALL        // 1      - All Rates
DotwService::RATE_BASIS_ROOM_ONLY  // 1331   - Room Only
DotwService::RATE_BASIS_BB         // 1332   - Bed & Breakfast
DotwService::RATE_BASIS_HB         // 1333   - Half Board
DotwService::RATE_BASIS_FB         // 1334   - Full Board
DotwService::RATE_BASIS_AI         // 1335   - All Inclusive
DotwService::RATE_BASIS_SC         // 1336   - Self Catering
```

---

## DotwCacheService

Handles per-company, order-independent caching of hotel search results.

### Overview

- **Purpose**: Reduce redundant API calls during multi-step WhatsApp conversations
- **Key**: Deterministic, company-isolated, order-independent cache keys
- **TTL**: 150 seconds (2.5 minutes) — configurable via `DOTW_CACHE_TTL`
- **Storage**: Laravel Cache (File, Redis, Memcached, etc.)

### Initialization

```php
public function __construct()
```

Configuration is automatically loaded from `config/dotw.php`:
- `dotw.cache.ttl`: Cache time-to-live in seconds (default: 150)
- `dotw.cache.prefix`: Key prefix (default: 'dotw_search')

### Cache Key Format

```
{prefix}_{companyId}_{destination}_{checkin}_{checkout}_{roomsHash}
```

**Example:**
```
dotw_search_42_jed_2026-03-15_2026-03-18_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
```

**Key Properties:**
- **Company Isolation**: `company_id` in key ensures Company A cache never serves Company B
- **Order Independence**: Rooms array is normalized (ksorted per room, children ages sorted, rooms sorted by adultsCode) before hashing
- **Deterministic**: Identical room configs in different order produce the same hash

### Public Methods

#### buildKey()

```php
public function buildKey(
    int $companyId,
    string $destination,
    string $checkin,
    string $checkout,
    array $rooms
): string
```

Builds a deterministic cache key for a hotel search.

**Parameters:**
- `$companyId` (int): Tenant company identifier
- `$destination` (string): City/destination code (case-insensitive, trimmed)
- `$checkin` (string): Check-in date in YYYY-MM-DD format
- `$checkout` (string): Check-out date in YYYY-MM-DD format
- `$rooms` (array): Room configuration array (order-independent)

**Returns:**
- (string) Fully formed cache key ready for use with other methods

**Example:**

```php
$cacheService = new DotwCacheService();

$key = $cacheService->buildKey(
    companyId: 42,
    destination: 'JED',
    checkin: '2026-03-15',
    checkout: '2026-03-18',
    rooms: [
        ['adultsCode' => 2, 'children' => [8, 5], ...],
    ]
);
// Returns: dotw_search_42_jed_2026-03-15_2026-03-18_<roomsHash>
```

#### remember()

```php
public function remember(string $key, callable $callback): array
```

Retrieves cached value or executes callback and caches the result.

Uses `Cache::remember()` internally with a `DateInterval` TTL.

**Parameters:**
- `$key` (string): Cache key from `buildKey()`
- `$callback` (callable): Function returning the search result array

**Returns:**
- (array) Cached or freshly computed search result

**Example:**

```php
$results = $cacheService->remember($key, function () use ($service, $params) {
    return $service->searchHotels($params);
});
```

#### isCached()

```php
public function isCached(string $key): bool
```

Checks whether a result is currently stored in the cache.

**Parameters:**
- `$key` (string): Cache key to check

**Returns:**
- (bool) True if key exists in cache, false otherwise

**Use Case:** Annotate response with `cached: true` when value came from cache.

**Example:**

```php
$isCached = $cacheService->isCached($key);

return [
    'hotels' => $results,
    'cached' => $isCached,
    'cacheExpires' => $isCached ? now()->addSeconds(150) : null,
];
```

#### get()

```php
public function get(string $key): ?array
```

Retrieves cached value directly without affecting TTL.

**Parameters:**
- `$key` (string): Cache key from `buildKey()`

**Returns:**
- (array|null) Cached result, or null on cache miss

**Use Case:** Circuit breaker fallback — return cached results when DOTW API is down.

**Example:**

```php
if ($circuitBreaker->isOpen($companyId)) {
    $cachedResults = $cacheService->get($key);
    if ($cachedResults) {
        return $cachedResults; // Stale but available
    }
    throw new Exception('API unavailable and no cached results');
}
```

#### forget()

```php
public function forget(string $key): bool
```

Removes a specific entry from the cache.

**Parameters:**
- `$key` (string): Cache key to remove

**Returns:**
- (bool) True if key was present and removed, false otherwise

**Use Case:** Invalidate stale search results after a booking is confirmed.

**Example:**

```php
// After booking is confirmed, invalidate the search cache
$cacheService->forget($key);
```

### Room Normalization

The `normalizeRooms()` private method ensures cache keys are order-independent:

1. **Per-room sorting**: `ksort($room)` — keys in canonical order
2. **Children sorting**: `sort($children)` — ages in ascending order (e.g., [8,5] → [5,8])
3. **Multi-room sorting**: Rooms sorted by `adultsCode` ascending

**Example of Order Independence:**

```php
$rooms1 = [
    ['adultsCode' => 2, 'children' => [8, 5]],
];

$rooms2 = [
    ['adultsCode' => 2, 'children' => [5, 8]], // Different order
];

// Both produce identical keys:
$key1 = $cache->buildKey(..., $rooms1);
$key2 = $cache->buildKey(..., $rooms2);
assert($key1 === $key2); // ✓
```

---

## DotwCircuitBreakerService

Prevents DOTW API hammering during outages.

### Overview

- **Pattern**: State-machine circuit breaker using Laravel Cache
- **Threshold**: 5 failures in 60 seconds opens the circuit
- **Open Duration**: 30 seconds (auto-closes via key expiry)
- **State Store**: Two cache keys per company
- **Scope**: Applied to `searchHotels` only; `getRooms` and `blockRates` excluded

### State Machine

```
CLOSED (normal)
    ↓ (5 failures/60s)
OPEN (reject requests for 30s)
    ↓ (30s expires OR recordSuccess() called)
CLOSED (reset)
```

### Cache Keys

| Key | Purpose | TTL |
|-----|---------|-----|
| `dotw_circuit_failures_{companyId}` | Rolling failure counter | 60s |
| `dotw_circuit_open_{companyId}` | Open flag | 30s |

### Public Methods

#### isOpen()

```php
public function isOpen(int $companyId): bool
```

Checks whether the circuit is currently open (failures exceeded threshold).

**Parameters:**
- `$companyId` (int): Company whose circuit state to check

**Returns:**
- (bool) True if circuit is open, false if closed

**Example:**

```php
$breaker = new DotwCircuitBreakerService();

if ($breaker->isOpen($companyId)) {
    // Return cached results or error
    $cached = $cacheService->get($key);
    if ($cached) return $cached;
    throw new Exception('DOTW API temporarily unavailable');
}

// Proceed with API call
```

#### recordFailure()

```php
public function recordFailure(int $companyId): void
```

Records an API failure for the given company.

**Behavior:**
1. Uses `Cache::add()` to start the 60-second window on first failure
2. Increments failure counter atomically
3. Opens the circuit (sets `dotw_circuit_open_{companyId}`) if threshold reached

**Parameters:**
- `$companyId` (int): Company whose failure counter to increment

**Logging:**
- When circuit opens, logs warning to 'dotw' channel with company_id, failure_count, and open duration

**Example:**

```php
try {
    $results = $service->searchHotels($params);
    $breaker->recordSuccess($companyId);
} catch (Exception $e) {
    $breaker->recordFailure($companyId); // Increments counter
    // After 5 failures/60s: circuit opens for 30s
    throw $e;
}
```

#### recordSuccess()

```php
public function recordSuccess(int $companyId): void
```

Records a successful API call — resets failure counter and closes the circuit immediately.

**Behavior:**
1. Clears `dotw_circuit_failures_{companyId}`
2. Clears `dotw_circuit_open_{companyId}`
3. Circuit returns to CLOSED state immediately

**Parameters:**
- `$companyId` (int): Company whose circuit state to reset

**Example:**

```php
try {
    $results = $service->searchHotels($params);
    $breaker->recordSuccess($companyId); // Reset on success
    return $results;
} catch (Exception $e) {
    $breaker->recordFailure($companyId); // Or increment on failure
    throw $e;
}
```

### Important Notes

**Cache Driver Requirement:**
- For production: Use Redis or Memcached (`CACHE_STORE=redis`)
- File cache: `Cache::increment()` is NOT atomic; race condition possible on the 5th failure
- Logic is still correct; only the race window differs

**Applied To:**
- ✅ `searchHotels()` — circuit breaker enabled
- ❌ `getRooms()` and `blockRates()` — excluded (must succeed for booking flow)

**Integration Pattern:**

```php
// In GraphQL resolver or controller
if ($breaker->isOpen($companyId)) {
    $fallback = $cacheService->get($cacheKey);
    if ($fallback) {
        return ['hotels' => $fallback, 'cached' => true];
    }
    throw new Exception('API temporarily unavailable');
}

try {
    $results = $service->searchHotels($params);
    $breaker->recordSuccess($companyId);
    return $results;
} catch (Exception $e) {
    $breaker->recordFailure($companyId);
    throw $e;
}
```

---

## DotwAuditService

Sanitized audit logging for all DOTW operations.

### Overview

- **Purpose**: Compliance audit trail with automatic PII/credential sanitization
- **Responsibility**: Single point for writing to `dotw_audit_logs` table
- **Security**: Automatic redaction of sensitive keys (MSG-05 compliance)
- **Resilience**: Audit failures never break operations

### Operation Types

Four standard operation types defined as class constants:

| Constant | Value | Logged By |
|----------|-------|-----------|
| `OP_SEARCH` | 'search' | `searchHotels()` |
| `OP_RATES` | 'rates' | `getRooms(blocking=false)` |
| `OP_BLOCK` | 'block' | `getRooms(blocking=true)` |
| `OP_BOOK` | 'book' | `confirmBooking()`, `saveBooking()`, `bookItinerary()` |

### Public Methods

#### log()

```php
public function log(
    string $operationType,
    array $request,
    array $response,
    ?string $resayilMessageId = null,
    ?string $resayilQuoteId = null,
    ?int $companyId = null
): DotwAuditLog
```

Writes a sanitized audit log entry for a DOTW operation.

**Parameters:**
- `$operationType` (string): One of OP_SEARCH, OP_RATES, OP_BLOCK, OP_BOOK
- `$request` (array): Raw request payload (will be sanitized)
- `$response` (array): Raw response payload (will be sanitized)
- `$resayilMessageId` (string|null): WhatsApp message_id from X-Resayil-Message-ID header (MSG-02)
- `$resayilQuoteId` (string|null): Quoted message_id from X-Resayil-Quote-ID header (MSG-03)
- `$companyId` (int|null): Company context (nullable — module is standalone)

**Returns:**
- (DotwAuditLog) Created model instance (or unsaved instance if DB write failed)

**Sanitization:**
- Both request and response payloads are recursively sanitized
- Any key matching SENSITIVE_KEYS has its value replaced with '[REDACTED]'
- Case-insensitive matching

**Error Handling:**
- If DB write fails, exception is caught and logged to 'dotw' channel
- No exception is rethrown — audit failure must not break operations
- Returns unsaved DotwAuditLog instance for type safety

**Example:**

```php
$auditService = new DotwAuditService();

// After searching hotels
$auditLog = $auditService->log(
    operationType: DotwAuditService::OP_SEARCH,
    request: [
        'fromDate' => '2026-03-15',
        'toDate' => '2026-03-18',
        'currency' => 'USD',
    ],
    response: [
        'hotels' => [...],
        'totalCount' => 42,
    ],
    resayilMessageId: 'MSG-123456',
    companyId: 42
);

// Log is persisted (or failed safely logged)
```

#### operationTypes()

```php
public function operationTypes(): array
```

Returns valid operation type labels.

**Returns:**
- (array) Array of strings: ['search', 'rates', 'block', 'book']

**Use Case:** Validate user-supplied operation types before calling `log()`.

**Example:**

```php
$valid = $service->operationTypes();
if (!in_array($userSuppliedType, $valid, true)) {
    throw new InvalidArgumentException('Invalid operation type');
}
```

### Sensitive Keys (Redaction List)

The following keys are automatically redacted from audit logs (case-insensitive, recursive):

```php
'password'
'dotw_password'
'dotw_username'
'username'
'md5'
'secret'
'token'
'authorization'
'credit_card'
'card_number'
'cvv'
'passport_number'
```

### Redaction Example

**Input Request:**
```php
[
    'fromDate' => '2026-03-15',
    'password' => 'abc123xyz',
    'dotw_username' => 'secret_user',
    'nested' => [
        'credit_card' => '4111111111111111',
    ],
]
```

**Output (Sanitized):**
```php
[
    'fromDate' => '2026-03-15',
    'password' => '[REDACTED]',
    'dotw_username' => '[REDACTED]',
    'nested' => [
        'credit_card' => '[REDACTED]',
    ],
]
```

### Database Table Structure

The `dotw_audit_logs` table has the following structure:

| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | Auto-increment |
| company_id | bigint (nullable) | Multi-tenant isolation |
| resayil_message_id | varchar (nullable) | WhatsApp message_id (MSG-02) |
| resayil_quote_id | varchar (nullable) | WhatsApp quote_id (MSG-03) |
| operation_type | varchar | One of: search, rates, block, book |
| request_payload | json (nullable) | Sanitized request data |
| response_payload | json (nullable) | Sanitized response data |
| created_at | timestamp | Auto-set by Laravel |
| updated_at | timestamp | Auto-set by Laravel |

### Integration Pattern

The DotwService automatically logs all operations:

```php
$service = new DotwService($companyId);

// DotwService internally calls auditService->log()
try {
    $hotels = $service->searchHotels($params, $resayilMessageId, $resayilQuoteId, $companyId);
    // Audit log created with OP_SEARCH
} catch (Exception $e) {
    // Even on error, audit log may have been attempted
    throw $e;
}
```

---

## Configuration Reference

All configuration is in `config/dotw.php`:

### API Credentials

```php
'username' => env('DOTW_USERNAME', ''),
'password' => env('DOTW_PASSWORD', ''),
'company_code' => env('DOTW_COMPANY_CODE', ''),
```

Used only in legacy path (when `$companyId = null`). For B2B, credentials come from `company_dotw_credentials` table.

### Development Mode

```php
'dev_mode' => env('DOTW_DEV_MODE', true),
```

- `true`: Uses sandbox `https://xmldev.dotwconnect.com/gatewayV4.dotw`
- `false`: Uses production `https://us.dotwconnect.com/gatewayV4.dotw`

### API Endpoints

```php
'endpoints' => [
    'development' => 'https://xmldev.dotwconnect.com/gatewayV4.dotw',
    'production' => 'https://us.dotwconnect.com/gatewayV4.dotw',
]
```

### Request Configuration

```php
'request' => [
    'timeout' => env('DOTW_TIMEOUT', 25),           // 25s per DOTW SLA
    'connect_timeout' => env('DOTW_CONNECT_TIMEOUT', 30),
    'source' => 1,                                   // Always 1 for hotel
    'product' => 'hotel',                            // Always 'hotel'
],
```

### Allocation Expiry

```php
'allocation_expiry_minutes' => env('DOTW_ALLOCATION_EXPIRY_MINUTES', 3),
```

How long an allocation (rate block) is valid after `getRooms()`. Spec: 3 minutes.

### B2C Markup

```php
'b2c_markup_percentage' => env('DOTW_B2C_MARKUP', 20),
```

Fallback markup for legacy path. B2B uses per-company value from `company_dotw_credentials.markup_percent`.

### Rate Basis Codes (Reference)

```php
'rate_basis_codes' => [
    'ALL' => 1,              // All Rates
    'ROOM_ONLY' => 1331,     // Room Only
    'BB' => 1332,            // Bed & Breakfast
    'HB' => 1333,            // Half Board
    'FB' => 1334,            // Full Board
    'AI' => 1335,            // All Inclusive
    'SC' => 1336,            // Self Catering
],
```

Reference only — use `DotwService::RATE_BASIS_*` constants in code.

### Logging

```php
'log_channel' => 'dotw',
```

Configure the 'dotw' channel in `config/logging.php` for DOTW-specific logs.

### Search Result Cache

```php
'cache' => [
    'ttl' => env('DOTW_CACHE_TTL', 150),
    'prefix' => env('DOTW_CACHE_PREFIX', 'dotw_search'),
],
```

- `ttl`: Cache time-to-live in seconds (default: 150 = 2.5 minutes)
- `prefix`: Key prefix for all DOTW cache entries

---

## Error Handling

### Exception Types

#### DotwTimeoutException

```php
class DotwTimeoutException extends Exception
```

Thrown when request exceeds the configured timeout (25s default).

**Common Causes:**
- DOTW API is slow or unresponsive
- Network connectivity issues
- Load-dependent performance

**Recovery:**
- Circuit breaker opens after 5 timeouts in 60s
- Use cached results from previous searches
- Retry after circuit reopens (30s)

**Example:**

```php
try {
    $results = $service->searchHotels($params);
} catch (DotwTimeoutException $e) {
    // Log timeout
    Log::error('DOTW timeout', ['error' => $e->getMessage()]);

    // Fallback to cache
    $cached = $cacheService->get($key);
    if ($cached) return $cached;

    throw new Exception('Hotel search unavailable');
}
```

#### Standard Exception

```php
throw new Exception("DOTW searchHotels error [{$errorCode}]: {$errorDetails}");
```

Thrown for all other errors returned by DOTW API.

**DOTW Error Codes (Examples):**
- `INVALID_PARAMETER`: Missing or invalid parameter
- `AUTHENTICATION_FAILED`: Invalid credentials
- `RATE_UNAVAILABLE`: Selected rate is no longer available
- `SYSTEM_ERROR`: DOTW server error

**Example:**

```php
try {
    $rooms = $service->getRooms($params, blocking: true);
} catch (Exception $e) {
    if (str_contains($e->getMessage(), 'RATE_UNAVAILABLE')) {
        // Re-browse available rates
        return $service->getRooms($params, blocking: false);
    }
    throw $e;
}
```

### SOAP Fault Handling

All SOAP faults are parsed and thrown as standard `Exception` objects:

```php
if ((string) $response->successful !== 'TRUE') {
    $errorCode = (string) $response->request->error->code ?? 'UNKNOWN';
    $errorDetails = (string) $response->request->error->details ?? 'Unknown error';
    throw new Exception("DOTW {$operation} error [{$errorCode}]: {$errorDetails}");
}
```

---

## Full Workflow Examples

### Example 1: Simple Hotel Search and Booking

```php
// 1. Initialize service
$service = new DotwService($companyId);
$cacheService = new DotwCacheService();
$breaker = new DotwCircuitBreakerService();

// 2. Build cache key
$cacheKey = $cacheService->buildKey(
    $companyId,
    'JED',
    '2026-03-15',
    '2026-03-18',
    [['adultsCode' => 2, 'children' => []]]
);

// 3. Search (with circuit breaker and cache)
if ($breaker->isOpen($companyId)) {
    $hotels = $cacheService->get($cacheKey) ?? [];
} else {
    try {
        $hotels = $cacheService->remember($cacheKey, function () use ($service) {
            return $service->searchHotels([
                'fromDate' => '2026-03-15',
                'toDate' => '2026-03-18',
                'currency' => 'USD',
                'city' => 'JED',
                'rooms' => [
                    ['adultsCode' => 2, 'children' => [], 'rateBasis' => 1]
                ]
            ]);
        });
        $breaker->recordSuccess($companyId);
    } catch (Exception $e) {
        $breaker->recordFailure($companyId);
        throw $e;
    }
}

// 4. Get rooms (browse)
$selected = $hotels[0]['rooms'][0]['roomTypes'][0];
$roomsResponse = $service->getRooms([
    'fromDate' => '2026-03-15',
    'toDate' => '2026-03-18',
    'currency' => 'USD',
    'productId' => $hotels[0]['hotelId'],
    'rooms' => [['adultsCode' => 2, 'children' => [], 'rateBasis' => 1]]
], blocking: false);

// 5. Block rate
$allocationDetails = $roomsResponse[0]['details'][0]['allocationDetails'];
$service->getRooms([
    'fromDate' => '2026-03-15',
    'toDate' => '2026-03-18',
    'currency' => 'USD',
    'productId' => $hotels[0]['hotelId'],
    'rooms' => [['adultsCode' => 2, 'children' => []]],
    'roomTypeSelected' => [
        'code' => 'STD',
        'selectedRateBasis' => $selected['rateBasisId'],
        'allocationDetails' => $allocationDetails,
    ]
], blocking: true);

// 6. Confirm booking
$confirmation = $service->confirmBooking([
    'fromDate' => '2026-03-15',
    'toDate' => '2026-03-18',
    'currency' => 'USD',
    'productId' => $hotels[0]['hotelId'],
    'sendCommunicationTo' => 'guest@example.com',
    'customerReference' => 'MY-BOOKING-001',
    'rooms' => [
        [
            'roomTypeCode' => 'STD',
            'selectedRateBasis' => $selected['rateBasisId'],
            'allocationDetails' => $allocationDetails,
            'adultsCode' => 2,
            'actualAdults' => 2,
            'passengers' => [
                ['salutation' => 1, 'firstName' => 'John', 'lastName' => 'Doe'],
                ['salutation' => 2, 'firstName' => 'Jane', 'lastName' => 'Doe'],
            ],
        ],
    ],
]);

return ['bookingCode' => $confirmation['bookingCode']];
```

### Example 2: Non-Refundable Booking with saveBooking/bookItinerary

```php
// ... (search and blocking same as above) ...

// 6a. Save booking (for non-refundable rates)
$itinerary = $service->saveBooking([
    'fromDate' => '2026-03-15',
    'toDate' => '2026-03-18',
    'currency' => 'USD',
    'productId' => $hotelId,
    'sendCommunicationTo' => 'guest@example.com',
    'customerReference' => 'MY-BOOKING-001',
    'rooms' => [...]
]);

// Store itineraryCode somewhere (DB)
// Later, when payment is confirmed...

// 6b. Book the itinerary
$confirmation = $service->bookItinerary($itinerary['itineraryCode']);

return ['bookingCode' => $confirmation['bookingCode']];
```

### Example 3: Cancellation

```php
// 1. Query cancellation charges
$cancelQuery = $service->cancelBooking([
    'bookingCode' => 'DOTW123456',
    'confirm' => 'no',
]);

$penalty = $cancelQuery['charge'];

// 2. Show penalty to user, get approval

// 3. Confirm cancellation
$cancelConfirm = $service->cancelBooking([
    'bookingCode' => 'DOTW123456',
    'confirm' => 'yes',
    'penaltyApplied' => $penalty,
]);

// Process refund
$refundAmount = $cancelConfirm['refund'];
```

---

## Performance Considerations

### Caching Strategy

- **Search Results**: Cache 150s to reduce redundant API calls during WhatsApp conversations
- **Reference Data** (countries, cities, classifications): Cache 86400s (1 day) — change infrequently
- **Cache Driver**: Use Redis or Memcached for production (circuit breaker requires atomic `increment()`)

### Rate Limiting

- DOTW has internal rate limits — monitor 'dotw' logs for throttling
- Circuit breaker prevents hammering during outages

### Timeout Tuning

- Default: 25s (per DOTW SLA)
- If timeouts increase, check DOTW status and network
- Fallback to cached results when timeouts occur

### Multi-Tenant Isolation

- All cache keys include `company_id`
- All audit logs include `company_id`
- No credential bleed between companies

---

## Troubleshooting

### Common Issues

**Issue**: `RuntimeException: DOTW credentials not configured for this company`

**Cause**: B2B path initialized with `$companyId` but no row in `company_dotw_credentials`

**Solution**: Add credentials via DOTW Admin UI (`/admin/dotw` or `/settings` DOTW tab)

**Issue**: `DotwTimeoutException: DOTW API timeout after 25s`

**Cause**: DOTW API is slow or network latency

**Solution**:
- Check DOTW status
- Check network connectivity
- Use cached results as fallback
- Wait for circuit breaker to auto-recover (30s)

**Issue**: `Circuit is open` error

**Cause**: 5+ failures in last 60 seconds

**Solution**:
- Wait 30s for circuit to auto-close
- Or fix underlying issue (credentials, network, DOTW outage)
- Call `recordSuccess()` manually if service recovers

**Issue**: Audit logs not appearing

**Cause**: DB write failure or 'dotw' channel misconfigured

**Solution**:
- Check `config/logging.php` has 'dotw' channel
- Check `dotw_audit_logs` table exists
- Review logs for sanitization errors

---

## Security Notes

- **Credentials**: Never hardcoded; always use environment variables or DB per-company credentials
- **Sanitization**: Audit logs automatically redact passwords, card numbers, passport numbers, etc.
- **SSL/TLS**: All DOTW API calls over HTTPS
- **MD5 Hashing**: Passwords MD5-hashed per DOTW spec (not for security, protocol requirement)
- **PII**: Guest details (names, emails) logged in sanitized form only; no sensitive data persisted

