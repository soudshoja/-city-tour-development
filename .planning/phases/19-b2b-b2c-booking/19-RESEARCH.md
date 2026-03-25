# Phase 19: B2B + B2C Booking - Research

**Researched:** 2026-03-24
**Domain:** Hotel booking pipeline with dual-track payment flows (credit line + gateway) on DOTW V4 XML API
**Confidence:** HIGH

## Summary

Phase 19 builds the complete booking pipeline on top of Phase 18's foundation (module scaffold, phone resolution, search, caching). The core challenge is bridging three independent timing systems: DOTW's 3-minute rate allocation window, payment gateway processing (5-15 minutes for B2C), and WhatsApp message delivery latency. The re-block-after-payment pattern is the single most critical architectural decision.

The existing codebase provides all required building blocks: `DotwService` (2,232 lines) has `confirmBooking`, `saveBooking`, `bookItinerary`, and `getRooms` with blocking support. `MyFatoorah.createCharge()` returns a `PaymentURL`. The `Credit` model tracks company balances via simple `SUM(amount)` queries. The `DotwPrebook` model tracks rate allocations with 3-minute expiry. The `DotwBooking` model stores immutable confirmation records. All of these exist and must NOT be modified -- the DotwAI module wraps them via delegation.

**Primary recommendation:** Build 5 REST endpoints (prebook_hotel, confirm_booking, payment_link, payment callback, get_company_balance) in the existing `app/Modules/DotwAI/` module, with a queued `ConfirmBookingAfterPaymentJob` for the asynchronous re-block + confirm flow. Credit deduction uses `DB::transaction` + `lockForUpdate()`. Voucher delivery delegates to `WhatsappController::sendToResayil()`.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Agent with credit line: prebook -> confirm immediately -> deduct credit -> send voucher via WhatsApp
- Agent without credit line: prebook -> generate payment link -> send via WhatsApp -> after payment webhook -> confirm with DOTW -> send voucher
- Credit deduction uses pessimistic locking (DB::transaction + lockForUpdate) to prevent concurrent overdraw
- get_company_balance endpoint returns credit_limit, used, available
- 0% markup by default for B2B (configurable per company)
- B2B/B2C enabled/disabled per company via CompanyDotwCredential columns (from Phase 18)
- B2C: RE-BLOCK after payment webhook received, then confirm. If re-block fails, refund payment and notify customer
- Configurable markup per company (default 20%) -- already applied in search results (Phase 18 HotelSearchService)
- MSP enforced: selling price >= DOTW totalMinimumSelling
- After confirmation: auto-create invoice + task + send voucher via WhatsApp
- POST /api/dotwai/prebook -- accepts phone, hotelId (or optionNumber), roomTypeCode, rateBasisId, checkIn, checkOut, occupancy
- POST /api/dotwai/book -- accepts phone, prebookKey, passengers array, specialRequests
- POST /api/dotwai/payment-link -- accepts phone, prebookKey, amount, currency
- POST /api/dotwai/payment-callback -- receives payment gateway webhook
- GET /api/dotwai/balance -- accepts phone, returns credit summary
- For APR rates (nonrefundable), use saveBooking + bookItinerary flow instead of confirmBooking
- Voucher must include paymentGuaranteedBy from DOTW response (certification requirement)

### Claude's Discretion
- DotwAIBooking model structure (new model or extend existing DotwPrebook)
- Queue job vs synchronous for payment callback -> DOTW confirmation
- Payment link generation method (which gateway class method to call)
- Voucher format (text message vs PDF attachment)
- How to handle partial payment or payment timeout
- Error recovery: what if DOTW confirm fails after payment received

