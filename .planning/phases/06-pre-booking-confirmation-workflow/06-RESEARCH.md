# Phase 6: Pre-Booking & Confirmation Workflow - Research

**Researched:** 2026-02-21
**Domain:** DOTW V4 XML API — confirmBooking, passenger validation, dotw_bookings table, GraphQL mutation, alternative hotel suggestion
**Confidence:** HIGH

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| BOOK-01 | `createPreBooking` mutation accepts: prebook_key, passengers array (salutation, firstName, lastName, nationality, residenceCountry, email), resayil_message_id header | DotwService::confirmBooking() already exists; buildPassengersXml() accepts salutation/firstName/lastName; nationality/residenceCountry are per-room params; email is `sendCommunicationTo` |
| BOOK-02 | Validates: all required passenger fields present, passenger count matches room configuration, email format valid | DotwPrebook::booking_details JSON stores room_config; passenger count from prebook; PHP filter_var EMAIL_VALIDATE for email |
| BOOK-03 | Validates prebook_key exists and not expired (> 0 seconds remaining) | DotwPrebook::where('prebook_key')->first(); isValid() method or direct expired_at check |
| BOOK-04 | Calls DOTW confirmBooking with passenger details, allocationDetails, email | DotwService::confirmBooking() takes fromDate, toDate, currency, productId, sendCommunicationTo, customerReference, rooms[] with passengers[] |
| BOOK-05 | On success: Returns booking_confirmation_code, booking_status, itinerary_details | DotwService::parseConfirmation() returns bookingCode, confirmationNumber, status, paymentGuaranteedBy |
| BOOK-06 | Creates `dotw_bookings` record: confirmation_code, prebook_key, passengers, booking_status, hotel_details, resayil_message_id, company_id | New table + model required — no existing dotw_bookings migration found |
| BOOK-07 | Logs to `dotw_audit_logs` with confirmation_code, booking_status | DotwService::confirmBooking() calls DotwAuditService::log(OP_BOOK, ...) internally — audit already happens; planner should check if supplementary post-booking log is needed |
| BOOK-08 | On failure: Returns specific error (rate no longer available, hotel sold out, DOTW system error) with helpful message for N8N | Exception message from DotwService::confirmBooking() can be parsed for DOTW error code; searchHotels() available for 3-alternative suggestion |
| ERROR-03 | Allocation expired → "Rate offer expired, please search again" (clear, actionable) | Check expired_at > now() before calling DOTW; return ALLOCATION_EXPIRED + RESEARCH action |
| ERROR-04 | Rate no longer available → "This hotel/rate no longer available, similar options:" + suggest 3 alternatives | DOTW error codes in exception message; call searchHotels() with same destination/dates for alternatives |
</phase_requirements>

---

## Summary

Phase 6 implements `createPreBooking` — the GraphQL mutation that converts a locked prebook (from Phase 5 `blockRates`) into a confirmed hotel booking. The resolver loads the DotwPrebook record by `prebook_key`, validates expiry and passenger fields, calls `DotwService::confirmBooking()`, creates a `dotw_bookings` record, and returns the DOTW confirmation code.

The DOTW API infrastructure is fully operational. `DotwService::confirmBooking()` already exists with a complete implementation including XML building, XML parsing, and internal audit logging (OP_BOOK). The planner does NOT need to add audit service calls in the resolver — DotwService::confirmBooking() already calls `DotwAuditService::log(OP_BOOK, ...)` internally (verified in source). The resolver's audit responsibility is only a supplementary log if specific Phase 6 fields (confirmation_code, booking_status) need to be captured separately in audit_logs.

The two special error paths (ERROR-03 expired prebook, ERROR-04 rate unavailable with alternatives) are the primary implementation complexity. For alternatives, the resolver calls `DotwService::searchHotels()` with the same destination/dates from the prebook and returns the first 3 results — no new DOTW methods needed.

**Primary recommendation:** Follow the DotwBlockRates pattern exactly — DotwService instantiated in __invoke, errorResponse/buildMeta helpers, try/catch with RuntimeException vs Exception separation, DB::transaction for the dotw_bookings create + prebook mark-expired pair.

