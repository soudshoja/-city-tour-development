# Project Research Summary

**Project:** Bulk Invoice Upload for Travel Agency Platform
**Domain:** Laravel multi-tenant B2B invoice management with Excel import
**Researched:** 2026-02-12
**Confidence:** HIGH

## Executive Summary

This feature adds bulk invoice creation from Excel uploads to an existing Laravel 11 multi-tenant travel agency platform. The research reveals a well-trodden path: all required libraries (maatwebsite/excel 3.1.x, barryvdh/laravel-dompdf 3.1.0, Laravel queues) are already installed and working in the codebase. This is **not** a greenfield stack decision but an integration project leveraging proven patterns already used for TasksImport and ClientsImport.

The recommended approach follows a preview-approve workflow pattern standard across enterprise systems: upload → validate → preview (grouped by client) → approve → background job creation → PDF generation → email delivery. This pattern mitigates the primary risk of bulk operations (data quality errors) by requiring human review before commit. Industry research confirms this as table stakes for batch invoice systems, with QuickBooks, NetSuite, and Zoho all implementing preview-before-commit.

The critical risks are multi-tenant data leakage (missing company_id isolation), race conditions in invoice number generation, and CSV injection vulnerabilities. All three are preventable through established Laravel patterns: scoped queries with company_id, lockForUpdate() for sequences, and formula sanitization on input. The accounting integration adds complexity (journal entries, general ledger updates) requiring atomic transactions, but the codebase already demonstrates this pattern in InvoiceController::store(). **Success depends on disciplined application of existing patterns, not new technology.**

## Key Findings

### Recommended Stack

All required technology already exists in the codebase. Zero new dependencies needed for MVP. The stack centers on three proven libraries already integrated: maatwebsite/excel 3.1.x for Excel parsing with Laravel validation concerns (WithValidation, SkipsOnFailure), barryvdh/laravel-dompdf 3.1.0 for PDF generation (already used in InvoiceController line 41), and Laravel 11's native queue system with job batching for background processing.

**Core technologies:**
- **maatwebsite/excel 3.1.x**: Excel import/export with row-level validation — Industry standard, already used for TasksImport/ClientsImport in codebase, Laravel 11 compatible
- **barryvdh/laravel-dompdf 3.1.0**: PDF invoice generation — Already integrated and working in InvoiceController, no setup needed
- **Laravel Queue + Job Batches**: Background bulk processing — Native Laravel 11 feature, database driver already configured, handles 100-1000 invoices comfortably
- **Laravel Mail**: Email delivery with PDF attachments — Already configured with SMTP/SES/Postmark, supports queueing
- **PhpSpreadsheet 1.30.x**: Underlying Excel engine — Current version sufficient, optional upgrade to 5.x not required

**Key insight from STACK.md:** "All required libraries are already installed and compatible with Laravel 11. Focus is on patterns and integration rather than new dependencies." The implementation complexity is in orchestration (validation, grouping, error handling), not in adding new packages.

### Expected Features

Research into batch invoice systems (QuickBooks Online, NetSuite, Zoho, Malaysia e-Invoice portals) reveals consistent expectations: preview before commit, row-level validation with clear error messages, downloadable error reports, and one-invoice-per-client grouping as the simple default. Users absolutely expect to see what will be created before it's permanent.

**Must have (table stakes):**
- Excel file upload with pre-validation (headers, file format) — Users expect spreadsheet support as industry standard
- Row-level validation (required fields, enums, supplier exists) — Each row must validate before preview
- Client matching by (company_id, phone) with exact match only — Travel agencies use mobile as primary ID
- Preview before commit showing "X invoices for Y clients" summary — Critical for catching errors, prevents "oh no" moments
- Clear error messages with row numbers and actionable guidance — "Row 5: Supplier 'ABC Tours' not found" not "Invalid data"
- Downloadable error report (Excel with failures) — For large uploads with many errors
- One invoice per client grouping — Natural grouping, matches manual workflow
- Invoice PDF generation + email to accountant and agent — Auto-delivery after creation
- Upload history tracking for audit trail — Track upload_id, filename, invoice_ids created

