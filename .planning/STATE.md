# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-12)

**Core value:** Agents can invoice clients accurately from any source (AIR files, PDFs, Excel uploads) with automated payment tracking and accounting integration.
**Current focus:** Phase 2 - UI & Preview Workflow

## Current Position

Phase: Not started (defining requirements)
Plan: —
Status: Defining requirements for DOTW v1.0 B2B
Last activity: 2026-02-21 — Started milestone DOTW v1.0 B2B

Progress: ○ Gathering requirements

## Performance Metrics

**Velocity:**
- Total plans completed: 9
- Average duration: 8.0 minutes
- Total execution time: 1.2 hours

**By Phase:**

| Phase | Plans | Total Time | Avg/Plan |
|-------|-------|------------|----------|
| 01-data-foundation-validation | 3 | 16 min | 5.3 min |
| 02-ui-preview-workflow | 2 | 3 min | 1.5 min |
| 03-background-invoice-creation | 2 | 51 min | 25.5 min |
| 04-pdf-generation-email-delivery | 2 | 3 min | 1.5 min |

**Recent Plans:**

| Phase-Plan | Duration | Tasks | Files | Completed |
|------------|----------|-------|-------|-----------|
| 04-02 | 2 min | 2 | 2 | 2026-02-13 |
| 04-01 | 1 min | 2 | 3 | 2026-02-13 |
| 03-02 | 49 min | 2 | 2 | 2026-02-13 |
| 03-01 | 2 min | 2 | 3 | 2026-02-13 |
| 02-02 | 1 min | 3 | 4 | 2026-02-13 |

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting current work:

- One invoice per client (not per row) — Matches existing manual invoice creation pattern
- Flag unknown clients instead of auto-create — Prevents duplicate/incorrect client creation
- Full validation before preview — Fail fast with clear errors, better UX than partial imports
- Email to accountant + agent (not WhatsApp) — Professional invoice delivery
- Leverage existing InvoiceController logic — Reuse proven invoice creation

**From Plan 01-01:**
- Multi-sheet Excel export pattern — Separate template from client reference for better UX
- Soft deletes on bulk_uploads only — Parent audit trail needed, rows cascade with parent
- Cascade delete strategy — bulk_uploads → rows CASCADE, tasks/clients/suppliers → rows SET NULL for audit

**From Plan 01-02:**
- PostgreSQL fallback for testing — MySQL not running, PostgreSQL driver available, test infrastructure more resilient
- Unknown client flagging confirmed — Matches PROJECT.md decision, flags with 'unknown_client' not error
- Case-insensitive supplier lookup — Handles user input variation in capitalization

**From Plan 01-03:**
- Excel::toArray() pattern for parsing — Simpler than ToCollection for validation-first workflow
- Bulk insert BulkUploadRow records — Performance optimization for 50+ row uploads
- Multi-tenant file storage — Files stored at bulk-uploads/{company_id}/ for isolation
- Fail fast on header validation — Return 422 immediately, don't process rows if headers wrong

**From Plan 02-01:**
- Composite key grouping over nested groupBy — Simpler Blade iteration, flatter structure
- Redirect to preview vs JSON response — Better UX flow, immediate visual feedback
- Disabled buttons for future features — Visual feedback without incomplete functionality

**From Plan 02-02:**
- Conditional update with status guard prevents race conditions — WHERE status='validated' prevents double-click, concurrent requests
- Alpine.js modals over separate confirmation pages — Better UX, keeps context visible, ESC key support
- Empty invoices collection on success page — Phase 3 creates actual Invoice records
- Reject redirects to dashboard — Flash message sufficient, no dedicated reject page needed

**From Plan 03-01:**
- lockForUpdate on InvoiceSequence instead of no locking — Prevents race conditions when multiple jobs generate invoice numbers concurrently
- Duplicate task check throws exception causing full rollback — Ensures atomicity - if ANY task is already invoiced, NO invoices are created from this upload
- Migration execution deferred to production deployment — No local database server available, migrations will run during deployment after all phases complete
- Verification uses syntax/static checks only — PHP linting and Pint formatting instead of database-dependent tinker/migrate checks
- [Phase 03]: afterCommit() prevents job from running before status commit — Ensures 'processing' status is visible in database before job starts
- [Phase 03]: Three-state success page (processing/failed/completed) — Better UX than single static message
- [Phase 04-01]: Serialize only bulkUploadId (not Eloquent model) in job constructor - prevents serialization issues in queue
- [Phase 04-01]: No ShouldQueue on Mailable class - SendInvoiceEmailsJob handles queueing for clear separation
- [Phase 04-01]: Email failure does not update BulkUpload status - invoices already created, email is just notification
- [Phase 04-02]: Dispatch email job AFTER transaction (not inside) - PDF generation is CPU-intensive and must not hold DB locks
- [Phase 04-02]: Separate 'emails' queue for email workers - allows independent scaling from invoice creation workers

### Pending Todos

None yet.

### Blockers/Concerns

None yet.

## Session Continuity

Last session: 2026-02-13
Stopped at: Completed 04-02-PLAN.md (Integration with CreateBulkInvoicesJob)
Resume file: .planning/phases/04-pdf-generation-email-delivery/04-02-SUMMARY.md
Next: All phases complete - ready for production deployment
