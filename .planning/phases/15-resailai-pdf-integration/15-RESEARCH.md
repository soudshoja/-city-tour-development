# Phase 15: ResailAI PDF Integration - Research

**Researched:** 2026-03-17
**Domain:** Laravel webhook pipeline integration, data normalization, task creation
**Confidence:** HIGH — all findings based on direct codebase inspection

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- n8n is the PRIMARY processor (Tika/Gutenberg + Code node parsing)
- ResailAI is the AI FALLBACK (handles what n8n can't parse)
- Laravel ONLY receives structured data and creates tasks — no AI processing in Laravel
- OpenAI/OpenWebUI/AIManager are NOT part of this pipeline
- Processing Flow: PDF uploaded via portal → Laravel checks auto_process_pdf flag on supplier_companies → If enabled → dispatch ProcessDocumentJob to queue → sends to n8n webhook → n8n/ResailAI processes PDF externally → calls back to Laravel callback URL → CallbackController receives results → TaskWebhookBridge normalizes → TaskWebhook creates task
- Error Handling Option C (Mixed):
  - Critical field failures REJECT entirely: reference, type, company_id, status
  - Non-critical field failures CREATE task anyway (with is_complete=false, enabled=false)
  - Normalization errors logged with document_id for agent to review

### Claude's Discretion
- Internal method organization within TaskWebhookBridge (helper methods, etc.)
- Logging format and detail level for normalization errors
- How to structure the completeness scoring logic
- Whether to use a dedicated NormalizationError model or log to existing DocumentProcessingLog

### Deferred Ideas (OUT OF SCOPE)
- n8n workflow configuration (separate project)
- ResailAI AI service configuration (separate project)
- Production deployment and route 404 fix (Phase 16 debug)
- Qwen 3.5:397B integration for ResailAI service (future)
- Admin UI for viewing normalization errors and fixing incomplete tasks (future phase)
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| RESAIL-11 | Complete TaskWebhookBridge with full field normalization for all 4 task types (flight, hotel, visa, insurance) including financial fields and references | transformExtractionToPayload() is a skeleton — maps basic fields only, completely missing type-detail key renaming, financial normalization, and lookup resolution |
| RESAIL-12 | Implement completeness scoring — reject if critical fields missing; create task with needs_review flag if non-critical fields fail | DocumentProcessingLog has needs_review boolean + DocumentError model for per-field errors — use these |
| RESAIL-13 | Wire TaskController::upload() to dispatch ProcessDocumentJob for PDF files when supplier has auto_process_pdf enabled | ALREADY DONE — TaskController already dispatches ProcessDocumentJob at lines 3213–3226 and 3314–3327 |
| RESAIL-14 | Implement CallbackController::handle() to receive n8n/ResailAI extraction results and route through TaskWebhookBridge | CallbackController exists but has schema mismatch: validates `extraction_result` wrapper but CONTEXT.md shows flat payload; needs rewrite of routing logic |
| RESAIL-15 | Normalize flight fields — airline name→ID lookup, IATA code→country_id, date format parsing, class_type lowercase | Airline lookup: use iata_designator first, then UPPER(name) LIKE; Airport→country: Airport model has iata_code + country_id columns |
| RESAIL-16 | Normalize hotel fields — date formats, board code→meal_type mapping, refundable string→bool, room_amount string→float | TaskWebhook validates room_amount as integer min:1; board code mapping not present anywhere — must build |
| RESAIL-17 | Normalize visa fields — entries lowercase, stay_duration string→int extraction | TaskWebhook validates number_of_entries as in:single,double,multiple; stay_duration as integer |
| RESAIL-18 | Normalize insurance fields — year extraction, pass-through with type validation | TaskInsuranceSchema.date is 'YYYY' year string; all other fields are string pass-through |
| RESAIL-19 | Normalize shared financial fields — currency swap for non-KWD, string→float conversion, taxes_record format validation, defaults for tax/surcharge/penalty_fee | TaskWebhook::prepareRequestData() already handles currency swap and defaults — bridge must output clean numeric values |
| RESAIL-20 | Log normalization errors per field with document_id correlation | DocumentProcessingLog has document_id + needs_review + error fields; DocumentError model has input_context (array) for per-field details |
</phase_requirements>

---

## Summary

Phase 14 built the module skeleton. The core infrastructure (ProcessDocumentJob, routes, middleware, config, SupplierCompany.auto_process_pdf) is complete and migrations exist. TaskController::upload() ALREADY dispatches ProcessDocumentJob for PDF files when auto_process_pdf is enabled — RESAIL-13 is done in the existing code.

The remaining work is concentrated in two areas. First, TaskWebhookBridge.transformExtractionToPayload() is a non-functional skeleton: it maps basic scalars but uses wrong key names for type-details arrays (maps `flight_details` but TaskWebhook expects `task_flight_details`), performs zero normalization, and has no lookup resolution. Second, CallbackController.handle() has a schema mismatch: it validates for a nested `extraction_result` wrapper, but the CONTEXT.md inbound payload spec is flat (reference, type, company_id, task_flight_details all at top level). The controller must be rewritten to accept the flat payload directly.

The DocumentProcessingLog + DocumentError models provide the exact infrastructure for RESAIL-20 normalization error logging. Lookup resolution paths are established in the codebase: airline_id via Airline.iata_designator (from flight_number prefix) then UPPER(name) LIKE fallback; country_id from Airport.iata_code → Airport.country_id.

**Primary recommendation:** Rewrite transformExtractionToPayload() as the central normalization engine with per-type normalizers; fix CallbackController to accept flat payload; use DocumentError.input_context for per-field normalization failure logging.

---

## Standard Stack

### Core (all already installed/available)
| Component | Location | Purpose |
|-----------|----------|---------|
| TaskWebhook | `app/Http/Webhooks/TaskWebhook.php` | Task creation pipeline — validates, deduplicates, creates task + details |
| TaskWebhookBridge | `app/Modules/ResailAI/Services/TaskWebhookBridge.php` | Transforms n8n extraction → TaskWebhook Request — SKELETON to complete |
| CallbackController | `app/Modules/ResailAI/Http/Controllers/CallbackController.php` | Receives n8n callback — EXISTS but needs schema fix |
| ProcessDocumentJob | `app/Modules/ResailAI/Jobs/ProcessDocumentJob.php` | Sends PDF to n8n — COMPLETE, do not modify |
| ProcessingAdapter | `app/Modules/ResailAI/Services/ProcessingAdapter.php` | Feature flag check — COMPLETE |
| DocumentProcessingLog | `app/Models/DocumentProcessingLog.php` | Tracks document processing state + needs_review flag |
| DocumentError | `app/Models/DocumentError.php` | Per-field normalization error records linked to DocumentProcessingLog |
| Airline model | `app/Models/Airline.php` | Lookup: name→id, iata_designator→id |
| Airport model | `app/Models/Airport.php` | Lookup: iata_code→country_id |
| Carbon | Available via Laravel | Date normalization |

### Schema field definitions (what TaskWebhook validates)
| Schema | File | Key observation |
|--------|------|-----------------|
| TaskFlightSchema | `app/Schema/TaskFlightSchema.php` | airline_id expects integer; country_id_from/to expects integer |
| TaskHotelSchema | `app/Schema/TaskHotelSchema.php` | room_amount is float; is_refundable is boolean |
| TaskVisaSchema | `app/Schema/TaskVisaSchema.php` | number_of_entries enum: single/double/multiple; stay_duration is int |
| TaskInsuranceSchema | `app/Schema/TaskInsuranceSchema.php` | date is year string 'YYYY'; all other fields are nullable strings |

---

## Architecture Patterns

### Complete Processing Flow (verified)

```
TaskController::upload()                     [DONE — lines 3213-3327]
  └── ProcessingAdapter::isPdfProcessingEnabled()
        └── ProcessDocumentJob::dispatch()    [DONE]
              └── HTTP POST to n8n webhook    [DONE]

n8n/ResailAI processes PDF externally
  └── HTTP POST to /api/modules/resailai/callback

CallbackController::handle()                 [NEEDS SCHEMA FIX]
  └── validate flat payload
  └── check status field
  └── route to TaskWebhookBridge::processExtraction()

TaskWebhookBridge::processExtraction()
  └── validateCriticalFields()               [NEEDS IMPLEMENTATION]
  └── transformExtractionToPayload()         [NEEDS FULL IMPLEMENTATION]
        ├── normalizeSharedFields()
        ├── normalizeFlightDetails()          [per RESAIL-15]
        ├── normalizeHotelDetails()           [per RESAIL-16]
        ├── normalizeVisaDetails()            [per RESAIL-17]
        └── normalizeInsuranceDetails()       [per RESAIL-18]
  └── TaskWebhook::webhook(Request)
  └── updateDocumentLog()
```

### Pattern 1: Critical Field Validation (Option C)
**What:** Before any normalization, check that reference, type, company_id, status are present.
**When to use:** Always, as the first step in processExtraction().
**Example:**
```php
$critical = ['reference', 'type', 'company_id', 'status'];
foreach ($critical as $field) {
    if (empty($extractionResult[$field])) {
        $this->logCriticalFailure($documentId, $field);
        return ['success' => false, 'error' => "Missing critical field: {$field}"];
    }
}
```

### Pattern 2: Airline Name → ID Lookup (verified from codebase)
**Strategy:** iata_designator prefix from flight_number first; fallback to name LIKE match.
```php
// From flight_number prefix (most reliable)
$iataCode = substr(preg_replace('/[^A-Z]/', '', strtoupper($flightNumber)), 0, 2);
$airline = Airline::where('iata_designator', $iataCode)->first();

// Name fallback
if (!$airline && $airlineName) {
    $airline = Airline::whereRaw('UPPER(name) LIKE ?', ['%' . strtoupper($airlineName) . '%'])->first();
    if (!$airline) {
        $airline = Airline::whereRaw('UPPER(name_ar) LIKE ?', ['%' . strtoupper($airlineName) . '%'])->first();
    }
}

// Non-critical: set null if not found, log normalization error
$airlineId = $airline?->id;
```
Source: `app/Console/Commands/MigrateFlightDetailsToForeignKeys.php` lines 158-186, `app/Http/Controllers/TaskController.php` lines 3480-3489

### Pattern 3: IATA Airport Code → country_id (verified from models)
**What:** Airport model has `iata_code` (3-char) and `country_id` columns. Use direct DB lookup.
```php
// Airport model: iata_code (char 3), country_id (FK to countries)
$airport = Airport::where('iata_code', strtoupper($iataCode))->first();
$countryId = $airport?->country_id;
// Non-critical: null if not found
```
Source: `app/Models/Airport.php`, `database/migrations/2026_01_28_143110_update_airports_table.php`

### Pattern 4: Country Name → country_id (for string country values)
**What:** If n8n returns country names instead of IATA codes, look up by name.
```php
$country = Country::where('name', 'like', '%' . $countryName . '%')->first();
$countryId = $country?->id;
```
Source: `app/Console/Commands/FixFlightDetails.php` lines 740-741, `app/Http/Controllers/TaskController.php` lines 3462-3476

### Pattern 5: Key Name Mapping (CRITICAL BUG in skeleton)
**What:** The current skeleton maps to `flight_details`, `hotel_details`, etc. TaskWebhook expects `task_flight_details`, `task_hotel_details`, `task_visa_details`, `task_insurance_details`.
**Fix required:** transformExtractionToPayload must output the correct keys.
```php
// WRONG (current skeleton):
$payload['flight_details'] = $extractionResult['flight_details'];

// CORRECT:
$payload['task_flight_details'] = $this->normalizeFlightDetails($extractionResult['task_flight_details'] ?? []);
```
Source: `app/Http/Webhooks/TaskWebhook.php` lines 426-448 (saveTaskTypeDetails uses these exact keys)

### Pattern 6: Normalization Error Logging (DocumentError model)
**What:** DocumentError belongs to DocumentProcessingLog. Use input_context array for field-level detail.
```php
// 1. Find or create the DocumentProcessingLog entry by document_id
$log = DocumentProcessingLog::where('document_id', $documentId)->first();

// 2. Create a DocumentError record per failed normalization
DocumentError::create([
    'document_processing_log_id' => $log->id,
    'error_type' => DocumentError::TYPE_NON_TRANSIENT,
    'error_code' => 'NORM_FIELD_FAILED',
    'error_message' => "Failed to resolve airline_id from '{$airlineName}'",
    'input_context' => [
        'field' => 'airline_id',
        'raw_value' => $airlineName,
        'document_id' => $documentId,
    ],
]);

// 3. Mark the DocumentProcessingLog as needing review
$log->update(['needs_review' => true]);
```
Source: `app/Models/DocumentError.php`, `app/Models/DocumentProcessingLog.php`

### Pattern 7: Date Normalization
**What:** TaskWebhook validates dates with `nullable|date`. Carbon::parse() handles most formats.
```php
// Accept: DD/MM/YYYY, DD-Mon-YYYY, YYYYMMDD, ISO 8601
// Output: YYYY-MM-DD HH:MM:SS (for datetime fields)
try {
    $normalized = $rawDate ? Carbon::parse($rawDate)->format('Y-m-d H:i:s') : null;
} catch (\Exception $e) {
    $normalized = null; // Non-critical: log and set null
}
```

### Anti-Patterns to Avoid
- **Wrong detail key names:** Never output `flight_details` — TaskWebhook only reads `task_flight_details` etc.
- **Throwing on non-critical failures:** Non-critical lookups (airline, country, terminal) must set null silently, not throw exceptions.
- **Calling ProcessingAdapter::processExtractionResult():** This method is a no-op pass-through. The real normalization must happen in TaskWebhookBridge.
- **Assuming DocumentProcessingLog exists:** The CallbackController may receive callbacks for documents not tracked in DocumentProcessingLog (uploaded before the feature was added). Guard with `if ($log)`.
- **Mutating the inbound Request:** The bridge builds a synthetic Request via `Request::create()` — this is the correct pattern and does not affect the actual HTTP request.
- **Passing `booking_time` as null for hotels:** TaskWebhook validates `booking_time` as `required|date` — set to now() if missing rather than null.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Airline lookup | Custom array/cache | `Airline::where('iata_designator', ...)` | Already has full airline DB with iata_designator + name + name_ar |
| Airport→country lookup | Static IATA map | `Airport::where('iata_code', ...)->value('country_id')` | Airport table has iata_code + country_id FK |
| Country name lookup | Static array | `Country::where('name', 'like', ...)` | Countries table exists with full names |
| Date parsing | Custom regex | `Carbon::parse()` | Handles all common formats; wrap in try-catch for null fallback |
| Task creation | Direct model create | `TaskWebhook::webhook(Request)` | Handles dedup, supplier rules, surcharges, financials, IATA wallet |
| Error tracking | Custom log table | `DocumentProcessingLog` + `DocumentError` | Already has needs_review, per-field error storage, document_id correlation |

---

## Common Pitfalls

### Pitfall 1: CallbackController Payload Schema Mismatch
**What goes wrong:** CallbackController currently validates for `extraction_result` (nested array wrapper) and `error` (nested object). The CONTEXT.md spec has a flat payload where reference, type, company_id, task_flight_details etc. are all top-level.
**Why it happens:** Phase 14 was built speculatively; the actual n8n payload format was decided in Phase 15 CONTEXT.md.
**How to avoid:** Rewrite CallbackController validation to match the flat inbound payload spec from CONTEXT.md exactly.
**Warning signs:** If `extraction_result` is still in the validation rules, the schema is wrong.

### Pitfall 2: TaskController::upload() Is Already Wired
**What goes wrong:** Implementing RESAIL-13 when it is already done — wasting a task slot or creating duplicate dispatch code.
**Why it happens:** Phase 14 implemented the upload wiring. RESAIL-13 says "wire" but the code is at lines 3213-3226 (merge path) and 3314-3327 (single file path).
**How to avoid:** Read TaskController::upload() before implementing. The upload wiring is complete. RESAIL-13 work = verify the existing implementation is correct and add branch_id passing if needed.

### Pitfall 3: `task_hotel_details.room_amount` Type Conflict
**What goes wrong:** TaskWebhook validates `room_amount` as `required|integer|min:1` but TaskHotelSchema defines it as `float`. n8n may send string `"45.500"`.
**Why it happens:** The webhook validation uses integer but the schema says float. String values from n8n fail validation.
**How to avoid:** In normalizeHotelDetails(), cast room_amount to `(int) round($rawValue)` before passing to TaskWebhook.

### Pitfall 4: `booking_time` Required for Hotel Validation
**What goes wrong:** TaskWebhook validates `task_hotel_details.*.booking_time` as `required|date`. If n8n doesn't provide booking_time, the entire webhook fails.
**Why it happens:** It's a required field in TaskWebhook but not in TaskHotelSchema defaults.
**How to avoid:** In normalizeHotelDetails(), default booking_time to `now()->toDateTimeString()` if absent.

### Pitfall 5: `is_complete` Is a Computed Accessor, Not a Column
**What goes wrong:** Trying to set `is_complete = false` on a task or passing it in the request payload expecting it to persist.
**Why it happens:** `Task::getIsCompleteAttribute()` is a dynamic accessor that checks required columns: company_id, supplier_id, type, status, reference, total. It is not stored in the DB.
**How to avoid:** To create an "incomplete" task, omit or null out the required columns (particularly `total` = 0 or keep `agent_id` null). Do not attempt to set is_complete directly.
**Note:** The task will have enabled=false automatically when is_complete is false (TaskWebhook line 529).

### Pitfall 6: Hotel Dedup Requires Normalized Dates
**What goes wrong:** Hotel dedup in TaskWebhook uses `Carbon::parse($checkIn)->toDateString()`. If check_in/check_out aren't parseable, dedup fails silently and duplicates are created.
**Why it happens:** Dates from n8n may be in non-standard formats.
**How to avoid:** Always normalize hotel dates to Y-m-d H:i:s before passing to TaskWebhook.

### Pitfall 7: `needs_review` vs `status` on DocumentProcessingLog
**What goes wrong:** Setting status='completed' on a document that had normalization failures, making it invisible to agents.
**Why it happens:** Confusion between "task was created" (success) and "task was created cleanly" (no errors).
**How to avoid:** When creating task with non-critical failures: set DocumentProcessingLog.status='completed' AND needs_review=true. Create DocumentError records for each failed field. This way the log shows completed but still surfaces to review queue.

---

## Code Examples

### CallbackController Inbound Payload (CONTEXT.md spec — what to validate)
```php
// Source: 15-CONTEXT.md inbound payload spec
$validated = $request->validate([
    'document_id' => 'required|string',   // NOTE: FileUpload.id is integer not UUID
    'status' => 'required|in:success,error,needs_processing',
    'reference' => 'nullable|string',
    'type' => 'nullable|string|in:flight,hotel,visa,insurance',
    'company_id' => 'nullable|integer',
    'supplier_id' => 'nullable|integer',
    'agent_id' => 'nullable|integer',
    'client_name' => 'nullable|string',
    'price' => 'nullable|numeric',
    'total' => 'nullable|numeric',
    'tax' => 'nullable|numeric',
    'exchange_currency' => 'nullable|string',
    'task_flight_details' => 'nullable|array',
    'task_hotel_details' => 'nullable|array',
    'task_visa_details' => 'nullable|array',
    'task_insurance_details' => 'nullable|array',
]);
```

### TaskWebhookBridge: Correct Output Key Names
```php
// Source: app/Http/Webhooks/TaskWebhook.php saveTaskTypeDetails() lines 426-448
// TaskWebhook reads these exact keys from the Request:
$detailsMap = [
    'hotel'     => 'task_hotel_details',
    'flight'    => 'task_flight_details',
    'insurance' => 'task_insurance_details',
    'visa'      => 'task_visa_details',
];
// Bridge MUST output these key names in transformExtractionToPayload()
```

### TaskWebhook Required Fields (what must be present to avoid validation failure)
```php
// Source: app/Http/Webhooks/TaskWebhook.php validateWebhookRequest() lines 94-135
// Required (no nullable):
// - reference (string)
// - status (string)
// - company_id (exists:companies,id)
// - type (in:flight,hotel,insurance,visa)

// Required per type — flight requires ALL of:
// task_flight_details.*.is_ancillary (boolean)
// task_flight_details.*.farebase (numeric)
// task_flight_details.*.departure_time (date)
// task_flight_details.*.country_id_from (integer, exists:countries,id)
// task_flight_details.*.airport_from (string)
// task_flight_details.*.terminal_from (string)    <- NOTE: required, not nullable
// task_flight_details.*.arrival_time (date)
// task_flight_details.*.duration_time (string)
// task_flight_details.*.country_id_to (integer, exists:countries,id)
// task_flight_details.*.airport_to (string)
// task_flight_details.*.terminal_to (string)      <- NOTE: required, not nullable
// task_flight_details.*.airline_id (integer)
// task_flight_details.*.flight_number (string)
// task_flight_details.*.ticket_number (string)
// task_flight_details.*.class_type (string)
// task_flight_details.*.baggage_allowed (string)
// task_flight_details.*.equipment (string)
// task_flight_details.*.flight_meal (string)
// task_flight_details.*.seat_no (string)
```

### Board Code → Meal Type Mapping (not in codebase — must implement)
```php
// Source: 15-CONTEXT.md specifics section
private const BOARD_CODES = [
    'BB' => 'Bed and Breakfast',
    'HB' => 'Half Board',
    'FB' => 'Full Board',
    'AI' => 'All Inclusive',
    'RO' => 'Room Only',
    'SC' => 'Self Catering',
];
```

### is_complete Logic (Task model accessor)
```php
// Source: app/Models/Task.php lines 73-127
// Required columns that determine is_complete:
protected $requiredColumn = [
    'company_id',
    'supplier_id',
    'type',
    'status',
    'reference',
    'total',
];
// is_complete = true only if ALL above are non-empty
// agent_id and client_id are NOT required for is_complete
// Task.enabled = is_complete && agent_id && client (TaskWebhook line 529)
```

---

## Critical Gap Analysis: TaskWebhookBridge Skeleton

### What the skeleton DOES (current state):
- Maps: reference, status, company_id, supplier_id, agent_id, branch_id, type, original_reference, passenger_name, exchange_currency, exchange_rate, issued_by, client_id, client_name
- Wraps in Request::create() and calls TaskWebhook::webhook()
- Updates DocumentProcessingLog status

### What the skeleton is MISSING:
1. **Wrong detail keys:** Maps `flight_details` / `hotel_details` / `visa_details` / `insurance_details` — TaskWebhook needs `task_flight_details` etc.
2. **No type normalization:** airline name→id, IATA→country_id, date parsing, board codes, string booleans, string numbers
3. **No critical field validation:** Missing reference/type/company_id/status should reject; skeleton passes through
4. **No non-critical error logging:** No DocumentError creation for failed lookups
5. **No needs_review flagging:** DocumentProcessingLog.needs_review never set to true
6. **Financial fields missing:** price, total, tax, taxes_record, original_* fields not mapped
7. **Task-level fields missing:** issued_date, expiry_date, ticket_number, booking_reference, cancellation_deadline, file_name, additional_info
8. **ProcessingAdapter.processExtractionResult() is a no-op:** Does nothing. All normalization logic belongs in TaskWebhookBridge.

---

## State of the Art

| Component | Current State | What Phase 15 Changes |
|-----------|--------------|----------------------|
| TaskController::upload() | COMPLETE — dispatches ProcessDocumentJob | Verify only; no changes needed |
| ProcessDocumentJob | COMPLETE | No changes |
| SupplierCompany.auto_process_pdf | COMPLETE (migration exists) | No changes |
| CallbackController | EXISTS but wrong payload schema | Rewrite validation to flat payload |
| TaskWebhookBridge.transformExtractionToPayload() | Skeleton — wrong keys, no normalization | Full rewrite |
| Normalization error logging | Models exist, nothing writes to them | Add DocumentError creation in bridge |
| Airline/country/airport lookups | Pattern established in MigrateFlightDetailsToForeignKeys.php | Implement same pattern in bridge |

---

## Open Questions

1. **Is `FileUpload.id` an integer or UUID?**
   - What we know: FileUpload model uses standard `$table->id()` (integer auto-increment). CallbackController currently validates `document_id` as `uuid` — this will fail.
   - What's unclear: Does n8n send the integer ID or convert it to a string?
   - Recommendation: Change `document_id` validation to `required|string` (accept both; FileUpload::find() handles integers sent as strings).

2. **Does `needs_review` field exist on tasks table?**
   - What we know: Task.$fillable does not include `needs_review`. DocumentProcessingLog has it.
   - What's unclear: The user said "task created with is_complete=false, enabled=false" — this happens naturally when total=null. No task-level needs_review field needed.
   - Recommendation: Use DocumentProcessingLog.needs_review for the review flag, not a Task column. The task's disabled/incomplete state is sufficient signal in the portal.

3. **What status values does n8n actually send in `status` field of callback?**
   - What we know: CONTEXT.md says `success|error|needs_processing`. CallbackController validates `success|error|pending`.
   - Recommendation: Use CONTEXT.md spec: `success|error|needs_processing`. Treat `needs_processing` as a partial result that still routes through the bridge.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit (Laravel 11 built-in) |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --filter ResailAI` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| RESAIL-11 | transformExtractionToPayload maps all fields correctly | unit | `php artisan test --filter TaskWebhookBridgeTest` | Wave 0 |
| RESAIL-12 | Critical field missing → returns error array, no task created | unit | `php artisan test --filter TaskWebhookBridgeTest::testCriticalFieldRejection` | Wave 0 |
| RESAIL-13 | upload() dispatches ProcessDocumentJob for PDF+auto_process_pdf | unit | `php artisan test --filter TaskControllerUploadTest` | Wave 0 |
| RESAIL-14 | CallbackController accepts flat payload, routes to bridge | unit | `php artisan test --filter CallbackControllerTest` | Wave 0 |
| RESAIL-15 | Flight normalization: airline lookup, IATA→country, dates | unit | `php artisan test --filter TaskWebhookBridgeTest::testFlightNormalization` | Wave 0 |
| RESAIL-16 | Hotel normalization: board codes, refundable bool, room_amount int | unit | `php artisan test --filter TaskWebhookBridgeTest::testHotelNormalization` | Wave 0 |
| RESAIL-17 | Visa normalization: entries lowercase, stay_duration int | unit | `php artisan test --filter TaskWebhookBridgeTest::testVisaNormalization` | Wave 0 |
| RESAIL-18 | Insurance normalization: year string pass-through | unit | `php artisan test --filter TaskWebhookBridgeTest::testInsuranceNormalization` | Wave 0 |
| RESAIL-19 | Financial fields: non-KWD swap, string→float, defaults | unit | `php artisan test --filter TaskWebhookBridgeTest::testFinancialNormalization` | Wave 0 |
| RESAIL-20 | DocumentError created per failed field, document_id present | unit | `php artisan test --filter TaskWebhookBridgeTest::testNormalizationErrorLogging` | Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter ResailAI`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Unit/Modules/ResailAI/TaskWebhookBridgeTest.php` — covers RESAIL-11,12,15,16,17,18,19,20
- [ ] `tests/Unit/Modules/ResailAI/CallbackControllerTest.php` — covers RESAIL-14
- [ ] `tests/Unit/TaskControllerUploadTest.php` — covers RESAIL-13 (verify existing wiring)

---

## Sources

### Primary (HIGH confidence — direct codebase inspection)
- `app/Http/Webhooks/TaskWebhook.php` — Full validation rules, field names, saveTaskTypeDetails key mapping
- `app/Modules/ResailAI/Services/TaskWebhookBridge.php` — Current skeleton state
- `app/Modules/ResailAI/Http/Controllers/CallbackController.php` — Current callback schema
- `app/Http/Controllers/TaskController.php` — upload() lines 3213-3327 (already wired), saveFlightDetails, airline lookup pattern lines 3480-3489
- `app/Models/Task.php` — is_complete computed accessor, required columns
- `app/Models/DocumentProcessingLog.php` — needs_review, error fields, markForReview()
- `app/Models/DocumentError.php` — error_type, input_context, per-field error logging
- `app/Models/Airline.php` — iata_designator, name, name_ar columns
- `app/Models/Airport.php` — iata_code, country_id columns
- `app/Models/SupplierCompany.php` — auto_process_pdf in fillable + casts
- `app/Console/Commands/MigrateFlightDetailsToForeignKeys.php` — established airline lookup pattern (iata_designator → name LIKE → name_ar LIKE)
- `app/Console/Commands/FixFlightDetails.php` — Country name→id pattern
- `database/migrations/2026_01_28_143110_update_airports_table.php` — confirms iata_code + country_id on airports
- `database/migrations/2025_03_17_093925_create_airlines_table.php` + `2026_01_27_194005_add_details_to_airlines_table.php` — confirms iata_designator on airlines

### Secondary (MEDIUM confidence)
- `.planning/phases/14-resailai-module/14-RESEARCH.md` — Phase 14 component status, confirmed TaskController already wired
- `.planning/phases/15-resailai-pdf-integration/15-CONTEXT.md` — Locked decisions including inbound payload spec

---

## Metadata

**Confidence breakdown:**
- What is/isn't implemented: HIGH — direct code inspection
- TaskWebhook field requirements: HIGH — validated against source
- Lookup patterns: HIGH — existing code in MigrateFlightDetailsToForeignKeys and TaskController
- DB schema: HIGH — migrations inspected directly
- CallbackController payload mismatch: HIGH — compared CONTEXT.md spec vs current validation rules

**Research date:** 2026-03-17
**Valid until:** 2026-04-17 (stable codebase)
