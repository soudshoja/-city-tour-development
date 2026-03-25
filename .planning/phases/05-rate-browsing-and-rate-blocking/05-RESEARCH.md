# Phase 5: Rate Browsing & Rate Blocking — Research

**Researched:** 2026-02-21
**Domain:** DOTW V4 getRooms (browse + block), GraphQL mutation pattern, DotwPrebook model extension, markup transparency, single-active-prebook-per-user constraint
**Confidence:** HIGH — all findings derived from existing codebase (DotwService, DotwPrebook, established Phase 4 patterns). No external sources required.

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| RATE-01 | GraphQL `getRoomRates` query accepts hotel_code, check-in, check-out, room config, resayil_message_id header | DotwService::getRooms() already accepts these; new resolver wires them via GraphQL input + middleware |
| RATE-02 | Returns all room types for hotel with all meal plans and rates (detailed breakdown) | DotwService::getRooms(blocking=false) returns rooms[].details[] with full rate data |
| RATE-03 | Each rate includes: total_fare, tax, total_price, cancellation_policy (refundable/non-refundable with fees) | DotwService::parseRooms() returns price, taxes, cancellationRules per rateBasis; nonRefundable from rateType attribute |
| RATE-04 | Response includes `allocationDetails` token (opaque string, required for blocking and confirmation) | DotwService::parseRooms() returns allocationDetails per rateBasis detail; must be passed to blockRates |
| RATE-05 | Shows original_currency, exchange_rate, and final_currency (with markup applied) | RateMarkup type already in graphql/dotw.graphql; DotwService::applyMarkup() returns {original_fare, markup_percent, markup_amount, final_fare} |
| RATE-06 | Each rate tagged with rate_basis code (1331=RO, 1332=BB, 1333=HB, 1334=FB, 1335=AI, 1336=SC) | rateBasis id field from parseRooms() maps to DotwService rate basis constants |
| RATE-07 | Includes refundability status and cancellation deadline | nonRefundable attribute on rateType element; cancellationRules from parseCancellationRules() with fromDate/toDate/charge |
| RATE-08 | Logs operation to `dotw_audit_logs` with hotel_code, rates returned count | DotwService::getRooms() calls DotwAuditService::log(OP_RATES) internally — no double-logging |
| BLOCK-01 | GraphQL `blockRates` mutation accepts: hotel_code, dates, room_config, selected_room_type, selected_rate_basis, allocationDetails token, resayil_message_id header | DotwService::getRooms(blocking=true) accepts all these via roomTypeSelected; new mutation wires them |
| BLOCK-02 | Validates allocationDetails token matches selected hotel (prevents token mixing) | Must validate hotel_code in input matches hotel_code stored with the allocationDetails from browse call |
| BLOCK-03 | Calls DOTW getRooms with blocking=true (locks rate for 3 minutes) | DotwService::getRooms($params, true) — blocking path already implemented, validateBlockingStatus() validates lock |
| BLOCK-04 | Creates `dotw_prebooks` record: prebook_key (UUID), allocation_details, hotel_code, hotel_name, room_type, total_fare, total_tax, currency, is_refundable, expired_at (now + 3 min), resayil_message_id | DotwPrebook model exists with all needed fillable columns except: company_id, resayil_message_id, whatsapp_user_id — migration needed to add these |
| BLOCK-05 | Returns: prebook_key, hotel details, selected rates, countdown_timer_seconds (180), expires_at timestamp | Computed at response time from expires_at: countdown = max(0, now()->diffInSeconds(expires_at)) |
| BLOCK-06 | Rejects if allocation < 1 minute remaining (prompts re-search) | allocationDetails contains DOTW-side expiry; browse call must succeed; countdown guard < 60s triggers RESEARCH error |
| BLOCK-07 | Logs to `dotw_audit_logs` with prebook_key created, allocation_expiry time | DotwService::getRooms() with blocking=true calls DotwAuditService::log(OP_BLOCK); resolver adds prebook_key to request payload before logging |
| BLOCK-08 | Only one active prebook per (company, WhatsApp user) allowed (new prebook expires previous) | Requires company_id + resayil_message_id (WhatsApp user proxy) on dotw_prebooks; expiry logic in mutation before insert |
| MARKUP-03 | Markup calculation transparent in responses: {original_fare, markup_percent, markup_amount, final_fare} | RateMarkup type already defined in graphql/dotw.graphql; DotwService::applyMarkup() returns this exact shape |
| MARKUP-04 | Markup applied consistently across all operations (same hotel+rate always shows same markup) | DotwService::applyMarkup() is the single source of truth — used by DotwSearchHotels; getRoomRates resolver must use the same method |
| MARKUP-05 | Markup shown in WhatsApp messages (e.g., "100 KD → 120 KD after markup") | Output shape includes markup.original_fare and markup.final_fare; N8N formats the WhatsApp message using these fields |
</phase_requirements>

---

## Summary