**Should have (competitive):**
- Template download (pre-formatted Excel with headers) — Trigger: "what format?" questions repeatedly
- Real-time upload progress for 100+ row files — Better UX, prevents page abandonment
- Duplicate detection warnings (same client + task + date + amount) — Prevents duplicate invoices from re-uploads
- Inline error editing (fix validation errors in preview table) — Reduces re-upload cycle frustration
- Column auto-mapping (detect headers even if order differs) — Handles "Mobile" vs "Phone" vs "Contact" variations

**Defer (v2+):**
- Copy/paste from clipboard (skip file upload) — Nice-to-have, Excel upload sufficient
- Invoice grouping options (per supplier, per task type, per date) — Simple "per client" works for 90% of cases
- Fuzzy client matching (suggest matches for typos) — High complexity, manual review queue simpler for MVP
- Partial commit on errors (create valid invoices, queue failed rows) — May confuse users, preview-before-commit prevents need

**Anti-features (commonly requested, problematic):**
- Auto-create clients from Excel — Creates duplicates, violates data quality requirements, requires manual review instead
- Edit existing invoices via upload — Dangerous for accounting, breaks audit trail, should be UI-only operation
- Real-time spreadsheet editing in browser — Massive complexity, reinventing Excel, not justified

### Architecture Approach

The architecture integrates bulk upload as a parallel entry point to existing manual invoice creation, reusing InvoiceController logic without disruption. The pattern is: new BulkInvoiceUploadController handles HTTP upload/preview/approve → InvoiceUploadService orchestrates validation and grouping → InvoiceTasksImport parses Excel with Laravel-Excel concerns → CreateBulkInvoicesJob processes in background using existing invoice creation patterns. A new InvoiceUpload model tracks upload sessions and stores preview_data as JSON for the approval workflow.

**Major components:**
1. **BulkInvoiceUploadController** (new) — Handles upload/preview/approve HTTP endpoints, stores files to storage/app/uploads/invoices/{company_id}/
2. **InvoiceUploadService** (new) — Orchestrates validation, client matching by (company_id, phone), supplier validation, preview generation with invoice grouping
3. **InvoiceTasksImport** (new) — Laravel-Excel import class implementing ToCollection, WithValidation, SkipsOnFailure to parse and validate without database writes
4. **CreateBulkInvoicesJob** (new) — Queue job for background invoice creation, PDF generation, email delivery, reuses InvoiceController::store logic
5. **InvoiceUpload model + table** (new) — Tracks upload status (pending → validated → processing → completed), stores preview_data JSON, audit trail
6. **Existing InvoiceController, InvoiceSequence, InvoiceMail** (reuse) — No changes needed, called from job for actual invoice creation and delivery

**Key architectural decision from ARCHITECTURE.md:** Preview-approve workflow with two-phase commit. Excel parses to in-memory collection (no DB writes), validation runs and stores results in preview_data JSON, user reviews and approves, then background job performs actual creation. This prevents orphaned invoices if approval rejected and provides audit trail of what was approved.

**Integration points:** Reuse InvoiceSequence for invoice numbering (must add lockForUpdate() to prevent race conditions), reuse existing Client/Supplier/Task models with company_id scoping, reuse InvoiceMail for PDF email delivery, leverage existing queue infrastructure with database driver.

**Build order (from ARCHITECTURE.md):** Phase 1: invoice_uploads table and InvoiceUpload model → Phase 2: InvoiceTasksImport with validation → Phase 3: InvoiceUploadService orchestration → Phase 4: BulkInvoiceUploadController and views → Phase 5: CreateBulkInvoicesJob → Phase 6: PDF/email integration → Phase 7: cleanup and audit trail.

### Critical Pitfalls

1. **Multi-tenant data leakage via missing company_id isolation** — Client matching by phone alone can match clients from different companies, allowing cross-tenant invoice creation. Prevention: Always include `where('company_id', $companyId)` in ALL queries (Client, Supplier, InvoiceSequence). Existing imports (TasksImport, ClientsImport) don't enforce this, making it an easy mistake to repeat.

