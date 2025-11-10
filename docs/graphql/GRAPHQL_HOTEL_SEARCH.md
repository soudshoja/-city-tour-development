# GraphQL Hotel Search - Setup & Testing Guide

## Overview
This GraphQL API allows you to search for hotel rooms using Magic Holidays API and get the cheapest available room with prebook details in a single request.

## Setup Completed ✅

1. **Installed Packages:**
   - `nuwave/lighthouse` (v6.63.1) - Laravel GraphQL implementation
   
2. **Created Files:**
   - `app/Services/HotelSearchService.php` - Main service orchestrating the hotel search flow
   - `app/GraphQL/Queries/SearchHotelRooms.php` - GraphQL resolver
   - `app/GraphQL/Scalars/MixedScalar.php` - Custom scalar for JSON data
   - `graphql/schema.graphql` - GraphQL schema definition
   
3. **Updated Files:**
   - `app/Services/MagicHolidayService.php` - Added hotel search API methods:
     - `startHotelSearch()` - POST /reseller/api/hotels/v1/search/start
     - `checkSearchProgress()` - POST /reseller/api/hotels/v1/search/progress
     - `getSearchSummary()` - POST /reseller/api/hotels/v1/search/progress/summary
     - `prebookHotel()` - POST /reseller/api/hotels/v1/search/results/{srk}/hotels/{hotel_id}/offers/{offer_index}/availability

## How It Works

### The Complete Flow (Single Request):

1. **Phone Number Detection** → Finds company credentials via Agent/Branch relationship
2. **Hotel Lookup** → Searches for exact hotel name in MapHotel table
3. **Start Search** → Initiates Magic Holiday search
4. **Poll Progress** → Waits for search completion (max 60 attempts, 2 sec interval)
5. **Get Summary** → Retrieves all available offers
6. **Save & Filter** → Saves to TemporaryOffer & OfferedRoom tables, finds cheapest
7. **Prebook** → Confirms availability and gets booking token
8. **Save Request** → Updates RequestBookingRoom with search details
9. **Return Result** → Returns cheapest room with prebook data

### Company Credentials Logic:
```
Phone Number → Agent → Branch → Company → SupplierCredential (Magic Holiday)
                                     ↓
                               If not found → Use default config from services.php
```

## GraphQL Endpoint

**URL:** `http://your-domain.com/graphql`

**Method:** POST

**Content-Type:** application/json

## Example Query

### Request:
```graphql
query SearchHotelRooms {
  searchHotelRooms(input: {
    telephone: "+96512345678"
    hotel: "Hilton Kuwait Resort"
    checkIn: "2025-12-01"
    checkOut: "2025-12-05"
  }) {
    success
    message
    data {
      telephone
      hotel_name
      srk
      enquiry_id
      hotel_index
      offer_index
      result_token
      room {
        id
        room_name
        board_basis
        non_refundable
        price
        currency
        room_token
        package_token
        occupancy
      }
      prebook {
        availability_token
        check_in
        check_out
        duration
        autocancel_date
        cancel_policy
        remarks
      }
    }
  }
}
```

### cURL Example:
```bash
curl -X POST http://your-domain.com/graphql \
  -H "Content-Type: application/json" \
  -d '{
    "query": "query SearchHotelRooms { searchHotelRooms(input: { telephone: \"+96512345678\", hotel: \"Hilton Kuwait Resort\", checkIn: \"2025-12-01\", checkOut: \"2025-12-05\" }) { success message data { telephone hotel_name srk room { room_name price currency } prebook { availability_token } } } }"
  }'
```

### Success Response:
```json
{
  "data": {
    "searchHotelRooms": {
      "success": true,
      "message": null,
      "data": {
        "telephone": "+96512345678",
        "hotel_name": "Hilton Kuwait Resort",
        "srk": "SRK123456",
        "enquiry_id": "ENQ789",
        "hotel_index": 12345,
        "offer_index": "1",
        "result_token": "token_xyz",
        "room": {
          "id": "1",
          "room_name": "Deluxe Room",
          "board_basis": "BB",
          "non_refundable": false,
          "price": 125.50,
          "currency": "KWD",
          "room_token": "room_token_123",
          "package_token": "package_token_456",
          "occupancy": [
            {
              "adults": 2,
              "childrenAges": []
            }
          ]
        },
        "prebook": {
          "availability_token": "avail_token_789",
          "check_in": "2025-12-01",
          "check_out": "2025-12-05",
          "duration": 4,
          "autocancel_date": "2025-11-30T23:59:59",
          "cancel_policy": [
            {
              "from": "2025-11-25",
              "amount": 50.00
            }
          ],
          "remarks": ["Non-smoking room", "Free WiFi"]
        }
      }
    }
  }
}
```