Phase 5 implements two GraphQL operations: `getRoomRates` (query) and `blockRates` (mutation). Both build directly on the infrastructure established in Phases 1–4: DotwService, DotwAuditService, DotwTraceMiddleware, ResayilContextMiddleware, and the graphql/dotw.graphql schema.

`getRoomRates` calls `DotwService::getRooms(blocking=false)` to browse rates for a specific hotel. It returns all room types with all meal plans, cancellation policies, allocationDetails tokens, and transparent markup breakdown using the existing `RateMarkup` type. The allocationDetails token from this response must be passed verbatim to `blockRates`.

`blockRates` calls `DotwService::getRooms(blocking=true)` to lock the rate for 3 minutes, then creates a `dotw_prebooks` record tracking the allocation. The DotwPrebook model exists but its migration is missing two columns needed for Phase 5: `company_id` (for per-company isolation of BLOCK-08) and `resayil_message_id` (as the WhatsApp user proxy for BLOCK-08). A migration is needed to add these. The single-active-prebook constraint (BLOCK-08) requires expiring any previous active prebook for the same (company_id, resayil_message_id) pair before creating a new one.

**Primary recommendation:** Two new resolver classes (`DotwGetRoomRates`, `DotwBlockRates`) + schema extension in `graphql/dotw.graphql` + one migration to add `company_id` and `resayil_message_id` to `dotw_prebooks`. Follow the exact DotwSearchHotels resolver pattern for error handling, meta building, audit logging, and company resolution.

---

## Standard Stack

### Core (all already built — no new packages required)

| Component | Location | Purpose | Status |
|-----------|----------|---------|--------|
| `DotwService::getRooms()` | `app/Services/DotwService.php:271` | DOTW getRooms browse + block, XML builder, audit logging | Built in Phase 1 |
| `DotwService::applyMarkup()` | `app/Services/DotwService.php:170` | Per-company markup: original_fare, markup_percent, markup_amount, final_fare | Built in Phase 1 |
| `DotwPrebook` model | `app/Models/DotwPrebook.php` | Rate allocation tracking, isValid(), setExpiry(), valid() scope | Exists, needs migration to add company_id + resayil_message_id |
| `DotwAuditService` | `app/Services/DotwAuditService.php` | Fail-silent audit logging, OP_RATES + OP_BLOCK constants | Built in Phase 2 |
| `DotwTraceMiddleware` | `app/GraphQL/Middleware/DotwTraceMiddleware.php` | Injects trace_id into app container | Built in Phase 3 |
| `ResayilContextMiddleware` | `app/GraphQL/Middleware/ResayilContextMiddleware.php` | Sets resayil_message_id on request attributes | Built in Phase 2 |
| `RateMarkup` type | `graphql/dotw.graphql:261` | Markup breakdown: original_fare, markup_percent, markup_amount, final_fare | Built in Phase 4 |
| `DotwErrorCode`, `DotwErrorAction` enums | `graphql/dotw.graphql:32` | Machine-readable error codes for N8N workflow branching | Built in Phase 3 |

### No New Packages Required

All dependencies exist. Phase 5 is: 1 migration + 2 new resolver classes + schema extension.

---

## Architecture Patterns

### Recommended Project Structure

Phase 5 creates:
```
app/GraphQL/
├── Queries/
│   ├── DotwGetCities.php          (Phase 4, existing)
│   ├── DotwSearchHotels.php       (Phase 4, existing)
│   └── DotwGetRoomRates.php       (Phase 5, NEW — getRoomRates query resolver)
├── Mutations/
│   └── DotwBlockRates.php         (Phase 5, NEW — blockRates mutation resolver)

graphql/
└── dotw.graphql                   (extend with getRoomRates query + blockRates mutation + 8 new types)

database/migrations/
└── {timestamp}_add_company_id_resayil_to_dotw_prebooks_table.php  (Phase 5, NEW)
```

### Pattern 1: getRoomRates Resolver — Mirror DotwSearchHotels

The resolver follows the exact same structure as `DotwSearchHotels`:

