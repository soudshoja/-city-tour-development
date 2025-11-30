# TBO Hotel Search GraphQL Query

## Overview
This GraphQL query allows you to search for hotel rooms using the TBO (Travel Boutique Online) API and automatically prebook the cheapest available room.

## Query Name
`searchTBOHotelRooms`

## Input Parameters

### TBOHotelSearchInput
- **hotelCode** (Int!, required): The TBO hotel code
- **guestNationality** (String!, required): Guest nationality code (ISO 3166-1 alpha-2, e.g., "US", "GB", "KW")
- **checkIn** (Date!, required): Check-in date in YYYY-MM-DD format
- **checkOut** (Date!, required): Check-out date in YYYY-MM-DD format (must be after checkIn)
- **rooms** (Array of TBORoomInput!, required): Array of room configurations

### TBORoomInput
- **adults** (Int!, required): Number of adults (minimum 1)
- **children** (Int!, required): Number of children (minimum 0)
- **childAges** (Array of Int, optional): Ages of children if any

## Response Structure

### TBOHotelSearchResponse
- **success** (Boolean!): Whether the search was successful
- **message** (String): Description message
- **data** (TBOHotelSearchData): Search results (null if unsuccessful)

### TBOHotelSearchData
- **hotel_code** (Int!): TBO hotel code
- **hotel_name** (String!): Hotel name
- **room_count** (Int!): Number of room results
- **rooms** (Array of TBORoomResult!): Room details with prebook information

### TBORoomResult
- **success** (Boolean!): Whether the room prebook was successful
- **error** (String): Error message if prebook failed
- **room** (Array of RoomDetails!): Room details
- **prebook** (TBOPrebookDetails): Prebook information

### TBOPrebookDetails
- **prebookKey** (String): Unique prebook key
- **tboId** (Int): Database ID for the prebook
- **bookingCode** (String): TBO booking code
- **serviceDates** (Mixed): Check-in/check-out dates
- **package** (Mixed): Package details with pricing
- **totalFare** (Float): Total fare
- **totalTax** (Float): Total tax
- **currency** (String): Currency code
- **mealType** (String): Meal type (e.g., RO, BB, HB, FB)
- **isRefundable** (Boolean): Whether the room is refundable

## Example Query

```graphql
query SearchTBOHotel($input: TBOHotelSearchInput!) {
  searchTBOHotelRooms(input: $input) {
    success
    message
    data {
      hotel_code
      hotel_name
      room_count
      rooms {
        success
        error
        room {
          room_name
          board_basis
          non_refundable
          price
          currency
          info
          occupancy
        }
        prebook {
          prebookKey
          tboId
          bookingCode
          totalFare
          totalTax
          currency
          mealType
          isRefundable
        }
      }
    }
  }
}
```

**Query Variables:**
```json
{
  "input": {
    "hotelCode": 1555902,
    "guestNationality": "AL",
    "checkIn": "2026-01-18",
    "checkOut": "2026-01-25",
    "rooms": [
      {
        "adults": 1,
        "children": 0,
        "childAges": []
      },
      {
        "adults": 2,
        "children": 0,
        "childAges": []
      }
    ]
  }
}
```

## Example Response

```json
{
  "data": {
    "searchTBOHotelRooms": {
      "success": true,
      "message": "TBO hotel search completed successfully.",
      "data": {
        "hotel_code": 1555902,
        "hotel_name": "Grand Hotel",
        "room_count": 1,
        "rooms": [
          {
            "success": true,
            "error": null,
            "room": [
              {
                "room_name": "Classic Room, 1 King Bed, Sea View,NonSmoking",
                "board_basis": "Room_Only",
                "non_refundable": false,
                "price": 2735.125,
                "currency": "USD",
                "info": "Free self parking",
                "occupancy": {
                  "adults": 1,
                  "children": 0,
                  "childAges": []
                }
              },
              {
                "room_name": "Classic Room, 1 King Bed, Sea View,NonSmoking",
                "board_basis": "Room_Only",
                "non_refundable": false,
                "price": 2735.125,
                "currency": "USD",
                "info": "Free self parking",
                "occupancy": {
                  "adults": 2,
                  "children": 0,
                  "childAges": []
                }
              }
            ],
            "prebook": {
              "prebookKey": "TBO-123",
              "tboId": 123,
              "bookingCode": "1555902!TB!1!TB!7a997d48-71c2-44cc-a0a0-d862d6776c84",
              "totalFare": 5470.25,
              "totalTax": 0.0,
              "currency": "USD",
              "mealType": "Room_Only",
              "isRefundable": true
            }
          }
        ]
      }
    }
  }
}
```

