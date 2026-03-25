# DOTWconnect API Methods Reference

**Extracted:** 2026-03-09  
**Source:** DOTWconnect Documentation v4  
**Endpoint:** https://xmldev.dotwconnect.com

## Authentication

All requests must include:
```xml
<customer>
  <username>CUSTOMER_USERNAME</username>
  <password>MD5_HASH</password>  <!-- CRITICAL: Must be MD5 encrypted -->
  <id>COMPANY_CODE</id>
  <source>1</source>
  <product>hotel</product>
  <request command='METHOD_NAME'/>
</customer>
```

**Key Points:**
- Protocol: HTTPS POST only
- Password field: MD5 encrypted (REQUIRED)
- Source: Always "1"
- Product: Always "hotel"

---

## Core API Methods

### 1. searchHotels
**Purpose:** Search available hotels by location and dates

**Request Parameters:**
- checkInDate (required)
- checkOutDate (required)
- noOfRooms (required)
- noOfAdults (required)
- noOfChildren (required)
- searchRadius, filters, pagination options

**Response:**
- Hotel list with IDs, names, locations, basic pricing
- Hotel classification, amenities summary

**Usage:** First step in booking workflow

---

### 2. getRooms
**Purpose:** Get room types, rates, and cancellation policies

**CRITICAL v4 CHANGE:** This method is now MANDATORY (was optional in v3)

**Modes:**
- **Without Blocking:** Quick availability check (no rate lock)
- **With Blocking:** 3-minute rate lock for confirmation (MANDATORY)

**Request Parameters:**
- hotelId (from searchHotels)
- checkInDate, checkOutDate
- noOfRooms, noOfAdults, noOfChildren

**Response:**
- Room types available
- Daily rates and total cost
- Cancellation policies
- allocationDetails token (for booking confirmation)

**Workflow Requirements:**
1. Call getRooms without blocking first (preview)
2. Call getRooms with blocking second (lock rates)
3. Must complete confirmBooking within 3 minutes

---

### 3. confirmBooking
**Purpose:** Immediately finalize reservation

**Request Parameters:**
- hotelId, roomId
- rate, allocationDetails (from getRooms response)
- Passenger details (name, email, phone, etc.)

**Response:**
- Confirmation number
- Booking reference
- Final price with breakdown

**Timing:** Must be called within 3 minutes of blocking getRooms call

---

### 4. savebooking
**Purpose:** Save booking temporarily for later confirmation

**Request Parameters:**
- hotelId, roomId
- rate, allocationDetails
- Passenger information
- Reference token from getRooms

**Response:**
- Temporary booking reference
- Expiration time
- Confirmation token for bookItinerary

**Workflow:** Deferred booking flow - allows user to review before committing

---

### 5. bookItinerary
**Purpose:** Confirm previously saved booking

**Request Parameters:**
- Reference number/token (from savebooking)

**Response:**
- Final confirmation number
- Booking locked in system
- Invoice/receipt details

**Timing:** Must be called before saved booking expires

---

### 6. updatebooking
**Purpose:** Modify existing booking (dates, rooms, guests)

**Request Parameters:**
- Booking reference
- Updated details

**Response:**
- Updated confirmation
- New pricing if changed
- Updated cancellation policy

---

### 7. deleteItinerary
**Purpose:** Cancel saved or confirmed booking

**Request Parameters:**
- Booking reference

**Response:**
- Cancellation confirmation
- Refund details (if applicable)
- Cancellation reference

---

## Workflow Sequences

### Immediate Confirmation Flow
```
1. searchHotels
   └─ Get hotel list
2. getRooms (no blocking)
   └─ Preview rates
3. getRooms (with blocking)
   └─ Lock rates for 3 minutes
4. confirmBooking
   └─ Complete immediately
```
**Use Case:** Direct booking from search results

### Deferred Booking Flow
```
1. searchHotels
   └─ Get hotel list
2. getRooms (no blocking)
   └─ Preview rates
3. getRooms (with blocking)
   └─ Lock rates for 3 minutes
4. savebooking
   └─ Save for later review
5. bookItinerary
   └─ Confirm after review
```
**Use Case:** Save itinerary, let user review before confirming

---

## Data Models

### Search Criteria
```xml
<search>
  <checkInDate>YYYY-MM-DD</checkInDate>
  <checkOutDate>YYYY-MM-DD</checkOutDate>
  <noOfRooms>1</noOfRooms>
  <noOfAdults>2</noOfAdults>
  <noOfChildren>0</noOfChildren>
</search>
```

### Passenger Information
```xml
<passenger>
  <title>Mr/Ms/Mrs</title>
  <firstName>John</firstName>
  <lastName>Doe</lastName>
  <email>john@example.com</email>
  <phone>+1234567890</phone>
  <nationality>US</nationality>
</passenger>
```

### Hotel Response Item
```xml
<hotel>
  <id>HOTEL_ID</id>
  <name>Hotel Name</name>
  <city>City Code</city>
  <country>Country Code</country>
  <starRating>5</starRating>
  <location>Location Description</location>
  <amenities>List of codes</amenities>
</hotel>
```

### Room Response Item
```xml
<room>
  <roomId>ROOM_ID</roomId>
  <roomType>Double Room</roomType>
  <capacity>2</capacity>
  <price>99.00</price>
  <currency>USD</currency>
  <allocationDetails>TOKEN</allocationDetails>
  <cancellationPolicy>Free until 24h before</cancellationPolicy>
</room>
```

---

## Critical Implementation Notes

### Version 4 Mandatory Changes
1. **getRooms is now REQUIRED** - Cannot skip room detail step
2. **Blocking step is MANDATORY** - Must lock rates before confirming
3. **allocationDetails token** - Required for confirmBooking/savebooking
4. **3-minute rate lock** - Hard limit between blocking getRooms and confirmation

### Security Requirements
- MD5 password encryption (MANDATORY)
- HTTPS protocol only
- No plain-text passwords in logs/debug
- Secure token handling for allocationDetails

### Error Handling
- 3-minute rate expiration
- Invalid hotel/room combinations
- Passenger data validation
- Cancellation policy enforcement

### Performance Considerations
- searchHotels can return large lists - implement pagination
- getRooms blocking calls should complete quickly (typical 5-30 seconds)
- Implement caching for reference data (countries, cities, amenities)
- Connection pooling for multiple simultaneous requests

---

## Reference Data Methods

Available for lookups (documented separately):
- getallcountries - Country codes and names
- getservingcities - Cities available by country
- gethotelclass - Hotel rating/classification
- getamenitiess - Amenity codes (Wi-Fi, Pool, etc.)
- getchainids - Hotel chain codes
- getpreferences - Preference/leisure category codes
- getlocations - Location/region information

---

*Phase 1 Deliverable*
*Research complete: 2026-03-09*
*Ready for Phase 2 skill development*