### Deferred Ideas (OUT OF SCOPE)
- Cancellation flow (Phase 20)
- Accounting journal entries (Phase 20)
- Auto-reminders and lifecycle (Phase 21)
- Booking history and resend voucher (Phase 21)
- Dashboard monitoring (Phase 22)
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| B2B-01 | Agent with credit line can book directly -- no upfront payment | Credit model supports INVOICE type deduction; pessimistic locking pattern documented in PITFALLS.md Pitfall 2 |
| B2B-02 | Agent without credit line gets payment link via WhatsApp | MyFatoorah.createCharge() returns PaymentURL; WhatsappController.sendToResayil() delivers message |
| B2B-03 | prebook_hotel locks rate using option number from cached search | HotelSearchService.getCachedResults() returns numbered options; DotwService.getRooms(blocking=true) locks rate |
| B2B-04 | confirm_booking accepts passenger details and confirms with DOTW | DotwService.confirmBooking() accepts params array with rooms/passengers; sanitizePassengerName() validates names |
| B2B-05 | get_company_balance returns credit_limit, used, available | Credit.getTotalCreditsByClient() returns balance; company credit_limit from CompanyDotwCredential or Company model |
| B2B-06 | Company credit deduction uses pessimistic locking | DB::transaction + Credit::lockForUpdate()->sum('amount') pattern; prevents concurrent overdraw |
| B2B-07 | Booking creates voucher and sends via WhatsApp | WhatsappController.sendToResayil($phone, $message) + MessageBuilderService for formatting |
| B2C-01 | payment_link generates MyFatoorah/KNET payment URL | MyFatoorah.createCharge() returns payment_url; module registers own callback URL |
| B2C-02 | After payment webhook, re-block rate and auto-confirm | ConfirmBookingAfterPaymentJob: getRooms(blocking=true) -> confirmBooking() with retry backoff |
| B2C-03 | Configurable markup per company (default 20%) | Already applied in Phase 18 HotelSearchService; DotwAIContext.markupPercent available |
| B2C-04 | Auto-create invoice + task + voucher after confirmation | Task model (type=hotel), Invoice model, InvoiceDetail -- all exist; bridge creates them |
| B2C-05 | MSP enforced -- selling price never below DOTW minimum selling price | HotelSearchService already applies max(displayPrice, MSP); prebook must re-verify |
</phase_requirements>

## Standard Stack

### Core (All Existing -- No New Dependencies)

| Library/Component | Location | Purpose | Why Standard |
|-------------------|----------|---------|--------------|
| DotwService | `app/Services/DotwService.php` | DOTW XML API wrapper | Certified, 2,232 lines, per-company credentials |
| MyFatoorah | `app/Support/PaymentGateway/MyFatoorah.php` | Payment link generation | Production-proven, returns PaymentURL |
| Credit | `app/Models/Credit.php` | Company credit tracking | Uses SUM queries, 4 types: Invoice, Topup, Refund, Invoice Refund |
| DotwPrebook | `app/Models/DotwPrebook.php` | Rate allocation records | 3-min expiry, prebook_key unique, booking_details JSON |
| DotwBooking | `app/Models/DotwBooking.php` | Immutable booking confirmations | Append-only (UPDATED_AT=null), prebook_key reference |
| WhatsappController | `app/Http/Controllers/WhatsappController.php` | Resayil WhatsApp API | sendToResayil($phone, $message, $header, $footer, $buttons) |
| MessageBuilderService | `app/Modules/DotwAI/Services/MessageBuilderService.php` | Bilingual WhatsApp formatting | Static pure functions from Phase 18 |
| DotwAIResponse | `app/Modules/DotwAI/Services/DotwAIResponse.php` | Response envelope | success() and error() with whatsappMessage |
| DotwAIContext | `app/Modules/DotwAI/DTOs/DotwAIContext.php` | Request context | Immutable DTO: agent, companyId, credentials, track, markup |
| GatewayConfigService | `app/Services/GatewayConfigService.php` | Payment gateway config resolution | Loads from DB (Charges table) with env fallback |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| MyFatoorah directly | Tap, KNET, Hesabe, UPayment | All 5 exist; MyFatoorah is simplest for link generation. Module should support configurable gateway per company |
| Credit model SUM query | Separate balance table | SUM is simpler; with lockForUpdate() race condition is solved. Separate table is premature optimization |
| Synchronous DOTW confirm | Queue job always | B2B credit can be sync (fast path); B2C payment webhook MUST be queued (DOTW takes 25s, webhook expects 5-10s response) |

**No installation needed** -- all dependencies are existing project infrastructure.

## Architecture Patterns

### Recommended Module Extension Structure

