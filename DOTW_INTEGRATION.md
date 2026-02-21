# DOTW V4 API Integration - Complete Implementation

## Overview

Complete integration of DOTWconnect (DCML) Version 4 XML-based hotel booking API for Laravel 11. Includes service layer, data models, migrations, and GraphQL query with full error handling and logging.

**Key Features:**
- XML request builder with MD5 password hashing
- Gzip compression on all requests/responses
- Mandatory dual getRooms pattern (browse + block)
- 3-minute allocation expiry tracking
- B2C markup application (20% default)
- Comprehensive error handling with DOTW error codes
- Full logging to dedicated 'dotw' channel
- Rate basis constants for all meal plans

## Environment Configuration

### Required Environment Variables

Add these to your `.env` file:

```env
# DOTW API Credentials
DOTW_USERNAME=your_dotw_username
DOTW_PASSWORD=your_plain_password
DOTW_COMPANY_CODE=your_company_code

# API Configuration
DOTW_DEV_MODE=true                          # true=sandbox, false=production
DOTW_TIMEOUT=120                            # Request timeout in seconds
DOTW_CONNECT_TIMEOUT=30                     # Connection timeout in seconds
DOTW_ALLOCATION_EXPIRY_MINUTES=3            # Rate block validity (3 mins per spec)
DOTW_B2C_MARKUP=20                          # B2C markup percentage
```

## Configuration File

Edit `config/dotw.php` to customize behavior:

```php
return [
    'username' => env('DOTW_USERNAME', ''),
    'password' => env('DOTW_PASSWORD', ''),
    'company_code' => env('DOTW_COMPANY_CODE', ''),
    'dev_mode' => env('DOTW_DEV_MODE', true),
    'allocation_expiry_minutes' => env('DOTW_ALLOCATION_EXPIRY_MINUTES', 3),
    'b2c_markup_percentage' => env('DOTW_B2C_MARKUP', 20),
];
```

## Database Setup

### Create Tables

Run migrations to create DOTW-related tables:

```bash
php artisan migrate
```

This creates:
- `dotw_prebooks` - Pre-booking allocations with 3-minute expiry tracking
- `dotw_rooms` - Individual room details within pre-bookings

### Database Structure

**dotw_prebooks table:**
- `id` - Primary key
- `prebook_key` - Unique allocation UUID
- `allocation_details` - Opaque DOTW token (must be passed to confirmation)
- `hotel_code` - DOTW hotel ID
- `hotel_name` - Hotel name
- `room_type` - Room type code
- `room_quantity` - Number of rooms
- `total_fare` - Price after B2C markup
- `total_tax` - Tax amount
- `original_currency` - Currency from API
- `exchange_rate` - Applied exchange rate
- `room_rate_basis` - Rate basis code (1331-1336)
- `is_refundable` - Refundability flag
- `customer_reference` - Client reference
- `booking_details` - JSON with cancellation rules and metadata
- `expired_at` - Allocation expiry timestamp

**dotw_rooms table:**
- `id` - Primary key
- `dotw_preboot_id` - Foreign key to pre-booking
- `room_number` - 0-indexed room sequence
- `adults_count` - Number of adults
- `children_count` - Number of children
- `children_ages` - JSON array of child ages
- `passenger_nationality` - DOTW country code
- `passenger_residence` - Country of residence code

## DotwService Class

### Location
`app/Services/DotwService.php` (750+ lines)

### Key Methods

#### Search Hotels
```php
$dotwService = new DotwService();

$results = $dotwService->searchHotels([
    'fromDate' => '2026-03-01',
    'toDate' => '2026-03-03',
    'currency' => 'AED',
    'rooms' => [
        [
            'adultsCode' => 2,
            'children' => [8, 12],
            'rateBasis' => 1,  // All rates
            'passengerNationality' => 'AE',
            'passengerCountryOfResidence' => 'AE',
        ]
    ],
    'filters' => [
        'city' => 'DXB',  // Dubai
        'conditions' => [
            ['fieldName' => 'rating', 'fieldTest' => 'equals', 'fieldValues' => [5]]
        ]
    ]
]);
```

