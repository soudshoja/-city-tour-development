# DOTWconnect Reference Data API Integration Guide

## Overview

The DOTWconnect reference lookup APIs provide essential master data for building hotel search interfaces and booking systems. These are foundational lookups that must be cached and used to populate filter options and validate user inputs.

## Core Reference Methods

### 1. **getallcountries** - Country Master Data
**Purpose**: Retrieve all available countries with internal codes and regional groupings

**Key Information**:
- Returns: Country name, internal code, region name, region code
- Method: Simple request with optional field selection
- Critical Data: `code` field is REQUIRED for all subsequent country-based lookups

**Response Fields**:
```xml
<country>
  <name>United States</name>
  <code>US_CODE_12345</code>
  <regionName>North America</regionName>
  <regionCode>NA_001</regionCode>
</country>
```

**Usage in Soud Laravel**:
```php
// Cache countries at startup
$countries = $api->request('getallcountries');

// Store in cache
Cache::put('dotw_countries', $countries, now()->addDays(30));

// Use country code for passenger nationality
$booking->passenger_country = $country['code']; // NOT the name!
```

---

### 2. **getservingcities** - City Lookup by Country
**Purpose**: Get available cities within a selected country

**Key Information**:
- Requires: Country code from getallcountries
- Returns: City names and internal codes
- Filters: Can apply topDeals, luxury, specialDeals flags

**Request Example**:
```xml
<request command="getservingcities">
  <return>
    <filters>
      <countryCode>US_CODE_12345</countryCode>
      <topDeals>true</topDeals>
      <luxury>false</luxury>
      <specialDeals>true</specialDeals>
    </filters>
    <fields>
      <field>name</field>
      <field>code</field>
    </fields>
  </return>
</request>
```

**Response Fields**:
```xml
<city>
  <name>New York</name>
  <code>NYC_001_DOTW</code>
</city>
```

**Usage in Soud Laravel**:
```php
// Populate city dropdown based on selected country
public function getCitiesByCountry($countryCode)
{
    $cities = Cache::remember("dotw_cities_{$countryCode}", 3600, function() use ($countryCode) {
        return $api->request('getservingcities', [
            'countryCode' => $countryCode,
            'topDeals' => true
        ]);
    });

    return $cities['cities'];
}

// Use city code in hotel search
$search = $api->searchHotels([
    'cityCode' => $city['code'], // Use code, not name!
    'checkIn' => '2026-03-15',
    'checkOut' => '2026-03-20'
]);
```

---

### 3. **gethotelclassificationids** - Star Ratings/Classes
**Purpose**: Get available hotel classification/rating systems

**Key Information**:
- Simple lookup (no parameters)
- Returns: Classification codes and descriptions
- Examples: 1-5 stars, Budget/Standard/Luxury categories

**Response Fields**:
```xml
<option runno="1" value="5">5-Star (Luxury)</option>
<option runno="2" value="4">4-Star (Deluxe)</option>
<option runno="3" value="3">3-Star (Standard)</option>
```

**Usage in Soud Laravel**:
```php
// Cache hotel classifications
$classifications = Cache::remember('dotw_classifications', 30 * 24 * 3600, function() {
    return $api->request('gethotelclassificationids');
});

// Use in search filter
$search = $api->searchHotels([
    'classification' => '4', // 4-star hotels
    'cityCode' => $cityCode
]);
```

---

### 4. **getamenitieids** - Hotel Amenities/Facilities
**Purpose**: Get list of available amenities for hotel filtering

**Key Information**:
- Simple lookup (no parameters)
- Returns: Amenity codes with descriptions
- Common amenities: WiFi, Pool, Gym, Restaurant, Parking, AC, etc.

**Response Fields**:
```xml
<option runno="1" value="WIFI">Free WiFi / Internet</option>
<option runno="2" value="POOL">Swimming Pool</option>
<option runno="3" value="GYM">Fitness Center</option>
<option runno="4" value="PARKING">Free Parking</option>
<option runno="5" value="RESTAURANT">Restaurant</option>
```

