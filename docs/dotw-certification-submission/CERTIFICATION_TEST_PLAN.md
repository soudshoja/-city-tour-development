# XML CERTIFICATION TEST PLAN

**Customer:** Alphia Venture Sdn Bhd / Tech House
**Integration Ref:** INT0004091
**Platform:** Soud Laravel - B2B Travel Agency Management Platform
**Contact:** Soud Shoja
**Date:** 2026-03-24
**Environment:** xmldev.dotwconnect.com (sandbox)

---

Here is an overview of the actions that will be reviewed during the certification process. Below are the RQ and RS xml results for each test case.

Before conducting the tests, we have implemented both booking flows:

- **Flow A:** searchHotels -> getRooms (simple) -> getRooms (with roomTypeSelected) -> confirmBooking
- **Flow B:** searchHotels -> getRooms (simple) -> getRooms (with roomTypeSelected) -> saveBooking -> bookItinerary (Credit Card / APR flow)

---

## Occupancy Tests

### Test 1: Create a booking for 2 Adults Occupancy

**Status:** PASS

**Observations:** Full booking flow implemented (Flow A): searchHotels -> getRooms (browse) -> getRooms (blocking with status=checked validation) -> confirmBooking. Tested with 2 adults, 1 night, Dubai. Booking code extracted and verified. Correct adultsCode and passenger names passed.

---

### Test 2: Create a booking for 2 Adults + 1 Child (11 years old)

**Status:** PASS

**Observations:** Booking created for 2 adults + 1 child (age 11). Child passed with `<child runno="0">11</child>`. All 3 passengers listed in passengersDetails with correct salutation codes mapped dynamically via getsalutationsids API (Mr=1, Mrs=2, Miss=3, Master=4).

---

### Test 3: Create a booking for 2 Adults + 2 Children (8, 9 years old) (child runno starts at 0)

**Status:** PASS

**Observations:** Booking created for 2 adults + 2 children (ages 8, 9). Children use separate runno attributes: `<child runno="0">8</child>` and `<child runno="1">9</child>`. All 4 passengers included in passengersDetails with names and salutations.

---

### Test 4: Create a booking for 2 Rooms (1 Single + 1 Double)

**Status:** PASS

**Observations:** Multi-room booking with `<rooms no="2">`: Room 0 = 1 adult (single), Room 1 = 2 adults (double). Both rooms validated with status=checked before confirmBooking. Each room has separate roomTypeSelected with individual allocationDetails.

---

## Cancellation Process

### Test 5: Cancel booking outside cancellation deadline (free cancellation)

**Status:** PASS

**Observations:** Two-step cancellation implemented. Step 1: cancelBooking with `<confirm>no</confirm>` returns `<charge>0</charge>` (outside deadline, +90 days). Step 2: cancelBooking with `<confirm>yes</confirm>` with `<penaltyApplied>0</penaltyApplied>`. Correct service code passed from step 1 response. Free cancellation verified.

---

### Test 6: Cancel 2-room booking within cancellation deadline (with penalty)

**Status:** PASS

**Observations:** Two-step cancellation with penalty for 2 rooms within deadline (+2 days). Code correctly checks charge > 0 and passes penaltyApplied value from `<charge>` element (NOT from `<formatted>` tag as per DOTW requirement). Both rooms cancelled with correct penalty amounts.

---

### Test 7: productsLeftOnItinerary verification

**Status:** PASS

**Observations:** After cancellation, `<productsLeftOnItinerary>` element is checked. If value > 0, application displays message that not all services have been cancelled. If value = 0, confirms complete cancellation. deleteItinerary method also implemented for APR/itinerary-based bookings.

---

## Mandatory Elements Display

### Test 8: Tariff Notes

**Status:** PASS

**Observations:** getRooms request includes `<roomField>tariffNotes</roomField>`. tariffNotes content extracted from rateBasis and displayed in application UI. Content includes rate notes, hotel policies, and supplier-specific terms. Same details included on customer booking vouchers.

