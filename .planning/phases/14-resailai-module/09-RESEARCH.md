# Phase 09: ResailAI Module — Research Summary

**Phase Date:** 2026-03-11
**Status:** RESEARCH COMPLETE — Ready for GSD planning
**Research Source:** `.planning/quick/resailai-module-research.md`

---

## 1. Overview

Self-contained Laravel module that handles automated document processing for ANY supplier and ANY task type (flight, hotel, visa, insurance). Documents are uploaded from Laravel, sent to ResailAI extraction service via n8n webhook, and results feed back into the existing TaskWebhook pipeline to create tasks automatically.

**Branding:** ResailAI (external processing service is hidden from clients)

---

## 2. Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Self-contained module | Zero existing file modifications, clean separation | Module at `app/Modules/ResailAI/` |
| Queue-based processing | Non-blocking for users, handles concurrent uploads | `ProcessDocumentJob` for async processing |
| Generic module | Works for any supplier (42 found) and any task type | Single module handles all suppliers |
| Feature flag | Opt-in per company+supplier to avoid breaking existing flows | `auto_process_pdf` toggle on pivot table |
| Reuse TaskWebhook | No new task creation logic, leverages existing pipeline | Bridge calls `TaskWebhook::webhook()` internally |
| Bearer token auth | Simple server-to-server authentication | `RESAILAI_API_TOKEN` in .env |
| UUID file naming | No collision risk on parallel uploads | Files stored with UUID prefix |

---

## 3. Architecture

```
app/Modules/ResailAI/
├── Providers/ResailAIServiceProvider.php     — bootstraps routes, config, middleware
├── Http/Controllers/
│   ├── CallbackController.php                 — receives extraction results from n8n
│   └── Api/
│       ├── AdminController.php                — API key generation (admin only)
│       └── ResailAIController.php             — Feature flag toggle UI (admin only)
├── Services/
│   ├── ProcessingAdapter.php                  — orchestrator + feature flag check
│   └── TaskWebhookBridge.php                  — transforms extraction → Request → TaskWebhook
├── Jobs/ProcessDocumentJob.php                — queued document processing
├── Middleware/
│   ├── VerifyResailAIToken.php                — Bearer token auth for callbacks
│   └── ResailAIPdfProcessor.php               — intercepts PDF uploads
├── Routes/
│   └── routes.php                             — module routes (loaded by provider)
├── Config/
│   └── resailai.php                           — module configuration
└── database/
    └── migrations/
        └── 2026_03_11_000000_add_auto_process_pdf_to_supplier_companies.php
```

---

## 4. Processing Flow

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
ResailAI service processes PDF (extraction on external n8n VPS)
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

## 5. File Upload Intercept Flow

```
POST /tasks/upload (TaskController)
    ↓
File stored with UUID prefix: storage/app/{company}/{supplier}/files_unprocessed/{uuid}_filename.pdf
    ↓
DocumentProcessingLog created (status: queued)
    ↓
Check: auto_process_pdf enabled on supplier_companies?
    ↓ YES
Dispatch ProcessDocumentJob to queue
    ↓ Job runs async (user sees instant "Upload queued" response)
    ↓
Job retrieves document context (company_id, supplier_id, agent_id, branch_id)
    ↓
Send to ResailAI n8n webhook:
POST {N8N_WEBHOOK_URL}
Authorization: Bearer {RESAILAI_API_TOKEN}
Payload: {company_id, supplier_id, agent_id, branch_id, file_path, callback_url}
    ↓
n8n on external VPS processes PDF (extraction)
    ↓
n8n POSTs callback to Laravel:
POST /api/modules/resailai/callback
Authorization: Bearer {RESAILAI_API_TOKEN}
Payload: {document_id, status, extraction_result}
    ↓
CallbackController validates token
    ↓
ProcessingAdapter checks feature flag
    ↓
TaskWebhookBridge creates Request from extraction_result
    ↓
TaskWebhook::webhook($request) creates task via existing pipeline
```

---

## 6. Supplier-Specific Rules (TaskWebhook handles these)

| Supplier | Task Type | Special Rules |
|----------|-----------|---------------|
| Jazeera Airways | Flight | Status mapping (confirmed→issued), auto-void 48h, task rules |
| FlyDubai | Flight | IATA wallet (issued_by=KWIKT211N), status mapping |
| Smile Holidays | Hotel | Batch merge before ResailAI (2+ PDFs → 1), prefix SMIL |
| VFS | Visa | Status mapping, 6 visa detail fields |
| First Takaful | Insurance | 1 task per policy (NOT per person), 8 insurance detail fields |
| NDC Suppliers (29,38,39) | Flight | IATA wallet support |

---

## 7. Key Requirements