```
app/Modules/DotwAI/
+-- Http/Controllers/
|   +-- BookingController.php         # prebook, book, payment-link, balance endpoints
|   +-- PaymentCallbackController.php # Payment gateway webhook handler
+-- Services/
|   +-- BookingService.php            # Orchestrates prebook/confirm/payment flows
|   +-- CreditService.php            # Pessimistic locking credit operations
|   +-- PaymentBridgeService.php     # Wraps MyFatoorah for payment link generation
|   +-- VoucherService.php           # Builds and sends voucher via WhatsApp
+-- Jobs/
|   +-- ConfirmBookingAfterPaymentJob.php  # Re-block + confirm + voucher (queued)
+-- Models/
|   +-- DotwAIBooking.php            # Lifecycle tracking model (NEW table)
+-- Http/Requests/
|   +-- PrebookRequest.php           # Validation for prebook endpoint
|   +-- ConfirmBookingRequest.php    # Validation for book endpoint
|   +-- PaymentLinkRequest.php       # Validation for payment-link endpoint
+-- Database/Migrations/
|   +-- create_dotwai_bookings_table.php
```

### Pattern 1: Prebook Flow (Rate Locking)

**What:** Lock a specific room/rate from cached search results and create a booking record.
**When to use:** When user says "book option 3" referencing cached search results.

```php
// BookingService::prebook()
public function prebook(DotwAIContext $context, array $input): array
{
    // 1. Resolve option from cache
    $cached = $this->searchService->getCachedResults($input['telephone']);
    $selectedHotel = $cached[$input['option_number'] - 1] ?? null;

    // 2. Call DotwService::getRooms(blocking=true) to lock rate
    $dotwService = new DotwService($context->companyId);
    $blockResult = $dotwService->getRooms($blockingParams, true, null, null, $context->companyId);

    // 3. Verify <status>checked</status>
    // 4. Create DotwAIBooking record with status=prebooked
    // 5. Store allocation_details, cancellation_rules, etc.
    // 6. Return prebookKey, total price, cancellation policy

    return ['prebookKey' => $booking->prebook_key, ...];
}
```

### Pattern 2: B2B Credit Confirm (Synchronous)

**What:** For B2B agents with credit, confirm immediately within a DB transaction.
**When to use:** B2B track + sufficient credit balance.

```php
// BookingService::confirmWithCredit()
DB::transaction(function () use ($booking, $context) {
    // 1. Lock credit rows for this company
    $balance = Credit::where('client_id', $clientId)
        ->lockForUpdate()
        ->sum('amount');

    if ($balance < $booking->original_total_fare) {
        throw new InsufficientCreditException($balance, $booking->original_total_fare);
    }

    // 2. Deduct credit within same transaction
    Credit::create([
        'company_id' => $context->companyId,
        'client_id' => $clientId,
        'type' => Credit::INVOICE,
        'amount' => -$booking->original_total_fare,
        'description' => "DOTW Booking: {$booking->prebook_key}",
    ]);

    // 3. Confirm with DOTW (within transaction -- if DOTW fails, credit rolls back)
    $dotwService = new DotwService($context->companyId);
    $confirmation = $dotwService->confirmBooking($bookingParams);

    // 4. Update booking record
    $booking->update([
        'status' => 'confirmed',
        'confirmation_no' => $confirmation['bookingCode'],
        'payment_status' => 'credit_applied',
    ]);
});
```

### Pattern 3: B2C Re-Block After Payment (Asynchronous)

**What:** After payment webhook, re-block the rate (fresh 3-min allocation) then confirm.
**When to use:** B2C payments and B2B gateway payments where allocation has expired.

```php
// ConfirmBookingAfterPaymentJob::handle()
class ConfirmBookingAfterPaymentJob implements ShouldQueue
{
    public $tries = 4;
    public $backoff = [30, 120, 300]; // seconds between retries

    public function handle(): void
    {
        $booking = DotwAIBooking::where('prebook_key', $this->prebookKey)->first();

        // Idempotency gate
        if ($booking->confirmation_no) {
            return; // Already confirmed
        }

        $dotwService = new DotwService($booking->company_id);

        // 1. RE-BLOCK: Get fresh allocation
        try {
            $freshBlock = $dotwService->getRooms($blockingParams, true, null, null, $booking->company_id);
            // Update allocation_details with fresh token
        } catch (\Exception $e) {
            // Rate no longer available -- initiate refund
            $this->initiateRefund($booking);
            return;
        }

        // 2. CONFIRM: Use fresh allocation within 3-min window
        $confirmation = $dotwService->confirmBooking($confirmParams);

        // 3. Post-confirm: update records, send voucher
        $booking->update(['status' => 'confirmed', 'confirmation_no' => $confirmation['bookingCode']]);
    }

    public function failed(\Throwable $e): void
    {
        // Flag for manual review, do NOT auto-refund (admin may retry manually)
        Log::critical('[DotwAI] Booking confirmation failed after all retries', [
            'prebook_key' => $this->prebookKey,
            'error' => $e->getMessage(),
        ]);
    }
}
```