```php
// Source: app/GraphQL/Queries/DotwSearchHotels.php (established pattern)
class DotwGetRoomRates
{
    public function __invoke($root, array $args, $context = null): array
    {
        $input = $args['input'] ?? [];
        $request = $context?->request();

        // 1. Extract Resayil IDs from request attributes (ResayilContextMiddleware)
        $resayilMessageId = $request?->attributes->get('resayil_message_id');
        $resayilQuoteId   = $request?->attributes->get('resayil_quote_id');

        // 2. Resolve company — B2B always requires auth
        $companyId = auth()->user()?->company?->id;
        if ($companyId === null) {
            return $this->errorResponse('CREDENTIALS_NOT_CONFIGURED', '...', 'RECONFIGURE_CREDENTIALS');
        }

        // 3. Build DotwService params from input
        $params = [
            'fromDate'  => $input['checkin'],
            'toDate'    => $input['checkout'],
            'productId' => $input['hotel_code'],
            'rooms'     => $this->buildRoomsFromInput($input['rooms'] ?? []),
            'fields'    => ['cancellation', 'allocationDetails', 'tariffNotes'],
        ];
        if (!empty($input['currency'])) {
            $params['currency'] = $input['currency'];
        }

        // 4. Call getRooms (browse — no blocking)
        try {
            $dotwService = new DotwService($companyId);
            $rooms = $dotwService->getRooms($params, false, $resayilMessageId, $resayilQuoteId, $companyId);
        } catch (\RuntimeException $e) {
            return $this->errorResponse('CREDENTIALS_NOT_CONFIGURED', '...', 'RECONFIGURE_CREDENTIALS', $e->getMessage());
        } catch (\Exception $e) {
            return $this->errorResponse('API_ERROR', '...', 'RETRY', $e->getMessage());
        }

        // 5. Format rooms — apply markup to each rate
        $formattedRooms = $this->formatRooms($rooms, $companyId);

        return [
            'success' => true,
            'error'   => null,
            'cached'  => false,   // getRoomRates never cached — rates change
            'data'    => [
                'hotel_code' => $input['hotel_code'],
                'rooms'      => $formattedRooms,
                'total_count' => count($formattedRooms),
            ],
            'meta' => $this->buildMeta($companyId),
        ];
    }
}
```

**Key insight:** `getRoomRates` is NOT cached. Rates change frequently; the browse call must always hit DOTW live to return current allocationDetails tokens. Only search results (Phase 4) are cached.

### Pattern 2: blockRates Mutation Resolver

```php
// Source: established DOTW resolver pattern (DotwSearchHotels.php)
class DotwBlockRates
{
    public function __invoke($root, array $args, $context = null): array
    {
        $input = $args['input'] ?? [];
        $request = $context?->request();

        $resayilMessageId = $request?->attributes->get('resayil_message_id');
        $resayilQuoteId   = $request?->attributes->get('resayil_quote_id');
        $companyId = auth()->user()?->company?->id;

        if ($companyId === null) {
            return $this->errorResponse('CREDENTIALS_NOT_CONFIGURED', '...', 'RECONFIGURE_CREDENTIALS');
        }

        // BLOCK-08: Expire any previous active prebook for this (company, WhatsApp user)
        if ($resayilMessageId) {
            DotwPrebook::where('company_id', $companyId)
                ->where('resayil_message_id', $resayilMessageId)
                ->where('expired_at', '>', now())
                ->update(['expired_at' => now()]);
        }

        // BLOCK-03: Call getRooms with blocking=true
        $params = [
            'fromDate'         => $input['checkin'],
            'toDate'           => $input['checkout'],
            'productId'        => $input['hotel_code'],
            'rooms'            => $this->buildRoomsFromInput($input['rooms'] ?? []),
            'roomTypeSelected' => [
                'code'               => $input['selected_room_type'],
                'selectedRateBasis'  => $input['selected_rate_basis'],
                'allocationDetails'  => $input['allocation_details'],
            ],
        ];
        if (!empty($input['currency'])) {
            $params['currency'] = $input['currency'];
        }

        try {
            $dotwService = new DotwService($companyId);
            $rooms = $dotwService->getRooms($params, true, $resayilMessageId, $resayilQuoteId, $companyId);
        } catch (\RuntimeException $e) {
            return $this->errorResponse('CREDENTIALS_NOT_CONFIGURED', '...', 'RECONFIGURE_CREDENTIALS', $e->getMessage());
        } catch (\Exception $e) {
            // DOTW blocking failed — rate no longer available
            return $this->errorResponse('RATE_UNAVAILABLE', 'Rate is no longer available. Please search again.', 'RESEARCH', $e->getMessage());
        }

        // BLOCK-04: Create dotw_prebooks record
        $prebookKey = (string) \Illuminate\Support\Str::uuid();
        $expiresAt  = now()->addMinutes(config('dotw.allocation_expiry_minutes', 3));

        // BLOCK-06: Reject if < 1 minute remaining
        $countdownSeconds = max(0, (int) now()->diffInSeconds($expiresAt, false));
        if ($countdownSeconds < 60) {
            return $this->errorResponse('ALLOCATION_EXPIRED', 'Rate offer too close to expiry. Please search again.', 'RESEARCH');
        }

        $rate = $this->extractSelectedRate($rooms, $input);
        $markup = $dotwService->applyMarkup((float) ($rate['price'] ?? 0));

        DotwPrebook::create([
            'prebook_key'        => $prebookKey,
            'allocation_details' => $input['allocation_details'],
            'hotel_code'         => $input['hotel_code'],
            'hotel_name'         => $input['hotel_name'] ?? '',
            'room_type'          => $input['selected_room_type'],
            'room_rate_basis'    => $input['selected_rate_basis'],
            'total_fare'         => $markup['final_fare'],
            'total_tax'          => (float) ($rate['taxes'] ?? 0),
            'original_currency'  => $input['currency'] ?? '',
            'is_refundable'      => ! ($rate['nonRefundable'] ?? false),
            'expired_at'         => $expiresAt,
            'company_id'         => $companyId,
            'resayil_message_id' => $resayilMessageId,
            'booking_details'    => [
                'cancellation_rules' => $rate['cancellationRules'] ?? [],
                'markup'             => $markup,
                'trace_id'           => app('dotw.trace_id'),
            ],
        ]);

        return [
            'success' => true,
            'error'   => null,
            'cached'  => false,
            'data'    => [
                'prebook_key'              => $prebookKey,
                'expires_at'              => $expiresAt->toIso8601String(),
                'countdown_timer_seconds' => $countdownSeconds,
                'hotel_code'             => $input['hotel_code'],
                'hotel_name'             => $input['hotel_name'] ?? '',
                'room_type'              => $input['selected_room_type'],
                'rate_basis'             => $input['selected_rate_basis'],
                'total_fare'             => $markup['final_fare'],
                'total_tax'              => (float) ($rate['taxes'] ?? 0),
                'markup'                 => $markup,
                'is_refundable'          => ! ($rate['nonRefundable'] ?? false),
                'cancellation_rules'     => $rate['cancellationRules'] ?? [],
            ],
            'meta' => $this->buildMeta($companyId),
        ];
    }
}
```