---

## Standard Stack

### Core (already installed and in use)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel 11 | 11.x | Framework — migrations, Eloquent, DB transactions | Project baseline |
| Lighthouse | 6.x | GraphQL — @field resolver, schema extension | All DOTW resolvers use this |
| DotwService | 750+ lines | DOTW V4 XML API calls — confirmBooking() already implemented | Central service for all DOTW calls |
| DotwAuditService | Existing | Sanitized audit logging — OP_BOOK constant already defined | Internal to DotwService::confirmBooking(), also injectable |
| DotwPrebook | Existing model | Prebook retrieval by prebook_key, isValid() check | Direct dependency from Phase 5 |
| DB::transaction | Laravel | Wrap dotw_bookings create + prebook expiry atomically | Same pattern as BLOCK-08 in DotwBlockRates |
| PHP filter_var | Built-in | FILTER_VALIDATE_EMAIL for passenger email validation | No external library needed |

### Supporting (new for this phase)

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| DotwBooking (new model) | New | Persist confirmed booking — `dotw_bookings` table | Created in Plan 01 |
| Str::uuid() | Laravel | Generate unique customer_reference for DOTW | Same pattern as prebook_key in Phase 5 |

**Installation:** No new packages. All dependencies are existing project libraries.

---

## Architecture Patterns

### Recommended Project Structure

```
Phase 6 adds:
database/migrations/
├── YYYY_MM_DD_HHMMSS_create_dotw_bookings_table.php   # Plan 01
app/Models/
├── DotwBooking.php                                      # Plan 01
graphql/dotw.graphql                                     # Plan 01 — new types + mutation
app/GraphQL/Mutations/
├── DotwCreatePreBooking.php                             # Plan 02 — main resolver
```

### Pattern 1: DotwService::confirmBooking() Parameter Structure

**What:** The confirmBooking() method takes a `$params` array that must include `fromDate`, `toDate`, `currency`, `productId`, `sendCommunicationTo`, `customerReference`, and `rooms[]`. The rooms array must include `passengers[]` with `salutation`, `firstName`, `lastName`.

**Source:** Verified in `app/Services/DotwService.php` line 865 (`buildConfirmBookingBody`) and line 1035 (`buildConfirmRoomsXml`).

```php
// Source: app/Services/DotwService.php lines 865-887, 1035-1093
$confirmation = $dotwService->confirmBooking([
    'fromDate'            => $prebook->booking_details['checkin'],   // from stored prebook
    'toDate'              => $prebook->booking_details['checkout'],
    'currency'            => $prebook->original_currency ?: 'USD',
    'productId'           => $prebook->hotel_code,
    'sendCommunicationTo' => $leadPassengerEmail,     // from input passengers[0]
    'customerReference'   => (string) Str::uuid(),   // generated booking reference
    'rooms' => [
        [
            'roomTypeCode'       => $prebook->room_type,
            'selectedRateBasis'  => $prebook->room_rate_basis,
            'allocationDetails'  => $prebook->allocation_details,   // raw — no encoding
            'adultsCode'         => $prebook->booking_details['adults_count'] ?? 2,
            'actualAdults'       => $prebook->booking_details['adults_count'] ?? 2,
            'children'           => $prebook->booking_details['children_ages'] ?? [],
            'actualChildren'     => $prebook->booking_details['children_ages'] ?? [],
            'beddingPreference'  => 0,   // no preference default
            'passengers'         => $formattedPassengers,   // salutation, firstName, lastName
        ]
    ]
], $resayilMessageId, $resayilQuoteId, $companyId);
// Returns: ['bookingCode' => '...', 'confirmationNumber' => '...', 'status' => 'confirmed', 'paymentGuaranteedBy' => '...']
```

### Pattern 2: Prebook Data Recovery

**What:** The DotwPrebook record stores all data needed to reconstruct the confirmBooking call. The `booking_details` JSON column stores cancellation_rules, markup, trace_id, and rate_basis_name. Room configuration (adultsCode, children) must be reconstructed from stored prebook fields.