### Pattern 4: APR Flow (saveBooking + bookItinerary)

**What:** For non-refundable Advance Purchase Rates, use the 2-step save+book flow.
**When to use:** When `is_apr = true` on the prebooked rate.

```php
// In BookingService, branch on is_apr:
if ($booking->is_apr) {
    // Flow B: saveBooking creates itinerary, bookItinerary confirms
    $itinerary = $dotwService->saveBooking($params, null, null, $context->companyId);
    $confirmation = $dotwService->bookItinerary(
        $itinerary['itineraryCode'], null, null, $context->companyId
    );
} else {
    // Flow A: direct confirmBooking
    $confirmation = $dotwService->confirmBooking($params, null, null, $context->companyId);
}
```

### Pattern 5: Payment Bridge (Module-Owned Webhook)

**What:** Generate payment links and handle callbacks without modifying PaymentController.
**When to use:** B2C and B2B-no-credit flows.

```php
// PaymentBridgeService -- wraps MyFatoorah with module-specific callback URL
class PaymentBridgeService
{
    public function createPaymentLink(DotwAIBooking $booking, DotwAIContext $context): array
    {
        $gateway = new MyFatoorah();

        // Build a Request object matching MyFatoorah.createCharge() expectations
        // Key: set CallBackUrl to module's own webhook endpoint
        $request = new Request([
            'final_amount' => $booking->display_total_fare,
            'client_name' => $booking->guest_name ?? 'Guest',
            'invoice_number' => 'DOTWAI-' . $booking->id,
            'payment_id' => $paymentRecord->id,
            'payment_gateway' => 'myfatoorah',
            'payment_method_id' => $this->getDefaultPaymentMethodId(),
        ]);

        return $gateway->createCharge($request);
        // Returns: ['status' => 'success', 'payment_url' => '...', 'invoice_id' => '...']
    }
}
```

### Anti-Patterns to Avoid

- **Modifying PaymentController.php:** It is 5000+ lines. Register a NEW route + controller inside the module for payment callbacks instead.
- **Modifying DotwService.php, DotwPrebook.php, DotwBooking.php:** These are certification-layer code. The module wraps them via delegation.
- **Creating journal entries in Phase 19:** Per CONTEXT.md, accounting journal entries are Phase 20. Phase 19 creates the booking and invoice records only.
- **Synchronous DOTW confirm in webhook handler:** DOTW API takes up to 25 seconds. Payment webhooks expect 5-10 second responses. Always queue.
- **Blocking rate at search time for B2C:** Rate expires in 3 minutes. Payment takes 5-15 minutes. Block at prebook time for display, then RE-BLOCK after payment.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Payment link generation | Custom payment URL builder | `MyFatoorah::createCharge()` | Handles MobileCountryCode, ExpiryDate, InvoiceItems, gateway validation |
| Credit balance check | Manual SUM query | `Credit::getTotalCreditsByClient($id)` with `lockForUpdate()` | Existing static method; wrap in transaction for atomicity |
| Passenger name sanitization | Custom regex | `DotwService::sanitizePassengerName()` | Handles 2-25 char limit, no spaces, no special chars |
| Rate allocation tracking | Custom timer system | `DotwPrebook::isValid()` / `DotwPrebook::setExpiry()` | 3-min expiry calculation built in |
| WhatsApp message delivery | Custom HTTP calls | `WhatsappController::sendToResayil($phone, $msg)` | Handles Resayil API auth, payload format, logging |
| Gateway config resolution | Hardcoded API keys | `GatewayConfigService` | Loads from DB (Charges table) with config/services.php fallback |
| XML API communication | Custom XML builder | `DotwService::confirmBooking()`, `saveBooking()`, `bookItinerary()` | Handles auth wrapper, gzip, error parsing, audit logging |

**Key insight:** Every external integration this phase needs already has a working wrapper in the codebase. The module's job is orchestration, not re-implementation.

## Common Pitfalls

### Pitfall 1: DOTW 3-Minute Rate Expiry vs B2C Payment Time

