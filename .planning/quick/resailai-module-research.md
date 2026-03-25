# ResailAI Module — Complete Research & Architecture

**Research Date:** 2026-03-10
**Status:** RESEARCH COMPLETE — Ready for GSD project setup
**Agents Used:** 10+ Haiku agents across 3 rounds

---

## 1. Overview

Self-contained Laravel module that handles automated document processing for ANY supplier and ANY task type (flight, hotel, visa, insurance). Documents are uploaded from Laravel, sent to ResailAI extraction service, and results feed back into the existing TaskWebhook pipeline to create tasks automatically.

**Branding:** ResailAI (external processing service is hidden from clients)

---

## 2. Supplier Matrix

| Supplier | ID | Task Type | File Format | Current Processing | Special Rules |
|---|---|---|---|---|---|
| Jazeera Airways | 1 | Flight | AIR | AirFileParser (regex) | Status mapping (confirmed→issued), expired task auto-void (48h), task rules (minus_existing) |
| FlyDubai | 2 | Flight | AIR + PDF | AirFileParser / AI | IATA wallet (issued_by=KWIKT211N), status mapping |
| Smile Holidays | Not seeded | Hotel | PDF (batch merge 2+ files) | AI extraction | Merge prefix SMIL, pax in additional_info, proforma=issued/voucher=confirmed |
| VFS | Not seeded | Visa | PDF | AI extraction | Status mapping (confirmed→issued), 6 visa detail fields |
| First Takaful | Not seeded | Insurance | PDF | AI extraction | 1 task per policy (NOT per person), 8 insurance detail fields |

### Supplier-Specific Rules in TaskWebhook

- **Status mapping** for Jazeera/FlyDubai/VFS: confirmed→issued, on hold→confirmed
- **IATA wallet** only for supplier_id=2 (Amadeus) and NDC (29, 38, 39)
- **Magic Holiday**: requires client_ref, auto-invoice
- **TBO Holiday**: requires booking_reference, auto-invoice
- **Non-Amadeus suppliers**: gds_reference, airline_reference, created_by, issued_by cleared

---

## 3. TaskWebhook Data Contract (ALL Types)

### Required for ALL types
- reference (string, required)
- status (string, required)
- company_id (integer, required)
- type (string, in: flight/hotel/insurance/visa)

### Type-Specific Detail Arrays

**Flight (task_flight_details[]):** 18 required fields — is_ancillary, farebase, departure_time, country_id_from, airport_from, terminal_from, arrival_time, duration_time, country_id_to, airport_to, terminal_to, airline_id, flight_number, ticket_number, class_type, baggage_allowed, equipment, flight_meal, seat_no

**Hotel (task_hotel_details[]):** 14 required fields — hotel_name, booking_time, check_in, check_out, room_reference, room_number, room_type, room_amount, room_details, room_promotion, rate, meal_type, is_refundable, supplements

**Visa (task_visa_details[]):** 6 required fields — visa_type, application_number, expiry_date, number_of_entries, stay_duration, issuing_country

**Insurance (task_insurance_details[]):** 8 required fields — date, paid_leaves, document_reference, insurance_type, destination, plan_type, duration, package

---

## 4. Concurrency & Scale Analysis

### Current Infrastructure
- Queue: database driver (configured, barely used)
- Cache locks: used in ProcessAirFiles command
- DB transactions: used in TaskWebhook
- No rate limiting on API routes
- No unique constraint on tasks table (was removed)

### Identified Gaps
| Issue | Severity |
|---|---|
| No unique constraint on tasks table | CRITICAL |
| TaskWebhook duplicate check before transaction (race condition) | CRITICAL |
| No rate limiting on API endpoints | HIGH |
| File naming collision (raw client names) | HIGH |
| No callback retry mechanism | HIGH |
| No dead-letter queue for failed callbacks | HIGH |
| Merge filename collision (minute-precision timestamp) | MEDIUM |
| No idempotency key support | MEDIUM |

