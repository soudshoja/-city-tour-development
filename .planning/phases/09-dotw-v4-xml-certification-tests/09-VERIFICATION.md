---
phase: 09-dotw-v4-xml-certification-tests
verified: 2026-02-25T06:00:00Z
status: passed
score: 20/20 must-haves verified
re_verification:
  previous_status: gaps_found
  previous_score: 19/20
  gaps_closed:
    - "getBookingDetails resolver now returns schema-correct field names (hotel_code, from_date, to_date, customer_reference, total_amount, passengers); parseBookingDetail() extended to return all required fields with backward-compat aliases"
  gaps_remaining: []
  regressions: []
human_verification:
  - test: "Run php artisan dotw:certify --test=14,15,16,17,18,19,20 against xmldev.dotwconnect.com"
    expected: "All tests output PASS or WARN — never ERROR or PHP fatal. Tests requiring specific rate types (changedOccupancy, APR, promotions) may WARN if dev environment lacks those rates on test dates."
    why_human: "Cannot verify against live DOTW API without network access."
  - test: "Call blockRates for a valid hotel then verify validateBlockingStatus correctly passes rates with <status>checked</status> and aborts on any other value"
    expected: "Successfully blocked rates proceed to createPreBooking; rates with non-checked status return a clear VALIDATION_ERROR"
    why_human: "Requires live DOTW API call to xmldev.dotwconnect.com"
---

# Phase 9: DOTW V4 Complete API Models — Verification Report

**Phase Goal:** All missing DOTW V4 operations are implemented in DotwService and exposed via GraphQL, with correctness rules (blocking status, APR routing, changed occupancy, gzip) baked in — giving the system a complete, spec-compliant DOTW API surface.
**Verified:** 2026-02-25T06:00:00Z
**Status:** passed
**Re-verification:** Yes — after gap closure (09-05 plan closed BOOK-04)

---

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | saveBooking mutation accepts booking params and returns an itinerary code from DOTW savebooking command | VERIFIED | `DotwSaveBooking.php` loads prebook, calls `DotwService::saveBooking()`, returns `itinerary_code`. Service method exists at line 409. |
| 2 | bookItinerary mutation accepts an itinerary code and returns a confirmed booking code from DOTW bookitinerary command | VERIFIED | `DotwBookItinerary.php` calls `DotwService::bookItinerary()`, returns `booking_code + booking_status`. Service method exists at line 465. |
| 3 | getBookingDetails query accepts a booking code and returns full DOTW booking details | VERIFIED | Resolver (gap-05 closure) returns all non-null schema fields with correct names: `booking_code`, `hotel_code`, `from_date`, `to_date`, `status`, `customer_reference`, `total_amount`, `currency`, `passengers`. `parseBookingDetail()` extended to return `hotelCode`, `fromDate`, `toDate`, `customerReference`, `totalAmount`, `passengerDetails` from XML plus backward-compat aliases. |
| 4 | searchBookings query accepts date range and/or customer reference and returns matching bookings | VERIFIED | `DotwSearchBookings.php` calls `DotwService::searchBookings()`, maps response correctly, returns `bookings` array and `total_count`. |
| 5 | createPreBooking detects nonrefundable=yes on the prebook and automatically routes to savebooking+bookitinerary instead of confirmbooking | VERIFIED | `DotwCreatePreBooking.php` line 131: `$isApr = !$prebook->is_refundable;` branches to `saveBooking() + bookItinerary()` for APR flow. |
| 6 | APR bookings (is_refundable=false) are blocked from the cancelBooking mutation with a clear error | VERIFIED | `DotwCancelBooking.php` lines 48-60: loads `DotwBooking` by `confirmation_code`, checks `hotel_details['is_apr']`, returns VALIDATION_ERROR before any DOTW call. |
| 7 | checkCancellation query calls cancelbooking confirm=no and returns the charge amount before any cancellation is committed | VERIFIED | `DotwCheckCancellation.php` calls `DotwService::cancelBooking(['confirm' => 'no'])`, returns `charge` and `is_outside_deadline`. |
| 8 | cancelBooking mutation calls cancelbooking confirm=yes with penaltyApplied and returns the cancellation result | VERIFIED | `DotwCancelBooking.php` calls `DotwService::cancelBooking(['confirm' => 'yes', 'penaltyApplied' => ...])`. |
| 9 | deleteItinerary mutation calls DOTW deleteitinerary command and removes an unconfirmed (saved) itinerary | VERIFIED | `DotwDeleteItinerary.php` calls `DotwService::deleteItinerary()`. Service method at line 572 wraps `deleteitinerary` XML command. |
| 10 | getAllCountries query returns all DOTW internal country codes | VERIFIED | `DotwGetAllCountries.php` calls `getCountryList()`. Schema wired at line 1256. |
| 11 | getServingCountries query returns country codes that DOTW serves with hotels | VERIFIED | `DotwGetServingCountries.php` calls `getServingCountries()`. Service method at line 776. |
| 12 | getHotelClassifications query returns hotel star rating classification codes | VERIFIED | `DotwGetHotelClassifications.php` calls `getHotelClassifications()` with `id->code` remap. Schema at line 1264. |
| 13 | getLocationIds query returns location filtering codes | VERIFIED | `DotwGetLocationIds.php` calls `getLocationIds()`. Service method at line 814. |
| 14 | getAmenityIds query returns amenity, leisure, and business facility codes merged | VERIFIED | `DotwGetAmenityIds.php` calls `getAmenityIds()`. Service at line 855 merges 3 DOTW commands with category labels and fault tolerance. |
| 15 | getPreferenceIds query returns hotel preference codes | VERIFIED | `DotwGetPreferenceIds.php` calls `getPreferenceIds()`. Service method at line 920. |
| 16 | getChainIds query returns hotel chain codes | VERIFIED | `DotwGetChainIds.php` calls `getChainIds()`. Service method at line 958. |
| 17 | validateBlockingStatus() correctly reads status as XML child element not attribute | VERIFIED | `DotwService.php` line 1629: `$status = (string) ($rateBasis->status ?? 'unchecked');` — element access confirmed. |
| 18 | buildConfirmRoomsXml() supports changedOccupancy with extraBed | VERIFIED | `DotwService.php` lines 1378-1381: `extraBed` handled with `<extraBed>N</extraBed>` when `$room['extraBed'] > 0`. PHPDoc documents changedOccupancy contract. |
| 19 | All DOTW HTTP requests include Accept-Encoding: gzip header | VERIFIED | `DotwService.php` line 1554: `'Accept-Encoding' => 'gzip, deflate'`. |
| 20 | DotwCertify tests 14-20 are implemented and runnable | VERIFIED | `grep -c "private function runTest"` returns 20. Methods confirmed at lines 2075 (test14) through 3042 (test20). |