**Critical insight:** The `booking_details` JSON as stored in Phase 5 (`DotwBlockRates`) does NOT include checkin/checkout dates or adultsCode/children. The prebook stores `hotel_code`, `room_type`, `room_rate_basis`, `total_fare`, `total_tax`, `original_currency`, `is_refundable`, and `booking_details` (JSON with cancellation_rules, markup, trace_id, rate_basis_name). Checkin/checkout are NOT stored in DotwPrebook.

**Resolution:** Phase 6 mutation input must accept `checkin` and `checkout` dates (or they must be added to `booking_details` in Phase 5 data). The simplest approach: require caller to pass checkin/checkout in `createPreBooking` input — the caller already has them from the blockRates context. Alternatively, store them in booking_details when creating the prebook (Phase 5 BLOCK-04). The planner should add checkin/checkout to `CreatePreBookingInput` to avoid requiring a migration change to DotwPrebook.

### Pattern 3: Passenger Validation Logic

**What:** Validate each passenger before calling DOTW. Return field-level errors for each missing/invalid field.

```php
// Source: project convention from CRED-01 (ERROR-05 pattern) verified in REQUIREMENTS.md
private function validatePassengers(array $passengers, int $expectedCount): ?array
{
    if (count($passengers) !== $expectedCount) {
        return $this->errorResponse(
            'VALIDATION_ERROR',
            "Expected {$expectedCount} passenger(s), received " . count($passengers) . ".",
            'RESUBMIT'
        );
        // NOTE: 'RESUBMIT' is used in DotwBlockRates but is NOT in DotwErrorAction enum.
        // Use 'RETRY' for the planner — see Pitfall 3 below.
    }
    foreach ($passengers as $i => $p) {
        $required = ['salutation', 'firstName', 'lastName', 'nationality', 'residenceCountry', 'email'];
        foreach ($required as $field) {
            if (empty($p[$field])) {
                return $this->errorResponse(
                    'PASSENGER_VALIDATION_FAILED',
                    "Please provide passenger {$field} for passenger " . ($i + 1) . ".",
                    'RETRY'
                );
            }
        }
        if (!filter_var($p['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse(
                'PASSENGER_VALIDATION_FAILED',
                "Passenger " . ($i + 1) . " email format is invalid.",
                'RETRY'
            );
        }
    }
    return null; // All valid
}
```

### Pattern 4: ERROR-04 Alternative Hotels Suggestion

**What:** When DOTW returns "rate no longer available" or "hotel sold out", call `searchHotels()` with the same city/destination from stored prebook context, and return the first 3 results.

**Challenge:** DotwPrebook does NOT store destination/city_code. The confirmBooking parameters include `productId` (hotel_code) but not city. Solutions:
1. Store `destination` in `booking_details` JSON when blockRates creates the prebook (requires no migration) — **recommended**
2. Accept `destination` as optional input on `createPreBooking`

**Alternative suggestion pattern:**
```php
// After catching DOTW "rate unavailable" exception:
try {
    $alternatives = $dotwService->searchHotels([
        'fromDate'  => $checkin,
        'toDate'    => $checkout,
        'currency'  => $prebook->original_currency ?: 'USD',
        'filters'   => ['city' => $destination],
        'rooms'     => [/* minimal 1-room config */],
    ]);
    $topThree = array_slice($alternatives, 0, 3);
} catch (\Exception) {
    $topThree = [];
}
return $this->errorResponseWithAlternatives('RATE_UNAVAILABLE', '...', $topThree);
```

### Pattern 5: DotwBooking Model (new)

**What:** New Eloquent model for `dotw_bookings` table following the DotwPrebook pattern — no FK to companies (standalone module per MOD-06), UPDATED_AT = null (append-only log like DotwAuditLog).