**Usage in Soud Laravel**:
```php
// Cache amenities
$amenities = Cache::remember('dotw_amenities', 30 * 24 * 3600, function() {
    return $api->request('getamenitieids');
});

// Build amenity checkboxes in search form
@foreach($amenities as $amenity)
    <input type="checkbox" name="amenities[]" value="{{ $amenity['value'] }}">
    {{ $amenity['description'] }}
@endforeach

// Use in hotel search (multiple selections)
$search = $api->searchHotels([
    'amenities' => ['WIFI', 'POOL', 'PARKING'],
    'cityCode' => $cityCode
]);
```

---

### 5. **getchainids** - Hotel Chain/Brand Codes
**Purpose**: Get available hotel chains for brand-based filtering

**Key Information**:
- Simple lookup (no parameters)
- Returns: Hotel chain codes and brand names
- Examples: Marriott, Hilton, Hyatt, IHG, Accor, etc.

**Response Fields**:
```xml
<option runno="1" value="MAR">Marriott International</option>
<option runno="2" value="HIL">Hilton Hotels</option>
<option runno="3" value="HYA">Hyatt Hotels</option>
<option runno="4" value="IHG">InterContinental Hotels Group</option>
```

**Usage in Soud Laravel**:
```php
// Cache hotel chains
$chains = Cache::remember('dotw_chains', 30 * 24 * 3600, function() {
    return $api->request('getchainids');
});

// Use in search filter
$search = $api->searchHotels([
    'chains' => ['MAR', 'HIL'], // Multiple chains supported
    'cityCode' => $cityCode,
    'minRating' => '4'
]);
```

---

### 6. **getleisureids** - Preference/Leisure Types
**Purpose**: Get available leisure and preference types for guest profiling

**Key Information**:
- Simple lookup (no parameters)
- Returns: Leisure type codes and descriptions
- Examples: Business, Leisure, Adventure, Family, Honeymoon, etc.

**Response Fields**:
```xml
<option runno="1" value="BUS">Business Travel</option>
<option runno="2" value="LEI">Leisure/Vacation</option>
<option runno="3" value="FAM">Family Vacation</option>
<option runno="4" value="ADV">Adventure</option>
```

**Usage in Soud Laravel**:
```php
// Store in guest preferences
$guest->preference_type = 'BUS'; // Business traveler

// Use for personalized recommendations
if ($guest->preference_type === 'FAM') {
    $search->minStars = 4; // Recommend 4+ star for families
}
```

---

### 7. **getlocationids** - Location/Region Codes
**Purpose**: Get granular location/neighborhood codes within cities

**Key Information**:
- Simple lookup (no parameters)
- Returns: Location codes for city districts/neighborhoods
- Finer granularity than city-level filtering

**Usage in Soud Laravel**:
```php
// Filter hotels by specific neighborhood
$search = $api->searchHotels([
    'cityCode' => $cityCode,
    'locationCode' => $locationCode, // Specific district/area
]);
```

---

## Implementation Pattern

### 1. Initialize Reference Data Cache
```php
namespace App\Services;

class DOTWConnectService
{
    protected $api;
    protected $cacheExpiry = 2592000; // 30 days

    public function initializeReferences()
    {
        // Fetch all reference data at startup
        $this->cacheCountries();
        $this->cacheAmenities();
        $this->cacheChains();
        $this->cacheClassifications();
        $this->cacheLeisureTypes();
    }

    private function cacheCountries()
    {
        $countries = $this->api->request('getallcountries');
        Cache::put('dotw_countries', $countries, $this->cacheExpiry);

        // Index by code for quick lookup
        $byCode = collect($countries['country'])->keyBy('code')->toArray();
        Cache::put('dotw_countries_by_code', $byCode, $this->cacheExpiry);
    }

    public function getCountries()
    {
        return Cache::get('dotw_countries');
    }

    public function getCountryByCode($code)
    {
        $countries = Cache::get('dotw_countries_by_code');
        return $countries[$code] ?? null;
    }
}
```

