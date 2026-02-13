# Roadmap: Soud Laravel - Bulk Invoice Upload

## Overview

This roadmap delivers bulk invoice creation from Excel uploads for travel agencies. Agents upload Excel files with task lists, the system validates data and groups tasks by client, presents a preview for approval, then creates invoices with automated PDF generation and email delivery. The implementation leverages existing Laravel-Excel patterns and accounting infrastructure, focusing on validation quality and multi-tenant data isolation.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3, 4): Planned milestone work
- Decimal phases (X.Y): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [ ] **Phase 1: Data Foundation & Validation** - Excel parsing, validation, client matching, error reporting
- [x] **Phase 2: UI & Preview Workflow** - Upload interface, preview display, approval actions
- [x] **Phase 3: Background Invoice Creation** - Queue job, transaction handling, invoice number generation
- [x] **Phase 4: PDF Generation & Email Delivery** - PDF creation, email distribution to accountant and agent

## Phase Details

### Phase 1: Data Foundation & Validation
**Goal**: System accurately validates Excel uploads and identifies data quality issues before any database changes
**Depends on**: Nothing (first phase)
**Requirements**: UPLOAD-01, UPLOAD-02, UPLOAD-03, UPLOAD-04, UPLOAD-05, UPLOAD-06, MATCH-01, MATCH-02, MATCH-03, MATCH-04, MATCH-05, AUDIT-01, AUDIT-04
**Success Criteria** (what must be TRUE):
  1. Agent downloads Excel template pre-filled with their company's client list
  2. System rejects invalid Excel files with clear error messages showing row numbers and field names
  3. System identifies unknown clients and flags them for manual review without blocking upload
  4. Agent downloads error report as Excel file showing all validation failures
  5. Upload session is tracked with filename, date, agent, and stored file for audit
**Plans**: 4 plans in 3 waves

Plans:
- [ ] 01-01-PLAN.md -- Database foundation (migrations, models, template export, controller skeleton, route)
- [ ] 01-02-PLAN.md -- Validation service with TDD (BulkUploadValidationService: headers, rows, clients, tasks, suppliers)
- [ ] 01-03-PLAN.md -- Upload endpoint + Excel parsing + validation orchestration (file upload, parse, validate, store)
- [ ] 01-04-PLAN.md -- Error report Excel export (downloadable validation error report with color coding)

### Phase 2: UI & Preview Workflow
**Goal**: Agent sees exactly what invoices will be created before committing to database
**Depends on**: Phase 1
**Requirements**: INVOICE-01, INVOICE-02, INVOICE-03, DELIVER-03, AUDIT-02
**Success Criteria** (what must be TRUE):
  1. Agent sees preview showing "X invoices for Y clients" summary with task grouping
  2. Agent can approve all invoices or reject entire upload from preview page
  3. Preview clearly shows which tasks belong to which invoice grouped by client and date
  4. Agent sees success page after approval with invoice summary and download links
**Plans**: 2 plans in 2 waves

Plans:
- [ ] 02-01-PLAN.md -- Preview page with invoice grouping (controller preview method, route, Blade template with grouped invoice cards and flagged rows)
- [ ] 02-02-PLAN.md -- Approve/reject actions and success page (controller approve/reject/success methods, Alpine.js modals, success page with summary)

### Phase 3: Background Invoice Creation
**Goal**: System creates all approved invoices atomically without race conditions or duplicate invoice numbers
**Depends on**: Phase 2
**Requirements**: INVOICE-04, INVOICE-06, AUDIT-03
**Success Criteria** (what must be TRUE):
  1. All approved invoices create in single database transaction (all succeed or all fail)
  2. Invoice numbers generate without duplicates even under concurrent uploads
  3. System prevents tasks from being invoiced twice across multiple uploads
  4. Failed invoice creations log detailed error information for debugging
  5. Upload record links to all created invoice IDs for audit trail
**Plans**: 2 plans in 2 waves

Plans:
- [ ] 03-01-PLAN.md -- Migration, model update, and CreateBulkInvoicesJob queue job (atomic transaction, lockForUpdate sequence, duplicate task guard)
- [ ] 03-02-PLAN.md -- Controller integration and success page update (dispatch job from approve, show real invoices on success page)

### Phase 4: PDF Generation & Email Delivery
**Goal**: Created invoices automatically deliver to accountant and uploading agent as PDF attachments
**Depends on**: Phase 3
**Requirements**: INVOICE-05, DELIVER-01, DELIVER-02
**Success Criteria** (what must be TRUE):
  1. PDF generates for each created invoice using existing invoice template
  2. Company accountant receives email with all invoice PDFs attached
  3. Uploading agent receives email copy with all invoice PDFs attached
  4. Emails queue for delivery to prevent blocking invoice creation
**Plans**: 2 plans in 2 waves

Plans:
- [ ] 04-01-PLAN.md -- Email infrastructure (BulkInvoicesMail mailable with PDF attachments, email Blade template, SendInvoiceEmailsJob queue job)
- [ ] 04-02-PLAN.md -- Wiring and success page (dispatch email job from CreateBulkInvoicesJob, add PDF download links to success page)

## Progress

**Execution Order:**
Phases execute in numeric order: 1 -> 2 -> 3 -> 4

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Data Foundation & Validation | 0/4 | Not started | - |
| 2. UI & Preview Workflow | 2/2 | Complete | 2026-02-13 |
| 3. Background Invoice Creation | 2/2 | Complete | 2026-02-13 |
| 4. PDF Generation & Email Delivery | 2/2 | Complete | 2026-02-13 |

---
*Roadmap created: 2026-02-12*
*Last updated: 2026-02-13 after Phase 4 execution complete*