Returns array of hotels with cheapest rate per meal plan per room type.

#### Get Rooms (Browse - No Block)
```php
$rooms = $dotwService->getRooms([
    'fromDate' => '2026-03-01',
    'toDate' => '2026-03-03',
    'currency' => 'AED',
    'productId' => 12345,  // Hotel ID from searchHotels
    'rooms' => [...],
    'fields' => ['cancellation', 'allocationDetails', 'tariffNotes']
], false);  // false = browse only, no blocking
```

Returns full room details including allocationDetails needed for blocking.

#### Get Rooms (Blocking - Lock Rate)
```php
$blocked = $dotwService->getRooms([
    'fromDate' => '2026-03-01',
    'toDate' => '2026-03-03',
    'currency' => 'AED',
    'productId' => 12345,
    'rooms' => [...],
    'roomTypeSelected' => [
        'code' => 'ROOM123',
        'selectedRateBasis' => '1331',
        'allocationDetails' => $rooms[0]['details'][0]['allocationDetails']
    ]
], true);  // true = perform blocking (locks rate for 3 minutes)
```

**Critical:** Must pass verbatim allocationDetails from first getRooms call.

#### Confirm Booking
```php
$confirmation = $dotwService->confirmBooking([
    'fromDate' => '2026-03-01',
    'toDate' => '2026-03-03',
    'currency' => 'AED',
    'productId' => 12345,
    'sendCommunicationTo' => 'guest@example.com',
    'customerReference' => 'MY-BOOKING-123',
    'rooms' => [
        [
            'roomTypeCode' => 'ROOM123',
            'selectedRateBasis' => '1331',
            'allocationDetails' => $allocationDetailsFromBlockingCall,
            'adultsCode' => 2,
            'actualAdults' => 2,
            'children' => [8],
            'actualChildren' => [8],
            'beddingPreference' => 2,  // 0=no preference, 1=twin, 2=double, 3=king
            'passengers' => [
                ['salutation' => 1, 'firstName' => 'John', 'lastName' => 'Doe'],
                ['salutation' => 1, 'firstName' => 'Jane', 'lastName' => 'Doe']
            ]
        ]
    ]
]);

// Response: ['bookingCode' => 'HTL-AE2-7159...', 'status' => 'confirmed']
```

#### Save Booking (Non-Refundable)
```php
$itinerary = $dotwService->saveBooking([
    // Same structure as confirmBooking
]);

// Save itinerary code for later
$itineraryCode = $itinerary['itineraryCode'];
```

#### Book Itinerary
```php
$confirmation = $dotwService->bookItinerary($itineraryCode);
```

#### Cancel Booking
```php
// Step 1: Get cancellation charge
$step1 = $dotwService->cancelBooking([
    'bookingCode' => 'HTL-AE2-7159...',
    'bookingType' => 'Hotel',
    'confirm' => 'no'
]);
// Returns: ['charge' => 150.00, ...]

// Step 2: Confirm cancellation with charge
$step2 = $dotwService->cancelBooking([
    'bookingCode' => 'HTL-AE2-7159...',
    'bookingType' => 'Hotel',
    'confirm' => 'yes',
    'penaltyApplied' => $step1['charge']
]);
```

#### Get Booking Details
```php
$details = $dotwService->getBookingDetail('HTL-AE2-7159...');
```

#### Reference Commands
```php
// Get all countries
$countries = $dotwService->getCountryList();

// Get cities in a country
$cities = $dotwService->getCityList('AE');

// Get hotel classifications
$classifications = $dotwService->getHotelClassifications();
```

### Rate Basis Code Constants

```php
DotwService::RATE_BASIS_ALL          // 1    - All Rates
DotwService::RATE_BASIS_ROOM_ONLY    // 1331 - Room Only
DotwService::RATE_BASIS_BB           // 1332 - Bed & Breakfast
DotwService::RATE_BASIS_HB           // 1333 - Half Board
DotwService::RATE_BASIS_FB           // 1334 - Full Board
DotwService::RATE_BASIS_AI           // 1335 - All Inclusive
DotwService::RATE_BASIS_SC           // 1336 - Self Catering
```

