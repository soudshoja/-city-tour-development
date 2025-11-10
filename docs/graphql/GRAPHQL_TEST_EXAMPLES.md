# GraphQL Hotel Search - Quick Test Examples

## Test 1: Basic Introspection (Check if GraphQL is working)

```graphql
query {
  __schema {
    types {
      name
    }
  }
}
```

**Expected:** List of all GraphQL types including HotelSearchResponse, RoomDetails, etc.

---

## Test 2: Schema Documentation Check

```graphql
query {
  __type(name: "HotelSearchInput") {
    name
    kind
    inputFields {
      name
      type {
        name
        kind
      }
    }
  }
}
```

**Expected:** Shows the input fields (telephone, hotel, checkIn, checkOut)

---

## Test 3: Full Hotel Search (Replace with real data)

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

---

## Test 4: Minimal Query (Only essential fields)

```graphql
query SearchHotelRoomsMinimal {
  searchHotelRooms(input: {
    telephone: "+96512345678"
    hotel: "Hilton Kuwait Resort"
    checkIn: "2025-12-01"
    checkOut: "2025-12-05"
  }) {
    success
    message
    data {
      hotel_name
      room {
        room_name
        price
        currency
      }
      prebook {
        availability_token
      }
    }
  }
}
```

---

## Test 5: Error Testing - Invalid Dates

```graphql
query TestInvalidDates {
  searchHotelRooms(input: {
    telephone: "+96512345678"
    hotel: "Hilton Kuwait Resort"
    checkIn: "2025-12-05"
    checkOut: "2025-12-01"
  }) {
    success
    message
  }
}
```

**Expected:** `success: false`, message about check-out must be after check-in

---

## Test 6: Error Testing - Past Dates

```graphql
query TestPastDates {
  searchHotelRooms(input: {
    telephone: "+96512345678"
    hotel: "Hilton Kuwait Resort"
    checkIn: "2025-01-01"
    checkOut: "2025-01-05"
  }) {
    success
    message
  }
}
```

**Expected:** `success: false`, message about check-in must be today or later

---

## Postman Collection (JSON)

Save this as a Postman collection:

```json
{
  "info": {
    "name": "GraphQL Hotel Search",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Search Hotel Rooms",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          }
        ],
        "body": {
          "mode": "graphql",
          "graphql": {
            "query": "query SearchHotelRooms {\n  searchHotelRooms(input: {\n    telephone: \"+96512345678\"\n    hotel: \"Hilton Kuwait Resort\"\n    checkIn: \"2025-12-01\"\n    checkOut: \"2025-12-05\"\n  }) {\n    success\n    message\n    data {\n      telephone\n      hotel_name\n      room {\n        room_name\n        price\n        currency\n      }\n      prebook {\n        availability_token\n      }\n    }\n  }\n}",
            "variables": ""
          }
        },
        "url": {
          "raw": "{{base_url}}/graphql",
          "host": ["{{base_url}}"],
          "path": ["graphql"]
        }
      }
    },
    {
      "name": "Schema Introspection",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          }
        ],
        "body": {
          "mode": "graphql",
          "graphql": {
            "query": "query {\n  __schema {\n    queryType {\n      name\n      fields {\n        name\n        description\n      }\n    }\n  }\n}",
            "variables": ""
          }
        },
        "url": {
          "raw": "{{base_url}}/graphql",
          "host": ["{{base_url}}"],
          "path": ["graphql"]
        }
      }
    }
  ],
  "variable": [
    {
      "key": "base_url",
      "value": "http://localhost",
      "type": "string"
    }
  ]
}
```

---

## Using with n8n Workflow

### HTTP Request Node Configuration:

**URL:** `https://your-domain.com/graphql`

**Method:** POST

**Body Content Type:** JSON

