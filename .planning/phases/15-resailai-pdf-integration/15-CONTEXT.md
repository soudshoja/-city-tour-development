# Phase 15: ResailAI PDF Integration - Context

**Gathered:** 2026-03-17
**Status:** Ready for planning
**Source:** Conversation decisions with user

<domain>
## Phase Boundary

Wire up the ResailAI PDF processing pipeline end-to-end within Laravel. Phase 14 built the module foundation (service provider, config, routes, middleware, admin UI, models). Phase 15 completes the integration: TaskController dispatches PDFs to n8n webhook, CallbackController receives extraction results, TaskWebhookBridge normalizes data for all 4 task types and creates tasks via existing TaskWebhook pipeline.

**What this phase does NOT do:**
- Build or configure n8n workflows (external)
- Build or configure ResailAI AI service (external)
- Replace or modify existing AirFileParser (stays as-is for AIR files)
- Add new AI/OpenAI/OpenWebUI code to Laravel

</domain>

<decisions>
## Implementation Decisions

### Architecture
- n8n is the PRIMARY processor (Tika/Gutenberg + Code node parsing)
- ResailAI is the AI FALLBACK (handles what n8n can't parse)
- Laravel ONLY receives structured data and creates tasks — no AI processing in Laravel
- OpenAI/OpenWebUI/AIManager are NOT part of this pipeline

### Processing Flow
- PDF uploaded via portal → Laravel checks auto_process_pdf flag on supplier_companies
- If enabled → dispatch ProcessDocumentJob to queue → sends to n8n webhook
- n8n/ResailAI processes PDF externally → calls back to Laravel callback URL
- CallbackController receives results → TaskWebhookBridge normalizes → TaskWebhook creates task

### Error Handling (CRITICAL — Mixed approach Option C)
- **Critical field failures REJECT entirely**: reference, type, company_id, status
  - Without these, cannot create a meaningful task
  - Return error to n8n, file marked as 'error'
- **Non-critical field failures CREATE task anyway**:
  - Airline/airport/country lookups that fail → set to null
  - Date format parsing failures → set to null
  - Financial amount parsing failures → set to 0 or null
  - Terminal, seat, baggage, equipment → set to null
  - agent_id, client_name, issued_by → set to null
  - Task created with is_complete=false, enabled=false
  - Normalization errors logged with document_id for agent to review and fix manually in portal

### TaskWebhookBridge Normalization
- Normalize ALL 4 task types together (flight, hotel, visa, insurance)
- Bridge handles common variations from n8n extraction format:
  - Date formats: accept DD/MM/YYYY, DD-Mon-YYYY, YYYYMMDD → normalize to YYYY-MM-DD HH:MM:SS
  - Airline names → lookup airline_id from database
  - IATA airport codes → lookup country_id from database
  - Currency handling: if non-KWD, swap to original_* fields
  - Board codes (BB, HB, AI) → full meal_type names
  - String booleans ("Yes"/"No") → true/false
  - String numbers ("45.500") → float
  - Class types → lowercase
  - Entries ("Single") → lowercase ("single")
  - Stay duration ("30 days") → integer (30)

### Outbound Payload (Laravel → n8n webhook)
```json
{
  "document_id": "uuid",
  "company_id": 1,
  "supplier_id": 2,
  "agent_id": 5,
  "branch_id": 1,
  "file_path": "storage/app/company/supplier/files_unprocessed/uuid_file.pdf",
  "callback_url": "https://development.citycommerce.group/api/modules/resailai/callback"
}
```

### Inbound Payload (n8n/ResailAI → Laravel callback)
```json
{
  "document_id": "uuid",
  "status": "success|error|needs_processing",
  "reference": "booking-ref",
  "type": "flight|hotel|visa|insurance",
  "company_id": 1,
  "supplier_id": 2,
  "agent_id": 5,
  "client_name": "TEST/USER MR",
  "price": 100.000,
  "total": 130.000,
  "tax": 30.000,
  "exchange_currency": "KWD",
  "task_flight_details": [...],
  "task_hotel_details": [...],
  "task_visa_details": [...],
  "task_insurance_details": [...]
}
```

### Claude's Discretion
- Internal method organization within TaskWebhookBridge (helper methods, etc.)
- Logging format and detail level for normalization errors
- How to structure the completeness scoring logic
- Whether to use a dedicated NormalizationError model or log to existing DocumentProcessingLog

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### ResailAI Module (Phase 14 output)
- `app/Modules/ResailAI/Services/TaskWebhookBridge.php` — Current skeleton to complete
- `app/Modules/ResailAI/Services/ProcessingAdapter.php` — Feature flag check + orchestration
- `app/Modules/ResailAI/Http/Controllers/CallbackController.php` — Current skeleton to implement
- `app/Modules/ResailAI/Jobs/ProcessDocumentJob.php` — Outbound job (already built)
- `app/Modules/ResailAI/Middleware/VerifyResailAIToken.php` — Auth middleware (already built)
- `app/Modules/ResailAI/Config/resailai.php` — Module config (already built)
- `app/Modules/ResailAI/Routes/routes.php` — Module routes (already built)

### TaskWebhook Pipeline (source of truth for task creation)
- `app/Http/Webhooks/TaskWebhook.php` — Full webhook pipeline: validation, dedup, supplier rules, financials, IATA wallet
- `app/Schema/TaskSchema.php` — Main task field definitions
- `app/Schema/TaskFlightSchema.php` — Flight detail validation rules
- `app/Schema/TaskHotelSchema.php` — Hotel detail validation rules
- `app/Schema/TaskVisaSchema.php` — Visa detail validation rules
- `app/Schema/TaskInsuranceSchema.php` — Insurance detail validation rules

### Existing Processing (reference for field formats)
- `app/Services/AirFileParser.php` — How AIR files produce flight fields (reference format)
- `app/Http/Controllers/TaskController.php` — Upload method to modify

### Research from Phase 14
- `.planning/phases/14-resailai-module/09-RESEARCH.md` — Full architecture, supplier rules, processing flows
- `.planning/phases/14-resailai-module/14-RESEARCH.md` — Current state, missing components, file paths

</canonical_refs>

<specifics>
## Specific Ideas

### Supplier-specific normalization notes
- Amadeus: only supplier that uses gds_reference, airline_reference, created_by — clear these for non-Amadeus
- Status mapping: Jazeera/FlyDubai/VFS map confirmed→issued, on hold→confirmed (already handled by TaskWebhook)
- Insurance: 1 task per policy NOT per person (First Takaful rule)
- Hotel dedup: matches by reference + company_id + hotel_name + room_type + check_in + check_out
- TBO/Magic Holiday: n8n booking integration sets is_n8n_booking=true, skips financial processing
- Trendy Travel/Alam Al Raya: zero-total suppliers, skip financial processing

### Normalization lookup tables needed
- Airlines: name → airline_id (from airlines/suppliers table)
- Airports: IATA code → country_id (from airports/countries table)
- Board codes: BB→Bed and Breakfast, HB→Half Board, FB→Full Board, AI→All Inclusive, RO→Room Only, SC→Self Catering

</specifics>

<deferred>
## Deferred Ideas

- n8n workflow configuration (separate project)
- ResailAI AI service configuration (separate project)
- Production deployment and route 404 fix (Phase 16 debug)
- Qwen 3.5:397B integration for ResailAI service (future)
- Admin UI for viewing normalization errors and fixing incomplete tasks (future phase)

</deferred>

---

*Phase: 15-resailai-pdf-integration*
*Context gathered: 2026-03-17 via conversation decisions*
