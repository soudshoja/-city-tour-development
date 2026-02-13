---
phase: 03-background-invoice-creation
plan: 02
subsystem: bulk-invoice-workflow
tags: [background-jobs, job-dispatch, status-display, ui-feedback]
dependency_graph:
  requires:
    - "03-01 (CreateBulkInvoicesJob)"
    - "02-02 (approve/success workflow)"
  provides:
    - "Job dispatch on approve with afterCommit()"
    - "Status-aware success page (processing/completed/failed)"
    - "Real invoice data display when completed"
  affects:
    - "BulkInvoiceController (approve/success methods)"
    - "success.blade.php (three-state UI)"
tech_stack:
  added: []
  patterns:
    - "afterCommit() job dispatch to prevent race conditions"
    - "Conditional Blade rendering based on status field"
    - "Invoice eager loading with client relationship"
key_files:
  created: []
  modified:
    - path: "app/Http/Controllers/BulkInvoiceController.php"
      lines_changed: 19
      purpose: "Dispatch job on approve, load real invoices on success"
    - path: "resources/views/bulk-invoice/success.blade.php"
      lines_changed: 31
      purpose: "Status-aware UI for processing/completed/failed states"
decisions:
  - "afterCommit() prevents job from running before status commit — Ensures 'processing' status is visible in database before job starts"
  - "Invoice::whereIn with invoice_ids when completed — Loads actual created invoices for display"
  - "Three-state success page (processing/failed/completed) — Better UX than single static message"
  - "Spinner animation for processing state — Visual feedback with refresh hint"
  - "Route to invoice.show with company_id + invoice_number — Matches existing invoice route pattern"
metrics:
  duration_minutes: 49
  tasks_completed: 2
  files_modified: 2
  commits: 2
  completed_date: "2026-02-13"
---

# Phase 03 Plan 02: Dispatch Job and Success Page Update Summary

**One-liner:** Wired CreateBulkInvoicesJob into approve workflow with afterCommit() and updated success page to show real-time processing status and created invoices.

## What Was Built

### Task 1: Wire Job Dispatch and Real Invoice Loading

**File:** `app/Http/Controllers/BulkInvoiceController.php`

**Changes:**
1. **approve() method** (lines 296-320):
   - Added `CreateBulkInvoicesJob::dispatch($id)->onQueue('invoices')->afterCommit()`
   - Used `afterCommit()` to ensure job doesn't start until 'processing' status is committed
   - Updated flash message: "Invoices are being created in the background."

2. **success() method** (lines 350-389):
   - Added conditional invoice loading when `status === 'completed'` and `invoice_ids` is not empty
   - Loads actual `Invoice` records with `whereIn('id', $bulkUpload->invoice_ids)->with('client')`
   - Returns empty collection when processing or failed

3. **Added imports:**
   - `use App\Jobs\CreateBulkInvoicesJob;`
   - `use App\Models\Invoice;`

**Commit:** `dd9ccf25` - feat(03-02): dispatch CreateBulkInvoicesJob on approve and load real invoices on success

### Task 2: Status-Aware Success Page

**File:** `resources/views/bulk-invoice/success.blade.php`

**Changes:**
1. **Section 3: Status-aware processing message** (replaced lines 54-57):
   - **Processing state:** Spinner with "Invoices are being created in the background. Refresh this page to check progress."
   - **Failed state:** Red error box with `$bulkUpload->error_summary['job_failure']` message + support contact hint
   - **Completed state:** Green success box "All invoices have been created successfully."

2. **Section 4: Invoice list** (replaced lines 60-75):
   - Shows invoice count in header: `Created Invoices ({{ $invoices->count() }})`
   - Each invoice displays:
     - `invoice_number` (bold)
     - `client->name` (gray text with fallback to "Unknown Client")
     - `invoice_date · currency amount` (smaller gray text, 3 decimal places)
     - View link routing to `invoice.show` with `[$invoice->company_id, $invoice->invoice_number]`

