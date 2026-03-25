# Phase 19: B2B + B2C Booking - Context

**Gathered:** 2026-03-24
**Status:** Ready for planning
**Source:** Milestone v2.0 discussion + /dotwai skill + memory

<domain>
## Phase Boundary

Build the complete booking pipeline for both B2B and B2C tracks. After this phase: agents can prebook, confirm, and receive vouchers through WhatsApp. B2B agents with credit line book directly; without credit line they pay via gateway first. B2C customers pay upfront via MyFatoorah/KNET payment link, then system auto-confirms after payment.

Requirements: B2B-01 through B2B-07, B2C-01 through B2C-05 (12 total)

Depends on: Phase 18 (module foundation, search endpoints, phone resolution, DotwAIResponse envelope)

</domain>

<decisions>
## Implementation Decisions

### B2B Track (agent-facing, WhatsApp-only)
- Agent with credit line: prebook → confirm immediately → deduct credit → send voucher via WhatsApp
- Agent without credit line: prebook → generate payment link → send via WhatsApp → after payment webhook → confirm with DOTW → send voucher
- Credit deduction uses pessimistic locking (DB::transaction + lockForUpdate) to prevent concurrent overdraw
- get_company_balance endpoint returns credit_limit, used, available
- 0% markup by default (configurable per company)
- B2B enabled/disabled per company via CompanyDotwCredential.b2b_enabled column (from Phase 18)

### B2C Track (customer-facing)
- Customer searches → prebook → payment link sent via WhatsApp → customer pays → system auto-confirms
- CRITICAL: DOTW rate allocation expires in 3 minutes. Payment takes longer. Solution: RE-BLOCK after payment webhook received, then confirm. If re-block fails (rate gone), refund payment and notify customer.
- Configurable markup per company (default 20%) — already applied in search results (Phase 18 HotelSearchService)
- MSP enforced: selling price >= DOTW totalMinimumSelling
- After confirmation: auto-create invoice + task + send voucher via WhatsApp
- B2C enabled/disabled per company via CompanyDotwCredential.b2c_enabled column

### Prebook Endpoint
- POST /api/dotwai/prebook — accepts phone, hotelId (or optionNumber from cached search), roomTypeCode, rateBasisId, checkIn, checkOut, occupancy
- Calls DotwService::getRooms(blocking: true) to lock the rate
- Creates DotwAIBooking record with prebookKey (UUID), status=prebooked, expires_at
- Returns: prebookKey, total price, cancellation policy, expiry countdown
- WhatsApp-formatted response with booking summary

### Confirm Endpoint
- POST /api/dotwai/book — accepts phone, prebookKey, passengers array, specialRequests
- Validates prebookKey not expired
- B2B with credit: calls DotwService::confirmBooking directly, deducts credit
- B2B no credit: only confirms if payment received (checked via prebookKey → payment record)
- B2C: only confirms after payment webhook (handled by payment callback, not this endpoint directly)
- Creates booking record, sends voucher via WhatsApp
- Returns: bookingCode, confirmation details, voucher

### Payment Link Endpoint
- POST /api/dotwai/payment-link — accepts phone, prebookKey, amount, currency
- Generates MyFatoorah/KNET payment link using existing PaymentController/gateway classes
- Stores payment reference linked to prebookKey
- Returns: payment URL, expiry
- WhatsApp message with clickable payment link

### Payment Webhook Handler
- POST /api/dotwai/payment-callback — receives payment gateway webhook
- Verifies payment success
- Re-blocks the rate with DOTW (getRooms blocking)
- If re-block succeeds: confirm booking, create invoice/task, send voucher
- If re-block fails: refund payment, notify customer "rate no longer available"
- All async — queued job for DOTW confirmation

### Company Balance Endpoint
- GET /api/dotwai/balance — accepts phone
- Returns: credit_limit, used_credit, available_credit, recent_transactions
- WhatsApp-formatted balance summary

### Voucher Delivery
- After booking confirmed, send voucher via WhatsApp (Resayil API)
- Include: hotel name, dates, booking reference, guest names, payment status
- paymentGuaranteedBy from DOTW confirmBooking response included on voucher

### Claude's Discretion
- DotwAIBooking model structure (new model or extend existing DotwPrebook)
- Queue job vs synchronous for payment callback → DOTW confirmation
- Payment link generation method (which gateway class method to call)
- Voucher format (text message vs PDF attachment)
- How to handle partial payment or payment timeout
- Error recovery: what if DOTW confirm fails after payment received

</decisions>

<canonical_refs>
## Canonical References

### Phase 18 Foundation (built, use directly)
- `app/Modules/DotwAI/Providers/DotwAIServiceProvider.php` — Module bootstrap
- `app/Modules/DotwAI/Services/DotwAIResponse.php` — Response envelope (whatsappMessage)
- `app/Modules/DotwAI/Services/PhoneResolverService.php` — Phone → company resolution
- `app/Modules/DotwAI/Services/HotelSearchService.php` — Search with caching
- `app/Modules/DotwAI/Services/MessageBuilderService.php` — WhatsApp formatting
- `app/Modules/DotwAI/DTOs/DotwAIContext.php` — Request context with B2B/B2C flags
- `app/Modules/DotwAI/Http/Middleware/ResolveDotwAIContext.php` — Middleware
- `app/Modules/DotwAI/Routes/api.php` — Existing routes to extend
- `app/Modules/DotwAI/Config/dotwai.php` — Module config

### Existing Services (wrap, don't modify)
- `app/Services/DotwService.php` — DOTW XML API (getRooms blocking, confirmBooking, saveBooking, bookItinerary)
- `app/Support/PaymentGateway/MyFatoorah.php` — Payment gateway
- `app/Http/Controllers/PaymentController.php` — Payment orchestration (5000+ lines)
- `app/Services/GatewayConfigService.php` — Gateway config resolution
- `app/Models/Credit.php` — Company credit model (INVOICE, TOPUP, REFUND types)
- `app/Services/PaymentApplicationService.php` — Credit application (full/partial/split)

### Skills
- `.claude/skills/dotwai/references/laravel-files.md` — Blueprint for booking controller
- `.claude/skills/dotwai/references/n8n-tools.md` — Tool definitions for prebook, book, payment-link, balance
- `.claude/skills/dotw-api/references/api-methods.md` — DOTW XML templates for confirmBooking, saveBooking

### Research
- `.planning/research/PITFALLS.md` — Rate expiry timing (Pitfall 1), credit locking (Pitfall 2), accounting timing (Pitfall 3)
- `.planning/research/ARCHITECTURE.md` — Payment flow, re-block pattern

</canonical_refs>

<specifics>
## Specific Ideas

- Re-block pattern is the most critical architectural decision — if payment takes 5 minutes but DOTW allocation expires in 3, we MUST re-block after payment before confirming
- Credit model uses lockForUpdate() inside DB::transaction — see Pitfall 2 in research
- Payment callback should be a queued job (ConfirmBookingAfterPayment) since DOTW API takes up to 25s
- For APR rates (nonrefundable), use saveBooking + bookItinerary flow (Flow B) instead of confirmBooking
- Voucher should include paymentGuaranteedBy from DOTW response (certification requirement)

</specifics>

<deferred>
## Deferred Ideas

- Cancellation flow (Phase 20)
- Accounting journal entries (Phase 20)
- Auto-reminders and lifecycle (Phase 21)
- Booking history and resend voucher (Phase 21)
- Dashboard monitoring (Phase 22)

</deferred>

---

*Phase: 19-b2b-b2c-booking*
*Context gathered: 2026-03-24 via milestone discussion*
