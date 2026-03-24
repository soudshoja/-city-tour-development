# DOTW Certification Test Mapping

## Overview

Our internal certification suite (`DotwCertify.php`) runs **20 tests**. DOTW's official XML Certification Test Plan has **19 tests**. This document maps between the two numbering systems.

The difference: We split DOTW's Test 16 (Restricted Cancellation Rules) into two separate internal tests — one for APR/nonrefundable rates (our Test 16) and one for cancelRestricted/amendRestricted flags (our Test 17). DOTW considers both part of the same test.

## Mapping Table

| DOTW # | DOTW Test Name | Internal # | Internal Test Name | Notes |
|--------|----------------|------------|-------------------|-------|
| 1 | Book 2 Adults | 1 | Book 2 adults | 1:1 |
| 2 | Book 2A + 1 Child (11yo) | 2 | Book 2 adults + 1 child (age 11) | 1:1 |
| 3 | Book 2A + 2 Children (8, 9yo) | 3 | Book 2 adults + 2 children (ages 8, 9) | 1:1 |
| 4 | Book 2 Rooms (1 single + 1 double) | 4 | Book 2 rooms (1 single + 1 double) | 1:1 |
| 5 | Cancel Outside Deadline | 5 | Cancel booking outside cancellation deadline | 1:1 |
| 6 | Cancel Within Deadline (Penalty) | 6 | Cancel 2-room booking within deadline | 1:1 |
| 7 | productsLeftOnItinerary Check | 7 | Cancel booking — productsLeftOnItinerary | 1:1 |
| 8 | Tariff Notes Display | 8 | Tariff Notes | 1:1 |
| 9 | Cancellation Rules Display | 9 | Cancellation Rules | 1:1 |
| 10 | Passenger Name Restrictions | 10 | Passenger Name Restrictions | 1:1 |
| 11 | Minimum Selling Price (MSP) | 11 | Minimum Selling Price (MSP) | 1:1 |
| 12 | Gzip Compression | 12 | Gzip Compression | 1:1 |
| 13 | Blocking Step Validation | 13 | Blocking Step Validation | 1:1 |
| 14 | Changed Occupancy | 14 | Changed Occupancy | 1:1 |
| 15 | Special Promotions | 15 | Special Promotions | 1:1 |
| **16** | **Restricted Cancellation Rules** | **16 + 17** | **APR Booking + Restricted Cancellation** | **2-to-1 merge** |
| 17 | Minimum Stay Rules | 18 | Minimum Stay | Renumbered |
| 18 | Special Requests | 19 | Special Requests | Renumbered |
| 19 | Taxes & Fees | 20 | Property Taxes/Fees | Renumbered |

## Merge Details: DOTW Test 16

DOTW Test 16 ("Restricted Cancellation Rules") covers two scenarios:

1. **APR / Non-Refundable Rates** (our internal Test 16)
   - Detect `<rateType nonrefundable="yes">` in getRooms response
   - Route to saveBooking + bookItinerary flow (Flow B)
   - Disable cancel/amend UI for APR bookings

2. **cancelRestricted / amendRestricted** (our internal Test 17)
   - Detect `<cancelRestricted>true</cancelRestricted>` in cancellation rules
   - Detect `<amendRestricted>true</amendRestricted>` in cancellation rules
   - Block cancel/amend actions during the restricted period

Both are reported as a single DOTW Test 16 in the submission. PASS requires both sub-tests to pass.

## Running Individual Tests

```bash
# Run all 20 internal tests
php artisan dotw:certify

# Run specific internal test
php artisan dotw:certify --test=16

# Run DOTW Test 16 equivalent (both APR + Restricted Cancel)
php artisan dotw:certify --test=16,17
```

## Result Translation

When reporting to DOTW, translate internal results:

- Internal tests 1-15 PASS/SKIP/FAIL → report as DOTW 1-15
- Internal test 16 AND 17 must BOTH pass → report as DOTW 16 PASS
- Internal test 18 → report as DOTW 17
- Internal test 19 → report as DOTW 18
- Internal test 20 → report as DOTW 19
