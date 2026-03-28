---
phase: 23-agent-facade-n8n-workflow
plan: 01
subsystem: DotwAI v2.0
tags:
  - agent-facade
  - n8n-integration
  - session-management
  - unified-endpoints
dependency_graph:
  requires:
    - Phase 18 (SearchController, HotelSearchService)
    - Phase 19 (BookingService, PaymentBridgeService)
    - Phase 20 (CancellationService)
    - Phase 21 (VoucherService, MessageBuilderService)
  provides:
    - AgentSessionService
    - AgentController
    - Two unified endpoints for n8n AI agents
  affects:
    - Phase 23-02 (webhook events integration)
    - Phase 24+ (DOTW certification fixes)
tech_stack:
  added:
    - AgentSessionService (per-phone session state management)
    - Cache-based session storage (60-min TTL)
  patterns:
    - PHP match() for action routing
    - Service injection via constructor
    - DotwAIResponse envelope for all responses
    - sessionContext in every response (success + error)
key_files:
  created:
    - app/Modules/DotwAI/Services/AgentSessionService.php (109 lines)
    - app/Modules/DotwAI/Http/Controllers/AgentController.php (645 lines)
    - app/Modules/DotwAI/Http/Requests/AgentRequest.php (39 lines)
  modified:
    - app/Modules/DotwAI/Services/DotwAIResponse.php (added SEARCH_EXPIRED, SESSION_EMPTY)
    - app/Modules/DotwAI/Routes/api.php (registered agent-b2c and agent-b2b routes)
decisions:
  - Session storage: Cache (in-memory) with 60-min TTL per phone number
  - Expiry validation: search>600s returns SEARCH_EXPIRED, prebook>30min returns PREBOOK_EXPIRED
  - Response format: Every response (success+error) includes sessionContext {stage, summary, next_actions}
  - Action routing: PHP match() statement with 8 branches (search, details, book, pay, cancel, status, history, voucher)
  - Error handling: Errors with sessionContext injected into response JSON
---

# Phase 23 Plan 01: Agent Facade for n8n — COMPLETE

## Summary

Built the unified agent facade: two POST endpoints (/api/dotwai/agent-b2c and /api/dotwai/agent-b2b) that accept {action, params, telephone}, manage per-phone session state in Cache, route to existing services, and return every response enriched with sessionContext.

**One-liner:** Unified n8n interface with action routing, per-phone session state, DOTW expiry validation, and sessionContext in every response.

## Objective

Enable n8n AI agents to orchestrate hotel booking workflows through a single unified endpoint that:
1. Routes 8 actions (search, details, book, pay, cancel, status, history, voucher) via PHP match()
2. Maintains per-phone session state in Cache with 60-min TTL
3. Validates DOTW time constraints (search 10-min, prebook 30-min)
4. Returns every response with sessionContext (stage, summary, next_actions) so AI can understand journey state

## Tasks Completed

### Task 1: AgentSessionService + Error Codes

**Status:** COMPLETE

Created app/Modules/DotwAI/Services/AgentSessionService.php with:
- `getSession(phone)` — retrieves session from Cache::get("dotwai_session_{phone}")
- `saveSession(phone, data)` — persists with 60-min TTL
- `clearSession(phone)` — erases on logout/expiry
- `isSearchExpired(phone)` — returns true if search_cached_at > 600 seconds old
- `isPrebookExpired(phone)` — returns true if prebook_expires_at is in the past
- `getStageContext(session)` — maps session['stage'] to {stage, summary, next_actions} for every response

Added two new error codes to DotwAIResponse:
- `SEARCH_EXPIRED` — "انتهت صلاحية نتائج البحث..." (10-min search cache TTL exceeded)
- `SESSION_EMPTY` — "لا توجد جلسة نشطة..." (no active session for action requiring state)

Both include bilingual Arabic/English whatsappMessage and suggestedAction defaults.

**Acceptance Criteria Met:**
- File created with all 6 methods + stage context derivation
- Both error codes added to DotwAIResponse with bilingual defaults
- Cache key pattern: "dotwai_session_{phone}"
- TTL constants: 600 seconds (search), 30 minutes (prebook), 60 minutes (session)
- All 7 journey stages supported: idle, searching, viewing_details, prebooked, awaiting_payment, confirmed, cancelling

**Commits:**
- fa1d34b0: feat(23-01): create AgentSessionService and add SEARCH_EXPIRED, SESSION_EMPTY error codes

---

### Task 2: AgentController + AgentRequest + Route Registration

**Status:** COMPLETE

Created app/Modules/DotwAI/Http/Requests/AgentRequest.php with:
- Validates `telephone` (required string)
- Validates `action` (required, in: search,details,book,pay,cancel,status,history,voucher)
- Validates `params` (nullable array — each action validates its own required fields)

Created app/Modules/DotwAI/Http/Controllers/AgentController.php (645 lines) with:
- Single public method: `handle(AgentRequest $request): JsonResponse`
- Constructor injects: AgentSessionService, HotelSearchService, BookingService, CancellationService, PaymentBridgeService
- VoucherService instantiated directly (per existing pattern)
- Loads session per phone via `$this->sessionService->getSession($phone)`
- Routes via PHP match($action) to 8 private handler methods