```php
// Source: pattern from DotwAuditLog.php + DotwPrebook.php established conventions
class DotwBooking extends Model
{
    public const UPDATED_AT = null;  // Booking records are immutable after creation

    protected $table = 'dotw_bookings';
    protected $fillable = [
        'prebook_key', 'confirmation_code', 'booking_status',
        'passengers', 'hotel_details', 'resayil_message_id',
        'resayil_quote_id', 'company_id', 'customer_reference',
    ];
    protected $casts = [
        'passengers'   => 'array',
        'hotel_details' => 'array',
        'company_id'   => 'integer',
    ];
}
```

### Pattern 6: BOOK-07 — Audit Logging Strategy

**What:** DotwService::confirmBooking() already calls `DotwAuditService::log(OP_BOOK, $params, $confirmation, ...)` internally (verified in source, lines 376-383). The resolver does NOT need to add a separate audit call. However, if BOOK-07 requires that `confirmation_code` and `booking_status` appear in the audit log as specific fields, a supplementary log following the BLOCK-07 two-phase pattern is appropriate:

```php
// After DotwService::confirmBooking() succeeds and dotw_bookings is created:
try {
    $this->auditService->log(
        DotwAuditService::OP_BOOK,
        ['prebook_key' => $prebookKey, 'customer_reference' => $customerRef],
        ['confirmation_code' => $confirmationCode, 'booking_status' => $bookingStatus],
        $resayilMessageId, $resayilQuoteId, $companyId
    );
} catch (\Throwable) {
    // Fail-silent — audit failure must never break booking response
}
```

### Anti-Patterns to Avoid

- **Encoding allocationDetails before passing to confirmBooking:** The allocation_details token in DotwPrebook is stored raw. Pass it directly. Any modification (trim, urlencode, base64) corrupts the token and DOTW will reject the booking.
- **Calling confirmBooking before validating prebook expiry:** Always check `expired_at > now()` before making the API call. Return ALLOCATION_EXPIRED + RESEARCH without hitting DOTW.
- **Using RESUBMIT as a DotwErrorAction:** `RESUBMIT` is not in the DotwErrorAction enum. DotwBlockRates uses it in a PHP string (which is not validated by Lighthouse for string fields) — but if the schema declares `action: DotwErrorAction!`, GraphQL will reject unknown enum values. Use `RETRY` for resubmittable validation errors.
- **Storing plain passenger PII in audit_logs:** DotwAuditService::sanitizePayload() handles credential keys but does NOT redact email addresses. The planner should be aware that passenger emails WILL appear in audit_logs. This is acceptable per the existing design (MSG-05 only prohibits credentials and "sensitive passenger details" — email for contact is NOT a credential).
- **Not expiring the prebook after successful booking:** After a confirmed booking, mark the DotwPrebook as expired (`expired_at = now()`) so it cannot be used again.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Email validation | Custom regex parser | `filter_var($email, FILTER_VALIDATE_EMAIL)` | PHP built-in, handles edge cases |
| DOTW XML booking | Custom XML builder | `DotwService::confirmBooking()` | Already fully implemented — 250+ lines of XML building + parsing |
| Booking audit trail | Custom logger | `DotwAuditService::log(OP_BOOK, ...)` | Already called by DotwService::confirmBooking() internally |
| UUID customer reference | Random string | `Str::uuid()` | Same pattern as prebook_key in Phase 5 |
| Alternative hotels | Custom hotel lookup | `DotwService::searchHotels()` | Already operational from Phase 4 |
| Atomic prebook expiry | Manual lock/check | `DB::transaction()` | Same BLOCK-08 pattern — expire prebook + create booking atomically |

**Key insight:** DotwService is a full-featured DOTW client. Phase 6 is primarily a resolver orchestration layer — validate inputs, call existing service methods, persist result, return structured response.

---

## Common Pitfalls