2. **Race condition in invoice number generation** — Concurrent uploads or parallel queue workers create duplicate invoice numbers when using firstOrCreate() + manual increment pattern (current InvoiceController lines 366-371). Prevention: Use `lockForUpdate()` on InvoiceSequence or Cache::lock() around entire bulk upload. Bulk uploads multiply this risk (50 invoices = 50 sequence increments).

3. **Incomplete transaction rollback with double-entry accounting** — Simple transaction rollback can leave orphaned journal entries, broken general ledger balances, inconsistent accounting data. Prevention: Wrap ENTIRE bulk upload in single DB::transaction(), do NOT use batch inserts (WithBatchInserts breaks atomicity), verify journal entry integrity after any failure.

4. **CSV injection via Excel formula exploitation** — Task descriptions starting with `=`, `+`, `-`, `@`, `|` execute as formulas when accountant opens error report Excel, leaking data or executing commands. Prevention: Sanitize ALL user input by prefixing dangerous characters with single quote `'` in prepareForValidation() method, apply to both storage AND exports.

5. **Memory exhaustion on large Excel files** — PhpSpreadsheet loads entire sheet into memory by default. 5,000-row file crashes at ~2,800 rows with "Allowed memory size exhausted". Prevention: Implement WithChunkReading (100 rows at a time), queue imports >500 rows, set memory_limit=512M.

6. **Client matching ambiguity with non-unique phone numbers** — Phone field has no uniqueness constraint, manual entry over years created duplicates. Excel contains "99887766", database has TWO clients in same company with this phone, import silently picks first, wrong invoice created. Prevention: Detect multiple matches during validation, flag for manual review with disambiguation UI.

7. **Timeout on slow journal entry creation** — 200 invoices × 5 tasks × 4 DB queries per journal entry = 4,000 queries. PHP max_execution_time expires at invoice #127. Prevention: Queue journal entry creation as separate jobs OR optimize with bulk inserts OR increase timeout to 300 seconds for bulk operations.

**Pitfall-to-phase mapping:** All 7 pitfalls must be addressed in Phase 1 (Validation) and Phase 2 (Bulk Creation). Multi-tenant isolation, CSV injection, and client ambiguity are validation concerns. Race conditions, transactions, memory, and timeouts are creation concerns.

## Implications for Roadmap

Based on research, the feature breaks into 4 core phases following the data flow: foundation → validation → creation → delivery. The architecture research (ARCHITECTURE.md lines 563-650) explicitly defines build order based on dependencies, and the pitfalls research maps critical issues to phases for prevention.

### Phase 1: Data Foundation & Validation
**Rationale:** All other components depend on InvoiceUpload model and InvoiceTasksImport validation logic. This phase establishes the upload tracking mechanism and proves validation works before building UI.
**Delivers:** Database table for upload tracking, Excel import class with row-level validation, preview data generation, error collection
**Addresses (from FEATURES.md):** Excel file upload, pre-upload validation, row-level validation, client matching by phone, flag unknown clients, clear error messages, downloadable error report
**Avoids (from PITFALLS.md):** Multi-tenant data leakage (enforce company_id in all queries), CSV injection (sanitize formulas on input), client matching ambiguity (detect duplicates, flag for review), memory exhaustion (implement chunk reading)
**Stack elements:** maatwebsite/excel WithValidation + SkipsOnFailure concerns, Laravel validation rules, InvoiceUpload model with JSON preview_data column
**Technical notes:** Must implement sanitizeFormulaInjection() in prepareForValidation(), detect ambiguous client matches (count > 1), chunk reading for files >500 rows, store validation results in preview_data JSON

### Phase 2: UI & Preview Workflow
**Rationale:** User interface comes after validation logic is proven. Preview display depends on Phase 1's preview_data structure. This phase completes the "validate → preview → approve" user journey.
**Delivers:** Upload form, preview page showing invoice summary grouped by client, approve/reject actions, status polling endpoint
**Addresses (from FEATURES.md):** Preview before commit, success confirmation, upload history tracking
**Uses (from STACK.md):** Laravel routes + controllers, Blade views (or Livewire if real-time progress needed)
**Implements (from ARCHITECTURE.md):** BulkInvoiceUploadController with upload()/preview()/approve() actions, InvoiceUploadService orchestration layer
**Technical notes:** Show "X invoices for Y clients" summary, flag unknown clients in red, display validation errors with row numbers, [Approve] button dispatches job, store file to storage/app/uploads/invoices/{company_id}/

