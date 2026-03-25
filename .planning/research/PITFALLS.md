# Domain Pitfalls: DOTW AI Module

**Domain:** WhatsApp-based hotel booking system on existing travel agency platform
**Researched:** 2026-03-24
**Confidence:** HIGH (based on codebase analysis + DOTW API certification experience + industry patterns)

---

## Critical Pitfalls

Mistakes that cause data corruption, financial loss, or require architectural rewrites.

---

### Pitfall 1: DOTW 3-Minute Rate Allocation Expires Before B2C Payment Completes

**What goes wrong:**
The B2C flow is: search -> block rate (3-min timer starts) -> send payment link via WhatsApp -> customer opens link -> enters card details -> payment gateway processes -> webhook fires -> Laravel calls DOTW confirmBooking. This chain routinely takes 5-15 minutes. By the time payment succeeds, the DOTW allocation has expired. confirmBooking fails with a stale allocation token, but the customer has already been charged.

**Why it happens:**
- DOTW's 3-minute allocation window is designed for server-to-server flows, not human-in-the-loop payment collection
- Payment gateways (MyFatoorah, KNET, Tap) have their own processing delays (30-120 seconds)
- WhatsApp message delivery is not instant (1-30 seconds, sometimes minutes if queued)
- The customer needs time to read the message, click the link, enter card details
- No re-blocking mechanism exists in the current DotwPrebook model

**Consequences:**
- Customer charged but no hotel booking confirmed -- requires manual refund
- Support tickets, chargebacks, loss of customer trust
- Potential accounting entries that need manual reversal

**Prevention:**
```
Strategy: Deferred Booking with Re-Block

1. At search time: store rate details but do NOT block yet (or block for display only)
2. At payment confirmation (webhook): RE-BLOCK the rate with fresh getRooms(blocking=true)
3. If re-block succeeds: proceed with confirmBooking immediately
4. If re-block fails (rate unavailable): initiate automatic refund, notify customer via WhatsApp

Alternative: For B2B credit-line bookings, block-then-confirm immediately (no payment delay)
```

```php
// In payment webhook handler:
public function processDotwBookingAfterPayment(Payment $payment): array
{
    $prebook = DotwPrebook::where('hotel_booking_id', $payment->hotel_booking_id)->first();

    // CRITICAL: Re-block the rate before confirming
    $dotwService = new DotwService($prebook->company_id);
    try {
        $freshBlock = $dotwService->getRooms($prebook->buildBlockingParams(), true);
        // Update allocation_details with fresh token
        $prebook->update([
            'allocation_details' => $freshBlock['allocationDetails'],
            'expired_at' => now()->addMinutes(3),
        ]);
    } catch (\Exception $e) {
        // Rate no longer available -- refund customer
        $this->initiateAutoRefund($payment);
        $this->notifyCustomerBookingFailed($payment);
        return ['success' => false, 'reason' => 'rate_unavailable'];
    }

    // Now confirm within the fresh 3-minute window
    $result = $dotwService->confirmBooking(...);
}
```

**Detection:** Monitor the time delta between prebook creation and confirmBooking call. Alert when delta > 2.5 minutes. Track "payment succeeded but booking failed" as a critical metric.

**Phase:** Must be addressed in Phase 1 (B2C Payment Flow). This is the single most important architectural decision in the entire module.

---

### Pitfall 2: Credit Line Concurrent Deduction -- Two Agents Book Simultaneously

**What goes wrong:**
Agent A and Agent B both serve the same company (company_id=5). Company has 500 KWD credit. Agent A books a hotel for 300 KWD. Agent B books a hotel for 400 KWD. Both check credit balance, both see 500 KWD available, both proceed. Total deducted: 700 KWD against 500 KWD limit. Company is now 200 KWD overdrawn.

**Why it happens:**
- The existing Credit model uses `getTotalCreditsByClient()` which is a simple SUM query with no locking
- WhatsApp-based bookings are inherently concurrent (multiple agents on different phones)
- B2B credit bookings skip the payment gateway, so there is no external serialization point
- The current codebase has no pessimistic locking on credit balance checks for booking flows

**Consequences:**
- Company exceeds credit limit, creating uncollectable receivables
- Accounting entries reference credit that does not exist
- If the credit check happens in a different transaction than the deduction, the race window is wide

**Prevention:**
```php
// WRONG: Check-then-act without locking
$balance = Credit::getTotalCreditsByClient($clientId);
if ($balance >= $bookingAmount) {
    // Another request can slip in here
    Credit::create([...deduction...]);
}

// CORRECT: Pessimistic lock within a transaction
DB::transaction(function () use ($clientId, $bookingAmount, $companyId) {
    // Lock all credit rows for this client to prevent concurrent reads
    $balance = Credit::where('client_id', $clientId)
        ->lockForUpdate()
        ->sum('amount');

    if ($balance < $bookingAmount) {
        throw new InsufficientCreditException($balance, $bookingAmount);
    }

    // Deduct within the same transaction
    Credit::create([
        'client_id' => $clientId,
        'company_id' => $companyId,
        'type' => Credit::INVOICE,
        'amount' => -$bookingAmount,
        'description' => "DOTW Hotel Booking: {$prebookKey}",
    ]);

    // Proceed with DOTW confirmBooking within same transaction
    // ...
});
```