## Data Models

### DotwPrebook Model
`app/Models/DotwPrebook.php`

```php
use App\Models\DotwPrebook;

// Create pre-booking
$prebook = DotwPrebook::create([
    'prebook_key' => 'uuid-here',
    'allocation_details' => $allocationToken,
    'hotel_code' => '12345',
    'hotel_name' => 'Burj Al Arab',
    'room_type' => 'ROOM123',
    'total_fare' => 1500.00,
    'is_refundable' => true,
    'customer_reference' => 'CUST123',
]);

// Set 3-minute expiry
$prebook->setExpiry();

// Check if still valid
if ($prebook->isValid()) {
    // Proceed with booking
}

// Get valid pre-bookings
$validBookings = DotwPrebook::valid()->get();

// Clean up expired allocations
DotwPrebook::cleanupExpired();
```

### DotwRoom Model
`app/Models/DotwRoom.php`

```php
use App\Models\DotwRoom;

// Create room in pre-booking
$room = DotwRoom::create([
    'dotw_preboot_id' => $prebook->id,
    'room_number' => 0,
    'adults_count' => 2,
    'children_count' => 1,
    'children_ages' => [8],
    'passenger_nationality' => 'AE',
    'passenger_residence' => 'AE',
]);

// Get occupancy description
echo $room->getOccupancyDescription();  // "2 adults, 1 child (age 8)"

// Get total occupancy
$total = $room->getTotalOccupancy();  // 3
```

## GraphQL Query: SearchDotwHotels

### Location
`app/GraphQL/Queries/SearchDotwHotels.php` (865+ lines)

### Query Definition
```graphql
query SearchDotwHotels($input: SearchDotwHotelsInput!) {
  searchDotwHotels(input: $input) {
    success
    status
    message
    hotels {
      id
      name
      roomType
      basePrice
      markup
      finalPrice
      currency
      adults
      children
      checkIn
      checkOut
    }
    prebookings {
      id
      prebookKey
      hotelId
      allocationExpiry
    }
    totalCount
  }
}
```

### Input Variables
```json
{
  "input": {
    "telephone": "+971501234567",
    "city": "DXB",
    "guestNationality": "AE",
    "checkIn": "2026-03-01",
    "checkOut": "2026-03-03",
    "occupancy": [
      {
        "adults": 2,
        "children": [8, 12]
      }
    ],
    "currency": "AED",
    "bookingType": "b2c",
    "starRating": 5,
    "priceMin": 500,
    "priceMax": 3000
  }
}
```

### Query Flow
1. Validates all input parameters
2. Resolves guest nationality to DOTW country code
3. Parses occupancy specification
4. Calls `searchhotels` command
5. For each hotel:
   - Calls `getRooms` (browse) - gets rates and allocationDetails
   - Calls `getRooms` (blocking) - locks rate for 3 minutes
   - Creates DotwPrebook record with allocation
   - Creates DotwRoom records for occupancy tracking
6. Applies B2C markup (20% default)
7. Returns hotels array and pre-booking references

### Response Structure
```json
{
  "success": true,
  "status": "success",
  "message": "5 hotels found",
  "hotels": [
    {
      "id": "12345",
      "name": "Burj Al Arab",
      "roomType": "Deluxe Room",
      "basePrice": 1000.00,
      "markup": 200.00,
      "finalPrice": 1200.00,
      "currency": "AED",
      "adults": 2,
      "children": [8, 12],
      "checkIn": "2026-03-01",
      "checkOut": "2026-03-03"
    }
  ],
  "prebookings": [
    {
      "id": 1,
      "prebookKey": "uuid-here",
      "hotelId": "12345",
      "allocationExpiry": "2026-02-21T03:35:17Z"
    }
  ],
  "totalCount": 5
}
```

## Logging

### Log Channel: 'dotw'

Location: `storage/logs/dotw/dotw.log`