**JSON Body:**
```json
{
  "query": "query SearchHotelRooms($telephone: String!, $hotel: String!, $checkIn: Date!, $checkOut: Date!) { searchHotelRooms(input: { telephone: $telephone, hotel: $hotel, checkIn: $checkIn, checkOut: $checkOut }) { success message data { hotel_name room { room_name price currency } prebook { availability_token } } } }",
  "variables": {
    "telephone": "{{$json.telephone}}",
    "hotel": "{{$json.hotel}}",
    "checkIn": "{{$json.checkIn}}",
    "checkOut": "{{$json.checkOut}}"
  }
}
```

**Response Path:** `data.searchHotelRooms`

---

## Using with WhatsApp Integration

If integrating with your existing WhatsApp flow, you can call the GraphQL endpoint from n8n:

```javascript
// In n8n Function node
const graphqlQuery = `
  query SearchHotelRooms($telephone: String!, $hotel: String!, $checkIn: Date!, $checkOut: Date!) {
    searchHotelRooms(input: {
      telephone: $telephone
      hotel: $hotel
      checkIn: $checkIn
      checkOut: $checkOut
    }) {
      success
      message
      data {
        hotel_name
        room {
          room_name
          board_basis
          price
          currency
        }
        prebook {
          availability_token
        }
      }
    }
  }
`;

return {
  json: {
    query: graphqlQuery,
    variables: {
      telephone: $input.item.json.from,
      hotel: $input.item.json.hotelName,
      checkIn: $input.item.json.checkIn,
      checkOut: $input.item.json.checkOut
    }
  }
};
```

---

## Testing Locally

### Using cURL:

```bash
# Test 1: Basic search
curl -X POST http://localhost/graphql \
  -H "Content-Type: application/json" \
  -d '{
    "query": "query { searchHotelRooms(input: { telephone: \"+96512345678\", hotel: \"Hilton Kuwait Resort\", checkIn: \"2025-12-01\", checkOut: \"2025-12-05\" }) { success message data { room { room_name price } } } }"
  }'

# Test 2: With variables
curl -X POST http://localhost/graphql \
  -H "Content-Type: application/json" \
  -d '{
    "query": "query SearchHotel($tel: String!, $hotel: String!, $in: Date!, $out: Date!) { searchHotelRooms(input: { telephone: $tel, hotel: $hotel, checkIn: $in, checkOut: $out }) { success message } }",
    "variables": {
      "tel": "+96512345678",
      "hotel": "Hilton Kuwait Resort",
      "in": "2025-12-01",
      "out": "2025-12-05"
    }
  }'
```

### Using HTTPie:

```bash
http POST http://localhost/graphql \
  query='query { searchHotelRooms(input: { telephone: "+96512345678", hotel: "Hilton Kuwait Resort", checkIn: "2025-12-01", checkOut: "2025-12-05" }) { success message } }'
```

---

## Common Issues & Solutions

### Issue: "Hotel not found"
**Solution:** Query the hotels first:
```sql
SELECT id, name, city_id FROM map_hotels WHERE name LIKE '%Hilton%' LIMIT 5;
```
Use the exact hotel name from the database.

### Issue: "Validation failed: Check-in date must be today or later"
**Solution:** Use future dates. Format: YYYY-MM-DD

### Issue: "No available rooms found"
**Solution:** 
- Check Magic Holiday API is accessible
- Verify hotel has availability for those dates
- Check logs in `storage/logs/magic_holidays/`

### Issue: GraphQL query syntax error
**Solution:** Use GraphQL Playground at `/graphql-playground` to test and get autocomplete

---

## Next Steps

1. **Find a Real Hotel:** Query your `map_hotels` table to get exact hotel names
2. **Test with Valid Phone:** Use a real agent phone number from your `agents` table
3. **Check Dates:** Use future dates (at least tomorrow)
4. **Monitor Logs:** Watch `storage/logs/magic_holidays/magic_holidays.log` during execution
5. **Verify Response:** Check that you get prebook data with availability_token
