# Magic Holidays API Integration - Technical Documentation

## Complete Implementation Guide (Updated: November 1, 2025)

This document provides a comprehensive technical reference for the Magic Holidays Hotel Search API integration via GraphQL.

---

## Table of Contents
1. [GraphQL API Interface](#graphql-api-interface)
2. [Authentication & Company Detection](#authentication--company-detection)
3. [Search Workflow](#search-workflow)
4. [Token Management](#token-management)
5. [Data Structure Processing](#data-structure-processing)
6. [Database Schema](#database-schema)
7. [Error Handling](#error-handling)
8. [Known Issues & Solutions](#known-issues--solutions)
9. [Next Steps](#next-steps)

---

## GraphQL API Interface

### Mutation: `searchHotelRooms`

**Endpoint**: `/graphql`

**Input Type**: `HotelSearchInput`
```graphql
type HotelSearchInput {
  telephone: String!        # Customer phone number (used for company detection)
  hotel: String!           # Hotel name to search
  city: String             # Optional: City name for filtering
  checkIn: Date!           # Check-in date (format: YYYY-MM-DD)
  checkOut: Date!          # Check-out date (format: YYYY-MM-DD)
}
```

**Response Type**: `HotelSearchResponse`
```graphql
type HotelSearchResponse {
  success: Boolean!
  message: String
  data: HotelSearchData
}

type HotelSearchData {
  telephone: String
  hotel_name: String
  city_name: String
  check_in: Date
  check_out: Date
  room_name: String
  board_basis: String
  price: Float
  currency: String
  prebook_data: JSON
}
```

**Example Request**:
```graphql
mutation {
  searchHotelRooms(input: {
    telephone: "+60193058463"
    hotel: "GRAND MILLENIUM DUBAI"
    checkIn: "2025-11-15"
    checkOut: "2025-11-17"
  }) {
    success
    message
    data {
      hotel_name
      city_name
      room_name
      price
      currency
      prebook_data
    }
  }
}
```

---

## Authentication & Company Detection

### 1. Company/Agent Lookup
```php
// File: app/Services/HotelSearchService.php

// Finds company and agent based on phone number
$company = Company::whereHas('agents', function ($query) use ($telephone) {
    $query->where('telephone', $telephone);
})->first();

$agent = Agent::where('telephone', $telephone)->first();
```

### 2. OAuth Token Management
```php
// File: app/Services/MagicHolidayService.php

// Tokens are cached for 10 minutes to avoid excessive token requests
protected function getAccessToken(array $scopes = [])
{
    return Cache::remember('magic_holiday_access_token', 10, function () use ($scopes) {
        // Request OAuth token with client credentials
    });
}
```

**Important**: Company-specific credentials are loaded when `$companyId` is provided to the service constructor.

---

## Search Workflow

### Complete Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. GraphQL Request                                              │
│    - Validate input                                             │
│    - Find company/agent by phone                                │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. Hotel Lookup                                                 │
│    - Search in map_hotels table                                 │
│    - Match by name (fuzzy matching with similarity check)       │
│    - Get city information                                       │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. Start Async Search (POST /hotels/v1/search/start)           │
│    Request:                                                     │
│    {                                                            │
│      "destination": {"city": {"id": 1}},                        │
│      "checkIn": "2025-11-15",                                   │
│      "checkOut": "2025-11-17",                                  │
│      "occupancy": {                                             │
│        "leaderNationality": 158,                                │
│        "rooms": [{"adults": 2, "childrenAges": []}]             │
│      },                                                         │
│      "filters": {"name": "GRAND HOTEL BUCHAREST"},              │
│      "language": "en_GB",                                       │
│      "timeout": 30,                                             │
│      "sellingChannel": "B2C",                                   │
│      "availableOnly": true                                      │
│    }                                                            │
│                                                                 │
│    Response:                                                    │
│    {                                                            │
│      "status": "IN_PROGRESS",                                   │
│      "srk": "cf4a4954-9e1c-4dd5-befa-a90087f3f924",             │
│      "tokens": {                                                │
│        "progress": "eyJ0eXAi...",  // For monitoring            │
│        "async": "eyJ0eXAi...",     // For real-time results     │
│        "results": "eyJ0eXAi..."    // For final results/booking │
│      }                                                          │
│    }                                                            │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. Poll Progress (GET /hotels/v1/search/progress)              │
│    - Query param: token={progressToken}                        │
│    - Poll every 2 seconds, max 30 attempts (60 seconds)        │
│    - Check status: IN_PROGRESS → COMPLETED                     │
│    - Monitor: countOffers, countHotels                          │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 5. Get Summary (GET /hotels/v1/search/progress/summary)        │
│    - Query param: token={progressToken}                        │
│    - Returns hotel count and basic information                 │
│    - Validates if any hotels were found                        │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 6. Get Results (GET /hotels/v1/search/results/{srk})           │
│    - Query param: token={resultsToken}                         │
│    - Returns array of hotels with basic info                   │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 7. Get Hotel Offers                                            │
│    (GET /hotels/v1/search/results/{srk}/hotels/{hotelIndex}/   │
│     offers)                                                     │
│    - Query param: token={resultsToken}                         │
│    - Loop through each hotel from results                      │
│    - Fetch complete offer data including packages              │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 8. Process Packages & Extract Tokens                           │
│    - Build rooms map by index                                  │
│    - Iterate through packages (not rooms directly)             │
│    - Extract packageToken from package                         │
│    - Extract roomToken from package.packageRooms.roomReferences│
│    - Store in database with complete room details              │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 9. Select Cheapest Offer                                       │
│    - Sort all packages by price                                │
│    - Select cheapest package                                   │
│    - Prepare prebook data                                      │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 10. Prebook/Availability Check                                 │
│     (POST /hotels/v1/search/results/{srk}/hotels/{hotelIndex}/ │
│      offers/{offerIndex}/availability)                         │
│     - Query param: token={resultsToken}                        │
│     - Body: {                                                  │
│         "packageToken": "eyJob3RlbCI6IjMi...",                  │
│         "roomTokens": ["eyJyb29tQ29kZSI6IjAi..."]               │
│       }                                                         │
│     - Validates availability and locks the price               │
│     - Returns prebook details for booking                      │
└────────────────────────┬────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│ 11. Save Booking Request                                       │
│     - Store in request_booking_rooms table                     │
│     - Return complete data to GraphQL client                   │
└─────────────────────────────────────────────────────────────────┘
```

### Key Files

1. **GraphQL Entry Point**
   - `app/GraphQL/Queries/SearchHotelRooms.php`
   - Handles GraphQL request and invokes HotelSearchService

2. **Business Logic**
   - `app/Services/HotelSearchService.php`
   - Orchestrates entire search workflow
   - Methods:
     - `searchHotelRooms()` - Main entry point
     - `findHotelByName()` - Hotel lookup with fuzzy matching
     - `saveOffersAndGetCheapest()` - Process packages and select cheapest
     - `prebookOffer()` - Validate availability

3. **API Client**
   - `app/Services/MagicHolidayService.php`
   - HTTP client for Magic Holidays API
   - Methods:
     - `startHotelSearch()` - Start async search
     - `checkSearchProgress()` - Monitor progress
     - `getSearchSummary()` - Get summary
     - `getSearchResults()` - Get hotel list
     - `getHotelOffers()` - Get offers for specific hotel
     - `prebookHotel()` - Validate availability

---

## Token Management

### Token Types and Usage

| Token Type | Source | Usage | Lifetime |
|------------|--------|-------|----------|
| **Access Token** | OAuth2 | API authentication (Bearer token) | 10 minutes (cached) |
| **Progress Token** | `/search/start` response | Monitor search progress | Until search complete |
| **Async Token** | `/search/start` response | Real-time results retrieval | Until search complete |
| **Results Token** | `/search/start` response | Access results & booking APIs | Until search expires |
| **SRK** | `/search/start` response | Search Results Key - identifier | Until search expires |
| **Package Token** | Offer packages | Identifies specific package | Until booking complete |
| **Room Token** | Package room references | Identifies specific room option | Until booking complete |

### Critical Token Flow

```php
// 1. Start Search
$startResponse = $magicService->startHotelSearch($searchParams);
$progressToken = $startResponse['data']['tokens']['progress'];
$resultsToken = $startResponse['data']['tokens']['results'];
$srk = $startResponse['data']['srk'];

// 2. Monitor Progress
$progress = $magicService->checkSearchProgress($progressToken);

// 3. Get Results (requires resultsToken AND srk)
$results = $magicService->getSearchResults($srk, $resultsToken);

// 4. Get Offers (requires resultsToken AND srk)
$offers = $magicService->getHotelOffers($srk, $hotelIndex, $resultsToken);

// 5. Prebook (requires resultsToken, packageToken, AND roomTokens)
$prebook = $magicService->prebookHotel(
    $srk, 
    $hotelId, 
    $offerIndex, 
    $packageToken,      // From package
    [$roomToken],       // From package.packageRooms.roomReferences
    $resultsToken
);
```

### Important: Query Parameters for POST Requests

**Issue Resolved**: POST requests with query parameters require special handling.

```php
// File: app/Services/MagicHolidayService.php

protected function request(string $method, string $endpoint, array $scopes = [], array $params = [], array $payload = [])
{
    // Build URL with query parameters
    if ($method === 'post' && !empty($params)) {
        $endpoint .= '?' . http_build_query($params);
    }

    // For POST: send payload as body, params already in URL
    // For GET: send params as query parameters
    $response = Http::withToken($token)
        ->accept('application/json')
        ->{$method}($this->baseUrl . $endpoint, $method === 'get' ? $params : $payload);
}
```

---

## Data Structure Processing

### Understanding the API Response Structure

The Magic Holidays API returns a hierarchical structure:

```
Hotels
└── Offers (supplier-specific)
    ├── Rooms (display information)
    │   ├── index (room identifier)
    │   ├── name, boardBasis, info
    │   └── price (may be null)
    │
    └── Packages (bookable combinations)
        ├── packageToken (required for booking)
        ├── price (actual bookable price)
        └── packageRooms
            └── roomReferences
                ├── roomCode (links to rooms[index])
                └── roomToken (required for booking)
```

### Critical Implementation: Package Processing

**IMPORTANT**: Do NOT process rooms directly. Process packages and extract room information.

```php
// File: app/Services/HotelSearchService.php

// ❌ WRONG: Processing rooms directly
foreach ($offer['rooms'] as $room) {
    // rooms don't have packageToken or valid roomToken
}

// ✅ CORRECT: Processing packages
foreach ($offer['packages'] as $package) {
    $packageToken = $package['packageToken'];  // From package
    $packagePrice = $package['price']['selling']['value'];
    
    // Build room map for quick lookup
    $roomsMap = [];
    foreach ($offer['rooms'] as $room) {
        $roomsMap[$room['index']] = $room;
    }
    
    // Get room details from package references
    foreach ($package['packageRooms'] as $packageRoom) {
        foreach ($packageRoom['roomReferences'] as $roomRef) {
            $roomCode = $roomRef['roomCode'];
            $roomToken = $roomRef['roomToken'];  // From package reference
            $roomDetails = $roomsMap[$roomCode];  // Get display info
            
            // Now save with both tokens
            OfferedRoom::create([
                'room_name' => $roomDetails['name'],
                'price' => $packagePrice,           // From package
                'room_token' => $roomToken,         // From reference
                'package_token' => $packageToken,   // From package
                // ... other fields
            ]);
        }
    }
}
```

### Why This Matters

1. **Rooms**: Display information (name, amenities, board basis)
2. **Packages**: Bookable entities with pricing and booking tokens
3. **Room References**: Link packages to specific room configurations

Without proper package processing, you'll get empty tokens and prebook will fail.

---

## Database Schema

### Tables Overview

#### 1. `temporary_offers`
Stores search session information and offer metadata.

```sql
CREATE TABLE `temporary_offers` (
  `id` bigint unsigned AUTO_INCREMENT PRIMARY KEY,
  `telephone` varchar(255) NOT NULL,
  `srk` text NOT NULL,                    -- Search Results Key
  `hotel_index` varchar(255) NOT NULL,    -- Hotel identifier
  `hotel_name` varchar(255) NOT NULL,
  `offer_index` text NOT NULL,            -- Offer identifier
  `result_token` text DEFAULT NULL,       -- Results token for booking
  `enquiry_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL
);
```

#### 2. `offered_rooms`
Stores individual room/package options with booking tokens.

```sql
CREATE TABLE `offered_rooms` (
  `id` bigint unsigned AUTO_INCREMENT PRIMARY KEY,
  `temp_offer_id` bigint unsigned NOT NULL,
  `room_name` varchar(255) NOT NULL,
  `board_basis` varchar(255) DEFAULT NULL,
  `non_refundable` tinyint(1) DEFAULT 0,
  `info` text DEFAULT NULL,
  `occupancy` longtext DEFAULT NULL,       -- JSON
  `price` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `room_token` text DEFAULT NULL,          -- From packageRooms.roomReferences
  `package_token` text DEFAULT NULL,       -- From package
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  FOREIGN KEY (`temp_offer_id`) REFERENCES `temporary_offers`(`id`)
);
```

#### 3. `request_booking_rooms`
Stores booking request details for tracking.

```sql
CREATE TABLE `request_booking_rooms` (
  `id` bigint unsigned AUTO_INCREMENT PRIMARY KEY,  -- Fixed with migration
  `phone_number` varchar(255) NOT NULL,
  `hotel` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `city_id` bigint unsigned DEFAULT NULL,
  `occupancy` longtext DEFAULT NULL,               -- JSON
  `check_in` datetime NOT NULL,
  `check_out` datetime NOT NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL
);
```

**Issue Fixed**: The `id` column was missing `AUTO_INCREMENT` attribute. Fixed with migration:
```sql
ALTER TABLE request_booking_rooms 
MODIFY id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY;
```

#### 4. `map_hotels`
Hotel master data from Magic Holidays mapping API.

```sql
CREATE TABLE `map_hotels` (
  `id` bigint unsigned AUTO_INCREMENT PRIMARY KEY,
  `city_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  -- ... other fields
);
```

---

## Error Handling

### Common Errors and Solutions

#### 1. "Please specify a valid search results token!"

**Cause**: POST requests not sending query parameters correctly.

**Solution**: Modified request method to append query params to URL for POST requests.

```php
// Before (broken)
->{$method}($this->baseUrl . $endpoint, $method === 'get' ? $params : $payload)

// After (fixed)
if ($method === 'post' && !empty($params)) {
    $endpoint .= '?' . http_build_query($params);
}
->{$method}($this->baseUrl . $endpoint, $method === 'get' ? $params : $payload)
```

#### 2. Empty roomToken/packageToken

**Cause**: Processing rooms directly instead of packages.

**Solution**: Implement package-based processing (see Data Structure Processing section).

#### 3. "Field 'id' doesn't have a default value"

**Cause**: Database table missing AUTO_INCREMENT on id column.

**Solution**: Run migration to fix:
```bash
php artisan migrate  # Runs fix_request_booking_rooms_id_auto_increment
```

#### 4. Array to String Conversion

**Cause**: Trying to insert array/object into string field.

**Solution**: Extract proper values from nested structures:
```php
// Wrong
'price' => $room['price']  // This is an object

// Correct
'price' => $room['price']['selling']['value']  // Extract the numeric value
```

#### 5. Rate Limiting

**Implemented**: Intelligent rate limit handling with dynamic delays.

```php
protected function applyRequestSpacing(): void
{
    $rateLimitInfo = Cache::get('magic_rate_limit_info');
    
    // Calculate optimal delay based on remaining requests
    $optimalDelay = $this->calculateOptimalDelay($remaining, $timeUntilReset);
    
    if ($optimalDelay > 0) {
        sleep($optimalDelay);
    }
}
```

---

## Known Issues & Solutions

### 1. Token Parameter Naming

**Issue**: Documentation inconsistency between `token` (query param) and specific token types.

**Resolution**: All endpoints requiring tokens use `token` as the query parameter name, regardless of which token type (progress, results, etc.).

### 2. Package vs Room Processing

**Issue**: Initial implementation tried to extract tokens from rooms directly.

**Resolution**: Complete refactor to process packages, which are the actual bookable entities containing tokens.

### 3. Fuzzy Hotel Name Matching

**Implementation**: Uses similarity percentage for flexible matching.

```php
similar_text(strtolower($searchTerm), strtolower($hotelName), $percent);
if ($percent >= 60) {
    // Consider this a match
}
```

### 4. Occupancy Structure

**Issue**: API requires specific occupancy format with nationality.

**Current Implementation**:
```php
'occupancy' => [
    'leaderNationality' => 158,  // Malaysia
    'rooms' => [
        [
            'adults' => 2,
            'childrenAges' => []
        ]
    ]
]
```

**Future Enhancement**: Make nationality dynamic based on customer profile.

---

## Next Steps

### Phase 1: Complete (✅)
- GraphQL mutation implementation
- Company/agent detection
- Async search workflow
- Package-based token extraction
- Prebook/availability validation
- Database persistence of search results

### Phase 2: Booking Creation (⏳ Pending)

**Objective**: Complete the booking workflow by implementing the final booking step.

**Required Implementation**:

1. **Book Endpoint** (`POST /hotels/v1/search/results/{srk}/hotels/{hotelIndex}/offers/{offerIndex}/book`)
   ```php
   public function bookHotel(string $srk, int $hotelId, string $offerIndex, array $bookingData, string $resultsToken)
   {
       $endpoint = "/hotels/v1/search/results/{$srk}/hotels/{$hotelId}/offers/{$offerIndex}/book";
       $params = ['token' => $resultsToken];
       return $this->request('post', $endpoint, ['write:hotels-book'], $params, $bookingData);
   }
   ```

2. **Booking Data Structure**
   ```json
   {
     "availabilityToken": "from_prebook_response",
     "holder": {
       "name": "John",
       "surname": "Doe",
       "email": "john@example.com",
       "phone": "+60123456789"
     },
     "travelers": [...],
     "remarks": [],
     "clientReference": "unique_booking_ref"
   }
   ```

3. **Database Schema**
   - Create `bookings` table to store confirmed reservations
   - Store supplier booking ID, status, and confirmation details
   - Link to `prebookings` or `request_booking_rooms`

4. **GraphQL Mutation**
   - Create `createHotelBooking` mutation
   - Accept prebook data + traveler information
   - Return booking confirmation

### Phase 3: Future Enhancements

1. **Multi-room Support**: Handle bookings with multiple rooms
2. **Payment Integration**: Add payment processing before booking
3. **Cancellation Workflow**: Implement booking cancellation
4. **Real-time Updates**: WebSocket for search progress
5. **Caching Strategy**: Redis for frequently searched hotels
6. **Error Recovery**: Retry mechanisms for failed bookings

---

## Testing

### GraphQL Playground

Access: `http://your-domain/graphql-playground`

**Test Query**:
```graphql
mutation TestHotelSearch {
  searchHotelRooms(input: {
    telephone: "+60193058463"
    hotel: "GRAND MILLENIUM DUBAI"
    checkIn: "2025-11-15"
    checkOut: "2025-11-17"
  }) {
    success
    message
    data {
      telephone
      hotel_name
      city_name
      check_in
      check_out
      room_name
      board_basis
      price
      currency
      prebook_data
    }
  }
}
```

### Logging

All API interactions are logged to:
- `storage/logs/magic_holidays/magic_holidays-YYYY-MM-DD.log`

**Log Channels**:
```php
Log::channel('magic_holidays')->info('Message', ['context' => $data]);
```

---

## Configuration

### Environment Variables

```env
# Magic Holidays API
MAGIC_HOLIDAY_URL=https://www.magicholidays.net/reseller/api
MAGIC_HOLIDAY_TOKEN_URL=https://www.magicholidays.net/reseller/api/authorizationService/v1/oauth/token
MAGIC_HOLIDAY_CLIENT_ID=your_client_id
MAGIC_HOLIDAY_CLIENT_SECRET=your_client_secret
```

### Config File

`config/services.php`:
```php
'magic-holiday' => [
    'url' => env('MAGIC_HOLIDAY_URL'),
    'token-url' => env('MAGIC_HOLIDAY_TOKEN_URL'),
    'client-id' => env('MAGIC_HOLIDAY_CLIENT_ID'),
    'client-secret' => env('MAGIC_HOLIDAY_CLIENT_SECRET'),
],
```

---

## Appendix: API Response Examples

### 1. Start Search Response
```json
{
  "status": "IN_PROGRESS",
  "progress": 0.0,
  "countOffers": 0,
  "countHotels": 0,
  "srk": "cf4a4954-9e1c-4dd5-befa-a90087f3f924",
  "tokens": {
    "async": "eyJ0eXAiOiJKV1Qi...",
    "progress": "eyJ0eXAiOiJKV1Qi...",
    "results": "eyJ0eXAiOiJKV1Qi..."
  }
}
```

### 2. Progress Response
```json
{
  "status": "COMPLETED",
  "progress": 100.0,
  "countOffers": 490,
  "countHotels": 2,
  "srk": "cf4a4954-9e1c-4dd5-befa-a90087f3f924"
}
```

### 3. Offers Response (Simplified)
```json
{
  "id": "d5acdfa395ad87e81d17c2763bdcf16e",
  "rooms": [
    {
      "index": "0",
      "name": "Superior Room, 2 Twin Beds",
      "boardBasis": "RO",
      "info": "Package Rate. Free WiFi."
    }
  ],
  "packages": [
    {
      "packageCode": "0",
      "packageToken": "eyJob3RlbCI6IjMi...",
      "price": {
        "selling": {
          "value": 76.94,
          "currency": "KWD"
        }
      },
      "packageRooms": [
        {
          "occupancy": {
            "adults": 2,
            "childrenAges": []
          },
          "roomReferences": [
            {
              "roomCode": 0,
              "roomToken": "eyJyb29tQ29kZSI6IjAi...",
              "selected": true
            }
          ]
        }
      ]
    }
  ]
}
```

### 4. Prebook Response
```json
{
  "availabilityToken": "eyJ0eXAiOiJKV1Qi...",
  "status": "OK",
  "price": {
    "selling": {
      "value": 76.94,
      "currency": "KWD"
    }
  },
  "hotel": {
    "name": "GRAND HOTEL BUCHAREST",
    "checkIn": "2025-11-15",
    "checkOut": "2025-11-17"
  },
  "rooms": [...]
}
```

---

## Support & Maintenance

**Developer**: AI Assistant  
**Last Updated**: November 1, 2025  
**Version**: 1.0 (Search & Prebook Complete)  
**Status**: ✅ Production Ready (Phase 1)

For issues or questions, refer to the Magic Holidays API documentation or review the comprehensive logging in `storage/logs/magic_holidays/`.
