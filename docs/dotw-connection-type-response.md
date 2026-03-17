# DOTW Certification: Connection Type Response

**Platform:** Soud Laravel — B2B Travel Agency Management Platform
**Date:** 2026-03-17
**Prepared for:** DOTW Certification Team
**Contact:** development.citycommerce.group

---

## 1. Platform Overview

**Platform Name:** Soud Laravel

**Platform Type:** B2B Web Portal for Travel Agencies (NOT a B2C consumer-facing site)

**Live URL:** https://development.citycommerce.group

**Description:**
Soud Laravel is a web-based travel agency management platform used exclusively by authenticated travel agency staff (agents and managers). The platform is not accessible to end consumers. End customers of the travel agency receive booking confirmations and vouchers via email — they do not access the platform directly.

**Technology Stack:**
- Backend: Laravel 11 (PHP 8.2+)
- Frontend: Livewire 3.5 (reactive server-side rendering), Alpine.js, Tailwind CSS
- API Layer: GraphQL via Lighthouse (consumed by Livewire components and n8n automation)
- Database: MySQL 8.0
- Caching: Redis (Laravel cache driver)

**User Base:**
- Travel agency administrators (company-level configuration)
- Travel agency branch managers
- Travel agency agents (primary booking users)
- All users are authenticated employees of registered travel agencies

---

## 2. Multi-Tenant Architecture

The platform implements a three-level hierarchy:

```
DOTW Supplier Account
        │
        ▼
    Company (Travel Agency)
    ├── Branch (Office / Department)
    │   ├── Agent (User)
    │   └── Agent (User)
    └── Branch (Office / Department)
        └── Agent (User)
```

Each company has its own isolated DOTW credentials stored encrypted in the database (`CompanyDotwCredential` model). Agents of one company cannot see or access data of another company. DOTW API calls are always authenticated using the credentials belonging to the company of the logged-in agent.

---

## 3. DOTW Integration Architecture

### Request Flow

```
Agent (Browser)
      │ HTTP
      ▼
Livewire 3 Component / n8n Workflow
      │ GraphQL Query/Mutation
      ▼
GraphQL API (Lighthouse — app/GraphQL/Resolvers/)
      │ PHP method call
      ▼
DotwService (app/Services/DotwService.php)
      │ HTTP POST with XML body
      │ gzip encoded (Accept-Encoding: gzip)
      ▼
DOTW V4 XML API (xmldev.dotwconnect.com)
```

### Supporting Services

| Service | Purpose |
|---------|---------|
| `DotwCacheService` | Rate search caching — 150-second TTL on searchHotels/getRooms results |
| `DotwAuditService` | Complete request/response XML logging to database and log files |
| `DotwCircuitBreakerService` | Transient failure recovery — 5 failures within 60s opens circuit for 30s |
| `CompanyDotwCredential` | Per-company encrypted DOTW API credentials (username, password, company code) |

### Gzip Compression

All requests to DOTW include `Accept-Encoding: gzip` in the HTTP headers. Responses are decompressed automatically before XML parsing.

---

## 4. Booking Flow

### Flow A — Standard (Refundable Rates)

```
1. searchHotels (city/destination, dates, rooms)
        │ Returns: hotel list with rates and availability
        ▼
2. getRooms (browse mode — no roomTypeSelected)
        │ Returns: room types, rate details, cancellation policies,
        │          tariffNotes, specials, propertyFees, MSP
        ▼
3. getRooms (blocking mode — with roomTypeSelected, status=checked validation)
        │ Returns: allocated rate with hotelRef and allocationDetails
        │ REQUIRED: response status must be "checked" before proceeding
        ▼
4. confirmBooking (with passenger details, allocationDetails from blocking step)
        │ Returns: confirmation number, booking reference
        ▼
   Booking confirmed — voucher generated and displayed to agent
```

### Flow B — APR / Credit Card (Advanced Purchase / Non-Refundable Rates)

```
1. searchHotels (same as Flow A)
        ▼
2. getRooms (browse mode)
        ▼
3. getRooms (blocking mode — with roomTypeSelected)
        ▼
4. saveBooking (creates provisional booking, returns preBookCode)
        │ Returns: preBookCode for subsequent bookItinerary call
        ▼
5. bookItinerary (completes the booking using preBookCode)
        │ Returns: final confirmation number
        ▼
   Booking confirmed — voucher generated
```

### Cancellation Flow

```
1. cancelBooking (confirm=no — check mode)
        │ Returns: cancellation penalty amount and deadline
        │ Displayed to agent: "Are you sure? Penalty: X"
        ▼
2. Agent confirms cancellation in the UI
        ▼
3. cancelBooking (confirm=yes, penaltyApplied=<amount from step 1>)
        │ Returns: cancellation confirmation
        ▼
   Booking cancelled — agent notified of any penalty charged
```