**Detection:** Query for companies where sum of credit amounts is negative. This should never happen. Run as a daily health check.

**Phase:** Must be addressed in Phase 1 (B2B Credit Booking Flow). The credit reservation must be atomic with the booking confirmation.

---

### Pitfall 3: Accounting Entry Timing -- Journal Entries Created Before Booking Confirmed

**What goes wrong:**
The system creates journal entries (debit Accounts Receivable, credit Revenue) when the booking is initiated. Then the DOTW confirmBooking call fails (network timeout, rate expired, DOTW server error). Now there are journal entries in the ledger for revenue that does not exist. Manual reversal is required.

**Why it happens:**
- The existing RunAutoBilling command creates journal entries as part of invoice creation, before external confirmation
- Developers copy this pattern into the DOTW booking flow
- The "happy path" bias -- assuming the API call will succeed
- The existing TBO flow (processTBOBookingAfterPayment) creates booking first, then handles accounting, but this pattern is not enforced by any framework

**Consequences:**
- Phantom revenue in financial reports
- Orphaned journal entries require manual cleanup
- Trial balance does not balance if reversal entries are missed
- Auditors flag inconsistencies

**Prevention:**
```
The hybrid accounting model must enforce: journal entries ONLY for confirmed money movement.

Timeline:
1. Booking initiated    -> CRM event only (DotwBookingEvent status=pending)
2. Payment received     -> Journal: DR Bank, CR Unearned Revenue
3. DOTW booking confirmed -> Journal: DR Unearned Revenue, CR Revenue
4. DOTW booking failed    -> Journal: DR Unearned Revenue, CR Bank (auto-refund)

For B2B credit:
1. Credit reserved      -> CRM event only (no journal yet)
2. DOTW booking confirmed -> Journal: DR Accounts Receivable, CR Revenue + DR COGS, CR Accounts Payable
3. DOTW booking failed    -> Release credit reservation (no journal needed)
```

**Detection:** Check for journal entries where the linked task/booking has status=failed or status=cancelled but no reversal entry exists.

**Phase:** Must be addressed in Phase 2 (Accounting Integration). Establish the rule early: "No journal entry until money moves or liability is confirmed."

---

### Pitfall 4: Payment Gateway Webhook Arrives But DOTW API Is Down

**What goes wrong:**
Customer pays successfully. MyFatoorah/KNET webhook fires. Laravel tries to call DOTW confirmBooking. DOTW API returns timeout or 500 error. The webhook handler returns an error or throws an exception. The payment gateway may retry the webhook, causing duplicate processing. Or worse, the webhook is acknowledged but the booking is never confirmed, leaving the customer in limbo.

**Why it happens:**
- DOTW API has documented timeout behavior (the DotwService already handles DotwTimeoutException)
- Payment webhooks are fire-and-forget from the gateway's perspective -- they expect quick acknowledgment
- No queued retry mechanism exists for failed DOTW API calls after payment
- The existing PaymentController processes TBO bookings synchronously in the webhook handler

**Consequences:**
- Customer charged, no booking, no notification
- If webhook is retried: potential double-booking or double-charge
- Manual intervention required to either confirm booking or refund customer

**Prevention:**
```
Strategy: Acknowledge webhook immediately, process DOTW booking asynchronously via queue

1. Payment webhook arrives -> Acknowledge with 200 immediately
2. Dispatch DotwConfirmBookingJob to queue
3. Job attempts confirmBooking with retry logic:
   - Attempt 1: immediate
   - Attempt 2: after 30 seconds
   - Attempt 3: after 2 minutes
   - Attempt 4: after 5 minutes
4. If all retries fail: flag for manual review + notify admin via WhatsApp
5. Idempotency: check if DotwBooking already has confirmation_code before confirming
```

```php
// Job with retry backoff
class ConfirmDotwBookingJob implements ShouldQueue
{
    public $tries = 4;
    public $backoff = [30, 120, 300]; // seconds

    public function handle()
    {
        $prebook = DotwPrebook::where('prebook_key', $this->prebookKey)->first();

        // Idempotency gate
        if ($prebook->confirmation_no) {
            return; // Already confirmed
        }

        // Re-block + confirm flow
        // ...
    }

    public function failed(Throwable $e)
    {
        // Notify admin, flag booking for manual resolution
        // Do NOT auto-refund -- admin may want to retry manually
    }
}
```

**Detection:** Monitor queue for ConfirmDotwBookingJob failures. Dashboard showing "paid but unconfirmed" bookings older than 10 minutes.

**Phase:** Must be addressed in Phase 1 (B2C Payment Flow). The job-based approach must be the default, not an afterthought.

---

### Pitfall 5: Module Isolation Violation -- New Code Mutates Existing Models

**What goes wrong:**
The new DotwAI module needs to create invoices, journal entries, tasks, and credit records. Developer adds methods to existing Invoice, JournalEntry, Task, and Credit models. Or worse, modifies existing controllers (PaymentController, InvoiceController) to add DOTW-specific logic. A bug in the new code breaks existing flight ticketing, visa processing, or accounting flows.

