---
phase: 02-ui-preview-workflow
plan: 01
subsystem: bulk-invoice-preview
tags: [ui, preview, blade, multi-tenant, invoice-grouping]
dependency_graph:
  requires: [01-03-file-upload-validation]
  provides: [preview-page, invoice-grouping-logic]
  affects: [bulk-invoice-workflow, agent-experience]
tech_stack:
  added: []
  patterns: [blade-templates, laravel-collections-groupby, eager-loading, multi-tenant-scoping]
key_files:
  created:
    - resources/views/bulk-invoice/preview.blade.php
  modified:
    - app/Http/Controllers/BulkInvoiceController.php
    - routes/web.php
decisions:
  - decision: "Use composite key grouping (client_id + invoice_date) instead of nested groupBy"
    rationale: "Simpler to iterate in Blade, single-level structure easier to display in cards"
    alternatives: ["Multi-level nested groupBy with [client_id][date] structure"]
  - decision: "Redirect upload() to preview page instead of JSON response"
    rationale: "Better UX flow, agent sees validation results immediately in visual form"
    alternatives: ["Keep JSON response, add separate preview navigation"]
  - decision: "Disabled action buttons in preview for Plan 02-02"
    rationale: "Clean separation of concerns, prevents incomplete functionality"
    alternatives: ["Remove buttons entirely, add in Plan 02-02"]
metrics:
  duration_minutes: 2
  tasks_completed: 2
  files_created: 1
  files_modified: 2
  commits: 2
  lines_added: 217
  completed_date: 2026-02-13
---

# Phase 02 Plan 01: Preview Page with Invoice Grouping Summary

**One-liner:** Preview page displays validated upload with tasks grouped by client+date into invoice cards, flagged rows shown separately, and action buttons for approval workflow.

## What Was Built

### Task 1: Controller and Route (Commit a2f7b41b)
Added `preview()` method to `BulkInvoiceController` that:
- Loads `BulkUpload` scoped by `company_id` AND `status='validated'` (multi-tenant isolation)
- Eager loads `rows.client` and `rows.supplier` relationships (prevents N+1 queries)
- Separates valid rows from flagged rows using `where('status', 'valid')` and `where('status', 'flagged')`
- Groups valid rows by composite key `"{$clientId}_{$invoiceDate}"` using Laravel Collections `groupBy()`
- Counts unique clients with `pluck('client_id')->unique()->count()`
- Passes data to Blade view: `bulkUpload`, `invoiceGroups`, `flaggedRows`, `clientCount`

Updated `upload()` method to redirect to preview page on successful validation instead of returning JSON response.

Registered `GET /bulk-invoices/{id}/preview` route with auth middleware.

### Task 2: Preview Blade Template (Commit 182f97f9)
Created `resources/views/bulk-invoice/preview.blade.php` with:

**Section 1: Upload Summary Banner (blue background)**
- Filename, total rows, valid rows, flagged rows
- "X invoices for Y clients" headline
- Download error report link if `error_rows > 0`

**Section 2: Invoice Group Cards**
- One card per invoice group (client + date combination)
- Card header: client name, phone, task count, invoice date
- Task details table inside each card with columns:
  - Row #, Task ID, Task Type, Supplier, Status, Currency, Notes
- Zebra striping on table rows for readability

**Section 3: Flagged Rows Section (yellow background)**
- Only shown if `$flaggedRows->isNotEmpty()`
- Warning text: "These rows have unknown clients and will NOT be included"
- Table showing: Row #, Client Mobile, Task ID, Task Type, Supplier, Flag Reason

**Section 4: Action Buttons (disabled placeholders)**
- "Approve All (X invoices)" button (disabled, will be activated in Plan 02-02)
- "Reject Upload" button (disabled, will be activated in Plan 02-02)
- Comment noting modals will be added in Plan 02-02

Uses `<x-app-layout>` wrapper for consistency with existing codebase.
Flash message display for upload success notification.
Responsive layout with Tailwind CSS utility classes.

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

All verification checks passed:

- ✓ `php artisan route:list --name=bulk-invoices` shows preview route
- ✓ `php -l app/Http/Controllers/BulkInvoiceController.php` returns no syntax errors
- ✓ `./vendor/bin/pint --test app/Http/Controllers/BulkInvoiceController.php` passes
- ✓ `resources/views/bulk-invoice/preview.blade.php` exists with 173 lines (exceeds 80 line minimum)
- ✓ Preview view uses `<x-app-layout>` wrapper matching existing codebase
- ✓ Controller eager-loads `rows.client` and `rows.supplier` (no N+1)
- ✓ Controller scopes by `company_id` (multi-tenant isolation)

## Success Criteria Met

- ✓ Agent can visit `/bulk-invoices/{id}/preview` for a validated upload
- ✓ Preview shows summary banner with invoice and client counts
- ✓ Tasks are visually grouped by client and invoice date in card layout
- ✓ Flagged rows appear in separate yellow section
- ✓ Multi-tenant isolation enforced (company_id scoping)
- ✓ No N+1 queries (eager loading applied)

## Technical Decisions

**1. Composite Key Grouping vs. Nested GroupBy**
- **Chosen:** `groupBy(fn($row) => "{$clientId}_{$invoiceDate}")`
- **Rationale:** Simpler to iterate in Blade with single `@foreach`, easier to debug, flatter structure
- **Alternative:** Multi-level `groupBy([fn($row) => $row->client_id, fn($row) => $row->date])` creates nested array `[client][date][rows]` which requires nested loops in Blade

**2. Redirect to Preview vs. JSON Response**
- **Chosen:** `return redirect()->route('bulk-invoices.preview', $id)->with('message', ...)`
- **Rationale:** Better UX flow, agent sees validation results immediately in visual form, no need for JavaScript redirect handling
- **Alternative:** Keep JSON response from `upload()`, add JavaScript handler to redirect to preview page

**3. Disabled Buttons vs. Remove Buttons**
- **Chosen:** Show disabled buttons with title attributes explaining future functionality
- **Rationale:** Visual feedback for future features, prevents confusion about next steps, cleaner than empty action area
- **Alternative:** Remove buttons entirely, add in Plan 02-02 (but leaves page feeling incomplete)

## What's Next

**Plan 02-02:** Approve/Reject Actions and Success Page
- Enable "Approve All" and "Reject Upload" buttons
- Add Alpine.js confirmation modals
- Create `approve()` and `reject()` controller methods
- Update `BulkUpload.status` to `processing` or `rejected`
- Create success page showing created invoices
- Handle race conditions on approval (prevent double-clicks)

## Self-Check: PASSED

**Files created:**
- ✓ FOUND: resources/views/bulk-invoice/preview.blade.php

**Files modified:**
- ✓ FOUND: app/Http/Controllers/BulkInvoiceController.php
- ✓ FOUND: routes/web.php

**Commits:**
- ✓ FOUND: a2f7b41b (Task 1 - preview method and route)
- ✓ FOUND: 182f97f9 (Task 2 - preview blade template)

All claimed files and commits verified to exist.

## Notes

- Preview page is read-only UI, no state mutations (approval logic in Plan 02-02)
- Collection `groupBy()` with closure is memory-efficient for 50+ row uploads
- Eager loading critical for performance: without it, 50 rows = 100+ queries (N+1 problem)
- Multi-tenant isolation enforced at controller level: agent can only view their company's uploads
- Flagged rows excluded from invoice groups automatically via `where('status', 'valid')`
- Flash messages handled by Laravel session: `->with('message', ...)` and `session('message')`

---
*Summary created: 2026-02-13*
*Execution time: 2 minutes*