### Pitfall 1: Missing Checkin/Checkout in DotwPrebook
**What goes wrong:** `createPreBooking` tries to reconstruct the confirmBooking params from the prebook record, but `checkin` and `checkout` dates are NOT stored in `dotw_prebooks` — the table has no fromDate/toDate columns (verified from migration and model).
**Why it happens:** Phase 5 created the prebook from `blockRates` input which had checkin/checkout but did not persist them.
**How to avoid:** Include `checkin` and `checkout` in the `CreatePreBookingInput` GraphQL input type (caller always has these from the blockRates flow context). OR add them to `booking_details` JSON in Phase 5 if re-planning blockRates. The first option is simpler for Phase 6 — no migration needed.
**Warning signs:** Test calling confirmBooking with empty fromDate/toDate — DOTW returns a validation error.

### Pitfall 2: DotwErrorAction enum — RESUBMIT is not valid
**What goes wrong:** DotwBlockRates (existing resolver) uses `'RESUBMIT'` as an action string. This worked because `errorResponse()` builds an array with `'action' => 'RESUBMIT'` — Lighthouse serializes it as a plain string and does NOT validate enum membership for resolver-returned strings. BUT if Phase 6 schema declares `action: DotwErrorAction!`, the GraphQL engine will reject `RESUBMIT` at the field resolver level.
**Why it happens:** DotwErrorAction enum was defined without `RESUBMIT`. The existing DotwBlockRates code is technically wrong but doesn't fail in practice because the action field is String! in the current schema (DotwError type has `action: DotwErrorAction!` — needs verification).
**How to avoid:** Use only valid enum values: `RETRY`, `RETRY_IN_30_SECONDS`, `RECONFIGURE_CREDENTIALS`, `RESEARCH`, `CANCEL`, `NONE`. For passenger validation errors, use `RETRY`.
**Warning signs:** GraphQL returns "Unexpected value" or schema validation errors on the action field.

**Verified:** `DotwError.action` is typed as `DotwErrorAction!` in `graphql/dotw.graphql` line 28. `RESUBMIT` is definitely NOT in `DotwErrorAction` enum. Existing DotwBlockRates code has a latent bug — Phase 6 must not repeat it.

### Pitfall 3: Destination Not Available for Alternative Hotel Search
**What goes wrong:** ERROR-04 requires suggesting 3 alternative hotels. DotwPrebook does not store the city/destination code. Without it, calling searchHotels() to find alternatives is impossible.
**Why it happens:** Phase 5 blockRates doesn't store destination because it wasn't needed for locking.
**How to avoid:** The `CreatePreBookingInput` should accept an optional `destination` field (city code). If provided, use it for alternatives search. If not provided, return the error without alternatives. This is simpler than modifying Phase 5's booking_details schema.
**Warning signs:** Cannot call searchHotels() for alternatives without city code — no way to derive it from hotel_code alone.

### Pitfall 4: Double Audit Logging for OP_BOOK
**What goes wrong:** Adding a DotwAuditService::log() call in the resolver AND DotwService::confirmBooking() already logging internally results in duplicate entries in dotw_audit_logs for every booking.
**Why it happens:** Phase 5 established two-phase audit only for blockRates (because DotwService::getRooms() logs the raw call, then the resolver logs the prebook commitment). For confirmBooking(), the internal log already includes params + parsed confirmation.
**How to avoid:** The resolver should use a supplementary log ONLY if the goal is to add booking-specific fields (confirmation_code, prebook_key) to audit trail. If DotwService::confirmBooking() already captures these in the response payload, no supplementary log is needed. Read DotwService::confirmBooking() source (lines 376-383) — it logs the full `$confirmation` array which includes bookingCode. Supplementary log is optional/additive, not required.
**Warning signs:** Two dotw_audit_logs rows per booking with identical request payloads.

### Pitfall 5: Room Configuration Reconstruction
**What goes wrong:** The DOTW `confirmBooking` rooms array needs `adultsCode`, `actualAdults`, `children`, `actualChildren` — these are the room occupancy config from the original search. DotwPrebook stores `room_quantity` but not the per-room adults/children breakdown.
**Why it happens:** blockRates stored hotel/rate details but not full room occupancy per room.
**How to avoid:** The `CreatePreBookingInput` should accept the room configuration input (reuse `SearchHotelRoomInput[]`) OR the planner must enrich DotwPrebook.booking_details in Phase 5 to include room occupancy. Since Phase 5 is already shipped, the cleanest approach is accepting `rooms` in Phase 6 input.
**Warning signs:** confirmBooking call with adultsCode=0 or missing passengers per room.