**What goes wrong:** Customer pays via MyFatoorah (5-15 min). By the time payment webhook fires, the DOTW allocation from prebook has expired. confirmBooking fails with stale allocation, but customer is already charged.
**Why it happens:** DOTW's allocation window is designed for server-to-server flows, not human-in-the-loop payment.
**How to avoid:** After payment webhook, RE-BLOCK the rate with a fresh `getRooms(blocking=true)` call. If re-block fails (rate no longer available), initiate automatic refund and notify customer.
**Warning signs:** Monitor time delta between prebook and confirm. Alert when > 2.5 minutes.

### Pitfall 2: Concurrent Credit Overdraw

**What goes wrong:** Two agents from the same company book simultaneously. Both check balance (500 KWD), both see sufficient. Total deducted: 700 KWD against 500 KWD limit.
**Why it happens:** `Credit::getTotalCreditsByClient()` is a simple SUM with no locking. WhatsApp bookings are inherently concurrent.
**How to avoid:** Wrap credit check + deduction in `DB::transaction` with `lockForUpdate()`. The lock serializes concurrent reads on the same client_id.
**Warning signs:** Daily health check: query for companies where sum of credit amounts is negative.

### Pitfall 3: Double-Booking via Webhook Retry

**What goes wrong:** Payment webhook fires. Laravel processes DOTW confirmBooking. Response takes 8+ seconds. n8n or payment gateway retries. Second request creates a duplicate DOTW booking.
**Why it happens:** No idempotency gate on the confirm flow. DOTW may accept the same confirmBooking twice.
**How to avoid:** Check `$booking->confirmation_no` before calling DOTW. If already set, return the existing confirmation. Use `prebook_key` UNIQUE constraint on the DotwAIBooking model.
**Warning signs:** Two DotwAIBooking records with the same prebook_key but different confirmation_no values.

### Pitfall 4: Payment Webhook Arrives But DOTW API Is Down

**What goes wrong:** Customer pays, webhook fires, DOTW API returns timeout/500. Customer charged, no booking.
**Why it happens:** DOTW API has documented timeout behavior. Payment webhooks are fire-and-forget.
**How to avoid:** Queue the DOTW confirmation job with retry logic (30s, 2min, 5min backoff). Acknowledge webhook with 200 immediately. If all retries fail, flag for manual review (do NOT auto-refund -- admin may retry).
**Warning signs:** Queue monitoring: ConfirmBookingAfterPaymentJob in failed_jobs table.

### Pitfall 5: Module Isolation Violation

**What goes wrong:** Developer adds DOTW-specific logic to PaymentController.php (5000+ lines) or modifies Credit model.
**Why it happens:** Temptation to "just add a method" to existing code.
**How to avoid:** Module registers its own payment webhook route + controller. Uses existing models via their public API (create, sum, etc.) without adding methods.
**Warning signs:** Any file modified outside `app/Modules/DotwAI/` (except route registration and bootstrap/providers.php).

### Pitfall 6: MyFatoorah createCharge Expects a Payment Record

**What goes wrong:** Trying to call MyFatoorah::createCharge() without first creating a Payment model record. The method expects `payment_id` and loads the Payment relationship to find the company.
**Why it happens:** MyFatoorah.createCharge() takes a Request with `payment_id` and uses `Payment::find()` internally to resolve company and client.
**How to avoid:** The PaymentBridgeService must create a Payment record first (linked to DotwAIBooking), then pass its ID to createCharge. OR bypass createCharge and call the MyFatoorah ExecutePayment API directly with the correct payload.
**Warning signs:** "Company not found for payment" error from MyFatoorah.

## Code Examples

### DotwAIBooking Model (New Module Model)

```php
// app/Modules/DotwAI/Models/DotwAIBooking.php
namespace App\Modules\DotwAI\Models;

use Illuminate\Database\Eloquent\Model;

class DotwAIBooking extends Model
{
    protected $table = 'dotwai_bookings';

    protected $fillable = [
        'prebook_key', 'dotw_prebook_id', 'dotw_booking_id',
        'hotel_booking_id', 'track', 'status', 'company_id',
        'agent_phone', 'client_phone', 'client_email',
        'hotel_id', 'hotel_name', 'city_code',
        'check_in', 'check_out',
        'original_total_fare', 'original_currency',
        'display_total_fare', 'display_currency',
        'markup_percentage', 'is_refundable', 'is_apr',
        'cancellation_deadline', 'confirmation_no',
        'payment_id', 'payment_link', 'payment_status',
        'payment_gateway_ref', 'task_id', 'invoice_id',
        'voucher_sent_at', 'guest_details',
        'allocation_details', 'room_type_code', 'rate_basis_id',
        'nationality_code', 'residence_code',
        'changed_occupancy', 'cancellation_rules',
        'payment_guaranteed_by',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'original_total_fare' => 'decimal:3',
        'display_total_fare' => 'decimal:3',
        'markup_percentage' => 'decimal:2',
        'is_refundable' => 'boolean',
        'is_apr' => 'boolean',
        'cancellation_deadline' => 'datetime',
        'voucher_sent_at' => 'datetime',
        'guest_details' => 'array',
        'changed_occupancy' => 'array',
        'cancellation_rules' => 'array',
    ];
}
```