### Solutions in ResailAI Module
1. Queue-based processing (Job dispatched, instant user response)
2. UUID file naming (no collisions)
3. Idempotency key on callbacks
4. Duplicate check inside DB transaction
5. Stuck document sweeper command
6. Rate limiting on module routes
7. Feature flag per company+supplier (auto_process_pdf)

---

## 5. Module Architecture

```
app/Modules/ResailAI/
├── Providers/ResailAIServiceProvider.php     — bootstraps routes, config, middleware
├── Http/Controllers/CallbackController.php   — receives extraction results
├── Services/
│   ├── ProcessingAdapter.php                — orchestrator + feature flag check
│   └── TaskWebhookBridge.php                — transforms extraction → Request → TaskWebhook
├── Jobs/ProcessDocumentJob.php              — queued document processing
├── Middleware/VerifyResailAIToken.php        — Bearer token auth
├── Routes/routes.php                        — POST /api/modules/resailai/callback
└── Config/resailai.php                      — module config (merged via provider)
```

### Zero Existing File Modifications
- Routes loaded from ServiceProvider (not routes/api.php)
- Config merged via mergeConfigFrom (not config/services.php)
- Middleware registered in route group (not bootstrap/app.php)
- TaskWebhook called via Request::create() internally (not modified)

### Only External Changes
- One migration: add auto_process_pdf boolean to supplier_companies pivot
- .env: add RESAILAI_API_TOKEN
- Optional: composer.json autoload line

---

## 6. Processing Flow

```
Agent uploads PDF via web form
    ↓
Check: supplier_companies.auto_process_pdf == true?
    ↓ YES
ProcessDocumentJob dispatched to queue (instant response to user)
    ↓
Job sends document to ResailAI service with Bearer token
Payload: {company_id, supplier_id, agent_id, branch_id, document_id, file_path, callback_url}
    ↓
ResailAI service processes PDF (extraction)
    ↓
ResailAI POSTs callback: POST /api/modules/resailai/callback
    ↓
CallbackController validates token + payload
    ↓
ProcessingAdapter checks feature flag + handles errors
    ↓
TaskWebhookBridge transforms extraction_result → Request object
    ↓
TaskWebhook::webhook($request) called internally
    ↓
Full pipeline runs: validation → dedup → supplier rules → task creation →
surcharges → auto-billing → type details → financials → IATA wallet
    ↓
Task + details created, DocumentProcessingLog updated to 'completed'
```

---

## 7. Security

- Bearer token (RESAILAI_API_TOKEN in .env) — server-to-server
- HTTPS enforced
- IP whitelist on ResailAI server firewall
- Token never logged
- Rate limiting on callback endpoint
- Feature flag prevents unauthorized processing

---

## 8. Multi-Tenant Design

- Token is system-level (one token for all companies)
- Tenant context travels in payload (company_id, supplier_id, agent_id, branch_id)
- ResailAI service is tenant-blind (just extracts, returns data)
- Feature flag is per company+supplier (auto_process_pdf on supplier_companies pivot)
- All task creation inherits existing multi-tenant isolation in TaskWebhook

---

## 9. Key Decisions

1. Brand as ResailAI (not n8n) — hidden from clients
2. Self-contained module — zero existing file changes
3. Queue-based — non-blocking for users
4. Generic — works for any supplier, any task type
5. Feature flag — opt-in per company+supplier
6. Reuse TaskWebhook — no new task creation logic
7. Bearer token — simple server-to-server auth
8. UUID file naming — no collision risk

---

## 10. File References

### Existing Files (NOT modified)
- app/Http/Webhooks/TaskWebhook.php (780 lines) — full task pipeline
- app/Http/Controllers/Api/DocumentProcessingController.php — queue to service
- app/Http/Controllers/Api/Webhooks/N8nCallbackController.php — current callback
- app/Schema/TaskSchema.php, TaskFlightSchema, TaskHotelSchema, TaskVisaSchema, TaskInsuranceSchema
- app/Models/Task.php, TaskFlightDetail, TaskHotelDetail, TaskVisaDetail, TaskInsuranceDetail
- app/Models/SupplierCompany.php — pivot with auto_process_pdf toggle
- config/services.php, routes/api.php, bootstrap/app.php

