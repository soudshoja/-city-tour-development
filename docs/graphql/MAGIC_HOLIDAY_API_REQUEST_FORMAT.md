# Magic Holiday API - Correct Request Format

## Based on Official OpenAPI Documentation (api.hotels.v1.documentation-openapi.yaml)

---

## 🎯 Key Findings from Documentation

### 1. **Occupancy Structure - CRITICAL**

The `occupancy.rooms` field is an **ARRAY of room objects**, NOT a single object!

#### Correct Structure:
```json
{
  "occupancy": {
    "leaderNationality": 158,
    "rooms": [
      {
        "adults": 2,
        "childrenAges": []
      },
      {
        "adults": 1,
        "childrenAges": [5]
      }
    ]
  }
}
```

#### Schema Definition:
- `OccupancySearchInputType` (required: `leaderNationality`, `rooms`)
  - `rooms`: **RoomOccupanciesSearchInputType** (array, minItems: 1, maxItems: 5)
    - Each item: **RoomOccupancySearchInputType** (required: `adults`)
      - `adults`: integer (minimum: 1, maximum: 9)
      - `childrenAges`: array of integers (0-18, maxItems: 8)

---

### 2. **Destination Options**

Multiple destination types are supported:

```typescript
// Option 1: City by ID
{ "destination": { "city": { "id": 48 } } }

// Option 2: City by Code
{ "destination": { "city": { "code": "LON" } } }

// Option 3: Region
{ "destination": { "region": { "id": 91 } } }

// Option 4: Location by ID
{ "destination": { "location": { "id": 3663 } } }

// Option 5: Location by Code
{ "destination": { "location": { "code": "206e6" } } }

// Option 6: Geofence (radius search)
{ 
  "destination": { 
    "geofence": { 
      "latitude": 51.52174815, 
      "longitude": -0.11330118, 
      "radius": "10" 
    } 
  } 
}

// Option 7: Specific Hotels (accommodation IDs)
{ "destination": { "accommodation": [1051316, 1264590121] } }
```

---

### 3. **Filters Structure**

```json
{
  "filters": {
    "name": "central",          // Partial or full hotel name (minLength: 2)
    "classification": [3, 4, 5] // Star ratings (1-5)
  }
}
```

---

### 4. **Complete Search Request Example**

Based on the documentation examples:

```json
{
  "destination": {
    "city": {
      "id": 48
    }
  },
  "checkIn": "2021-02-08",
  "checkOut": "2021-02-15",
  "occupancy": {
    "leaderNationality": 158,
    "rooms": [
      {
        "adults": 2,
        "childrenAges": [10, 5]
      }
    ]
  },
  "sellingChannel": "B2B",
  "language": "en_GB",
  "timeout": 10,
  "availableOnly": true,
  "filters": {
    "name": "central",
    "classification": [3, 4, 5]
  },
  "providers": ["expediarapid"]
}
```

---

### 5. **Required vs Optional Fields**

#### Required (AbstractSearchInput):
- ✅ `checkIn` (string, format: date, e.g., "2021-02-08")
- ✅ `checkOut` (string, format: date, e.g., "2021-02-15")
- ✅ `occupancy` (OccupancySearchInputType)
- ✅ `sellingChannel` (SellingChannelType - e.g., "B2B", "B2C")

#### Required for Async Search (SearchInput):
- ✅ `destination` (DestinationSearchInputType)

#### Optional:
- ⭕ `language` (string, e.g., "en_GB" - 5 characters)
- ⭕ `timeout` (integer, minimum: 1, in seconds)
- ⭕ `availableOnly` (boolean - only bookable rooms)
- ⭕ `cancellationDeadline` (boolean - compute cancellation info)
- ⭕ `travelPurpose` (enum: 'leisure', 'business')
- ⭕ `providers` (array of provider codes)
- ⭕ `filters` (FiltersSearchInputType)
- ⭕ `providerParameters` (object with provider-specific params)

---

### 6. **Authentication**

- **Scope Required**: `read:hotels-search`
- **Token Type**: OAuth 2.0 Bearer Token
- **Critical Warning**: DO NOT request new token for every API call! Cache and reuse tokens until expiration.

---

### 7. **API Endpoints**

#### Asynchronous Search (for destinations, flexible searches):
```
POST /hotels/v1/search/start
```

#### Synchronous Search (for specific hotels, max 200 IDs):
```
POST /hotels/v1/search/sync
```

---

## 🔧 Changes Needed in HotelSearchService.php

### Current Code (INCORRECT):
```php
'occupancy' => [
    'leaderNationality' => 158,
    'rooms' => [
        'adults' => 2,
        'childrenAges' => []
    ]
]
```

### Should Be (CORRECT):
```php
'occupancy' => [
    'leaderNationality' => 158,
    'rooms' => [
        [
            'adults' => 2,
            'childrenAges' => []
        ]
    ]
]
```

Note the extra array wrapper around the room object - it's an array of room objects!

---

## 📝 Additional Notes

1. **Multiple Rooms Support**: The API supports 1-5 rooms per search (minItems: 1, maxItems: 5)

2. **Children Ages**: Must be 0-18, with maximum 8 children per room

3. **Date Format**: ISO 8601 date format (YYYY-MM-DD)

4. **Rate Limiting**: Monitor `X-RateLimit-*` headers in responses

5. **Filter Field**: Use `name` (not `hotelName` or other variations) for hotel name filtering

6. **Leader Nationality**: Required integer field (country code)

---

## 🚀 Recommended Implementation

For searching by hotel name in a city:

```php
$searchParams = [
    'destination' => [
        'city' => [
            'id' => $cityId // from map_hotels database
        ]
    ],
    'checkIn' => '2024-12-01',
    'checkOut' => '2024-12-05',
    'occupancy' => [
        'leaderNationality' => 158, // Kuwait (or detect from company)
        'rooms' => [
            [
                'adults' => 2,
                'childrenAges' => []
            ]
        ]
    ],
    'filters' => [
        'name' => 'hotel name here'
    ],
    'language' => 'en_GB',
    'timeout' => 10,
    'sellingChannel' => 'B2B',
    'availableOnly' => true
];
```

---

## 📚 Documentation Reference

- **File**: api.hotels.v1.documentation-openapi.yaml
- **API Version**: 1.3.2
- **Base URL**: https://www.magicholidays.net/reseller/api
- **Endpoint**: POST /hotels/v1/search/start (async) or POST /hotels/v1/search/sync (sync)