---

### Test 9: Cancellation Rules and Policies

**Status:** PASS

**Observations:** Cancellation rules sourced exclusively from getRooms response (not searchHotels). getRooms request includes `<roomField>cancellation</roomField>`. Rules parsed from `<cancellationRules><rule>` array with fromDate, toDate, and cancelCharge per rule. Tiered penalties supported. No additional buffer applied to DOTW cancellation deadlines.

---

### Test 10: Passenger Name Restrictions

**Status:** PASS

**Observations:** sanitizePassengerName() method enforces: minimum 2 chars, maximum 25 chars, no whitespace (multi-word names merged: "James Lee" -> "JamesLee"), no numbers or special characters ("O'Brien" -> "OBrien"). Duplicate passenger names rejected. All passengers including children passed in confirmBooking/saveBooking requests.

---

### Test 11: Minimum Selling Price (MSP)

**Status:** PASS

**Observations:** Application reads `<totalMinimumSelling>` and `<totalMinimumSellingInRequestedCurrency>` from getRooms rateBasis. If MSP present, selling price is validated to be >= MSP value. MSP displayed to agents. Note: Our platform operates primarily as B2B for travel agencies, not direct B2C.

---

### Test 12: Gzip Compression

**Status:** PASS

**Observations:** All HTTP requests include `Accept-Encoding: gzip, deflate` header. CURLOPT_ENCODING configured for automatic gzip decompression. Verified with getServingCountries request. Header present in every API call throughout the booking lifecycle.

---

### Test 13: Blocking Step Validation

**Status:** PASS

**Observations:** After getRooms blocking step (with roomTypeSelected), each room's rateBasis status is validated. If status != "checked", booking process is aborted with error message: "Rate no longer available. Please search again." Multi-room validation ensures ALL rooms must have status=checked before proceeding to confirmBooking.

---

## Feature-Specific Tests

### Test 14: Changed Occupancy

**Status:** PASS

**Observations:** Search with 3 adults + 1 child (age 12) to trigger changedOccupancy. When `<changedOccupancy>` element present in getRooms response, `<validForOccupancy>` values override booking occupancy:
- `<adultsCode>` = value from validForOccupancy/adults (what DOTW needs for pricing)
- `<actualAdults>` = original searched adults count (real occupancy)
- `<children>` = from validForOccupancy (may be 0 if child converted to adult)
- `<actualChildren>` = original searched children with ages
- `<extraBed>` = from validForOccupancy/extraBed

Rates without changedOccupancy are handled normally with matching adultsCode/actualAdults values.

---

### Test 15: Special Promotions

**Status:** SKIP — Awaiting hotel with active promotions

**Observations:** Implementation complete. getRooms request includes `<roomField>specials</roomField>`. Code parses `<specials><special>` elements at room type level and `<specialsApplied><special>` at rateBasis level. Promotion type and description displayed to agent before booking confirmation. Promotions are handled per rate basis (not per room type). Tested with DOTW-provided hotel 2344175 (The S Hotel Al Barsha, Dubai, 14-15 May 2026, 2A+2C ages 8,12) but no active specials/specialsApplied returned in current sandbox inventory. Will re-test when promotions are available or with alternative hotel IDs from DOTW.

---

### Test 16: Restricted Cancellation Rules (incl. APR / Non-Refundable Rates)

**Status:** SKIP — Awaiting hotels with restricted cancellation and APR rates

**Observations:** Implementation complete for both scenarios:

**APR / Non-Refundable Rates:**
- Rates with `<rateType nonrefundable="yes">` detected in getRooms response
- APR bookings routed to Flow B: saveBooking (returns itineraryCode) -> bookItinerary (returns bookingCode)
- Cancel/amend UI disabled entirely for APR bookings
- APR rates are paid upfront from credit and cannot be modified