**Why it happens:**
- PROJECT.md specifies "app/Modules/DotwAI/ -- own namespace, does not modify existing code"
- But the existing models (Invoice, JournalEntry, Credit, Task) are shared infrastructure
- The temptation to "just add a method" to an existing model is strong
- PaymentController.php is already 5000+ lines with TBO booking logic embedded
- No architectural boundary enforces isolation

**Consequences:**
- Regression in existing accounting system (flight ticket invoices, refunds)
- AutoBilling command breaks because of unexpected model changes
- Payment gateway webhooks process differently for existing flows
- Debugging becomes harder when DOTW-specific code is scattered across existing files

**Prevention:**
```
Module Boundary Rules:

1. CONSUME existing models (read-only access) -- never ADD methods to them
2. Own models (DotwPrebook, DotwBooking, DotwRoom) live in app/Modules/DotwAI/Models/
3. Own services call existing services via their public API:
   - InvoiceController::generateInvoiceNumber() -- call, don't copy
   - Credit::create() -- use the existing model as-is
   - JournalEntry::create() -- use with existing fillable fields
4. Payment webhook integration: register a NEW route + controller, not modify PaymentController
5. Use Laravel events to bridge: dispatch BookingConfirmed event, let the module's listener
   handle DOTW-specific post-booking logic

Structural pattern:
app/Modules/DotwAI/
  Services/DotwBookingService.php      -- orchestrates booking flow
  Services/DotwAccountingBridge.php    -- creates invoices/journals via existing models
  Jobs/ConfirmDotwBookingJob.php       -- async booking confirmation
  Listeners/HandleDotwPaymentWebhook.php
  Events/DotwBookingConfirmed.php
```

**Detection:** Code review rule: any PR that modifies files outside app/Modules/DotwAI/ (except routes, config, and service provider registration) must have explicit justification.

**Phase:** Must be established in Phase 1 as an architectural constraint. Every subsequent phase builds on this boundary.

---

## High-Severity Pitfalls

Mistakes that cause significant user-facing issues or require substantial rework.

---

### Pitfall 6: Timezone Mismatch in Cancellation Deadline Reminders

**What goes wrong:**
DOTW cancellation rules return dates like `fromDate: "2026-04-15"` with no timezone. The system stores this as a date string. The cancellation deadline scheduler runs at midnight UTC. But the hotel is in Dubai (UTC+4). The "3 days before" reminder for an April 15 deadline fires on April 12 UTC, which is already April 12 in Dubai. For a Kuwait-based agency (UTC+3), the cancellation must be processed before midnight Kuwait time on April 14. If the scheduler calculates based on UTC, reminders may arrive too late or too early.

**Why it happens:**
- DOTW API returns dates without timezone information
- The existing SendReminders command uses `Carbon::now()` without explicit timezone
- The existing Kernel.php schedules jobs without timezone awareness (except RunAutoBilling which explicitly uses 'Asia/Kuala_Lumpur')
- Hotels, agencies, and customers may be in different timezones
- "3 days before" is ambiguous without specifying whose timezone

**Consequences:**
- Cancellation reminder arrives after the deadline has passed in the hotel's timezone
- Customer gets charged full cancellation fee despite receiving the reminder
- Agency must absorb the cancellation charge as a goodwill gesture
- Legal liability if the system promised timely reminders

**Prevention:**
```
Rules:
1. Store all cancellation deadlines as UTC timestamps, not bare dates
2. When DOTW returns "2026-04-15" as cancellation date, interpret as:
   - End of day (23:59:59) in the HOTEL's timezone
   - Convert to UTC for storage
3. Calculate reminder triggers from the UTC timestamp:
   - "3 days before" = UTC deadline minus 72 hours
   - "2 days before" = UTC deadline minus 48 hours
   - "1 day before"  = UTC deadline minus 24 hours
4. Display deadline to user in THEIR timezone (Kuwait time for agents)
5. Add safety margin: send the "1 day before" reminder 26 hours early, not 24

Configuration:
- Store hotel_timezone per booking (derive from hotel city/country)
- Reminder scheduler uses UTC exclusively
- WhatsApp message includes explicit timezone: "Deadline: April 14, 11:59 PM Kuwait time"
```

**Detection:** Log timezone of every cancellation deadline calculation. Alert if deadline_utc is in the past at reminder time.

**Phase:** Phase 2 (Lifecycle Automation). Must be designed before the first reminder is scheduled.

---

### Pitfall 7: WhatsApp Message Delivery Failures for Critical Reminders

**What goes wrong:**
The cancellation deadline is tomorrow. The system sends a "1 day before" reminder via WhatsApp. The Resayil API returns success (message queued). But the customer's phone is off, or WhatsApp servers are down, or the number is no longer on WhatsApp. The message is never delivered. Customer misses the deadline, gets charged full rate.

**Why it happens:**
- WhatsApp Cloud API uses at-least-once delivery with no guaranteed delivery confirmation
- The existing SendReminders command marks status as "sent" when the API call succeeds, not when the message is actually delivered
- No fallback channel (SMS, email) exists for critical reminders
- WhatsApp delivery receipts (blue ticks) are not tracked by the current ResayilController