### Pattern 3: GraphQL Schema Extension

Follow the exact same pattern as Phase 4 — add to `graphql/dotw.graphql`:

```graphql
# Add to extend type Query:
extend type Query {
    "Get detailed room rates for a specific hotel. Returns all room types and meal plans with cancellation policies and allocationDetails tokens."
    getRoomRates(input: GetRoomRatesInput!): GetRoomRatesResponse!
        @field(resolver: "App\\GraphQL\\Queries\\DotwGetRoomRates")
}

extend type Mutation {
    "Block a selected rate for 3 minutes via DOTW. Creates a prebook record and returns countdown timer."
    blockRates(input: BlockRatesInput!): BlockRatesResponse!
        @field(resolver: "App\\GraphQL\\Mutations\\DotwBlockRates")
}
```

**Important: Triple-quoted descriptions required for multi-line SDL descriptions.** (Established decision from Phase 4, STATE.md line 83.)

### Pattern 4: Migration for dotw_prebooks Extension

The existing `dotw_prebooks` migration lacks `company_id` and `resayil_message_id`. A new migration adds these:

```php
// New migration: add_company_id_resayil_to_dotw_prebooks_table
Schema::table('dotw_prebooks', function (Blueprint $table) {
    $table->unsignedBigInteger('company_id')->nullable()->after('id')
        ->comment('Company that created this prebook (for BLOCK-08 single-active constraint)');
    $table->string('resayil_message_id')->nullable()->after('company_id')
        ->comment('WhatsApp user proxy — one active prebook per (company, resayil_message_id)');

    $table->index(['company_id', 'resayil_message_id', 'expired_at'],
        'dotw_prebooks_company_user_expiry_idx');
});
```

**No FK on company_id** — consistent with MOD-06 (dotw module is standalone, no FK to companies table). Pattern established in dotw_audit_logs migration.

Also update `DotwPrebook::$fillable` and `DotwPrebook::$casts` for these two columns.

### Pattern 5: Single-Active-Prebook Constraint (BLOCK-08)

Use `resayil_message_id` as the WhatsApp user proxy — it identifies the WhatsApp conversation thread. Before creating a new prebook:

```php
// Expire all active prebooks for this (company, WhatsApp user) — BLOCK-08
DotwPrebook::where('company_id', $companyId)
    ->where('resayil_message_id', $resayilMessageId)
    ->where('expired_at', '>', now())
    ->update(['expired_at' => now()]);
```

This is a soft expiry — we set `expired_at = now()` on the old record rather than deleting it. Audit trail preserved.

### Pattern 6: Markup Format for getRoomRates

The `RateMarkup` type is already in `graphql/dotw.graphql`. Use `DotwService::applyMarkup()` for every rate detail:

```php
// Source: app/Services/DotwService.php:170
// applyMarkup returns: {original_fare, markup_percent, markup_amount, final_fare}
$markup = $dotwService->applyMarkup((float) $detail['price']);
```

Apply to **every** rateBasis detail in the browse response (MARKUP-04: consistent application).

### Pattern 7: SEARCH-06 Hotel Metadata (Deferred from Phase 4)

Phase 4 SUMMARY noted: "hotel name, city, rating, location, image_url deferred to Phase 5 getRoomRates". However, the DOTW `getRooms` command also does NOT return hotel metadata (name, city, star rating, image). This is a known DOTW V4 limitation.

**Resolution strategy:** The `getRoomRates` response will include hotel_code (the hotel identifier passed by the caller) but hotel_name, rating, and image_url are not available from DOTW V4 without a separate hotel details API call. SEARCH-06 remains partially deferred — include hotel_code in getRoomRates response; note that name/rating/image fields are not returned by DOTW getRooms command and remain an open question.