**cancelRestricted / amendRestricted:**
- `<cancelRestricted>true</cancelRestricted>` parsed from cancellation rules
- `<amendRestricted>true</amendRestricted>` parsed from cancellation rules
- When cancelRestricted=true, cancel button is disabled/hidden in UI during restricted period
- When amendRestricted=true, amend button is disabled/hidden in UI during restricted period

Skipped in sandbox: no nonrefundable rates or cancelRestricted/amendRestricted flags found after scanning all Dubai hotels and DOTW-provided hotel 809755 (Conrad London St James). Will pass with specific hotel IDs from DOTW that have these rate types.

---

### Test 17: Minimum Stay Rules

**Status:** SKIP — Awaiting hotel with minimum stay requirement

**Observations:** Implementation complete. getRooms response parsed for `<minStay>` and `<dateApplyMinStay>` elements per rateBasis. When minStay is populated (value > 0), the minimum night stay requirement is communicated to the user. When `<dateApplyMinStay>` is present, indicates the starting date when the minimum stay condition applies. Application enforces that booking nights >= minStay value. Skipped in sandbox: no hotels with minStay constraints found after scanning all Dubai results and hotel 809755 (Conrad London St James). Can be resolved by DOTW providing specific hotel IDs with minimum stay requirements, or DOTW can filter these rates server-side.

---

### Test 18: Special Requests

**Status:** PASS

**Observations:** Special request code 1 (No Smoking) sent in confirmBooking XML as `<specialRequests count="1"><req runno="0">1</req></specialRequests>`. Multiple special requests supported with incremental runno. DOTW internal codes used as specified. getallspecialrequests method available for retrieving the full list of available codes.

---

### Test 19: Taxes & Fees

**Status:** PASS

**Observations:** getRooms response parsed for `<propertyFees>` array. Each fee includes:
- Fee name and description
- Amount and currency
- `includedinprice` attribute: "Yes" = included in displayed rate, "No" = payable at property
Fees with includedinprice="No" are displayed separately as "Payable at property" to the customer. All tax/fee information properly displayed in the application before booking confirmation.

---

## Summary

| # | Test | Status |
|---|------|--------|
| 1 | Book 2 Adults | PASS |
| 2 | Book 2A + 1C | PASS |
| 3 | Book 2A + 2C | PASS |
| 4 | Book 2 Rooms | PASS |
| 5 | Cancel Outside Deadline | PASS |
| 6 | Cancel With Penalty | PASS |
| 7 | productsLeftOnItinerary | PASS |
| 8 | Tariff Notes | PASS |
| 9 | Cancellation Rules | PASS |
| 10 | Passenger Names | PASS |
| 11 | MSP | PASS |
| 12 | Gzip | PASS |
| 13 | Blocking Validation | PASS |
| 14 | Changed Occupancy | PASS |
| 15 | Special Promotions | SKIP |
| 16 | Restricted Cancellation / APR | SKIP |
| 17 | Minimum Stay | SKIP |
| 18 | Special Requests | PASS |
| 19 | Taxes & Fees | PASS |

**Result: 16 PASS / 3 SKIP**

---

## Certification Log

Full RQ/RS XML logs for all test cases are generated by running:
```
php artisan dotw:certify
```
Log output: `storage/logs/dotw_certification.log`

## Validation Approach

We are providing **Option A** — Complete test logs with screenshots:
- Full RQ/RS XML logs for all 19 test cases
- Screenshots of mandatory display features (tariffNotes, cancellation policies, special promotions, taxes & fees, MSP)
- Connection type response document (docs/dotw-connection-type-response.md)

## Integration Architecture

```
Agent (Browser) -> Livewire UI -> GraphQL API -> DOTW Resolvers -> DotwService -> DOTW XML API
```

- Laravel 11 + Livewire 3 + GraphQL (Lighthouse)
- Per-company encrypted credentials
- DotwCacheService (150s TTL)
- DotwCircuitBreakerService for transient failure recovery
- DotwAuditService for full request/response logging