**Consequences:**
- Customer blindsided by full cancellation charge
- "But I didn't receive any reminder!" -- agency bears the cost
- Erodes trust in the automated system

**Prevention:**
```
Multi-Channel Escalation Pattern:

Day 3 before deadline:
  1. Send WhatsApp reminder
  2. Log delivery attempt

Day 2 before deadline:
  1. Check if Day 3 WhatsApp was delivered (via delivery receipt webhook if available)
  2. Send WhatsApp reminder (regardless)
  3. If Day 3 was NOT delivered: also send Email

Day 1 before deadline:
  1. Send WhatsApp reminder
  2. ALWAYS send Email as backup
  3. ALWAYS send SMS as final fallback
  4. Notify the AGENT via WhatsApp: "Your client has a deadline tomorrow"

Implementation:
- Track delivery_status per reminder: queued -> sent -> delivered -> read
- Subscribe to Resayil/WhatsApp delivery status webhooks
- Do NOT rely solely on WhatsApp for time-critical notifications
```

**Detection:** Dashboard showing reminders with status="sent" but no delivery confirmation after 1 hour. Daily report of upcoming deadlines with undelivered reminders.

**Phase:** Phase 2 (Lifecycle Automation). The reminder system must be multi-channel from day one.

---

### Pitfall 8: Double-Booking via n8n Webhook Retry

**What goes wrong:**
n8n calls the confirm booking endpoint. Laravel processes the request and calls DOTW confirmBooking. DOTW confirms successfully. Laravel starts creating the Task, Invoice, and JournalEntry records. The response takes 8 seconds due to database operations. n8n's HTTP timeout (default 60s, but some configurations are lower) or WhatsApp webhook timeout causes a retry. n8n sends the same confirmBooking request again. Laravel processes it again. Now there are two DOTW bookings, two invoices, two journal entry sets for the same reservation.

**Why it happens:**
- n8n webhook responses have a 60-second timeout by default
- The existing DotwBooking model has no unique constraint on prebook_key (only created_at is set)
- confirmBooking does not check if a booking already exists for the given prebook_key
- DOTW may accept the same confirmBooking twice if the allocation is still valid
- The existing pattern in DotwBlockRates.php wraps prebook creation in DB::transaction, but there is no equivalent idempotency gate for booking confirmation

**Consequences:**
- Duplicate hotel bookings with DOTW (two bookingCodes for the same stay)
- Double charges to customer or credit line
- Double invoice and journal entries
- Manual cleanup required with DOTW support

**Prevention:**
```php
// Idempotency gate at the start of every booking confirmation
public function confirmBooking(Request $request): JsonResponse
{
    $prebookKey = $request->input('prebookKey');

    // Check if already confirmed -- return existing result
    $existing = DotwBooking::where('prebook_key', $prebookKey)
        ->where('booking_status', 'confirmed')
        ->first();

    if ($existing) {
        return response()->json([
            'success' => true,
            'already_confirmed' => true,
            'confirmation_code' => $existing->confirmation_code,
        ]);
    }

    // Use database advisory lock to prevent concurrent processing
    // of the same prebook_key
    $lockKey = "dotw_confirm_{$prebookKey}";
    $lock = Cache::lock($lockKey, 120); // 2-minute lock

    if (!$lock->get()) {
        return response()->json([
            'success' => false,
            'message' => 'Booking confirmation already in progress',
        ], 409);
    }

    try {
        // Proceed with confirmation...
    } finally {
        $lock->release();
    }
}
```

**Detection:** Query for duplicate DotwBooking rows with the same prebook_key. This should always return zero. Alert immediately if it returns non-zero.

**Phase:** Must be addressed in Phase 1. Every mutation endpoint (confirm, cancel) must have an idempotency gate.

---

### Pitfall 9: Currency Conversion Race Between Search and Booking

**What goes wrong:**
At 10:00 AM, the customer searches for a hotel. The exchange rate is 1 USD = 0.308 KWD. The system displays "45 KWD" to the customer. The customer decides to book at 10:15 AM. Between 10:00 and 10:15, the exchange rate was updated by the scheduled `UpdateExchangeRate` command. New rate: 1 USD = 0.312 KWD. The booking now calculates a different KWD amount than what was displayed. Customer sees a higher price than quoted.

**Why it happens:**
- The existing CurrencyExchangeTrait does a live lookup against the latest CurrencyExchange record
- Exchange rates are mutable (new rows inserted by the scheduled command)
- DotwPrebook stores `exchange_rate` and `total_fare` at search time, but the booking flow may recalculate
- DOTW returns prices in the requested currency, but the display currency may differ
- No price guarantee mechanism exists between search and booking

**Consequences:**
- Customer quoted one price, charged a different price
- Legal issues in some jurisdictions (advertised price must be honored)
- If the rate moves unfavorably for the agency: loss on the booking
- If the rate moves favorably: customer complaint about price change