## Error Handling

### Validation Errors
If validation fails, you'll receive:
```json
{
  "data": {
    "searchTBOHotelRooms": {
      "success": false,
      "message": "Validation failed: Check-in date must be today or later.",
      "data": null
    }
  }
}
```

### API Errors
If the TBO API returns an error:
```json
{
  "data": {
    "searchTBOHotelRooms": {
      "success": false,
      "message": "Hotel search failed: Invalid hotel code",
      "data": null
    }
  }
}
```

### No Results
If no rooms are available:
```json
{
  "data": {
    "searchTBOHotelRooms": {
      "success": false,
      "message": "No available rooms found.",
      "data": null
    }
  }
}
```

## Features

1. **Automatic Prebook**: The query automatically prebooks the cheapest available room
2. **Multi-room Support**: Can search for multiple rooms in a single request
3. **Child Age Support**: Handles children with specific ages
4. **Detailed Response**: Returns comprehensive room and pricing information
5. **Error Handling**: Provides clear error messages for validation and API failures
6. **Hotel Name Retrieval**: Automatically fetches hotel name from TBO API using hotel code
7. **Date Formatting**: Converts dates from GraphQL to TBO API format (Y-m-d)

## Implementation Details

### API Request Flow
1. **Search API Call**: Searches available rooms with given criteria
   - Endpoint: `http://api.tbotechnology.in/TBOHolidays_HotelAPI/Search`
   - Parameters: Uses lowercase keys (`adults`, `children`, `childrenAges`) in `PaxRooms`
   - Format: `IsDetailedResponse: false`, `NoOfRooms: 0` (TBO API requirement)
   
2. **Hotel Details API Call**: Fetches hotel name and details
   - Endpoint: `http://api.tbotechnology.in/TBOHolidays_HotelAPI/HotelDetails`
   - Required because search response only contains `HotelCode` and `Currency`
   
3. **Prebook API Call**: Prebooks the cheapest room found
   - Endpoint: `http://api.tbotechnology.in/TBOHolidays_HotelAPI/PreBook`
   - Uses `BookingCode` from search results

### Database Storage
- **tbos table**: Stores main prebook information
  - booking_code, hotel_code, hotel_name, room_quantity
  - room_name (JSON array), currency, inclusion
  - day_rates (JSON), total_fare, total_tax
  - meal_type, is_refundable, with_transfer
  
- **tbo_rooms table**: Stores individual room details
  - tbo_id (foreign key), room_name
  - adult_quantity, child_quantity

### TBO API Specifics
- **PaxRooms format**: Must use lowercase keys (`adults`, `children`, `childrenAges`)
- **Date format**: Must be `Y-m-d` format (e.g., "2026-01-18")
- **IsDetailedResponse**: Must be `false` for search
- **NoOfRooms**: Set to `0` (TBO API requirement, not count)
- **Multiple Rooms**: Can search multiple room configurations at once

## Differences from Magic Holiday Search

- Uses TBO hotel codes instead of hotel names
- Requires guest nationality (ISO country code)
- Stores prebook data in TBO-specific tables (`tbos` and `tbo_rooms`)
- Returns TBO-specific prebook details
- Simpler flow - searches and prebooks in one operation

## Next Steps

To complete a booking after prebooking, you'll need to:
1. Use the returned `tboId` from the prebook
2. Call the TBO booking API with customer and payment details
3. Refer to the TBO booking implementation in `TBOController@book` method