### Migration: dotwai_bookings

```php
Schema::create('dotwai_bookings', function (Blueprint $table) {
    $table->id();
    $table->string('prebook_key')->unique()->index();
    $table->unsignedBigInteger('dotw_prebook_id')->nullable();   // cert-layer prebook
    $table->unsignedBigInteger('dotw_booking_id')->nullable();   // cert-layer booking
    $table->unsignedBigInteger('hotel_booking_id')->nullable();  // shared booking model
    $table->string('track');              // b2b, b2c, b2b_gateway
    $table->string('status')->default('prebooked'); // prebooked|pending_payment|confirmed|failed|expired
    $table->unsignedBigInteger('company_id')->index();
    $table->string('agent_phone');
    $table->string('client_phone')->nullable();
    $table->string('client_email')->nullable();
    $table->string('hotel_id');
    $table->string('hotel_name');
    $table->string('city_code')->nullable();
    $table->date('check_in');
    $table->date('check_out');
    $table->decimal('original_total_fare', 12, 3);
    $table->string('original_currency', 10);
    $table->decimal('display_total_fare', 12, 3);
    $table->string('display_currency', 10);
    $table->decimal('markup_percentage', 5, 2)->default(0);
    $table->boolean('is_refundable')->default(true);
    $table->boolean('is_apr')->default(false);
    $table->datetime('cancellation_deadline')->nullable();
    $table->string('confirmation_no')->nullable();
    $table->unsignedBigInteger('payment_id')->nullable();
    $table->text('payment_link')->nullable();
    $table->string('payment_status')->nullable();
    $table->string('payment_gateway_ref')->nullable();
    $table->unsignedBigInteger('task_id')->nullable();
    $table->unsignedBigInteger('invoice_id')->nullable();
    $table->datetime('voucher_sent_at')->nullable();
    $table->json('guest_details')->nullable();
    $table->text('allocation_details')->nullable();
    $table->string('room_type_code')->nullable();
    $table->string('rate_basis_id')->nullable();
    $table->string('nationality_code')->nullable();
    $table->string('residence_code')->nullable();
    $table->json('changed_occupancy')->nullable();
    $table->json('cancellation_rules')->nullable();
    $table->text('payment_guaranteed_by')->nullable();
    $table->timestamps();
});
```

### Credit Check with Pessimistic Locking

```php
// app/Modules/DotwAI/Services/CreditService.php
public function checkAndDeductCredit(int $clientId, int $companyId, float $amount, string $prebookKey): bool
{
    return DB::transaction(function () use ($clientId, $companyId, $amount, $prebookKey) {
        $balance = Credit::where('client_id', $clientId)
            ->lockForUpdate()
            ->sum('amount');

        if ($balance < $amount) {
            return false;
        }

        Credit::create([
            'company_id' => $companyId,
            'client_id' => $clientId,
            'type' => Credit::INVOICE,
            'amount' => -$amount,
            'description' => "DOTW Hotel Booking: {$prebookKey}",
        ]);

        return true;
    });
}

public function getBalance(int $clientId): array
{
    $used = Credit::where('client_id', $clientId)
        ->where('type', Credit::INVOICE)
        ->sum('amount'); // negative values

    $topups = Credit::where('client_id', $clientId)
        ->whereIn('type', [Credit::TOPUP, Credit::REFUND, Credit::INVOICE_REFUND])
        ->sum('amount'); // positive values

    $totalBalance = $topups + $used; // used is negative, so this gives available

    return [
        'credit_limit' => $topups,       // total ever credited
        'used_credit' => abs($used),     // total deducted
        'available_credit' => $totalBalance,
    ];
}
```

### WhatsApp Voucher Delivery

