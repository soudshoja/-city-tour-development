# FlyDubai PDF Processing via n8n — Complete Research & Build Plan

**Research Date:** 2026-03-10
**Research Status:** COMPLETE
**Confidence Level:** HIGH

---

## Table of Contents

1. [Current State](#1-current-state)
2. [Target Architecture](#2-target-architecture)
3. [Security Decision](#3-security-decision)
4. [Multi-Tenant Design](#4-multi-tenant-design)
5. [DB Fields Required](#5-db-fields-required)
6. [Post-Task Automation](#6-post-task-automation)
7. [Build Plan — Files to Change](#7-build-plan--files-to-change)
8. [Existing Infrastructure to Reuse](#8-existing-infrastructure-to-reuse)
9. [Key File Locations](#9-key-file-locations)
10. [Open Decisions](#10-open-decisions)
11. [Sources](#11-sources)

---

## 1. Current State

### Supplier Identity
- **Supplier ID:** 2
- **Supplier Name:** "Fly Dubai" (or variant)
- **Region:** Middle East
- **File Types:** PDF (invoices, confirmations), AIR (Amadeus GDS format)

### Current Upload Flow
```
Agent uploads FlyDubai PDF via web form
     ↓
POST /tasks/upload (TaskController::upload)
     ↓
File stored in storage/app/{company}/{supplier}/files_unprocessed/
     ↓
FileUpload record created (status: pending)
     ↓
ProcessAirFiles command (manual or scheduled: php artisan app:process-files)
     ↓
shouldUseAirFileParser() check:
   - supplier.name === 'Amadeus'? NO → returns FALSE
     ↓
Falls back to AI-based extraction (OpenAI/OpenWebUI)
     ↓
Task + TaskFlightDetail created
```

### Current N8N Integration Status
- N8N workflow exists: `n8n/workflows/supplier-document-processing.json`
- **FlyDubai routing:** supplier_id=2 → "AIR Processor" route
- **N8N response:** Returns `extraction_status: 'deferred'` (defers to Laravel AirFileParser)
- **Problem:** Laravel's `shouldUseAirFileParser()` returns FALSE for FlyDubai (not named "Amadeus"), so even deferred status doesn't use AIR parsing—falls back to AI extraction anyway

### Configuration Gap
- `N8N_WEBHOOK_URL` is **NOT configured** in production `.env`
- Currently documented as empty placeholder in `.env.example`
- HMAC secret (`N8N_WEBHOOK_SECRET`) exists but webhook URL is missing

### Processing Today
1. **Method:** AI-based extraction (OpenAI/OpenWebUI)
2. **Confidence:** MEDIUM (variable based on PDF quality)
3. **Processing Time:** ~10-30 seconds per document
4. **Automation:** Via scheduled `app:process-files` command (every 5 minutes)

---

## 2. Target Architecture

### Design Goal
Replace AI-based extraction for FlyDubai PDFs with **n8n webhook orchestration** that:
- Offloads PDF processing to n8n (external VPS)
- Extracts structured flight data from PDFs
- Returns extraction results via webhook callback
- Feeds results into existing **TaskWebhook pipeline** (no new code needed)

### Execution Flow

```
┌─ Agent uploads FlyDubai PDF ──────────────────────────────────────┐
│  via web form: POST /tasks/upload                                 │
└──────────────────────────┬──────────────────────────────────────┘
                           ↓
            File stored in files_unprocessed/
            FileUpload record created (pending)
                           ↓
    ┌─ CheckBox: auto_process_pdf enabled? ─────────────┐
    │ (supplier_companies.auto_process_pdf = true)       │
    │                                                     │
    NO → Traditional path (manual trigger)               YES
    │                                                     │
    └─────────────────────────┬──────────────────────────┘
                              ↓
              Trigger n8n webhook immediately
              (new PostUploadController or middleware)
                              ↓
        POST /n8n/webhook/supplier-document-processing
        Payload: {company_id, supplier_id, agent_id, file_path, ...}
                              ↓
        n8n on separate VPS processes PDF
        (Tika text extraction, regex/AI parsing)
                              ↓
        n8n POSTs callback to Laravel:
        POST /api/webhooks/n8n/extraction
        With extracted_data: {reference, passenger, total, flights[], ...}
                              ↓
        N8nCallbackController updates DocumentProcessingLog
        Feeds extraction_result into TaskWebhook pipeline
                              ↓
        TaskWebhook::webhook() creates Task + TaskFlightDetail
        (existing full pipeline: validation, surcharges, financials, IATA wallet)
                              ↓
        Task enabled, invoices created, journals posted, IATA wallet deducted
```

### Key Changes from Current Flow
1. **Immediate trigger:** n8n webhook called RIGHT after upload (if auto_process_pdf enabled)
2. **Multi-tenant context:** company_id, supplier_id, agent_id, branch_id passed to n8n
3. **Callback-driven:** No polling; n8n calls back when extraction complete
4. **Reuses TaskWebhook:** Same task creation pipeline works for both AI-extracted and n8n-extracted data
5. **IATA wallet automation:** If supplier_id=2 + issued_by='KWIKT211N' + iata_number='42230215', wallet deducted automatically

---

## 3. Security Decision

### Authentication Method: Simple Bearer Token (Server-to-Server)

**NOT** OAuth, HMAC-SHA256, or JWT.

**Rationale:**
- Communication is **server-to-server** (Laravel to n8n, not agent/customer-to-server)
- Both servers are controlled by the same organization
- n8n VPS is firewalled to only accept from Laravel server IP
- HTTPS encrypts transport layer
- No replay attacks possible without firewall breach
- Simpler than HMAC: easier to debug, fewer moving parts

### Implementation

**One shared token per environment:**

```bash
# Laravel .env (production)
N8N_API_TOKEN=your-secure-random-token-min-32-chars

# N8N Credentials Manager (N8N UI)
Credential Name: "Laravel API Token"
Token: <same value as above>
```

**Token usage in requests:**

```php
// Laravel → N8n (outbound)
$token = config('services.n8n.api_token');
Http::withToken($token)->post($n8nUrl, $payload);

// N8n → Laravel (callback/inbound)
const token = $secrets.laravelApiToken;
// Set Authorization header in N8n HTTP request node
```

**Security checklist:**

- [ ] Token stored in `.env` (NOT in code)
- [ ] Token is 32+ random characters (use `php artisan make:secret`)
- [ ] HTTPS enforced for all communication (not HTTP)
- [ ] n8n VPS has firewall rule: only accept POST from Laravel server IP
- [ ] Token never logged or printed (use Log::withoutContext() if needed)
- [ ] Token rotated every 90 days (add to deployment checklist)
- [ ] Credentials Manager in n8n locked down (only admins can view)

### Why NOT HMAC-SHA256?
- Adds complexity for zero additional security (server-to-server)
- Requires shared secret + payload hashing logic on both sides
- Timestamp tolerance checking adds latency
- Already protected by HTTPS + firewall + IP restriction

### Why NOT OAuth?
- Overkill for internal service communication
- Requires token endpoint, refresh logic, scope negotiation
- More failure points

---

## 4. Multi-Tenant Design

### Data Isolation Layer

**Company/Branch/Agent Hierarchy (Always Deterministic):**
```
Company (e.g., "City Travelers")
  ↓
Branch (e.g., "Kuwait Main")
  ↓
Agent (e.g., "John Doe")
  ↓
User (e.g., login: john.doe@citycommerce.group)
```

**Rule:** Agent → Branch → Company is 1:1:1. Can derive company from agent_id.

### Multi-Tenant Configuration

**New DB Field: `supplier_companies.auto_process_pdf`**

```sql
ALTER TABLE supplier_companies ADD COLUMN auto_process_pdf BOOLEAN DEFAULT FALSE;
```

**Purpose:**
- Enable/disable automatic n8n processing **per company+supplier combination**
- Example: Enable for "City Travelers + FlyDubai", disable for "Travel Co. + FlyDubai"
- Stored in pivot table (company_id, supplier_id, auto_process_pdf)

**UI Location:**
- Existing supplier settings page: `resources/views/settings/suppliers.blade.php`
- Add checkbox: "Auto-process PDFs via n8n for this supplier"
- Only visible for suppliers with n8n support (FlyDubai, others TBD)

### When Creating Task

**Context always includes:**

```php
$context = [
    'company_id' => $company->id,          // Derived from user → agent → branch → company
    'supplier_id' => 2,                     // FlyDubai
    'agent_id' => $user->agent_id,          // Authenticated user's agent
    'branch_id' => $agent->branch_id,       // Agent's branch
    'user_id' => $user->id,                 // Uploader
];
```

**File isolation:**
```
storage/app/{company_name}/{supplier_name}/files_unprocessed/
                ↓                            ↓
        "city_travelers"            "fly_dubai"
```

**n8n is tenant-blind:**
- n8n receives file path, extracts PDF text
- Returns structured data (no company context in extraction)
- Laravel callback handler adds company/supplier context to task creation

### Supplier-Level Toggle

**Example: Check if auto-process enabled**

```php
// In TaskController::upload()
$supplierCompany = SupplierCompany::where([
    'company_id' => $company->id,
    'supplier_id' => $supplier->id,
])->first();

if ($supplierCompany && $supplierCompany->auto_process_pdf) {
    // Trigger n8n webhook immediately
    $this->queueForN8nProcessing($file, $context);
} else {
    // Traditional path: wait for manual trigger or scheduled command
    // File sits in files_unprocessed/ until user runs:
    // php artisan app:process-files
}
```

---

## 5. DB Fields Required

### What Laravel Knows BEFORE n8n (File Upload Time)

These fields are collected when agent uploads file. n8n doesn't provide them.

| Field | Type | Source | Example |
|-------|------|--------|---------|
| `company_id` | int | User context | 1 |
| `supplier_id` | int | Upload form | 2 (FlyDubai) |
| `agent_id` | int | User.agent_id | 45 |
| `branch_id` | int | Agent.branch_id | 3 |
| `user_id` | int | Auth::user() | 120 |
| `type` | string | Hardcoded | "flight" |
| `file_name` | string | Upload | "FZ-12345.pdf" |
| `is_n8n_booking` | bool | Flag | true |

### Fields n8n MUST Extract from PDF

#### Pricing (KWD equivalent required)

| Field | Required | Notes |
|-------|----------|-------|
| `price` | YES | Base fare in KWD (0 if foreign-only) |
| `tax` | YES | Taxes in KWD |
| `total` | YES | Total booking amount in KWD |
| `surcharge` | NO | Service fees (e.g., 5 KWD) |
| `exchange_currency` | YES | "KWD" always for FlyDubai |
| `original_price` | YES (if non-KWD) | Base fare in original currency |
| `original_currency` | YES (if non-KWD) | Original currency code (e.g., "AED") |
| `original_total` | YES (if non-KWD) | Total in original currency |
| `original_tax` | YES (if non-KWD) | Taxes in original currency |
| `taxes_record` | NO | Tax breakdown string (e.g., "VAT: 5 KWD, Fuel: 10 KWD") |

#### References & Status

| Field | Required | Notes |
|-------|----------|-------|
| `ticket_number` | YES | Last 10 digits of e-ticket (e.g., "2833133219") |
| `reference` | YES | Booking ref/PNR (e.g., "8DROXL") |
| `gds_reference` | NO | GDS booking ref |
| `airline_reference` | NO | Airline's internal ref |
| `client_name` / `passenger_name` | YES | Primary passenger |
| `issued_date` | YES | Issue date (YYYY-MM-DD) |
| `venue` | NO | Route (e.g., "KWI-DXB") |
| `status` | YES | "issued" or "confirmed" or "refund" |
| `additional_info` | NO | Free-text notes |
| `cancellation_policy` | NO | Policy text |

#### Flight Details (Array)

**Per segment in itinerary:**

| Field | Required | Type | Example |
|-------|----------|------|---------|
| `departure_time` | YES | datetime | "2026-07-30 04:35" |
| `arrival_time` | YES | datetime | "2026-07-30 06:05" |
| `airport_from` | YES | string (IATA) | "KWI" |
| `airport_to` | YES | string (IATA) | "DXB" |
| `flight_number` | YES | string | "FZ-054" or "FZ054" |
| `airline` | NO | string (IATA) | "FZ" (inferred from flight_number) |
| `class_type` | NO | string | "Y" (economy) or "J" (business) |
| `terminal_from` | NO | string | "T1" |
| `terminal_to` | NO | string | "2" |
| `baggage_allowed` | NO | string | "1x23kg" |
| `equipment` | NO | string | "B777" (aircraft type) |
| `seat_no` | NO | string | "12A" (if available) |
| `ticket_number` | NO | string | Segment-level ticket |

### Currency Rules

**KWD Only:**
```json
{
  "price": 100.00,
  "tax": 15.00,
  "total": 115.00,
  "exchange_currency": "KWD",
  "is_exchanged": true,
  "original_price": null,
  "original_currency": null
}
```

**KWD + Foreign:**
```json
{
  "price": 25.00,
  "tax": 3.75,
  "total": 28.75,
  "exchange_currency": "KWD",
  "original_price": 85.00,
  "original_currency": "AED",
  "original_total": 90.00,
  "original_tax": 5.00,
  "is_exchanged": true
}
```

**Foreign Only (no KWD amount):**
```json
{
  "price": 0,
  "tax": 0,
  "total": 0,
  "exchange_currency": "KWD",
  "original_price": 85.00,
  "original_currency": "AED",
  "original_total": 90.00,
  "original_tax": 5.00,
  "is_exchanged": false
}
```

### Multi-Passenger Rule

**If booking includes multiple passengers:**

1. **Create separate Task per passenger**
2. **Divide total equally:** `total_per_task = booking_total / passenger_count`
3. **Same flight segments for all:** Copy `task_flight_details` array to each task
4. **Track in additional_info:** Include passenger list in booking

**Example:**
```json
{
  "passengers": ["John Doe", "Jane Doe", "Child Doe"],
  "passenger_count": 3,
  "booking_total": 300.00,
  "passenger_cost": 100.00,
  "flight_segments": [
    { "departure_time": "2026-07-30 04:35", "airport_from": "KWI", ... }
  ]
}
```

**Creates 3 tasks:**
- Task 1: client_name="John Doe", total=100.00
- Task 2: client_name="Jane Doe", total=100.00
- Task 3: client_name="Child Doe", total=100.00

(Same `task_flight_details` array for all 3)

---

## 6. Post-Task Automation

### Full Pipeline (TaskWebhook)

When N8nCallbackController feeds extraction_result into `TaskWebhook::webhook()`, these happen automatically:

#### 1. Invoice Creation (Optional)
```php
// NOT created for FlyDubai (only for Magic Holiday / TBO)
if (in_array($task->supplier_id, [10, 11])) {
    // Create invoice
}
```

#### 2. Journal Entry Creation
```php
// Created if enabled + task is complete
if ($supplierCompany->enable_journal_creation && $task->is_complete) {
    // POST /api/task/{id}/journal → creates journal entries
}
```

#### 3. IATA Wallet Deduction (FlyDubai-Specific)

**Triggered in:** `TaskWebhook::processIataWallet()` (line 576)

**Conditions:**
1. `task->iata_number` is set (from extraction: "42230215")
2. `task->supplier_id == 2` (FlyDubai)
3. `task->issued_by == 'KWIKT211N'` (City Travelers agent ID)
4. `task->iata_number == '42230215'` (City Travelers IATA account)

**When all met:**
```php
// TaskWebhook::processCityTravelersWallet()
→ Find "City Travelers (EasyPay)" account
→ Update task.payment_method_account_id
→ Update journal entries to point to City Travelers account
→ Create Wallet record (opening_balance, task_amount, closing_balance)
→ Send notification to company admin
```

**Wallet Record Tracking:**
```php
Wallet::create([
    'iata_number' => '42230215',
    'currency' => 'KWD',
    'opening_balance' => 5000.00,     // Previous balance
    'task_amount' => 150.00,           // Task total
    'closing_balance' => 4850.00,      // Updated balance
]);
```

#### 4. Status Mapping
```php
// For FlyDubai specifically (in applySupplierSpecificRules)
if ($request->status === 'confirmed' && $supplier_id === 2) {
    $request->status = 'issued';  // Map to system standard
}
```

#### 5. Task Enabled Status

**Task enabled if ALL true:**
```php
$enabled = $task->is_complete
    && $task->agent_id !== null
    && $task->client_id !== null
    && !$task->cancelled;
```

**Task created by n8n extraction will be enabled if:**
- n8n extraction provides valid passenger name (creates/links Client)
- agent_id is set (from upload context)
- all_required_fields are filled

---

## 7. Build Plan — Files to Change

### MODIFY (5 files)

#### 1. `config/services.php` (lines 109-112)

**Current:**
```php
'n8n' => [
    'webhook_url' => env('N8N_WEBHOOK_URL'),
    'webhook_secret' => env('N8N_WEBHOOK_SECRET', 'default-secret'),
],
```

**Change to:**
```php
'n8n' => [
    'webhook_url' => env('N8N_WEBHOOK_URL'),
    'api_token' => env('N8N_API_TOKEN'),
    'base_url' => env('N8N_BASE_URL', 'http://localhost:5678'),
],
```

**Reasoning:** Replace webhook_secret with api_token (Bearer auth instead of HMAC).

---

#### 2. `.env.example`

**Current:**
```bash
# N8n Integration
N8N_WEBHOOK_URL=
N8N_WEBHOOK_SECRET=
```

**Change to:**
```bash
# N8n Integration
N8N_WEBHOOK_URL=https://n8n.example.com/webhook/supplier-document-processing
N8N_API_TOKEN=your-secure-api-token-here
N8N_BASE_URL=https://n8n.example.com
```

---

#### 3. `app/Http/Controllers/Api/DocumentProcessingController.php` (lines 50-61)

**Current:**
```php
$payload = [
    'company_id' => $validated['company_id'],
    'supplier_id' => $validated['supplier_id'],
    'document_id' => $documentId,
    'document_type' => $validated['document_type'],
    'file_path' => $validated['file_path'],
    'file_size_bytes' => $validated['file_size_bytes'] ?? 0,
    'file_hash' => $validated['file_hash'] ?? '',
    'callback_url' => route('api.webhooks.n8n.callback'),
    'timestamp' => $timestamp,
];
```

**Change to (ADD multi-tenant context):**
```php
$payload = [
    'company_id' => $validated['company_id'],
    'supplier_id' => $validated['supplier_id'],
    'agent_id' => $validated['agent_id'] ?? null,           // NEW
    'branch_id' => $validated['branch_id'] ?? null,         // NEW
    'document_id' => $documentId,
    'document_type' => $validated['document_type'],
    'file_path' => $validated['file_path'],
    'file_size_bytes' => $validated['file_size_bytes'] ?? 0,
    'file_hash' => $validated['file_hash'] ?? '',
    'callback_url' => route('api.webhooks.n8n.callback'),
    'timestamp' => $timestamp,
];
```

**And change HTTP call:**
```php
// OLD: Post with no auth
$response = Http::timeout(10)->post($n8nWebhookUrl, $payload);

// NEW: Post with Bearer token
$token = config('services.n8n.api_token');
$response = Http::withToken($token)
    ->timeout(10)
    ->post($n8nWebhookUrl, $payload);
```

---

#### 4. `app/Http/Controllers/Api/Webhooks/N8nCallbackController.php`

**MAJOR CHANGE: After updating log, feed extraction_result into TaskWebhook pipeline**

**Current (lines 70-88):**
```php
if ($validated['status'] === 'success') {
    $updateData['extraction_result'] = $validated['extraction_result'] ?? null;
} else {
    $updateData['error_code'] = $validated['error']['code'] ?? 'ERR_UNKNOWN';
    $updateData['error_message'] = $validated['error']['message'] ?? 'Unknown error';
    $updateData['error_context'] = $validated['error']['context'] ?? null;
}

$log->update($updateData);

// If failed, dispatch notification (placeholder for Phase 3)
if ($validated['status'] === 'error') {
    // ... logging ...
}
```

**Change to (FEED INTO TASKWEBHOOK):**
```php
if ($validated['status'] === 'success') {
    $updateData['extraction_result'] = $validated['extraction_result'] ?? null;
} else {
    $updateData['error_code'] = $validated['error']['code'] ?? 'ERR_UNKNOWN';
    $updateData['error_message'] = $validated['error']['message'] ?? 'Unknown error';
    $updateData['error_context'] = $validated['error']['context'] ?? null;
}

$log->update($updateData);

// NEW: After successful extraction, feed into TaskWebhook pipeline
if ($validated['status'] === 'success' && $validated['extraction_result']) {
    try {
        // Build request from extraction_result + context
        $extractionData = $validated['extraction_result'];

        // Ensure required multi-tenant fields are present
        $taskPayload = array_merge($extractionData, [
            'company_id' => $log->company_id,
            'supplier_id' => $log->supplier_id,
            'agent_id' => $extractionData['agent_id'] ?? null,
            'branch_id' => $extractionData['branch_id'] ?? null,
            'user_id' => $extractionData['user_id'] ?? null,
            'is_n8n_booking' => true,  // Flag this flow
        ]);

        // Create a fake Request object for TaskWebhook validation
        $request = new Request($taskPayload);
        $request->setMethod('POST');

        // Call TaskWebhook::webhook() — uses existing full pipeline
        $taskWebhook = app(\App\Http\Webhooks\TaskWebhook::class);
        $webhookResponse = $taskWebhook->webhook($request);

        // Log success/failure
        if ($webhookResponse->getData(true)['status'] === 'success') {
            Log::info('N8n extraction fed into TaskWebhook successfully', [
                'document_id' => $validated['document_id'],
                'task_id' => $webhookResponse->getData(true)['data']['task_id'] ?? null,
            ]);
        } else {
            Log::error('N8n extraction failed TaskWebhook processing', [
                'document_id' => $validated['document_id'],
                'errors' => $webhookResponse->getData(true),
            ]);
        }
    } catch (\Exception $e) {
        Log::error('Error processing N8n extraction in TaskWebhook', [
            'document_id' => $validated['document_id'],
            'error' => $e->getMessage(),
        ]);
    }
}

// If failed, dispatch notification
if ($validated['status'] === 'error') {
    // ... existing error handling ...
}
```

---

#### 5. `routes/api.php` (lines 156-164)

**Current:**
```php
Route::post('/documents/process', [DocumentProcessingController::class, 'store'])->name('api.documents.process');
Route::post('/webhooks/n8n/extraction', [N8nCallbackController::class, 'handle'])->name('api.webhooks.n8n.callback');
```

**Change to (Add middleware):**
```php
Route::post('/documents/process', [DocumentProcessingController::class, 'store'])
    ->middleware('verify.n8n.token')  // NEW
    ->name('api.documents.process');

Route::post('/webhooks/n8n/extraction', [N8nCallbackController::class, 'handle'])
    ->middleware('verify.n8n.token')  // NEW
    ->name('api.webhooks.n8n.callback');
```

---

### CREATE (2 files)

#### 1. `app/Http/Middleware/VerifyN8nToken.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyN8nToken
{
    public function handle(Request $request, Closure $next)
    {
        // Get token from Authorization header
        $providedToken = $request->bearerToken();
        $expectedToken = config('services.n8n.api_token');

        // If no token configured, allow (development only)
        if (!$expectedToken) {
            return $next($request);
        }

        // Verify token matches
        if (!$providedToken || !hash_equals($providedToken, $expectedToken)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid N8n API token',
            ], 401);
        }

        return $next($request);
    }
}
```

**Registration in `app/Http/Kernel.php`:**
```php
protected $routeMiddleware = [
    // ...
    'verify.n8n.token' => \App\Http\Middleware\VerifyN8nToken::class,
];
```

---

#### 2. Migration: Add `auto_process_pdf` to `supplier_companies`

**File:** `database/migrations/2026_03_10_000000_add_auto_process_pdf_to_supplier_companies_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_companies', function (Blueprint $table) {
            $table->boolean('auto_process_pdf')
                ->default(false)
                ->after('is_active')
                ->comment('Auto-process PDF files via n8n webhook for this supplier/company combo');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_companies', function (Blueprint $table) {
            $table->dropColumn('auto_process_pdf');
        });
    }
};
```

**Run:**
```bash
php artisan migrate
```

---

### NO CHANGES NEEDED

These files stay as-is; the new n8n flow reuses existing code:

| File | Why No Change |
|------|---------------|
| `app/Http/Webhooks/TaskWebhook.php` | Full pipeline already handles n8n-extracted data (validation, task creation, IATA wallet, journals) |
| `app/Models/Task.php` | Model already has all required fields (price, tax, total, status, reference, etc.) |
| `app/Models/TaskFlightDetail.php` | Flight segments work for any extraction method |
| `app/Services/AirFileParser.php` | AIR flow stays separate; n8n flow skips AirFileParser |
| `app/Console/Commands/ProcessAirFiles.php` | Scheduled processing for traditional uploads still works |
| Frontend upload form | Same `/tasks/upload` endpoint used; no UI changes needed |
| `n8n/workflows/supplier-document-processing.json` | Handled separately (n8n admin configures callback URL, auth token, etc.) |

---

## 8. Existing Infrastructure to Reuse

### Tested & Production-Ready

| Component | Purpose | How Reused |
|-----------|---------|-----------|
| **TaskWebhook::webhook()** | Full task creation pipeline (validation → task → flight details → surcharges → financials → IATA wallet → journals) | Feed n8n extraction_result into this method; ALL automation happens automatically |
| **DocumentProcessingLog** | Tracks document processing state (queued → processing → completed) | Already used; extends with extraction_result storage |
| **POST /api/task/webhook** | TaskWebhook endpoint | Already exists; reused by N8nCallbackController |
| **IATA wallet automation** | Deducts from City Travelers wallet when supplier_id=2 + issued_by='KWIKT211N' | Automatic; no changes needed |
| **Multi-tenant context derivation** | Derive company/branch/agent from authenticated user | Reuse pattern in DocumentProcessingController |
| **FileUpload model** | Track uploaded files | Extends with n8n_document_id link |

### Data Flow Overview

```
Agent uploads PDF
     ↓
TaskController::upload() validates, stores file
Creates FileUpload record (pending)
     ↓
Check auto_process_pdf setting
     ↓
IF enabled:
  DocumentProcessingController::store() called
  Creates DocumentProcessingLog (queued)
  POSTs to n8n webhook with Bearer token
     ↓
  n8n processes PDF (external VPS)
  Extracts structured data
     ↓
  POSTs callback to /api/webhooks/n8n/extraction
  Includes Bearer token in Authorization header
     ↓
  N8nCallbackController::handle()
  Updates DocumentProcessingLog (completed/failed)
  IF success: feeds extraction_result into TaskWebhook pipeline
     ↓
  TaskWebhook::webhook()
  Validates extraction data
  Creates Task + TaskFlightDetail
  Applies supplier rules (status mapping, IATA wallet, etc.)
  Creates journal entries
  Deducts IATA wallet
  Sets task enabled status
  DONE
```

---

## 9. Key File Locations

### Configuration & Routes
| File | Lines | Purpose |
|------|-------|---------|
| `config/services.php` | 109-112 | n8n webhook URL + API token config |
| `.env.example` | ~196 | Environment variable templates |
| `routes/api.php` | 156-164 | Webhook route definitions |

### Controllers
| File | Lines | Purpose |
|------|-------|---------|
| `app/Http/Controllers/Api/DocumentProcessingController.php` | full | Queue documents to n8n |
| `app/Http/Controllers/Api/Webhooks/N8nCallbackController.php` | full | Handle n8n callbacks + feed into TaskWebhook |
| `app/Http/Controllers/TaskController.php` | 3000-3334 | Main upload() method |

### Models & Webhooks
| File | Lines | Purpose |
|------|-------|---------|
| `app/Http/Webhooks/TaskWebhook.php` | 1-800+ | Full task creation pipeline (validation, flight details, surcharges, IATA wallet) |
| `app/Models/Task.php` | full | Task model with all fields |
| `app/Models/TaskFlightDetail.php` | full | Flight segment model |
| `app/Models/DocumentProcessingLog.php` | full | Processing state tracking |
| `app/Models/FileUpload.php` | full | File upload tracking |
| `app/Models/Supplier.php` | full | Supplier master data |
| `app/Models/SupplierCompany.php` | full | Pivot table (supplier + company settings) |

### Middleware (New)
| File | Purpose |
|------|---------|
| `app/Http/Middleware/VerifyN8nToken.php` | Bearer token verification |

### Schemas (Reference)
| File | Lines | Purpose |
|------|-------|---------|
| `app/Schema/TaskSchema.php` | 1-600+ | Task data normalization schema (field types, required fields) |
| `app/Schema/TaskFlightSchema.php` | 1-145 | Flight segment schema |

### n8n Files
| File | Purpose |
|------|---------|
| `n8n/workflows/supplier-document-processing.json` | Main n8n workflow (configurable separately) |

---

## 10. Open Decisions

### Decision 1: N8n-Side PDF Extraction

**Question:** How should n8n read the PDF file?

**Options:**

A. **Shared volume mount** (Simple)
- n8n Docker has volume mount: `/var/www/storage/app:ro`
- Read file directly: `/var/www/storage/app/city_travelers/fly_dubai/files_unprocessed/booking.pdf`
- **Pros:** No extra API call, fast
- **Cons:** Requires volume setup in docker-compose, read-only permission

B. **Laravel API endpoint** (Flexible)
- n8n calls `GET /api/documents/{documentId}/file` with Bearer token
- Laravel serves file content (signed URL or direct download)
- **Pros:** No volume mount required, works for cloud storage (S3), more secure
- **Cons:** Extra API call, more code in Laravel

C. **n8n direct HTTP call to extract text** (User's choice)
- User configures n8n to call external PDF extraction service (Tika, etc.)
- Out of scope for this research

**Recommendation:** Option A (volume mount) for MVP; later migrate to Option B for cloud-native.

---

### Decision 2: Multi-Passenger Handling

**Question:** How should n8n handle bookings with multiple passengers?

**Current assumption:** n8n extraction returns single passenger_name + passenger_count, and Laravel creates separate tasks.

**Alternative:** n8n creates multiple extraction results (one per passenger), each with divided costs.

**Recommendation:** n8n returns single result with `passenger_count` field; Laravel's TaskWebhook handles task duplication.

---

### Decision 3: Fallback Behavior

**Question:** What if n8n callback never arrives (timeout, network error)?

**Current design:**
- DocumentProcessingLog stays in 'processing' state
- File stays in `files_unprocessed/`
- No manual recovery path yet

**Future enhancement (Phase 3):**
- Add manual recovery UI: admin can retry callback
- Add timeout check: if processing > 5 minutes, send alert
- Add manual task creation form: bypass n8n, create task directly

---

## 11. Sources

### Primary Research Files (HIGH confidence)

| File | Content | Relevance |
|------|---------|-----------|
| `.planning/quick/01-n8n-flydubai-extraction-research.md` | N8n webhook processing, deferred status, HMAC signatures | n8n architecture, callback format |
| `.planning/quick/flydubai-supplier-research.md` | Flydubai supplier ID=2, IATA wallet processing, shared AirFileParser | Supplier identity, IATA logic |
| `.planning/quick/flydubai-file-upload-method-research.md` | Current upload flow, shouldUseAirFileParser() check, PDF vs AIR processing | Current state details, why AI extraction used |
| `.planning/quick/n8n-integration-research.md` | N8n workflow files, webhook routes, supplier ID mapping, error codes | n8n infrastructure, routing logic |
| `.planning/quick/webhook-urls-research.md` | Production URLs, environment config, N8N_WEBHOOK_URL gap, callback route | Security decisions, config details |
| `.planning/quick/pdf-upload-flow-research.md` | Upload endpoints, storage paths, FileUpload model, processing command | Upload flow, data storage |

### Code Sources (Direct inspection)

| File | Lines/Purpose | Usage |
|------|--------------|-------|
| `app/Http/Controllers/Api/DocumentProcessingController.php` | Full file | Query document queueing logic, auth method |
| `app/Http/Controllers/Api/Webhooks/N8nCallbackController.php` | Full file | Query callback handling, update mechanism |
| `app/Http/Webhooks/TaskWebhook.php` | 576-704 | IATA wallet processing logic |
| `app/Http/Controllers/TaskController.php` | 3000-3334 | Upload flow, supplier checking |
| `config/services.php` | 109-112 | Current n8n config |
| `routes/api.php` | 156-164 | Route definitions |
| `database/migrations/2025_*` | Multiple | Database schema understanding |
| `n8n/workflows/supplier-document-processing.json` | Full file | N8n workflow structure, routing logic |

### Research Date & Validity

- **Researched:** 2026-03-10
- **Valid until:** 2026-04-10 (30 days for stable Laravel/N8n architecture)
- **Confidence Level:** HIGH (based on code inspection, multiple verification points)

---

## Appendix: Implementation Checklist

### Phase 1: Setup & Configuration (Week 1)

- [ ] Add `N8N_API_TOKEN` and `N8N_BASE_URL` to production `.env`
- [ ] Update `.env.example` with n8n variables
- [ ] Modify `config/services.php` to use api_token instead of webhook_secret
- [ ] Create migration: add `auto_process_pdf` column
- [ ] Create `VerifyN8nToken` middleware
- [ ] Register middleware in `Kernel.php`

### Phase 2: Laravel Code Changes (Week 2)

- [ ] Update `DocumentProcessingController::store()` to use Bearer token + add agent_id/branch_id
- [ ] Update `routes/api.php` to apply `verify.n8n.token` middleware
- [ ] Refactor `N8nCallbackController::handle()` to feed extraction into TaskWebhook
- [ ] Test TaskWebhook reuse with mocked n8n payload

### Phase 3: N8n Configuration (Week 2-3, n8n admin)

- [ ] Configure n8n workflow to receive Laravel Bearer token
- [ ] Set up PDF text extraction (Tika or similar)
- [ ] Implement FlyDubai-specific regex/AI extraction
- [ ] Configure callback URL + Bearer token in HTTP request node
- [ ] Test end-to-end with sample PDF

### Phase 4: Testing & Validation (Week 3-4)

- [ ] Unit tests: VerifyN8nToken middleware
- [ ] Integration tests: DocumentProcessingController + N8nCallbackController
- [ ] End-to-end test: Upload FlyDubai PDF → n8n processes → Task created + IATA wallet deducted
- [ ] Test multi-passenger bookings
- [ ] Test failure scenarios (invalid extraction, callback timeout)

### Phase 5: Deployment & Monitoring (Week 4)

- [ ] Deploy to production with feature flag: `auto_process_pdf` disabled by default
- [ ] Enable for test company first
- [ ] Monitor logs: `storage/logs/laravel.log`, `storage/logs/n8n.log`
- [ ] Monitor Wallet records: verify IATA deductions correct
- [ ] Enable for all companies (gradual rollout)

---

**End of Research Document**

Generated: 2026-03-10
Status: Ready for implementation phase