**For Phase 5 scope:** Return `hotel_code` and all room/rate data. The `hotel_name` field in blockRates input allows the caller to pass the name from their own context (e.g., from a hotel directory lookup). This is acceptable for WhatsApp flow — agent selects hotel from search results and passes hotel name.

### Anti-Patterns to Avoid

- **Caching getRoomRates:** Never cache rate browse results. AllocationDetails tokens expire and rates change minute-to-minute. Only `searchHotels` results are cached.
- **Double-logging:** DotwService::getRooms() already calls DotwAuditService::log(OP_RATES or OP_BLOCK) internally. The resolver must NOT call DotwAuditService directly — that would double-log. (Pattern established in DotwSearchHotels — comment at line 107–108.)
- **Injecting DotwService into resolver constructor:** Always instantiate inside the callable/method with `new DotwService($companyId)`. Constructing in __construct() means credentials resolve at DI time, not per-request. (Decision from STATE.md line 87.)
- **Missing `allocationDetails` field in browse fields param:** The `fields` array in getRooms params must include `'allocationDetails'` or DOTW will not return the token. DotwService::buildGetRoomsBody() uses this fields array directly.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Markup calculation | Custom formula | `DotwService::applyMarkup(float $originalFare)` | Single source of truth; per-company markup_percent from DB; returns normalized array shape |
| UUID prebook key | `uniqid()` or random hex | `Str::uuid()` | Collision-safe, Eloquent-compatible, readable in logs |
| Allocation expiry | Manual `now()->addMinutes(3)` | `config('dotw.allocation_expiry_minutes', 3)` | Config-driven, consistent with DotwPrebook::setExpiry() |
| Blocking status validation | Custom XML check | `DotwService::validateBlockingStatus()` (called internally by getRooms when blocking=true) | Already implemented; throws Exception with descriptive message |
| Audit logging | Direct DotwAuditLog::create() | DotwService::getRooms() logs internally | Avoid double-logging; audit already happens inside the service |
| Single-prebook constraint | Application-level lock | DB `update(['expired_at' => now()])` before insert | Simple, atomic, preserves audit trail |
| Rate currency handling | Store or convert | Pass through from DOTW response (same as Phase 4) | DOTW returns currency in rateType attribute; no conversion needed |

---

## Common Pitfalls

### Pitfall 1: allocationDetails Token Corruption
**What goes wrong:** The allocationDetails token is an opaque base64/XML blob. Any HTML-encoding, JSON-encoding, or truncation causes DOTW to reject the blocking call with "rate no longer available."
**Why it happens:** PHP's htmlspecialchars() is called in DotwService::buildGetRoomsBody() — this is correct for the XML call but the token must be stored raw in the DB. If the resolver HTML-decodes or re-encodes the token when reading it from input, it gets corrupted.
**How to avoid:** Accept the allocationDetails token from GraphQL input as a raw String. Pass it directly to DotwService without modification. DotwService::buildGetRoomsBody() handles its own XML-safe encoding internally.
**Warning signs:** DOTW returns E1003 "Rate no longer available" immediately after a successful browse call.

### Pitfall 2: BLOCK-08 Race Condition — Expiry Before Insert
**What goes wrong:** If expiry update and prebook insert are not logically ordered, two concurrent mutations could both pass the expiry check and both create records.
**Why it happens:** No DB transaction wrapping the expire + insert pair.
**How to avoid:** Wrap the expire-old + create-new pair in `DB::transaction()`.

```php
DB::transaction(function () use (...) {
    DotwPrebook::where('company_id', $companyId)
        ->where('resayil_message_id', $resayilMessageId)
        ->where('expired_at', '>', now())
        ->update(['expired_at' => now()]);

    DotwPrebook::create([...]);
});
```

### Pitfall 3: countdown_timer_seconds Stale by Response Time
**What goes wrong:** The countdown_timer_seconds value computed at the start of the resolver is already a few hundred milliseconds old by the time the response reaches the N8N caller.
**Why it happens:** Network round-trip + PHP processing time.
**How to avoid:** Accept this as inherent — 180 seconds minus a few hundred ms is still "approximately 180". Compute countdown from `expires_at` timestamp: `max(0, (int)now()->diffInSeconds($expiresAt, false))`. N8N can recompute from `expires_at` on each polling tick if needed.

### Pitfall 4: blockRates Mutation Not Registered in Schema
**What goes wrong:** Lighthouse does not register mutations unless `extend type Mutation { ... }` is present in an imported schema file.
**Why it happens:** `graphql/dotw.graphql` extends `type Query` correctly in Phase 4 but `extend type Mutation` has not been added yet.
**How to avoid:** Add `extend type Mutation { ... }` to `graphql/dotw.graphql`, NOT to `schema.graphql`. Lighthouse auto-imports all files in the graphql directory (verify via `config/lighthouse.php` schema path setting).