### 2. Populate UI Filters
```php
// HotelSearchController.php
public function showSearchForm()
{
    $countries = DOTWConnect::getCountries();
    $amenities = Cache::get('dotw_amenities');
    $chains = Cache::get('dotw_chains');
    $classifications = Cache::get('dotw_classifications');

    return view('hotel-search', compact(
        'countries',
        'amenities',
        'chains',
        'classifications'
    ));
}
```

### 3. Validate and Use in Search
```php
public function searchHotels(HotelSearchRequest $request)
{
    // Validate country code exists
    $country = DOTWConnect::getCountryByCode($request->country_code);
    if (!$country) {
        return response()->json(['error' => 'Invalid country'], 400);
    }

    // Get cities for this country
    $cities = DOTWConnect::getCitiesByCountry($country['code']);

    // Validate city code
    $city = collect($cities)->firstWhere('code', $request->city_code);
    if (!$city) {
        return response()->json(['error' => 'Invalid city'], 400);
    }

    // Build search request
    $searchParams = [
        'cityCode' => $city['code'],
        'checkIn' => $request->check_in,
        'checkOut' => $request->check_out,
        'rooms' => $request->rooms,
        'amenities' => $request->amenities ?? [],
        'chains' => $request->chains ?? [],
        'classification' => $request->classification ?? null,
    ];

    return $this->api->searchHotels($searchParams);
}
```

---

## Best Practices

### 1. **Cache Everything**
- Reference data changes infrequently - cache for 30 days
- Reduces API calls and improves performance
- Clear cache programmatically after data updates

### 2. **Always Use Codes, Never Names**
- ✅ Correct: `cityCode: 'NYC_001_DOTW'`
- ❌ Wrong: `cityCode: 'New York'`
- Codes are stable; names may vary by locale

### 3. **Validate User Input**
```php
// Always validate against reference data
$validCountries = collect(Cache::get('dotw_countries'))
    ->pluck('code')
    ->toArray();

if (!in_array($request->country, $validCountries)) {
    throw new ValidationException('Invalid country');
}
```

### 4. **Handle Reference Data Not Found**
```php
// Some cities/chains may be unavailable
$cities = DOTWConnect::getCitiesByCountry($countryCode);
if (empty($cities)) {
    return ['error' => 'No cities available for this country'];
}
```

### 5. **Monitor Reference Data Updates**
```php
// Add scheduler to refresh reference data
// app/Console/Kernel.php
$schedule->call(function () {
    app(DOTWConnectService::class)->initializeReferences();
})->daily()->at('2:00am');
```

---

## Response Structure Summary

All reference lookups return a consistent structure:

```xml
<result command="METHOD_NAME" date="YYYY-MM-DD">
  <!-- Method-specific data -->
  <successful>TRUE</successful>
</result>
```

Always check `<successful>` flag:
- `TRUE` = Data retrieved successfully
- `FALSE` = Error occurred

---

## Related Methods (Not Reference)

These methods USE the reference data codes:
- `searchhotels` - Accepts country, city, chain, classification, amenity codes
- `getrooms` - Requires hotel/room selection from search
- `savebooking` - Uses country codes for passenger nationality
- `confirmbooking` - Booking confirmation

---

## Integration Checklist

- [ ] Create DOTWConnectService with reference caching
- [ ] Initialize reference data on application startup
- [ ] Populate UI filters from cached reference data
- [ ] Validate user input against reference data
- [ ] Use codes (not names) in all API requests
- [ ] Handle missing/unavailable reference data gracefully
- [ ] Set up cache invalidation strategy
- [ ] Test with actual API credentials
- [ ] Monitor cache hit rates and API performance
- [ ] Document any custom reference data handling

---

## File Locations in Soud Laravel

**Service Implementation**:
```
app/Services/DOTWConnectService.php
```

**API Client**:
```
app/AI/Services/DOTWConnectClient.php (if integrated)
```

**Configuration**:
```
config/dotw.php
.env variables:
  DOTW_API_KEY
  DOTW_API_SECRET
  DOTW_API_HOST
  DOTW_CACHE_EXPIRY
```

**Database Schema** (if caching in DB):
```
app/Models/DOTWReferenceData.php
database/migrations/create_dotw_reference_tables.php
```