**Prevention:**
```
Rules:
1. Lock the exchange rate at prebook time -- store it in DotwPrebook.exchange_rate
2. At booking time, use the STORED exchange rate, not a fresh lookup
3. DOTW confirmBooking uses original_total_fare (pre-conversion) -- this is already correct
4. The display price shown to customer must be the exact amount charged
5. If exchange rate has moved > 2% between search and booking:
   - Log a warning
   - Still honor the original quoted price
   - Absorb the difference as a cost variance

Code pattern:
// At search/block time:
$prebook->exchange_rate = $currentRate;
$prebook->total_fare = $convertedAndMarkedUpPrice;

// At booking time:
$chargeAmount = $prebook->total_fare;  // Use stored price, NOT recalculated
// Do NOT call CurrencyExchangeTrait again
```

**Detection:** Compare `prebook.total_fare` vs. what was actually charged. Alert on any mismatch.

**Phase:** Phase 1 (Booking Flow). The price displayed must equal the price charged, always.

---

### Pitfall 10: Auto-Invoice After Cancellation Deadline Creates Invoice for Cancelled Booking

**What goes wrong:**
The auto-invoice scheduler runs daily and checks: "Has the cancellation deadline passed? Is the booking still active? If yes, generate invoice." But between the last reminder and the auto-invoice check, the customer cancelled the booking through a different channel (called the hotel directly, or another agent cancelled via WhatsApp). The scheduler does not know about the external cancellation and generates an invoice anyway.

**Why it happens:**
- DOTW booking status is not automatically synced -- the system does not poll DOTW for status changes
- Cancellations done outside the system (phone, email to hotel) are invisible
- The auto-invoice check only looks at the internal booking status
- There is no "verify booking still active with DOTW" step before invoicing

**Consequences:**
- Invoice generated for a cancelled booking
- Customer disputes the invoice
- Accounting entries for non-existent revenue
- Manual reversal required (credit note, journal reversal)

**Prevention:**
```
Before auto-invoicing, verify booking status with DOTW:

1. Auto-invoice scheduler finds booking past deadline
2. Call DOTW getBookingDetails (or equivalent status check API)
3. If DOTW confirms booking is still active: generate invoice
4. If DOTW says booking is cancelled: update internal status, skip invoicing
5. If DOTW API is unreachable: defer invoicing, retry next cycle

Additionally:
- Implement a periodic booking status sync job (hourly)
- Store last_verified_at timestamp on each active booking
- Never auto-invoice if last_verified_at is older than 24 hours
```

**Detection:** After auto-invoicing, cross-reference invoice list with DOTW booking statuses. Alert on any mismatch.

**Phase:** Phase 2 (Lifecycle Automation). The auto-invoice feature must include verification.

---

## Moderate Pitfalls

Mistakes that cause degraded user experience or increased support load.

---

### Pitfall 11: DOTW API Timeout Cascading to WhatsApp User Experience

**What goes wrong:**
The DOTW searchHotels or getRooms call takes 15-25 seconds (documented behavior for large cities). The n8n AI agent tool has a timeout. The WhatsApp user waits, sees no response, sends the same search again. n8n triggers a second search. Now there are two concurrent DOTW API calls for the same user. Both may return results, leading to duplicate messages in the WhatsApp conversation.

**Why it happens:**
- DotwService has a 25-second timeout (config('dotw.request.timeout', 25))
- DOTW searchHotels for cities like Dubai can take 8+ seconds
- WhatsApp users expect near-instant responses (< 5 seconds)
- No "thinking" indicator is sent to the user while the search is processing
- The existing DotwBlockRates resolver handles timeouts with a clean error, but the search query may not

**Prevention:**
```
1. Send immediate acknowledgment: "Searching hotels in Dubai... this may take 15-20 seconds"
2. Implement search deduplication: hash (city + dates + occupancy + user)
   -> if identical search is in-flight, return "search already in progress"
3. Use n8n's "respond to webhook" to ack immediately, then "wait" node for DOTW response
4. Cache recent search results per user (5-minute TTL) to handle re-searches
5. For large cities: use hotelId batch search (50 per request, parallel) per best practices
```

**Phase:** Phase 1 (Search Flow). The UX of waiting is the first thing users encounter.

---

### Pitfall 12: Allocation Details Token Corruption

**What goes wrong:**
The `allocationDetails` string from DOTW getRooms(blocking) must be passed exactly as received to confirmBooking. Any encoding, trimming, HTML-escaping, or character substitution corrupts it. The booking fails with a cryptic DOTW error.

**Why it happens:**
- GraphQL layers may HTML-encode special characters in the allocation string
- JSON encoding/decoding can modify whitespace or escape sequences
- Database text columns may truncate long strings
- Developers add `trim()` or `htmlspecialchars()` as "safety measures"
- The existing DotwBlockRates.php already has a comment about this: "raw token -- not encoded (Pitfall 1)"

**Consequences:**
- confirmBooking fails consistently for certain hotels
- Difficult to debug because the token looks similar but is subtly different
- Rate is wasted (3-minute allocation consumed but booking fails)

**Prevention:**
```
Rules (already partially in place, must be enforced throughout):
1. Store allocation_details as TEXT column (not VARCHAR) -- no truncation risk
2. Never trim(), htmlspecialchars(), or urlencode() the token
3. When passing through GraphQL: use raw String type, not escaped
4. Test with allocation tokens containing special characters: &, <, >, ", '
5. Add a hash validation: store MD5 of original token, verify before confirmBooking

// The existing DotwPrebook correctly stores it in a text column.
// The risk is in the NEW booking confirmation flow where it's read back and sent to DOTW.
```