**Score: 20/20 truths verified**

---

## Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/GraphQL/Mutations/DotwSaveBooking.php` | saveBooking resolver | VERIFIED | Substantive implementation, wired via schema resolver path |
| `app/GraphQL/Mutations/DotwBookItinerary.php` | bookItinerary resolver | VERIFIED | Substantive implementation, wired via schema resolver path |
| `app/GraphQL/Queries/DotwGetBookingDetails.php` | getBookingDetails resolver | VERIFIED | Gap closed: returns all 9 schema-correct field names; PHPDoc confirms gap-05 closure |
| `app/GraphQL/Queries/DotwSearchBookings.php` | searchBookings resolver | VERIFIED | Substantive, maps service keys correctly, wired in schema |
| `app/GraphQL/Queries/DotwCheckCancellation.php` | checkCancellation resolver | VERIFIED | Substantive, confirm=no flow, wired in schema |
| `app/GraphQL/Mutations/DotwCancelBooking.php` | cancelBooking resolver | VERIFIED | VALID-02 guard present, confirm=yes flow, wired in schema |
| `app/GraphQL/Mutations/DotwDeleteItinerary.php` | deleteItinerary resolver | VERIFIED | Calls DotwService::deleteItinerary(), wired in schema |
| `app/GraphQL/Queries/DotwGetAllCountries.php` | getAllCountries resolver | VERIFIED | Calls getCountryList(), wired in schema |
| `app/GraphQL/Queries/DotwGetServingCountries.php` | getServingCountries resolver | VERIFIED | Calls getServingCountries(), wired in schema |
| `app/GraphQL/Queries/DotwGetHotelClassifications.php` | getHotelClassifications resolver | VERIFIED | Calls getHotelClassifications() with id->code remap, wired |
| `app/GraphQL/Queries/DotwGetLocationIds.php` | getLocationIds resolver | VERIFIED | Calls getLocationIds(), wired in schema |
| `app/GraphQL/Queries/DotwGetAmenityIds.php` | getAmenityIds resolver | VERIFIED | Calls getAmenityIds() (3-command merge), wired in schema |
| `app/GraphQL/Queries/DotwGetPreferenceIds.php` | getPreferenceIds resolver | VERIFIED | Calls getPreferenceIds(), wired in schema |
| `app/GraphQL/Queries/DotwGetChainIds.php` | getChainIds resolver | VERIFIED | Calls getChainIds(), wired in schema |
| `app/Services/DotwService.php` | validateBlockingStatus fix + extraBed + searchBookings + parseBookingDetail extended | VERIFIED | VALID-01 element access at line 1629; VALID-03 extraBed at lines 1378-1381; searchBookings at line 1001; parseBookingDetail returns hotelCode/fromDate/toDate/customerReference/totalAmount/passengerDetails at lines 1829-1846 |
| `app/Console/Commands/DotwCertify.php` | Tests 14-20 implemented | VERIFIED | 20 runTest methods confirmed |
| `graphql/dotw.graphql` | All 14 new operations + correct BookingDetails type | VERIFIED | BookingDetails type at lines 753-780 with all non-null fields; operations at lines 891-907, 1041-1065, 1256-1280 |