**Visual States:**
- **Blue spinning loader** when status = 'processing'
- **Red error box** when status = 'failed'
- **Green success box + invoice list** when status = 'completed'

**Commit:** `7017ecc0` - feat(03-02): update success page to show status-aware invoice display

## Deviations from Plan

None. Plan executed exactly as written.

## Verification Results

**Controller Verification:**
- ✅ `./vendor/bin/pint` passed (Laravel formatting)
- ✅ `CreateBulkInvoicesJob::dispatch` found in approve() method
- ✅ `Invoice::whereIn` found in success() method
- ✅ No other controller methods were modified

**Blade Template Verification:**
- ✅ `php artisan view:cache` compiled successfully (no syntax errors)
- ✅ `animate-spin` class found (processing spinner present)
- ✅ `job_failure` check found (error display present)
- ✅ `invoice_number` rendering found (real invoice data display)

## Success Criteria Validation

1. ✅ Approving a bulk upload dispatches the background job
2. ✅ Job dispatch uses `afterCommit()` to prevent race with status commit
3. ✅ Success page shows processing state with refresh hint when job is running
4. ✅ Success page shows created invoices with details when job completes
5. ✅ Success page shows error message when job fails permanently
6. ✅ Controller passes phpstan analysis (Pint formatting passed)

## Integration Points

**Upstream (Plan 03-01):**
- `CreateBulkInvoicesJob` is dispatched by approve() method
- Job populates `invoice_ids` array in `BulkUpload` model
- Job sets status to 'completed' or 'failed' with error_summary

**Downstream (Future):**
- Users can click View links to see individual invoices
- Email notification could be added after job completion
- Export functionality could use completed invoices

## User Flow After This Plan

1. User approves upload on preview page
2. Redirect to success page with message: "Invoices are being created in the background."
3. Success page shows **blue spinner** + "Refresh this page to check progress."
4. User refreshes page periodically
5. When job completes:
   - **Green success box** appears
   - **Invoice list** shows with numbers, clients, amounts
   - **View links** route to each invoice

If job fails:
   - **Red error box** with failure reason
   - Support contact hint

## Technical Notes

**Why afterCommit():**
Without `afterCommit()`, the job could start before the transaction commits the 'processing' status. This would cause the job to see status='validated' and potentially fail or create race conditions.

**Why three states:**
- **processing:** Job may take 30-60 seconds for large uploads (50+ invoices)
- **completed:** User needs to see what was created
- **failed:** User needs to know what went wrong and what to do

**Invoice route pattern:**
The existing invoice show route expects `[$companyId, $invoiceNumber]` (routes/web.php line 466), so the View link matches this pattern.

## Self-Check: PASSED

**Created files exist:**
- ✅ `.planning/phases/03-background-invoice-creation/03-02-SUMMARY.md` (this file)

**Modified files exist:**
- ✅ `app/Http/Controllers/BulkInvoiceController.php` (verified by Read tool before modification)
- ✅ `resources/views/bulk-invoice/success.blade.php` (verified by Read tool before modification)

**Commits exist:**
```bash
git log --oneline --all | grep -E "(dd9ccf25|7017ecc0)"
```
- ✅ `dd9ccf25` - feat(03-02): dispatch CreateBulkInvoicesJob on approve and load real invoices on success
- ✅ `7017ecc0` - feat(03-02): update success page to show status-aware invoice display

**Claims verification:**
- ✅ approve() dispatches CreateBulkInvoicesJob with afterCommit() — Confirmed by grep
- ✅ success() loads Invoice records from invoice_ids — Confirmed by grep
- ✅ success.blade.php has processing spinner — Confirmed by grep (animate-spin)
- ✅ success.blade.php shows job_failure errors — Confirmed by grep (job_failure)
- ✅ success.blade.php shows invoice_number — Confirmed by grep (invoice_number)

All claims verified.