**Detection:** Log the first 20 and last 20 characters of allocation_details at storage and retrieval. Compare in debug mode.

**Phase:** Phase 1 (Booking Flow). Already partially mitigated by existing code, but must be tested end-to-end.

---

### Pitfall 13: Minimum Selling Price (MSP) Violation in B2C Track

**What goes wrong:**
The DOTW API returns `<MinimumSelling>` for rates on direct hotel chain connections. The B2C markup calculation produces a price below the MSP. For example: DOTW rate is 100 USD, MSP is 120 USD, but your 20% markup on 100 yields 120 exactly. After currency conversion rounding, the display price becomes 119.997, which rounds down to 119 -- below MSP. DOTW may reject the booking or flag the distribution for compliance violation.

**Why it happens:**
- The existing DotwBlockRates.php applies markup via `$dotwService->applyMarkup()` but does not check against MSP
- Currency conversion rounding can push prices below MSP boundaries
- ceil() is mentioned in the SKILL.md for B2C pricing, but it must be applied AFTER MSP comparison
- Not all rates have MSP -- developers may skip the check entirely

**Prevention:**
```php
// After markup calculation, enforce MSP floor
$markedUpPrice = $originalFare * (1 + $markupPercent);
$mspPrice = $rate['minimumSellingPrice'] ?? 0;

$displayPrice = max($markedUpPrice, $mspPrice);
$displayPrice = ceil($displayPrice); // Always round UP, never down
```

**Detection:** Log when MSP floor is hit. If it happens frequently, the markup percentage may be too low.

**Phase:** Phase 1 (Search/Pricing). Must be part of the initial pricing logic.

---

### Pitfall 14: Hotel Static Data Cache Staleness

**What goes wrong:**
The system caches DOTW hotel/city/country data via weekly sync jobs (already scheduled in Kernel.php). A hotel is added to DOTW's inventory on Monday. The next sync runs Sunday. For 6 days, searches for that hotel return no results. Conversely, a hotel is removed from DOTW inventory, but the cache still shows it. User selects it, searchHotels returns nothing.

**Why it happens:**
- Existing schedule: countries weekly (Sunday), cities weekly (Monday), hotels daily
- Hotels sync daily, but cities/countries are weekly -- a new city may not be searchable for up to a week
- DOTW static data can change without notification
- No mechanism to detect and handle "hotel in cache but not in DOTW" gracefully

**Prevention:**
```
1. Hotels: keep daily sync (already configured)
2. Cities: increase to daily if DOTW rate limits allow; otherwise, bi-weekly
3. Graceful degradation: if cached hotel returns no search results,
   try searching by city + hotel name (fuzzy match)
4. Add a "last_verified" timestamp to cached hotels
5. When getRooms returns "hotel not found": mark hotel as inactive in cache,
   return user-friendly message instead of error
6. Provide admin tool to force-sync specific cities/hotels on demand
```

**Phase:** Phase 1 (Static Data Foundation). The sync schedule is already in place; this is about handling edge cases.

---

### Pitfall 15: APR (Advance Purchase Rate) Booking Treated as Refundable

**What goes wrong:**
An APR rate is booked. The `is_refundable` flag is stored as `false` and `is_apr` flag as `true` in the prebook. But the cancellation flow does not check these flags. Customer requests cancellation via WhatsApp. The n8n agent calls the cancel endpoint. The system attempts to cancel with DOTW. DOTW rejects it (APR cannot be cancelled). But the system has already started processing internal cancellation (status change, credit release).

**Why it happens:**
- The SKILL.md documents this: "If the prebook is APR (is_apr=true), block cancellation entirely"
- But the enforcement may only be in the DOTW API response, not in the Laravel validation layer
- n8n AI agent may not check is_apr before suggesting cancellation to the user
- The existing code has `is_refundable` but the cancellation endpoint may not check it pre-emptively

**Prevention:**
```php
// In cancellation endpoint -- check BEFORE calling DOTW
public function cancelBooking(Request $request): JsonResponse
{
    $prebook = DotwPrebook::where('prebook_key', $request->prebookKey)->first();

    if ($prebook->booking_details['is_apr'] ?? false) {
        return response()->json([
            'success' => false,
            'message' => 'This is a non-refundable Advance Purchase Rate. Cancellation and amendments are not permitted.',
            'error_code' => 'APR_NON_CANCELLABLE',
        ], 422);
    }

    // Also check cancelRestricted periods
    // ...
}
```

**Detection:** Log any attempt to cancel an APR booking. This should trigger an immediate response, not a DOTW API call.

**Phase:** Phase 1 (Cancellation Flow). Must be enforced at the application layer, not just relying on DOTW rejection.

---

### Pitfall 16: Existing Global Scope on JournalEntry Breaks Module Queries

**What goes wrong:**
The existing JournalEntry model has a global scope that filters by `auth()->user()->company->id`. When the DotwAI module creates journal entries from a queue job (no authenticated user) or from a webhook (API token auth without company context), the global scope either throws an error or silently filters out all entries.

