# GraphQL Hotel Search Examples

## Basic Search (Without City)

```graphql
query {
  searchHotelRooms(input: {
    telephone: "+60193058463"
    hotel: "ENNAKHIL HOTEL"
    checkIn: "2025-11-15"
    checkOut: "2025-11-17"
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

## Search With City (Recommended)

```graphql
query {
  searchHotelRooms(input: {
    telephone: "+60193058463"
    hotel: "ENNAKHIL"
    city: "Marrakech"
    checkIn: "2025-11-15"
    checkOut: "2025-11-17"
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

## Benefits of Including City Parameter

1. **More Accurate Results**: When multiple hotels have similar names across different cities
2. **Faster Search**: Reduces the search space by filtering at database level
3. **Better User Experience**: Users get exactly what they're looking for
4. **Flexible Matching**: Both hotel and city support partial matching
   - Hotel: "ENNAKHIL" matches "ENNAKHIL HOTEL"
   - City: "Marrakech" matches "Marrakech City"

## Partial Matching Examples

### Hotel Name Matching
- "HILTON" → matches "HILTON HOTEL", "HILTON RESORT", "THE HILTON"
- "MARRIOTT BEACH" → matches "MARRIOTT BEACH RESORT"

### City Name Matching
- "Dubai" → matches "Dubai", "Dubai City"
- "New York" → matches "New York", "New York City"

## Error Handling

### No Hotels Found
```json
{
  "success": false,
  "message": "Hotel not found with the provided name and city."
}
```

### No Availability
```json
{
  "success": false,
  "message": "No available hotels or rooms found for the specified dates and hotel name."
}
```

### Validation Error
```json
{
  "success": false,
  "message": "Validation failed: Check-in date must be today or later."
}
```