### Phase 3: Background Invoice Creation
**Rationale:** This is the highest-risk phase (accounting integrity, transactions, race conditions). Requires Phases 1-2 complete so preview_data structure is finalized and UI can trigger jobs.
**Delivers:** Queue job that creates invoices from approved preview_data, generates invoice numbers with race condition prevention, creates invoice details, handles partial success
**Addresses (from FEATURES.md):** One invoice per client grouping, existing data protection (duplicate prevention)
**Avoids (from PITFALLS.md):** Race condition in invoice numbers (use lockForUpdate()), incomplete transaction rollback (single transaction wrapping), timeout on journal entries (queue separately or optimize bulk inserts)
**Uses (from STACK.md):** Laravel Queue + Job Batches, InvoiceSequence with atomic locking, DB::transaction() for atomicity
**Implements (from ARCHITECTURE.md):** CreateBulkInvoicesJob with individual try-catch per invoice for partial success, result_data tracking, integration with InvoiceController::store logic
**Technical notes:** Must use Cache::lock("invoice-upload-{$companyId}") OR lockForUpdate() on InvoiceSequence, single DB::transaction() wraps entire operation, track success/failed in result_data, update InvoiceUpload status to completed/failed

### Phase 4: PDF Generation & Email Delivery
**Rationale:** Delivery layer comes after creation proven. Reuses existing PDF/email infrastructure with minimal changes.
**Delivers:** PDF generation for bulk-created invoices, email to accountant and agent with PDF attachments, email queueing to prevent flood
**Addresses (from FEATURES.md):** Invoice PDF generation, email to accountant + agent
**Uses (from STACK.md):** barryvdh/laravel-dompdf (already integrated), Laravel Mailable + Queue
**Implements (from ARCHITECTURE.md):** Pdf::loadView() in job, Mail::to([$accountant, $agent])->queue(new InvoiceMail($invoice))
**Technical notes:** Generate PDFs in CreateBulkInvoicesJob after invoice creation, store to storage/app/invoices/{company_id}/{invoice_number}.pdf, queue emails (don't send synchronously), consider single summary email instead of 50 individual emails

### Phase Ordering Rationale

- **Foundation before UI:** Can't build preview page without validation logic and preview_data structure (InvoiceTasksImport must exist first)
- **Validation before creation:** Must prove validation catches errors before allowing actual database writes (prevent bad data commits)
- **Creation before delivery:** Can't generate PDFs or send emails without invoices existing in database
- **Addresses dependencies from ARCHITECTURE.md:** Import layer (Phase 1) → Service layer (Phase 1-2) → Controller layer (Phase 2) → Queue job (Phase 3) → Delivery (Phase 4) matches suggested build order lines 563-650
- **Mitigates pitfalls progressively:** Phase 1 prevents input-level issues (CSV injection, memory, ambiguity), Phase 3 prevents creation-level issues (race conditions, transactions, timeouts)
- **Each phase independently testable:** Phase 1 = unit test validation, Phase 2 = feature test HTTP flow, Phase 3 = queue test with fake invoices, Phase 4 = mail fake assertions

### Research Flags

**Phases likely needing deeper research during planning:**
- **Phase 3 (Background Creation):** Accounting integration complexity — Current research identifies journal entry creation as potential bottleneck (4,000 queries for 200 invoices), but optimal solution (queue separately vs bulk insert vs optimize queries) requires deeper dive into existing accounting code patterns. May need `/gsd:research-phase` to analyze InvoiceController::addJournalEntry() method and GeneralLedger update patterns.

**Phases with standard patterns (skip research-phase):**
- **Phase 1 (Validation):** Well-documented Laravel-Excel patterns — WithValidation, SkipsOnFailure, WithChunkReading all have official documentation and proven examples in codebase (TasksImport, ClientsImport)
- **Phase 2 (UI & Preview):** Standard Laravel controller patterns — Form request validation, Blade views, JSON responses for preview are core Laravel features with extensive documentation
- **Phase 4 (Delivery):** Existing infrastructure reuse — PDF generation and email delivery already working in InvoiceController, zero new patterns needed

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All libraries verified in composer.json with version numbers, Laravel 11 compatibility confirmed via official Packagist updates (maatwebsite/excel updated 2026-02-10, barryvdh/dompdf v3.1.0 supports illuminate/support ^11). Existing integration points verified in codebase (InvoiceController line 41 uses DomPDF, TasksImport uses maatwebsite/excel). |
| Features | MEDIUM | Industry patterns confirmed from multiple sources (QuickBooks, NetSuite, Zoho, Malaysia e-Invoice systems), but specific feature priorities (preview before commit, error reporting) based on general best practices not domain-specific research. Table stakes vs differentiators validated across 10+ sources. |
| Architecture | HIGH | Recommended architecture based on existing codebase analysis (InvoiceController lines 1171-1290 for invoice creation pattern, TasksImport for import pattern). Preview-approve workflow validated across multiple enterprise systems. Component responsibilities and integration points match proven Laravel patterns. |
| Pitfalls | HIGH | All 7 critical pitfalls verified with multiple sources: multi-tenant isolation from field-ready guide, race conditions from Laracasts + freek.dev, CSV injection from OWASP, memory issues from GitHub Issues #2166, transaction patterns from Laravel docs. Codebase analysis confirms vulnerabilities exist (no company_id global scope, no lockForUpdate in invoice sequence). |

**Overall confidence:** HIGH

The research is highly confident because it's primarily **integration research**, not greenfield research. All required libraries already installed and working. All architectural patterns already demonstrated in existing code (TasksImport, InvoiceController). Pitfalls verified through both documentation and codebase analysis showing current vulnerabilities. The unknowns are in implementation details (exact query optimization, best journal entry handling), not in fundamental approach.

### Gaps to Address

**Accounting integration optimization:** Research identifies journal entry creation as potential bottleneck (PITFALLS.md lines 382-448) with three suggested solutions (queue separately, bulk inserts, increase timeout), but doesn't definitively recommend which based on existing code patterns. **Resolution:** During Phase 3 planning, analyze InvoiceController::addJournalEntry() implementation (line 1292 referenced), measure actual query count per invoice, benchmark bulk insert vs queued approach with 100-invoice test, then decide. May trigger `/gsd:research-phase accounting-journal-optimization` if complexity high.

**Duplicate invoice number prevention strategy:** Research identifies race condition risk (PITFALLS.md lines 42-102) and suggests three solutions (lockForUpdate, Cache::lock, dedicated queue worker), but doesn't benchmark which performs best under concurrent load. **Resolution:** During Phase 3 planning, load test all three approaches with 5 concurrent uploads creating 50 invoices each, measure duplicate occurrence rate and throughput, select most reliable. Default to lockForUpdate() unless performance unacceptable.

**Email delivery strategy for bulk uploads:** Research suggests both "single summary email" and "batch all PDFs" (FEATURES.md line 41, UX PITFALLS line 502), but doesn't specify which accountants prefer. **Resolution:** During Phase 4 planning, validate with product owner/accountant stakeholder: prefer 50 individual emails (filterable by client) or single email with 50 PDFs attached (easier overview)? Default to single summary email with download link to avoid inbox flood, but confirm before implementation.

**Client disambiguation UI design:** Research identifies ambiguous phone matching as critical issue (PITFALLS.md lines 299-378) and suggests "disambiguation UI" showing multiple matches, but doesn't detail UX flow. **Resolution:** During Phase 2 planning, design preview page with inline disambiguation (when 2+ clients match, show dropdown/radio select requiring agent to pick correct client before approve button enables). Validate design with actual agents using mockup.

## Sources

### Primary (HIGH confidence)
- [Laravel Excel 3.1 Documentation - Row Validation](https://docs.laravel-excel.com/3.1/imports/validation.html) — WithValidation, SkipsOnFailure, SkipsEmptyRows concerns, prepareForValidation method
- [Laravel 11 Queues Documentation](https://laravel.com/docs/11.x/queues) — Job batching with Bus::batch(), queue drivers, retry mechanisms, failed job handling
- [Laravel 11 Mail Documentation](https://laravel.com/docs/11.x/mail) — Mailables with PDF attachments, queueing emails, attachment API
- [Laravel 11 Validation Documentation](https://laravel.com/docs/11.x/validation) — File validation rules, custom rules, FormRequest validation
- [maatwebsite/excel on Packagist](https://packagist.org/packages/maatwebsite/excel) — Version 3.1.x verified Laravel 11 compatible, updated 2026-02-10
- [barryvdh/laravel-dompdf on GitHub](https://github.com/barryvdh/laravel-dompdf) — Version 3.1.0 release notes confirm illuminate/support ^11 compatibility
- **Codebase analysis** — `/app/Http/Controllers/InvoiceController.php` (invoice creation lines 1171-1290, invoice numbering 366-371, 1281-1283), `/app/Imports/TasksImport.php` (existing import pattern), `/app/Imports/ClientsImport.php` (missing company_id isolation), `/app/Models/InvoiceSequence.php` (sequence generation)

### Secondary (MEDIUM confidence)
- [QuickBooks Online Advanced - Batch Invoicing](https://quickbooks.intuit.com/r/whats-new/batch-invoicing-and-expenses-why-and-when-group-invoices-improve-billing/) — Preview before save, batch selection, 37% faster than individual creation
- [NetSuite Invoice Groups](https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/article_158922715446.html) — Multi-group per customer, review unsaved items, column mapping
- [LHDN MyInvois Portal Batch Upload Guide](https://www.rockbell.com.my/lhdn-updates-myinvois-portal-new-guidelines-for-batch-uploads/) — Date format validation, file size limits (2MB), dropdown lists for enums, double-check before submit
- [UX Case Study: Bulk Upload Feature](https://medium.com/design-bootcamp/ux-case-study-bulk-upload-feature-785803089328) — Error indicator icons, inline editing, summary view for 1000+ errors, download annotated file
- [How to Design Bulk Import UX](https://smart-interface-design-patterns.com/articles/bulk-ux/) — Staged approach (upload → validate → preview → approve), shared review between stakeholders
- [Field-Ready Complete Guide: Multi-Tenant SaaS in Laravel](https://blog.greeden.me/en/2025/12/24/field-ready-complete-guide-designing-a-multi-tenant-saas-in-laravel-tenant-isolation-db-schema-row-domain-url-strategy-billing-authorization-auditing-performance-and-an-access/) — company_id scoping patterns, global scopes, tenant isolation
- [Laravel Queue Design Guide (Feb 2026)](https://blog.greeden.me/en/2026/02/11/field-proven-complete-guide-laravel-queue-design-and-async-processing-jobs-queues-horizon-retries-idempotency-delays-priorities-failure-isolation-external-api-integrations/) — Job batching, retries, idempotency, failure isolation

### Tertiary (LOW confidence)
- [OWASP CSV Injection](https://owasp.org/www-community/attacks/CSV_Injection) — Formula exploitation prevention, prefix dangerous characters with single quote
- [How generate unique invoice number and avoid race condition | Laracasts](https://laracasts.com/discuss/channels/laravel/how-generate-unique-invoice-number-and-avoid-race-condition) — Community discussion on lockForUpdate() vs Cache::lock()
- [Breaking Laravel's firstOrCreate using race conditions | freek.dev](https://freek.dev/1087-breaking-laravels-firstorcreate-using-race-conditions) — Demonstration of race condition vulnerability in firstOrCreate without locks
- [Memory Issue with Importing Huge Excel File | GitHub Issue #2166](https://github.com/Maatwebsite/Laravel-Excel/issues/2166) — Community reports of memory exhaustion, WithChunkReading solution
- [8 Tips Best Practice for Uploading Excel Data in Laravel | Medium](https://medium.com/@developerawam/8-tips-best-practice-for-uploading-excel-data-in-laravel-85050452ad42) — Community best practices for chunk reading, validation, error handling

---
*Research completed: 2026-02-12*
*Ready for roadmap: yes*