### Previous Research
- .planning/quick/flydubai-n8n-pdf-processing-migration.md (1800+ lines, FlyDubai-specific)
- .planning/quick/.continue-here-n8n-migration.md (handoff from first session)

---

## 11. Complete Supplier Registry (42 Suppliers Found)

### Seeded Suppliers (5)
| Supplier | ID | Task Types |
|---|---|---|
| Amadeus | 2 | flight |
| Magic Holiday | Unknown | hotel |
| TBO Holiday | Unknown | hotel |
| DOTW | Unknown | hotel |
| Rate Hawk | Unknown | hotel |

### N8n Workflow Routed (IDs known)
| Supplier | ID | Task Types |
|---|---|---|
| Jazeera Airways | 1 | flight |
| FlyDubai | 2 | flight |
| ETA UK | 3 | visa |
| The Skyrooms | 4 | hotel |
| Air Arabia | 5 | flight |
| Indigo | 6 | flight |
| Cham Wings | 7 | flight |
| VFS Global | 8 | visa |
| Gmail | 11 | email |
| Image Upload | 12 | image |
| NDC Suppliers | 29, 38, 39 | flight |

### AI Extraction Hints Only (20+ suppliers)
Airlines: Cebu Pacific, SalamAir, Wizz Air, AirCairo, Emirates
Hotels: Smile Holidays, Bella Vita, World of Luxury, Travel Collection, Heysam Group, Bedzinn, Supreme Services, Enlite, Restel, Webbeds, Alpha Maldives, Pilot Tours, Sky Rooms, Como Travels, Trendy Travel, Alam Al Raya
Visa: London Visa, BLS Spain Visa, Bahrain E-Visa
Insurance: First Takaful
Car: TBO Car

---

## 12. Edge Case Analysis

| Edge Case | Status | Action Required |
|---|---|---|
| NDC IATA Wallet (29,38,39) | WORKS | None |
| Como Travels (issued_by) | NEEDS ATTENTION | Verify issued_by preservation |
| Trendy Travel / Alam Al Raya | WORKS | Verify correct totals |
| AutoBilling Matching | WORKS | ResailAI must provide agent_id |
| Hotel is_online Flag | WORKS | Complete extraction needed |
| Supplier Surcharges | WORKS | None |
| Original Task Linking | WORKS | Accurate passenger_name + original_reference |
| Currency Conversion | WORKS | Provide exchange_rate if non-KWD |
| Task Enabled Status | CRITICAL | Must provide agent_id + client_id |

### Critical Requirements for ResailAI Payload
1. Always: company_id, supplier_id, type, status, reference, total
2. For agent linking: agent_id (critical for enabled=true)
3. For client linking: client_id OR AutoBilling rule match data
4. For refund/void: original_reference, passenger_name (exact match)
5. For currency: exchange_currency + exchange_rate if non-KWD
6. For Como: preserve issued_by field
7. For hotels: complete detail extraction

---

## 13. Scenarios Evaluation (Pending full results)

### Confirmed Working
- Generic flight suppliers (Jazeera, FlyDubai, NDC) — TaskWebhook handles all
- Generic hotel suppliers — hotel details saved correctly
- Visa suppliers (VFS, London, BLS) — visa details saved correctly
- Insurance (First Takaful) — 1 task per policy rule works

### Needs Special Handling
- **Smile Holidays batch merge**: Merge must happen BEFORE sending to ResailAI (2+ PDFs → 1 merged PDF → send)
- **Multi-passenger flights**: ResailAI must split into multiple callbacks (1 per passenger) OR TaskWebhookBridge must loop
- **AIR files**: Module must NOT intercept — only PDF path affected

---

## 14. Concurrency Solutions in Module

| Problem | Solution |
|---|---|
| No unique constraint on tasks | Duplicate check inside DB transaction |
| File naming collision | UUID prefix on all uploads |
| No rate limiting | Throttle middleware on module routes |
| No callback retry | Stuck document sweeper command |
| No idempotency | Idempotency key on callbacks |
| Queue driver (database) | Works for now, Redis recommended for scale |