---

## 5. Mandatory Display Features

All mandatory display features are implemented in the platform's hotel booking UI. The following sections describe what is displayed and where.

### 5.1 tariffNotes

**Source:** `<tariffNotes>` element in getRooms response

**Displayed:**
- In the room rate detail view after calling getRooms (browse mode)
- Full text of tariff notes shown to the agent before confirming a room selection
- Also included in the booking voucher sent to the agent (and forwarded to the end customer)

**Purpose:** Communicates hotel policies, rate conditions, and supplier terms that the agent must be aware of before booking.

---

### 5.2 Cancellation Policies

**Source:** `<cancellation>` elements in getRooms response (browse and blocking)

**Displayed:**
- Refundable status (whether the rate is refundable)
- Cancellation deadline dates — "Cancel by [date] to avoid penalty"
- Penalty charge amounts per cancellation rule
- Multiple cancellation rules shown in timeline order

**Special Handling:**

| Flag | Display Behavior |
|------|-----------------|
| `<cancelRestricted>true</cancelRestricted>` | Cancel button disabled in UI; message shown: "This booking cannot be cancelled online — contact DOTW directly" |
| `<amendRestricted>true</amendRestricted>` | Modify/amend button disabled in UI |
| Non-refundable (APR) rates | Clearly labelled as "Non-Refundable" in rate selection UI; cancel/amend options hidden |

**Note:** Cancellation policies are sourced exclusively from the getRooms response. The searchHotels response is NOT used as the source for cancellation policy display, as it may not reflect the most current policies.

---

### 5.3 Special Promotions

**Source:** `<specials>` and `<specialsApplied>` elements in getRooms response

**Displayed:**
- Promotion type and description shown per rate on the room selection screen
- Applied promotions highlighted with promotion name before booking confirmation
- If `<specialsApplied>` confirms the promotion was applied, a badge/label is shown on the rate

**Example display:** "Special Promotion: Early Bird Discount — 10% off for stays booked 30+ days in advance"

---

### 5.4 Taxes & Fees (propertyFees)

**Source:** `<propertyFees>` → `<fee>` elements in getRooms `<rateBasis>` section

**Displayed:**
- Fee name/description
- Fee amount and currency

**Inclusion Logic:**

| `includedinprice` value | Display |
|------------------------|---------|
| `Yes` | "Included in rate: [Fee Name] [Amount] [Currency]" |
| `No` | "Payable at property: [Fee Name] [Amount] [Currency]" |

Fees payable at property are prominently displayed with a visual indicator to ensure the agent communicates this cost to the traveller.

---

### 5.5 Minimum Selling Price (MSP)

**Source:** `<totalMinimumSelling>` and `<totalMinimumSellingInRequestedCurrency>` in getRooms response

**Displayed:**
- The minimum selling price is shown in the rate detail view
- The platform enforces: `selling price >= MSP` — agents cannot set a price below the minimum

**Distribution Model Note:** This platform is B2B (travel agencies selling to their own clients). The travel agency agent sets the final selling price to their client within the system. MSP enforcement ensures compliance with DOTW's pricing requirements at the agency level.

**MSP Enforcement:** If markup calculation results in a price below MSP, the system automatically adjusts to the MSP floor value and logs the override.

---

## 6. Additional Technical Implementation Details

### Passenger Name Sanitization

- Minimum 2 characters, maximum 25 characters per name component
- No spaces permitted within name components (multi-word names are merged: "Mohammed Ali" → "MohammedAli")
- No special characters (hyphens, apostrophes removed)
- Validated before confirmbooking/savebooking XML is constructed

### Mandatory Passenger Fields

All search, getRooms, confirmbooking, and savebooking requests include:
- `<nationality>` — passenger nationality (ISO 2-letter country code)
- `<countryOfResidence>` — passenger country of residence (ISO 2-letter country code)

These fields are mandatory on every request per DOTW certification requirements.

### Salutation (Title) Mapping

Salutation IDs are fetched dynamically from the DOTW API at certification startup using the `getsalutationsids` method. The live API response maps codes (Mr, Mrs, Ms, Miss, Dr, etc.) to their corresponding numeric IDs. These are used in confirmbooking and savebooking passenger XML instead of hardcoded values.

In production, the `DotwService::getSalutationIds()` method caches the salutation map after first fetch.

### changedOccupancy Handling

When `<validForOccupancy>` in getRooms blocking response differs from the original search occupancy (e.g., a child age is converted to an adult for pricing), the confirmbooking/savebooking XML uses a dual-source pattern:

```xml
<changedOccupancy>
    <!-- adultsCode and children come from validForOccupancy (DOTW pricing requirement) -->
    <adultsCode>4</adultsCode>
    <children no="0"></children>

    <!-- actualAdults and actualChildren come from original search (real room occupancy) -->
    <actualAdults>3</actualAdults>
    <actualChildren no="1">
        <actualChild runno="0">12</actualChild>
    </actualChildren>
</changedOccupancy>
```

