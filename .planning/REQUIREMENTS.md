# Requirements: Soud Laravel - Bulk Invoice Upload

**Defined:** 2026-02-12
**Milestone:** v1.0
**Core Value:** Agents can invoice clients accurately from any source (AIR files, PDFs, Excel uploads) with automated payment tracking and accounting integration.

## v1 Requirements

Requirements for initial release. Each maps to roadmap phases.

### Excel Upload & Validation

- [ ] **UPLOAD-01**: Agent can download Excel template with company's client list
- [ ] **UPLOAD-02**: Agent can upload filled Excel file (task_id, client_id, invoice_date, currency, notes)
- [ ] **UPLOAD-03**: System validates file headers match expected columns before processing
- [ ] **UPLOAD-04**: System validates each row for required fields, data types, and enum values
- [ ] **UPLOAD-05**: System shows clear error messages with row numbers and field names
- [ ] **UPLOAD-06**: Agent can download error report as Excel file for large uploads

### Client & Data Matching

- [ ] **MATCH-01**: System matches clients by (company_id, phone) combination
- [ ] **MATCH-02**: System validates tasks exist and belong to agent's company
- [ ] **MATCH-03**: System validates tasks are not already invoiced
- [ ] **MATCH-04**: System validates suppliers exist in database
- [ ] **MATCH-05**: System flags unknown clients for manual review queue

### Invoice Processing

- [ ] **INVOICE-01**: System automatically groups tasks by (client_id, invoice_date)
- [ ] **INVOICE-02**: Agent can preview grouped invoices before creation
- [ ] **INVOICE-03**: Agent can approve all invoices or reject entire upload
- [ ] **INVOICE-04**: System creates all approved invoices in single database transaction
- [x] **INVOICE-05**: System generates PDF for each created invoice
- [ ] **INVOICE-06**: System uses existing invoice_sequence table to prevent duplicate invoice numbers

### Delivery & Communication

- [x] **DELIVER-01**: System emails generated invoice PDFs to company accountant
- [x] **DELIVER-02**: System emails generated invoice PDFs to uploading agent
- [ ] **DELIVER-03**: Agent sees success page with summary of created invoices and download links

### History & Audit

- [ ] **AUDIT-01**: System tracks upload history (filename, upload date, agent, company)
- [ ] **AUDIT-02**: System links created invoices to original upload record
- [ ] **AUDIT-03**: System logs validation errors for audit trail
- [ ] **AUDIT-04**: System stores uploaded Excel file for reference

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Advanced Matching

- **MATCH-06**: System performs fuzzy matching on client names if phone not found
- **MATCH-07**: System suggests similar clients for manual selection
- **MATCH-08**: System detects duplicate rows before invoice creation

### Enhanced UX

- **UPLOAD-07**: Agent sees real-time progress indicator during upload processing
- **UPLOAD-08**: Agent can edit validation errors inline before re-validating
- **INVOICE-07**: Agent can choose custom grouping rules (per client, supplier, task type)
- **INVOICE-08**: Agent can undo uploaded invoices within 5-minute window

### Async Processing

- **UPLOAD-09**: System processes large files (500+ rows) in background job queue
- **DELIVER-04**: System sends upload completion notification when background job finishes

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Auto-create clients from Excel | Requires manual review to ensure data quality (mobile + name not enough) - creates duplicates with typos |
| Modify existing invoices via Excel | Too risky - overwrites payments, accounting entries, breaks audit trail |
| Real-time Excel editing in browser | Massive complexity, reinventing Excel, not aligned with Maatwebsite/Laravel-Excel pattern |
| Multi-currency in Excel upload | Complex exchange rate handling - use company default currency, manual adjustment via UI if needed |
| WhatsApp notifications on upload | Spam clients before invoices reviewed - prefer manual follow-up after review |
| Smart data inference (AI guessing) | Unreliable for bulk operations - AI is for document extraction, not bulk upload |
| Merge with existing draft invoices | Complex state management - upload creates new invoices only |
| Conditional grouping rules | Too complex for Excel upload - simple rule: one invoice per client per date |
| Rollback entire upload | If any invoice paid/emailed, can't safely rollback - preview prevents need |
| Partial commit on errors | Confusing UX - all-or-nothing approach is clearer and safer |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| UPLOAD-01 | Phase 1 | Pending |
| UPLOAD-02 | Phase 1 | Pending |
| UPLOAD-03 | Phase 1 | Pending |
| UPLOAD-04 | Phase 1 | Pending |
| UPLOAD-05 | Phase 1 | Pending |
| UPLOAD-06 | Phase 1 | Pending |
| MATCH-01 | Phase 1 | Pending |
| MATCH-02 | Phase 1 | Pending |
| MATCH-03 | Phase 1 | Pending |
| MATCH-04 | Phase 1 | Pending |
| MATCH-05 | Phase 1 | Pending |
| INVOICE-01 | Phase 2 | Pending |
| INVOICE-02 | Phase 2 | Pending |
| INVOICE-03 | Phase 2 | Pending |
| INVOICE-04 | Phase 3 | Pending |
| INVOICE-05 | Phase 4 | Complete |
| INVOICE-06 | Phase 3 | Pending |
| DELIVER-01 | Phase 4 | Complete |
| DELIVER-02 | Phase 4 | Complete |
| DELIVER-03 | Phase 2 | Pending |
| AUDIT-01 | Phase 1 | Pending |
| AUDIT-02 | Phase 2 | Pending |
| AUDIT-03 | Phase 3 | Pending |
| AUDIT-04 | Phase 1 | Pending |

**Coverage:**
- v1 requirements: 23 total
- Mapped to phases: 23 (100% coverage)
- Phase 1: 13 requirements
- Phase 2: 5 requirements
- Phase 3: 3 requirements
- Phase 4: 3 requirements

---
*Requirements defined: 2026-02-12*
*Last updated: 2026-02-12 after roadmap creation*