```php
// app/Modules/DotwAI/Services/VoucherService.php
public function sendVoucher(DotwAIBooking $booking): bool
{
    $message = MessageBuilderService::buildVoucher([
        'hotel_name' => $booking->hotel_name,
        'check_in' => $booking->check_in->format('Y-m-d'),
        'check_out' => $booking->check_out->format('Y-m-d'),
        'confirmation_no' => $booking->confirmation_no,
        'guest_names' => collect($booking->guest_details)->pluck('firstName')->join(', '),
        'total_fare' => $booking->display_total_fare,
        'currency' => $booking->display_currency,
        'payment_guaranteed_by' => $booking->payment_guaranteed_by,
        'payment_status' => $booking->payment_status,
    ]);

    $phone = $booking->agent_phone; // B2B: send to agent
    if ($booking->track === 'b2c' && $booking->client_phone) {
        $phone = $booking->client_phone; // B2C: send to customer
    }

    $wa = new \App\Http\Controllers\WhatsappController();
    $wa->sendToResayil($phone, $message);

    $booking->update(['voucher_sent_at' => now()]);
    return true;
}
```

### Payment Callback Webhook

```php
// app/Modules/DotwAI/Http/Controllers/PaymentCallbackController.php
public function handle(Request $request, string $gateway): JsonResponse
{
    // 1. Acknowledge webhook immediately
    // 2. Verify payment with gateway
    // 3. Find DotwAIBooking by payment reference
    // 4. Dispatch queued confirmation job

    $booking = DotwAIBooking::where('payment_gateway_ref', $gatewayRef)->first();

    if (!$booking || $booking->confirmation_no) {
        return response()->json(['status' => 'ok']); // Idempotent
    }

    $booking->update(['payment_status' => 'paid']);

    ConfirmBookingAfterPaymentJob::dispatch($booking->prebook_key)
        ->onQueue('dotwai');

    return response()->json(['status' => 'ok']);
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Block rate at search time, hold for payment | Block at prebook, RE-BLOCK after payment | Phase 19 design | Avoids 3-min expiry problem for B2C |
| Sync DOTW confirm in webhook | Queued job with retry backoff | Phase 19 design | Prevents webhook timeout + enables retry |
| Direct PaymentController modification | Module-owned PaymentCallbackController | Phase 19 design | Module isolation preserved |
| Single confirmBooking for all rates | confirmBooking for refundable, saveBooking+bookItinerary for APR | DOTW certification | Different API flows per rate type |

## Open Questions

1. **Credit model client_id mapping**
   - What we know: Credit model uses `client_id` for balance tracking. DotwAI module resolves phone -> agent -> company.
   - What's unclear: Which `client_id` to use for company credit balance? Is it the company's own client record, or the agent's client record?
   - Recommendation: Investigate `Credit::where('company_id', $companyId)->sum('amount')` as alternative. Or create a company-level client record for B2B credit tracking. Resolve during implementation by examining existing credit usage patterns.

2. **MyFatoorah Payment Record Dependency**
   - What we know: `MyFatoorah::createCharge()` expects a `payment_id` referencing an existing Payment model record. It loads `$payment->agent->branch->company` for company resolution.
   - What's unclear: Can the module create a Payment record independently, or must it go through existing payment creation flow?
   - Recommendation: Create a minimal Payment record in PaymentBridgeService before calling createCharge, OR call the MyFatoorah API directly via HTTP (bypassing the class) with the module's own callback URL. The direct HTTP approach is cleaner for module isolation.

3. **Task and Invoice Creation Specifics**
   - What we know: Task model (type=hotel) and Invoice model exist. Phase 20 handles journal entries.
   - What's unclear: Exact fields required for Task/Invoice creation in the DOTW context. What status should the task start with?
   - Recommendation: Create task with type=hotel, status=issued, and invoice with status=pending. Defer detailed accounting to Phase 20. Keep it minimal in Phase 19.

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit (via `php artisan test`) |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --filter DotwAI` |
| Full suite command | `php artisan test` |