### Pitfall 5: parseRooms() returns rooms[], not the selected rate directly
**What goes wrong:** After blocking, the resolver needs to extract the specific rateBasis detail that was locked to build the prebook record. parseRooms() returns an array of rooms, each with details[]. The blocking call with `roomTypeSelected` returns a single room with the locked rateBasis.
**Why it happens:** It's easy to assume blocking returns a single flat object.
**How to avoid:** After blocking, extract `$rooms[0]['details'][0]` — blocking with a single roomTypeSelected returns one room with one rateBasis detail (the locked one). Guard against empty array.

### Pitfall 6: hotel_name Required but Not from DOTW
**What goes wrong:** blockRates needs to store hotel_name in dotw_prebooks, but DOTW getRooms does not return hotel name.
**Why it happens:** DOTW V4 separates hotel metadata from rate data.
**How to avoid:** Accept hotel_name as an **optional input field** in BlockRatesInput. The N8N/Resayil caller knows the hotel name from the searchHotels response (agent selected it). If not provided, store an empty string or the hotel_code as fallback. Do not make hotel_name a required field.

---

## Code Examples

### getRooms Browse Call (with allocationDetails field requested)

```php
// Source: app/Services/DotwService.php DOTW_INTEGRATION.md
$params = [
    'fromDate'  => '2026-03-10',
    'toDate'    => '2026-03-12',
    'currency'  => 'KWD',
    'productId' => 12345,         // hotel_code from searchHotels
    'rooms'     => [
        [
            'adultsCode'                  => 2,
            'children'                    => [],
            'rateBasis'                   => DotwService::RATE_BASIS_ALL,
            'passengerNationality'        => 'KW',
            'passengerCountryOfResidence' => 'KW',
        ]
    ],
    'fields'    => ['cancellation', 'allocationDetails', 'tariffNotes'],
];
$rooms = $dotwService->getRooms($params, false, $resayilMessageId, $resayilQuoteId, $companyId);
// Returns: [['roomTypeCode'=>'...', 'roomName'=>'...', 'details'=>[['id'=>'1332', 'price'=>100, 'taxes'=>10, 'allocationDetails'=>'...', 'cancellationRules'=>[...]]]]]
```

### getRooms Block Call (locks rate for 3 minutes)

```php
// Source: app/Services/DotwService.php (blocking=true path)
$blockParams = [
    'fromDate'  => '2026-03-10',
    'toDate'    => '2026-03-12',
    'currency'  => 'KWD',
    'productId' => 12345,
    'rooms'     => $rooms,   // same rooms array as browse
    'roomTypeSelected' => [
        'code'              => 'ROOM_TYPE_CODE',      // from browse response roomTypeCode
        'selectedRateBasis' => '1332',                // rateBasis id from browse
        'allocationDetails' => 'OPAQUE_TOKEN_HERE',   // verbatim from browse response
    ],
];
$blocked = $dotwService->getRooms($blockParams, true, $resayilMessageId, $resayilQuoteId, $companyId);
// validateBlockingStatus() called internally — throws Exception if status != 'checked'
```

### Applying Markup Consistently (MARKUP-04)

```php
// Source: app/Services/DotwService.php:170
// Same method used by DotwSearchHotels — ensures consistency across operations
$dotwService = new DotwService($companyId);
foreach ($rooms as $room) {
    foreach ($room['details'] as $detail) {
        $markup = $dotwService->applyMarkup((float) $detail['price']);
        // $markup = ['original_fare'=>100, 'markup_percent'=>20, 'markup_amount'=>20, 'final_fare'=>120]
    }
}
```

### DotwPrebook Create with new columns

```php
// Source: established DotwPrebook model pattern (app/Models/DotwPrebook.php)
// After migration adds company_id + resayil_message_id to dotw_prebooks
$prebookKey = (string) \Illuminate\Support\Str::uuid();
$expiresAt  = now()->addMinutes(config('dotw.allocation_expiry_minutes', 3));

\DB::transaction(function () use (...) {
    // BLOCK-08: expire previous active prebook
    DotwPrebook::where('company_id', $companyId)
        ->where('resayil_message_id', $resayilMessageId)
        ->where('expired_at', '>', now())
        ->update(['expired_at' => now()]);

    DotwPrebook::create([
        'prebook_key'        => $prebookKey,
        'allocation_details' => $input['allocation_details'],
        'hotel_code'         => $input['hotel_code'],
        'hotel_name'         => $input['hotel_name'] ?? '',
        'room_type'          => $input['selected_room_type'],
        'room_rate_basis'    => $input['selected_rate_basis'],
        'total_fare'         => $markup['final_fare'],
        'total_tax'          => (float) ($rate['taxes'] ?? 0),
        'original_currency'  => $input['currency'] ?? '',
        'is_refundable'      => ! ($rate['nonRefundable'] ?? false),
        'expired_at'         => $expiresAt,
        'company_id'         => $companyId,
        'resayil_message_id' => $resayilMessageId,
        'booking_details'    => [
            'cancellation_rules' => $rate['cancellationRules'] ?? [],
            'markup'             => $markup,
        ],
    ]);
});
```