**Why it happens:**
- JournalEntry has `addGlobalScope('company', ...)` that requires `auth()->check()` and `auth()->user()->company`
- Queue jobs run without authenticated users
- API webhooks authenticate differently than web sessions
- The DotwAI module's accounting bridge will create and query journal entries

**Consequences:**
- Journal entries created without company_id filtering may be visible to all companies (data leak)
- Journal entry queries from queue jobs return empty results
- Accounting reports show inconsistent data

**Prevention:**
```php
// When creating journal entries from the module:
// 1. Always explicitly set company_id (don't rely on auth scope)
// 2. Use withoutGlobalScope when querying from non-authenticated contexts

JournalEntry::withoutGlobalScope('company')
    ->where('company_id', $booking->company_id)
    ->where('task_id', $task->id)
    ->get();

// Better: create a service method that handles scope correctly
class DotwAccountingBridge
{
    public function createJournalEntries(int $companyId, array $entries): void
    {
        foreach ($entries as $entry) {
            JournalEntry::create(array_merge($entry, [
                'company_id' => $companyId,  // Always explicit
            ]));
        }
    }
}
```

**Detection:** Unit test: create journal entry from a queue job context (no auth). Verify it is saved with correct company_id. Verify it is queryable with explicit company filter.

**Phase:** Phase 1 (Module Foundation). Must be understood before any accounting integration code is written.

---

## Minor Pitfalls

Issues that cause minor friction or technical debt.

---

### Pitfall 17: Passenger Name Sanitization Edge Cases

**What goes wrong:**
DOTW requires passenger names to be 2-25 characters with no spaces, numbers, or special characters. Arabic names, hyphenated names (Jean-Pierre), and names with prefixes (Al Rasheed) need sanitization. But aggressive sanitization can produce invalid results: "Li" becomes valid, but "O" (single character after trimming prefix) fails the 2-character minimum.

**Prevention:**
- Sanitize step-by-step: remove spaces, remove special chars, check length, pad if < 2 chars
- Test with: Arabic names, single-word names, very long names (> 25 chars), names with diacritics
- The best-practices.md already documents the rules: 2-25 chars, no whitespace/numbers/special chars

**Phase:** Phase 1 (Booking Flow). Implement as a dedicated `DotwPassengerNameSanitizer` utility.

---

### Pitfall 18: n8n AI Agent Tool Response Format Mismatch

**What goes wrong:**
The n8n AI agent expects a specific JSON structure from the GraphQL/REST tools. If the response format changes (new fields, renamed fields, null vs missing), the AI agent's prompt may fail to extract the right data. The WhatsApp message to the user contains raw JSON or garbled data.

**Prevention:**
- Document the exact response contract for each n8n tool
- Version the response format (include `format_version: "1.0"` in every response)
- Use GraphQL schema types to enforce structure
- Test n8n AI agent end-to-end when changing any response field

**Phase:** Ongoing concern across all phases. Document response contracts in Phase 1.

---

### Pitfall 19: Cancellation Charge Value vs Formatted Value Confusion

**What goes wrong:**
The DOTW cancellation response has both `<charge>` (numeric) and `<formatted>` (human-readable with currency symbol). If the code uses `<formatted>` instead of `<charge>` as the `penaltyApplied` value, DOTW rejects the second cancellation call.

**Prevention:**
- The best-practices.md already warns: "Use the `<charge>` value, NOT the `<formatted>` tag value"
- Enforce in code: `$penalty = (float) $response['charge']` -- never parse from formatted string
- Add validation: penaltyApplied must be numeric

**Phase:** Phase 1 (Cancellation Flow). Already documented as a certification requirement.

---

### Pitfall 20: Company DOTW Credentials Missing for New Companies

**What goes wrong:**
A new company is onboarded to the platform. Admin forgets to add DOTW credentials in the `company_dotw_credentials` table. Agents from that company try to search hotels. DotwService constructor throws RuntimeException("DOTW credentials not configured"). The error message surfaces as a generic "error" in WhatsApp, confusing the agent.

**Prevention:**
- Check credentials at company onboarding time
- Return a human-readable message to n8n: "DOTW hotel search is not configured for your company. Please contact your administrator."
- The existing DotwBlockRates.php already has this check with a RECONFIGURE_CREDENTIALS action code
- Ensure all DOTW entry points (search, getRooms, block, confirm, cancel) have this guard

**Phase:** Phase 1 (Guard on all endpoints). Already partially implemented.