### Phase Requirements -> Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| B2B-01 | Credit agent can book directly | unit | `php artisan test --filter BookingServiceCreditTest -x` | Wave 0 |
| B2B-02 | No-credit agent gets payment link | unit | `php artisan test --filter BookingServicePaymentLinkTest -x` | Wave 0 |
| B2B-03 | Prebook locks rate from cached results | unit | `php artisan test --filter PrebookServiceTest -x` | Wave 0 |
| B2B-04 | Confirm with passenger details | unit | `php artisan test --filter ConfirmBookingTest -x` | Wave 0 |
| B2B-05 | Balance returns credit info | unit | `php artisan test --filter CreditServiceTest -x` | Wave 0 |
| B2B-06 | Pessimistic locking prevents overdraw | unit | `php artisan test --filter CreditConcurrencyTest -x` | Wave 0 |
| B2B-07 | Voucher sent via WhatsApp | unit | `php artisan test --filter VoucherServiceTest -x` | Wave 0 |
| B2C-01 | Payment link generated | unit | `php artisan test --filter PaymentBridgeTest -x` | Wave 0 |
| B2C-02 | Re-block + auto-confirm after payment | unit | `php artisan test --filter ConfirmBookingAfterPaymentJobTest -x` | Wave 0 |
| B2C-03 | Markup applied correctly | unit | `php artisan test --filter MarkupTest -x` | Existing (Phase 18) |
| B2C-04 | Task + invoice created | unit | `php artisan test --filter BookingPostConfirmTest -x` | Wave 0 |
| B2C-05 | MSP enforced | unit | `php artisan test --filter MspEnforcementTest -x` | Wave 0 |

### Sampling Rate

- **Per task commit:** `php artisan test --filter DotwAI -x`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps

- [ ] `tests/Unit/DotwAI/BookingServiceTest.php` -- covers B2B-01, B2B-02, B2B-04
- [ ] `tests/Unit/DotwAI/PrebookServiceTest.php` -- covers B2B-03
- [ ] `tests/Unit/DotwAI/CreditServiceTest.php` -- covers B2B-05, B2B-06
- [ ] `tests/Unit/DotwAI/VoucherServiceTest.php` -- covers B2B-07
- [ ] `tests/Unit/DotwAI/PaymentBridgeTest.php` -- covers B2C-01
- [ ] `tests/Unit/DotwAI/ConfirmBookingAfterPaymentJobTest.php` -- covers B2C-02
- [ ] `tests/Unit/DotwAI/BookingPostConfirmTest.php` -- covers B2C-04, B2C-05

## Sources

### Primary (HIGH confidence)
- `app/Services/DotwService.php` -- Direct codebase analysis of confirmBooking (line 356), saveBooking (line 415), bookItinerary (line 471), getRooms (line 278)
- `app/Models/Credit.php` -- Direct analysis of credit types (INVOICE, TOPUP, REFUND, INVOICE_REFUND), getTotalCreditsByClient()
- `app/Support/PaymentGateway/MyFatoorah.php` -- Direct analysis of createCharge() (line 48), ExecutePayment payload structure, PaymentURL return
- `app/Models/DotwPrebook.php` -- Direct analysis of isValid(), setExpiry(), allocation tracking
- `app/Models/DotwBooking.php` -- Direct analysis of immutable append-only record structure
- `app/Http/Controllers/WhatsappController.php` -- Direct analysis of sendToResayil() (line 419)
- `.planning/research/PITFALLS.md` -- Rate expiry (Pitfall 1), credit locking (Pitfall 2), accounting timing (Pitfall 3), webhook retry (Pitfall 4/8)
- `.planning/research/ARCHITECTURE.md` -- Payment flow (Section 7), database design (Section 6), delegation pattern (Section 4)
- `.claude/skills/dotwai/SKILL.md` -- Booking tracks, flow documentation, model structure
- `.claude/skills/dotwai/references/laravel-files.md` -- Complete DotwService code, migration schemas, controller patterns
- `.claude/skills/dotw-api/references/api-methods.md` -- XML templates for confirmBooking, saveBooking, bookItinerary

### Secondary (MEDIUM confidence)
- `app/Services/GatewayConfigService.php` -- Gateway config resolution pattern (DB + env fallback)
- `app/Services/PaymentApplicationService.php` -- Credit application modes (full/partial/split)
- Phase 18 summaries (18-01, 18-02) -- Module patterns, service architecture, caching strategy

### Tertiary (LOW confidence)
- MyFatoorah payment link expiry behavior -- observed 2-day default in code but needs production verification
- Credit balance query performance under concurrent load -- lockForUpdate tested pattern but not load-tested

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- all components exist in codebase and were directly analyzed
- Architecture: HIGH -- patterns established by Phase 18 + ARCHITECTURE.md + PITFALLS.md research
- Pitfalls: HIGH -- 5 critical pitfalls identified from codebase analysis and domain research

**Research date:** 2026-03-24
**Valid until:** 2026-04-24 (stable -- no external dependency changes expected)