### Error Response Examples:

**Hotel Not Found:**
```json
{
  "data": {
    "searchHotelRooms": {
      "success": false,
      "message": "Hotel not found with the provided name.",
      "data": null
    }
  }
}
```

**No Rooms Available:**
```json
{
  "data": {
    "searchHotelRooms": {
      "success": false,
      "message": "No available rooms found for the specified dates.",
      "data": null
    }
  }
}
```

**Validation Error:**
```json
{
  "data": {
    "searchHotelRooms": {
      "success": false,
      "message": "Validation failed: Check-out date must be after check-in date.",
      "data": null
    }
  }
}
```

## Testing with GraphQL Playground

Lighthouse includes GraphiQL playground. Access it at:
```
http://your-domain.com/graphql-playground
```

Or enable GraphiQL in `config/lighthouse.php`:
```php
'graphiql' => [
    'enabled' => env('LIGHTHOUSE_GRAPHIQL_ENABLED', true),
],
```

## Database Tables Used

1. **agents** - To find agent by phone number
2. **branches** - To get company_id from agent
3. **map_hotels** (mysql_map connection) - To find hotel details
4. **temporary_offers** - Stores search results
5. **offered_rooms** - Stores individual room offers
6. **request_booking_rooms** - Stores user booking request

## Future Improvements (Mentioned by User)

### 1. Multiple Results
Currently returns only the cheapest room. Future enhancement:
```graphql
input HotelSearchInput {
  telephone: String!
  hotel: String!
  checkIn: Date!
  checkOut: Date!
  limit: Int  # Return top N cheapest rooms (default: 1)
}
```

### 2. Flexible Hotel Search
Magic Holiday API supports fuzzy hotel search. Future enhancement:
- Use `/reseller/api/hotels/v1/search` with partial hotel names
- Return list of matching hotels for user selection
- Then proceed with room search

Example flow:
```graphql
# Step 1: Find hotels by partial name
query FindHotels {
  findHotels(cityName: "Kuwait", hotelName: "Hilton") {
    hotels {
      id
      name
      address
    }
  }
}

# Step 2: Search rooms in specific hotel
query SearchRooms {
  searchHotelRooms(input: {
    telephone: "+96512345678"
    hotelId: 12345  # Use ID from step 1
    checkIn: "2025-12-01"
    checkOut: "2025-12-05"
  }) {
    # ... same response
  }
}
```

## Logging

All operations are logged to:
- `storage/logs/magic_holidays/magic_holidays.log` - API calls
- `storage/logs/magic_holidays/magic_holidays_error.log` - Errors
- Laravel default log - Application errors

## Important Notes

1. **Occupancy:** Currently hardcoded to 2 adults, no children. Easy to add as input parameter.

2. **Search Timeout:** Max 60 attempts × 2 seconds = 2 minutes timeout.

3. **Previous Offers:** Automatically deleted when new search is performed for same phone number.

4. **Company Detection:** If agent not found by phone, uses default Magic Holiday credentials from config/services.php.

5. **Hotel Name:** Must be exact match. Case-sensitive.

## Troubleshooting

### Error: "Hotel not found"
- Check hotel name spelling (exact match required)
- Verify hotel exists in map_hotels table
- Check mysql_map database connection

### Error: "Failed to start hotel search"
- Verify Magic Holiday API credentials
- Check network connectivity
- Review logs in storage/logs/magic_holidays/

### Error: "Hotel search timeout"
- Magic Holiday API may be slow
- Increase max attempts in HotelSearchService::pollSearchProgress()
- Check Magic Holiday API status

### GraphQL Syntax Errors
- Use GraphQL Playground for query validation
- Check schema at http://your-domain.com/graphql-playground
- Verify all required fields are provided

## Testing Checklist

- [ ] Test with valid phone number (agent exists)
- [ ] Test with unknown phone number (uses default config)
- [ ] Test with exact hotel name
- [ ] Test with non-existent hotel
- [ ] Test with past dates (should fail validation)
- [ ] Test with check-out before check-in (should fail validation)
- [ ] Verify TemporaryOffer and OfferedRoom records created
- [ ] Verify RequestBookingRoom updated
- [ ] Check that cheapest room is returned
- [ ] Verify prebook data is complete

## Contact & Support

For issues or questions about:
- **GraphQL Schema:** Check `graphql/schema.graphql`
- **Search Logic:** Review `app/Services/HotelSearchService.php`
- **Magic API:** Review `app/Services/MagicHolidayService.php`
- **Resolver:** Check `app/GraphQL/Queries/SearchHotelRooms.php`