---

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Severity | Mitigation |
|-------------|---------------|----------|------------|
| Search Flow | DOTW timeout cascading to duplicate searches (Pitfall 11) | High | Dedup + acknowledgment message |
| Search Flow | Static data staleness (Pitfall 14) | Moderate | Graceful degradation + verify on miss |
| Rate Blocking | Allocation token corruption (Pitfall 12) | High | No-encoding rule + hash verification |
| B2C Payment | Rate expiry before payment completes (Pitfall 1) | Critical | Re-block pattern after payment |
| B2C Payment | Payment webhook + DOTW API down (Pitfall 4) | Critical | Queue-based async confirmation |
| B2B Credit | Concurrent credit deduction (Pitfall 2) | Critical | Pessimistic locking in transaction |
| Booking Confirm | Double-booking via retry (Pitfall 8) | High | Idempotency gate + advisory lock |
| Booking Confirm | Wrong price charged after rate change (Pitfall 9) | High | Lock exchange rate at prebook time |
| Cancellation | APR booking treated as refundable (Pitfall 15) | Moderate | Pre-check before DOTW API call |
| Cancellation | Charge vs formatted value (Pitfall 19) | Moderate | Already documented in certification |
| Reminders | Timezone mismatch (Pitfall 6) | High | UTC storage + hotel timezone awareness |
| Reminders | Delivery failure for critical notifications (Pitfall 7) | High | Multi-channel escalation |
| Auto-Invoice | Invoice for cancelled booking (Pitfall 10) | High | Verify with DOTW before invoicing |
| Accounting | Journal entries before confirmation (Pitfall 3) | Critical | "No journal until money moves" rule |
| Accounting | Global scope breaks module queries (Pitfall 16) | Moderate | Explicit company_id, withoutGlobalScope |
| Module Architecture | Existing code mutation (Pitfall 5) | Critical | Strict module boundary enforcement |
| Module Architecture | n8n response format drift (Pitfall 18) | Minor | Versioned response contracts |
| Pricing | MSP violation in B2C (Pitfall 13) | Moderate | max(markedUp, MSP) + ceil() |
| Passenger Data | Name sanitization edge cases (Pitfall 17) | Minor | Dedicated sanitizer utility |
| Company Setup | Missing DOTW credentials (Pitfall 20) | Minor | Human-readable error + onboarding check |

---

## Integration Risk Matrix

These pitfalls specifically arise from ADDING this module to an existing system (not greenfield risks):

| Existing Component | Risk When Integrating | Pitfall # |
|--------------------|----------------------|-----------|
| PaymentController (5000+ lines) | Adding DOTW payment processing to an already complex file | 5 |
| Credit model (no locking) | Concurrent booking drains credit beyond limit | 2 |
| JournalEntry (global scope) | Queue jobs/webhooks fail silently or leak data | 16 |
| CurrencyExchangeTrait (live lookup) | Price changes between quote and charge | 9 |
| SendReminders command | Timezone-unaware scheduling | 6 |
| RunAutoBilling command | Pattern of journal-before-confirmation may be copied | 3 |
| InvoiceSequence (lockForUpdate) | Existing locking pattern is good -- use it, don't bypass | 2, 8 |
| ResayilController | WhatsApp delivery not verified | 7 |
| Kernel.php schedule | New scheduled jobs may conflict with existing ones | 6, 10 |
| DotwService (multi-tenant) | Already handles per-company credentials correctly | 20 |

---

## Sources

- Codebase analysis: `app/Models/Credit.php`, `app/Models/JournalEntry.php`, `app/Models/DotwPrebook.php`, `app/Models/DotwBooking.php`, `app/GraphQL/Mutations/DotwBlockRates.php`, `app/Services/DotwService.php`, `app/Http/Controllers/PaymentController.php`, `app/Console/Commands/SendReminders.php`, `app/Console/Commands/RunAutoBilling.php`, `app/Console/Kernel.php`
- DOTW best practices: `.claude/skills/dotw-api/references/best-practices.md` (HIGH confidence -- direct project documentation)
- DOTW AI skill: `.claude/skills/dotwai/SKILL.md` (HIGH confidence -- direct project documentation)
- Project context: `.planning/PROJECT.md` (HIGH confidence -- project specification)
- [Hotel cancellation timezone issues](https://www.elliott.org/blog/cancellation-deadline-hotel-local-time/) (MEDIUM confidence -- industry reference)
- [WhatsApp webhook silent failures](https://medium.com/@siri.prasad/the-shadow-delivery-mystery-why-your-whatsapp-cloud-api-webhooks-silently-fail-and-how-to-fix-2c7383fec59f) (MEDIUM confidence -- verified against WhatsApp docs)
- [WhatsApp webhook best practices](https://hookdeck.com/webhooks/platforms/guide-to-whatsapp-webhooks-features-and-best-practices) (MEDIUM confidence)
- [Laravel pessimistic locking for data races](https://laravel-news.com/managing-data-races-with-pessimistic-locking-in-laravel) (HIGH confidence -- Laravel official source)
- [Laravel credit system with ledger pattern](https://www.blog.brightcoding.dev/2025/12/31/build-bulletproof-credit-systems-in-laravel-the-complete-ledger-based-developer-guide-2025/) (MEDIUM confidence)
- [n8n idempotent webhook retries](https://medium.com/@Modexa/idempotent-webhook-retries-in-n8n-without-duplicates-8380273a95a2) (MEDIUM confidence)
- [n8n webhook timeout limitations](https://community.n8n.io/t/respond-to-webhook-didnt-work-when-use-more-than-64-seconds-in-wait-node/80495) (MEDIUM confidence -- community source)
- [Handling concurrency in Laravel](https://medium.com/@developerawam/handling-concurrency-in-laravel-preventing-race-conditions-and-double-submits-cae46909b8ac) (MEDIUM confidence)