**Action Handlers:**

1. **search** — Validates params (city, check_in, check_out, occupancy), calls HotelSearchService::searchHotels(), saves to session with search_cached_at, returns with sessionContext
2. **details** — Requires active session, checks search not expired, resolves hotel from cached search using option number, calls getHotelDetails(), updates session to viewing_details
3. **book** — Requires active session, checks search not expired, calls BookingService::prebook(), updates session with prebook_key + 30-min expiry
4. **pay** — Requires prebook_key in session, checks prebook not expired (else PREBOOK_EXPIRED + reset stage), finds booking, calls PaymentBridgeService::createPaymentLink(), updates stage to awaiting_payment
5. **cancel** — Gets prebook_key from session or params, calls CancellationService::cancel(), updates session based on confirm step (yes→idle, no→cancelling)
6. **status** — Gets prebook_key from session or params, finds booking, returns formatted status with sessionContext
7. **history** — Paginates bookings for phone, returns formatted history with sessionContext
8. **voucher** — Gets prebook_key from session or params, finds confirmed booking, calls VoucherService::resendVoucher(), returns confirmation

**All Responses:**
- Success responses: data + whatsappMessage + whatsappOptions + sessionContext (as top-level key)
- Error responses: error{code, message, suggestedAction} + whatsappMessage + sessionContext (injected into response JSON)
- Logging: Every action logged to dotw channel with phone, action, stage

Updated app/Modules/DotwAI/Routes/api.php:
- Added use statement for AgentController
- Registered two routes inside dotwai.resolve middleware group:
  - POST /api/dotwai/agent-b2c → AgentController@handle
  - POST /api/dotwai/agent-b2b → AgentController@handle

**Acceptance Criteria Met:**
- Both routes registered with dotwai.resolve middleware ✓
- AgentController::handle() routes all 8 actions ✓
- Session persisted in Cache with 60-min TTL ✓
- DOTW expiry validation: search>10min=SEARCH_EXPIRED, prebook>30min=PREBOOK_EXPIRED ✓
- Every response includes sessionContext ✓
- AI can send {action: "details", params: {option: 2}} without context ✓

**Routes Verification:**
```
POST   api/dotwai/agent-b2b   App\Modules\DotwAI\Http\Controllers\AgentController@handle
POST   api/dotwai/agent-b2c   App\Modules\DotwAI\Http\Controllers\AgentController@handle
```

**Commits:**
- 904d070f: feat(23-01): build unified agent facade with AgentController and routes

---

## Verification

All files created and routes registered:
- app/Modules/DotwAI/Services/AgentSessionService.php ✓
- app/Modules/DotwAI/Http/Requests/AgentRequest.php ✓
- app/Modules/DotwAI/Http/Controllers/AgentController.php ✓
- app/Modules/DotwAI/Services/DotwAIResponse.php (updated) ✓
- app/Modules/DotwAI/Routes/api.php (updated) ✓

Route listing verified:
```
php artisan route:list --path=dotwai/agent
```
Output confirms both agent-b2b and agent-b2c routes mapped to AgentController@handle.

Syntax validation passed on all PHP files:
```
php -l app/Modules/DotwAI/Services/AgentSessionService.php
php -l app/Modules/DotwAI/Http/Requests/AgentRequest.php
php -l app/Modules/DotwAI/Http/Controllers/AgentController.php
php -l app/Modules/DotwAI/Routes/api.php
```

---

## Next Steps

Phase 23 Plan 02 will:
- Add webhook event dispatch integration (payment_completed, reminder_due, deadline_passed, booking_confirmed)
- Integrate WebhookDispatchJob for fire-and-forget events
- Connect lifecycle state changes to webhook pushes
- Test end-to-end n8n workflows

Phase 24 will address DOTW certification fixes (Olga March 27 feedback).

---

## Known Stubs

None. All required functionality implemented.

---

## Deviations from Plan

None — plan executed exactly as written.

---

## Self-Check

**Created Files Verified:**
- ✓ app/Modules/DotwAI/Services/AgentSessionService.php exists (109 lines)
- ✓ app/Modules/DotwAI/Http/Requests/AgentRequest.php exists (39 lines)
- ✓ app/Modules/DotwAI/Http/Controllers/AgentController.php exists (645 lines)

**Modified Files Verified:**
- ✓ app/Modules/DotwAI/Services/DotwAIResponse.php contains SEARCH_EXPIRED and SESSION_EMPTY
- ✓ app/Modules/DotwAI/Routes/api.php contains both agent routes

**Commits Verified:**
- ✓ fa1d34b0: feat(23-01): create AgentSessionService and add SEARCH_EXPIRED, SESSION_EMPTY error codes
- ✓ 904d070f: feat(23-01): build unified agent facade with AgentController and routes

**Routes Verified:**
- ✓ POST /api/dotwai/agent-b2c → AgentController@handle
- ✓ POST /api/dotwai/agent-b2b → AgentController@handle

---

## Self-Check: PASSED

All files exist, commits verified, routes registered, syntax valid.