| ID | Requirement | Files Modified | Notes |
|----|-------------|----------------|-------|
| RESAIL-01 | Module self-contained at `app/Modules/ResailAI/` | New | Zero existing file changes |
| RESAIL-02 | Migration adds `auto_process_pdf` to `supplier_companies` | 1 migration | Feature flag per company+supplier |
| RESAIL-03 | Middleware intercepts PDF uploads | New | `ResailAIPdfProcessor.php` |
| RESAIL-04 | Queue job for document processing | New | `ProcessDocumentJob.php` |
| RESAIL-05 | TaskWebhookBridge for task creation | New | `TaskWebhookBridge.php` |
| RESAIL-06 | CallbackController for n8n callbacks | New | `CallbackController.php` |
| RESAIL-07 | Bearer token auth for callbacks | Middleware | `VerifyResailAIToken.php` |
| RESAIL-08 | Admin API key generation | New controller | Encrypted storage, displayed once |
| RESAIL-09 | Config merged via ServiceProvider | New config | Module config file |
| RESAIL-10 | Routes loaded via ServiceProvider | New routes | Routes defined in provider |

---

## 8. Database Changes

### Migration: Add `auto_process_pdf` to `supplier_companies`

```php
Schema::table('supplier_companies', function (Blueprint $table) {
    $table->boolean('auto_process_pdf')
        ->default(false)
        ->after('is_active')
        ->comment('Auto-process PDF files via ResailAI webhook for this supplier/company combo');
});
```

---

## 9. Environment Variables

```env
# ResailAI Module
RESAILAI_API_TOKEN=your-secure-api-token-here
N8N_WEBHOOK_URL=https://n8n.example.com/webhook/resailai-process
```

---

## 10. Critical Requirements

1. **agent_id MUST be in payload** — Required for task enabled status and agent linking
2. **client_id or AutoBilling match data** — Required for client linking
3. **exchange_currency + exchange_rate** — If non-KWD currency, required for conversion
4. **original_reference + passenger_name** — Required for refund/void original task linking
5. **issued_by preserved** — For Como Travels, must be preserved in extraction

---

## 11. Concurrency Solutions

| Problem | Solution |
|---------|----------|
| No unique constraint on tasks | Duplicate check inside DB transaction |
| File naming collision | UUID prefix on all uploads |
| No rate limiting | Throttle middleware on module routes |
| No callback retry | Stuck document sweeper command |
| No idempotency | Idempotency key on callbacks |

---

## 12. Edge Cases Resolved

| Edge Case | Status | Action Required |
|-----------|--------|-----------------|
| NDC IATA Wallet (29,38,39) | WORKS | None |
| Smile Holiday batch merge | WORKS | Merge BEFORE ResailAI |
| Multi-passenger flights | WORKS | Bridge loops multiple calls |
| AIR files | NOT INTERCEPTED | AIR detector prevents |
| AutoBilling Matching | WORKS | ResailAI must provide agent_id |
| Task Enabled Status | CRITICAL | Must provide agent_id + client_id |

---

## 13. Files NOT Modified

These existing files stay as-is (module is self-contained):

| File | Reason |
|------|--------|
| `app/Http/Webhooks/TaskWebhook.php` | Full pipeline already handles extraction data |
| `app/Models/Task.php` | Model already has all required fields |
| `app/Services/AirFileParser.php` | AIR flow stays separate |
| `app/Console/Commands/ProcessAirFiles.php` | Traditional processing still works |
| `routes/api.php` | Module loads its own routes |

---

## 14. 42 Suppliers Found

| Seeded (5) | N8n Routed (12) | AI Extraction Hints (20+) |
|------------|-----------------|---------------------------|
| Amadeus (ID: 2) | Jazeera Airways (1) | Airlines: Cebu Pacific, SalamAir, Wizz Air, AirCairo, Emirates |
| Magic Holiday | FlyDubai (2) | Hotels: Smile Holidays, Bella Vita, World of Luxury |
| TBO Holiday | ETA UK (3) | Visas: London Visa, BLS Spain Visa |
| DOTW | The Skyrooms (4) | Insurance: First Takaful |
| Rate Hawk | Air Arabia (5) | Car: TBO Car |

---

## 15. Next Steps

After research complete, build in 5 waves:

| Wave | Phase | Deliverables |
|------|-------|--------------|
| 1 | Foundation | Migration, model, service, config |
| 2 | Middleware & Job | PDF processor, queue job, bridging |
| 3 | Admin UI v1 | API key generation, admin controller |
| 4 | Admin UI v2 | Feature flag toggle UI (enable/disable per supplier) |
| 5 | Testing | Verification, documentation |

---

*Research compiled: 2026-03-11*
*Source: `.planning/quick/resailai-module-research.md`*
*Status: Ready for Phase Planning*