---

## Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `DotwSaveBooking.php` | `DotwService.php` | `saveBooking()` | WIRED | Line 118 calls `$dotwService->saveBooking(...)` |
| `DotwBookItinerary.php` | `DotwService.php` | `bookItinerary()` | WIRED | Line 63 calls `$dotwService->bookItinerary(...)` |
| `DotwGetBookingDetails.php` | `DotwService.php` | `getBookingDetail()` | WIRED | Line 34 calls correct method; returned keys now match schema non-null fields exactly |
| `DotwSearchBookings.php` | `DotwService.php` | `searchBookings()` | WIRED | Line 46 calls `$dotwService->searchBookings($params)` |
| `DotwCreatePreBooking.php` | `DotwService.php` | APR branch to saveBooking+bookItinerary | WIRED | Line 131: `$isApr = !$prebook->is_refundable;`, branches at line 135 |
| `DotwCheckCancellation.php` | `DotwService.php` | `cancelBooking(['confirm'=>'no'])` | WIRED | Line 46 calls confirm=no cancel |
| `DotwCancelBooking.php` | `DotwService.php` | `cancelBooking(['confirm'=>'yes'])` | WIRED | Line 68 calls confirm=yes cancel with penaltyApplied |
| `DotwDeleteItinerary.php` | `DotwService.php` | `deleteItinerary()` | WIRED | Line 42 calls `$dotwService->deleteItinerary($itineraryCode)` |
| `DotwGetAllCountries.php` | `DotwService.php` | `getCountryList()` | WIRED | Line 34 |
| `DotwGetAmenityIds.php` | `DotwService.php` | `getAmenityIds()` (3-command merge) | WIRED | Calls getAmenityIds() which merges amenity/leisure/business commands |
| `DotwBlockRates.php` | `DotwService.php` | `validateBlockingStatus()` element fix | WIRED | DotwService line 1629 uses element access, not attribute |
| `parseBookingDetail()` | `BookingDetails` schema type | field name contract | WIRED | Service returns hotelCode/fromDate/toDate/customerReference/totalAmount/passengerDetails; resolver maps them to hotel_code/from_date/to_date/customer_reference/total_amount/passengers |

---

## Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|-------------|--------|---------|
| BOOK-01 | 09-01 | APR detection, route to savebooking+bookitinerary | SATISFIED | DotwCreatePreBooking line 131 APR branch |
| BOOK-02 | 09-01 | saveBooking mutation wraps savebooking | SATISFIED | DotwSaveBooking.php + DotwService::saveBooking() |
| BOOK-03 | 09-01 | bookItinerary mutation wraps bookitinerary | SATISFIED | DotwBookItinerary.php + DotwService::bookItinerary() |
| BOOK-04 | 09-01 / 09-05 | getBookingDetails returns full booking details | SATISFIED | Gap closed: resolver returns hotel_code, from_date, to_date, customer_reference, total_amount, passengers — all matching schema non-null contract |
| BOOK-05 | 09-01 | searchBookings query returns bookings by date/ref | SATISFIED | DotwSearchBookings.php correctly maps fields |
| CANCEL-01 | 09-02 | checkCancellation query (confirm=no) | SATISFIED | DotwCheckCancellation.php |
| CANCEL-02 | 09-02 | cancelBooking mutation (confirm=yes + penaltyApplied) | SATISFIED | DotwCancelBooking.php |
| CANCEL-03 | 09-02 | deleteItinerary mutation | SATISFIED | DotwDeleteItinerary.php |
| LOOKUP-01 | 09-03 | getAllCountries query | SATISFIED | DotwGetAllCountries.php |
| LOOKUP-02 | 09-03 | getServingCountries query | SATISFIED | DotwGetServingCountries.php |
| LOOKUP-03 | 09-03 | getHotelClassifications query | SATISFIED | DotwGetHotelClassifications.php |
| LOOKUP-04 | 09-03 | getLocationIds query | SATISFIED | DotwGetLocationIds.php |
| LOOKUP-05 | 09-03 | getAmenityIds query (3-command merge) | SATISFIED | DotwGetAmenityIds.php merges amenity/leisure/business |
| LOOKUP-06 | 09-03 | getPreferenceIds query | SATISFIED | DotwGetPreferenceIds.php |
| LOOKUP-07 | 09-03 | getChainIds query | SATISFIED | DotwGetChainIds.php |
| VALID-01 | 09-04 | validateBlockingStatus reads status as element | SATISFIED | DotwService.php line 1629: `$rateBasis->status` element access |
| VALID-02 | 09-01/09-02 | APR bookings blocked from cancel | SATISFIED | DotwCancelBooking.php lines 48-60 + is_apr stored in hotel_details |
| VALID-03 | 09-04 | changedOccupancy: extraBed support | SATISFIED | DotwService.php lines 1378-1381 + PHPDoc |
| VALID-04 | 09-04 | gzip on all DOTW requests | SATISFIED | DotwService.php line 1554 Accept-Encoding header |

**All 19 requirements SATISFIED. 0 BLOCKED.**

---

## Anti-Patterns Found

None. The BOOK-04 blocker from the previous verification has been resolved. No new anti-patterns were introduced.

---

## Human Verification Required

### 1. DotwCertify Tests 14-20 Live Run

**Test:** Run `php artisan dotw:certify --test=14,15,16,17,18,19,20` against the xmldev.dotwconnect.com environment.
**Expected:** All tests output PASS or WARN (never ERROR or PHP fatal). Tests that require specific rate types (changedOccupancy, APR, promotions) may WARN if the dev environment lacks those rates on the test dates.
**Why human:** Cannot verify against live DOTW API without network access.

### 2. VALID-01 Blocking Status Real Request

**Test:** Call `blockRates` for a valid hotel, then verify the blocking status check works correctly — that a rate with `<status>checked</status>` passes, and one without would abort.
**Expected:** Successfully blocked rates proceed to createPreBooking; rates with non-"checked" status return a clear VALIDATION_ERROR.
**Why human:** Requires live DOTW API call.

---

## Re-Verification Summary

**Gap closed: BOOK-04 / getBookingDetails field name mismatch.**

In the initial verification, `DotwGetBookingDetails.php` returned `hotel_name`, `check_in`, `check_out`, `total_price` — none of which exist in the `BookingDetails` GraphQL type. Four required non-null fields were entirely absent, producing a null coercion error at GraphQL runtime.

Plan 09-05 fixed both layers:

1. `parseBookingDetail()` in `DotwService.php` (lines 1829-1846) now returns the full set of schema-required keys: `bookingCode`, `hotelCode`, `fromDate`, `toDate`, `status`, `customerReference`, `totalAmount`, `currency`, `passengerDetails`. Backward-compat aliases (`hotelName`, `checkIn`, `checkOut`, `totalPrice`) are preserved for any existing callers.

2. `DotwGetBookingDetails.php` resolver (lines 56-65) now maps service response keys to schema field names exactly: `hotel_code`, `from_date`, `to_date`, `customer_reference`, `total_amount`, `passengers` (JSON-encoded array). All fields use null-safe defaults (`?? ''` / `?? 0.0` / `?? []`) so GraphQL null coercion cannot occur even when the DOTW response is sparse.

All 20 must-haves now pass. No regressions detected on previously-passing items. The phase goal is fully achieved: DOTW V4 has a complete, spec-compliant API surface implemented in DotwService and exposed via GraphQL with all correctness rules baked in.

---

_Verified: 2026-02-25T06:00:00Z_
_Verifier: Claude (gsd-verifier)_
_Re-verification: Yes (gap closure after 09-05 plan)_