### Blocking Validation

Before calling confirmBooking or saveBooking, the platform always:
1. Calls getRooms with `<roomTypeSelected>` (blocking mode)
2. Validates that the response `<status>` is `checked`
3. Only proceeds to confirm/save if status is `checked`
4. Passes `allocationDetails` from blocking response verbatim to confirm/save

### Minimum Stay

- `<minStay>` and `<dateApplyMinStay>` are parsed from getRooms
- Minimum stay requirement displayed on rate selection UI
- Enforced at the UI level — agent cannot select fewer nights than required

---

## 7. Validation Approach: Option A

We are providing **Option A** for certification validation:

**Option A — Complete Test Logs + Screenshots**

1. **Full RQ/RS XML Logs** — Complete request and response XML for all 19 mandatory test cases, generated by the DOTW certification test command
2. **Screenshots** — Screenshots of mandatory display features from the live B2B web portal UI, showing:
   - tariffNotes display on room detail screen
   - Cancellation policy display (refundable and non-refundable rates)
   - Special promotions badge/label on applicable rates
   - Property fees (included and payable-at-property)
   - Minimum selling price enforcement

---

## 8. Certification Log Generation

The platform includes a dedicated Artisan command that runs all 19 DOTW certification test cases against the DOTW sandbox API:

```bash
php artisan dotw:certify
```

**What the command does:**
- Runs all 19 mandatory test cases sequentially
- Each test logs full request XML (RQ) and response XML (RS)
- Each test is marked PASS or SKIP with a reason
- All output is written to the log file

**Log file location:**
```
storage/logs/dotw_certification.log
```

**Test cases covered (19 total):**

| # | Test | Description |
|---|------|-------------|
| 1 | Search + Book | Standard single-room booking (2 adults) |
| 2 | Multi-room | Two rooms, same dates |
| 3 | Multi-passenger | Single room, 2 adults with full names |
| 4 | Children | Room with children (ages provided) |
| 5 | Cancel (free) | Cancel within free-cancel window |
| 6 | Cancel (penalty) | Cancel with penalty applied |
| 7 | Nationality | Non-Kuwait nationality booking |
| 8 | Long stay | 14-night stay |
| 9 | Multi-city | Cross-city search |
| 10 | Salutation | Dynamic salutation ID from getsalutationsids |
| 11 | MSP | Minimum Selling Price enforcement (hotel 809755) |
| 12 | Currency | Non-default requested currency |
| 13 | Country of Residence | countryOfResidence field mandatory |
| 14 | changedOccupancy | Child converted to adult via validForOccupancy |
| 15 | Special Promotions | Active specials (hotel 2344175, 2026-05-14 to 2026-05-15) |
| 16 | APR | Advanced Purchase Rate / non-refundable |
| 17 | Restricted Cancel | cancelRestricted flag handling |
| 18 | Minimum Stay | minStay enforcement |
| 19 | Amendments | bookItinerary with amendment |
| 20 | Property Fees | propertyFees payable at property |

**To generate fresh certification logs after all fixes are applied:**

```bash
# Clear old logs (optional)
rm -f storage/logs/dotw_certification.log

# Run full certification suite
php artisan dotw:certify

# Review results
tail -n 100 storage/logs/dotw_certification.log
```

---

## 9. Summary

| Requirement | Status |
|-------------|--------|
| Platform type: B2B web portal | Implemented |
| Authenticated agent access | Implemented (Laravel Auth + RBAC) |
| DOTW XML API integration | Implemented (DotwService) |
| Gzip compression | Implemented (Accept-Encoding: gzip on all requests) |
| tariffNotes display | Implemented |
| Cancellation policies display | Implemented |
| cancelRestricted / amendRestricted handling | Implemented |
| Special promotions display | Implemented |
| Taxes & fees (propertyFees) display | Implemented |
| Minimum Selling Price enforcement | Implemented |
| Blocking validation (status=checked) | Implemented |
| changedOccupancy dual-source | Implemented |
| Dynamic salutation IDs (getsalutationsids) | Implemented |
| Passenger nationality + country of residence | Implemented (mandatory on all requests) |
| Passenger name sanitization (2-25 chars, no spaces) | Implemented |
| Standard booking flow (searchHotels → getRooms → confirmBooking) | Implemented |
| APR booking flow (saveBooking → bookItinerary) | Implemented |
| Cancellation flow (check penalty → confirm cancel) | Implemented |
| Validation approach | Option A: Full RQ/RS logs + screenshots |

---

*Prepared by the Soud Laravel development team*
*Date: 2026-03-17*
*For DOTW certification review*