---

## Code Examples

### DotwService::confirmBooking() Return Value
```php
// Source: app/Services/DotwService.php line 1412-1420
// parseConfirmation() returns:
[
    'bookingCode'         => 'HTL-AE2-7159...',   // DOTW booking reference
    'confirmationNumber'  => 'CONF123...',           // Optional secondary reference
    'status'              => 'confirmed',
    'paymentGuaranteedBy' => 'hotel',               // or 'dotw'
]
```

### DotwAuditService::log() Signature (verified)
```php
// Source: app/Services/DotwAuditService.php line 83-89
public function log(
    string $operationType,    // DotwAuditService::OP_BOOK
    array $request,
    array $response,
    ?string $resayilMessageId = null,
    ?string $resayilQuoteId   = null,
    ?int $companyId           = null
): DotwAuditLog
```

### DotwPrebook Retrieval and Validity Check
```php
// Source: app/Models/DotwPrebook.php lines 88-98 (isValid method)
$prebook = DotwPrebook::where('prebook_key', $prebookKey)->first();
if ($prebook === null) {
    return $this->errorResponse('VALIDATION_ERROR', 'Prebook not found.', 'RESEARCH');
}
if (!$prebook->isValid()) {
    // expired_at has passed OR created_at > 3 minutes ago
    return $this->errorResponse(
        'ALLOCATION_EXPIRED',
        'Rate offer expired, please search again.',
        'RESEARCH'
    );
}
```

### DB::transaction Pattern for Booking Creation
```php
// Source: pattern from app/GraphQL/Mutations/DotwBlockRates.php lines 267-301
$booking = null;
DB::transaction(function () use (...) {
    // Mark prebook as used/expired — prevent double-booking
    $prebook->update(['expired_at' => now()]);

    // Create booking record
    $booking = DotwBooking::create([
        'prebook_key'        => $prebookKey,
        'confirmation_code'  => $confirmationCode,
        'customer_reference' => $customerReference,
        'booking_status'     => $bookingStatus,
        'passengers'         => $passengers,         // JSON
        'hotel_details'      => $hotelDetails,       // JSON
        'resayil_message_id' => $resayilMessageId,
        'resayil_quote_id'   => $resayilQuoteId,
        'company_id'         => $companyId,
    ]);
});
```

### Proposed CreatePreBookingInput GraphQL Schema
```graphql
# To be added in graphql/dotw.graphql
input PassengerInput {
    "Salutation code: 1=Mr, 2=Mrs, 3=Ms, 4=Dr, 5=Prof."
    salutation: Int!

    "Passenger first name."
    firstName: String!

    "Passenger last name / family name."
    lastName: String!

    "ISO 3166-1 alpha-2 nationality code (e.g. KW, AE, GB)."
    nationality: String!

    "ISO 3166-1 alpha-2 country of residence code."
    residenceCountry: String!

    "Guest email address for booking confirmation communication."
    email: String!
}

input CreatePreBookingInput {
    "UUID prebook key from blockRates response."
    prebook_key: String!

    "Check-in date YYYY-MM-DD. Must match the dates used in blockRates."
    checkin: String!

    "Check-out date YYYY-MM-DD. Must match the dates used in blockRates."
    checkout: String!

    """
    Passenger details. One passenger entry per adult per room.
    Count must match total adults in the room configuration used in blockRates.
    First passenger is treated as lead guest.
    """
    passengers: [PassengerInput!]!

    """
    Room configuration. Reuse SearchHotelRoomInput from Phase 4.
    Required to reconstruct room occupancy for DOTW confirmBooking call.
    Must match the room config used in blockRates.
    """
    rooms: [SearchHotelRoomInput!]!

    """
    Optional city/destination code for alternative hotel suggestions.
    Used when suggesting 3 alternatives if rate is no longer available (ERROR-04).
    Same city code used in the original searchHotels call.
    """
    destination: String
}

type CreatePreBookingResponse {
    success: Boolean!
    error: DotwError
    meta: DotwMeta!
    cached: Boolean!
    data: CreatePreBookingData
}

type CreatePreBookingData {
    "DOTW booking confirmation code (bookingCode from confirmBooking response)."
    booking_confirmation_code: String!

    "Booking status (e.g. confirmed)."
    booking_status: String!

    "Booking itinerary details for display in WhatsApp."
    itinerary_details: BookingItinerary!

    "Alternative hotels suggested when rate was unavailable. Empty on successful booking."
    alternatives: [HotelSearchResult!]!
}

type BookingItinerary {
    "DOTW hotel identifier."
    hotel_code: String!

    "Hotel name from prebook context."
    hotel_name: String!

    "Check-in date."
    checkin: String!

    "Check-out date."
    checkout: String!

    "Room type name."
    room_type: String!

    "Rate basis name (e.g. Bed & Breakfast)."
    rate_basis: String!

    "Total fare (marked up, customer-facing price)."
    total_fare: Float!

    "Currency code."
    currency: String!

    "Whether the booking is refundable."
    is_refundable: Boolean!

    "Lead passenger name."
    lead_guest_name: String!

    "Customer reference (UUID generated for DOTW)."
    customer_reference: String!

    "Secondary DOTW confirmation number (if provided)."
    confirmation_number: String!
}
```

