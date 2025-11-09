# Magic Holidays Search Endpoints

## Overview

The Magic Holidays async search workflow has **multiple endpoints** for retrieving search data after completion:

### 1. Summary Endpoint (`/hotels/v1/search/progress/summary`)
- **Purpose**: Quick overview of search results
- **Use Case**: Check how many hotels found, price ranges, basic info
- **Token**: Uses `progress` token (from search start response)
- **Data Structure**:
  ```json
  {
    "count": 2,
    "hotels": [
      {
        "id": 3,
        "name": "GRAND HOTEL BUCHAREST",
        "stars": 5,
        "minPrice": {"value": 75.48, "currency": "KWD"},
        "boardBasis": [...],
        "nonRefundable": [...]
        // NO DETAILED OFFERS OR ROOM TOKENS
      }
    ]
  }
  ```
- **Limitations**: 
  - No detailed room information
  - No offer indices
  - No room tokens or package tokens
  - Only summary pricing data

### 2. Results Endpoint (`/hotels/v1/search/results/{srk}`)
- **Purpose**: Get list of hotels from completed search
- **Use Case**: Retrieve hotel IDs to fetch detailed offers
- **Parameter**: Uses `srk` (Search Results Key from start response)
- **Data Structure**:
  ```json
  {
    "hotels": [
      {
        "id": 3,
        "index": 3,
        "name": "GRAND HOTEL BUCHAREST",
        "stars": 5,
        "minPrice": {"value": 75.48, "currency": "KWD"}
        // NO OFFERS INCLUDED HERE
      }
    ]
  }
  ```
- **Limitations**:
  - Only returns hotel list with basic info
  - No offers included
  - Must call offers endpoint for each hotel

### 3. Hotel Offers Endpoint (`/hotels/v1/search/results/{srk}/hotels/{hotelIndex}/offers`)
- **Purpose**: Get detailed offers with bookable rooms for specific hotel
- **Use Case**: Fetch complete room details, tokens, and prebook-ready data
- **Parameters**: Uses `srk` + `hotelIndex` (hotel id from results)
- **Data Structure**:
  ```json
  {
    "offers": [
      {
        "id": "offer-123",
        "index": "offer-123",
        "rooms": [
          {
            "name": "Double Room",
            "boardBasis": "BB",
            "nonRefundable": false,
            "price": {"value": 75.48, "currency": "KWD"},
            "roomToken": "room-token-xyz",
            "packageToken": "package-token-abc",
            "occupancy": {...}
          }
        ],
        "packages": [...]
      }
    ]
  }
  ```
- **Advantages**:
  - Complete room details with tokens
  - Offer indices for prebooking
  - Room tokens and package tokens
  - Ready for prebook API call

## Workflow

```
1. Start Search → Get srk, progress token, async token, results token
2. Poll Progress  → Use progress token to check status
3. Check Summary  → Use progress token (optional, quick overview)
4. Get Results    → Use srk to get hotel list
5. Get Offers     → Use srk + hotelIndex for each hotel
6. Prebook Offer  → Use srk + hotelIndex + offerIndex
7. Book           → Use prebook data
```

## Token vs SRK Usage

| Endpoint | Parameter Type | Value | Purpose |
|----------|---------------|-------|---------|
| `/hotels/v1/search/start` | - | - | Start search, returns srk and tokens |
| `/hotels/v1/search/progress` | Token | `progress` | Check completion status |
| `/hotels/v1/search/progress/summary` | Token | `progress` | Quick overview (optional) |
| `/hotels/v1/search/results/{srk}` | SRK | `srk` | Get hotel list |
| `/hotels/v1/search/results/{srk}/hotels/{hotelIndex}/offers` | SRK + ID | `srk` + `hotelIndex` | Get detailed offers |
| `/hotels/v1/search/results/{srk}/hotels/{hotelIndex}/offers/{offerIndex}/availability` | SRK + IDs | `srk` + `hotelIndex` + `offerIndex` | Prebook |

## Why SRK for Results and Offers?

**SRK (Search Results Key)** is the main identifier for the entire search session:
- Stored in database (`temporary_offers` table has `srk` field)
- Used for all result-related operations
- Persists throughout the booking workflow
- Links all API calls to the same search session

**Tokens** are temporary access credentials:
- `progress` token: Only for monitoring search status
- `async` token: For real-time results during search
- `results` token: Not actually used (legacy field)

## Implementation

In our GraphQL hotel search service:

```php
// 1. Start search - get srk
$searchResult = $this->startSearch($magicService, $searchParams);
$srk = $searchResult['srk'];
$progressToken = $searchResult['progress_token'];

// 2. Wait for completion
$this->pollSearchProgress($magicService, $progressToken);

// 3. Optional: Check summary (just to verify hotels exist)
$summary = $this->getSearchSummary($magicService, $progressToken);
if (empty($summary['hotels'])) {
    return ['success' => false, 'message' => 'No hotels found'];
}

// 4. Get hotel list using srk
$resultsResponse = $magicService->getSearchResults($srk);
$hotels = $resultsResponse['data']['hotels'];

// 5. Get detailed offers for each hotel using srk + hotelIndex
foreach ($hotels as $hotel) {
    $hotelIndex = $hotel['id'];
    $offersResponse = $magicService->getHotelOffers($srk, $hotelIndex);
    $hotel['offers'] = $offersResponse['data']['offers'];
}

// 6. Process offers and rooms with tokens
$cheapestData = $this->saveOffersAndGetCheapest($telephone, $srk, $enquiryId, ['hotels' => $hotels]);

// 7. Prebook using srk + hotelIndex + offerIndex
$prebookResponse = $this->prebookOffer($magicService, [
    'srk' => $srk,
    'hotel_id' => $hotelIndex,
    'offer_index' => $offerIndex,
    'rooms' => [...]
]);
```

## Common Mistakes

❌ **Wrong**: Using results token for results endpoint
```php
$results = $magicService->getSearchResults($resultsToken);
// ERROR: Results endpoint uses srk, not results token
```

✅ **Correct**: Using srk for results endpoint
```php
$results = $magicService->getSearchResults($srk);
// SUCCESS: Results endpoint requires srk
```

❌ **Wrong**: Expecting offers in results response
```php
$results = $magicService->getSearchResults($srk);
$offers = $results['data']['hotels'][0]['offers'];
// ERROR: Results don't include offers
```

✅ **Correct**: Fetching offers separately for each hotel
```php
$results = $magicService->getSearchResults($srk);
$hotelIndex = $results['data']['hotels'][0]['id'];
$offersResponse = $magicService->getHotelOffers($srk, $hotelIndex);
$offers = $offersResponse['data']['offers'];
// SUCCESS: Offers fetched separately
```

## Field Name Variations

The API may use different field naming conventions:

| Field | Possible Names |
|-------|---------------|
| Hotel ID | `id`, `index`, `hotelIndex` |
| Offer ID | `index`, `id`, `offerIndex` |
| Room Name | `name`, `roomName` |
| Board Basis | `boardBasis`, `board_basis` |
| Non-Refundable | `nonRefundable`, `non_refundable` |
| Price | `price.value`, `price.amount`, `price` |
| Room Token | `roomToken`, `room_token` |
| Package Token | `packageToken`, `package_token` |

Our implementation handles all variations with fallback logic.