### buildMeta (identical across all resolvers)

```php
// Source: app/GraphQL/Queries/DotwSearchHotels.php:319 (established pattern)
private function buildMeta(int $companyId): array
{
    return [
        'trace_id'   => app('dotw.trace_id'),
        'request_id' => app('dotw.trace_id'),
        'timestamp'  => now()->toIso8601String(),
        'company_id' => $companyId,
    ];
}
```

### errorResponse (identical across all resolvers)

```php
// Source: app/GraphQL/Queries/DotwSearchHotels.php:338 (established pattern)
private function errorResponse(string $code, string $message, string $action, ?string $details = null): array
{
    return [
        'success' => false,
        'error'   => [
            'error_code'    => $code,
            'error_message' => $message,
            'error_details' => $details,
            'action'        => $action,
        ],
        'cached' => false,
        'meta'   => [
            'trace_id'   => app('dotw.trace_id'),
            'request_id' => app('dotw.trace_id'),
            'timestamp'  => now()->toIso8601String(),
            'company_id' => 0,
        ],
        'data'   => null,
    ];
}
```

---

## New GraphQL Types Required

These types must be added to `graphql/dotw.graphql`:

```graphql
# Query + Mutation declarations
extend type Query {
    getRoomRates(input: GetRoomRatesInput!): GetRoomRatesResponse!
        @field(resolver: "App\\GraphQL\\Queries\\DotwGetRoomRates")
}

extend type Mutation {
    blockRates(input: BlockRatesInput!): BlockRatesResponse!
        @field(resolver: "App\\GraphQL\\Mutations\\DotwBlockRates")
}

# Input types
input GetRoomRatesInput {
    hotel_code: String!
    checkin: String!
    checkout: String!
    rooms: [SearchHotelRoomInput!]!   # reuse existing input type from Phase 4
    currency: String
}

input BlockRatesInput {
    hotel_code: String!
    hotel_name: String               # optional — caller passes from their context
    checkin: String!
    checkout: String!
    rooms: [SearchHotelRoomInput!]!  # reuse existing input type from Phase 4
    selected_room_type: String!      # roomTypeCode from getRoomRates
    selected_rate_basis: String!     # rateBasis id from getRoomRates
    allocation_details: String!      # allocationDetails token from getRoomRates — pass verbatim
    currency: String
}

# Response types
type GetRoomRatesResponse {
    success: Boolean!
    error: DotwError
    meta: DotwMeta!
    cached: Boolean!
    data: GetRoomRatesData
}

type GetRoomRatesData {
    hotel_code: String!
    rooms: [RoomRateResult!]!
    total_count: Int!
}

type RoomRateResult {
    room_type_code: String!
    room_name: String!
    rate_details: [RateDetail!]!
}

type RateDetail {
    rate_basis_id: String!          # e.g. "1332" = BB
    rate_basis_name: String!        # e.g. "Bed & Breakfast"
    is_refundable: Boolean!
    total_fare: Float!
    total_taxes: Float!
    total_price: Float!
    markup: RateMarkup!             # reuse existing type from Phase 4
    allocation_details: String!     # opaque token — pass to blockRates verbatim
    cancellation_rules: [CancellationRule!]!
}

type CancellationRule {
    from_date: String!
    to_date: String!
    charge: Float!
    cancel_charge: Float!
}

type BlockRatesResponse {
    success: Boolean!
    error: DotwError
    meta: DotwMeta!
    cached: Boolean!
    data: BlockRatesData
}

type BlockRatesData {
    prebook_key: String!
    expires_at: String!
    countdown_timer_seconds: Int!
    hotel_code: String!
    hotel_name: String!
    room_type: String!
    rate_basis: String!
    total_fare: Float!
    total_tax: Float!
    markup: RateMarkup!
    is_refundable: Boolean!
    cancellation_rules: [CancellationRule!]!
}
```

---

## Rate Basis Code Mapping

From DotwService constants and REQUIREMENTS.md RATE-06:

| Code | Constant | Meal Plan |
|------|----------|-----------|
| 1331 | RATE_BASIS_ROOM_ONLY | Room Only (RO) |
| 1332 | RATE_BASIS_BB | Bed & Breakfast |
| 1333 | RATE_BASIS_HB | Half Board |
| 1334 | RATE_BASIS_FB | Full Board |
| 1335 | RATE_BASIS_AI | All Inclusive |
| 1336 | RATE_BASIS_SC | Self Catering |

Map in `formatRateDetails()`:
```php
private const RATE_BASIS_NAMES = [
    '1331' => 'Room Only',
    '1332' => 'Bed & Breakfast',
    '1333' => 'Half Board',
    '1334' => 'Full Board',
    '1335' => 'All Inclusive',
    '1336' => 'Self Catering',
];
```

---

## DotwPrebook Model Updates Required

The existing `DotwPrebook` model needs updates after migration:

1. Add `'company_id'` and `'resayil_message_id'` to `$fillable`
2. Add `'company_id' => 'integer'` to `$casts`
3. Add PHPDoc `@property int|null $company_id` and `@property string|null $resayil_message_id`
4. Add a named scope for BLOCK-08 query:
```php
public static function activeForUser(int $companyId, string $resayilMessageId): Builder
{
    return static::where('company_id', $companyId)
        ->where('resayil_message_id', $resayilMessageId)
        ->where('expired_at', '>', now());
}
```

---

## Open Questions

1. **SEARCH-06 hotel metadata — getRoomRates still can't provide it**
   - What we know: DOTW `getRooms` does not return hotel name, star rating, location, image_url — same limitation as `searchhotels`
   - What's unclear: Is there a DOTW command (e.g. `gethoteldetails`) that returns metadata?
   - Recommendation: For Phase 5 scope, include `hotel_code` in response and accept optional `hotel_name` as input to blockRates. N8N caller can pass hotel name from a static directory or from their own hotel metadata lookup. Mark SEARCH-06 as still deferred/partially complete after Phase 5.

2. **allocationDetails expiry from DOTW perspective**
   - What we know: DOTW specification says rate is locked for 3 minutes from blocking call
   - What's unclear: Does DOTW embed the expiry timestamp in the allocationDetails token, or is it always 3 minutes from call time?
   - Recommendation: Treat expiry as always `now() + config('dotw.allocation_expiry_minutes', 3)` — consistent with DotwPrebook::setExpiry() pattern. Do not attempt to parse the allocationDetails token.

3. **Multi-room blocking semantics**
   - What we know: blockRates accepts a `rooms` array (for the blocking getRooms call) but `roomTypeSelected` in DotwService only supports one room selection at a time
   - What's unclear: Phase 5 success criteria implies single rate selection; BLOCK-01 mentions `selected_room_type` (singular)
   - Recommendation: Phase 5 blocks a single room type/rate. Multi-room blocking (e.g., select different room types for different rooms) is Phase 6+ complexity. Scope blockRates to one roomTypeSelected.

---

## State of the Art

| Area | Established Pattern | Phase that Set It | Phase 5 Follows |
|------|--------------------|--------------------|-----------------|
| Resolver structure | `__invoke($root, $args, $context)` + errorResponse() + buildMeta() | Phase 4 (DotwSearchHotels) | Yes |
| Company resolution | `auth()->user()?->company?->id` | Phase 1 | Yes |
| Resayil IDs | `$request->attributes->get('resayil_message_id')` | Phase 2 | Yes |
| Markup | `DotwService::applyMarkup()` | Phase 1 | Yes |
| Audit logging | Internal to DotwService — do NOT call DotwAuditService in resolver | Phase 4 | Yes |
| Schema extension | `extend type Query`/`extend type Mutation` in dotw.graphql | Phase 3/4 | Yes |
| No FK on company_id in DOTW tables | standalone per MOD-06 | Phase 2 | Yes — migration uses nullable, no FK |
| Triple-quoted descriptions in SDL | Required for multi-line | Phase 4 (STATE.md) | Yes |
| No caching on rate operations | Only searchHotels is cached | Phase 3/4 | Yes |
| Error codes from DotwErrorCode enum | VALIDATION_ERROR not INVALID_INPUT | Phase 4 (STATE.md) | Yes |

---

## Sources

### Primary (HIGH confidence)

All findings from direct codebase inspection:

- `app/Services/DotwService.php` — getRooms(), applyMarkup(), parseRooms(), validateBlockingStatus(), rate basis constants, buildGetRoomsBody()
- `app/GraphQL/Queries/DotwSearchHotels.php` — resolver structure template (errorResponse, buildMeta, formatHotels, company resolution, Resayil IDs, no double-logging comment)
- `app/Models/DotwPrebook.php` — existing columns, isValid(), setExpiry(), valid() scope
- `database/migrations/2026_02_21_033317_create_dotw_prebooks_table.php` — existing schema (missing company_id, resayil_message_id)
- `graphql/dotw.graphql` — existing types (DotwMeta, DotwError, RateMarkup, SearchHotelRoomInput)
- `app/Services/DotwCacheService.php` — isCached() before remember() pattern
- `app/Services/DotwAuditService.php` — OP_RATES, OP_BLOCK constants
- `.planning/STATE.md` — accumulated decisions (no FK, triple-quoted SDL, DotwErrorCode values, no double-logging)
- `DOTW_INTEGRATION.md` — dual getRooms pattern, allocationDetails flow, 3-minute expiry

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all components exist and are inspected directly
- Architecture: HIGH — exact patterns from Phase 4 established, minimal new design decisions
- Pitfalls: HIGH — derived from codebase analysis (allocationDetails handling, BLOCK-08 race condition, double-logging)
- Migration design: HIGH — consistent with existing dotw migrations (no FK, nullable, standalone)

**Research date:** 2026-02-21
**Valid until:** 2026-03-21 (30 days — stack is stable, internal codebase only)