---

## Architecture Decision: Plan Structure

Phase 6 should be split into 2 plans:

**Plan 06-01: Data Layer + Schema**
- Migration: `create_dotw_bookings_table.php`
- Model: `DotwBooking.php`
- Schema: `graphql/dotw.graphql` — PassengerInput, CreatePreBookingInput, CreatePreBookingResponse, CreatePreBookingData, BookingItinerary, + extend type Mutation with createPreBooking

**Plan 06-02: DotwCreatePreBooking Resolver**
- `app/GraphQL/Mutations/DotwCreatePreBooking.php`
- Implements: prebook lookup + expiry check (ERROR-03), passenger validation (BOOK-02), confirmBooking call (BOOK-04), DB::transaction for booking creation (BOOK-06), prebook expiry, supplementary audit log (BOOK-07), alternative suggestions (ERROR-04)

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| DotwService with env credentials only | DotwService with companyId resolving DB credentials | Phase 1 | Must pass companyId to DotwService constructor |
| Generic error messages | Structured DotwError with DotwErrorCode + DotwErrorAction enums | Phase 3 | Error codes must match enum exactly |
| Single audit log per operation | Two-phase audit for mutations (raw API call + post-commit commitment) | Phase 5 | confirmBooking: DotwService handles Phase A; resolver does Phase B if supplementary prebook_key tracking required |
| Prebook created, never expired | Prebook expired on new blockRates call (BLOCK-08) | Phase 5 | Must also expire prebook on successful booking (Phase 6) |

**Deprecated/outdated:**
- Using `RESUBMIT` as a DotwErrorAction string: Not in enum, latent bug in DotwBlockRates. Phase 6 must use valid enum values only.

---

## Open Questions

1. **Where are checkin/checkout stored for confirmBooking reconstruction?**
   - What we know: DotwPrebook has no fromDate/toDate columns. DotwBlockRates input has checkin/checkout.
   - What's unclear: Does Phase 5 store them in booking_details JSON? (Not verified — booking_details stores cancellation_rules, markup, trace_id, rate_basis_name per DotwBlockRates source line 294-300)
   - Recommendation: Include `checkin` and `checkout` in `CreatePreBookingInput`. Planner should NOT add a migration to DotwPrebook for this — simpler to require from caller.

2. **What exactly does DotwAuditService::sanitizePayload() redact from passenger data?**
   - What we know: SENSITIVE_KEYS includes password, dotw_password, dotw_username, username, md5, secret, token, authorization, credit_card, card_number, cvv, passport_number.
   - What's unclear: Email addresses are NOT in SENSITIVE_KEYS. Passenger first/last names are NOT redacted.
   - Recommendation: This is acceptable. MSG-05 prohibits credentials and passport_number. Email + names in audit logs are an acceptable trade-off for booking dispute resolution.