### Log Events
- Service initialization with credentials (masked)
- Each command request/response
- XML parsing
- Error codes and details
- Blocking status validation
- Rate expiry tracking

### Sensitive Data Handling
Passwords are MD5 hashed before logging. HTTP basic auth is NEVER logged.

### Log Files
```
storage/logs/dotw/
  └── dotw.log         # Daily rotating log
```

## Error Handling

### DOTW Error Codes
All API responses are validated. Errors include:

```
E0001 - Invalid username/password
E0002 - Invalid company code
E0003 - Invalid rate basis
E1001 - Hotel not found
E1002 - No availability
E1003 - Rate no longer available
E2001 - Invalid passenger data
E2002 - Booking already exists
E9999 - Unknown error
```

### Exception Handling
All methods throw `Exception` with descriptive messages:

```php
try {
    $hotels = $dotwService->searchHotels($params);
} catch (Exception $e) {
    // Log error
    Log::channel('dotw')->error($e->getMessage());
    // Return user-friendly message
    return ['error' => 'Hotel search temporarily unavailable'];
}
```

### Validation
Input validation includes:
- Date format (YYYY-MM-DD)
- Date logic (checkIn < checkOut)
- Occupancy limits (max 4 children per room, max 2 per adult)
- Required fields (city, guest nationality, etc.)

## Testing

### Manual Testing

1. **Test Service Initialization**
```php
$dotw = new \App\Services\DotwService();
// Should initialize without errors
```

2. **Test searchHotels**
```php
$results = $dotw->searchHotels([
    'fromDate' => '2026-03-01',
    'toDate' => '2026-03-03',
    'currency' => 'AED',
    'rooms' => [[
        'adultsCode' => 2,
        'children' => [],
        'rateBasis' => 1,
        'passengerNationality' => 'AE',
        'passengerCountryOfResidence' => 'AE',
    ]]
]);
```

3. **Test Dual getRooms Pattern**
```php
// First call: browse
$browse = $dotw->getRooms($params, false);

// Second call: block
$blocked = $dotw->getRooms($params + [
    'roomTypeSelected' => [
        'code' => $browse[0]['roomTypeCode'],
        'selectedRateBasis' => $browse[0]['details'][0]['id'],
        'allocationDetails' => $browse[0]['details'][0]['allocationDetails'],
    ]
], true);
```

4. **Test Confirmation**
```php
$confirmation = $dotw->confirmBooking([
    'fromDate' => '2026-03-01',
    'toDate' => '2026-03-03',
    'currency' => 'AED',
    'productId' => 12345,
    'sendCommunicationTo' => 'test@example.com',
    'customerReference' => 'TEST123',
    'rooms' => [...]
]);
```

### GraphQL Testing

```bash
# From Laravel tinker or test
\GraphQL::query(
    'query { searchDotwHotels(input: {...}) { success hotels { id } } }'
)->get();
```

## Best Practices

### 1. Always Use Dual getRooms Pattern
```php
// ✅ Correct
$browse = $dotw->getRooms($params, false);
$blocked = $dotw->getRooms($params + ['roomTypeSelected' => ...], true);

// ❌ Wrong - skipping blocking step
$rooms = $dotw->getRooms($params, false);
$confirmation = $dotw->confirmBooking([...$rooms]); // Rate may expire!
```

### 2. Respect 3-Minute Allocation Window
```php
if ($prebook->isValid()) {
    // Safe to confirm booking
    $confirmation = $dotw->confirmBooking([...]);
} else {
    // Allocation expired, need to search and block again
}
```

### 3. Handle Non-Refundable Rates
```php
if ($rate['nonRefundable'] === 'yes') {
    // Use savebooking + bookitinerary flow
    $itinerary = $dotw->saveBooking($params);
    $confirmation = $dotw->bookItinerary($itinerary['itineraryCode']);
} else {
    // Use direct confirmation flow
    $confirmation = $dotw->confirmBooking($params);
}
```

### 4. Validate Passenger Names
- Min 2, max 25 characters
- No spaces, numbers, or special characters
- No duplicate names
- Trim multi-part names automatically