3. **Should ERROR-06 (hotel sold out) be handled in Phase 6 or Phase 7?**
   - What we know: REQUIREMENTS.md assigns ERROR-06 to Phase 6 (in the BOOK-08 error type list). ROADMAP.md Phase 6 success criteria lists "hotel sold out" as criterion 6.
   - What's unclear: BOOK-08 and the success criteria say "hotel sold out" returns 3 alternatives — same behavior as ERROR-04. They share the same implementation path.
   - Recommendation: Handle ERROR-04 and ERROR-06 together in the same catch block — both trigger "rate unavailable" type exceptions from DOTW and both suggest 3 alternatives.

---

## dotw_bookings Migration Schema

```php
// Proposed schema for dotw_bookings table
Schema::create('dotw_bookings', function (Blueprint $table) {
    $table->id();
    $table->string('prebook_key', 36)->unique();             // FK reference to dotw_prebooks.prebook_key (NOT FK constraint — standalone module MOD-06)
    $table->string('confirmation_code')->nullable();          // DOTW bookingCode
    $table->string('confirmation_number')->nullable();        // DOTW confirmationNumber (secondary)
    $table->string('customer_reference', 36);                 // UUID sent to DOTW as customerReference
    $table->string('booking_status', 50)->default('pending'); // confirmed, failed
    $table->json('passengers');                               // Array of passenger details (sanitized)
    $table->json('hotel_details');                            // hotel_code, hotel_name, checkin, checkout, room_type, total_fare, etc.
    $table->string('resayil_message_id')->nullable();         // WhatsApp conversation link
    $table->string('resayil_quote_id')->nullable();           // Quoted message link
    $table->unsignedBigInteger('company_id')->nullable();     // No FK — standalone module (MOD-06)
    $table->timestamps();

    $table->index(['company_id', 'created_at']);
    $table->index('prebook_key');
    $table->index('confirmation_code');
});
```

---

## Sources

### Primary (HIGH confidence)
- `app/Services/DotwService.php` — confirmBooking(), buildConfirmBookingBody(), buildConfirmRoomsXml(), buildPassengersXml(), parseConfirmation() — all verified in source
- `app/Services/DotwAuditService.php` — log() signature, OP_BOOK constant, SENSITIVE_KEYS list — all verified
- `app/Models/DotwPrebook.php` — isValid(), activeForUser(), stored fields — all verified
- `app/GraphQL/Mutations/DotwBlockRates.php` — DB::transaction pattern, errorResponse pattern, buildMeta, audit strategy — verified as reference implementation
- `graphql/dotw.graphql` — DotwErrorAction enum values, DotwErrorCode enum, HotelSearchResult type (reusable) — all verified in source
- `DOTW_INTEGRATION.md` — confirmBooking parameter structure, example — verified

### Secondary (MEDIUM confidence)
- `database/migrations/` — No dotw_bookings migration exists (verified via `ls` command) — must create in Plan 01
- PHP `filter_var(FILTER_VALIDATE_EMAIL)` — standard PHP built-in, reliable for email validation

### Tertiary (LOW confidence — project inference)
- Alternative hotel suggestion via DotwService::searchHotels() — pattern inferred from existing implementation; actual DOTW error codes in exception messages are not fully documented in codebase

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — All dependencies are existing, verified in project source
- Architecture: HIGH — confirmBooking() is fully implemented; patterns follow established Phase 5 conventions
- Pitfalls: HIGH — Verified via direct source reading (missing checkin/checkout, RESUBMIT enum bug, double-audit)
- Alternative hotel suggestion: MEDIUM — Mechanism clear but DOTW error code parsing from exception message requires implementation-time verification

**Research date:** 2026-02-21
**Valid until:** 2026-03-21 (stable stack — Laravel 11, Lighthouse, DOTW V4 API — no version changes expected)