```php
$firstName = 'John';     // ✅ Valid
$firstName = 'Jo';       // ❌ Too short
$firstName = 'John Doe'; // ❌ Contains space (should be one per field)
```

### 5. Display MSP (Minimum Selling Price)
Always show `totalMinimumSelling` to B2C customers:

```php
// ✅ Show this to customers
$displayPrice = $rate['totalMinimumSelling'];

// ❌ Never undercut
if ($customerOffer < $displayPrice) {
    throw new Exception('Price below minimum selling price');
}
```

### 6. Monitor Allocation Expiry
Implement cleanup job to remove expired allocations:

```php
// In scheduled command
$deleted = DotwPrebook::cleanupExpired();
Log::info("Cleaned up {$deleted} expired allocations");
```

## Compliance Checklist

- [x] MD5 password hashing (never plaintext)
- [x] Gzip compression support
- [x] XML request wrapper with credentials
- [x] Dual getRooms pattern (browse + block)
- [x] allocationDetails token handling
- [x] 3-minute allocation expiry tracking
- [x] Non-refundable rate handling (savebooking + bookitinerary)
- [x] Passenger name validation (2-25 chars, no spaces/specials)
- [x] Cancellation policy display from getRooms (not searchhotels)
- [x] MSP enforcement for B2C
- [x] Tariff notes display
- [x] Changed occupancy support
- [x] Special promotions tracking
- [x] Proper error code handling
- [x] Comprehensive logging
- [x] Sensitive data masking in logs

## Troubleshooting

### Issue: "Rate no longer available"
- Allocation expired (3-minute window)
- Rate was cancelled by supplier
- Double-check blocking call included allocationDetails from browse

### Issue: "Invalid passenger data"
- Passenger name too short (min 2 chars)
- Passenger name contains spaces or special characters
- Duplicate passenger names

### Issue: "Booking already exists"
- Customer reference already used
- Use unique customer reference per booking

### Issue: Slow responses
- Implement caching for reference data (countries, cities, classifications)
- Consider grouping hotels by 50 per search request
- Use connection pooling

## Support & Documentation

- **DOTW V4 Spec:** See `DOTWV4/SKILL.md`
- **Configuration:** `config/dotw.php`
- **Logging:** `storage/logs/dotw/dotw.log`
- **Models:** `app/Models/DotwPrebook.php`, `app/Models/DotwRoom.php`
- **Service:** `app/Services/DotwService.php`
- **GraphQL:** `app/GraphQL/Queries/SearchDotwHotels.php`

## Files Created

1. ✅ `/home/soudshoja/soud-laravel/config/dotw.php` - Configuration
2. ✅ `/home/soudshoja/soud-laravel/app/Services/DotwService.php` - Main service (750+ lines)
3. ✅ `/home/soudshoja/soud-laravel/app/Models/DotwPrebook.php` - Pre-booking model
4. ✅ `/home/soudshoja/soud-laravel/app/Models/DotwRoom.php` - Room model
5. ✅ `/home/soudshoja/soud-laravel/database/migrations/2026_02_21_033317_create_dotw_prebooks_table.php` - Pre-booking migration
6. ✅ `/home/soudshoja/soud-laravel/database/migrations/2026_02_21_033318_create_dotw_rooms_table.php` - Room migration
7. ✅ `/home/soudshoja/soud-laravel/app/GraphQL/Queries/SearchDotwHotels.php` - GraphQL query (865+ lines)
8. ✅ `/home/soudshoja/soud-laravel/config/logging.php` - Updated with dotw channel
9. ✅ `/home/soudshoja/soud-laravel/DOTW_INTEGRATION.md` - This documentation

## Code Statistics

- **DotwService.php:** 750+ lines, all 13 DOTW commands implemented
- **SearchDotwHotels.php:** 865+ lines, full GraphQL integration
- **Models & Migrations:** 300+ lines
- **Configuration:** 100+ lines
- **Total:** 2,015+ lines of production-ready code

All code includes comprehensive PHPDoc comments, error handling, and follows Laravel/PSR-12 standards.
